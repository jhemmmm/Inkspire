<?php

use App\Models\JobOrder;
use Illuminate\Database\Eloquent\MissingAttributeException;

test('display_status throws when a query left out the columns it needs, rather than showing a stale stage', function () {
    JobOrder::factory()->create(['released_at' => now()]);

    $jobOrder = JobOrder::query()->select(['id', 'status'])->sole();

    expect(fn () => $jobOrder->display_status)->toThrow(MissingAttributeException::class);
});

test('whereDisplayStatus finds the orders display_status would label that way', function (string $displayStatus, string $expected) {
    $orders = [
        'on the shelf' => JobOrder::factory()->create(['status' => 'ready_for_pickup']),
        'released' => JobOrder::factory()->create(['status' => 'ready_for_pickup', 'released_at' => '2026-10-03 02:00:00']),
        'cancelled' => JobOrder::factory()->create(['status' => 'ready_for_pickup', 'cancelled_at' => '2026-10-03 02:00:00']),
    ];

    $found = JobOrder::query()->whereDisplayStatus($displayStatus)->pluck('id')->all();

    expect($found)->toBe([$orders[$expected]->id]);
})->with([
    'a stage leaves out orders that left the shop' => ['ready_for_pickup', 'on the shelf'],
    'released' => [JobOrder::DISPLAY_RELEASED, 'released'],
    'cancelled' => [JobOrder::DISPLAY_CANCELLED, 'cancelled'],
]);
