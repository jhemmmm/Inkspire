<?php

namespace App\Actions\JobOrder;

use App\Enums\ArtistStatus;
use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ClaimJobOrderForArtist
{
    /**
     * Let an available Artist take an unclaimed job order out of the shared
     * pool. Returns false — writing nothing — when another Artist claimed it
     * first, when the artist is not Available, or when the job order is no
     * longer claimable.
     *
     * Intentionally not filtered by type. `status = intake` already IS the
     * "waiting for an artist" set: a Type B arrives there, and so does a
     * Type A whose file the scanner judged NeedsArtist. Filtering on
     * `type_b` as well stranded every one of those Type A rows — invisible
     * to the pool that was supposed to hold them, and never sent to
     * production either. A Type A the scanner rejected outright sits at
     * `validation_failed`, a different status, and is still excluded.
     *
     * Concurrency: the claim is a single conditional UPDATE whose WHERE
     * clause carries `assigned_artist_id IS NULL`, and the decision is the
     * affected-row count. Two Artists pressing Accept on the same row at
     * the same moment both issue that statement; the engine serialises them
     * on the row's exclusive write lock, the first flips the column to a
     * non-null id, and the second's predicate no longer matches, so it
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
        if ($artist->artist_status !== ArtistStatus::Available) {
            return false;
        }

        $claimed = JobOrder::query()
            ->whereKey($jobOrder->getKey())
            ->whereNull('assigned_artist_id')
            ->where('status', JobOrderStatus::Intake->value)
            ->whereNull('cancelled_at')
            ->update([
                'assigned_artist_id' => $artist->id,
                'status' => JobOrderStatus::Assigned->value,
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
     * The shared pool every available Artist sees: job orders nobody has
     * claimed yet. Both kinds land here — a Type B consultation, and a
     * Type A whose file the scanner sent for artist work.
     *
     * Rush jobs lead, newest first. Regular jobs follow in arrival order so
     * a new regular job joins the bottom of the available list.
     *
     * @return Builder<JobOrder>
     */
    public static function pool(): Builder
    {
        return JobOrder::query()
            ->where('status', JobOrderStatus::Intake->value)
            ->whereNull('assigned_artist_id')
            ->whereNull('cancelled_at')
            ->orderByDesc('is_rush')
            ->orderByRaw('CASE WHEN is_rush = 1 THEN created_at END DESC')
            ->orderByRaw('CASE WHEN is_rush = 1 THEN id END DESC')
            ->orderBy('created_at')
            ->orderBy('id');
    }
}
