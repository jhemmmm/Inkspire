<?php

namespace App\Listeners\Auth;

use App\Support\AuditLogger;
use Illuminate\Auth\Events\Lockout;

class RecordLockoutEvent
{
    /**
     * Handle the event.
     */
    public function handle(Lockout $event): void
    {
        AuditLogger::recordAuthEvent(null, 'lockout', $event->request);
    }
}
