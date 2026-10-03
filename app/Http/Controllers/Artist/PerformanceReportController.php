<?php

namespace App\Http\Controllers\Artist;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artist\PerformanceReportFilterRequest;
use App\Models\JobOrder;
use App\Models\SystemConfiguration;
use App\Support\BusinessTime;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceReportController extends Controller
{
    /**
     * Show the Artist's own performance report (JOB-10): jobs completed,
     * average revisions per job, and SLA adherence over a selectable date
     * range, scoped to this artist's own design_approved job orders (D-16).
     *
     * Cancelled job orders are excluded: CancellationController leaves
     * `status` untouched and permits cancelling from all three production
     * statuses, so without this a cancelled job would keep inflating both
     * jobsCompleted and slaAdherence.
     */
    public function index(PerformanceReportFilterRequest $request): Response
    {
        // Shop days, not UTC ones: a design approved at 1 AM on the 3rd is
        // stored as 5 PM UTC on the 2nd.
        $from = BusinessTime::utcStartOfDay($request->date('from', null, BusinessTime::zone()));
        $to = BusinessTime::utcEndOfDay($request->date('to', null, BusinessTime::zone()));

        $completed = JobOrder::query()
            ->where('assigned_artist_id', $request->user()->id)
            ->whereNull('cancelled_at')
            ->whereIn('status', [
                JobOrderStatus::DesignApproved->value,
                JobOrderStatus::ForProduction->value,
                JobOrderStatus::Printing->value,
                JobOrderStatus::ReadyForPickup->value,
            ])
            ->withCount('revisionLogs')
            ->with(['revisionLogs' => fn ($query) => $query->where('outcome', 'approved')->latest('reviewed_at')->limit(1)])
            ->get(['id', 'number', 'description', 'created_at', 'assigned_artist_id', 'status'])
            ->filter(function (JobOrder $jobOrder) use ($from, $to) {
                $approvedAt = $jobOrder->revisionLogs->first()?->reviewed_at;

                if ($approvedAt === null) {
                    return false;
                }

                if ($from !== null && $approvedAt->lt($from)) {
                    return false;
                }

                if ($to !== null && $approvedAt->gt($to)) {
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
                'slaDays' => $slaDays,
            ],
            // The rows behind the three figures. Without them the page states
            // an SLA percentage an artist has no way to check or learn from.
            'completedJobOrders' => $completed
                ->map(function (JobOrder $jobOrder) use ($slaDays) {
                    $approvedAt = $jobOrder->revisionLogs->first()->reviewed_at;

                    return [
                        'id' => $jobOrder->id,
                        'number' => $jobOrder->number,
                        'description' => $jobOrder->description,
                        'approved_at' => BusinessTime::local($approvedAt)->toDateString(),
                        'revisions' => $jobOrder->revision_logs_count,
                        'days_taken' => (int) $jobOrder->created_at->diffInDays($approvedAt),
                        'within_sla' => $jobOrder->created_at->diffInDays($approvedAt) <= $slaDays,
                    ];
                })
                ->sortByDesc('approved_at')
                ->values(),
            'filters' => $request->only(['from', 'to']),
        ]);
    }
}
