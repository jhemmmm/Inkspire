<?php

namespace App\Http\Controllers\Artist;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artist\UpdateJobOrderQueuePositionRequest;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobOrderQueueController extends Controller
{
    /**
     * The artist's own dashboard queue — every in-progress job order
     * assigned to them, ordered oldest-first (deprioritized ones sort by
     * their deprioritization timestamp instead).
     */
    public function index(Request $request): Response
    {
        return Inertia::render('artist/Dashboard', [
            'jobOrders' => JobOrder::query()
                ->where('assigned_artist_id', $request->user()->id)
                ->whereNotIn('status', [
                    JobOrderStatus::Intake->value,
                    JobOrderStatus::ValidationFailed->value,
                    JobOrderStatus::ReadyForProduction->value,
                ])
                ->orderByRaw('COALESCE(queue_deprioritized_at, created_at) ASC')
                ->get(['id', 'description', 'status', 'not_appeared', 'created_at']),
        ]);
    }

    /**
     * Claim the oldest eligible Assigned job order into in_consultation
     * (D-04), or manually resume a not_appeared job order regardless of
     * ordering.
     */
    public function next(UpdateJobOrderQueuePositionRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, 'This job order is not assigned to you.');

        if (! $jobOrder->not_appeared) {
            abort_unless($jobOrder->status === JobOrderStatus::Assigned, 422, 'This job order is not waiting to be called.');
            abort_unless($this->oldestEligibleId($request->user()) === $jobOrder->id, 422, 'Another job order is next in your queue.');
        }

        $jobOrder->forceFill([
            'status' => JobOrderStatus::InConsultation,
            'not_appeared' => false,
            'queue_deprioritized_at' => null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job order moved to consultation.')]);

        return to_route('artist.job-orders.show', $jobOrder);
    }

    /**
     * Forward an in-progress job order back into the Assigned pool,
     * deprioritized to the back of the line, without reassigning it to
     * another artist (D-03/D-04).
     */
    public function forward(UpdateJobOrderQueuePositionRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, 'This job order is not assigned to you.');
        abort_unless($jobOrder->status === JobOrderStatus::InConsultation || $jobOrder->status->value === 'in_design', 422, 'This job order cannot be forwarded in its current status.');

        $jobOrder->forceFill([
            'status' => JobOrderStatus::Assigned,
            'queue_deprioritized_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Forwarded. This job order moves to the back of your queue.')]);

        return back();
    }

    /**
     * Mark a job order Not Appeared, removing it from Next's automatic
     * oldest-first pool while it stays visible and directly resumable
     * (D-04).
     */
    public function notAppear(UpdateJobOrderQueuePositionRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, 'This job order is not assigned to you.');
        abort_unless($jobOrder->status === JobOrderStatus::InConsultation || $jobOrder->status->value === 'in_design', 422, 'This job order cannot be marked not-appeared in its current status.');

        $jobOrder->forceFill([
            'status' => JobOrderStatus::Assigned,
            'queue_deprioritized_at' => now(),
            'not_appeared' => true,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marked as not appeared. You can resume this job order any time from your dashboard.')]);

        return back();
    }

    /**
     * The oldest eligible Assigned, not-not_appeared job order for an
     * artist — independently re-derived server-side so a tampered request
     * targeting a non-oldest job order is rejected (T-04-02).
     */
    private function oldestEligibleId(User $artist): ?int
    {
        return JobOrder::query()
            ->where('assigned_artist_id', $artist->id)
            ->where('status', JobOrderStatus::Assigned->value)
            ->where('not_appeared', false)
            ->orderByRaw('COALESCE(queue_deprioritized_at, created_at) ASC')
            ->value('id');
    }
}
