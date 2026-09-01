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

        $request->session()->put('last_activity_at', now());

        return $next($request);
    }
}
