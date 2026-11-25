<?php

use App\Models\AuditLog;
use App\Models\JobOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * @param  array<string, mixed>  $attributes
 */
function pickupJobOrder(array $attributes): JobOrder
{
    return tap(JobOrder::factory()->create(), fn (JobOrder $jobOrder) => $jobOrder->forceFill($attributes)->save());
}

test('a customer confirms an order staff released, and it is audited without a staff user', function () {
    $this->freezeSecond();
    $jobOrder = pickupJobOrder(['status' => 'ready_for_pickup', 'released_at' => now()->subHour()]);

    $this->post(route('public.tracking.received', ['token' => $jobOrder->tracking_token]))
        ->assertRedirect(route('public.tracking.token', ['token' => $jobOrder->tracking_token]));

    expect($jobOrder->fresh()->pickup_confirmed_at->equalTo(now()))->toBeTrue();

    $entry = AuditLog::query()->latest('id')->first();
    expect($entry->user_id)->toBeNull()
        ->and($entry->new_values)->toHaveKey('pickup_confirmed_at');
});

test('confirming again keeps the first time', function () {
    $firstConfirmedAt = Carbon::parse('2026-11-20 09:00:00');
    $jobOrder = pickupJobOrder([
        'status' => 'ready_for_pickup',
        'released_at' => $firstConfirmedAt->copy()->subDay(),
        'pickup_confirmed_at' => $firstConfirmedAt,
    ]);

    $this->post(route('public.tracking.received', ['token' => $jobOrder->tracking_token]))->assertRedirect();

    expect($jobOrder->fresh()->pickup_confirmed_at->equalTo($firstConfirmedAt))->toBeTrue();
});

test('an order the customer cannot have received is left unconfirmed', function (array $attributes) {
    $jobOrder = pickupJobOrder($attributes);

    $this->post(route('public.tracking.received', ['token' => $jobOrder->tracking_token]))
        ->assertRedirect(route('public.tracking.token', ['token' => $jobOrder->tracking_token]));

    expect($jobOrder->fresh()->pickup_confirmed_at)->toBeNull();
})->with([
    'still printing' => [['status' => 'printing']],
    'ready but not released' => [['status' => 'ready_for_pickup']],
    'cancelled after release' => [['status' => 'ready_for_pickup', 'released_at' => '2026-11-20 09:00:00', 'cancelled_at' => '2026-11-20 10:00:00']],
]);

test('an unknown token is not found', function () {
    $this->post(route('public.tracking.received', ['token' => 'thisisnotarealtrackingtoken00000']))->assertNotFound();
});

test('the confirm route is public and has its own throttle bucket', function () {
    expect(Route::getRoutes()->getByName('public.tracking.received')->gatherMiddleware())
        ->toBe(['web', 'throttle:30,1,track-received']);
});
