<?php

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the board lists a job order on a production stage with the required fields', function () {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::ForProduction->value,
        'description' => 'Tarpaulin, 3x5ft',
    ]);
    $jobOrder->forceFill(['due_at' => now()->addDays(3)])->save();

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('production-staff/Dashboard')
        ->has('jobOrders', 1)
        ->where('jobOrders.0.id', $jobOrder->id)
        ->where('jobOrders.0.number', $jobOrder->number)
        ->where('jobOrders.0.description', $jobOrder->description)
        ->where('jobOrders.0.status', $jobOrder->status->value)
        ->where('jobOrders.0.queue_entry_id', $jobOrder->queue_entry_id)
        ->has('jobOrders.0.due_at')
        ->where('jobOrders.0.is_rush', false));
});

test('a job order due today or earlier is flagged rush', function (\Closure $dueAt) {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value]);
    $jobOrder->forceFill(['due_at' => $dueAt()])->save();

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrders.0.is_rush', true));
})->with([
    'due right now' => fn () => now(),
    'due earlier today' => fn () => now()->startOfDay(),
    'overdue by a day' => fn () => now()->subDay(),
]);

test('a job order due strictly after today is not flagged rush', function () {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::QualityCheck->value]);
    $jobOrder->forceFill(['due_at' => now()->addDay()])->save();

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrders.0.is_rush', false));
});

test('a job order with no due_at is not flagged rush', function () {
    $staff = User::factory()->productionStaff()->create();
    JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForPickup->value]);

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrders.0.is_rush', false));
});

test('a job order not yet entered production does not appear on the board', function (JobOrderStatus $status) {
    $staff = User::factory()->productionStaff()->create();
    JobOrder::factory()->create(['status' => $status->value]);

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders', 0));
})->with([
    JobOrderStatus::ReadyForProduction,
    JobOrderStatus::DesignApproved,
]);

test('a released job order does not appear on the board even if its status is a board status', function () {
    $staff = User::factory()->productionStaff()->create();
    JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
        'released_at' => now(),
    ]);

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders', 0));
});

test('a cancelled job order does not appear on the board even if its status is a board status', function () {
    $staff = User::factory()->productionStaff()->create();
    JobOrder::factory()->create([
        'status' => JobOrderStatus::Printing->value,
        'cancelled_at' => now(),
    ]);

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders', 0));
});

test('the board response never leaks payment or pricing data', function () {
    $staff = User::factory()->productionStaff()->create();
    JobOrder::factory()->create([
        'status' => JobOrderStatus::ForProduction->value,
        'description' => 'Confidential customer description text',
    ]);

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertOk();
    $content = $response->getContent();
    expect($content)->not->toContain('payment_status');
    expect($content)->not->toContain('total_amount');
});

test('a non-production-staff role is forbidden from the board', function () {
    $staff = User::factory()->frontlineStaff()->create();

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertForbidden();
});
