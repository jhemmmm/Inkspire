<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class EnsureAccountIsNotLocked
{
    /**
     * Reject locked-out or deactivated accounts before authentication is attempted.
     *
     * @param  callable(Request): mixed  $next
     */
    public function __invoke(Request $request, callable $next): mixed
    {
        $user = User::query()
            ->where(Fortify::username(), $request->input(Fortify::username()))
            ->first();

        if (! $user) {
            return $next($request);
        }

        if ($user->locked_until?->isFuture()) {
            throw ValidationException::withMessages([
                'email' => [__('Too many failed attempts. This account is locked for :minutes minutes. Contact your Admin if you need immediate access.', [
                    'minutes' => now()->diffInMinutes($user->locked_until),
                ])],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('This account has been deactivated. Contact your Admin for access.')],
            ]);
        }

        return $next($request);
    }
}
