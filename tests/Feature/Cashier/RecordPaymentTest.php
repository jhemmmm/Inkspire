<?php

use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\User;

test('a down payment leaves the job order partially paid and tracks the remaining balance', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => false,
        'payment_method' => 'cash',
        'payment_type' => 'down',
        'amount_tendered' => 400,
        'down_payment_amount' => 400,
    ]);

    $response->assertRedirect();

    $jobOrder->refresh();
    expect($jobOrder->payment_status)->toBe(PaymentStatus::PartiallyPaid);
    expect((float) $jobOrder->total_amount)->toBe(1000.0);
    expect($jobOrder->transactions()->count())->toBe(1);
    expect($jobOrder->transactions()->first()->type)->toBe(TransactionType::DownPayment);
    expect((float) $jobOrder->transactions()->first()->amount)->toBe(400.0);
});

test('a second submission against an already-priced job order ignores resubmitted pricing fields and never recomputes the total', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => false,
        'payment_method' => 'cash',
        'payment_type' => 'down',
        'amount_tendered' => 400,
        'down_payment_amount' => 400,
    ]);

    $jobOrder->refresh();
    expect((float) $jobOrder->total_amount)->toBe(1000.0);

    // A differing line_amount is submitted on the balance visit — the
    // FormRequest must ignore it entirely since total_amount is already set.
    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'line_amount' => 5000,
        'payment_method' => 'cash',
        'payment_type' => 'full',
        'amount_tendered' => 600,
    ]);

    $response->assertRedirect();

    $jobOrder->refresh();
    expect((float) $jobOrder->total_amount)->toBe(1000.0);
    expect($jobOrder->payment_status)->toBe(PaymentStatus::Paid);
    expect($jobOrder->transactions()->count())->toBe(2);
    expect((float) $jobOrder->transactions()->latest('id')->first()->amount)->toBe(600.0);
});

test('a down payment cannot exceed the remaining balance', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => false,
        'payment_method' => 'cash',
        'payment_type' => 'down',
        'amount_tendered' => 400,
        'down_payment_amount' => 400,
    ]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'payment_method' => 'cash',
        'payment_type' => 'down',
        'amount_tendered' => 900,
        'down_payment_amount' => 900,
    ]);

    $response->assertSessionHasErrors('down_payment_amount');
});

test('a bank transfer payment records the reference number', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 750]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 750,
        'rush_fee_applied' => false,
        'payment_method' => 'bank_transfer',
        'payment_type' => 'full',
        'reference_number' => 'BT-12345',
    ]);

    $response->assertRedirect();
    $jobOrder->refresh();
    expect($jobOrder->payment_status)->toBe(PaymentStatus::Paid);
    expect($jobOrder->transactions()->first()->reference_number)->toBe('BT-12345');
});

test('an already fully paid job order cannot be paid again', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 300]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 300,
        'rush_fee_applied' => false,
        'payment_method' => 'cash',
        'payment_type' => 'full',
        'amount_tendered' => 300,
    ]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'payment_method' => 'cash',
        'payment_type' => 'full',
        'amount_tendered' => 300,
    ]);

    $response->assertStatus(422);
});
