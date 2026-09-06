<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\JobOrder;
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
     */
    public function show(Request $request, JobOrder $jobOrder): Response
    {
        abort_unless($jobOrder->transactions()->exists(), 404, 'No payment has been recorded for this job order yet.');

        $jobOrder->loadMissing(['pricingEntry', 'queueEntry.customer:id,name', 'transactions.recordedBy:id,name']);

        $completedTransactions = $jobOrder->transactions->where('status', TransactionStatus::Completed);
        $amountPaid = (float) $completedTransactions->sum('amount');
        $balance = $jobOrder->total_amount !== null
            ? round((float) $jobOrder->total_amount - $amountPaid, 2)
            : 0.0;

        $latestTransaction = $completedTransactions->sortByDesc('created_at')->first();

        return Inertia::render('cashier/Receipt', [
            'jobOrder' => $jobOrder->only(['id', 'number', 'description', 'base_price_snapshot', 'rush_fee_amount', 'discount_amount', 'total_amount', 'created_at']) + [
                'pricing_entry' => $jobOrder->pricingEntry?->only(['id', 'name']),
            ],
            'customerName' => $jobOrder->queueEntry?->customer?->name,
            'latestTransaction' => $latestTransaction ? [
                'payment_method' => $latestTransaction->payment_method,
            ] : null,
            'amountPaid' => $amountPaid,
            'balance' => $balance,
            'cashierName' => $latestTransaction?->recordedBy?->name,
            'trackingUrl' => route('public.tracking.show', ['number' => $jobOrder->number]),
        ]);
    }
}
