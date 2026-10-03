<?php

namespace App\Http\Controllers\Public;

use App\Actions\POS\ConfirmPaymentIntent;
use App\Actions\POS\StartPaymongoPayment;
use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\PayOnlineRequest;
use App\Models\JobOrder;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Luigel\Paymongo\Facades\Paymongo;
use Luigel\Paymongo\Models\PaymentIntent;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class OnlinePaymentController extends Controller
{
    public function __construct(
        public StartPaymongoPayment $startPaymongoPayment,
        public ConfirmPaymentIntent $confirmPaymentIntent,
    ) {}

    /**
     * Let a customer pay their full outstanding balance by GCash or Maya
     * from their tracking page, or carry on with a checkout already open.
     *
     * Always the full balance, never a part payment: any payment that
     * unlocks production is a decision only the counter may make. The amount
     * is the server's own outstandingBalance(); the request carries only the
     * wallet. The webhook and ConfirmPaymentIntent are untouched, so a
     * confirmed payment clears the production gate exactly as a Cashier-taken
     * one does.
     */
    public function store(PayOnlineRequest $request, string $token): RedirectResponse|SymfonyResponse
    {
        $jobOrder = JobOrder::query()
            ->where('tracking_token', $token)
            ->firstOrFail(['id', 'number', 'status', 'cancelled_at', 'total_amount', 'payment_status']);

        $paymentMethod = PaymentMethod::from($request->validated('payment_method'));
        $trackingUrl = route('public.tracking.token', ['token' => $token]);

        try {
            $destination = match ($jobOrder->onlinePaymentState()) {
                'due' => $this->startCheckout($jobOrder, $paymentMethod, $trackingUrl),
                'pending' => $this->continueCheckout($jobOrder, $paymentMethod, $trackingUrl),
                default => null,
            };
        } catch (Throwable $e) {
            report($e);

            return redirect($trackingUrl)->withErrors([
                'payment' => __("We couldn't start the payment. Please try again, or pay at the shop."),
            ]);
        }

        // Null means there is nothing to send the customer to PayMongo for;
        // the tracking page shows the truth (paid, nothing due, or confirmed).
        return $destination === null ? redirect($trackingUrl) : Inertia::location($destination);
    }

    /**
     * Open a new checkout for the full balance.
     */
    private function startCheckout(JobOrder $jobOrder, PaymentMethod $paymentMethod, string $trackingUrl): ?string
    {
        return ($this->startPaymongoPayment)(
            $jobOrder,
            $jobOrder->outstandingBalance(),
            TransactionType::FullPayment,
            $paymentMethod,
            $trackingUrl,
            null,
        );
    }

    /**
     * Carry on with the checkout already open for this job order. Never
     * creates a second intent for a pending transaction: that would charge
     * the customer twice.
     *
     * PayMongo's status vocabulary is unverified against the sandbox, as
     * ReconciliationController already notes: `succeeded` and
     * `awaiting_next_action` are treated as known, anything else as an
     * intent that needs a fresh payment method attached.
     */
    private function continueCheckout(JobOrder $jobOrder, PaymentMethod $paymentMethod, string $trackingUrl): ?string
    {
        $transaction = $jobOrder->transactions()
            ->where('status', TransactionStatus::PendingConfirmation)
            ->latest('id')
            ->first();

        if (! $transaction instanceof Transaction) {
            return null;
        }

        $intent = Paymongo::paymentIntent()->find((string) $transaction->paymongo_payment_intent_id);

        if (! $intent instanceof PaymentIntent) {
            throw new RuntimeException('PayMongo did not return a payment intent.');
        }

        $status = (string) ($intent->getData()['status'] ?? '');

        if ($status === 'succeeded') {
            ($this->confirmPaymentIntent)($transaction, true);

            return null;
        }

        if ($status === 'awaiting_next_action') {
            $existingUrl = $intent->getData()['next_action']['redirect']['url'] ?? null;

            if (is_string($existingUrl)) {
                return $existingUrl;
            }
        }

        $paymongoPaymentMethod = Paymongo::paymentMethod()->create([
            'type' => $paymentMethod === PaymentMethod::Gcash ? 'gcash' : 'paymaya',
        ]);
        $attached = Paymongo::paymentIntent()->attach($intent, (string) $paymongoPaymentMethod->getData()['id'], $trackingUrl);

        if ($transaction->payment_method !== $paymentMethod) {
            $transaction->forceFill(['payment_method' => $paymentMethod])->save();
        }

        $newUrl = $attached->getData()['next_action']['redirect']['url'] ?? null;

        return is_string($newUrl) ? $newUrl : null;
    }
}
