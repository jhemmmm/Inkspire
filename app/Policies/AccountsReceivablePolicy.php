<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AccountsReceivable;
use App\Models\User;

/**
 * This codebase's second (after DesignFilePolicy) Owner-exclusive-not-Admin
 * authorization check. `routes/owner.php`'s `credit-requests.*` group keeps
 * the blanket `role:owner,admin` middleware for page visibility — an Admin
 * can still see the approval queue — but the actual approve()/reject()
 * mutation deliberately narrows below that, to Owner only, matching
 * DesignFileController::unlock's established split and PROJECT.md's
 * "Owner-exclusive financial/approval powers distinct from Admin's"
 * decision.
 */
class AccountsReceivablePolicy
{
    /**
     * Determine whether the actor can approve the given credit request.
     *
     * Owner only, deliberately not extended to Admin, matching
     * DesignFilePolicy::unlock()'s exact shape.
     */
    public function approve(User $actor, AccountsReceivable $accountsReceivable): bool
    {
        return $actor->role === UserRole::Owner;
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
     * (D-13). Owner only, same Owner-not-Admin narrowing as approve().
     */
    public function approveWriteOff(User $actor, AccountsReceivable $accountsReceivable): bool
    {
        return $actor->role === UserRole::Owner;
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
