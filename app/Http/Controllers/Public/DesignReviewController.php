<?php

namespace App\Http\Controllers\Public;

use App\Actions\JobOrder\EnterProduction;
use App\Actions\JobOrder\SyncQueueEntryStatus;
use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use App\Models\RevisionLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class DesignReviewController extends Controller
{
    public function __construct(
        public EnterProduction $enterProduction,
        public SyncQueueEntryStatus $syncQueueEntryStatus,
    ) {}

    /**
     * Show the client's remote design-review page for a signed link
     * (D-17/D-18). Entirely independent of DesignEditorController.
     */
    public function show(RevisionLog $revisionLog): Response
    {
        $revisionLog->loadMissing('jobOrder.designFile');

        return $this->render($revisionLog);
    }

    /**
     * Record the client's remote "Client Approved" verdict, reaching the
     * exact same outcome DesignEditorController::approve() already produces
     * (D-17), through entirely independent code.
     */
    public function approve(RevisionLog $revisionLog): Response
    {
        $revisionLog->loadMissing('jobOrder.designFile');
        $jobOrder = $revisionLog->jobOrder;

        if ($this->isActionable($revisionLog, $jobOrder)) {
            DB::transaction(function () use ($revisionLog, $jobOrder): void {
                $revisionLog->forceFill([
                    'outcome' => 'approved',
                    'reviewed_at' => now(),
                ])->save();

                $jobOrder->designFile->forceFill(['locked_at' => now()])->save();

                $jobOrder->forceFill(['status' => JobOrderStatus::DesignApproved])->save();

                ($this->enterProduction)($jobOrder);

                // Mirrors DesignEditorController::approve() -- the remote
                // verdict closes the visit exactly as the in-person one does.
                ($this->syncQueueEntryStatus)($jobOrder->queueEntry);
            });
        }

        return $this->render($revisionLog->fresh(['jobOrder.designFile']));
    }

    /**
     * Record the client's remote "Client Requested Changes" verdict,
     * reaching the exact same outcome DesignEditorController::requestChanges()
     * already produces (D-17), through entirely independent code. Deliberately
     * never touches designFile.locked_at, mirroring the in-person path.
     */
    public function requestChanges(RevisionLog $revisionLog): Response
    {
        $revisionLog->loadMissing('jobOrder.designFile');
        $jobOrder = $revisionLog->jobOrder;

        if ($this->isActionable($revisionLog, $jobOrder)) {
            DB::transaction(function () use ($revisionLog, $jobOrder): void {
                $revisionLog->forceFill([
                    'outcome' => 'changes_requested',
                    'reviewed_at' => now(),
                ])->save();

                $jobOrder->forceFill(['status' => JobOrderStatus::InDesign])->save();
            });
        }

        return $this->render($revisionLog->fresh(['jobOrder.designFile']));
    }

    /**
     * Render the public/DesignReview page in the state matching this
     * revision's current lifecycle position (D-19/D-20/D-21).
     *
     * Every state carries the job order's number and its tracking link, so
     * a customer who has given their verdict has somewhere to go next: the
     * tracking page is where they follow the order and pay for it. The
     * signed link was emailed to the same customer the tracking link was,
     * so this hands the token to nobody who did not already hold it.
     */
    private function render(RevisionLog $revisionLog): Response
    {
        $jobOrder = $revisionLog->jobOrder;
        $expiresAt = $revisionLog->submitted_at->addDays(7);

        $order = [
            'jobOrderDescription' => $jobOrder->description,
            'jobOrderNumber' => $jobOrder->number,
            'trackingUrl' => route('public.tracking.token', ['token' => $jobOrder->tracking_token]),
        ];

        if (! $this->isCurrentRevision($revisionLog, $jobOrder)) {
            return Inertia::render('public/DesignReview', ['state' => 'stale', ...$order]);
        }

        if (! $this->isActionable($revisionLog, $jobOrder)) {
            return Inertia::render('public/DesignReview', [
                'state' => 'closed',
                ...$order,
                'outcome' => $revisionLog->outcome,
            ]);
        }

        return Inertia::render('public/DesignReview', [
            'state' => 'active',
            ...$order,
            'imageUrl' => Storage::disk('local')->temporaryUrl($jobOrder->designFile->file_path, now()->addMinutes(10)),
            'approveUrl' => URL::temporarySignedRoute('public.design-review.approve', $expiresAt, ['revisionLog' => $revisionLog->id]),
            'requestChangesUrl' => URL::temporarySignedRoute('public.design-review.request-changes', $expiresAt, ['revisionLog' => $revisionLog->id]),
        ]);
    }

    /**
     * Whether this revision is still the job order's most recent Send for
     * Review submission (D-19) — a superseded revision always renders
     * 'stale', never acting on outdated design data.
     */
    private function isCurrentRevision(RevisionLog $revisionLog, JobOrder $jobOrder): bool
    {
        return $revisionLog->id === $jobOrder->revisionLogs()->latest('submitted_at')->value('id');
    }

    /**
     * D-20's "first verdict wins" guard: whichever caller (in-person via
     * DesignEditorController, or this remote path) resolves the row first
     * flips both conditions false for the other caller.
     */
    private function isActionable(RevisionLog $revisionLog, JobOrder $jobOrder): bool
    {
        return $jobOrder->status === JobOrderStatus::PendingReview && $revisionLog->outcome === null;
    }
}
