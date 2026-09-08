<?php

use App\Enums\PaymentStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
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
