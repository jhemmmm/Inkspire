<?php

namespace App\Listeners\Auth;

use App\Models\SystemConfiguration;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\Failed;

class RecordFailedLoginAttempt
{
    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->increment('failed_login_attempts');

        AuditLogger::recordAuthEvent($event->user, 'failed_login', request());

        $threshold = SystemConfiguration::getInt('account_lockout_max_attempts', 5);
        $duration = SystemConfiguration::getInt('account_lockout_minutes', 15);

        if ($event->user->fresh()->failed_login_attempts >= $threshold) {
            $event->user->forceFill(['locked_until' => now()->addMinutes($duration)])->save();

            AuditLogger::recordAuthEvent($event->user, 'lockout', request());
        }
    }
}
