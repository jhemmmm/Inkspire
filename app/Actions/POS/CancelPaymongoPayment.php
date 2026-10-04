<?php

namespace App\Actions\POS;

use App\Models\Transaction;
use Luigel\Paymongo\Facades\Paymongo;

class CancelPaymongoPayment
{
    /**
     * Intent statuses in which the customer has not paid and the checkout
     * can still be withdrawn. `processing` is deliberately absent: money is
     * already moving, and it resolves on its own.
     *
     * @var array<int, string>
     */
    private const array CANCELLABLE = ['awaiting_payment_method', 'awaiting_next_action'];

    public function __construct(
        public SettlePaymongoPayment $settlePaymongoPayment,
        public ConfirmPaymentIntent $confirmPaymentIntent,
    ) {}

    /**
     * Withdraw a GCash/Maya checkout the customer opened and has not paid,
     * so the order can be paid another way: at the counter, or with the
     * other wallet.
     *
     * PayMongo is asked first. A checkout it reports as paid is confirmed
     * instead, and one already cancelled is closed, exactly as
     * SettlePaymongoPayment does. Only an unpaid one is cancelled, and at
     * PayMongo before anything is written here: if that call throws, the
     * transaction stays pending, so a checkout the customer can still pay
     * is never marked failed on this side.
     *
     * Returns the intent's final status: `cancelled` once the checkout is
     * closed, `succeeded` when it turned out to be paid, anything else
     * (`processing`) when it was left alone.
     */
    public function __invoke(Transaction $transaction): string
    {
        $intent = ($this->settlePaymongoPayment)($transaction);
        $status = (string) ($intent->getData()['status'] ?? '');

        if (! in_array($status, self::CANCELLABLE, true)) {
            return $status;
        }

        Paymongo::paymentIntent()->cancel($intent);

        ($this->confirmPaymentIntent)($transaction, false);

        return 'cancelled';
    }
}
