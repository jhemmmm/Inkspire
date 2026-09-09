<?php

namespace App\Actions\POS;

use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\JobOrder;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class ConfirmPaymentIntent
{
    /**
     * Confirm a pending GCash/Maya payment, called by either the signed
     * webhook receiver (this plan) or Plan 05-04's manual reconciliation
     * action. Idempotent by construction: a locked re-read + guard means a
     * webhook arriving while reconciliation is mid-flight (or vice versa,
     * or the same webhook delivered twice) can never double-credit the job
     * order (T-05-02). This is the single idempotency boundary — never
     * duplicate this guard logic elsewhere.
     *
     * Both the Completed and Failed/expired branches also protect an
     * already-WrittenOff job order's terminal payment_status: the
     * Transaction itself still resolves to Completed/Failed as normal
     * (money still moved or didn't), but the job order's payment_status is
     * never overwritten once it's a booked loss. Silent no-op, not an
     * abort — this is a background job/webhook handler, not a user-facing
     * request.
     */
    public function __invoke(Transaction $transaction, bool $succeeded): Transaction
    {
        return DB::transaction(function () use ($transaction, $succeeded): Transaction {
            $locked = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->first();

            if ($locked->status !== TransactionStatus::PendingConfirmation) {
                return $locked; // already resolved by another caller — no-op
            }

            $locked->forceFill([
                'status' => $succeeded ? TransactionStatus::Completed : TransactionStatus::Failed,
                'confirmed_at' => now(),
            ])->save();

            if ($locked->status === TransactionStatus::Completed) {
                $jobOrder = JobOrder::query()->whereKey($locked->job_order_id)->lockForUpdate()->first();

                if ($jobOrder->payment_status !== PaymentStatus::WrittenOff) {
                    $amountPaid = $jobOrder->transactions()->where('status', TransactionStatus::Completed)->sum('amount');

                    $jobOrder->forceFill([
                        'payment_status' => $amountPaid >= $jobOrder->total_amount
                            ? PaymentStatus::Paid
                            : PaymentStatus::PartiallyPaid,
                    ])->save();
                }
            } else {
                // Failed/expired confirmation (D-13, Plan 05-04) — recompute
                // payment_status from any OTHER completed transactions for
                // this job order rather than leaving it stuck on
                // PendingConfirmation forever, so the Cashier/Accounting
                // Staff sees an actionable Unpaid/PartiallyPaid state and
                // can choose a different payment method.
                $jobOrder = JobOrder::query()->whereKey($locked->job_order_id)->lockForUpdate()->first();

                if ($jobOrder->payment_status !== PaymentStatus::WrittenOff) {
                    $amountPaid = $jobOrder->transactions()->where('status', TransactionStatus::Completed)->sum('amount');

                    $jobOrder->forceFill([
                        'payment_status' => $amountPaid > 0
                            ? PaymentStatus::PartiallyPaid
                            : PaymentStatus::Unpaid,
                    ])->save();
                }
            }

            return $locked;
        });
    }
}
