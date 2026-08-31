<?php

namespace App\Listeners\Auth;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\Logout;

class HandleLogout
{
    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        if ($event->user instanceof User) {
            AuditLogger::recordAuthEvent($event->user, 'logout', request());
        }
    }
}
