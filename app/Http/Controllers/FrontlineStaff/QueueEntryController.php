<?php

namespace App\Http\Controllers\FrontlineStaff;

use App\Actions\JobOrder\CreateJobOrder;
use App\Actions\JobOrder\OpenVisit;
use App\Actions\JobOrder\SyncQueueEntryStatus;
use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FrontlineStaff\AddJobOrderRequest;
use App\Http\Requests\FrontlineStaff\StoreQueueEntryRequest;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\QueueEntry;
use App\Models\SpecificationOption;
use App\Models\SystemConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QueueEntryController extends Controller
{
    public function __construct(
        public SyncQueueEntryStatus $syncQueueEntryStatus,
        public CreateJobOrder $createJobOrder,
        public OpenVisit $openVisit,
    ) {}

    /**
     * Show today's queue with each entry's number, customer, and status
     * (D-05) — the authenticated, PII-permitted counterpart to the public
     * queue display.
     *
     * Also carries a `readyForPickup` summary (PROD-03/D-13) so staff
     * already working this page see the same self-correcting alert as the
     * Frontline Dashboard, without navigating away.
     *
     * The summary's count and items come from ONE fetch, not two cloned
     * queries. This page polls every 5 seconds per staff member, and a
     * release landing between a separate count() and get() yielded
     * `count: 1, items: []`, which the banner rendered as an empty
     * subject ("1 job order ready for pickup /  is waiting on the shelf.").
     *
     * Oldest-first is measured from the production_logs row that recorded
     * the ready_for_pickup transition, not from `updated_at` — which any
     * unrelated write to the job order resets, silently dropping the
     * longest-waiting order out of the two shown here (mirrors
     * FrontlineStaff\DashboardController::index()).
     */
    public function index(Request $request): Response
    {
        $readyForPickup = JobOrder::query()
            ->where('status', JobOrderStatus::ReadyForPickup->value)
            ->whereNull('released_at')
            ->whereNull('cancelled_at')
            ->select(['id', 'number', 'updated_at'])
            ->withMax(
                ['productionLogs as ready_at' => fn (Builder $query) => $query->where('to_status', JobOrderStatus::ReadyForPickup->value)],
                'created_at',
            )
            ->get()
            ->sortBy(fn (JobOrder $jobOrder) => $jobOrder->ready_at ?? $jobOrder->updated_at)
            ->values();

        return Inertia::render('frontline-staff/QueueList', [
            'queueEntries' => fn () => QueueEntry::query()
                ->with([
                    'customer:id,name',
                    'jobOrders' => fn ($query) => $query
                        ->select(['id', 'queue_entry_id', 'description', 'type', 'status', 'validation_failure_reason', 'assigned_artist_id', 'payment_status', 'released_at', 'number', 'total_amount', 'quoted_amount', 'is_rush'])
                        ->with('assignedArtist:id,name,artist_label'),
                ])
                ->whereDate('queue_date', QueueEntry::currentBusinessDate())
                ->where('queue_prefix', '!=', QueueEntry::ONLINE_PREFIX)
                ->orderByRaw("CASE queue_prefix WHEN 'R' THEN 0 ELSE 1 END")
                ->orderBy('queue_number')
                ->get(['id', 'customer_id', 'queue_prefix', 'queue_number', 'status'])
                ->each(fn (QueueEntry $entry) => $entry->jobOrders->append('display_total')),
            'readyForPickup' => [
                'count' => $readyForPickup->count(),
                'items' => $readyForPickup->take(2)
                    ->map(fn (JobOrder $jobOrder) => ['number' => $jobOrder->number])
                    ->values(),
            ],
            // Powers the Add Job Order dialog's price fields — this dialog
            // has never had a service field before.
            'pricingEntries' => fn () => PricingEntry::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'base_price', 'unit']),
            'specificationOptions' => fn () => SpecificationOption::activeLabelsByCategory(),
            'printSizeDimensions' => fn () => SpecificationOption::printSizeDimensionsByLabel(),
            'rushFeePercentage' => fn () => SystemConfiguration::getFloat('rush_fee_percentage', 0.0),
        ]);
    }

    /**
     * Add a job order to an existing visit, regardless of its current
     * status (D-15/D-18) — visits are never locked, even when Done.
     *
     * The create + intake outcome run inside one transaction, matching
     * store(). That enclosing transaction is load-bearing, not cosmetic:
     * JobOrder::nextNumberForYear() opens its own transaction, so without
     * an outer one its lockForUpdate() range lock would be released before
     * this insert ran and two concurrent staff could compute the same
     * number (SQLite makes lockForUpdate() a no-op, so no test catches it).
     */
    public function addJobOrder(AddJobOrderRequest $request, QueueEntry $queueEntry): RedirectResponse
    {
        $jobOrder = DB::transaction(function () use ($request, $queueEntry): JobOrder {
            $jobOrder = ($this->createJobOrder)($queueEntry, [...$request->validated(), 'file' => $request->file('file')]);

            ($this->syncQueueEntryStatus)($queueEntry);

            return $jobOrder;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $this->jobOrderOutcomeToastMessage($jobOrder->fresh()),
        ]);

        return back();
    }

    /**
     * Generate a queue number and create one-or-more job orders for a
     * visit, atomically, in a single save (D-07, D-14).
     */
    public function store(StoreQueueEntryRequest $request): RedirectResponse
    {
        $rows = collect($request->validated('job_orders'))
            ->map(fn (array $row, int $index): array => [...$row, 'file' => $request->file("job_orders.{$index}.file")])
            ->all();

        $queueEntry = ($this->openVisit)((int) $request->validated('customer_id'), $rows);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Queue number :number created with :count job order(s).', [
                'number' => $queueEntry->paddedNumber(),
                'count' => $queueEntry->jobOrders()->count(),
            ]),
        ]);

        return to_route('frontline-staff.new-visit', [
            'customer' => $queueEntry->customer_id,
            'queueEntry' => $queueEntry->id,
        ]);
    }

    /**
     * Build the outcome-specific toast message for a job order, per the
     * 03-UI-SPEC.md Copywriting Contract.
     *
     * A freshly created job order's status here can only ever be one of
     * the five named arms below — CreateJobOrder only ever writes
     * ReadyForProduction/ForProduction/ValidationFailed (Type A) or
     * Assigned/Intake (Type B). The `default` arm exists solely to satisfy
     * Larastan's match-exhaustiveness check against JobOrderStatus's
     * remaining, structurally unreachable-here cases (DesignApproved and
     * every later design/production stage) — this pre-existing gap (see
     * `.planning/phases/06-production-monitoring-public-tracking/deferred-items.md`)
     * is closed here since this plan's own wiring is what widened it.
     */
    private function jobOrderOutcomeToastMessage(JobOrder $jobOrder): string
    {
        return match ($jobOrder->status) {
            JobOrderStatus::ReadyForProduction => __('Job order added — ready for production.'),
            JobOrderStatus::ForProduction => __('Job order added — ready for production.'),
            JobOrderStatus::ValidationFailed => __('Job order added — this file can\'t be used. See details in the queue list.'),
            JobOrderStatus::Intake => $jobOrder->validation_failure_reason === null
                ? __('Job order added — waiting for an artist to accept it.')
                : __('Job order added — the file needs an artist to improve it before printing.'),
            default => __('Job order added.'),
        };
    }
}
