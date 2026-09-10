<?php

namespace App\Actions\JobOrder;

use App\Enums\JobOrderStatus;
use App\Enums\QueueStatus;
use App\Models\JobOrder;
use App\Models\QueueEntry;

class SyncQueueEntryStatus
{
    /**
     * The statuses at which a job order still wants the customer present --
     * either at the counter or with an artist.
     *
     * Everything past these has left the front of the shop: the job is
     * priced, printed or waiting on a shelf, and the visit itself is over.
     */
    private const array OPEN_STATUSES = [
        JobOrderStatus::Intake,
        JobOrderStatus::ValidationFailed,
        JobOrderStatus::Assigned,
        JobOrderStatus::InConsultation,
        JobOrderStatus::InDesign,
        JobOrderStatus::PendingReview,
    ];

    /**
     * Derive a visit's queue status from the job orders in it.
     *
     * This replaces Frontline's manual Call Next and Mark Done buttons, and
     * is deliberately a pure function of the job orders rather than a
     * one-way ratchet. A ratchet looked safer -- it stopped a second job
     * order dragging a finished visit backwards -- but it produced rows
     * reading "Done" above job orders still marked "Waiting for an Artist",
     * which is precisely what the queue exists to tell staff.
     *
     * Waiting : work outstanding, nobody has taken it.
     * Serving : an artist has taken one of them, so the customer has
     *           somewhere to go.
     * Done    : nothing left that needs the counter or an artist.
     *
     * A cancelled job order never counts -- CancellationController leaves
     * `status` untouched, so one cancelled mid-design would otherwise hold
     * its visit open forever.
     */
    public function __invoke(?QueueEntry $queueEntry): void
    {
        if ($queueEntry === null) {
            return;
        }

        $open = $queueEntry->jobOrders()
            ->whereNull('cancelled_at')
            ->whereIn('status', array_map(
                fn (JobOrderStatus $status) => $status->value,
                self::OPEN_STATUSES,
            ))
            ->get(['id', 'assigned_artist_id']);

        $status = match (true) {
            $open->isEmpty() => QueueStatus::Done,
            $open->contains(fn (JobOrder $jobOrder) => $jobOrder->assigned_artist_id !== null) => QueueStatus::Serving,
            default => QueueStatus::Waiting,
        };

        if ($queueEntry->status !== $status) {
            $queueEntry->update(['status' => $status]);
        }
    }
}
