<?php

namespace App\Actions\JobOrder;

use App\Enums\QueueStatus;
use App\Models\QueueEntry;
use Illuminate\Support\Facades\DB;

class OpenVisit
{
    public function __construct(
        public CreateJobOrder $createJobOrder,
        public SyncQueueEntryStatus $syncQueueEntryStatus,
    ) {}

    /**
     * Generate a queue number and create one-or-more job orders for a
     * visit, atomically (D-07, D-14). Each row is shaped as CreateJobOrder
     * expects. A null `$prefix` picks the lane from the rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __invoke(int $customerId, array $rows, ?string $prefix = null): QueueEntry
    {
        return DB::transaction(function () use ($customerId, $rows, $prefix): QueueEntry {
            $businessDate = QueueEntry::currentBusinessDate();

            // The lane is decided from the job orders being booked, before
            // any of them exist -- the ticket is printed and handed over as
            // the visit starts, so it cannot wait for the rows.
            if ($prefix === null) {
                $isRushVisit = collect($rows)
                    ->contains(fn (array $row): bool => filter_var($row['is_rush'] ?? false, FILTER_VALIDATE_BOOLEAN));

                $prefix = $isRushVisit ? QueueEntry::RUSH_PREFIX : QueueEntry::REGULAR_PREFIX;
            }

            $entry = QueueEntry::create([
                'customer_id' => $customerId,
                'queue_date' => $businessDate,
                'queue_prefix' => $prefix,
                'queue_number' => QueueEntry::nextForBusinessDay($businessDate, $prefix),
                'status' => QueueStatus::Waiting,
            ]);

            foreach ($rows as $row) {
                ($this->createJobOrder)($entry, $row);
            }

            // A visit made entirely of print-ready Type A job orders never
            // reaches an artist, so nothing downstream would ever close it.
            ($this->syncQueueEntryStatus)($entry);

            return $entry;
        });
    }
}
