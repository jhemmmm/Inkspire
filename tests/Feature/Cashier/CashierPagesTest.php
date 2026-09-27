<?php

use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the cashier dashboard lists eligible job orders', function () {
    $cashier = User::factory()->cashier()->create();
    $eligible = JobOrder::factory()->readyForProduction()->create([
        'description' => 'Eligible Job Order',
        'quoted_amount' => 288,
    ]);
    JobOrder::factory()->create(['status' => 'intake', 'description' => 'Not Eligible']);

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('cashier/Dashboard')
        ->has('jobOrders', 1)
        ->where('jobOrders.0.id', $eligible->id)
        ->where('jobOrders.0.display_total', 288)
        ->has('jobOrders.0.amount_paid')
    );
});

test('the job order payment page renders the pricing catalog and system config for an eligible job order', function () {
    $cashier = User::factory()->cashier()->create();
    PricingEntry::factory()->create(['is_active' => true]);
    PricingEntry::factory()->inactive()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->get(route('cashier.job-orders.payment.edit', $jobOrder));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('cashier/JobOrderPayment')
        ->has('pricingEntries', 1)
        ->where('pricingLocked', false)
        ->where('remainingBalance', null)
    );
});

test('the job order payment page prefills from the intake quote and total_amount stays null until the cashier saves', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create([
        'quoted_amount' => 288,
        'total_amount' => null,
    ]);

    $response = $this->actingAs($cashier)->get(route('cashier.job-orders.payment.edit', $jobOrder));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('cashier/JobOrderPayment')
        ->where('jobOrder.quoted_amount', '288.00')
        ->where('jobOrder.total_amount', null)
    );

    // The PayMongo/credit-eligibility sentinel — intake must never have
    // written total_amount, only the Cashier's own store() call may.
    expect($jobOrder->fresh()->total_amount)->toBeNull();
});

test('the job order payment page is not reachable for a job order still in intake', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['status' => 'intake']);

    $response = $this->actingAs($cashier)->get(route('cashier.job-orders.payment.edit', $jobOrder));

    $response->assertStatus(422);
});

test('a non cashier role cannot view the cashier dashboard', function () {
    $staff = User::factory()->frontlineStaff()->create();

    $response = $this->actingAs($staff)->get(route('cashier.dashboard'));

    $response->assertForbidden();
});

test('the cashier dashboard exposes payment_status for every eligible job order status, including on-credit states', function () {
    $cashier = User::factory()->cashier()->create();

    $onCredit = JobOrder::factory()->readyForProduction()->create(['payment_status' => 'on_credit', 'total_amount' => 1000]);
    $creditRejected = JobOrder::factory()->readyForProduction()->create(['payment_status' => 'credit_rejected', 'total_amount' => 1000]);
    $creditPendingApproval = JobOrder::factory()->readyForProduction()->create(['payment_status' => 'credit_pending_approval', 'total_amount' => 1000]);

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertOk();
    $response->assertInertia(function (Assert $page) use ($onCredit, $creditRejected, $creditPendingApproval) {
        $jobOrders = collect($page->toArray()['props']['jobOrders']);

        expect($jobOrders->firstWhere('id', $onCredit->id)['payment_status'])->toBe('on_credit');
        expect($jobOrders->firstWhere('id', $creditRejected->id)['payment_status'])->toBe('credit_rejected');
        expect($jobOrders->firstWhere('id', $creditPendingApproval->id)['payment_status'])->toBe('credit_pending_approval');
    });
});

test('the payment page is reachable for an on_credit job order (CR-02)', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['payment_status' => 'on_credit', 'total_amount' => 1000]);

    $response = $this->actingAs($cashier)->get(route('cashier.job-orders.payment.edit', $jobOrder));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('cashier/JobOrderPayment')
        ->where('pricingLocked', true)
    );
});
