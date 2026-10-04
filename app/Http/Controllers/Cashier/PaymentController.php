<?php

namespace App\Http\Controllers\Cashier;

use App\Actions\POS\PriceJobOrder;
use App\Enums\JobOrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\SavePricingAndPaymentRequest;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\SystemConfiguration;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(public PriceJobOrder $priceJobOrder) {}

    /**
     * Show the Pricing + Payment page for a job order eligible for POS
     * action (POS-01/POS-02/POS-05).
     */
    public function edit(Request $request, JobOrder $jobOrder): Response
    {
        abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
        abort_unless(in_array($jobOrder->status, JobOrderStatus::PAYABLE, true), 422, 'This job order is not ready for pricing.');
        abort_if($jobOrder->payment_status === PaymentStatus::WrittenOff, 422, __('This job order has been written off and cannot accept further payments.'));
        abort_if($jobOrder->payment_status === PaymentStatus::CreditPendingApproval, 422, __('This job order has an On-Credit request awaiting Admin approval. Resolve it before recording a payment.'));

        $jobOrder->loadMissing(['pricingEntry', 'transactions', 'queueEntry.customer:id,name']);

        $amountPaid = (float) $jobOrder->transactions->where('status', TransactionStatus::Completed)->sum('amount');
        $remainingBalance = $jobOrder->total_amount !== null
            ? $jobOrder->outstandingBalance()
            : null;

        // A checkout the customer opened from their tracking page and has
        // not finished. The page warns the Cashier about it, so the same
        // money is not taken twice.
        $pendingPaymongoTransaction = $jobOrder->transactions
            ->where('status', TransactionStatus::PendingConfirmation)
            ->sortByDesc('id')
            ->first();

        return Inertia::render('cashier/JobOrderPayment', [
            'jobOrder' => $jobOrder,
            'pricingEntries' => PricingEntry::query()->where('is_active', true)->get(['id', 'name', 'base_price']),
            'rushFeePercentage' => SystemConfiguration::getFloat('rush_fee_percentage', 0.0),
            'discountCapPercentage' => SystemConfiguration::getFloat('discount_cap_percentage', 20.0),
            'discountCapFlatAmount' => SystemConfiguration::getFloat('discount_cap_flat_amount', 500.0),
            'pricingLocked' => ! $jobOrder->pricingIsEditable(),
            'amountPaid' => $amountPaid,
            'remainingBalance' => $remainingBalance,
            'pendingPaymongoAmount' => $pendingPaymongoTransaction !== null ? (float) $pendingPaymongoTransaction->amount : null,
            'pendingPaymongoMethod' => $pendingPaymongoTransaction?->payment_method?->value,
        ]);
    }

    /**
     * Save the job order's pricing (first visit only) and record a payment
     * taken at the counter, full or as a down payment (POS-01/POS-02/POS-05).
     *
     * Every method is recorded directly, as a Completed transaction. Bank
     * Transfer, GCash and Maya carry the reference number the customer
     * shows; PayMongo is only ever used by the customer's own online
     * checkout (OnlinePaymentController), never from here.
     *
     * The server always recomputes total_amount via ComputeJobOrderPrice —
     * a client-submitted total is never read or trusted (T-05-03).
     */
    public function store(SavePricingAndPaymentRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        if (($blocker = $jobOrder->paymentBlocker()) !== null) {
            abort(422, __($blocker));
        }

        abort_unless(in_array($jobOrder->status, JobOrderStatus::PAYABLE, true), 422, 'This job order is not ready for pricing.');

        $result = DB::transaction(function () use ($request, $jobOrder): array {
            // Locked re-read (CR-01) — the terminal-state guards above ran
            // against the unlocked, route-bound instance. Without re-checking
            // them here, a WriteOffApprovalController::approve() landing
            // between that check and this commit is silently overwritten by
            // the payment_status write below, reopening a just-booked loss.
            // Same idempotency boundary every other payment_status writer
            // already uses.
            $jobOrder = JobOrder::query()->whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();

            if (($blocker = $jobOrder->paymentBlocker()) !== null) {
                abort(422, __($blocker));
            }

            $amountPaid = (float) $jobOrder->transactions()->where('status', TransactionStatus::Completed->value)->sum('amount');

            // Pricing is editable per the single shared predicate,
            // JobOrder::pricingIsEditable() -- NOT "total_amount is null".
            // The two are different states and the gap between them
            // silently lost money: a job order can already carry a total
            // with no transaction against it (seeded rows, or a rejected
            // On-Credit request, which prices the order and then leaves it
            // unpaid), and it used to entirely miss the On-Credit case too
            // (an Admin-approved On-Credit order has zero transactions, so
            // the old "no transactions" check said it was still editable).
            // edit() shows the pricing form in exactly the states this
            // predicate reports editable, so the Cashier could tick Rush
            // Fee, watch the preview total rise, and submit, while this
            // block refused to run and charged the stale total with
            // `rush_fee_amount` still at zero. The receipt then printed a
            // rush order with no rush fee on it.
            //
            // Matching edit()'s condition exactly is what keeps the form the
            // Cashier sees and the figures the server saves in agreement.
            // A pending GCash/Maya intent counts as a transaction, so a
            // repricing cannot slip underneath a checkout the customer has
            // open either.
            if ($jobOrder->pricingIsEditable()) {
                ($this->priceJobOrder)($jobOrder, $request->validated());
            }

            $isDownPayment = $request->validated('payment_type') === 'down';
            $remainingBalance = $jobOrder->outstandingBalance();
            $transactionAmount = $isDownPayment
                ? (float) $request->validated('down_payment_amount')
                : $remainingBalance;

            Transaction::create([
                'job_order_id' => $jobOrder->id,
                'type' => $isDownPayment ? TransactionType::DownPayment->value : TransactionType::FullPayment->value,
                'payment_method' => $request->validated('payment_method'),
                'amount' => $transactionAmount,
                'status' => TransactionStatus::Completed->value,
                'reference_number' => $request->validated('reference_number'),
                'recorded_by' => $request->user()->id,
                'confirmed_at' => now(),
            ]);

            $newAmountPaid = $amountPaid + $transactionAmount;

            $jobOrder->forceFill([
                'payment_status' => $newAmountPaid >= (float) $jobOrder->total_amount
                    ? PaymentStatus::Paid->value
                    : PaymentStatus::PartiallyPaid->value,
            ])->save();

            return [
                'is_down_payment' => $isDownPayment,
                'remaining_balance' => round((float) $jobOrder->total_amount - $newAmountPaid, 2),
            ];
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => $result['is_down_payment']
            ? __('Down payment recorded. Remaining balance: ₱:balance.', ['balance' => number_format($result['remaining_balance'], 2)])
            : __('Payment recorded. Job order is fully paid.'),
        ]);

        // Both land on the Receipt page (POS-06). A down payment used to
        // return here instead, which left the customer who just handed over
        // real money with no printable proof of it -- the receipt already
        // shows Amount Paid and the remaining Balance, so it answers the
        // "what do I still owe" question this page was kept open for.
        return to_route('cashier.job-orders.receipt.show', $jobOrder);
    }
}
