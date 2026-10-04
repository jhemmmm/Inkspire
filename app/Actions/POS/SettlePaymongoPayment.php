<?php

namespace App\Actions\POS;

use App\Models\Transaction;
use Luigel\Paymongo\Facades\Paymongo;
use Luigel\Paymongo\Models\PaymentIntent;
use RuntimeException;

class SettlePaymongoPayment
{
    public function __construct(public ConfirmPaymentIntent $confirmPaymentIntent) {}

    /**
     * Ask PayMongo where a pending GCash/Maya transaction's Payment Intent
     * stands, and resolve the transaction when PayMongo has a final answer.
     *
     * This is the pull side of the webhook: the tracking page, the pay
     * endpoint and manual reconciliation all call it, so a payment is
     * confirmed even when the webhook is late or cannot reach this server.
     * Resolution goes through ConfirmPaymentIntent, the single idempotency
     * boundary, so racing the webhook can never credit an order twice.
     *
     * Payment Intent status vocabulary [MEDIUM confidence, unverified
     * against a live sandbox delivery]: awaiting_payment_method /
     * awaiting_next_action / processing / succeeded / cancelled. There is no
     * distinct "expired" status, so `cancelled` is the failed/expired
     * outcome and every other status leaves the transaction pending.
     *
     * The intent is returned so a caller can read its status or carry on
     * with its checkout.
     */
    public function __invoke(Transaction $transaction): PaymentIntent
    {
        $intent = Paymongo::paymentIntent()->find((string) $transaction->paymongo_payment_intent_id);

        // The trait's find() is typed to return the generic BaseModel;
        // paymentIntent() only sets returnModel = PaymentIntent::class at
        // runtime, so this guard is what narrows it (Pitfall 4).
        if (! $intent instanceof PaymentIntent) {
            throw new RuntimeException('PayMongo did not return a payment intent.');
        }

        $status = (string) ($intent->getData()['status'] ?? '');

        if ($status === 'succeeded') {
            ($this->confirmPaymentIntent)($transaction, true);
        }

        if ($status === 'cancelled') {
            ($this->confirmPaymentIntent)($transaction, false);
        }

        return $intent;
    }
}
