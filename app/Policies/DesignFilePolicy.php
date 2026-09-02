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
     * Owner only, deliberately not extended to Admin (unlike
     * UserPolicy::deactivate()), matching JOB-07's exact "Owner can
     * authorize" wording.
     */
    public function unlock(User $actor, DesignFile $designFile): bool
    {
        return $actor->role === UserRole::Owner;
    }
}
