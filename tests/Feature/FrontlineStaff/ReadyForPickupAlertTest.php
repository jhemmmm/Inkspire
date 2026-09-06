<?php

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the frontline dashboard shows every job order ready for pickup', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
        'description' => 'Tarpaulin, 3x5ft',
    ]);

    $response = $this->actingAs($staff)->get(route('frontline-staff.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('frontline-staff/Dashboard')
        ->has('readyForPickup', 1)
        ->where('readyForPickup.0.id', $jobOrder->id)
        ->where('readyForPickup.0.number', $jobOrder->number)
        ->where('readyForPickup.0.description', $jobOrder->description)
        ->where('readyForPickup.0.payment_status', $jobOrder->payment_status->value)
        ->where('readyForPickup.0.queue_entry_id', $jobOrder->queue_entry_id)
        ->has('readyForPickup.0.updated_at')
        ->where('readyForPickup.0.queue_entry.customer.name', $jobOrder->queueEntry->customer->name));
});

test('a released job order does not appear on the dashboard', function () {
    $staff = User::factory()->frontlineStaff()->create();
    JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
        'released_at' => now(),
    ]);

    $response = $this->actingAs($staff)->get(route('frontline-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('frontline-staff/Dashboard')
        ->has('readyForPickup', 0));
});

test('a cancelled job order does not appear on the dashboard', function () {
    $staff = User::factory()->frontlineStaff()->create();
    JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
        'cancelled_at' => now(),
    ]);

    $response = $this->actingAs($staff)->get(route('frontline-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('frontline-staff/Dashboard')
        ->has('readyForPickup', 0));
});

test('a job order sent back a stage from ready for pickup disappears from the dashboard', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
    ]);
    $jobOrder->forceFill(['status' => JobOrderStatus::QualityCheck->value])->save();

    $response = $this->actingAs($staff)->get(route('frontline-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('frontline-staff/Dashboard')
        ->has('readyForPickup', 0));
});

test('the queue page carries a ready-for-pickup summary with count and up to two oldest names', function () {
    $staff = User::factory()->frontlineStaff()->create();

    $first = JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForPickup->value]);
    JobOrder::withoutTimestamps(fn () => $first->forceFill(['updated_at' => now()->subMinutes(30)])->save());

    $second = JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForPickup->value]);
    JobOrder::withoutTimestamps(fn () => $second->forceFill(['updated_at' => now()->subMinutes(20)])->save());

    $third = JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForPickup->value]);
    JobOrder::withoutTimestamps(fn () => $third->forceFill(['updated_at' => now()->subMinutes(10)])->save());

    $response = $this->actingAs($staff)->get(route('frontline-staff.queue-entries.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('frontline-staff/QueueList')
        ->where('readyForPickup.count', 3)
        ->has('readyForPickup.items', 2)
        ->where('readyForPickup.items.0.number', $first->number)
        ->where('readyForPickup.items.1.number', $second->number));
});

test('a released or cancelled job order is excluded from the queue page summary', function () {
    $staff = User::factory()->frontlineStaff()->create();
    JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
        'released_at' => now(),
    ]);
    JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
        'cancelled_at' => now(),
    ]);

    $response = $this->actingAs($staff)->get(route('frontline-staff.queue-entries.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('frontline-staff/QueueList')
        ->where('readyForPickup.count', 0)
        ->has('readyForPickup.items', 0));
});

test('a job order sent back a stage from ready for pickup disappears from the queue page summary', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
    ]);
    $jobOrder->forceFill(['status' => JobOrderStatus::QualityCheck->value])->save();

    $response = $this->actingAs($staff)->get(route('frontline-staff.queue-entries.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('frontline-staff/QueueList')
        ->where('readyForPickup.count', 0));
});
