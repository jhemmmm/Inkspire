<?php

namespace App\Http\Controllers\Owner;

use App\Enums\ArtistStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\DeactivateUserRequest;
use App\Http\Requests\Owner\ReactivateUserRequest;
use App\Models\SystemConfiguration;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    /**
     * Show the Owner/Admin user management list.
     */
    public function index(Request $request): Response
    {
        $maxBreakMinutes = SystemConfiguration::getInt('max_artist_break_minutes', 15);

        return Inertia::render('owner/UserManagement', [
            'users' => User::query()
                ->select(['id', 'name', 'email', 'role', 'is_active', 'artist_status', 'break_started_at'])
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'artist_status' => $user->role === UserRole::Artist ? $user->artist_status : null,
                    // Carbon 3's diff defaults to a signed difference (not
                    // absolute), so an explicit `absolute: true` is required
                    // here — otherwise a past break_started_at produces a
                    // negative diff that can never exceed a positive threshold.
                    'exceeded_break_time' => $user->role === UserRole::Artist
                        && $user->artist_status === ArtistStatus::OnBreak
                        && $user->break_started_at !== null
                        && now()->diffInMinutes($user->break_started_at, absolute: true) > $maxBreakMinutes,
                ]),
        ]);
    }

    /**
     * Deactivate a user account. The account is never hard-deleted.
     */
    public function deactivate(DeactivateUserRequest $request, User $user): RedirectResponse
    {
        $user->forceFill(['is_active' => false])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(":name's account has been deactivated.", ['name' => $user->name])]);

        return back();
    }

    /**
     * Reactivate a previously deactivated user account.
     */
    public function reactivate(ReactivateUserRequest $request, User $user): RedirectResponse
    {
        $user->forceFill(['is_active' => true])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(":name's account has been reactivated.", ['name' => $user->name])]);

        return back();
    }
}
