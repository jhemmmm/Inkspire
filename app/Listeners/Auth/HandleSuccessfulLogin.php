<?php

namespace App\Listeners\Auth;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\Login;

class HandleSuccessfulLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill([
            'current_session_id' => session()->getId(),
            'last_activity_at' => now(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        AuditLogger::recordAuthEvent($event->user, 'login', request());
    }
}
