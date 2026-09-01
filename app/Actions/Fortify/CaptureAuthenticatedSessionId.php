<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\Request;

class CaptureAuthenticatedSessionId
{
    /**
     * Record the winning session id on the user row after the session id has
     * been regenerated.
     *
     * Must run after `PrepareAuthenticatedSession` in the authentication
     * pipeline: Fortify's `AttemptToAuthenticate` fires the `Login` event
     * (and its listeners) before the session id is regenerated, so capturing
     * `session()->getId()` any earlier would store a stale id that never
     * matches the id actually sent back to the browser — causing
     * `VerifySingleSession` to force-logout every user on their very next
     * request.
     *
     * @param  callable(Request): mixed  $next
     */
    public function __invoke(Request $request, callable $next): mixed
    {
        if ($request->user() instanceof User) {
            $request->user()->forceFill([
                'current_session_id' => session()->getId(),
            ])->save();
        }

        return $next($request);
    }
}
