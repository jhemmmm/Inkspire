<?php

namespace App\Http\Controllers\FrontlineStaff;

use App\Enums\JobOrderStatus;
use App\Enums\QueueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FrontlineStaff\AddJobOrderRequest;
use App\Http\Requests\FrontlineStaff\StoreQueueEntryRequest;
use App\Http\Requests\FrontlineStaff\UpdateQueueEntryStatusRequest;
use App\Models\QueueEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QueueEntryController extends Controller
{
    /**
     * Show today's queue with each entry's number, customer, and status
     * (D-05) — the authenticated, PII-permitted counterpart to the public
     * queue display.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('frontline-staff/QueueList', [
            'queueEntries' => QueueEntry::query()
                ->with('customer:id,name')
                ->whereDate('queue_date', QueueEntry::currentBusinessDate())
                ->orderBy('queue_number')
                ->get(['id', 'customer_id', 'queue_number', 'status']),
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
     */
    public function addJobOrder(AddJobOrderRequest $request, QueueEntry $queueEntry): RedirectResponse
    {
        $queueEntry->jobOrders()->create([
            'description' => $request->validated('description'),
            'type' => $request->validated('type'),
            'status' => JobOrderStatus::Intake,
            'file_path' => $request->file('file')?->store('job-orders', 'local'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Job order added to queue number :number.', ['number' => $queueEntry->queue_number]),
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
                $entry->jobOrders()->create([
                    'description' => $row['description'],
                    'type' => $row['type'],
                    'status' => JobOrderStatus::Intake,
                    'file_path' => $request->file("job_orders.{$index}.file")?->store('job-orders', 'local'),
                ]);
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
}
