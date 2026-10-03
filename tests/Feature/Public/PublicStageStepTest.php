<?php

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;

/**
 * The tracking page draws its progress ladder from `stageStep`, and the step
 * copy lives in resources/js/components/OrderProgress.vue. These pin every
 * status to its position so the two cannot drift apart silently.
 */
test('each public stage reports its position on the customer journey', function (JobOrderStatus $status, int $expectedStep, string $expectedLabel) {
    $jobOrder = JobOrder::factory()->create(['status' => $status->value]);

    $response = $this->get(route('public.tracking.show', ['number' => $jobOrder->number]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('result.stageStep', $expectedStep)
        ->where('result.stage', $expectedLabel));
})->with([
    'intake collapses to the first step' => [JobOrderStatus::Intake, 0, 'In Progress'],
    'in design collapses to the first step' => [JobOrderStatus::InDesign, 0, 'In Progress'],
    'pending review collapses to the first step' => [JobOrderStatus::PendingReview, 0, 'In Progress'],
    'for production' => [JobOrderStatus::ForProduction, 1, 'For Production'],
    'printing' => [JobOrderStatus::Printing, 2, 'Printing'],
    'ready for pickup' => [JobOrderStatus::ReadyForPickup, 3, 'Ready for Pickup'],
]);

test('a released job order sits on the last step', function () {
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
        'released_at' => now(),
    ]);

    $this->get(route('public.tracking.show', ['number' => $jobOrder->number]))
        ->assertInertia(fn ($page) => $page
            ->where('result.stageStep', 4)
            ->where('result.stage', 'Completed'));
});

test('a cancelled job order is off the ladder entirely', function () {
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::Printing->value,
        'cancelled_at' => now(),
    ]);

    $this->get(route('public.tracking.show', ['number' => $jobOrder->number]))
        ->assertInertia(fn ($page) => $page
            ->where('result.stageStep', null)
            ->where('result.stage', 'Cancelled'));
});
