<?php

use App\Enums\JobOrderStatus;
use App\Enums\PaymentStatus;
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
        ->where('jobOrders.0.is_rush', false)
        ->where('jobOrders.0.is_urgent', false));
});

test('a job order due today or earlier is urgent without becoming an intake rush job', function (Closure $dueAt) {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value]);
    $jobOrder->forceFill(['due_at' => $dueAt()])->save();

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrders.0.is_rush', false)
        ->where('jobOrders.0.is_urgent', true));
})->with([
    'due right now' => fn () => now(),
    'due earlier today' => fn () => now()->startOfDay(),
    'overdue by a day' => fn () => now()->subDay(),
]);

test('a job order due strictly after today is not flagged rush', function () {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value]);
    $jobOrder->forceFill(['due_at' => now()->addDay()])->save();

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrders.0.is_rush', false)
        ->where('jobOrders.0.is_urgent', false));
});

test('a due date on the next Manila business day does not mark a job rush', function () {
    $this->travelTo(Carbon::parse('2026-09-05 02:00:00', 'UTC'));

    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value]);
    $jobOrder->forceFill(['due_at' => Carbon::parse('2026-09-06 07:00:00', 'Asia/Manila')->utc()])->save();

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrders.0.is_rush', false)
        ->where('jobOrders.0.is_urgent', false));
});

test('a job order due at the end of the Manila business day remains regular when unmarked', function () {
    $this->travelTo(Carbon::parse('2026-09-05 02:00:00', 'UTC'));

    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value]);
    $jobOrder->forceFill(['due_at' => Carbon::parse('2026-09-05 23:00:00', 'Asia/Manila')->utc()])->save();

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrders.0.is_rush', false)
        ->where('jobOrders.0.is_urgent', true));
});

test('a job order with no due_at is not flagged rush', function () {
    $staff = User::factory()->productionStaff()->create();
    JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForPickup->value]);

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrders.0.is_rush', false)
        ->where('jobOrders.0.is_urgent', false));
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

test('the board exposes payment_status and cleared_for_production but never total_amount', function () {
    $staff = User::factory()->productionStaff()->create();
    $paid = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);
    $paid->forceFill(['payment_status' => PaymentStatus::Paid])->save();
    $unpaid = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertOk();
    expect($response->getContent())->not->toContain('total_amount');
    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrders', fn ($rows) => collect($rows)->firstWhere('id', $paid->id)['cleared_for_production'] === true
            && collect($rows)->firstWhere('id', $paid->id)['payment_status'] === 'paid'
            && collect($rows)->firstWhere('id', $unpaid->id)['cleared_for_production'] === false
            && collect($rows)->firstWhere('id', $unpaid->id)['payment_status'] === 'unpaid'));
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

test('the board puts overdue deadlines before future rush jobs and rush ahead of future regular jobs', function () {
    $staff = User::factory()->productionStaff()->create();
    $dueToday = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);
    $dueToday->forceFill(['due_at' => now()->subDay()])->save();
    $regular = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);
    $regular->forceFill(['due_at' => now()->addDays(2)])->save();
    $intakeRush = JobOrder::factory()->rush()->create(['status' => JobOrderStatus::ForProduction->value]);
    $intakeRush->forceFill(['due_at' => now()->addWeek()])->save();

    $response = $this->actingAs($staff)->get(route('production-staff.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrders.0.id', $dueToday->id)
        ->where('jobOrders.0.is_rush', false)
        ->where('jobOrders.0.is_urgent', true)
        ->where('jobOrders.1.id', $intakeRush->id)
        ->where('jobOrders.1.is_rush', true)
        ->where('jobOrders.1.is_urgent', false)
        ->where('jobOrders.2.id', $regular->id)
        ->where('jobOrders.2.is_rush', false));
});
