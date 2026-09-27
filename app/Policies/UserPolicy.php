<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the actor can deactivate the target user.
     *
     * Admin may act on anyone but themselves. The self-check is the whole
     * rule now: while Owner existed, Admin was blocked from touching an
     * Owner or another Admin, but with one administrative role left that
     * restriction would make a second Admin permanently unmanageable --
     * and unremovable -- once created.
     */
    public function deactivate(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        return $actor->role === UserRole::Admin;
    }

    /**
     * Determine whether the actor can reactivate the target user.
     *
     * Identical rule to deactivation.
     */
    public function reactivate(User $actor, User $target): bool
    {
        return $this->deactivate($actor, $target);
    }

    /**
     * Determine whether the actor can create a user with the given role.
     *
     * Admin may create any role. A missing or invalid target role never
     * authorizes.
     */
    public function create(User $actor, ?UserRole $targetRole = null): bool
    {
        if ($targetRole === null) {
            return false;
        }

        return $actor->role === UserRole::Admin;
    }

    /**
     * Determine whether the actor can edit the target user's account.
     *
     * Admin may edit anyone, themselves included; the request forbids an
     * Admin from changing their own role.
     */
    public function update(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Admin;
    }
}
