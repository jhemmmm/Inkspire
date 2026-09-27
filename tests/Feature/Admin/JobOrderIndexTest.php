<?php

use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\QueueEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin sees the job order list with totals', function () {
    $admin = User::factory()->admin()->create();
    $pricingEntry = PricingEntry::factory()->create(['name' => 'Tarpaulin']);
    $jobOrder = JobOrder::factory()->readyForProduction()->create([
        'pricing_entry_id' => $pricingEntry->id,
        'quoted_amount' => 288,
        'total_amount' => null,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.job-orders.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/JobOrders')
        ->has('jobOrders.data', 1)
        ->where('jobOrders.data.0.id', $jobOrder->id)
        ->where('jobOrders.data.0.display_total', 288)
        ->where('jobOrders.data.0.total_amount', null)
    );
});

test('the search filter narrows results by job order number or customer name', function () {
    $admin = User::factory()->admin()->create();

    $customer = Customer::factory()->create(['name' => 'Marites Dela Cruz']);
    $queueEntry = QueueEntry::factory()->for($customer)->create();
    $matching = JobOrder::factory()->for($queueEntry)->create(['number' => 'JO-2026-0001']);
    $other = JobOrder::factory()->create(['number' => 'JO-2026-9999']);

    $this->actingAs($admin)
        ->get(route('admin.job-orders.index', ['q' => 'Marites']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('jobOrders.data', 1)
            ->where('jobOrders.data.0.id', $matching->id)
        );

    $this->actingAs($admin)
        ->get(route('admin.job-orders.index', ['q' => 'JO-2026-9999']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('jobOrders.data', 1)
            ->where('jobOrders.data.0.id', $other->id)
        );
});

test('the status filter narrows results', function () {
    $admin = User::factory()->admin()->create();

    $intake = JobOrder::factory()->create(['status' => 'intake']);
    $readyForProduction = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($admin)->get(route('admin.job-orders.index', ['status' => 'ready_for_production']));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders.data', 1)
        ->where('jobOrders.data.0.id', $readyForProduction->id)
    );

    expect($intake)->not->toBeNull();
});

test('pagination limits the list to 25 per page', function () {
    $admin = User::factory()->admin()->create();
    JobOrder::factory()->count(30)->create();

    $response = $this->actingAs($admin)->get(route('admin.job-orders.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders.data', 25)
        ->where('jobOrders.total', 30)
        ->where('jobOrders.last_page', 2)
    );
});

test('a cancelled job order is excluded from the list', function () {
    $admin = User::factory()->admin()->create();
    JobOrder::factory()->create(['cancelled_at' => now()]);

    $response = $this->actingAs($admin)->get(route('admin.job-orders.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders.data', 0)
    );
});

test('every other role gets 403 on the admin job orders index', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user)->get(route('admin.job-orders.index'))->assertForbidden();
})->with(['frontlineStaff', 'artist', 'cashier', 'productionStaff', 'accountingStaff']);
