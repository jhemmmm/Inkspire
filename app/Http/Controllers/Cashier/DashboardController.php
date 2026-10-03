<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\AccountsReceivableStatus;
use App\Enums\JobOrderStatus;
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
     *
     * Fully-paid job orders are rejected in PHP rather than in SQL, and
     * deliberately so: this query is unpaginated and already fully
     * materialised, and a `havingRaw` over the `amount_paid` alias has no
     * GROUP BY to hang off (it is a correlated sub-select, not an
     * aggregate). The predicate reuses the `withSum` over Completed
     * transactions that already ran — there is exactly one definition of
     * "paid" on this request, not two. The 0.005 epsilon absorbs the float
     * dust a decimal cast leaves behind, so a peso-exact settlement is not
     * left on the worklist by a rounding hair. `->values()` is mandatory:
     * reject() preserves keys, and a gapped-key Collection serialises to
     * Inertia as a JSON object, which breaks `v-for` and the
     * `CashierJobOrder[]` prop type.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('cashier/Dashboard', [
            'jobOrders' => JobOrder::query()
                ->whereIn('status', [
                    JobOrderStatus::ReadyForProduction->value,
                    JobOrderStatus::DesignApproved->value,
                    JobOrderStatus::ForProduction->value,
                    JobOrderStatus::Printing->value,
                    JobOrderStatus::ReadyForPickup->value,
                ])
                ->whereNull('cancelled_at')
                ->withAmountPaid()
                ->with([
                    'queueEntry.customer:id,name',
                    // Surfaced so the cancellation dialog can tell the
                    // Cashier what happens to an outstanding On-Credit
                    // balance (WR-05): CancellationController closes the
                    // receivable, so the customer no longer owes it.
                    'accountsReceivable' => fn ($query) => $query
                        ->where('status', AccountsReceivableStatus::Active->value)
                        ->select(['id', 'job_order_id', 'balance', 'status']),
                ])
                ->orderBy('created_at')
                ->get(['id', 'number', 'description', 'status', 'payment_status', 'queue_entry_id', 'total_amount', 'quoted_amount', 'is_rush', 'released_at', 'cancelled_at'])
                ->reject(fn (JobOrder $jobOrder) => $jobOrder->total_amount !== null
                    && $jobOrder->amount_paid !== null
                    && (float) $jobOrder->amount_paid >= (float) $jobOrder->total_amount - 0.005)
                ->values()
                ->append(['display_total', 'display_status'])
                ->each(fn (JobOrder $jobOrder) => $jobOrder->setAttribute('can_cancel', $jobOrder->cancellationBlocker() === null)),
            // Mirrors the exact server-authoritative value CancellationController
            // reads, so the pre-confirmation dialog body (D-04/D-05) matches
            // what actually gets charged (informational display only).
            'cancellationFeeAmount' => SystemConfiguration::getFloat('cancellation_fee_amount', 500.0),
        ]);
    }
}
