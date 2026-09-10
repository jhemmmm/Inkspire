<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\FilterReportRequest;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportRegistry;
use App\Support\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

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
}
