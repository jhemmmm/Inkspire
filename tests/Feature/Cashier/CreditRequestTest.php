<?php

use App\Enums\PaymentStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\User;

test('invalid credit pricing returns field errors to the payment page without creating credit', function (array $invalidPricing, string $field, string $message) {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $paymentPage = route('cashier.job-orders.payment.edit', $jobOrder);
    $pricing = array_merge([
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => false,
    ], $invalidPricing);

    $response = $this->actingAs($cashier)
        ->from($paymentPage)
        ->withHeader('X-Inertia', 'true')
        ->post(route('cashier.job-orders.credit-request.store', $jobOrder), $pricing);

    $response->assertRedirect($paymentPage);
    $response->assertSessionHasErrors([$field => $message]);
    $this->assertDatabaseCount('accounts_receivable', 0);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Unpaid);
    expect($jobOrder->fresh()->total_amount)->toBeNull();
})->with([
    'missing product' => [
        ['pricing_entry_id' => null],
        'pricing_entry_id',
        'The pricing entry id field is required.',
    ],
    'discount over the cap' => [
        ['discount_type' => 'percentage', 'discount_value' => 999999],
        'discount_value',
        "Discount can't exceed the configured cap.",
    ],
]);

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
