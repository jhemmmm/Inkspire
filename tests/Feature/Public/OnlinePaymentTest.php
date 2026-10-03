<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\JobOrder;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Luigel\Paymongo\Facades\Paymongo;
use Luigel\Paymongo\Models\PaymentIntent;
use Luigel\Paymongo\Models\PaymentMethod as PaymongoPaymentMethod;

function onlinePayJobOrder(array $attributes = []): JobOrder
{
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(array_merge(['total_amount' => 1000, 'payment_status' => PaymentStatus::Unpaid], $attributes))->save();

    return $jobOrder->fresh();
}

function onlinePayIntent(string $status, ?string $url = null): PaymentIntent
{
    $attributes = ['status' => $status];

    if ($url !== null) {
        $attributes['next_action'] = ['type' => 'redirect', 'redirect' => ['url' => $url]];
    }

    return (new PaymentIntent)->setData(['id' => 'pi_online1', 'type' => 'payment_intent', 'attributes' => $attributes]);
}

function onlinePayPaymentMethod(): PaymongoPaymentMethod
{
    return (new PaymongoPaymentMethod)->setData(['id' => 'pm_online1', 'type' => 'payment_method', 'attributes' => ['type' => 'gcash']]);
}

function onlinePayPost(JobOrder $jobOrder, string $method = 'gcash')
{
    return test()
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('public.tracking.pay', ['token' => $jobOrder->tracking_token]), ['payment_method' => $method]);
}

function onlinePayPending(JobOrder $jobOrder, float $amount = 1000): Transaction
{
    $jobOrder->forceFill(['payment_status' => PaymentStatus::PendingConfirmation])->save();

    return Transaction::factory()->pendingConfirmation()->create([
        'job_order_id' => $jobOrder->id,
        'amount' => $amount,
        'payment_method' => PaymentMethod::Gcash,
        'paymongo_payment_intent_id' => 'pi_online1',
    ]);
}

test('an unpriced order shows no payment and refuses to start one', function () {
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    Paymongo::shouldReceive('paymentIntent')->never();

    $this->get(route('public.tracking.token', ['token' => $jobOrder->tracking_token]))
        ->assertInertia(fn (Assert $page) => $page->where('result.payment', null));

    onlinePayPost($jobOrder)->assertRedirect(route('public.tracking.token', ['token' => $jobOrder->tracking_token]));

    expect(Transaction::count())->toBe(0);
});

test('a priced unpaid order shows the amount due and a post opens one pending checkout for the full balance', function () {
    $jobOrder = onlinePayJobOrder();

    $this->get(route('public.tracking.token', ['token' => $jobOrder->tracking_token]))
        ->assertInertia(fn (Assert $page) => $page->where('result.payment', ['amountDue' => 1000, 'state' => 'due']));

    Paymongo::shouldReceive('paymentIntent')->twice()->andReturnSelf();
    Paymongo::shouldReceive('paymentMethod')->once()->andReturnSelf();
    Paymongo::shouldReceive('create')->twice()->andReturn(onlinePayIntent('awaiting_payment_method'), onlinePayPaymentMethod());
    Paymongo::shouldReceive('attach')->once()->andReturn(onlinePayIntent('awaiting_next_action', 'https://paymongo.test/checkout/pi_online1'));

    $response = onlinePayPost($jobOrder);

    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', 'https://paymongo.test/checkout/pi_online1');

    expect(Transaction::count())->toBe(1);
    $transaction = Transaction::first();
    expect($transaction->recorded_by)->toBeNull();
    expect((float) $transaction->amount)->toBe(1000.0);
    expect($transaction->type)->toBe(TransactionType::FullPayment);
    expect($transaction->status)->toBe(TransactionStatus::PendingConfirmation);
    expect($transaction->paymongo_payment_intent_id)->toBe('pi_online1');
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::PendingConfirmation);
});

test('a partially paid order is charged only the remaining balance', function () {
    $jobOrder = onlinePayJobOrder(['payment_status' => PaymentStatus::PartiallyPaid]);
    Transaction::factory()->create(['job_order_id' => $jobOrder->id, 'amount' => 400, 'status' => TransactionStatus::Completed]);

    Paymongo::shouldReceive('paymentIntent')->twice()->andReturnSelf();
    Paymongo::shouldReceive('paymentMethod')->once()->andReturnSelf();
    Paymongo::shouldReceive('create')->twice()->withArgs(fn (array $payload) => ! isset($payload['amount']) || $payload['amount'] === 600.0)
        ->andReturn(onlinePayIntent('awaiting_payment_method'), onlinePayPaymentMethod());
    Paymongo::shouldReceive('attach')->once()->andReturn(onlinePayIntent('awaiting_next_action', 'https://paymongo.test/c'));

    onlinePayPost($jobOrder, 'maya')->assertStatus(409);

    $pending = Transaction::where('status', TransactionStatus::PendingConfirmation)->sole();
    expect((float) $pending->amount)->toBe(600.0);
    expect($pending->payment_method)->toBe(PaymentMethod::Maya);
});

test('retrying a pending checkout reuses the existing intent and creates no second transaction', function () {
    $jobOrder = onlinePayJobOrder();
    onlinePayPending($jobOrder);

    Paymongo::shouldReceive('paymentIntent')->once()->andReturnSelf();
    Paymongo::shouldReceive('find')->once()->with('pi_online1')->andReturn(onlinePayIntent('awaiting_next_action', 'https://paymongo.test/existing'));
    Paymongo::shouldReceive('create')->never();

    $response = onlinePayPost($jobOrder);

    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', 'https://paymongo.test/existing');
    expect(Transaction::count())->toBe(1);
});

test('an intent that has not been given a method yet is re-attached with the wallet first chosen', function () {
    $jobOrder = onlinePayJobOrder();
    $transaction = onlinePayPending($jobOrder);

    Paymongo::shouldReceive('paymentIntent')->twice()->andReturnSelf();
    Paymongo::shouldReceive('find')->once()->andReturn(onlinePayIntent('awaiting_payment_method'));
    Paymongo::shouldReceive('paymentMethod')->once()->andReturnSelf();
    Paymongo::shouldReceive('create')->once()->with(['type' => 'gcash'])->andReturn(onlinePayPaymentMethod());
    Paymongo::shouldReceive('attach')->once()->andReturn(onlinePayIntent('awaiting_next_action', 'https://paymongo.test/fresh'));

    // The pending page has one button; whatever wallet it posts is not used.
    onlinePayPost($jobOrder, 'maya')->assertHeader('X-Inertia-Location', 'https://paymongo.test/fresh');

    expect(Transaction::count())->toBe(1);
    expect($transaction->fresh()->payment_method)->toBe(PaymentMethod::Gcash);
});

test('a payment PayMongo is still processing is left alone', function () {
    $jobOrder = onlinePayJobOrder();
    $transaction = onlinePayPending($jobOrder);

    Paymongo::shouldReceive('paymentIntent')->once()->andReturnSelf();
    Paymongo::shouldReceive('find')->once()->andReturn(onlinePayIntent('processing'));
    Paymongo::shouldReceive('create')->never();
    Paymongo::shouldReceive('attach')->never();

    onlinePayPost($jobOrder)->assertRedirect(route('public.tracking.token', ['token' => $jobOrder->tracking_token]));

    expect($transaction->fresh()->status)->toBe(TransactionStatus::PendingConfirmation);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::PendingConfirmation);
});

test('a checkout PayMongo cancelled is closed so the customer can choose a wallet again', function () {
    $jobOrder = onlinePayJobOrder();
    $transaction = onlinePayPending($jobOrder);

    Paymongo::shouldReceive('paymentIntent')->once()->andReturnSelf();
    Paymongo::shouldReceive('find')->once()->andReturn(onlinePayIntent('cancelled'));
    Paymongo::shouldReceive('create')->never();

    onlinePayPost($jobOrder)->assertRedirect(route('public.tracking.token', ['token' => $jobOrder->tracking_token]));

    expect($transaction->fresh()->status)->toBe(TransactionStatus::Failed);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Unpaid);
    expect($jobOrder->fresh()->onlinePaymentState())->toBe('due');
});

test('a second submit while a checkout is being opened does nothing', function () {
    $jobOrder = onlinePayJobOrder();
    Cache::lock('online-payment:'.$jobOrder->id, 30)->get();
    Paymongo::shouldReceive('paymentIntent')->never();

    onlinePayPost($jobOrder)->assertRedirect(route('public.tracking.token', ['token' => $jobOrder->tracking_token]));

    expect(Transaction::count())->toBe(0);
});

test('an order paid at the counter while PayMongo was being called gets no pending transaction and no error', function () {
    $jobOrder = onlinePayJobOrder();

    Paymongo::shouldReceive('paymentIntent')->twice()->andReturnSelf();
    Paymongo::shouldReceive('paymentMethod')->once()->andReturnSelf();
    Paymongo::shouldReceive('create')->twice()->andReturn(onlinePayIntent('awaiting_payment_method'), onlinePayPaymentMethod());
    Paymongo::shouldReceive('attach')->once()->andReturnUsing(function () use ($jobOrder): PaymentIntent {
        $jobOrder->forceFill(['payment_status' => PaymentStatus::Paid])->save();

        return onlinePayIntent('awaiting_next_action', 'https://paymongo.test/too-late');
    });

    onlinePayPost($jobOrder)
        ->assertRedirect(route('public.tracking.token', ['token' => $jobOrder->tracking_token]))
        ->assertSessionHasNoErrors();

    expect(Transaction::count())->toBe(0);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('a pending checkout that PayMongo reports as succeeded is confirmed and the order becomes paid', function () {
    $jobOrder = onlinePayJobOrder();
    $transaction = onlinePayPending($jobOrder);

    Paymongo::shouldReceive('paymentIntent')->once()->andReturnSelf();
    Paymongo::shouldReceive('find')->once()->andReturn(onlinePayIntent('succeeded'));

    onlinePayPost($jobOrder)->assertRedirect(route('public.tracking.token', ['token' => $jobOrder->tracking_token]));

    expect($transaction->fresh()->status)->toBe(TransactionStatus::Completed);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('a PayMongo failure shows a generic error and writes nothing', function () {
    $jobOrder = onlinePayJobOrder();
    Log::spy();

    Paymongo::shouldReceive('paymentIntent')->once()->andReturnSelf();
    Paymongo::shouldReceive('create')->once()->andThrow(new Exception('PayMongo secret sk_live_abc exploded'));

    $response = onlinePayPost($jobOrder);

    $response->assertRedirect(route('public.tracking.token', ['token' => $jobOrder->tracking_token]));
    $response->assertSessionHasErrors(['payment' => "We couldn't start the payment. Please try again, or pay at the shop."]);
    expect(Transaction::count())->toBe(0);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Unpaid);
});

test('the number lookup page never carries payment data', function () {
    $jobOrder = onlinePayJobOrder();

    $response = $this->get(route('public.tracking.show', ['number' => $jobOrder->number]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->missing('result.payment'));
    $response->assertDontSee('amountDue', false);
});

test('an unsupported wallet is rejected', function (string $method) {
    $jobOrder = onlinePayJobOrder();
    Paymongo::shouldReceive('paymentIntent')->never();

    $this->post(route('public.tracking.pay', ['token' => $jobOrder->tracking_token]), ['payment_method' => $method])
        ->assertSessionHasErrors('payment_method');

    expect(Transaction::count())->toBe(0);
})->with(['cash', 'bank_transfer', '']);

test('an unknown token is a 404', function () {
    Paymongo::shouldReceive('paymentIntent')->never();

    $this->post(route('public.tracking.pay', ['token' => 'thisisnotarealtrackingtoken00000']), ['payment_method' => 'gcash'])
        ->assertNotFound();
});

test('orders that are cancelled, on credit or written off offer no payment and start none', function (array $attributes) {
    $jobOrder = onlinePayJobOrder($attributes);
    Paymongo::shouldReceive('paymentIntent')->never();

    $this->get(route('public.tracking.token', ['token' => $jobOrder->tracking_token]))
        ->assertInertia(fn (Assert $page) => $page->where('result.payment', null));

    onlinePayPost($jobOrder)->assertRedirect();
    expect(Transaction::count())->toBe(0);
})->with([
    'cancelled' => [['cancelled_at' => '2026-09-10 00:00:00']],
    'on credit' => [['payment_status' => PaymentStatus::OnCredit]],
    'written off' => [['payment_status' => PaymentStatus::WrittenOff]],
]);
