<?php

use App\Enums\PaymentStatus;
use App\Mail\PaymentRequested;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\QueueEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function paymentLinkJobOrder(array $attributes = []): JobOrder
{
    $customer = Customer::factory()->create(['email' => 'customer@example.test']);

    return JobOrder::factory()
        ->readyForProduction()
        ->for(QueueEntry::factory()->for($customer))
        ->create($attributes);
}

function paymentLinkPricing(): array
{
    return [
        'pricing_entry_id' => PricingEntry::factory()->create(['base_price' => 1000])->id,
        'line_amount' => 1000,
        'rush_fee_applied' => false,
    ];
}

test('an unpriced order is priced and the customer is emailed a payment link without any payment being recorded', function () {
    Mail::fake();
    $cashier = User::factory()->cashier()->create();
    $jobOrder = paymentLinkJobOrder();
    $statusBefore = $jobOrder->fresh()->payment_status;

    $response = $this->actingAs($cashier)
        ->post(route('cashier.job-orders.payment-link.store', $jobOrder), paymentLinkPricing());

    $response->assertRedirect(route('cashier.dashboard'));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Price saved. Payment link emailed to customer@example.test.']);

    $jobOrder->refresh();
    expect((float) $jobOrder->total_amount)->toBe(1000.0);
    expect((float) $jobOrder->base_price_snapshot)->toBe(1000.0);
    expect(Transaction::count())->toBe(0);
    expect($jobOrder->payment_status)->toBe($statusBefore);

    Mail::assertSent(PaymentRequested::class, fn (PaymentRequested $mail) => $mail->hasTo('customer@example.test')
        && $mail->jobOrder->is($jobOrder));
});

test('a price-locked partially paid order is only emailed and never repriced', function () {
    Mail::fake();
    $cashier = User::factory()->cashier()->create();
    $jobOrder = paymentLinkJobOrder(['payment_status' => PaymentStatus::PartiallyPaid, 'total_amount' => 1000]);
    Transaction::factory()->create(['job_order_id' => $jobOrder->id, 'amount' => 400]);

    $this->actingAs($cashier)
        ->post(route('cashier.job-orders.payment-link.store', $jobOrder), ['line_amount' => 99999])
        ->assertRedirect(route('cashier.dashboard'));

    expect((float) $jobOrder->fresh()->total_amount)->toBe(1000.0);
    Mail::assertSent(PaymentRequested::class, 1);
});

test('fully paid and cancelled orders are refused', function (array $attributes) {
    Mail::fake();
    $cashier = User::factory()->cashier()->create();
    $jobOrder = paymentLinkJobOrder($attributes);

    $this->actingAs($cashier)
        ->post(route('cashier.job-orders.payment-link.store', $jobOrder), paymentLinkPricing())
        ->assertStatus(422);

    Mail::assertNothingSent();
})->with([
    'fully paid' => [['payment_status' => PaymentStatus::Paid, 'total_amount' => 1000]],
    'cancelled' => [['cancelled_at' => '2026-09-10 00:00:00']],
]);

test('orders the tracking page offers no online payment on are refused', function (PaymentStatus $status) {
    Mail::fake();
    $jobOrder = paymentLinkJobOrder(['payment_status' => $status, 'total_amount' => 1000]);

    $this->actingAs(User::factory()->cashier()->create())
        ->post(route('cashier.job-orders.payment-link.store', $jobOrder))
        ->assertStatus(422);

    expect($jobOrder->fresh()->onlinePaymentState())->not->toBe('due');
    Mail::assertNothingSent();
})->with([
    'on credit' => [PaymentStatus::OnCredit],
    'online checkout already open' => [PaymentStatus::PendingConfirmation],
]);

test('the link for a website order goes to the address it was confirmed from, not the one on file', function () {
    Mail::fake();
    $jobOrder = paymentLinkJobOrder(['payment_status' => PaymentStatus::Unpaid, 'total_amount' => 1000]);
    Transaction::factory()->create(['job_order_id' => $jobOrder->id, 'amount' => 400]);
    $jobOrder->queueEntry->update(['contact_email' => 'visitor@example.test']);

    $this->actingAs(User::factory()->cashier()->create())
        ->post(route('cashier.job-orders.payment-link.store', $jobOrder))
        ->assertRedirect(route('cashier.dashboard'));

    Mail::assertSent(PaymentRequested::class, fn (PaymentRequested $mail) => $mail->hasTo('visitor@example.test')
        && ! $mail->hasTo('customer@example.test'));
});

test('invalid pricing input is rejected while pricing is editable', function () {
    Mail::fake();
    $cashier = User::factory()->cashier()->create();
    $jobOrder = paymentLinkJobOrder();

    $this->actingAs($cashier)
        ->post(route('cashier.job-orders.payment-link.store', $jobOrder), ['line_amount' => -5])
        ->assertSessionHasErrors(['pricing_entry_id', 'line_amount', 'rush_fee_applied']);

    expect($jobOrder->fresh()->total_amount)->toBeNull();
    Mail::assertNothingSent();
});

test('a mail failure still saves the price and tells the cashier the email did not go out', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP down'));
    $cashier = User::factory()->cashier()->create();
    $jobOrder = paymentLinkJobOrder();

    $response = $this->actingAs($cashier)
        ->post(route('cashier.job-orders.payment-link.store', $jobOrder), paymentLinkPricing());

    $response->assertRedirect(route('cashier.dashboard'));
    $response->assertInertiaFlash('toast', [
        'type' => 'error',
        'message' => "The price was saved, but the email could not be sent. Check the customer's email address.",
    ]);
    expect((float) $jobOrder->fresh()->total_amount)->toBe(1000.0);
});

test('only the cashier role can send a payment link', function (string $factoryState) {
    Mail::fake();
    $user = User::factory()->{$factoryState}()->create();
    $jobOrder = paymentLinkJobOrder();

    $this->actingAs($user)
        ->post(route('cashier.job-orders.payment-link.store', $jobOrder), paymentLinkPricing())
        ->assertForbidden();

    Mail::assertNothingSent();
})->with(['admin', 'artist', 'accountingStaff', 'productionStaff']);
