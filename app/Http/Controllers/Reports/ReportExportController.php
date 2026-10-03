<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\FilterReportRequest;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportRegistry;
use App\Support\AuditLogger;
use App\Support\BusinessTime;
use App\Support\TableExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    /**
     * Export any role-scoped report to a real, downloadable PDF (RPT-05).
     * `ReportRegistry::isEntitled()` is the same entitlement boundary
     * `ReportController::index()` enforces (T-08-13) -- checked before any
     * query is built. The D-02 audit write happens before the PDF stream
     * begins, never after.
     */
    public function exportPdf(FilterReportRequest $request, ReportBuilder $reportBuilder, string $reportKey): Response
    {
        $user = $request->user();

        abort_unless(ReportRegistry::isEntitled($user, $reportKey), 403);

        [$from, $to] = $request->range();

        AuditLogger::recordReportExport($user, $reportKey, 'pdf', $from, $to);

        $title = ReportRegistry::definitions()[$reportKey]['title'];
        $filename = "{$reportKey}_{$from->toDateString()}_{$to->toDateString()}";

        if ($reportKey === 'financial-summary') {
            return Pdf::loadView('reports.financial-summary', [
                'title' => $title,
                'from' => $from,
                'to' => $to,
                'generatedAt' => BusinessTime::now(),
                'generatedBy' => $user->name,
                'summary' => $reportBuilder->summary($from, $to),
            ])
                ->setOption('isPhpEnabled', true)
                ->download("{$filename}.pdf");
        }

        $columns = ReportRegistry::definitions()[$reportKey]['columns'];
        $built = $this->buildTableRows($reportKey, $reportBuilder->rows($reportKey, $from, $to));

        return TableExport::pdf($title, $columns, $built['rows'], $this->moneyColumnIndexes($reportKey), [
            'from' => $from,
            'to' => $to,
            'generatedAt' => BusinessTime::now(),
            'generatedBy' => $user->name,
        ], $built['totalRow'], $filename);
    }

    /**
     * Export any role-scoped report to a real, streamed .xlsx (RPT-05).
     * Same entitlement check and audit-before-any-output ordering as
     * exportPdf(). The row set is always uncapped, even for reports whose
     * on-screen preview caps at 100.
     */
    public function exportXlsx(FilterReportRequest $request, ReportBuilder $reportBuilder, string $reportKey): StreamedResponse
    {
        $user = $request->user();

        abort_unless(ReportRegistry::isEntitled($user, $reportKey), 403);

        [$from, $to] = $request->range();

        AuditLogger::recordReportExport($user, $reportKey, 'xlsx', $from, $to);

        $title = ReportRegistry::definitions()[$reportKey]['title'];
        $filename = "{$reportKey}_{$from->toDateString()}_{$to->toDateString()}";

        if ($reportKey === 'financial-summary') {
            return TableExport::xlsx($title, ['Label', 'Amount'], $this->financialSummaryRows($reportBuilder->summary($from, $to)), [1], null, null, $filename);
        }

        $columns = ReportRegistry::definitions()[$reportKey]['columns'];
        $built = $this->buildTableRows($reportKey, $reportBuilder->rows($reportKey, $from, $to));

        return TableExport::xlsx($title, $columns, $built['rows'], $this->moneyColumnIndexes($reportKey), null, $built['totalRow'], $filename);
    }

    /**
     * The two-column Label/Amount body for `financial-summary` -- figure
     * block rows in the same top-to-bottom order as
     * `resources/views/reports/financial-summary.blade.php`, money cells
     * as raw numeric values (no currency symbol, no thousands separator).
     * The `['Label', 'Amount']` header is no longer part of this body --
     * `TableExport::xlsx()` supplies it via `$headings`. The write-off
     * disclosure is its own labelled row, only present when write-offs
     * occurred in range (D-09: disclosed, never subtracted).
     *
     * @param  array{job_sales: float, cancellation_fees: float, revenue_total: float, expenses_total: float, result: float, write_off_total: float}  $summary
     * @return list<list<float|string>>
     */
    private function financialSummaryRows(array $summary): array
    {
        $rows = [
            ['Job Sales', $summary['job_sales']],
            ['Cancellation Fees', $summary['cancellation_fees']],
            ['Total Revenue', $summary['revenue_total']],
            ['Recorded Expenses', $summary['expenses_total']],
            [$summary['result'] < 0 ? 'Net Loss' : 'Net Profit', $summary['result']],
        ];

        if ($summary['write_off_total'] > 0) {
            $rows[] = ['Bad Debt Written Off (not deducted -- never counted as revenue)', $summary['write_off_total']];
        }

        return $rows;
    }

    /**
     * Row set for a tabular report, positionally reordered to match that
     * report's `columns` order via exportRowValues(), plus the range Total
     * row both exportPdf() and exportXlsx() now render identically. A
     * voided row stays visible in the table but must never reach the
     * Total, mirroring `Expense::active()` -- the same exclusion the
     * on-screen ledger total and the Financial Summary's `expenses_total`
     * already apply. A report with no money column (production-status) has
     * nothing to total, so it gets no Total row at all rather than a bare
     * label.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{rows: list<list<mixed>>, totalRow: list<mixed>|null}
     */
    private function buildTableRows(string $reportKey, Collection $rows): array
    {
        $columns = ReportRegistry::definitions()[$reportKey]['columns'];
        $moneyIndexes = $this->moneyColumnIndexes($reportKey);

        $tableRows = [];
        $totals = array_fill_keys($moneyIndexes, 0.0);

        foreach ($rows as $row) {
            $values = $this->exportRowValues($reportKey, $row);
            $tableRows[] = $values;

            if (($row['status'] ?? null) === 'Voided') {
                continue;
            }

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
            array_keys($columns)
        );

        return ['rows' => $tableRows, 'totalRow' => $moneyIndexes === [] ? null : $totalRow];
    }

    /**
     * The 0-based column indexes (within that report's `columns` array)
     * that carry money and must be totalled on the final row.
     *
     * @return list<int>
     */
    private function moneyColumnIndexes(string $reportKey): array
    {
        return match ($reportKey) {
            'sales' => [5],
            'cancellations' => [3, 4],
            'expenses' => [3],
            default => [],
        };
    }

    /**
     * Reorders one `ReportBuilder::rows()` row into the positional order of
     * that report's `columns` array, resolving every label PDF and Excel
     * must show IDENTICALLY -- enum values via `Str::headline()`, booleans
     * to their display word, dates via `Carbon::parse()`. `TableExport`
     * owns the remaining per-format formatting (money, CarbonInterface).
     * `entered_production`/`due` stay the `CarbonInterface`/`null`
     * instances `ReportBuilder` already returns for that reason.
     *
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    private function exportRowValues(string $reportKey, array $row): array
    {
        return match ($reportKey) {
            'sales' => [
                Carbon::parse($row['date']),
                $row['job_order'],
                $row['customer'],
                Str::headline($row['type']),
                Str::headline($row['method']),
                (float) $row['amount'],
            ],
            'cancellations' => [
                Carbon::parse($row['date']),
                $row['job_order'],
                $row['customer'],
                (float) $row['job_order_total'],
                (float) $row['cancellation_fee'],
                Str::headline($row['payment_status']),
            ],
            'production-status' => [
                $row['job_order'],
                $row['customer'],
                $row['product'],
                Str::headline($row['stage']),
                $row['urgency'] ? 'Rush' : 'Normal',
                $row['entered_production'],
                $row['due'],
            ],
            'expenses' => [
                Carbon::parse($row['date']),
                $row['category'],
                $row['description'],
                (float) $row['amount'],
                $row['recorded_by'],
                $row['status'] === 'Voided' ? 'Voided' : 'Active',
            ],
            default => [],
        };
    }
}
