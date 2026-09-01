<?php

namespace App\Actions\JobOrder;

use App\Enums\JobOrderStatus;
use App\Enums\JobOrderType;
use App\Enums\UserRole;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignArtistToJobOrder
{
    /**
     * Auto-assign a Type B job order to the available Artist with the
     * oldest (or null) `last_assigned_at`, under a database lock so
     * concurrent requests can never collide (D-06, T-03-02).
     *
     * Returns null and writes nothing when the job order isn't Type B, or
     * when no artist is available (D-07 — the job order stays unassigned,
     * no fallback to an unavailable artist).
     */
    public function __invoke(JobOrder $jobOrder): ?User
    {
        if ($jobOrder->type !== JobOrderType::TypeB) {
            return null;
        }

        return DB::transaction(function () use ($jobOrder): ?User {
            $artist = User::query()
                ->where('role', UserRole::Artist->value)
                ->where('is_available', true)
                ->orderByRaw('last_assigned_at IS NOT NULL, last_assigned_at ASC')
                ->lockForUpdate()
                ->first();

            if ($artist === null) {
                return null;
            }

            $jobOrder->forceFill([
                'assigned_artist_id' => $artist->id,
                'status' => JobOrderStatus::Assigned,
            ])->save();

            // Routine fairness bookkeeping, silenced from the audit trail —
            // matches the existing last_activity_at precedent.
            $artist->forceFill(['last_assigned_at' => now()])->saveQuietly();

            return $artist;
        });
    }

    /**
     * Claim the oldest unassigned Type B job order for an artist who has
     * just become available (D-07). No caller exists yet this phase —
     * Phase 4's On Break/End Shift toggle will invoke this.
     */
    public function claimOldestUnassigned(User $artist): ?JobOrder
    {
        return DB::transaction(function () use ($artist): ?JobOrder {
            $jobOrder = JobOrder::query()
                ->where('type', JobOrderType::TypeB->value)
                ->where('status', JobOrderStatus::Intake->value)
                ->whereNull('assigned_artist_id')
                ->lockForUpdate()
                ->oldest('created_at')
                ->first();

            if ($jobOrder === null) {
                return null;
            }

            $jobOrder->forceFill([
                'assigned_artist_id' => $artist->id,
                'status' => JobOrderStatus::Assigned,
            ])->save();

            $artist->forceFill(['last_assigned_at' => now()])->saveQuietly();

            return $jobOrder;
        });
    }
}
