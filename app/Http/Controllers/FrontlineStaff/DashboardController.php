<?php

namespace App\Http\Controllers\FrontlineStaff;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show every job order waiting to be picked up (PROD-03) — a derived,
     * polled query with nothing stored (D-13). A job order disappears from
     * this list the instant it's released or sent back a stage, with no
     * code path needed to explicitly clear it.
     *
     * "Ready since" and the oldest-first ordering come from the
     * production_logs row that recorded the ready_for_pickup transition,
     * not from `updated_at`: any unrelated write to the row (recording a
     * payment, requesting credit, an Owner approving credit) bumps
     * updated_at, which reset "Ready Since" to "Just now" and dropped the
     * longest-waiting order to the bottom of the list — and out of the
     * QueueList banner, which shows only the two oldest.
     *
     * `select()` runs before `withMax()` on purpose: withAggregate() falls
     * back to `job_orders.*` when no columns are set yet, which would
     * silently widen this payload to every column on the table.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('frontline-staff/Dashboard', [
            'readyForPickup' => JobOrder::query()
                ->where('status', JobOrderStatus::ReadyForPickup->value)
                ->whereNull('released_at')
                ->whereNull('cancelled_at')
                ->with('queueEntry.customer:id,name')
                ->select(['id', 'number', 'description', 'payment_status', 'queue_entry_id', 'updated_at'])
                ->withMax(
                    ['productionLogs as ready_at' => fn (Builder $query) => $query->where('to_status', JobOrderStatus::ReadyForPickup->value)],
                    'created_at',
                )
                ->get()
                ->sortBy(fn (JobOrder $jobOrder) => $jobOrder->ready_at ?? $jobOrder->updated_at)
                ->values(),
        ]);
    }
}
