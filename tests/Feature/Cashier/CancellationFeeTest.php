<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\JobOrder;
use App\Models\SystemConfiguration;
use App\Models\Transaction;
use App\Models\User;

function seedCancellationFee(float $amount = 500.0): void
{
    SystemConfiguration::create([
        'key' => 'cancellation_fee_amount',
        'group' => 'business_rules',
        'value' => $amount,
        'type' => 'decimal',
        'label' => 'Cancellation fee (flat ₱)',
    ]);
}

test('cancelling a job order that never reached in_design collects no fee', function () {
    seedCancellationFee(500.0);
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.cancel', $jobOrder));

    $response->assertRedirect();
    $jobOrder->refresh();
    expect($jobOrder->cancelled_at)->not->toBeNull();
    expect(Transaction::count())->toBe(0);
});

test('cancelling an assigned job order (never reached in_design) collects no fee', function () {
    seedCancellationFee(500.0);
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->assigned()->create();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.cancel', $jobOrder));

    $response->assertRedirect();
    $jobOrder->refresh();
    expect($jobOrder->cancelled_at)->not->toBeNull();
    expect(Transaction::count())->toBe(0);
});

test('cancelling an in_design job order with no prior payment collects the full configured fee', function () {
    seedCancellationFee(500.0);
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['status' => 'in_design', 'total_amount' => 1000]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.cancel', $jobOrder));

    $response->assertRedirect();
    $jobOrder->refresh();
    expect($jobOrder->cancelled_at)->not->toBeNull();
    expect(Transaction::count())->toBe(1);

    $transaction = Transaction::first();
    expect($transaction->type)->toBe(TransactionType::CancellationFee);
    expect($transaction->status)->toBe(TransactionStatus::Completed);
    expect((float) $transaction->amount)->toBe(500.0);
});

test('cancelling an in_design job order whose down payment exceeds the fee nets to no new transaction', function () {
    seedCancellationFee(500.0);
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['status' => 'in_design', 'total_amount' => 1000]);
    Transaction::factory()->create([
        'job_order_id' => $jobOrder->id,
        'type' => TransactionType::DownPayment,
        'status' => TransactionStatus::Completed,
        'amount' => 700,
    ]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.cancel', $jobOrder));

    $response->assertRedirect();
    $jobOrder->refresh();
    expect($jobOrder->cancelled_at)->not->toBeNull();
    // Only the pre-existing down payment transaction — no new cancellation fee row.
    expect(Transaction::count())->toBe(1);
    expect(Transaction::first()->type)->toBe(TransactionType::DownPayment);
});

test('cancelling an in_design job order whose down payment falls short collects only the shortfall', function () {
    seedCancellationFee(500.0);
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['status' => 'in_design', 'total_amount' => 1000]);
    Transaction::factory()->create([
        'job_order_id' => $jobOrder->id,
        'type' => TransactionType::DownPayment,
        'status' => TransactionStatus::Completed,
        'amount' => 200,
    ]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.cancel', $jobOrder));

    $response->assertRedirect();
    $jobOrder->refresh();
    expect($jobOrder->cancelled_at)->not->toBeNull();
    expect(Transaction::count())->toBe(2);

    $fee = Transaction::where('type', TransactionType::CancellationFee)->first();
    expect((float) $fee->amount)->toBe(300.0);
    expect($fee->status)->toBe(TransactionStatus::Completed);
});

test('an already cancelled job order cannot be cancelled again', function () {
    seedCancellationFee(500.0);
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['cancelled_at' => now()])->save();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.cancel', $jobOrder));

    $response->assertStatus(422);
});

test('a fully paid job order cannot be cancelled from here', function () {
    seedCancellationFee(500.0);
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['status' => 'design_approved', 'payment_status' => 'paid', 'total_amount' => 1000]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.cancel', $jobOrder));

    $response->assertStatus(422);
});

test('a job order with a payment pending PayMongo confirmation cannot be cancelled (WR-03)', function () {
    seedCancellationFee(500.0);
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['status' => 'design_approved', 'payment_status' => 'pending_confirmation', 'total_amount' => 1000]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.cancel', $jobOrder));

    $response->assertStatus(422);
    expect($jobOrder->fresh()->cancelled_at)->toBeNull();
});

test('the cashier dashboard casts amount_paid to a float, not a numeric string (CR-04)', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000]);
    Transaction::factory()->create([
        'job_order_id' => $jobOrder->id,
        'type' => TransactionType::DownPayment,
        'status' => TransactionStatus::Completed,
        'amount' => 700.50,
    ]);

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    // A whole-number float (e.g. 700.0) round-trips through json_encode as
    // "700", indistinguishable from an int once re-decoded — using a
    // fractional amount here keeps this assertion meaningful: the bug this
    // guards against (CR-04) is amount_paid arriving as the JSON STRING
    // "700.50" (which has no .toFixed() in JS), not merely an int/float
    // distinction.
    $response->assertInertia(fn ($page) => $page
        ->component('cashier/Dashboard')
        ->whereType('jobOrders.0.amount_paid', 'double')
        ->where('jobOrders.0.amount_paid', 700.5)
    );
});

test('a cancelled job order no longer appears on the cashier dashboard', function () {
    seedCancellationFee(500.0);
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $this->actingAs($cashier)->post(route('cashier.job-orders.cancel', $jobOrder));

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->component('cashier/Dashboard')
        ->has('jobOrders', 0)
        ->where('cancellationFeeAmount', 500)
    );
});
