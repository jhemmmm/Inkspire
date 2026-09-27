<?php

namespace App\Actions\JobOrder;

use App\Enums\ArtistStatus;
use App\Models\User;

class SetArtistSessionStatus
{
    /**
     * Single mutation point for an Artist's session status. Keeps
     * `is_available` perfectly in sync with `artist_status` (D-13) and
     * stamps/clears `break_started_at` alongside the on_break transition.
     *
     * Returning to Available no longer claims a job order: work is pulled
     * from the shared pool by an explicit Accept, never pushed onto an
     * artist by a status change.
     */
    public function __invoke(User $artist, ArtistStatus $status): void
    {
        $artist->forceFill([
            'artist_status' => $status,
            'is_available' => $status === ArtistStatus::Available,
            'break_started_at' => $status === ArtistStatus::OnBreak ? now() : null,
        ])->save();
    }
}
