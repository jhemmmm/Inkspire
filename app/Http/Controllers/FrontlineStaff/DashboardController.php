<?php

namespace App\Http\Controllers\FrontlineStaff;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
     * payment, requesting credit, an Admin approving credit) bumps
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
            'readyForPickup' => fn () => JobOrder::query()
                ->where('status', JobOrderStatus::ReadyForPickup->value)
                ->whereNull('released_at')
                ->whereNull('cancelled_at')
                ->with('queueEntry.customer:id,name')
                ->select(['id', 'number', 'description', 'payment_status', 'queue_entry_id', 'updated_at', 'total_amount', 'quoted_amount', 'is_rush'])
                ->withMax(
                    ['productionLogs as ready_at' => fn (Builder $query) => $query->where('to_status', JobOrderStatus::ReadyForPickup->value)],
                    'created_at',
                )
                ->withAmountPaid()
                ->get()
                ->sortBy(fn (JobOrder $jobOrder) => $jobOrder->ready_at ?? $jobOrder->updated_at)
                ->values()
                ->append('display_total'),
            'searchResults' => fn () => $this->search($request),
            'filters' => $request->only(['q']),
        ]);
    }

    /**
     * Look a job order up by number, description or customer name (SRCH-01).
     *
     * Deliberately unfiltered by status: the counter gets asked "where is my
     * order?" about job orders at every stage, including ones already
     * released or cancelled, so narrowing this to the pickup list would
     * answer only the question staff can already answer by looking.
     *
     * Capped at 25 -- a staff member who cannot see their order in 25 rows
     * needs a better search term, not a longer list.
     *
     * @return Collection<int, JobOrder>
     */
    private function search(Request $request): Collection
    {
        if (! $request->filled('q')) {
            return collect();
        }

        return JobOrder::query()
            ->search((string) $request->string('q'))
            ->with(['queueEntry.customer:id,name', 'assignedArtist:id,name,artist_label'])
            ->withAmountPaid()
            ->latest('id')
            ->take(25)
            ->get(['id', 'number', 'description', 'status', 'payment_status', 'is_rush', 'queue_entry_id', 'assigned_artist_id', 'released_at', 'cancelled_at', 'created_at', 'total_amount', 'quoted_amount'])
            ->append(['display_total', 'display_status']);
    }
}
