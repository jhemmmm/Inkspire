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
    public function __construct(public PriceJobOrder $priceJobOrder) {}

    /**
     * Create a PayMongo Payment Intent for a GCash/Maya payment and record it
     * as a pending_confirmation Transaction, returning the checkout URL.
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
     * never leaves a priced-but-untracked job order behind (CR-02). The
     * optional `$pricing` snapshot is only applied inside the locked
     * transaction below, after PayMongo has succeeded.
     *
     * @param  array<string, mixed>|null  $pricing
     */
    public function __invoke(
        JobOrder $jobOrder,
        float $amount,
        TransactionType $type,
        PaymentMethod $paymentMethod,
        string $returnUrl,
        ?int $recordedBy,
        ?array $pricing = null,
    ): ?string {
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

        $paymongoPaymentMethod = Paymongo::paymentMethod()->create([
            'type' => $paymentMethod === PaymentMethod::Gcash ? 'gcash' : 'paymaya',
        ]);
        $paymongoPaymentMethodId = (string) $paymongoPaymentMethod->getData()['id'];

        $attached = Paymongo::paymentIntent()->attach($intent, $paymongoPaymentMethodId, $returnUrl);

        $paymongoPaymentIntentId = (string) $intent->getData()['id'];

        DB::transaction(function () use ($jobOrder, $pricing, $paymentMethod, $type, $amount, $recordedBy, $paymongoPaymentIntentId): void {
            // Locked re-read (CR-01). The PayMongo calls above widen the race
            // window between the caller's unlocked guards and this commit
            // considerably, so terminal state is re-verified here.
            $jobOrder = JobOrder::query()->whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();

            abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
            abort_if($jobOrder->payment_status === PaymentStatus::Paid, 422, 'This job order is already fully paid.');
            abort_if($jobOrder->payment_status === PaymentStatus::WrittenOff, 422, __('This job order has been written off and cannot accept further payments.'));
            abort_if($jobOrder->payment_status === PaymentStatus::CreditPendingApproval, 422, __('This job order has an On-Credit request awaiting Admin approval. Resolve it before recording a payment.'));

            if ($pricing !== null) {
                ($this->priceJobOrder)($jobOrder, $pricing);
            }

            Transaction::create([
                'job_order_id' => $jobOrder->id,
                'type' => $type->value,
                'payment_method' => $paymentMethod->value,
                'amount' => $amount,
                'status' => TransactionStatus::PendingConfirmation->value,
                'reference_number' => null,
                'paymongo_payment_intent_id' => $paymongoPaymentIntentId,
                'recorded_by' => $recordedBy,
            ]);

            $jobOrder->forceFill(['payment_status' => PaymentStatus::PendingConfirmation])->save();
        });

        return $attached->getData()['next_action']['redirect']['url'] ?? null;
    }
}
