<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use App\Models\SystemConfiguration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    /**
     * Show the printable digital receipt for a job order that has at least
     * one completed transaction (POS-06).
     *
     * Read-only render — no mutation surface, matches
     * DesignFileController::index's read-only-render shape.
     *
     * Shop prices already include VAT, so the VAT line is the portion of the
     * total that is tax, not an amount added on top of it. A rate of 0 (a
     * shop that is not VAT-registered) hides the breakdown.
     */
    public function show(Request $request, JobOrder $jobOrder): Response
    {
        abort_unless($jobOrder->transactions()->exists(), 404, 'No payment has been recorded for this job order yet.');

        $jobOrder->loadMissing(['pricingEntry', 'queueEntry.customer:id,name', 'transactions.recordedBy:id,name']);

        $completedTransactions = $jobOrder->transactions->where('status', TransactionStatus::Completed);
        $amountPaid = (float) $completedTransactions->sum('amount');
        $balance = $jobOrder->outstandingBalance();

        $latestTransaction = $completedTransactions->sortByDesc('created_at')->first();

        $vatRate = SystemConfiguration::getFloat('vat_percentage', 12.0);
        $vatableSales = round((float) $jobOrder->total_amount / (1 + $vatRate / 100), 2);

        return Inertia::render('cashier/Receipt', [
            'jobOrder' => $jobOrder->only(['id', 'number', 'description', 'is_rush', 'base_price_snapshot', 'rush_fee_applied', 'rush_fee_amount', 'discount_amount', 'total_amount', 'created_at']) + [
                'pricing_entry' => $jobOrder->pricingEntry?->only(['id', 'name']),
            ],
            'customerName' => $jobOrder->queueEntry?->customer?->name,
            'latestTransaction' => $latestTransaction ? [
                'payment_method' => $latestTransaction->payment_method,
            ] : null,
            'amountPaid' => $amountPaid,
            'balance' => $balance,
            'vat' => [
                'rate' => $vatRate,
                'vatable_sales' => $vatableSales,
                'amount' => round((float) $jobOrder->total_amount - $vatableSales, 2),
            ],
            'cashierName' => $latestTransaction?->recordedBy?->name,
            'trackingUrl' => route('public.tracking.show', ['number' => $jobOrder->number]),
        ]);
    }
}
