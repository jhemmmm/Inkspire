<?php

namespace App\Actions\POS;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\JobOrder;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Luigel\Paymongo\Facades\Paymongo;
use Luigel\Paymongo\Models\PaymentIntent;
use RuntimeException;

class StartPaymongoPayment
{
    /**
     * Create a PayMongo Payment Intent for a customer paying online by GCash
     * or Maya and record it as a pending_confirmation Transaction, returning
     * the checkout URL.
     *
     * Never creates a Completed Transaction: the transaction is only ever
     * resolved by ConfirmPaymentIntent, called from the signature-verified
     * webhook or manual reconciliation.
     *
     * All three PayMongo API calls go through the Paymongo facade — never
     * the model's own attach()/cancel() convenience methods, which
     * instantiate a fresh `new Paymongo` internally and bypass the facade,
     * making them unmockable in tests (verified by reading
     * vendor/luigel/laravel-paymongo/src/Models/PaymentIntent.php).
     *
     * A PayMongo failure throws before anything is written, so a failed call
     * never leaves a pending transaction with no checkout behind it (CR-02).
     */
    public function __invoke(JobOrder $jobOrder, float $amount, PaymentMethod $paymentMethod, string $returnUrl): ?string
    {
        $intent = Paymongo::paymentIntent()->create([
            'amount' => $amount,
            'currency' => 'PHP',
            'payment_method_allowed' => ['gcash', 'paymaya'],
            'capture_type' => 'automatic',
            'description' => "Job Order #{$jobOrder->id}",
        ]);

        // The trait's create() is typed to return the generic BaseModel
        // — paymentIntent() only sets returnModel = PaymentIntent::class
        // at runtime, so PHPStan can't narrow this by static flow alone
        // (Pitfall 4). Verifying it here, rather than casting, converts
        // an unchecked assumption into an actual runtime guard.
        if (! $intent instanceof PaymentIntent) {
            throw new RuntimeException('PayMongo did not return a payment intent.');
        }

        $checkoutUrl = $this->attachWallet($intent, $paymentMethod, $returnUrl);

        $paymongoPaymentIntentId = (string) $intent->getData()['id'];

        DB::transaction(function () use ($jobOrder, $paymentMethod, $amount, $paymongoPaymentIntentId): void {
            // Locked re-read (CR-01). The PayMongo calls above widen the race
            // window between the caller's unlocked guards and this commit
            // considerably, so terminal state is re-verified here.
            $jobOrder = JobOrder::query()->whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();

            if (($blocker = $jobOrder->paymentBlocker()) !== null) {
                abort(422, __($blocker));
            }

            Transaction::create([
                'job_order_id' => $jobOrder->id,
                'type' => TransactionType::FullPayment->value,
                'payment_method' => $paymentMethod->value,
                'amount' => $amount,
                'status' => TransactionStatus::PendingConfirmation->value,
                'paymongo_payment_intent_id' => $paymongoPaymentIntentId,
            ]);

            $jobOrder->forceFill(['payment_status' => PaymentStatus::PendingConfirmation])->save();
        });

        return $checkoutUrl;
    }

    /**
     * Attach the customer's wallet to a Payment Intent and return the URL of
     * PayMongo's checkout page for it. Also how a checkout that never got as
     * far as the wallet is carried on: same intent, fresh payment method.
     */
    public function attachWallet(PaymentIntent $intent, PaymentMethod $paymentMethod, string $returnUrl): ?string
    {
        $paymongoPaymentMethod = Paymongo::paymentMethod()->create([
            'type' => $paymentMethod === PaymentMethod::Gcash ? 'gcash' : 'paymaya',
        ]);

        $attached = Paymongo::paymentIntent()->attach($intent, (string) $paymongoPaymentMethod->getData()['id'], $returnUrl);

        $checkoutUrl = $attached->getData()['next_action']['redirect']['url'] ?? null;

        return is_string($checkoutUrl) ? $checkoutUrl : null;
    }
}
