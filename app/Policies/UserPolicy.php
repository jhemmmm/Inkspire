<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the actor can deactivate the target user.
     *
     * No self-action in either direction. Owner may act on anyone except
     * themselves. Admin may act only on the 5 staff roles — not Owner, not
     * another Admin.
     */
    public function deactivate(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        if ($actor->role === UserRole::Owner) {
            return true;
        }

        if ($actor->role === UserRole::Admin) {
            return ! in_array($target->role, [UserRole::Owner, UserRole::Admin], true);
        }

        return false;
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
}
