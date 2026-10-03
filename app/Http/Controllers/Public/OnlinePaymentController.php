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
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Luigel\Paymongo\Facades\Paymongo;
use Luigel\Paymongo\Models\PaymentIntent;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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

        // One checkout at a time per job order. PayMongo is called before the
        // pending transaction is written, so two submits arriving together
        // would otherwise each open an intent.
        $lock = Cache::lock('online-payment:'.$jobOrder->id, 30);

        if (! $lock->get()) {
            return redirect($trackingUrl);
        }

        try {
            $destination = match ($jobOrder->onlinePaymentState()) {
                'due' => $this->startCheckout($jobOrder, $paymentMethod, $trackingUrl),
                'pending' => $this->continueCheckout($jobOrder, $trackingUrl),
                default => null,
            };
        } catch (HttpExceptionInterface) {
            // StartPaymongoPayment's locked re-check refused: the order was
            // paid, cancelled or put on credit while PayMongo was being
            // called. Nothing was written, and the tracking page shows which.
            return redirect($trackingUrl);
        } catch (Throwable $e) {
            report($e);

            return redirect($trackingUrl)->withErrors([
                'payment' => __("We couldn't start the payment. Please try again, or pay at the shop."),
            ]);
        } finally {
            $lock->release();
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
     * The wallet is the one the customer chose when they opened the
     * checkout, read from the transaction. The pending page has a single
     * button and no wallet picker, so the posted method is not used here.
     *
     * PayMongo's status vocabulary is unverified against the sandbox, as
     * ReconciliationController already notes. Each status handled below is
     * named; anything else (`processing` above all, a payment in flight) is
     * left exactly as it is.
     */
    private function continueCheckout(JobOrder $jobOrder, string $trackingUrl): ?string
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

        // Closed at PayMongo and can no longer be paid. Failing it puts the
        // order back to "due", where the customer picks a wallet again.
        if ($status === 'cancelled') {
            ($this->confirmPaymentIntent)($transaction, false);

            return null;
        }

        if ($status === 'awaiting_next_action') {
            $existingUrl = $intent->getData()['next_action']['redirect']['url'] ?? null;

            return is_string($existingUrl) ? $existingUrl : null;
        }

        // The earlier attempt never got as far as the wallet. Same intent,
        // fresh payment method.
        if ($status === 'awaiting_payment_method') {
            $paymongoPaymentMethod = Paymongo::paymentMethod()->create([
                'type' => $transaction->payment_method === PaymentMethod::Gcash ? 'gcash' : 'paymaya',
            ]);
            $attached = Paymongo::paymentIntent()->attach($intent, (string) $paymongoPaymentMethod->getData()['id'], $trackingUrl);
            $newUrl = $attached->getData()['next_action']['redirect']['url'] ?? null;

            return is_string($newUrl) ? $newUrl : null;
        }

        return null;
    }
}
