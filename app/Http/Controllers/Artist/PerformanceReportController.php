<?php

namespace App\Http\Controllers\Artist;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artist\PerformanceReportFilterRequest;
use App\Models\JobOrder;
use App\Models\SystemConfiguration;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceReportController extends Controller
{
    /**
     * Show the Artist's own performance report (JOB-10): jobs completed,
     * average revisions per job, and SLA adherence over a selectable date
     * range, scoped to this artist's own design_approved job orders (D-16).
     */
    public function index(PerformanceReportFilterRequest $request): Response
    {
        $from = $request->date('from');
        $to = $request->date('to');

        $completed = JobOrder::query()
            ->where('assigned_artist_id', $request->user()->id)
            ->whereIn('status', [
                JobOrderStatus::DesignApproved->value,
                JobOrderStatus::ForProduction->value,
                JobOrderStatus::Printing->value,
                JobOrderStatus::QualityCheck->value,
                JobOrderStatus::ReadyForPickup->value,
            ])
            ->withCount('revisionLogs')
            ->with(['revisionLogs' => fn ($query) => $query->where('outcome', 'approved')->latest('reviewed_at')->limit(1)])
            ->get(['id', 'created_at', 'assigned_artist_id', 'status'])
            ->filter(function (JobOrder $jobOrder) use ($from, $to) {
                $approvedAt = $jobOrder->revisionLogs->first()?->reviewed_at;

                if ($approvedAt === null) {
                    return false;
                }

                if ($from !== null && $approvedAt->lt($from)) {
                    return false;
                }

                if ($to !== null && $approvedAt->gt($to->copy()->endOfDay())) {
                    return false;
                }

                return true;
            });

        $jobsCompleted = $completed->count();
        $avgRevisions = $jobsCompleted > 0 ? round($completed->avg('revision_logs_count'), 1) : 0;
        $slaDays = SystemConfiguration::getInt('default_sla_days', 3);
        $withinSla = $completed->filter(fn (JobOrder $jobOrder) => $jobOrder->created_at->diffInDays($jobOrder->revisionLogs->first()->reviewed_at) <= $slaDays)->count();
        $slaAdherence = $jobsCompleted > 0 ? (int) round(($withinSla / $jobsCompleted) * 100) : 0;

        return Inertia::render('artist/PerformanceReport', [
            'stats' => [
                'jobsCompleted' => $jobsCompleted,
                'avgRevisions' => $avgRevisions,
                'slaAdherence' => $slaAdherence,
            ],
            'filters' => $request->only(['from', 'to']),
        ]);
    }
}
