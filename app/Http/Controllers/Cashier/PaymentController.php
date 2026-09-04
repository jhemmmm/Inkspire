<?php

namespace App\Http\Controllers\Cashier;

use App\Actions\POS\ComputeJobOrderPrice;
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
    public function __construct(public ComputeJobOrderPrice $computeJobOrderPrice) {}

    /**
     * Show the Pricing + Payment page for a job order eligible for POS
     * action (POS-01/POS-02/POS-05).
     */
    public function edit(Request $request, JobOrder $jobOrder): Response
    {
        abort_unless(
            in_array($jobOrder->status, [JobOrderStatus::ReadyForProduction, JobOrderStatus::DesignApproved], true),
            422,
            'This job order is not ready for pricing.',
        );

        $jobOrder->loadMissing(['pricingEntry', 'transactions', 'queueEntry.customer:id,name']);

        $amountPaid = (float) $jobOrder->transactions->where('status', TransactionStatus::Completed)->sum('amount');
        $remainingBalance = $jobOrder->total_amount !== null
            ? round((float) $jobOrder->total_amount - $amountPaid, 2)
            : null;

        return Inertia::render('cashier/JobOrderPayment', [
            'jobOrder' => $jobOrder,
            'pricingEntries' => PricingEntry::query()->where('is_active', true)->get(['id', 'name', 'base_price']),
            'rushFeePercentage' => SystemConfiguration::getFloat('rush_fee_percentage', 0.0),
            'discountCapPercentage' => SystemConfiguration::getFloat('discount_cap_percentage', 20.0),
            'discountCapFlatAmount' => SystemConfiguration::getFloat('discount_cap_flat_amount', 500.0),
            'hasExistingTransactions' => $jobOrder->transactions->isNotEmpty(),
            'amountPaid' => $amountPaid,
            'remainingBalance' => $remainingBalance,
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
        abort_unless(
            in_array($jobOrder->status, [JobOrderStatus::ReadyForProduction, JobOrderStatus::DesignApproved], true),
            422,
            'This job order is not ready for pricing.',
        );
        abort_if($jobOrder->payment_status === PaymentStatus::Paid, 422, 'This job order is already fully paid.');

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
}
