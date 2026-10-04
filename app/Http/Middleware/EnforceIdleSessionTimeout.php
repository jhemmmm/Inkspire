<?php

namespace App\Http\Middleware;

use App\Models\SystemConfiguration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdleSessionTimeout
{
    /**
     * Sent by every portal page's background refresh (useLivePoll.ts, which
     * must name the same header). The expiry check still runs on a poll; it
     * just never counts as activity. A client that leaves the header off
     * only makes its own polls count, which is what they did before.
     */
    public const string POLL_HEADER = 'X-Inkspire-Poll';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $timeoutMinutes = SystemConfiguration::getInt('session_idle_timeout_minutes', 20);
        $lastActivity = $request->session()->get('last_activity_at');

        // Carbon 3's diffInMinutes() defaults to a signed difference (not
        // absolute), so an explicit `absolute: true` is required here —
        // otherwise a past $lastActivity produces a negative diff that can
        // never exceed a positive timeout threshold.
        if ($lastActivity && now()->diffInMinutes($lastActivity, absolute: true) > $timeoutMinutes) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(
                'sessionMessage',
                __('Your session expired after :n minutes of inactivity. Log in again to continue.', ['n' => $timeoutMinutes])
            );
        }

        // A poll is the page refreshing itself, not the person using it.
        // Counting it would keep a session alive for as long as any
        // dashboard is left open on an unattended screen.
        if (! $request->hasHeader(self::POLL_HEADER)) {
            $request->session()->put('last_activity_at', now());
        }

        return $next($request);
    }
}
