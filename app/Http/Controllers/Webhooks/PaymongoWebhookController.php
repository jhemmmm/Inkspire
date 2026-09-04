<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\POS\ConfirmPaymentIntent;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymongoWebhookController extends Controller
{
    public function __construct(public ConfirmPaymentIntent $confirmPaymentIntent) {}

    /**
     * Receive a signature-verified PayMongo webhook delivery (POS-03) and
     * confirm the matching pending Transaction via ConfirmPaymentIntent —
     * the single idempotency boundary this controller shares with Plan
     * 05-04's manual reconciliation action.
     *
     * Signature verification already happened in the `paymongo.signature`
     * middleware before this method runs (see routes/web.php); a request
     * with a missing or invalid signature never reaches here.
     */
    public function __invoke(Request $request): Response
    {
        // PayMongo's event envelope wraps the underlying payment resource
        // under data.attributes.data.attributes [MEDIUM confidence —
        // RESEARCH.md flags the exact payload shape as unverified against a
        // real sandbox delivery; re-confirm once test-mode keys are
        // available and adjust this parsing if the shape differs].
        $eventType = $request->json('data.attributes.type');
        $paymentIntentId = $request->json('data.attributes.data.attributes.payment_intent_id');

        if (! is_string($paymentIntentId)) {
            return response()->noContent();
        }

        $transaction = Transaction::query()
            ->where('paymongo_payment_intent_id', $paymentIntentId)
            ->first();

        if ($transaction === null) {
            // Unrecognized intent (e.g. a stale test delivery) — acknowledge
            // with 2xx and write nothing, so PayMongo doesn't retry forever
            // for a transaction this app never created (T-05-11).
            return response()->noContent();
        }

        ($this->confirmPaymentIntent)($transaction, $eventType === 'payment.paid');

        return response()->noContent();
    }
}
