<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterAuditTrailRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TableExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditTrailController extends Controller
{
    private const PDF_ROW_CAP = 1000;

    /**
     * Show the read-only, filterable audit trail for Admin.
     */
    public function index(FilterAuditTrailRequest $request): Response
    {
        $entries = $this->filteredQuery($request)
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/AuditTrail', [
            'entries' => $entries,
            'filters' => $request->only(['user', 'action', 'from', 'to']),
            'users' => User::query()->select(['id', 'name'])->orderBy('name')->get(),
            'actions' => ['created', 'updated', 'deleted', 'login', 'logout', 'failed_login', 'lockout', 'report_exported'],
        ]);
    }

    /**
     * Export the currently filtered Audit Trail to a real, downloadable
     * PDF, capped at PDF_ROW_CAP rows. The D-02/D-07 audit write happens
     * before any row is built or any byte streams -- and, since this write
     * lands in the very table the export then reads and sorts newest-first,
     * an UNFILTERED export legitimately includes its own just-written
     * report_exported row as the first data row (that's exactly why D-06
     * added report_exported to the filterable actions list).
     */
    public function exportPdf(FilterAuditTrailRequest $request): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        $from = $request->filled('from') ? $request->date('from') : null;
        $to = $request->filled('to') ? $request->date('to') : null;

        AuditLogger::recordReportExport($user, 'audit-trail', 'pdf', $from, $to);

        $query = $this->filteredQuery($request)->latest('created_at');
        $matched = $query->count();
        $entries = $query->limit(self::PDF_ROW_CAP)->get();

        $meta = ['generatedAt' => now(), 'generatedBy' => $user->name];

        // ponytail: a 1,000-row PDF cap keeps dompdf's render time bounded.
        // If the shop ever needs the full list in PDF past this ceiling,
        // move this export to a queued job instead of raising the cap.
        if ($matched > self::PDF_ROW_CAP) {
            $meta['note'] = 'Showing '.number_format(self::PDF_ROW_CAP).' of '.number_format($matched).' — narrow the filters or use Excel.';
        }

        return TableExport::pdf('Audit Trail', $this->exportColumns(), $this->exportRows($entries), [], $meta, null, 'audit-trail_'.now()->toDateString(), landscape: true);
    }

    /**
     * Export the currently filtered Audit Trail to a real, streamed .xlsx.
     * Uncapped, unlike exportPdf(). Same audit-before-output ordering.
     */
    public function exportXlsx(FilterAuditTrailRequest $request): StreamedResponse
    {
        $user = $request->user();
        $from = $request->filled('from') ? $request->date('from') : null;
        $to = $request->filled('to') ? $request->date('to') : null;

        AuditLogger::recordReportExport($user, 'audit-trail', 'xlsx', $from, $to);

        $entries = $this->filteredQuery($request)->latest('created_at')->get();

        return TableExport::xlsx('Audit Trail', $this->exportColumns(), $this->exportRows($entries), [], null, null, 'audit-trail_'.now()->toDateString());
    }

    /**
     * The filtered base query both index() and the two export methods
     * build on. `from`/`to` keep their existing whereDate() semantics --
     * an already-shipped filter, unrelated to Job Orders' new full-timestamp
     * bound requirement.
     *
     * @return Builder<AuditLog>
     */
    private function filteredQuery(FilterAuditTrailRequest $request): Builder
    {
        return AuditLog::query()
            ->with('user:id,name,email,role')
            ->when($request->filled('user'), fn (Builder $query) => $query->where('user_id', $request->integer('user')))
            ->when($request->filled('action'), fn (Builder $query) => $query->where('action', $request->string('action')))
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('to')));
    }

    /** @return list<string> */
    private function exportColumns(): array
    {
        return ['When', 'User', 'Role', 'Action', 'Record', 'Changes', 'IP'];
    }

    /**
     * @param  Collection<int, AuditLog>  $entries
     * @return list<list<string>>
     */
    private function exportRows(Collection $entries): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = [
                $entry->created_at?->format('M j, Y g:i A') ?? '—', // string, not Carbon -- TableExport would otherwise strip the time
                $entry->user === null ? '—' : $entry->user->name,
                $entry->user !== null ? Str::headline($entry->user->role->value) : '—',
                Str::headline($entry->action),
                $this->record($entry),
                $this->formatChanges($entry->old_values, $entry->new_values),
                $entry->ip_address ?? '—',
            ];
        }

        return $rows;
    }

    private function record(AuditLog $entry): string
    {
        if ($entry->auditable_type === null) {
            return '—';
        }

        $class = class_basename($entry->auditable_type); // same helper DashboardController::recentActivity() already uses

        return $entry->auditable_id !== null ? "{$class} #{$entry->auditable_id}" : $class;
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function formatChanges(?array $old, ?array $new): string
    {
        if ($old === null && $new === null) {
            return '—'; // auth events and report_exported's old_values side carry nothing
        }

        if ($old !== null && $new !== null) {
            $parts = [];

            foreach ($new as $key => $value) {
                $parts[] = "{$key}: {$this->stringifyValue($old[$key] ?? null)} → {$this->stringifyValue($value)}";
            }

            return implode('; ', $parts); // e.g. "status: intake → assigned; total_amount: 0 → 1500"
        }

        return $this->joinValues($new ?? $old); // 'created' (new only) or 'deleted' (old only)
    }

    /** @param  array<string, mixed>  $values */
    private function joinValues(array $values): string
    {
        $parts = [];

        foreach ($values as $key => $value) {
            $parts[] = "{$key}: {$this->stringifyValue($value)}";
        }

        return implode('; ', $parts);
    }

    private function stringifyValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => (string) json_encode($value),
            default => (string) $value,
        };
    }
}
