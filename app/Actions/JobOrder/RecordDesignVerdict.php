<?php

namespace App\Actions\JobOrder;

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use Illuminate\Support\Facades\DB;

class RecordDesignVerdict
{
    public function __construct(
        public EnterProduction $enterProduction,
        public SyncQueueEntryStatus $syncQueueEntryStatus,
    ) {}

    /**
     * Resolve only the current pending revision. Lock the job order first
     * for both submissions and verdicts so simultaneous decisions cannot
     * overwrite each other or act on a superseded file.
     */
    public function __invoke(JobOrder $jobOrder, string $outcome, ?string $message = null, ?int $revisionId = null): bool
    {
        return DB::transaction(function () use ($jobOrder, $outcome, $message, $revisionId): bool {
            $jobOrder = JobOrder::query()->lockForUpdate()->findOrFail($jobOrder->id);
            $revision = $jobOrder->revisionLogs()->latest('submitted_at')->latest('id')->lockForUpdate()->first();

            if ($jobOrder->status !== JobOrderStatus::PendingReview
                || $revision === null
                || $revision->outcome !== null
                || ($revisionId !== null && $revision->id !== $revisionId)) {
                return false;
            }

            $revision->forceFill([
                'outcome' => $outcome,
                'reviewed_at' => now(),
                'message' => $message,
            ])->save();

            if ($outcome === 'approved') {
                $jobOrder->designFile->forceFill(['locked_at' => now()])->save();
                $jobOrder->forceFill(['status' => JobOrderStatus::DesignApproved])->save();

                ($this->enterProduction)($jobOrder);
                ($this->syncQueueEntryStatus)($jobOrder->queueEntry);
            } else {
                $jobOrder->forceFill(['status' => JobOrderStatus::InDesign])->save();
            }

            return true;
        });
    }
}
