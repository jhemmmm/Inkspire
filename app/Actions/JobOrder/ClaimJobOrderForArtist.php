<?php

namespace App\Actions\JobOrder;

use App\Enums\ArtistStatus;
use App\Enums\JobOrderStatus;
use App\Enums\JobOrderType;
use App\Enums\QueueStatus;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ClaimJobOrderForArtist
{
    /**
     * Let an available Artist take an unclaimed Type B job order out of the
     * shared pool. Returns false — writing nothing — when another Artist
     * claimed it first, when the artist is not Available, or when the job
     * order is no longer claimable.
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
            ->where('type', JobOrderType::TypeB->value)
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

        $this->callCustomerToTheArtist($jobOrder);

        return true;
    }

    /**
     * Accepting a job order calls that customer's queue number.
     *
     * This reverses the original "never auto-triggered" rule on call-next
     * (D-08): an artist taking the job IS the moment the customer is wanted,
     * and leaving Frontline to notice and press Call Next themselves meant
     * the customer sat in the waiting area while their artist waited too.
     * Frontline keeps the manual Call Next for walk-ups that never reach an
     * artist, and Mark Done stays entirely manual.
     *
     * Only a Waiting entry is promoted -- an entry already Serving or Done
     * is left alone, so a second job order accepted for the same visit
     * cannot drag a finished visit backwards.
     */
    private function callCustomerToTheArtist(JobOrder $jobOrder): void
    {
        $queueEntry = $jobOrder->queueEntry;

        if ($queueEntry?->status !== QueueStatus::Waiting) {
            return;
        }

        $queueEntry->update(['status' => QueueStatus::Serving]);
    }

    /**
     * The shared pool every available Artist sees: Type B job orders nobody
     * has claimed yet.
     *
     * Rush jobs sort to the top, and within each group the longest-waiting
     * customer leads. Rush is what the customer paid a premium for, so it
     * outranks arrival order -- but only as a tie-break above it, never
     * instead of it: two rush jobs still come out oldest-first, so a rush
     * job cannot be overtaken by a newer rush job.
     *
     * @return Builder<JobOrder>
     */
    public static function pool(): Builder
    {
        return JobOrder::query()
            ->where('type', JobOrderType::TypeB->value)
            ->where('status', JobOrderStatus::Intake->value)
            ->whereNull('assigned_artist_id')
            ->whereNull('cancelled_at')
            ->orderByDesc('is_rush')
            ->oldest('created_at');
    }
}
