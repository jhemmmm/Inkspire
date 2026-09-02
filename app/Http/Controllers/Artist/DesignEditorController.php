<?php

namespace App\Http\Controllers\Artist;

use App\Actions\JobOrder\RecordDesignRevision;
use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artist\RecordDesignVerdictRequest;
use App\Http\Requests\Artist\SendForReviewRequest;
use App\Http\Requests\Artist\StartDesignRequest;
use App\Models\JobOrder;
use App\Models\RevisionLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DesignEditorController extends Controller
{
    public function __construct(public RecordDesignRevision $recordDesignRevision) {}

    /**
     * The latest unreviewed revision_logs row for this job order. There is
     * always exactly one per pending_review job order, since every prior
     * submission was already resolved by the time a new one was created
     * (D-07/D-08).
     */
    private function latestUnreviewedRevisionLog(JobOrder $jobOrder): RevisionLog
    {
        return $jobOrder->revisionLogs()->whereNull('outcome')->latest('submitted_at')->firstOrFail();
    }

    /**
     * D-06's first-pass entry point into in_design, called once by the
     * pre-editor choice (blank canvas or reference import) before the
     * canvas mounts. The pre-editor choice only ever renders while no
     * design_files row exists yet, which per D-08 means job_orders.status
     * cannot have advanced past in_consultation — so this guard also
     * organically prevents a second, redundant call once a bounce-back
     * re-opens the same job order at in_design.
     */
    public function startDesign(StartDesignRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, 'This job order is not assigned to you.');
        abort_unless($jobOrder->status === JobOrderStatus::InConsultation, 422, 'This job order is not ready to start a design.');

        $jobOrder->forceFill(['status' => JobOrderStatus::InDesign])->save();

        return back();
    }

    /**
     * Persist the current canvas export as this job order's design
     * revision and advance it to pending_review (JOB-05).
     */
    public function sendForReview(SendForReviewRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, 'This job order is not assigned to you.');
        abort_if($jobOrder->status === JobOrderStatus::Assigned, 422, 'Claim this job order with Next before starting a design.');
        abort_if($jobOrder->status === JobOrderStatus::PendingReview, 422, 'This design is already pending review.');
        abort_if(optional($jobOrder->designFile)->locked_at !== null, 422, 'This design is locked and cannot be edited.');

        ($this->recordDesignRevision)($jobOrder, $request->file('file'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sent for review. Waiting on the client\'s verdict.')]);

        return back();
    }

    /**
     * Record the client's in-person "Client Approved" verdict (D-05): locks
     * the design file and advances the job order to design_approved.
     */
    public function approve(RecordDesignVerdictRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, 'This job order is not assigned to you.');
        abort_unless($jobOrder->status === JobOrderStatus::PendingReview, 422, 'This job order is not pending review.');

        DB::transaction(function () use ($jobOrder) {
            $this->latestUnreviewedRevisionLog($jobOrder)->forceFill([
                'outcome' => 'approved',
                'reviewed_at' => now(),
            ])->save();

            $jobOrder->designFile->forceFill(['locked_at' => now()])->save();

            $jobOrder->forceFill(['status' => JobOrderStatus::DesignApproved])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Design approved. This job order is ready for pricing at the Cashier.')]);

        return back();
    }

    /**
     * Record the client's in-person "Client Requested Changes" verdict
     * (D-05): bounces the job order back to in_design. This is the SECOND
     * way a job order enters in_design — the bounce-back path — complementing
     * startDesign()'s first-pass entry point, so D-06's full cycle is
     * reachable on every design pass. Deliberately does not touch the
     * design file's lock state (it was never set at this point in the cycle).
     */
    public function requestChanges(RecordDesignVerdictRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, 'This job order is not assigned to you.');
        abort_unless($jobOrder->status === JobOrderStatus::PendingReview, 422, 'This job order is not pending review.');

        DB::transaction(function () use ($jobOrder) {
            $this->latestUnreviewedRevisionLog($jobOrder)->forceFill([
                'outcome' => 'changes_requested',
                'reviewed_at' => now(),
            ])->save();

            $jobOrder->forceFill(['status' => JobOrderStatus::InDesign])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Noted. Back to editing.')]);

        return back();
    }
}
