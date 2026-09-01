<?php

use App\Http\Controllers\Public\QueueDisplayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Public, unauthenticated (D-09/D-10, QUEUE-06) — intentionally outside every
// auth/role middleware group; see QueueDisplayController for the PII boundary.
Route::get('queue-display', [QueueDisplayController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('queue-display');

Route::middleware(['auth', 'verified'])->group(function () {
    // Every role has its own dedicated portal (see UserRole::portalRoute()).
    // This route only exists so old bookmarks/links to the starter kit's
    // generic dashboard still land somewhere sensible instead of a 404.
    Route::get('dashboard', fn (Request $request) => redirect()->route($request->user()->role->portalRoute()))
        ->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/owner.php';
require __DIR__.'/portals.php';
