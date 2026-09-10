<?php

namespace App\Http\Controllers\Reports;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\FilterReportRequest;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportRegistry;
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

        if ($request->filled('from') && $request->filled('to')) {
            $from = $request->date('from')->startOfDay();
            $to = $request->date('to')->endOfDay();
        } else {
            $from = now()->startOfMonth()->startOfDay();
            $to = now()->endOfDay();
        }

        $key = (string) ($request->query('report') ?? array_key_first(ReportRegistry::entitledFor($user)));

        abort_unless(ReportRegistry::isEntitled($user, $key), 403);

        if ($key === 'financial-summary') {
            $summary = $reportBuilder->summary($from, $to);
            $rows = [];
            $rowsTotal = 0;
        } else {
            $all = $reportBuilder->rows($key, $from, $to);
            $rows = $all->take(100)->values();
            $rowsTotal = $all->count();
            $summary = null;
        }

        $component = match ($user->role) {
            UserRole::Owner => 'owner/Reports',
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
            'summary' => $summary,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }
}
