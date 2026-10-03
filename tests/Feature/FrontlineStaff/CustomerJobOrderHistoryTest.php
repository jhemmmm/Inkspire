<?php

use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('selecting a returning customer returns their job orders, newest first', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();
    $queueEntry = QueueEntry::factory()->for($customer)->create();

    $oldest = JobOrder::factory()->for($queueEntry)->create(['description' => 'Tarpaulin, 3x5ft']);
    $newest = JobOrder::factory()->for($queueEntry)->create(['description' => 'Sticker, A4']);

    $response = $this->actingAs($staff)->get(route('frontline-staff.new-visit', ['customer' => $customer->id]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('frontline-staff/NewVisit')
        ->has('customerJobOrders', 2)
        ->where('customerJobOrders.0.id', $newest->id)
        ->where('customerJobOrders.0.number', $newest->number)
        ->where('customerJobOrders.0.description', 'Sticker, A4')
        ->where('customerJobOrders.0.type', $newest->type->value)
        ->where('customerJobOrders.0.status', $newest->status->value)
        ->has('customerJobOrders.0.created_at')
        ->where('customerJobOrders.1.id', $oldest->id));
});

test('a first-time customer returns an empty history', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $response = $this->actingAs($staff)->get(route('frontline-staff.new-visit', ['customer' => $customer->id]));

    $response->assertInertia(fn (Assert $page) => $page->has('customerJobOrders', 0));
});

test('no customer selected returns an empty history', function () {
    $staff = User::factory()->frontlineStaff()->create();
    JobOrder::factory()->create();

    $response = $this->actingAs($staff)->get(route('frontline-staff.new-visit'));

    $response->assertInertia(fn (Assert $page) => $page->has('customerJobOrders', 0));
});

test('another customer\'s job orders never appear in the history', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();
    $stranger = Customer::factory()->create();

    $theirs = JobOrder::factory()
        ->for(QueueEntry::factory()->for($customer))
        ->create(['description' => 'Belongs to the selected customer']);
    JobOrder::factory()
        ->for(QueueEntry::factory()->for($stranger))
        ->create(['description' => 'Belongs to somebody else entirely']);

    $response = $this->actingAs($staff)->get(route('frontline-staff.new-visit', ['customer' => $customer->id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('customerJobOrders', 1)
        ->where('customerJobOrders.0.id', $theirs->id));
    $response->assertDontSee('Belongs to somebody else entirely', false);
});

test('the history holds only the past 7 days of orders, each flagged rush or not', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();
    $queueEntry = QueueEntry::factory()->for($customer)->create();
    $this->travelTo('2026-09-28 12:00:00');

    JobOrder::factory()->for($queueEntry)->create(['created_at' => '2026-09-21 11:00:00']);
    $withinWeek = JobOrder::factory()->for($queueEntry)->create(['created_at' => '2026-09-21 13:00:00', 'is_rush' => true]);

    $response = $this->actingAs($staff)->get(route('frontline-staff.new-visit', ['customer' => $customer->id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('customerJobOrders', 1)
        ->where('customerJobOrders.0.id', $withinWeek->id)
        ->where('customerJobOrders.0.is_rush', true));
});

test('the history costs one query no matter how many visits the customer has', function () {
    $staff = User::factory()->frontlineStaff()->create();

    $countHistoryQueries = function (Customer $customer) use ($staff): int {
        $statements = 0;
        DB::listen(function (QueryExecuted $query) use (&$statements): void {
            if (str_contains($query->sql, 'from `job_orders`') && str_contains($query->sql, 'queue_entries')) {
                $statements++;
            }
        });

        $this->actingAs($staff)->get(route('frontline-staff.new-visit', ['customer' => $customer->id]))->assertOk();

        return $statements;
    };

    $oneVisit = Customer::factory()->create();
    JobOrder::factory()->for(QueueEntry::factory()->for($oneVisit))->create();

    $manyVisits = Customer::factory()->create();
    collect(range(1, 6))->each(fn () => JobOrder::factory()
        ->for(QueueEntry::factory()->for($manyVisits))
        ->create());

    // Eager-loading the visit tree and flattening it would fan out with the
    // visit count; one constrained query does not.
    expect($countHistoryQueries($manyVisits))->toBe($countHistoryQueries($oneVisit));
});

test('the confirmation block carries a tracking token per job order and an absolute tracking base url', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();
    $queueEntry = QueueEntry::factory()->for($customer)->create();
    $jobOrder = JobOrder::factory()->for($queueEntry)->create();

    $response = $this->actingAs($staff)->get(route('frontline-staff.new-visit', [
        'customer' => $customer->id,
        'queueEntry' => $queueEntry->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('confirmedQueueEntry.job_orders', 1)
        ->where('confirmedQueueEntry.job_orders.0.tracking_token', $jobOrder->tracking_token)
        ->where('confirmedQueueEntry.job_orders.0.number', $jobOrder->number)
        ->where('trackingBaseUrl', url('track')));

    expect($response->viewData('page')['props']['trackingBaseUrl'])->toEndWith('/track');
});

test('a released job order in the history reports released, not the stage it was last at', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();
    JobOrder::factory()
        ->for(QueueEntry::factory()->for($customer))
        ->create(['status' => 'ready_for_pickup', 'released_at' => now()]);

    $response = $this->actingAs($staff)->get(route('frontline-staff.new-visit', ['customer' => $customer->id]));

    $response->assertInertia(fn (Assert $page) => $page->where('customerJobOrders.0.display_status', 'released'));
});
