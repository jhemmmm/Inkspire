<?php

namespace App\Http\Controllers\Cashier;

use App\Actions\POS\CancelPaymongoPayment;
use App\Actions\POS\SettlePaymongoPayment;
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
use Throwable;

class ReconciliationController extends Controller
{
    public function __construct(public SettlePaymongoPayment $settlePaymongoPayment) {}

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
                ->orderByDesc('is_rush')
                ->orderBy('created_at')
                ->get(['id', 'number', 'description', 'payment_status', 'total_amount', 'queue_entry_id', 'created_at', 'is_rush']),
            'cashFlow' => fn () => $reportBuilder->cashFlow(),
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

        $transaction = $jobOrder->pendingPaymongoTransaction() ?? abort(404);

        // Resolves the transaction for a final status (succeeded, or
        // cancelled as the failed/expired outcome); anything else is still
        // pending and only reported back here.
        $status = (string) (($this->settlePaymongoPayment)($transaction)->getData()['status'] ?? '');

        if ($status === 'succeeded') {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment confirmed.')]);

            // Only a Cashier is sent on to the receipt; Accounting Staff
            // has no route access to cashier.job-orders.receipt.show
            // (role:cashier middleware only, T-05-12) — sending them there
            // would 403, so their dashboard action just returns to the
            // Accounting Staff Dashboard list instead.
            return $request->user()?->role === UserRole::Cashier
                ? to_route('cashier.job-orders.receipt.show', $jobOrder)
                : back();
        }

        if ($status === 'cancelled') {
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

    /**
     * Withdraw a checkout the customer opened online and never finished, so
     * the Cashier can take the payment at the counter instead. Cashier only:
     * while a checkout is open every other payment path refuses (see
     * JobOrder::paymentBlocker()), and this is the way out of it.
     *
     * A checkout PayMongo reports as paid is confirmed rather than
     * cancelled, and one still processing is left alone. When PayMongo
     * cannot be reached nothing changes, so the customer is never left able
     * to pay a checkout this side has already written off.
     */
    public function destroy(JobOrder $jobOrder, CancelPaymongoPayment $cancelPaymongoPayment): RedirectResponse
    {
        abort_unless($jobOrder->payment_status === PaymentStatus::PendingConfirmation, 422, 'This job order is not awaiting payment confirmation.');

        $transaction = $jobOrder->pendingPaymongoTransaction() ?? abort(404);

        try {
            $status = $cancelPaymongoPayment($transaction);
        } catch (Throwable $e) {
            report($e);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('The online payment could not be cancelled. Check its status, then try again.'),
            ]);

            return back();
        }

        Inertia::flash('toast', match ($status) {
            'cancelled' => ['type' => 'success', 'message' => __('Online payment cancelled. You can take the payment here now.')],
            'succeeded' => ['type' => 'success', 'message' => __('The customer already paid online. Payment confirmed.')],
            default => ['type' => 'error', 'message' => __("The customer's payment is being processed and cannot be cancelled. Check its status in a moment.")],
        });

        return back();
    }
}
