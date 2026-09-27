<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DesignFile;
use App\Models\User;

class DesignFilePolicy
{
    /**
     * Determine whether the actor can unlock the given design file.
     *
     * Admin only. This was Owner-only while both roles existed; Owner is
     * gone and Admin inherits JOB-07's "can authorize" power.
     */
    public function unlock(User $actor, DesignFile $designFile): bool
    {
        return $actor->role === UserRole::Admin;
    }
}
