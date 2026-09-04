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
                $amountPaid = $jobOrder->transactions()->where('status', TransactionStatus::Completed)->sum('amount');

                $jobOrder->forceFill([
                    'payment_status' => $amountPaid >= $jobOrder->total_amount
                        ? PaymentStatus::Paid
                        : PaymentStatus::PartiallyPaid,
                ])->save();
            }

            return $locked;
        });
    }
}
