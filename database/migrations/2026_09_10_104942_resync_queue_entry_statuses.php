<?php

use App\Actions\JobOrder\SyncQueueEntryStatus;
use App\Models\QueueEntry;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Bring every existing visit in line with SyncQueueEntryStatus.
     *
     * Queue status used to be set by hand from Frontline's Call Next and
     * Mark Done buttons, which have been removed. Rows left behind by those
     * buttons can disagree with the job orders under them -- a visit reading
     * "Done" above a job order still marked "Waiting for an Artist" -- and
     * nothing would correct them until that visit happened to be touched
     * again. Recomputing once here means the rule holds everywhere from the
     * moment it ships.
     *
     * Deliberately reuses the action rather than restating its rule in SQL:
     * two copies of "when is a visit finished" is exactly how they drift.
     */
    public function up(): void
    {
        $sync = new SyncQueueEntryStatus;

        QueueEntry::query()
            ->with('jobOrders:id,queue_entry_id,status,assigned_artist_id,cancelled_at')
            ->chunkById(200, fn ($entries) => $entries->each($sync));
    }

    /**
     * No down path: the previous values were hand-entered, not derived, so
     * there is nothing to restore them from.
     */
    public function down(): void {}
};
