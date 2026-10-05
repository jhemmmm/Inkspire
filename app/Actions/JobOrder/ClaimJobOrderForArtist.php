<?php

namespace App\Actions\JobOrder;

use App\Enums\ArtistStatus;
use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ClaimJobOrderForArtist
{
    private const array CLAIMABLE_STATUSES = [
        JobOrderStatus::Intake,
        JobOrderStatus::InDesign,
        JobOrderStatus::PendingReview,
    ];

    /**
     * Let an available Artist take an unclaimed job order out of the shared
     * pool. Transferred designs keep their current stage, including an open
     * client review. Returns false without writing when another Artist claims
     * first, the artist is unavailable, or the order is no longer claimable.
     *
     * Intentionally not filtered by type. Intake includes Type B requests and
     * Type A files that need an artist; transferred design work can also be
     * claimed. A rejected Type A file remains in validation_failed and is
     * excluded.
     *
     * Concurrency: the claim is a single conditional UPDATE whose WHERE
     * clause carries `assigned_artist_id IS NULL` and the observed status.
     * The decision is the affected-row count. Two Artists pressing Accept on
     * the same row at the same moment both issue that statement; the engine
     * serialises them on the row's exclusive write lock. The first flips the
     * column to a non-null id, and the second's predicate no longer matches, so it
     * affects 0 rows and is told it lost. There is no read-then-write
     * window for a competing writer to slip into.
     *
     * This deliberately does NOT use `SELECT ... FOR UPDATE`: SQLite (this
     * project's dev database) silently ignores row-level locking hints, so
     * a select-then-write pairing would appear correct in local tests and
     * only race in MySQL. A compare-and-swap UPDATE is atomic on both.
     */
    public function __invoke(JobOrder $jobOrder, User $artist): bool
    {
        if ($artist->artist_status !== ArtistStatus::Available
            || ! in_array($jobOrder->status, self::CLAIMABLE_STATUSES, true)) {
            return false;
        }

        $claimed = JobOrder::query()
            ->whereKey($jobOrder->getKey())
            ->whereNull('assigned_artist_id')
            ->where('status', $jobOrder->status->value)
            ->whereNull('cancelled_at')
            ->update([
                'assigned_artist_id' => $artist->id,
                'status' => $jobOrder->status === JobOrderStatus::Intake
                    ? JobOrderStatus::Assigned->value
                    : $jobOrder->status->value,
                'accepted_at' => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

        // Routine bookkeeping, silenced from the audit trail -- matches the
        // existing last_activity_at precedent.
        $artist->forceFill(['last_assigned_at' => now()])->saveQuietly();

        $jobOrder->refresh();

        // Accepting a job order is what calls the customer to the counter,
        // replacing Frontline's old manual Call Next. Instantiated rather
        // than injected: this action is constructed directly in several
        // places, and a constructor dependency would ripple through all of
        // them for one call.
        (new SyncQueueEntryStatus)($jobOrder->queueEntry);

        return true;
    }

    /**
     * The shared pool every available Artist sees: new intake work and
     * transferred designs that still need an Artist. Both Type A and Type B
     * orders can appear here.
     *
     * Rush jobs lead, with newest jobs first within each group.
     *
     * @return Builder<JobOrder>
     */
    public static function pool(): Builder
    {
        return JobOrder::query()
            ->whereIn('status', array_map(
                fn (JobOrderStatus $status) => $status->value,
                self::CLAIMABLE_STATUSES,
            ))
            ->whereNull('assigned_artist_id')
            ->whereNull('cancelled_at')
            ->orderByDesc('is_rush')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
