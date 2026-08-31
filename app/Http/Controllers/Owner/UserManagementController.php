<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\DeactivateUserRequest;
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
        return Inertia::render('owner/UserManagement', [
            'users' => User::query()
                ->select(['id', 'name', 'email', 'role', 'is_active'])
                ->orderBy('name')
                ->get(),
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
}
