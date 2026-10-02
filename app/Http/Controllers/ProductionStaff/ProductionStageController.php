<?php

namespace App\Http\Controllers\ProductionStaff;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use App\Models\ProductionLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Start, finish or undo a job order's production step (PROD-02). Starting and
 * finishing are payment-gated; undo is not, so a mistaken tap can always be
 * reversed.
 */
class ProductionStageController extends Controller
{
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
     * Start printing: For Production to Printing. Payment-gated.
     */
    public function start(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        return $this->transition($request, $jobOrder, [
            JobOrderStatus::ForProduction->value => JobOrderStatus::Printing,
        ], true, false);
    }

    /**
     * Finish: For Production, Printing or Quality Check to Ready for Pickup.
     * Payment-gated.
     */
    public function done(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        return $this->transition($request, $jobOrder, [
            JobOrderStatus::ForProduction->value => JobOrderStatus::ReadyForPickup,
            JobOrderStatus::Printing->value => JobOrderStatus::ReadyForPickup,
            JobOrderStatus::QualityCheck->value => JobOrderStatus::ReadyForPickup,
        ], true, false);
    }

    /**
     * Undo: Ready for Pickup to Printing, Printing or Quality Check to For
     * Production. Not payment-gated and needs no typed reason; the log still
     * records who and when.
     */
    public function undo(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        return $this->transition($request, $jobOrder, [
            JobOrderStatus::ReadyForPickup->value => JobOrderStatus::Printing,
            JobOrderStatus::Printing->value => JobOrderStatus::ForProduction,
            JobOrderStatus::QualityCheck->value => JobOrderStatus::ForProduction,
        ], false, true);
    }

    /**
     * Apply one transition from the given source-to-target table.
     *
     * Re-reads the job order under a database lock (T-06-06-03) so a
     * double-submitted/retried request can never write two ProductionLog
     * rows for a single logical move. Every guard, including the payment
     * gate, runs against that locked re-read, never against the
     * route-model-bound instance: a cancellation, release or payment change
     * committing between the two would otherwise slip a transition through.
     *
     * @param  array<string, JobOrderStatus>  $transitions  source status value => target status
     */
    private function transition(Request $request, JobOrder $jobOrder, array $transitions, bool $requiresPayment, bool $isUndo): RedirectResponse
    {
        [$jobOrder, $target] = DB::transaction(function () use ($request, $jobOrder, $transitions, $requiresPayment): array {
            $jobOrder = JobOrder::query()->whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();

            abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
            abort_if($jobOrder->released_at !== null, 422, __('This job order has already been released.'));

            abort_if(! in_array($jobOrder->status, [
                JobOrderStatus::ForProduction,
                JobOrderStatus::Printing,
                JobOrderStatus::QualityCheck,
                JobOrderStatus::ReadyForPickup,
            ], true), 422, __('This job order is not on the board'));

            $target = $transitions[$jobOrder->status->value] ?? null;

            abort_if($target === null, 422, __('This job order already moved on. The board has refreshed — check its current stage before trying again.'));

            abort_if($requiresPayment && ! $jobOrder->isClearedForProduction(), 422, __('Awaiting payment — send the customer to the Cashier.'));

            $from = $jobOrder->status;

            $jobOrder->forceFill(['status' => $target])->save();

            ProductionLog::create([
                'job_order_id' => $jobOrder->id,
                'from_status' => $from,
                'to_status' => $target,
                'reason' => null,
                'recorded_by' => $request->user()->id,
            ]);

            return [$jobOrder, $target];
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $isUndo
                ? __(':number moved back to :stage.', ['number' => $jobOrder->number, 'stage' => $this->stageLabel($target)])
                : __(':number moved to :stage.', ['number' => $jobOrder->number, 'stage' => $this->stageLabel($target)]),
        ]);

        return back();
    }
}
