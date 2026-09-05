<?php

use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\Transaction;
use App\Models\User;
use Luigel\Paymongo\Facades\Paymongo;
use Luigel\Paymongo\Models\PaymentIntent;
use Luigel\Paymongo\Models\PaymentMethod as PaymongoPaymentMethod;

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

test('a gcash payment creates a pending confirmation transaction with a paymongo intent id and shows a QR redirect URL', function () {
    // luigel/laravel-paymongo's Request trait instantiates `new Client()`
    // (raw Guzzle) directly rather than going through Laravel's HTTP
    // client, so Http::fake() cannot intercept it — the Paymongo facade
    // itself is the mockable seam (verified by reading the installed
    // package's source, per this plan's SUMMARY).
    $fakeIntent = (new PaymentIntent)->setData([
        'id' => 'pi_test123',
        'type' => 'payment_intent',
        'attributes' => ['amount' => 100000, 'status' => 'awaiting_payment_method'],
    ]);
    $fakePaymentMethod = (new PaymongoPaymentMethod)->setData([
        'id' => 'pm_test456',
        'type' => 'payment_method',
        'attributes' => ['type' => 'gcash'],
    ]);
    $fakeAttached = (new PaymentIntent)->setData([
        'id' => 'pi_test123',
        'type' => 'payment_intent',
        'attributes' => [
            'status' => 'awaiting_next_action',
            'next_action' => [
                'type' => 'redirect',
                'redirect' => ['url' => 'https://paymongo.test/checkout/pi_test123'],
            ],
        ],
    ]);

    Paymongo::shouldReceive('paymentIntent')->twice()->andReturnSelf();
    Paymongo::shouldReceive('paymentMethod')->once()->andReturnSelf();
    Paymongo::shouldReceive('create')->twice()->andReturn($fakeIntent, $fakePaymentMethod);
    Paymongo::shouldReceive('attach')->once()->andReturn($fakeAttached);

    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => false,
        'payment_method' => 'gcash',
        'payment_type' => 'full',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('redirectUrl', 'https://paymongo.test/checkout/pi_test123');

    $jobOrder->refresh();
    expect($jobOrder->payment_status)->toBe(PaymentStatus::PendingConfirmation);
    expect($jobOrder->transactions()->count())->toBe(1);

    $transaction = $jobOrder->transactions()->first();
    expect($transaction->status)->toBe(TransactionStatus::PendingConfirmation);
    expect($transaction->paymongo_payment_intent_id)->toBe('pi_test123');

    $follow = $this->actingAs($cashier)->get(route('cashier.job-orders.payment.edit', $jobOrder));
    $follow->assertInertia(fn ($page) => $page->where('paymongoRedirectUrl', 'https://paymongo.test/checkout/pi_test123'));
});

test('a cancelled job order cannot be paid (CR-03)', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 500]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['cancelled_at' => now()]);

    $editResponse = $this->actingAs($cashier)->get(route('cashier.job-orders.payment.edit', $jobOrder));
    $editResponse->assertStatus(422);

    $storeResponse = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 500,
        'rush_fee_applied' => false,
        'payment_method' => 'cash',
        'payment_type' => 'full',
        'amount_tendered' => 500,
    ]);

    $storeResponse->assertStatus(422);
    expect(Transaction::count())->toBe(0);
    expect($jobOrder->fresh()->total_amount)->toBeNull();
});

test('a maya payment failing to create a paymongo intent flashes an error and creates no transaction', function () {
    Paymongo::shouldReceive('paymentIntent')->once()->andReturnSelf();
    Paymongo::shouldReceive('create')->once()->andThrow(new Exception('PayMongo unavailable'));

    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 500]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 500,
        'rush_fee_applied' => false,
        'payment_method' => 'maya',
        'payment_type' => 'full',
    ]);

    $response->assertRedirect();
    expect(Transaction::count())->toBe(0);
    expect($jobOrder->fresh()->payment_status)->not->toBe(PaymentStatus::PendingConfirmation);
    // CR-02: pricing must never be persisted when the PayMongo call fails —
    // otherwise the job order is left silently priced with no transaction
    // to show for it, and a retry would skip pricing validation entirely.
    expect($jobOrder->fresh()->total_amount)->toBeNull();
});
