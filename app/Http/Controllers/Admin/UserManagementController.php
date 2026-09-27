<?php

namespace App\Http\Controllers\Owner;

use App\Enums\ArtistStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\CreateUserRequest;
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
                ->select(['id', 'name', 'email', 'role', 'artist_label', 'is_active', 'artist_status', 'break_started_at'])
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    // The name a customer is sent to ("Artist 3"), so the
                    // Owner can see at a glance which numbers are in use.
                    'artist_label' => $user->role === UserRole::Artist ? $user->artist_label : null,
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
     * Create a new user account with a role and an initial password.
     */
    public function store(CreateUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = new User;
        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            // Assigned here rather than left to the Owner to type: the label
            // is what a customer is sent to, so an artist must never exist
            // without one.
            'artist_label' => $validated['role'] === UserRole::Artist->value
                ? User::nextArtistLabel()
                : null,
            'password' => $validated['password'],
            'email_verified_at' => now(),
            'is_active' => true,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(":name's account has been created.", ['name' => $user->name])]);

        return back();
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
