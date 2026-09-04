<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\JobOrderStatus;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use App\Models\SystemConfiguration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show job orders eligible for POS action (POS-01/POS-02).
     *
     * Status-only eligibility filter, plus a `whereNull('cancelled_at')`
     * guard added by Plan 05-05 so a cancelled job order no longer
     * appears as payable. Plan 05-07 (release) will extend this same
     * query with its own additional guard.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('cashier/Dashboard', [
            'jobOrders' => JobOrder::query()
                ->whereIn('status', [JobOrderStatus::ReadyForProduction->value, JobOrderStatus::DesignApproved->value])
                ->whereNull('cancelled_at')
                ->withSum(['transactions as amount_paid' => fn ($query) => $query->where('status', TransactionStatus::Completed->value)], 'amount')
                ->with(['queueEntry.customer:id,name'])
                ->orderBy('created_at')
                ->get(['id', 'description', 'status', 'payment_status', 'queue_entry_id', 'total_amount']),
            // Mirrors the exact server-authoritative value CancellationController
            // reads, so the pre-confirmation dialog body (D-04/D-05) matches
            // what actually gets charged (informational display only).
            'cancellationFeeAmount' => SystemConfiguration::getFloat('cancellation_fee_amount', 500.0),
        ]);
    }
}
