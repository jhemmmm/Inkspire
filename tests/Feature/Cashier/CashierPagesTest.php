<?php

use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the cashier dashboard lists eligible job orders', function () {
    $cashier = User::factory()->cashier()->create();
    $eligible = JobOrder::factory()->readyForProduction()->create(['description' => 'Eligible Job Order']);
    JobOrder::factory()->create(['status' => 'intake', 'description' => 'Not Eligible']);

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('cashier/Dashboard')
        ->has('jobOrders', 1)
        ->where('jobOrders.0.id', $eligible->id)
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
        ->where('hasExistingTransactions', false)
        ->where('remainingBalance', null)
    );
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
