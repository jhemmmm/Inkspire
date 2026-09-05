<?php

use App\Enums\AccountsReceivableStatus;
use App\Enums\PaymentStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\User;

test('an admin can view the OnCredit requests queue but is forbidden from approving a request', function () {
    $admin = User::factory()->admin()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::CreditPendingApproval->value]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create();

    $indexResponse = $this->actingAs($admin)->get(route('owner.credit-requests.index'));
    $indexResponse->assertOk();

    $approveResponse = $this->actingAs($admin)->patch(route('owner.credit-requests.approve', $accountsReceivable));
    $approveResponse->assertForbidden();
});

test('an owner approving an OnCredit request flips the receivable active and the job order on_credit', function () {
    $owner = User::factory()->owner()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::CreditPendingApproval->value]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create(['balance' => 1000]);

    $response = $this->actingAs($owner)->patch(route('owner.credit-requests.approve', $accountsReceivable));

    $response->assertRedirect();
    expect($accountsReceivable->fresh()->status)->toBe(AccountsReceivableStatus::Active);
    expect($accountsReceivable->fresh()->approved_by)->toBe($owner->id);
    expect($accountsReceivable->fresh()->approved_at)->not->toBeNull();
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::OnCredit);
});

test('an owner rejecting an OnCredit request flips both to their rejected states with no other job order field touched', function () {
    $owner = User::factory()->owner()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create([
        'total_amount' => 1000,
        'payment_status' => PaymentStatus::CreditPendingApproval->value,
        'description' => 'Tarpaulin, 3x5ft',
    ]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create(['balance' => 1000]);

    $response = $this->actingAs($owner)->patch(route('owner.credit-requests.reject', $accountsReceivable));

    $response->assertRedirect();
    expect($accountsReceivable->fresh()->status)->toBe(AccountsReceivableStatus::Rejected);
    expect($accountsReceivable->fresh()->approved_by)->toBe($owner->id);
    expect($accountsReceivable->fresh()->approved_at)->not->toBeNull();

    $freshJobOrder = $jobOrder->fresh();
    expect($freshJobOrder->payment_status)->toBe(PaymentStatus::CreditRejected);
    expect($freshJobOrder->total_amount)->toEqual(1000);
    expect($freshJobOrder->description)->toBe('Tarpaulin, 3x5ft');
});

test('a cashier can request OnCredit for an eligible job order, posting the remaining outstanding balance', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.credit-request.store', $jobOrder));

    $response->assertRedirect();

    $freshJobOrder = $jobOrder->fresh();
    expect($freshJobOrder->payment_status)->toBe(PaymentStatus::CreditPendingApproval);

    $accountsReceivable = AccountsReceivable::where('job_order_id', $jobOrder->id)->first();
    expect($accountsReceivable)->not->toBeNull();
    expect($accountsReceivable->status)->toBe(AccountsReceivableStatus::PendingApproval);
    expect((float) $accountsReceivable->balance)->toBe(1000.0);
    expect($accountsReceivable->requested_by)->toBe($cashier->id);
});

test('a job order that already has a pending OnCredit request cannot be requested again', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create([
        'total_amount' => 1000,
        'payment_status' => PaymentStatus::CreditPendingApproval->value,
    ]);
    AccountsReceivable::factory()->for($jobOrder)->create(['balance' => 1000]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.credit-request.store', $jobOrder));

    $response->assertStatus(422);
});
