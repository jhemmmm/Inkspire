<?php

namespace App\Actions\JobOrder;

use App\Enums\JobOrderStatus;
use App\Models\DesignFile;
use App\Models\JobOrder;
use App\Models\RevisionLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class RecordDesignRevision
{
    /**
     * Atomically store the exported design file, overwrite the job order's
     * single current design_files row (D-08), log an unconditional
     * revision_logs entry for this submission (D-07), and advance the job
     * order to pending_review.
     */
    public function __invoke(JobOrder $jobOrder, UploadedFile $file): void
    {
        DB::transaction(function () use ($jobOrder, $file): void {
            $path = $file->store('design-files', 'local');

            DesignFile::updateOrCreate(
                ['job_order_id' => $jobOrder->id],
                ['file_path' => $path],
            );

            RevisionLog::create([
                'job_order_id' => $jobOrder->id,
                'submitted_at' => now(),
            ]);

            $jobOrder->forceFill(['status' => JobOrderStatus::PendingReview])->save();
        });
    }
}
