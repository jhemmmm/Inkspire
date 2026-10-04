<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterJobOrdersRequest;
use App\Models\JobOrder;
use App\Support\AuditLogger;
use App\Support\BusinessTime;
use App\Support\TableExport;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobOrderController extends Controller
{
    private const PDF_ROW_CAP = 1000;

    /**
     * Show the read-only, filterable Admin Job Orders list — the only
     * place an Admin can see every job order's price across every status,
     * without going through the Cashier/Frontline/Artist portals.
     */
    public function index(FilterJobOrdersRequest $request): Response
    {
        $jobOrders = $this->filteredQuery($request)
            ->orderByDesc('is_rush')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $jobOrders->getCollection()->each(fn (JobOrder $jobOrder) => $jobOrder->append(['display_total', 'display_status']));

        return Inertia::render('admin/JobOrders', [
            'jobOrders' => $jobOrders,
            'filters' => $request->only(['q', 'status', 'payment_status', 'from', 'to']),
            // Bare status values — the frontend already has a shared
            // jobOrderStatusLabel() helper (lib/jobOrders.ts) that every
            // other portal's job order table uses, so labels live there
            // rather than being duplicated on this controller too.
            'statuses' => FilterJobOrdersRequest::statuses(),
            'paymentStatuses' => collect(PaymentStatus::cases())->map(fn (PaymentStatus $status): string => $status->value)->all(),
        ]);
    }

    /**
     * Export the currently filtered Job Orders list to a real,
     * downloadable PDF, capped at PDF_ROW_CAP rows. The D-02/D-07 audit
     * write happens before any row is built or any byte streams.
     */
    public function exportPdf(FilterJobOrdersRequest $request): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        $from = $this->resolveFrom($request);
        $to = $this->resolveTo($request);

        AuditLogger::recordReportExport($user, 'job-orders', 'pdf', $from, $to);

        $query = $this->filteredQuery($request)->latest('id');
        $matched = $query->count();
        $jobOrders = $query->limit(self::PDF_ROW_CAP)->get();

        $meta = ['generatedAt' => BusinessTime::now(), 'generatedBy' => $user->name];

        if ($from !== null && $to !== null) {
            $meta['from'] = $from;
            $meta['to'] = $to;
        }

        // ponytail: a 1,000-row PDF cap keeps dompdf's render time bounded.
        // If the shop ever needs the full list in PDF past this ceiling,
        // move this export to a queued job instead of raising the cap.
        if ($matched > self::PDF_ROW_CAP) {
            $meta['note'] = 'Showing '.number_format(self::PDF_ROW_CAP).' of '.number_format($matched).' — narrow the filters or use Excel.';
        }

        $built = $this->buildTableRows($jobOrders);

        return TableExport::pdf('Job Orders', $this->exportColumns(), $built['rows'], $this->moneyColumnIndexes(), $meta, $built['totalRow'], 'job-orders_'.BusinessTime::now()->toDateString(), landscape: true);
    }

    /**
     * Export the currently filtered Job Orders list to a real, streamed
     * .xlsx. Uncapped, unlike exportPdf(). Same audit-before-output
     * ordering as exportPdf().
     */
    public function exportXlsx(FilterJobOrdersRequest $request): StreamedResponse
    {
        $user = $request->user();
        $from = $this->resolveFrom($request);
        $to = $this->resolveTo($request);

        AuditLogger::recordReportExport($user, 'job-orders', 'xlsx', $from, $to);

        $jobOrders = $this->filteredQuery($request)->latest('id')->get();
        $built = $this->buildTableRows($jobOrders);

        return TableExport::xlsx('Job Orders', $this->exportColumns(), $built['rows'], $this->moneyColumnIndexes(), null, $built['totalRow'], 'job-orders_'.BusinessTime::now()->toDateString());
    }

    /**
     * The filtered base query both index() and the two export methods
     * build on — same filters apply to the page and to whatever it
     * exports.
     *
     * @return Builder<JobOrder>
     */
    private function filteredQuery(FilterJobOrdersRequest $request): Builder
    {
        return JobOrder::query()
            ->whereNull('cancelled_at')
            ->when($request->filled('q'), fn (Builder $query) => $query->search((string) $request->string('q')))
            ->when($request->filled('status'), fn (Builder $query) => $query->whereDisplayStatus((string) $request->string('status')))
            ->when($request->filled('payment_status'), fn (Builder $query) => $query->where('payment_status', $request->string('payment_status')))
            ->when($this->resolveFrom($request), fn (Builder $query, CarbonInterface $from) => $query->where('created_at', '>=', BusinessTime::utcStartOfDay($from)))
            ->when($this->resolveTo($request), fn (Builder $query, CarbonInterface $to) => $query->where('created_at', '<=', BusinessTime::utcEndOfDay($to)))
            ->with([
                'queueEntry:id,customer_id',
                'queueEntry.customer:id,name',
                'pricingEntry:id,name',
            ])
            ->select(['id', 'number', 'description', 'width_ft', 'height_ft', 'quantity', 'pricing_entry_id', 'queue_entry_id', 'status', 'payment_status', 'total_amount', 'quoted_amount', 'is_rush', 'released_at', 'cancelled_at', 'created_at'])
            ->withAmountPaid();
    }

    /**
     * Full timestamp lower bound against created_at — never whereDate(),
     * which truncates to a bare date and misses same-day records on
     * SQLite's string-stored datetimes.
     *
     * The day is the shop's Asia/Manila day, not a UTC one: timestamps are
     * stored in UTC, so a UTC bound would file an order taken before 8 AM
     * under the previous date. filteredQuery() converts the bound to UTC
     * for the comparison; the Manila instance is kept for display.
     */
    private function resolveFrom(FilterJobOrdersRequest $request): ?CarbonInterface
    {
        return $request->filled('from') ? $request->date('from', null, BusinessTime::zone())->startOfDay() : null;
    }

    /**
     * Full timestamp upper bound against created_at — see resolveFrom().
     */
    private function resolveTo(FilterJobOrdersRequest $request): ?CarbonInterface
    {
        return $request->filled('to') ? $request->date('to', null, BusinessTime::zone())->endOfDay() : null;
    }

    /** @return list<string> */
    private function exportColumns(): array
    {
        return ['Job Order', 'Customer', 'Product', 'Size', 'Status', 'Payment', 'Urgency', 'Total', 'Paid', 'Balance', 'Created'];
    }

    /** @return list<int> */
    private function moneyColumnIndexes(): array
    {
        return [7, 8, 9]; // Total, Paid, Balance
    }

    /**
     * @param  Collection<int, JobOrder>  $jobOrders
     * @return array{rows: list<list<mixed>>, totalRow: list<mixed>}
     */
    private function buildTableRows(Collection $jobOrders): array
    {
        $moneyIndexes = $this->moneyColumnIndexes();
        $totals = array_fill_keys($moneyIndexes, 0.0);

        $rows = [];

        foreach ($jobOrders as $jobOrder) {
            $values = $this->exportRowValues($jobOrder);
            $rows[] = $values;

            foreach ($moneyIndexes as $index) {
                $totals[$index] += (float) ($values[$index] ?? 0);
            }
        }

        $totalRow = array_map(
            fn (int $index): mixed => match (true) {
                $index === 0 => 'Total',
                in_array($index, $moneyIndexes, true) => $totals[$index],
                default => null,
            },
            array_keys($this->exportColumns())
        );

        return ['rows' => $rows, 'totalRow' => $totalRow];
    }

    /** @return list<mixed> */
    private function exportRowValues(JobOrder $jobOrder): array
    {
        $paid = (float) ($jobOrder->amount_paid ?? 0);
        $total = $jobOrder->display_total; // accessor works without ->append()

        return [
            $jobOrder->number ?? '—',
            $this->customerName($jobOrder),
            $jobOrder->pricingEntry === null ? $jobOrder->description : $jobOrder->pricingEntry->name,
            $this->sizeLabel($jobOrder),
            Str::headline($jobOrder->display_status),
            Str::headline($jobOrder->payment_status->value),
            $jobOrder->is_rush ? 'Rush' : 'Normal',
            $total,
            $paid,
            $total === null ? null : max(0.0, $total - $paid),
            $jobOrder->created_at === null ? null : BusinessTime::local($jobOrder->created_at),
        ];
    }

    /**
     * `=== null` checks rather than a `?->...?->... ?? '—'` nullsafe chain
     * -- Larastan types a BelongsTo relation's magic property as always
     * present (never null), so a nullsafe fetch immediately coalesced is
     * flagged as dead code even though the relation can genuinely be
     * unloaded/absent at runtime. Mirrors
     * FrontlineStaff\JobOrderController::show()'s identical `=== null ?`
     * guard shape for the same relation chain.
     */
    private function customerName(JobOrder $jobOrder): string
    {
        if ($jobOrder->queueEntry === null || $jobOrder->queueEntry->customer === null) {
            return '—';
        }

        return $jobOrder->queueEntry->customer->name;
    }

    private function sizeLabel(JobOrder $jobOrder): string
    {
        if ($jobOrder->width_ft !== null && $jobOrder->height_ft !== null) {
            $quantity = $jobOrder->quantity ?? 1;

            return "{$jobOrder->width_ft}ft × {$jobOrder->height_ft}ft × {$quantity}";
        }

        if ($jobOrder->quantity) {
            return (string) $jobOrder->quantity;
        }

        return '—';
    }
}
