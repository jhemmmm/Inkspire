<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AccountsReceivable;
use App\Models\User;

/**
 * Credit and write-off approval, the shop's two money-authorising powers.
 *
 * These used to be Owner-only, deliberately narrower than the
 * `role:owner,admin` middleware on the routes so an Admin could see the
 * queue but not act on it. With the Owner role removed there is no second
 * administrative role to narrow against, so Admin holds both powers -- the
 * route middleware and the policy now agree, and this class exists to keep
 * the authorisation decision in one place rather than only in a route
 * string.
 */
class AccountsReceivablePolicy
{
    /**
     * Determine whether the actor can approve the given credit request.
     */
    public function approve(User $actor, AccountsReceivable $accountsReceivable): bool
    {
        return $actor->role === UserRole::Admin;
    }

    /**
     * Determine whether the actor can reject the given credit request.
     *
     * Identical rule to approval.
     */
    public function reject(User $actor, AccountsReceivable $accountsReceivable): bool
    {
        return $this->approve($actor, $accountsReceivable);
    }

    /**
     * Determine whether the actor can approve the given write-off request
     * (D-13).
     */
    public function approveWriteOff(User $actor, AccountsReceivable $accountsReceivable): bool
    {
        return $actor->role === UserRole::Admin;
    }

    /**
     * Determine whether the actor can reject the given write-off request.
     * Identical rule to write-off approval.
     */
    public function rejectWriteOff(User $actor, AccountsReceivable $accountsReceivable): bool
    {
        return $this->approveWriteOff($actor, $accountsReceivable);
    }
}
