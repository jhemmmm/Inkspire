<?php

use App\Enums\JobOrderStatus;
use App\Enums\PaymentStatus;
use App\Models\JobOrder;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('the frontline dashboard shows every job order ready for pickup', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
        'description' => 'Tarpaulin, 3x5ft',
        'quoted_amount' => 288,
    ])->refresh();

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
        ->where('readyForPickup.0.queue_entry.customer.name', $jobOrder->queueEntry->customer->name)
        ->where('readyForPickup.0.display_total', 288)
        ->has('readyForPickup.0.amount_paid'));
});

test('ready since and the dashboard ordering come from the logged ready_for_pickup transition, not updated_at', function () {
    $staff = User::factory()->frontlineStaff()->create();

    $waitingLongest = JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForPickup->value]);
    ProductionLog::factory()->for($waitingLongest)->create([
        'from_status' => JobOrderStatus::QualityCheck->value,
        'to_status' => JobOrderStatus::ReadyForPickup->value,
        'created_at' => now()->subHours(2),
    ]);

    $waitingBriefly = JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForPickup->value]);
    ProductionLog::factory()->for($waitingBriefly)->create([
        'from_status' => JobOrderStatus::QualityCheck->value,
        'to_status' => JobOrderStatus::ReadyForPickup->value,
        'created_at' => now()->subMinutes(10),
    ]);

    // An unrelated write — taking payment at the counter — bumps
    // updated_at on the order that has actually been on the shelf longest,
    // inverting the updated_at ordering relative to the real one.
    JobOrder::withoutTimestamps(fn () => $waitingBriefly->forceFill(['updated_at' => now()->subHour()])->save());
    $waitingLongest->forceFill(['payment_status' => PaymentStatus::Paid->value])->save();

    $response = $this->actingAs($staff)->get(route('frontline-staff.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('readyForPickup', 2)
        ->where('readyForPickup.0.id', $waitingLongest->id)
        ->where('readyForPickup.1.id', $waitingBriefly->id)
        ->has('readyForPickup.0.ready_at'));
});

test('the ready_at aggregate does not widen the dashboard payload beyond its column list', function () {
    // withAggregate() falls back to selecting job_orders.* when no columns
    // are set before it runs, which would silently expose pricing data not
    // on the explicit select() list — total_amount/quoted_amount/is_rush
    // ARE intentionally selected (they power the Total/Balance columns),
    // so base_price_snapshot (never selected here) is the canary instead.
    $staff = User::factory()->frontlineStaff()->create();
    JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
        'total_amount' => 1234.56,
        'base_price_snapshot' => 999.99,
    ]);

    $response = $this->actingAs($staff)->get(route('frontline-staff.dashboard'));

    $response->assertOk();
    expect($response->getContent())->not->toContain('base_price_snapshot');
});

test('the queue page summary orders by the logged ready_for_pickup transition, not updated_at', function () {
    $staff = User::factory()->frontlineStaff()->create();

    $waitingLongest = JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForPickup->value]);
    ProductionLog::factory()->for($waitingLongest)->create([
        'to_status' => JobOrderStatus::ReadyForPickup->value,
        'created_at' => now()->subHours(2),
    ]);

    $waitingBriefly = JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForPickup->value]);
    ProductionLog::factory()->for($waitingBriefly)->create([
        'to_status' => JobOrderStatus::ReadyForPickup->value,
        'created_at' => now()->subMinutes(10),
    ]);

    JobOrder::withoutTimestamps(fn () => $waitingBriefly->forceFill(['updated_at' => now()->subHour()])->save());
    $waitingLongest->forceFill(['payment_status' => PaymentStatus::Paid->value])->save();

    $response = $this->actingAs($staff)->get(route('frontline-staff.queue-entries.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('readyForPickup.items.0.number', $waitingLongest->number)
        ->where('readyForPickup.items.1.number', $waitingBriefly->number));
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

test('the queue page summary count and items come from a single fetch, not two unsynchronised queries', function () {
    $staff = User::factory()->frontlineStaff()->create();
    JobOrder::factory()->count(3)->create(['status' => JobOrderStatus::ReadyForPickup->value]);

    $readyForPickupStatements = 0;
    DB::listen(function (QueryExecuted $query) use (&$readyForPickupStatements): void {
        if (in_array(JobOrderStatus::ReadyForPickup->value, $query->bindings, true)) {
            $readyForPickupStatements++;
        }
    });

    $response = $this->actingAs($staff)->get(route('frontline-staff.queue-entries.index'));

    $response->assertOk();
    // Two statements with no snapshot between them let a release land in
    // the gap and yield `count: 1, items: []`, which the banner renders as
    // "1 job order ready for pickup /  is waiting on the shelf."
    expect($readyForPickupStatements)->toBe(1);
    $response->assertInertia(fn (Assert $page) => $page
        ->where('readyForPickup.count', 3)
        ->has('readyForPickup.items', 2));
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
