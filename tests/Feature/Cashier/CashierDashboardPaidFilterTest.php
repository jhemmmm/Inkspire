<?php

use App\Enums\AccountsReceivableStatus;
use App\Enums\JobOrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a fully paid job order is gone from the cashier dashboard', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);
    $jobOrder->forceFill([
        'total_amount' => 1000,
        'payment_status' => PaymentStatus::Paid->value,
    ])->save();
    Transaction::factory()->for($jobOrder)->create(['amount' => 1000]);

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('cashier/Dashboard')
        ->has('jobOrders', 0));
});

test('a job order settled by several transactions summing to the total is also gone', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value]);
    $jobOrder->forceFill(['total_amount' => 1000])->save();
    Transaction::factory()->for($jobOrder)->downPayment()->create(['amount' => 400]);
    Transaction::factory()->for($jobOrder)->create(['amount' => 600]);

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page->has('jobOrders', 0));
});

test('a partially paid job order stays on the dashboard', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);
    $jobOrder->forceFill([
        'total_amount' => 1000,
        'payment_status' => PaymentStatus::PartiallyPaid->value,
    ])->save();
    Transaction::factory()->for($jobOrder)->downPayment()->create(['amount' => 400]);

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders', 1)
        ->where('jobOrders.0.id', $jobOrder->id)
        ->where('jobOrders.0.amount_paid', 400));
});

test('an on-credit job order with an outstanding balance stays on the dashboard', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value]);
    $jobOrder->forceFill([
        'total_amount' => 1000,
        'payment_status' => PaymentStatus::PartiallyPaid->value,
    ])->save();
    AccountsReceivable::factory()->for($jobOrder)->create([
        'balance' => 1000,
        'status' => AccountsReceivableStatus::Active->value,
    ]);

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders', 1)
        ->where('jobOrders.0.id', $jobOrder->id));
});

test('a job order that has not been priced yet stays on the dashboard', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForProduction->value]);

    expect($jobOrder->fresh()->total_amount)->toBeNull();

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders', 1)
        ->where('jobOrders.0.id', $jobOrder->id)
        ->where('jobOrders.0.total_amount', null));
});

test('the surviving rows serialise as a json array, not a keyed object', function () {
    $cashier = User::factory()->cashier()->create();

    // The paid row is created FIRST so rejecting it leaves a gap at key 0 —
    // reject() preserves keys, and a gapped Collection serialises to Inertia
    // as an object, which breaks v-for and the CashierJobOrder[] prop type.
    $paid = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);
    $paid->forceFill(['total_amount' => 500, 'created_at' => now()->subHour()])->save();
    Transaction::factory()->for($paid)->create(['amount' => 500]);

    $unpaid = JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value]);
    $unpaid->forceFill(['total_amount' => 800])->save();

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders', 1)
        ->where('jobOrders.0.id', $unpaid->id));

    $props = $response->viewData('page')['props'];
    expect(array_keys($props['jobOrders']))->toBe([0]);
});

test('a peso-exact payment is not left on the list by decimal rounding dust', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::QualityCheck->value]);
    $jobOrder->forceFill(['total_amount' => 1234.56])->save();
    Transaction::factory()->for($jobOrder)->create(['amount' => 1234.56]);

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page->has('jobOrders', 0));
});

test('a pending gcash payment does not count towards settling a job order', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);
    $jobOrder->forceFill(['total_amount' => 1000])->save();
    Transaction::factory()->for($jobOrder)->pendingConfirmation()->create(['amount' => 1000]);

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders', 1)
        ->where('jobOrders.0.id', $jobOrder->id));
});

test('the dashboard carries the rush flag for each listed job order', function () {
    $cashier = User::factory()->cashier()->create();
    $rush = JobOrder::factory()->rush()->create(['status' => JobOrderStatus::ForProduction->value]);
    $rush->forceFill(['created_at' => now()->subHour()])->save();
    $notRush = JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value]);

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('jobOrders', 2)
        ->where('jobOrders.0.id', $rush->id)
        ->where('jobOrders.0.is_rush', true)
        ->where('jobOrders.1.id', $notRush->id)
        ->where('jobOrders.1.is_rush', false));
});
