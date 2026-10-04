<?php

use App\Http\Controllers\Public\DesignReviewController;
use App\Http\Controllers\Public\OnlineOrderController;
use App\Http\Controllers\Public\OnlinePaymentController;
use App\Http\Controllers\Public\QueueDisplayController;
use App\Http\Controllers\Public\TrackingController;
use App\Http\Controllers\Webhooks\PaymongoWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Public, unauthenticated — a customer places an order from the website. It
// is only parked until they open the emailed signed link, so nothing is
// written to the shop's workflow by an anonymous visitor alone. Deliberately
// outside every auth/role:* group. The POST throttle here only stops
// hammering; the limit on mail sent is counted in OnlineOrderController::store(),
// where a failed validation does not use it up.
//
// Every public throttle in this file names its own bucket (the third
// parameter). Without one, Laravel keys a guest by IP alone, so all of these
// routes would share a single counter: a tracking page left open polls 12
// times a minute, which on its own used up the allowance of every stricter
// route beside it.
Route::get('order', [OnlineOrderController::class, 'create'])
    ->middleware('throttle:60,1,order-form')
    ->name('public.orders.create');
Route::post('order', [OnlineOrderController::class, 'store'])
    ->middleware('throttle:30,1,order-submit')
    ->name('public.orders.store');
Route::middleware(['signed', 'throttle:60,1,order-confirm'])->prefix('order/confirm')->group(function () {
    Route::get('{onlineOrder}', [OnlineOrderController::class, 'show'])->whereNumber('onlineOrder')->name('public.orders.confirm.show');
    Route::post('{onlineOrder}', [OnlineOrderController::class, 'confirm'])->whereNumber('onlineOrder')->name('public.orders.confirm');
});

// Public, unauthenticated (D-09/D-10, QUEUE-06) — intentionally outside every
// auth/role middleware group; see QueueDisplayController for the PII boundary.
Route::get('queue-display', [QueueDisplayController::class, 'index'])
    ->middleware('throttle:60,1,queue-display')
    ->name('queue-display');

// Public, unauthenticated (TRACK-01/02, D-01/D-02) — a customer looks up
// their job order's current production stage by number. Deliberately
// outside every auth/role:* group; see TrackingController for the PII
// boundary.
//
// Sized above the other public routes' 60/min on purpose: Laravel's
// throttle limiter keys guests by IP, and Tracking.vue polls every 5s
// (12 req/min per open tab). At 240/min, twenty tabs can track orders from
// behind one NAT — shop Wi-Fi, a mall, mobile carrier CGNAT — before any of
// them receives a 429, which the page does not handle. The response is a
// handful of scalar fields, so the headroom is cheap.
Route::get('track', [TrackingController::class, 'show'])
    ->middleware('throttle:240,1,track')
    ->name('public.tracking.show');

// The QR-scan entry point to that same boundary (QR-01/QR-02) — the token
// printed on the customer's handoff slip stands in for typing a job order
// number. Also deliberately outside every auth/role:* group, and throttled
// in the same bucket as its neighbour above for the same per-IP/NAT reasons.
Route::get('track/{token}', [TrackingController::class, 'showByToken'])
    ->middleware('throttle:240,1,track')
    ->name('public.tracking.token');

// A customer paying their full balance by GCash or Maya from that tracking
// page (ONLINE-PAY-04). The token is the credential, the amount is always the
// server's own outstanding balance, and only the wallet is taken from the
// request. Throttled more tightly than the page itself because each call can
// reach out to PayMongo, and in its own bucket so the page's polling does
// not spend it.
Route::post('track/{token}/pay', [OnlinePaymentController::class, 'store'])
    ->middleware('throttle:30,1,track-pay')
    ->name('public.tracking.pay');

// Public, unauthenticated, signed-URL-protected (D-17 through D-21) — the
// client's remote design-review path, reached only via an emailed
// temporarySignedRoute() link. Deliberately outside every role:* group.
Route::middleware(['signed', 'throttle:60,1,design-review'])->prefix('design-review')->group(function () {
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
require __DIR__.'/admin.php';
require __DIR__.'/portals.php';
