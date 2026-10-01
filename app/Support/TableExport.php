<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The one row-shaping contract that feeds both report export formats
 * (RPT-05). Callers resolve every cell to its final display label before
 * calling either method -- no `bool` and no raw snake_case enum string may
 * ever reach `$rows`/`$totalRow`, only the two deliberate per-format
 * differences this class owns: money-column casting and `CarbonInterface`
 * date formatting. This is what keeps the PDF and Excel exports of the same
 * report from drifting (e.g. Urgency showing "Rush" in PDF but "TRUE" in
 * Excel) -- one row definition, one rendering pair.
 */
final class TableExport
{
    /**
     * Render a tabular report to a downloadable PDF via `reports.table`.
     *
     * @param  list<string>  $headings  Column labels, in display order.
     * @param  list<list<CarbonInterface|null|float|int|string>>  $rows  Display-ready cells, except money-column and CarbonInterface cells.
     * @param  list<int>  $moneyColumns  0-based indexes into $headings/every row that are money.
     * @param  array{from?: CarbonInterface, to?: CarbonInterface, generatedAt?: CarbonInterface, generatedBy?: string, note?: string}|null  $meta  Merged into reports.layout's view data.
     * @param  list<CarbonInterface|null|float|int|string>|null  $totalRow  One cell per heading, or null when the report has no total row.
     * @param  string  $filename  Base name with no extension -- `.pdf` is appended.
     * @param  bool  $landscape  Render the page in landscape. Passed to `reports.layout`, whose `@page { size }` rule decides the orientation -- that CSS rule overrides dompdf's `setPaper()`, so setting it there is the only thing that works.
     */
    public static function pdf(string $title, array $headings, array $rows, array $moneyColumns, ?array $meta, ?array $totalRow, string $filename, bool $landscape = false): Response
    {
        $data = array_merge([
            'title' => $title,
            'headings' => $headings,
            'rows' => $rows,
            'moneyColumns' => $moneyColumns,
            'totalRow' => $totalRow,
            'landscape' => $landscape,
        ], $meta ?? []);

        return Pdf::loadView('reports.table', $data)
            ->setOption('isPhpEnabled', true)
            ->download("{$filename}.pdf");
    }

    /**
     * Stream a tabular report to a downloadable .xlsx. `$title` and `$meta`
     * are accepted for signature parity with pdf() only -- no title/range
     * header row exists in the sheet today, so both go unused here.
     *
     * @param  list<string>  $headings  Column labels, in display order.
     * @param  list<list<CarbonInterface|null|float|int|string>>  $rows  Display-ready cells, except money-column and CarbonInterface cells.
     * @param  list<int>  $moneyColumns  0-based indexes into $headings/every row that are money.
     * @param  array{from?: CarbonInterface, to?: CarbonInterface, generatedAt?: CarbonInterface, generatedBy?: string, note?: string}|null  $meta  Unused -- signature parity with pdf() only.
     * @param  list<CarbonInterface|null|float|int|string>|null  $totalRow  One cell per heading, or null when the report has no total row.
     * @param  string  $filename  Base name with no extension -- `.xlsx` is appended.
     */
    public static function xlsx(string $title, array $headings, array $rows, array $moneyColumns, ?array $meta, ?array $totalRow, string $filename): StreamedResponse
    {
        $sheetRows = [$headings];

        foreach ($rows as $row) {
            $sheetRows[] = self::xlsxCells($row, $moneyColumns);
        }

        if ($totalRow !== null) {
            $sheetRows[] = self::xlsxCells($totalRow, $moneyColumns);
        }

        // Deliberately not the writer's browser-direct method -- bytes must
        // never start streaming before the caller's D-02 audit write lands.
        return response()->streamDownload(function () use ($sheetRows): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            foreach ($sheetRows as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $writer->close();
        }, "{$filename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Converts one row's cells to their xlsx-native representation: `null`
     * passes through unchanged, a `CarbonInterface` becomes an ISO date
     * string, a money-column cell is cast to `float`, everything else
     * passes through unchanged.
     *
     * @param  list<CarbonInterface|null|float|int|string>  $row
     * @param  list<int>  $moneyColumns
     * @return list<null|float|int|string>
     */
    private static function xlsxCells(array $row, array $moneyColumns): array
    {
        $cells = [];

        foreach ($row as $index => $value) {
            $cells[] = match (true) {
                $value === null => null,
                $value instanceof CarbonInterface => $value->toDateString(),
                in_array($index, $moneyColumns, true) => (float) $value,
                default => $value,
            };
        }

        return $cells;
    }
}
