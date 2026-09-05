<?php

use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\JobOrder;
use App\Models\Transaction;
use App\Models\User;
use Luigel\Paymongo\Facades\Paymongo;
use Luigel\Paymongo\Models\PaymentIntent;

function reconciliationFakeIntent(string $status): PaymentIntent
{
    return (new PaymentIntent)->setData([
        'id' => 'pi_test_reconcile',
        'type' => 'payment_intent',
        'attributes' => ['status' => $status],
    ]);
}

test('accounting staff reconciling a succeeded payment confirms it and flips the job order to paid', function () {
    Paymongo::shouldReceive('paymentIntent')->once()->andReturnSelf();
    Paymongo::shouldReceive('find')->once()->with('pi_test_reconcile')->andReturn(reconciliationFakeIntent('succeeded'));

    $accountingStaff = User::factory()->accountingStaff()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000, 'payment_status' => PaymentStatus::PendingConfirmation])->save();
    $transaction = Transaction::factory()->pendingConfirmation()->create([
        'job_order_id' => $jobOrder->id,
        'amount' => 1000,
        'paymongo_payment_intent_id' => 'pi_test_reconcile',
    ]);

    $response = $this->actingAs($accountingStaff)
        ->post(route('accounting-staff.job-orders.reconcile', $jobOrder));

    $response->assertRedirect();
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Payment confirmed.')]);
    expect($transaction->fresh()->status)->toBe(TransactionStatus::Completed);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('accounting staff reconciling a succeeded down payment flips the job order to partially paid', function () {
    Paymongo::shouldReceive('paymentIntent')->once()->andReturnSelf();
    Paymongo::shouldReceive('find')->once()->andReturn(reconciliationFakeIntent('succeeded'));

    $accountingStaff = User::factory()->accountingStaff()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000, 'payment_status' => PaymentStatus::PendingConfirmation])->save();
    Transaction::factory()->pendingConfirmation()->create([
        'job_order_id' => $jobOrder->id,
        'amount' => 400,
        'paymongo_payment_intent_id' => 'pi_test_reconcile',
    ]);

    $response = $this->actingAs($accountingStaff)
        ->post(route('accounting-staff.job-orders.reconcile', $jobOrder));

    $response->assertRedirect();
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::PartiallyPaid);
});

test('accounting staff reconciling a still-pending payment makes no state change and flashes the not-received-yet toast', function () {
    Paymongo::shouldReceive('paymentIntent')->once()->andReturnSelf();
    Paymongo::shouldReceive('find')->once()->andReturn(reconciliationFakeIntent('awaiting_next_action'));

    $accountingStaff = User::factory()->accountingStaff()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000, 'payment_status' => PaymentStatus::PendingConfirmation])->save();
    $transaction = Transaction::factory()->pendingConfirmation()->create([
        'job_order_id' => $jobOrder->id,
        'amount' => 1000,
        'paymongo_payment_intent_id' => 'pi_test_reconcile',
    ]);

    $response = $this->actingAs($accountingStaff)
        ->post(route('accounting-staff.job-orders.reconcile', $jobOrder));

    $response->assertRedirect();
    $response->assertInertiaFlash('toast', [
        'type' => 'error',
        'message' => __('Payment not received yet. Try again in a moment, or ask the customer to confirm they completed the payment.'),
    ]);
    expect($transaction->fresh()->status)->toBe(TransactionStatus::PendingConfirmation);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::PendingConfirmation);
});

test('accounting staff reconciling a failed payment falls the job order back to unpaid and flashes the D-13 toast', function () {
    Paymongo::shouldReceive('paymentIntent')->once()->andReturnSelf();
    Paymongo::shouldReceive('find')->once()->andReturn(reconciliationFakeIntent('cancelled'));

    $accountingStaff = User::factory()->accountingStaff()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000, 'payment_status' => PaymentStatus::PendingConfirmation])->save();
    $transaction = Transaction::factory()->pendingConfirmation()->create([
        'job_order_id' => $jobOrder->id,
        'amount' => 1000,
        'paymongo_payment_intent_id' => 'pi_test_reconcile',
    ]);

    $response = $this->actingAs($accountingStaff)
        ->post(route('accounting-staff.job-orders.reconcile', $jobOrder));

    $response->assertRedirect();
    $response->assertInertiaFlash('toast', [
        'type' => 'error',
        'message' => __('This payment failed or expired. Choose a different payment method to continue.'),
    ]);
    expect($transaction->fresh()->status)->toBe(TransactionStatus::Failed);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Unpaid);
});

test('cashier can reconcile from their own route using the identical controller', function () {
    Paymongo::shouldReceive('paymentIntent')->once()->andReturnSelf();
    Paymongo::shouldReceive('find')->once()->andReturn(reconciliationFakeIntent('succeeded'));

    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000, 'payment_status' => PaymentStatus::PendingConfirmation])->save();
    $transaction = Transaction::factory()->pendingConfirmation()->create([
        'job_order_id' => $jobOrder->id,
        'amount' => 1000,
        'paymongo_payment_intent_id' => 'pi_test_reconcile',
    ]);

    $response = $this->actingAs($cashier)
        ->post(route('cashier.job-orders.reconcile', $jobOrder));

    $response->assertRedirect();
    expect($transaction->fresh()->status)->toBe(TransactionStatus::Completed);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('reconciling a job order that is not awaiting payment confirmation is rejected', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000, 'payment_status' => PaymentStatus::Unpaid])->save();

    $response = $this->actingAs($accountingStaff)
        ->post(route('accounting-staff.job-orders.reconcile', $jobOrder));

    $response->assertStatus(422);
});

test('a cancelled job order no longer appears in the reconciliation queue and cannot be manually reconciled (CR-03)', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000, 'payment_status' => PaymentStatus::PendingConfirmation, 'cancelled_at' => now()])->save();
    Transaction::factory()->pendingConfirmation()->create([
        'job_order_id' => $jobOrder->id,
        'amount' => 1000,
        'paymongo_payment_intent_id' => 'pi_test_reconcile',
    ]);

    $indexResponse = $this->actingAs($accountingStaff)->get(route('accounting-staff.dashboard'));
    $indexResponse->assertInertia(fn ($page) => $page->has('jobOrders', 0));

    $storeResponse = $this->actingAs($accountingStaff)
        ->post(route('accounting-staff.job-orders.reconcile', $jobOrder));

    $storeResponse->assertStatus(422);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::PendingConfirmation);
});

test('a frontline staff user cannot reach the accounting staff reconcile route', function () {
    $frontlineStaff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();
    $jobOrder->forceFill(['total_amount' => 1000, 'payment_status' => PaymentStatus::PendingConfirmation])->save();

    $response = $this->actingAs($frontlineStaff)
        ->post(route('accounting-staff.job-orders.reconcile', $jobOrder));

    $response->assertStatus(403);
});
