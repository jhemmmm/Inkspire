<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\FilterReportRequest;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportRegistry;
use App\Support\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
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

        if ($request->filled('from') && $request->filled('to')) {
            $from = $request->date('from')->startOfDay();
            $to = $request->date('to')->endOfDay();
        } else {
            $from = now()->startOfMonth()->startOfDay();
            $to = now()->endOfDay();
        }

        AuditLogger::recordReportExport($user, $reportKey, 'pdf', $from, $to);

        $data = [
            'title' => ReportRegistry::definitions()[$reportKey]['title'],
            'from' => $from,
            'to' => $to,
            'generatedAt' => now(),
            'generatedBy' => $user->name,
        ];

        if ($reportKey === 'financial-summary') {
            $data['summary'] = $reportBuilder->summary($from, $to);
        } else {
            $data['rows'] = $reportBuilder->rows($reportKey, $from, $to)->all();
        }

        return Pdf::loadView("reports.{$reportKey}", $data)
            ->setOption('isPhpEnabled', true)
            ->download("{$reportKey}_{$from->toDateString()}_{$to->toDateString()}.pdf");
    }

    /**
     * Export any role-scoped report to a real, streamed .xlsx (RPT-05).
     * Same entitlement check and audit-before-any-output ordering as
     * exportPdf(). Uses openspout's `openToFile('php://output')` inside
     * Laravel's own `response()->streamDownload()` -- deliberately not the
     * writer's browser-direct convenience method, which bypasses Laravel's
     * response lifecycle and could let bytes stream before the D-02 audit
     * write lands (T-08-16, RESEARCH.md Pitfall 3). The row set is always
     * uncapped, even for reports whose on-screen preview caps at 100.
     */
    public function exportXlsx(FilterReportRequest $request, ReportBuilder $reportBuilder, string $reportKey): StreamedResponse
    {
        $user = $request->user();

        abort_unless(ReportRegistry::isEntitled($user, $reportKey), 403);

        if ($request->filled('from') && $request->filled('to')) {
            $from = $request->date('from')->startOfDay();
            $to = $request->date('to')->endOfDay();
        } else {
            $from = now()->startOfMonth()->startOfDay();
            $to = now()->endOfDay();
        }

        AuditLogger::recordReportExport($user, $reportKey, 'xlsx', $from, $to);

        $sheetRows = $reportKey === 'financial-summary'
            ? $this->financialSummaryXlsxRows($reportBuilder->summary($from, $to))
            : $this->tabularXlsxRows($reportKey, $reportBuilder->rows($reportKey, $from, $to));

        $filename = "{$reportKey}_{$from->toDateString()}_{$to->toDateString()}.xlsx";

        return response()->streamDownload(function () use ($sheetRows): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            foreach ($sheetRows as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * The two-column Label/Amount sheet for `financial-summary` -- figure
     * block rows in the same top-to-bottom order as
     * `resources/views/reports/financial-summary.blade.php`, money cells
     * as raw numeric values (no currency symbol, no thousands separator).
     * The write-off disclosure is its own labelled row, only present when
     * write-offs occurred in range (D-09: disclosed, never subtracted).
     *
     * @param  array{job_sales: float, cancellation_fees: float, revenue_total: float, expenses_total: float, result: float, write_off_total: float}  $summary
     * @return list<list<null|bool|float|int|string>>
     */
    private function financialSummaryXlsxRows(array $summary): array
    {
        $rows = [
            ['Label', 'Amount'],
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
     * Row 1 = the registry's `columns` array verbatim. Rows 2..n = the full
     * uncapped row set from `ReportBuilder::rows()`, positionally reordered
     * to match the column order, with money cells as raw numbers and date
     * cells as ISO `YYYY-MM-DD` strings. Final row = a 'Total' label plus
     * the range total in each money column, other cells empty.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<list<null|bool|float|int|string>>
     */
    private function tabularXlsxRows(string $reportKey, Collection $rows): array
    {
        $columns = ReportRegistry::definitions()[$reportKey]['columns'];
        $moneyIndexes = $this->xlsxMoneyColumnIndexes($reportKey);

        $sheetRows = [$columns];
        $totals = array_fill_keys($moneyIndexes, 0.0);

        foreach ($rows as $row) {
            $values = $this->xlsxRowValues($reportKey, $row);
            $sheetRows[] = $values;

            /**
             * A voided row stays visible in the sheet but must never reach the
             * Total, mirroring `Expense::active()` -- the same exclusion the
             * on-screen ledger total and the Financial Summary's
             * `expenses_total` already apply.
             */
            if (($row['status'] ?? null) === 'Voided') {
                continue;
            }

            foreach ($moneyIndexes as $index) {
                $totals[$index] += (float) ($values[$index] ?? 0);
            }
        }

        $totalRow = array_fill(0, count($columns), null);
        $totalRow[0] = 'Total';

        foreach ($moneyIndexes as $index) {
            $totalRow[$index] = $totals[$index];
        }

        $sheetRows[] = $totalRow;

        return $sheetRows;
    }

    /**
     * The 0-based column indexes (within that report's `columns` array)
     * that carry money and must be totalled on the final row.
     *
     * @return list<int>
     */
    private function xlsxMoneyColumnIndexes(string $reportKey): array
    {
        return match ($reportKey) {
            'sales' => [5],
            'cancellations' => [3, 4],
            'expenses' => [3],
            default => [],
        };
    }

    /**
     * Reorders one `ReportBuilder::rows()` row into the positional order
     * of that report's `columns` array, converting any remaining Carbon
     * instances to ISO date strings (production-status's `entered_production`
     * / `due` are the only fields ReportBuilder leaves as Carbon, since the
     * on-screen Inertia props format them client-side).
     *
     * @param  array<string, mixed>  $row
     * @return list<null|bool|float|int|string>
     */
    private function xlsxRowValues(string $reportKey, array $row): array
    {
        return match ($reportKey) {
            'sales' => [
                $row['date'],
                $row['job_order'],
                $row['customer'],
                $row['type'],
                $row['method'],
                $row['amount'],
            ],
            'cancellations' => [
                $row['date'],
                $row['job_order'],
                $row['customer'],
                $row['job_order_total'],
                $row['cancellation_fee'],
                $row['payment_status'],
            ],
            'production-status' => [
                $row['job_order'],
                $row['customer'],
                $row['product'],
                $row['stage'],
                $row['urgency'],
                $this->xlsxDate($row['entered_production']),
                $this->xlsxDate($row['due']),
            ],
            'expenses' => [
                $row['date'],
                $row['category'],
                $row['description'],
                $row['amount'],
                $row['recorded_by'],
                $row['status'],
            ],
            default => [],
        };
    }

    private function xlsxDate(?CarbonInterface $value): ?string
    {
        return $value?->toDateString();
    }
}
