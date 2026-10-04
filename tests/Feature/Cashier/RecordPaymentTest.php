<?php

use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\SystemConfiguration;
use App\Models\Transaction;
use App\Models\User;
use Luigel\Paymongo\Facades\Paymongo;

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
        'down_payment_amount' => 400,
    ]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'payment_method' => 'cash',
        'payment_type' => 'down',
        'down_payment_amount' => 900,
    ]);

    $response->assertSessionHasErrors('down_payment_amount');
});

test('a down payment cannot exceed the computed total on the very first pricing/payment visit (WR-01)', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => false,
        'payment_method' => 'cash',
        'payment_type' => 'down',
        'down_payment_amount' => 5000,
    ]);

    $response->assertSessionHasErrors('down_payment_amount');
    expect($jobOrder->fresh()->total_amount)->toBeNull();
    expect(Transaction::count())->toBe(0);
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
    ]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'payment_method' => 'cash',
        'payment_type' => 'full',
    ]);

    $response->assertStatus(422);
});

test('a gcash or maya payment at the counter is recorded against its reference number without calling PayMongo', function (string $method) {
    // PayMongo belongs to the customer's own online checkout. At the counter
    // the customer has already paid the shop's wallet, so there is nothing
    // to ask PayMongo for.
    Paymongo::shouldReceive('paymentIntent')->never();
    Paymongo::shouldReceive('paymentMethod')->never();

    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => false,
        'payment_method' => $method,
        'payment_type' => 'full',
        'reference_number' => '1029384756123',
    ]);

    $response->assertRedirect(route('cashier.job-orders.receipt.show', $jobOrder));

    $transaction = $jobOrder->transactions()->sole();

    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Paid);
    expect($transaction->status)->toBe(TransactionStatus::Completed);
    expect($transaction->payment_method->value)->toBe($method);
    expect($transaction->reference_number)->toBe('1029384756123');
    expect($transaction->paymongo_payment_intent_id)->toBeNull();
    expect((float) $transaction->amount)->toBe(1000.0);
    expect($transaction->recorded_by)->toBe($cashier->id);
})->with(['gcash', 'maya']);

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
    ]);

    $storeResponse->assertStatus(422);
    expect(Transaction::count())->toBe(0);
    expect($jobOrder->fresh()->total_amount)->toBeNull();
});

test('a written-off job order cannot be paid', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['payment_status' => 'written_off', 'total_amount' => 1000]);

    $editResponse = $this->actingAs($cashier)->get(route('cashier.job-orders.payment.edit', $jobOrder));
    $editResponse->assertStatus(422);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'payment_method' => 'cash',
        'payment_type' => 'full',
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => 'This job order has been written off and cannot accept further payments.']);
    expect(Transaction::count())->toBe(0);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::WrittenOff);
});

test('a job order with a pending credit request cannot be paid directly (CR-01)', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['payment_status' => 'credit_pending_approval', 'total_amount' => 1000]);

    $editResponse = $this->actingAs($cashier)->get(route('cashier.job-orders.payment.edit', $jobOrder));
    $editResponse->assertStatus(422);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'payment_method' => 'cash',
        'payment_type' => 'full',
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => 'This job order has an On-Credit request awaiting Admin approval. Resolve it before recording a payment.']);
    expect(Transaction::count())->toBe(0);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::CreditPendingApproval);
});

test('a gcash, maya or bank transfer payment without its reference number is rejected and nothing is recorded', function (string $method) {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 500]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 500,
        'rush_fee_applied' => false,
        'payment_method' => $method,
        'payment_type' => 'full',
    ]);

    $response->assertSessionHasErrors(['reference_number' => 'Enter the reference number for this payment.']);
    expect(Transaction::count())->toBe(0);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Unpaid);
    // A rejected payment must not leave the order silently priced either.
    expect($jobOrder->fresh()->total_amount)->toBeNull();
})->with(['gcash', 'maya', 'bank_transfer']);

test('the rush fee toggle submits as the string 0 or 1, matching the payment form', function (string $submitted, bool $expected) {
    // The Apply Rush Fee switch is a reka-ui SwitchRoot, which renders a real
    // checkbox: unchecked, the browser omits it entirely, so the form pairs it
    // with a hidden "0" that always submits and an explicit value="1" that
    // overrides it when checked. `rush_fee_applied` is required|boolean, so
    // both of those exact strings must be accepted.
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => $submitted,
        'payment_method' => 'cash',
        'payment_type' => 'full',
    ]);

    $response->assertSessionHasNoErrors();
    expect($jobOrder->fresh()->rush_fee_applied)->toBe($expected);
})->with([
    'switch off (hidden field only)' => ['0', false],
    'switch on (explicit value)' => ['1', true],
]);

test('a gcash payment charges edited pricing on a totalled, transaction-less order (bug 2)', function () {
    SystemConfiguration::create([
        'key' => 'rush_fee_percentage',
        'group' => 'business_rules',
        'value' => 10,
        'type' => 'decimal',
        'label' => 'Rush fee (%)',
    ]);

    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create([
        'total_amount' => 1000,
        'base_price_snapshot' => 1000,
        'rush_fee_amount' => 0,
    ]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => true,
        'payment_method' => 'gcash',
        'payment_type' => 'full',
        'reference_number' => '1029384756123',
    ]);

    $response->assertSessionHasNoErrors();

    $jobOrder->refresh();
    expect((float) $jobOrder->rush_fee_amount)->toBe(100.0)
        ->and((float) $jobOrder->total_amount)->toBe(1100.0);

    $transaction = $jobOrder->transactions()->first();
    expect((float) $transaction->amount)->toBe(1100.0);
});

test('a cash payment against an on-credit order leaves its approved total untouched (bug 1)', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create([
        'payment_status' => 'on_credit',
        'total_amount' => 1000,
        'base_price_snapshot' => 1000,
        'rush_fee_amount' => 0,
    ]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => 1,
        'line_amount' => 5000,
        'rush_fee_applied' => true,
        'payment_method' => 'cash',
        'payment_type' => 'full',
    ]);

    $response->assertRedirect();

    $jobOrder->refresh();
    expect((float) $jobOrder->total_amount)->toBe(1000.0);
    expect($jobOrder->payment_status)->toBe(PaymentStatus::Paid);
});

test('omitting rush_fee_applied entirely is still rejected, so a broken form fails loudly', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'payment_method' => 'cash',
        'payment_type' => 'full',
    ]);

    $response->assertSessionHasErrors('rush_fee_applied');
});

test('a counter payment is refused while the customer has an online checkout open', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000, 'payment_status' => PaymentStatus::PendingConfirmation])->save();
    $checkout = Transaction::factory()->pendingConfirmation()->create(['job_order_id' => $jobOrder->id, 'amount' => 1000]);

    $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'payment_method' => 'cash',
        'payment_type' => 'full',
    ])->assertStatus(422);

    expect(Transaction::count())->toBe(1);
    expect($checkout->fresh()->status)->toBe(TransactionStatus::PendingConfirmation);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::PendingConfirmation);
});
