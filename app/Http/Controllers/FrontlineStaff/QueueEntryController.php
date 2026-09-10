<?php

namespace App\Http\Controllers\FrontlineStaff;

use App\Actions\JobOrder\EnterProduction;
use App\Actions\JobOrder\SyncQueueEntryStatus;
use App\Actions\JobOrder\ValidateJobOrderFile;
use App\Enums\FileValidationOutcome;
use App\Enums\JobOrderStatus;
use App\Enums\JobOrderType;
use App\Enums\QueueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FrontlineStaff\AddJobOrderRequest;
use App\Http\Requests\FrontlineStaff\StoreQueueEntryRequest;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use Illuminate\Database\Eloquent\Builder;
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
        public EnterProduction $enterProduction,
        public SyncQueueEntryStatus $syncQueueEntryStatus,
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
            'queueEntries' => QueueEntry::query()
                ->with([
                    'customer:id,name',
                    'jobOrders:id,queue_entry_id,description,type,status,validation_failure_reason,assigned_artist_id,payment_status,released_at,number',
                    'jobOrders.assignedArtist:id,name,artist_label',
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
                'print_size' => $request->validated('print_size'),
                'material' => $request->validated('material'),
                'quantity' => $request->validated('quantity'),
                'deadline' => $request->validated('deadline'),
                'is_rush' => $request->boolean('is_rush'),
                'client_notes' => $request->validated('client_notes'),
                'pricing_entry_id' => $request->validated('pricing_entry_id'),
                'type' => $request->validated('type'),
                'status' => JobOrderStatus::Intake,
                'file_path' => $request->file('file')?->store('job-orders', 'local'),
            ]);

            $this->applyIntakeOutcome($jobOrder, $request->file('file'));

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
                    'print_size' => $row['print_size'] ?? null,
                    'material' => $row['material'] ?? null,
                    'quantity' => $row['quantity'] ?? null,
                    'deadline' => $row['deadline'] ?? null,
                    // filter_var, not a bare cast: the FormData path delivers
                    // the string "1"/"0" while a JSON payload delivers a real
                    // boolean, and both must land as the same column value.
                    'is_rush' => filter_var($row['is_rush'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'client_notes' => $row['client_notes'] ?? null,
                    'pricing_entry_id' => $row['pricing_entry_id'] ?? null,
                    'type' => $row['type'],
                    'status' => JobOrderStatus::Intake,
                    'file_path' => $request->file("job_orders.{$index}.file")?->store('job-orders', 'local'),
                ]);

                $this->applyIntakeOutcome($jobOrder, $request->file("job_orders.{$index}.file"));
            }

            // A visit made entirely of print-ready Type A job orders never
            // reaches an artist, so nothing downstream would ever close it.
            ($this->syncQueueEntryStatus)($entry);

            return $entry;
        });

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
     * Apply the correct auto-outcome for a freshly created job order
     * (JOB-01/JOB-02) — Type A gets its file validated. Type B is
     * deliberately left at Intake with no artist: job orders are pulled
     * from a shared pool by whichever available Artist accepts them, not
     * pushed onto one at intake.
     */
    private function applyIntakeOutcome(JobOrder $jobOrder, ?UploadedFile $file): void
    {
        if ($jobOrder->type !== JobOrderType::TypeA) {
            return;
        }

        $result = ($this->validateJobOrderFile)($file, $jobOrder->print_size);

        // NeedsArtist lands on Intake — the same shared pool a Type B waits
        // in — so any available Artist can pull it. The failure reason rides
        // along as the brief: it says exactly what is wrong with the file.
        $jobOrder->forceFill([
            'status' => match ($result['outcome']) {
                FileValidationOutcome::Passed => JobOrderStatus::ReadyForProduction,
                FileValidationOutcome::NeedsArtist => JobOrderStatus::Intake,
                FileValidationOutcome::Rejected => JobOrderStatus::ValidationFailed,
            },
            'validation_failure_reason' => $result['reason'],
        ])->save();

        if ($result['outcome'] === FileValidationOutcome::Passed) {
            ($this->enterProduction)($jobOrder);
        }
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
            JobOrderStatus::ValidationFailed => __('Job order added — this file can\'t be used. See details in the queue list.'),
            JobOrderStatus::Intake => $jobOrder->validation_failure_reason === null
                ? __('Job order added — waiting for an artist to accept it.')
                : __('Job order added — the file needs an artist to improve it before printing.'),
            default => __('Job order added.'),
        };
    }
}
