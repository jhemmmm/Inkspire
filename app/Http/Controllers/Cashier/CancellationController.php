<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\JobOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\CancelJobOrderRequest;
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
     */
    public function store(CancelJobOrderRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_if($jobOrder->cancelled_at !== null, 422, 'This job order is already cancelled.');
        abort_if($jobOrder->payment_status === PaymentStatus::Paid, 422, 'This job order is already fully paid and cannot be cancelled from here.');
        abort_if(
            $jobOrder->payment_status === PaymentStatus::PendingConfirmation,
            422,
            __('This job order has a payment awaiting confirmation. Resolve it before cancelling.'),
        );

        $designStarted = in_array($jobOrder->status, [
            JobOrderStatus::InDesign,
            JobOrderStatus::PendingReview,
            JobOrderStatus::DesignApproved,
        ], true);

        DB::transaction(function () use ($jobOrder, $request, $designStarted): void {
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

            // payment_status is left untouched in both branches — it
            // reflects payment history, not the active state. cancelled_at
            // is the authoritative "no longer actionable" signal.
            $jobOrder->forceFill(['cancelled_at' => now()])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job order cancelled.')]);

        return back();
    }
}
