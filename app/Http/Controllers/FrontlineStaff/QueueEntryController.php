<?php

namespace App\Http\Controllers\FrontlineStaff;

use App\Actions\JobOrder\AssignArtistToJobOrder;
use App\Actions\JobOrder\EnterProduction;
use App\Actions\JobOrder\ValidateJobOrderFile;
use App\Enums\JobOrderStatus;
use App\Enums\JobOrderType;
use App\Enums\QueueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FrontlineStaff\AddJobOrderRequest;
use App\Http\Requests\FrontlineStaff\StoreQueueEntryRequest;
use App\Http\Requests\FrontlineStaff\UpdateQueueEntryStatusRequest;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QueueEntryController extends Controller
{
    public function __construct(
        public ValidateJobOrderFile $validateJobOrderFile,
        public AssignArtistToJobOrder $assignArtistToJobOrder,
        public EnterProduction $enterProduction,
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
     */
    public function index(Request $request): Response
    {
        $readyForPickup = JobOrder::query()
            ->where('status', JobOrderStatus::ReadyForPickup->value)
            ->whereNull('released_at')
            ->whereNull('cancelled_at')
            ->oldest('updated_at')
            ->get(['id', 'number']);

        return Inertia::render('frontline-staff/QueueList', [
            'queueEntries' => QueueEntry::query()
                ->with([
                    'customer:id,name',
                    'jobOrders:id,queue_entry_id,description,type,status,validation_failure_reason,assigned_artist_id,payment_status,released_at,number',
                    'jobOrders.assignedArtist:id,name',
                ])
                ->whereDate('queue_date', QueueEntry::currentBusinessDate())
                ->orderBy('queue_number')
                ->get(['id', 'customer_id', 'queue_number', 'status']),
            'readyForPickup' => [
                'count' => $readyForPickup->count(),
                'items' => $readyForPickup->take(2)
                    ->map(fn (JobOrder $jobOrder) => ['number' => $jobOrder->number])
                    ->values(),
            ],
        ]);
    }

    /**
     * Advance a queue entry from Waiting to Serving (D-08) — a manual
     * staff action, never auto-triggered.
     */
    public function callNext(UpdateQueueEntryStatusRequest $request, QueueEntry $queueEntry): RedirectResponse
    {
        abort_unless($queueEntry->status === QueueStatus::Waiting, 422, 'This queue entry is not waiting.');

        $queueEntry->update(['status' => QueueStatus::Serving]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Queue number :number is now being served.', ['number' => $queueEntry->queue_number]),
        ]);

        return back();
    }

    /**
     * Advance a queue entry from Serving to Done (D-08) — a manual staff
     * action, never auto-triggered.
     */
    public function markDone(UpdateQueueEntryStatusRequest $request, QueueEntry $queueEntry): RedirectResponse
    {
        abort_unless($queueEntry->status === QueueStatus::Serving, 422, 'This queue entry is not being served.');

        $queueEntry->update(['status' => QueueStatus::Done]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Queue number :number is now done.', ['number' => $queueEntry->queue_number]),
        ]);

        return back();
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
            $jobOrder = $queueEntry->jobOrders()->create([
                'number' => JobOrder::nextNumberForYear(JobOrder::currentNumberingYear()),
                'description' => $request->validated('description'),
                'type' => $request->validated('type'),
                'status' => JobOrderStatus::Intake,
                'file_path' => $request->file('file')?->store('job-orders', 'local'),
            ]);

            $this->applyIntakeOutcome($jobOrder, $request->file('file'));

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
        $queueEntry = DB::transaction(function () use ($request): QueueEntry {
            $businessDate = QueueEntry::currentBusinessDate();
            $number = QueueEntry::nextForBusinessDay($businessDate);

            $entry = QueueEntry::create([
                'customer_id' => $request->validated('customer_id'),
                'queue_date' => $businessDate,
                'queue_number' => $number,
                'status' => QueueStatus::Waiting,
            ]);

            foreach ($request->validated('job_orders') as $index => $row) {
                $jobOrder = $entry->jobOrders()->create([
                    'number' => JobOrder::nextNumberForYear(JobOrder::currentNumberingYear()),
                    'description' => $row['description'],
                    'type' => $row['type'],
                    'status' => JobOrderStatus::Intake,
                    'file_path' => $request->file("job_orders.{$index}.file")?->store('job-orders', 'local'),
                ]);

                $this->applyIntakeOutcome($jobOrder, $request->file("job_orders.{$index}.file"));
            }

            return $entry;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Queue number :number created with :count job order(s).', [
                'number' => $queueEntry->queue_number,
                'count' => $queueEntry->jobOrders()->count(),
            ]),
        ]);

        return to_route('frontline-staff.new-visit', [
            'customer' => $queueEntry->customer_id,
            'queueEntry' => $queueEntry->id,
        ]);
    }

    /**
     * Apply the correct auto-outcome for a freshly created job order
     * (JOB-01/JOB-02) — Type A gets its file validated, Type B gets
     * auto-assigned to an available artist.
     */
    private function applyIntakeOutcome(JobOrder $jobOrder, ?UploadedFile $file): void
    {
        if ($jobOrder->type === JobOrderType::TypeA) {
            $outcome = ($this->validateJobOrderFile)($file);

            $jobOrder->forceFill([
                'status' => $outcome['passed'] ? JobOrderStatus::ReadyForProduction : JobOrderStatus::ValidationFailed,
                'validation_failure_reason' => $outcome['reason'],
            ])->save();

            if ($outcome['passed']) {
                ($this->enterProduction)($jobOrder);
            }

            return;
        }

        ($this->assignArtistToJobOrder)($jobOrder);
    }

    /**
     * Build the outcome-specific toast message for a job order, per the
     * 03-UI-SPEC.md Copywriting Contract.
     *
     * A freshly created job order's status here can only ever be one of
     * the five named arms below — applyIntakeOutcome() only ever writes
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
            JobOrderStatus::ValidationFailed => __('Job order added — file needs replacement. See details in the queue list.'),
            JobOrderStatus::Assigned => __('Job order added — assigned to :artist.', ['artist' => $jobOrder->assignedArtist->name]),
            JobOrderStatus::Intake => __('Job order added — awaiting an available artist.'),
            default => __('Job order added.'),
        };
    }
}
