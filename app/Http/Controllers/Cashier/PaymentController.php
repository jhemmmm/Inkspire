<?php

namespace App\Http\Controllers\Cashier;

use App\Actions\POS\ComputeJobOrderPrice;
use App\Enums\JobOrderStatus;
use App\Enums\PaymentMethod;
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
use Luigel\Paymongo\Facades\Paymongo;
use Luigel\Paymongo\Models\PaymentIntent;
use RuntimeException;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(public ComputeJobOrderPrice $computeJobOrderPrice) {}

    /**
     * Show the Pricing + Payment page for a job order eligible for POS
     * action (POS-01/POS-02/POS-05).
     */
    public function edit(Request $request, JobOrder $jobOrder): Response
    {
        abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
        abort_unless(
            in_array($jobOrder->status, [
                JobOrderStatus::ReadyForProduction,
                JobOrderStatus::DesignApproved,
                JobOrderStatus::ForProduction,
                JobOrderStatus::Printing,
                JobOrderStatus::QualityCheck,
                JobOrderStatus::ReadyForPickup,
            ], true),
            422,
            'This job order is not ready for pricing.',
        );
        abort_if($jobOrder->payment_status === PaymentStatus::WrittenOff, 422, __('This job order has been written off and cannot accept further payments.'));

        $jobOrder->loadMissing(['pricingEntry', 'transactions', 'queueEntry.customer:id,name']);

        $amountPaid = (float) $jobOrder->transactions->where('status', TransactionStatus::Completed)->sum('amount');
        $remainingBalance = $jobOrder->total_amount !== null
            ? round((float) $jobOrder->total_amount - $amountPaid, 2)
            : null;

        // The most recent still-pending GCash/Maya transaction — sourced
        // from the actual persisted Transaction row rather than the QR
        // sub-view's local form state, since a full Inertia redirect resets
        // every local ref (payment method/type/amount) back to its default.
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
            'hasExistingTransactions' => $jobOrder->transactions->isNotEmpty(),
            'amountPaid' => $amountPaid,
            'remainingBalance' => $remainingBalance,
            // Flashed after a GCash/Maya "Generate QR Code" submission
            // (POS-03) — read once, then gone on the next request, matching
            // this app's existing session-flash-to-prop convention.
            'paymongoRedirectUrl' => session('redirectUrl'),
            'pendingPaymongoAmount' => $pendingPaymongoTransaction !== null ? (float) $pendingPaymongoTransaction->amount : null,
            'pendingPaymongoMethod' => $pendingPaymongoTransaction?->payment_method?->value,
        ]);
    }

    /**
     * Save the job order's pricing (first visit only) and record a Cash or
     * Bank Transfer payment, full or as a down payment (POS-01/POS-02/POS-05).
     *
     * The server always recomputes total_amount via ComputeJobOrderPrice —
     * a client-submitted total is never read or trusted (T-05-03).
     */
    public function store(SavePricingAndPaymentRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
        abort_unless(
            in_array($jobOrder->status, [
                JobOrderStatus::ReadyForProduction,
                JobOrderStatus::DesignApproved,
                JobOrderStatus::ForProduction,
                JobOrderStatus::Printing,
                JobOrderStatus::QualityCheck,
                JobOrderStatus::ReadyForPickup,
            ], true),
            422,
            'This job order is not ready for pricing.',
        );
        abort_if($jobOrder->payment_status === PaymentStatus::Paid, 422, 'This job order is already fully paid.');
        abort_if($jobOrder->payment_status === PaymentStatus::WrittenOff, 422, __('This job order has been written off and cannot accept further payments.'));

        $paymentMethod = $request->validated('payment_method');

        if (in_array($paymentMethod, [PaymentMethod::Gcash->value, PaymentMethod::Maya->value], true)) {
            return $this->storePaymongoIntent($request, $jobOrder, $paymentMethod);
        }

        $result = DB::transaction(function () use ($request, $jobOrder): array {
            $amountPaid = (float) $jobOrder->transactions()->where('status', TransactionStatus::Completed->value)->sum('amount');

            if ($jobOrder->total_amount === null) {
                $computed = ($this->computeJobOrderPrice)(
                    (float) $request->validated('line_amount'),
                    (bool) $request->validated('rush_fee_applied'),
                    $request->validated('discount_type'),
                    $request->validated('discount_value') !== null ? (float) $request->validated('discount_value') : null,
                );

                $jobOrder->forceFill([
                    'pricing_entry_id' => $request->validated('pricing_entry_id'),
                    'base_price_snapshot' => $computed['base_price_snapshot'],
                    'rush_fee_applied' => $request->validated('rush_fee_applied'),
                    'rush_fee_amount' => $computed['rush_fee_amount'],
                    'discount_type' => $request->validated('discount_type'),
                    'discount_value' => $request->validated('discount_value'),
                    'discount_amount' => $computed['discount_amount'],
                    'total_amount' => $computed['total_amount'],
                ]);
            }

            $isDownPayment = $request->validated('payment_type') === 'down';
            $remainingBalance = round((float) $jobOrder->total_amount - $amountPaid, 2);
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

        // A full-payment submission lands on the Receipt page (POS-06); a
        // down payment keeps returning to this page to show the updated
        // remaining balance.
        return $result['is_down_payment']
            ? back()
            : to_route('cashier.job-orders.receipt.show', $jobOrder);
    }

    /**
     * Create a PayMongo Payment Intent for a GCash/Maya payment (POS-03),
     * pricing the job order first if this is the first pricing/payment
     * visit — the same server-authoritative snapshot logic the Cash/Bank
     * Transfer branch above uses. Unlike Cash/Bank Transfer, this never
     * creates a Completed Transaction directly: the transaction starts
     * pending_confirmation and is only ever resolved by
     * ConfirmPaymentIntent (Pattern 1), called from the signature-verified
     * webhook (this plan) or Plan 05-04's manual reconciliation.
     *
     * All three PayMongo API calls go through the Paymongo facade — never
     * the model's own attach()/cancel() convenience methods, which
     * instantiate a fresh `new Paymongo` internally and bypass the facade,
     * making them unmockable in tests (verified by reading
     * vendor/luigel/laravel-paymongo/src/Models/PaymentIntent.php).
     */
    private function storePaymongoIntent(SavePricingAndPaymentRequest $request, JobOrder $jobOrder, string $paymentMethod): RedirectResponse
    {
        $amountPaid = (float) $jobOrder->transactions()->where('status', TransactionStatus::Completed->value)->sum('amount');

        // Computed but NOT persisted yet (CR-02) — if the PayMongo calls
        // below fail, the job order must stay unpriced so the Cashier can
        // freely retry with different pricing or a different payment
        // method, exactly like the Cash/Bank Transfer branch's atomicity.
        $computed = null;

        if ($jobOrder->total_amount === null) {
            $computed = ($this->computeJobOrderPrice)(
                (float) $request->validated('line_amount'),
                (bool) $request->validated('rush_fee_applied'),
                $request->validated('discount_type'),
                $request->validated('discount_value') !== null ? (float) $request->validated('discount_value') : null,
            );
        }

        $totalAmount = $computed['total_amount'] ?? (float) $jobOrder->total_amount;

        $isDownPayment = $request->validated('payment_type') === 'down';
        $remainingBalance = round($totalAmount - $amountPaid, 2);
        $transactionAmount = $isDownPayment
            ? (float) $request->validated('down_payment_amount')
            : $remainingBalance;

        $methodLabel = $paymentMethod === PaymentMethod::Gcash->value ? 'GCash' : 'Maya';

        try {
            $intent = Paymongo::paymentIntent()->create([
                'amount' => $transactionAmount,
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
                'type' => $paymentMethod === PaymentMethod::Gcash->value ? 'gcash' : 'paymaya',
            ]);
            $paymongoPaymentMethodId = (string) $paymongoPaymentMethod->getData()['id'];

            // route('home') is a placeholder return destination — this is a
            // Cashier-counter flow, not a customer self-checkout, so the
            // webhook (not this redirect) is the source of truth; PayMongo
            // still requires a return_url for e-wallet payment methods.
            $attached = Paymongo::paymentIntent()->attach($intent, $paymongoPaymentMethodId, route('home'));
        } catch (Throwable $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __("Couldn't start the :method payment. Try again, or choose Cash or Bank Transfer instead.", ['method' => $methodLabel]),
            ]);

            return back();
        }

        $paymongoPaymentIntentId = (string) $intent->getData()['id'];

        // The pricing snapshot (if this was the first pricing/payment visit)
        // is only ever persisted here, alongside the Transaction and
        // payment_status writes, in the SAME transaction as the successful
        // PayMongo calls above (CR-02) — a failed PayMongo call above never
        // reaches this point, so it can never leave a priced-but-untracked
        // job order behind.
        DB::transaction(function () use ($jobOrder, $computed, $request, $paymentMethod, $transactionAmount, $isDownPayment, $paymongoPaymentIntentId): void {
            if ($computed !== null) {
                $jobOrder->forceFill([
                    'pricing_entry_id' => $request->validated('pricing_entry_id'),
                    'base_price_snapshot' => $computed['base_price_snapshot'],
                    'rush_fee_applied' => $request->validated('rush_fee_applied'),
                    'rush_fee_amount' => $computed['rush_fee_amount'],
                    'discount_type' => $request->validated('discount_type'),
                    'discount_value' => $request->validated('discount_value'),
                    'discount_amount' => $computed['discount_amount'],
                    'total_amount' => $computed['total_amount'],
                ]);
            }

            Transaction::create([
                'job_order_id' => $jobOrder->id,
                'type' => $isDownPayment ? TransactionType::DownPayment->value : TransactionType::FullPayment->value,
                'payment_method' => $paymentMethod,
                'amount' => $transactionAmount,
                'status' => TransactionStatus::PendingConfirmation->value,
                'reference_number' => null,
                'paymongo_payment_intent_id' => $paymongoPaymentIntentId,
                'recorded_by' => $request->user()->id,
            ]);

            $jobOrder->forceFill(['payment_status' => PaymentStatus::PendingConfirmation])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('QR code ready. Waiting for the customer to complete payment.')]);

        return back()->with(['redirectUrl' => $attached->getData()['next_action']['redirect']['url'] ?? null]);
    }
}
