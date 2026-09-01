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

        // current_session_id is intentionally NOT set here: this listener runs
        // (via the `Login` event) before Fortify's `PrepareAuthenticatedSession`
        // pipeline step regenerates the session id, so capturing it here would
        // store a stale id. See App\Actions\Fortify\CaptureAuthenticatedSessionId,
        // which runs later in the pipeline once the final session id is known.
        //
        // saveQuietly() is used deliberately: this is routine session
        // bookkeeping, not a business-data mutation, and the explicit `login`
        // audit row below already captures the meaningful signal. Without it,
        // AuditObserver::updated() would add a redundant `updated` row to the
        // audit trail for every single login (see WR-05).
        $event->user->forceFill([
            'last_activity_at' => now(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->saveQuietly();

        AuditLogger::recordAuthEvent($event->user, 'login', request());
    }
}
