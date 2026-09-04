<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show job orders eligible for POS action (POS-01/POS-02).
     *
     * Status-only eligibility filter for this plan. Plan 05-05
     * (cancellation) and Plan 05-07 (release) will each extend this same
     * query with additional whereNull() guards as those concepts are
     * introduced.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('cashier/Dashboard', [
            'jobOrders' => JobOrder::query()
                ->whereIn('status', [JobOrderStatus::ReadyForProduction->value, JobOrderStatus::DesignApproved->value])
                ->with(['queueEntry.customer:id,name'])
                ->orderBy('created_at')
                ->get(['id', 'description', 'status', 'payment_status', 'queue_entry_id', 'total_amount']),
        ]);
    }
}
