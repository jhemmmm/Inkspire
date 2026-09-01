<?php

namespace App\Http\Controllers\FrontlineStaff;

use App\Enums\JobOrderStatus;
use App\Enums\QueueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FrontlineStaff\StoreQueueEntryRequest;
use App\Models\QueueEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QueueEntryController extends Controller
{
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
