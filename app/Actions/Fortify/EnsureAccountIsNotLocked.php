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
            throw self::lockedException($user);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('This account has been deactivated. Contact your Admin for access.')],
            ]);
        }

        return $next($request);
    }

    /**
     * The error a locked-out account gets, both on the attempt that locks it
     * and on every attempt until the lock expires. Minutes are rounded up:
     * Carbon 3 returns a fractional diff, which read as "14.98 minutes".
     */
    public static function lockedException(User $user): ValidationException
    {
        return ValidationException::withMessages([
            'email' => [__('Too many failed attempts. This account is locked for :minutes minutes. Contact your Admin if you need immediate access.', [
                'minutes' => (int) ceil(now()->diffInMinutes($user->locked_until)),
            ])],
        ]);
    }
}
