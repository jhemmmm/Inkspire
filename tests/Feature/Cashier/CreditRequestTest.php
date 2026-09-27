<?php

use App\Enums\PaymentStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\User;

test('a written-off job order cannot be placed back on credit', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['payment_status' => 'written_off', 'total_amount' => 1000]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.credit-request.store', $jobOrder), [], ['Accept' => 'application/json']);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => 'This job order has been written off and cannot be placed on credit.']);
    expect(AccountsReceivable::count())->toBe(0);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::WrittenOff);
});

test('a credit request snapshots edited pricing on a totalled, transaction-less order (bug 3)', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1500]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create([
        'total_amount' => 1000,
        'base_price_snapshot' => 1000,
        'rush_fee_amount' => 0,
    ]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.credit-request.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1500,
        'rush_fee_applied' => false,
    ]);

    $response->assertRedirect();

    $jobOrder->refresh();
    expect((float) $jobOrder->base_price_snapshot)->toBe(1500.0);
    expect((float) $jobOrder->total_amount)->toBe(1500.0);
    expect($jobOrder->payment_status)->toBe(PaymentStatus::CreditPendingApproval);
    expect((float) AccountsReceivable::first()->balance)->toBe(1500.0);
});
