<?php

namespace App\Actions\JobOrder;

use App\Enums\JobOrderStatus;
use App\Mail\DesignReviewRequested;
use App\Models\DesignFile;
use App\Models\JobOrder;
use App\Models\RevisionLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class RecordDesignRevision
{
    /**
     * Atomically store the exported design file, overwrite the job order's
     * single current design_files row (D-08), log an unconditional
     * revision_logs entry for this submission (D-07), and advance the job
     * order to pending_review. Strictly after the transaction commits,
     * emails the client a signed remote-review link (D-18) so a rollback
     * can never be followed by an email pointing at a phantom revision.
     * A mail transport failure is caught and reported, never allowed to
     * fail the write, since the transactional core already committed.
     */
    public function __invoke(JobOrder $jobOrder, UploadedFile $file): void
    {
        $revisionLog = DB::transaction(function () use ($jobOrder, $file): RevisionLog {
            $path = $file->store('design-files', 'local');

            DesignFile::updateOrCreate(
                ['job_order_id' => $jobOrder->id],
                ['file_path' => $path],
            );

            $revisionLog = RevisionLog::create([
                'job_order_id' => $jobOrder->id,
                'submitted_at' => now(),
            ]);

            $jobOrder->forceFill(['status' => JobOrderStatus::PendingReview])->save();

            return $revisionLog;
        });

        try {
            Mail::to($jobOrder->queueEntry->contactEmail())->send(new DesignReviewRequested($revisionLog));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
