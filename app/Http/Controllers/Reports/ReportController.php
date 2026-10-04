<?php

namespace App\Http\Controllers\Reports;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\FilterReportRequest;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportRegistry;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Show the shared Reports page (D-04), rendered to the correct per-role
     * Inertia page. `ReportRegistry::isEntitled()` is the single security
     * boundary (T-08-08) -- it runs before any query is built, independent
     * of which role-scoped route was hit, so a Cashier requesting
     * `?report=expenses` on `cashier.reports.index` still gets 403 even
     * though the route itself is reachable by a Cashier.
     */
    public function index(FilterReportRequest $request, ReportBuilder $reportBuilder): Response
    {
        $user = $request->user();

        [$from, $to] = $request->range();

        $key = (string) ($request->query('report') ?? array_key_first(ReportRegistry::entitledFor($user)));

        abort_unless(ReportRegistry::isEntitled($user, $key), 403);

        if ($key === 'financial-summary') {
            $summary = $reportBuilder->summary($from, $to);
            $all = collect();
            $rows = [];
            $rowsTotal = 0;
            $rowsAmountTotal = null;
        } else {
            $all = $reportBuilder->rows($key, $from, $to, (string) $request->validated('q', ''));
            $rows = $all->take(100)->values();
            $rowsTotal = $all->count();
            $summary = null;
            $rowsAmountTotal = $this->amountTotal($key, $all);
        }

        $component = match ($user->role) {
            UserRole::Admin => 'admin/Reports',
            UserRole::Cashier => 'cashier/Reports',
            UserRole::ProductionStaff => 'production-staff/Reports',
            UserRole::AccountingStaff => 'accounting-staff/Reports',
            default => abort(403),
        };

        return Inertia::render($component, [
            'reports' => ReportRegistry::entitledFor($user),
            'selected' => $key,
            'columns' => ReportRegistry::definitions()[$key]['columns'],
            'rows' => $rows,
            'rowsTotal' => $rowsTotal,
            'rowsAmountTotal' => $rowsAmountTotal,
            'summary' => $summary,
            'chart' => $reportBuilder->chart($key, $all, $from, $to),
            'filters' => [
                'q' => $key === 'financial-summary' ? '' : (string) $request->validated('q', ''),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }

    /**
     * The money total for the two reports that have one figure worth
     * summing. Summed over the FULL row set, not the 100 rows the table
     * renders -- a total that only covered the visible page would be worse
     * than no total at all.
     *
     * Voided expenses are excluded, matching `Expense::active()`, which is
     * what the expense ledger and the financial summary both already count.
     * They stay visible as rows (D-11) but were never money spent.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function amountTotal(string $key, Collection $rows): ?float
    {
        if (! in_array($key, ['sales', 'expenses'], true)) {
            return null;
        }

        return round(
            (float) $rows
                ->reject(fn (array $row): bool => ($row['status'] ?? null) === 'Voided')
                ->sum('amount'),
            2,
        );
    }
}
