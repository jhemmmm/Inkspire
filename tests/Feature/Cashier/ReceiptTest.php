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

test('a job order with no number yields a bare tracking URL, which is why the receipt hides the QR block entirely', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create([
        'total_amount' => 500,
        'payment_status' => 'paid',
    ]);
    $jobOrder->forceFill(['number' => null])->save();
    Transaction::factory()->create([
        'job_order_id' => $jobOrder->id,
        'payment_method' => 'cash',
        'amount' => 500,
        'status' => 'completed',
        'recorded_by' => $cashier->id,
    ]);

    $response = $this->actingAs($cashier)->get(route('cashier.job-orders.receipt.show', $jobOrder));

    $response->assertOk();
    // route() drops the query string entirely for a null parameter, so the
    // QR would encode a bare lookup form and the fallback line would read
    // "Or visit http://host/track and enter " with nothing after it.
    $response->assertInertia(fn (Assert $page) => $page
        ->component('cashier/Receipt')
        ->where('jobOrder.number', null)
        ->where('trackingUrl', fn ($url) => ! str_contains($url, 'number=')));
});

test('the receipt includes the job order number and a tracking URL deep-linking to it', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create([
        'number' => 'JO-2026-0007',
        'total_amount' => 500,
        'payment_status' => 'paid',
    ]);
    Transaction::factory()->create([
        'job_order_id' => $jobOrder->id,
        'payment_method' => 'cash',
        'amount' => 500,
        'status' => 'completed',
        'recorded_by' => $cashier->id,
    ]);

    $response = $this->actingAs($cashier)->get(route('cashier.job-orders.receipt.show', $jobOrder));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('cashier/Receipt')
        ->where('jobOrder.number', 'JO-2026-0007')
        ->where('trackingUrl', fn ($url) => str_contains($url, '/track') && str_contains($url, 'JO-2026-0007')));
});
