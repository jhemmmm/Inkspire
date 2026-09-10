<?php

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
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

test('a job order due today or earlier is flagged rush', function (Closure $dueAt) {
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

test('rush is scoped to the Asia/Manila business day, not the UTC one', function () {
    // 02:00 UTC is 10:00 the same day in Manila. The Manila business day
    // ends at 15:59:59 UTC; the UTC day runs eight hours longer, so a job
    // order due 07:00 tomorrow Manila (23:00 today UTC) falls inside the
    // UTC day but outside the business day it is actually due on.
    $this->travelTo(Carbon::parse('2026-09-05 02:00:00', 'UTC'));

    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value]);
    // ->utc() matters: Eloquent's datetime cast stores the wall-clock of
    // whatever timezone the Carbon instance carries, without converting.
    $jobOrder->forceFill(['due_at' => Carbon::parse('2026-09-06 07:00:00', 'Asia/Manila')->utc()])->save();

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrders.0.is_rush', false));
});

test('a job order due at the very end of the Manila business day is flagged rush', function () {
    $this->travelTo(Carbon::parse('2026-09-05 02:00:00', 'UTC'));

    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value]);
    $jobOrder->forceFill(['due_at' => Carbon::parse('2026-09-05 23:00:00', 'Asia/Manila')->utc()])->save();

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrders.0.is_rush', true));
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

test('a job order marked rush at intake is flagged rush regardless of its due date', function () {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->rush()->create(['status' => JobOrderStatus::ForProduction->value]);
    $jobOrder->forceFill(['due_at' => now()->addWeek()])->save();

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrders.0.is_rush', true));
});
