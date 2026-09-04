<?php

use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a cashier viewing the receipt for a job order with a completed cash transaction sees the correct totals', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create([
        'pricing_entry_id' => $pricingEntry->id,
        'base_price_snapshot' => 1000,
        'rush_fee_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 1000,
        'payment_status' => 'paid',
    ]);
    Transaction::factory()->create([
        'job_order_id' => $jobOrder->id,
        'payment_method' => 'cash',
        'amount' => 1000,
        'status' => 'completed',
        'recorded_by' => $cashier->id,
    ]);

    $response = $this->actingAs($cashier)->get(route('cashier.job-orders.receipt.show', $jobOrder));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('cashier/Receipt')
        ->where('jobOrder.total_amount', '1000.00')
        ->where('balance', 0)
    );
});

test('a cashier viewing the receipt for a job order with zero transactions gets a 404', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->get(route('cashier.job-orders.receipt.show', $jobOrder));

    $response->assertNotFound();
});
