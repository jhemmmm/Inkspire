<?php

namespace App\Http\Controllers\ProductionStaff;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductionStaff\AdvanceProductionStageRequest;
use App\Http\Requests\ProductionStaff\SendBackProductionStageRequest;
use App\Models\JobOrder;
use App\Models\ProductionLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Move a job order exactly one production stage forward or back (PROD-02,
 * D-10, D-11) — the fixed four-stage sequence is never skippable in either
 * direction.
 */
class ProductionStageController extends Controller
{
    /**
     * The fixed, ordered production stage sequence (PROD-02, D-10).
     *
     * @var array<int, JobOrderStatus>
     */
    private const SEQUENCE = [
        JobOrderStatus::ForProduction,
        JobOrderStatus::Printing,
        JobOrderStatus::QualityCheck,
        JobOrderStatus::ReadyForPickup,
    ];

    /**
     * The display label for a production stage — stays a private controller
     * helper rather than a method on the enum itself, per the codebase's
     * established convention.
     */
    private function stageLabel(JobOrderStatus $status): string
    {
        return match ($status) {
            JobOrderStatus::ForProduction => 'For Production',
            JobOrderStatus::Printing => 'Printing',
            JobOrderStatus::QualityCheck => 'Quality Check',
            JobOrderStatus::ReadyForPickup => 'Ready for Pickup',
            default => $status->value,
        };
    }

    /**
     * Advance a job order to the next stage in the sequence (D-10).
     *
     * Re-reads the job order under a database lock (T-06-06-03) so a
     * double-submitted/retried request can never write two ProductionLog
     * rows for a single logical move, mirroring
     * CreditApprovalController::approve()'s idempotency boundary.
     */
    public function advance(AdvanceProductionStageRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
        abort_if($jobOrder->released_at !== null, 422, __('This job order has already been released.'));

        [$jobOrder, $nextStatus] = DB::transaction(function () use ($request, $jobOrder): array {
            $jobOrder = JobOrder::query()->whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();

            $currentIndex = array_search($jobOrder->status, self::SEQUENCE, true);

            abort_if($currentIndex === false, 422, __('This job order is not on the production board.'));
            abort_if($currentIndex === count(self::SEQUENCE) - 1, 422, __('This job order already moved on. The board has refreshed — check its current stage before trying again.'));

            $from = $jobOrder->status;
            $nextStatus = self::SEQUENCE[$currentIndex + 1];

            $jobOrder->forceFill(['status' => $nextStatus])->save();

            ProductionLog::create([
                'job_order_id' => $jobOrder->id,
                'from_status' => $from,
                'to_status' => $nextStatus,
                'reason' => null,
                'recorded_by' => $request->user()->id,
            ]);

            return [$jobOrder, $nextStatus];
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':number moved to :stage.', ['number' => $jobOrder->number, 'stage' => $this->stageLabel($nextStatus)]),
        ]);

        return back();
    }

    /**
     * Send a job order back to the previous stage in the sequence (D-11),
     * with a mandatory reason recorded on the ProductionLog row.
     *
     * Re-reads the job order under a database lock (T-06-06-03), identical
     * idempotency boundary to advance().
     */
    public function sendBack(SendBackProductionStageRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
        abort_if($jobOrder->released_at !== null, 422, __('This job order has already been released.'));

        [$jobOrder, $previousStatus] = DB::transaction(function () use ($request, $jobOrder): array {
            $jobOrder = JobOrder::query()->whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();

            $currentIndex = array_search($jobOrder->status, self::SEQUENCE, true);

            abort_if($currentIndex === false, 422, __('This job order is not on the production board.'));
            abort_if($currentIndex === 0, 422, __('This job order already moved on. The board has refreshed — check its current stage before trying again.'));

            $from = $jobOrder->status;
            $previousStatus = self::SEQUENCE[$currentIndex - 1];

            $jobOrder->forceFill(['status' => $previousStatus])->save();

            ProductionLog::create([
                'job_order_id' => $jobOrder->id,
                'from_status' => $from,
                'to_status' => $previousStatus,
                'reason' => $request->validated('reason'),
                'recorded_by' => $request->user()->id,
            ]);

            return [$jobOrder, $previousStatus];
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':number sent back to :stage.', ['number' => $jobOrder->number, 'stage' => $this->stageLabel($previousStatus)]),
        ]);

        return back();
    }
}
