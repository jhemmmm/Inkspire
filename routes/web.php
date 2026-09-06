<?php

use App\Http\Controllers\Public\DesignReviewController;
use App\Http\Controllers\Public\QueueDisplayController;
use App\Http\Controllers\Public\TrackingController;
use App\Http\Controllers\Webhooks\PaymongoWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Public, unauthenticated (D-09/D-10, QUEUE-06) — intentionally outside every
// auth/role middleware group; see QueueDisplayController for the PII boundary.
Route::get('queue-display', [QueueDisplayController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('queue-display');

// Public, unauthenticated (TRACK-01/02, D-01/D-02) — a customer looks up
// their job order's current production stage by number. Deliberately
// outside every auth/role:* group; see TrackingController for the PII
// boundary.
Route::get('track', [TrackingController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('public.tracking.show');

// Public, unauthenticated, signed-URL-protected (D-17 through D-21) — the
// client's remote design-review path, reached only via an emailed
// temporarySignedRoute() link. Deliberately outside every role:* group.
Route::middleware(['signed', 'throttle:60,1'])->prefix('design-review')->group(function () {
    Route::get('{revisionLog}', [DesignReviewController::class, 'show'])->name('public.design-review.show');
    Route::post('{revisionLog}/approve', [DesignReviewController::class, 'approve'])->name('public.design-review.approve');
    Route::post('{revisionLog}/request-changes', [DesignReviewController::class, 'requestChanges'])->name('public.design-review.request-changes');
});

// Public, unauthenticated, HMAC-signature-verified (POS-03) — PayMongo's
// servers call this with no session/CSRF token available. Deliberately
// outside every auth/role:* group, matching the design-review precedent,
// but CSRF-excluded (see bootstrap/app.php) instead of signed-URL-protected,
// since PayMongo signs the *body*, not a temporarySignedRoute() URL.
Route::post('webhooks/paymongo', PaymongoWebhookController::class)
    ->middleware('paymongo.signature:payment_paid')
    ->name('public.webhooks.paymongo');

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
