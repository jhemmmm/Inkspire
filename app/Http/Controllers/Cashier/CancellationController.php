<?php

namespace App\Http\Controllers\Cashier;

use App\Actions\JobOrder\SyncQueueEntryStatus;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Enums\AccountsReceivableStatus;
use App\Enums\JobOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\CancelJobOrderRequest;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\SystemConfiguration;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CancellationController extends Controller
{
    /**
     * Cancel a job order (POS-07), collecting a cancellation fee when
     * design work has already started (D-04) and netting any existing
     * down payment against that fee (D-05).
     *
     * The fee amount is always read from `system_configurations`
     * server-side — the confirm-only dialog never submits an amount
     * (T-05-13).
     *
     * The route-bound job order is re-read under a row lock before anything
     * is checked: a release, or a second Cancel, landing after the binding
     * would otherwise slip past guards that only saw the earlier state.
     */
    public function store(CancelJobOrderRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        DB::transaction(function () use ($jobOrder, $request): void {
            $jobOrder = JobOrder::query()->whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();

            $blocker = $jobOrder->cancellationBlocker();

            if ($blocker !== null) {
                abort(422, __($blocker));
            }

            $designStarted = in_array($jobOrder->status, [
                JobOrderStatus::InDesign,
                JobOrderStatus::PendingReview,
                JobOrderStatus::DesignApproved,
                JobOrderStatus::ForProduction,
                JobOrderStatus::Printing,
                JobOrderStatus::ReadyForPickup,
            ], true);

            if ($designStarted) {
                $fee = SystemConfiguration::getFloat('cancellation_fee_amount', 500.0);
                $existingDownPayment = (float) $jobOrder->transactions()
                    ->where('status', TransactionStatus::Completed->value)
                    ->sum('amount');

                if ($existingDownPayment < $fee) {
                    Transaction::create([
                        'job_order_id' => $jobOrder->id,
                        'type' => TransactionType::CancellationFee->value,
                        'payment_method' => PaymentMethod::Cash->value,
                        'amount' => round($fee - $existingDownPayment, 2),
                        'status' => TransactionStatus::Completed->value,
                        'recorded_by' => $request->user()->id,
                        'confirmed_at' => now(),
                    ]);
                }

                // $existingDownPayment >= $fee: the fee is already covered by
                // prior payment(s) — no new Transaction is created. The
                // refundable excess is informational only (shown in the
                // confirmation dialog before submitting); processing an
                // actual refund is out of scope for this plan.
            }

            // Cancelling voids the print-job debt — only the cancellation
            // fee stands. Close any open receivable so the balance stops
            // ageing, stops generating reminders and collection letters, and
            // can no longer be written off as an uncollected loss. Without
            // this the fee Transaction above would also net against the
            // print-job debt in outstandingBalance(), understating every AR
            // surface by the fee amount.
            $receivable = AccountsReceivable::query()
                ->where('job_order_id', $jobOrder->id)
                ->where('status', AccountsReceivableStatus::Active->value)
                ->lockForUpdate()
                ->first();

            $receivable?->forceFill([
                'collection_status' => AccountsReceivableCollectionStatus::Cancelled->value,
            ])->save();

            // payment_status is left untouched in both branches — it
            // reflects payment history, not the active state. cancelled_at
            // is the authoritative "no longer actionable" signal.
            $jobOrder->forceFill(['cancelled_at' => now()])->save();

            // A cancelled job order no longer holds its visit open. Without
            // this, cancelling the last outstanding job order would strand
            // the queue entry -- and Frontline's manual Mark Done, which
            // used to be the way out of that, is gone.
            (new SyncQueueEntryStatus)($jobOrder->queueEntry);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job order cancelled.')]);

        return back();
    }
}
