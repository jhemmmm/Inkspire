<?php

namespace App\Http\Controllers\Public;

use App\Actions\POS\CancelPaymongoPayment;
use App\Actions\POS\SettlePaymongoPayment;
use App\Actions\POS\StartPaymongoPayment;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\PayOnlineRequest;
use App\Models\JobOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class OnlinePaymentController extends Controller
{
    public function __construct(
        public StartPaymongoPayment $startPaymongoPayment,
        public SettlePaymongoPayment $settlePaymongoPayment,
        public CancelPaymongoPayment $cancelPaymongoPayment,
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
                'due' => ($this->startPaymongoPayment)($jobOrder, $jobOrder->outstandingBalance(), $paymentMethod, $trackingUrl),
                'pending' => $this->continueCheckout($jobOrder, $paymentMethod, $trackingUrl),
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
     * Carry on with the checkout already open for this job order. Never
     * creates a second intent for a pending transaction: that would charge
     * the customer twice.
     *
     * The pending page offers both wallets. The one the checkout was
     * opened with carries it on; the other one switches: the open checkout
     * is withdrawn at PayMongo first (CancelPaymongoPayment), and only once
     * that is done is a new one started for the wallet now chosen. If it
     * cannot be withdrawn, because it was paid or is processing, nothing
     * new is opened.
     *
     * SettlePaymongoPayment resolves the two final statuses: `succeeded`
     * confirms the payment, and `cancelled` fails it, which puts the order
     * back to "due" where the customer picks a wallet again. Either way
     * there is nowhere to send the customer. The two statuses that can
     * still be paid are handled below; anything else (`processing` above
     * all, a payment in flight) is left exactly as it is.
     */
    private function continueCheckout(JobOrder $jobOrder, PaymentMethod $paymentMethod, string $trackingUrl): ?string
    {
        $transaction = $jobOrder->pendingPaymongoTransaction();

        if ($transaction === null) {
            return null;
        }

        if ($transaction->payment_method !== $paymentMethod) {
            if (($this->cancelPaymongoPayment)($transaction) !== 'cancelled' || $jobOrder->refresh()->onlinePaymentState() !== 'due') {
                return null;
            }

            return ($this->startPaymongoPayment)($jobOrder, $jobOrder->outstandingBalance(), $paymentMethod, $trackingUrl);
        }

        $intent = ($this->settlePaymongoPayment)($transaction);
        $status = (string) ($intent->getData()['status'] ?? '');

        if ($status === 'awaiting_next_action') {
            $existingUrl = $intent->getData()['next_action']['redirect']['url'] ?? null;

            return is_string($existingUrl) ? $existingUrl : null;
        }

        // The earlier attempt never got as far as the wallet. Same intent,
        // fresh payment method.
        if ($status === 'awaiting_payment_method') {
            return $this->startPaymongoPayment->attachWallet($intent, $transaction->payment_method, $trackingUrl);
        }

        return null;
    }
}
