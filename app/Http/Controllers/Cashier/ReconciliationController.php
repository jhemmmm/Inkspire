<?php

namespace App\Http\Controllers\Cashier;

use App\Actions\POS\ConfirmPaymentIntent;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use App\Services\Reports\ReportBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Luigel\Paymongo\Facades\Paymongo;
use Luigel\Paymongo\Models\PaymentIntent;
use RuntimeException;

class ReconciliationController extends Controller
{
    public function __construct(public ConfirmPaymentIntent $confirmPaymentIntent) {}

    /**
     * List job orders awaiting GCash/Maya payment confirmation (POS-04) —
     * Accounting Staff's dashboard, D-12's "filtered list view".
     *
     * Below it, the Financial Summary report's revenue and expenses chart
     * over the last 14 days.
     */
    public function index(Request $request, ReportBuilder $reportBuilder): Response
    {
        return Inertia::render('accounting-staff/Dashboard', [
            'jobOrders' => JobOrder::query()
                ->where('payment_status', PaymentStatus::PendingConfirmation)
                ->whereNull('cancelled_at')
                ->with([
                    'queueEntry.customer:id,name',
                    'transactions' => fn ($query) => $query
                        ->where('status', TransactionStatus::PendingConfirmation)
                        ->latest(),
                ])
                ->orderBy('created_at')
                ->get(['id', 'description', 'payment_status', 'total_amount', 'queue_entry_id', 'created_at']),
            'cashFlow' => $reportBuilder->cashFlow(),
        ]);
    }

    /**
     * Manually check a stalled GCash/Maya payment against PayMongo when the
     * webhook hasn't arrived yet (POS-04, D-12). Mounted identically from
     * both the Cashier's and Accounting Staff's own route groups (see
     * routes/portals.php) — both converge on this single controller method,
     * which resolves through the exact same ConfirmPaymentIntent
     * idempotency boundary a webhook arriving mid-flight also uses
     * (T-05-02), so a race between this action and the webhook can never
     * double-credit the job order.
     */
    public function store(Request $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
        abort_unless($jobOrder->payment_status === PaymentStatus::PendingConfirmation, 422, 'This job order is not awaiting payment confirmation.');

        $transaction = $jobOrder->transactions()
            ->where('status', TransactionStatus::PendingConfirmation)
            ->latest()
            ->firstOrFail();

        $intent = Paymongo::paymentIntent()->find((string) $transaction->paymongo_payment_intent_id);

        // The trait's find() is typed to return the generic BaseModel —
        // paymentIntent() only sets returnModel = PaymentIntent::class at
        // runtime, so PHPStan can't narrow this by static flow alone
        // (Pitfall 4, matching PaymentController's own guard).
        if (! $intent instanceof PaymentIntent) {
            throw new RuntimeException('PayMongo did not return a payment intent.');
        }

        // Payment Intent status vocabulary [MEDIUM confidence — RESEARCH.md
        // flags PayMongo's exact status set as unverified against a real
        // sandbox delivery: awaiting_payment_method / awaiting_next_action /
        // processing / succeeded / cancelled. There is no distinct
        // "expired" status exposed by the API itself, so `cancelled` is
        // treated as this plan's failed/expired outcome and every other
        // non-terminal status as still pending — re-verify once the user's
        // PayMongo sandbox keys are available].
        $status = (string) ($intent->getData()['status'] ?? '');

        if ($status === 'succeeded') {
            ($this->confirmPaymentIntent)($transaction, true);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment confirmed.')]);

            // Only the Cashier's own "Scan to Pay" sub-view expects a
            // receipt redirect on success (UI-SPEC §1); Accounting Staff
            // has no route access to cashier.job-orders.receipt.show
            // (role:cashier middleware only, T-05-12) — sending them there
            // would 403, so their dashboard action just returns to the
            // Accounting Staff Dashboard list instead.
            return $request->user()?->role === UserRole::Cashier
                ? to_route('cashier.job-orders.receipt.show', $jobOrder)
                : back();
        }

        if ($status === 'cancelled') {
            ($this->confirmPaymentIntent)($transaction, false);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This payment failed or expired. Choose a different payment method to continue.'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('Payment not received yet. Try again in a moment, or ask the customer to confirm they completed the payment.'),
        ]);

        return back();
    }
}
