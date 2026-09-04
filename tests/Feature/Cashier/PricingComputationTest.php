<?php

use App\Enums\PaymentStatus;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\SystemConfiguration;
use App\Models\User;

test('a cashier prices and records a full cash payment, computing the total server-side', function () {
    SystemConfiguration::create([
        'key' => 'rush_fee_percentage',
        'group' => 'business_rules',
        'value' => 10,
        'type' => 'decimal',
        'label' => 'Rush fee (%)',
    ]);

    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => true,
        'payment_method' => 'cash',
        'payment_type' => 'full',
        'amount_tendered' => 1100,
    ]);

    $response->assertRedirect();

    $jobOrder->refresh();
    expect($jobOrder->payment_status)->toBe(PaymentStatus::Paid);
    expect((float) $jobOrder->base_price_snapshot)->toBe(1000.0);
    expect((float) $jobOrder->rush_fee_amount)->toBe(100.0);
    expect((float) $jobOrder->total_amount)->toBe(1100.0);
    expect($jobOrder->transactions()->count())->toBe(1);
});

test('a client-submitted total is never trusted — the server always recomputes from line amount and rush fee', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 500]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 500,
        'rush_fee_applied' => false,
        'payment_method' => 'bank_transfer',
        'payment_type' => 'full',
        'reference_number' => 'REF-001',
        // Attempting to smuggle a fabricated total — the controller/action
        // never reads this field at all, so it must be silently ignored.
        'total_amount' => 999999,
    ]);

    $jobOrder->refresh();
    expect((float) $jobOrder->total_amount)->toBe(500.0);
});

test('a design approved job order can also be priced and paid', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 250]);
    $jobOrder = JobOrder::factory()->create(['status' => 'design_approved']);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 250,
        'rush_fee_applied' => false,
        'payment_method' => 'cash',
        'payment_type' => 'full',
        'amount_tendered' => 250,
    ]);

    $response->assertRedirect();
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('a job order not yet ready for production or design approved cannot be priced', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create();
    $jobOrder = JobOrder::factory()->create(['status' => 'intake']);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 100,
        'rush_fee_applied' => false,
        'payment_method' => 'cash',
        'payment_type' => 'full',
        'amount_tendered' => 100,
    ]);

    $response->assertStatus(422);
});

test('a non cashier role is blocked from recording a payment', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $pricingEntry = PricingEntry::factory()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($staff)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 100,
        'rush_fee_applied' => false,
        'payment_method' => 'cash',
        'payment_type' => 'full',
        'amount_tendered' => 100,
    ]);

    $response->assertForbidden();
});
