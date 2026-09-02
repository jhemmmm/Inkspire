<?php

namespace App\Actions\JobOrder;

use App\Enums\ArtistStatus;
use App\Models\User;

class SetArtistSessionStatus
{
    /**
     * Single mutation point for an Artist's session status. Keeps
     * `is_available` perfectly in sync with `artist_status` so Phase 3's
     * `AssignArtistToJobOrder` round-robin query never needs to change
     * (D-13), and stamps/clears `break_started_at` alongside the on_break
     * transition.
     *
     * Returning to Available claims the artist's oldest unassigned Type B
     * job order via AssignArtistToJobOrder's existing claim-entry-point
     * method — its docblock explicitly forward-references this class as
     * its first caller (D-13/Pattern 3).
     */
    public function __invoke(User $artist, ArtistStatus $status): void
    {
        $artist->forceFill([
            'artist_status' => $status,
            'is_available' => $status === ArtistStatus::Available,
            'break_started_at' => $status === ArtistStatus::OnBreak ? now() : null,
        ])->save();

        if ($status === ArtistStatus::Available) {
            app(AssignArtistToJobOrder::class)->claimOldestUnassigned($artist);
        }
    }
}
