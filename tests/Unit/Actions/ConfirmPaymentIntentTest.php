<?php

use App\Actions\POS\ConfirmPaymentIntent;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\JobOrder;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('confirming a successful pending payment completes the transaction and marks the job order paid', function () {
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000])->save();
    $transaction = Transaction::factory()->pendingConfirmation()->create([
        'job_order_id' => $jobOrder->id,
        'amount' => 1000,
    ]);

    $confirmed = (new ConfirmPaymentIntent)($transaction, true);

    expect($confirmed->status)->toBe(TransactionStatus::Completed);
    expect($confirmed->confirmed_at)->not->toBeNull();
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('confirming a successful pending down payment marks the job order partially paid', function () {
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000])->save();
    $transaction = Transaction::factory()->pendingConfirmation()->create([
        'job_order_id' => $jobOrder->id,
        'amount' => 400,
    ]);

    (new ConfirmPaymentIntent)($transaction, true);

    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::PartiallyPaid);
});

test('confirming a failed pending payment marks the transaction failed and leaves the job order pending', function () {
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000, 'payment_status' => PaymentStatus::PendingConfirmation])->save();
    $transaction = Transaction::factory()->pendingConfirmation()->create([
        'job_order_id' => $jobOrder->id,
        'amount' => 1000,
    ]);

    $confirmed = (new ConfirmPaymentIntent)($transaction, false);

    expect($confirmed->status)->toBe(TransactionStatus::Failed);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::PendingConfirmation);
});

test('calling ConfirmPaymentIntent twice on the same transaction only applies the state transition once', function () {
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000])->save();
    $transaction = Transaction::factory()->pendingConfirmation()->create([
        'job_order_id' => $jobOrder->id,
        'amount' => 1000,
    ]);

    $first = (new ConfirmPaymentIntent)($transaction, true);
    $firstConfirmedAt = $first->confirmed_at;

    // A duplicate/replayed webhook delivery (or a reconciliation click
    // racing the webhook) for the same transaction — the guard inside
    // ConfirmPaymentIntent must make the second call a structural no-op.
    $second = (new ConfirmPaymentIntent)($transaction->fresh(), false);

    expect($second->status)->toBe(TransactionStatus::Completed);
    expect($second->confirmed_at->equalTo($firstConfirmedAt))->toBeTrue();
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});
