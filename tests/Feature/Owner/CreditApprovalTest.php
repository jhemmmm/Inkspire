<?php

use App\Enums\AccountsReceivableStatus;
use App\Enums\PaymentStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\PricingEntry;
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

test('approving an already-resolved credit request is rejected and does not re-approve it (CR-05)', function () {
    $owner = User::factory()->owner()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::OnCredit->value]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create([
        'balance' => 1000,
        'status' => AccountsReceivableStatus::Active,
        'approved_by' => $owner->id,
        'approved_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($owner)->patch(route('owner.credit-requests.approve', $accountsReceivable));

    $response->assertStatus(422);
    expect($accountsReceivable->fresh()->status)->toBe(AccountsReceivableStatus::Active);
});

test('rejecting an already-approved credit request is rejected and does not flip the job order back (CR-05)', function () {
    $owner = User::factory()->owner()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::OnCredit->value]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create([
        'balance' => 1000,
        'status' => AccountsReceivableStatus::Active,
        'approved_by' => $owner->id,
        'approved_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($owner)->patch(route('owner.credit-requests.reject', $accountsReceivable));

    $response->assertStatus(422);
    expect($accountsReceivable->fresh()->status)->toBe(AccountsReceivableStatus::Active);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::OnCredit);
});

test('approving an already-rejected credit request is rejected and does not reverse the prior decision (CR-05)', function () {
    $owner = User::factory()->owner()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::CreditRejected->value]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create([
        'balance' => 1000,
        'status' => AccountsReceivableStatus::Rejected,
        'approved_by' => $owner->id,
        'approved_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($owner)->patch(route('owner.credit-requests.approve', $accountsReceivable));

    $response->assertStatus(422);
    expect($accountsReceivable->fresh()->status)->toBe(AccountsReceivableStatus::Rejected);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::CreditRejected);
});

test('a cashier can request OnCredit for an eligible job order, posting the remaining outstanding balance', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.credit-request.store', $jobOrder));

    $response->assertRedirect(route('cashier.dashboard'));

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

test('a cancelled job order cannot have OnCredit requested against it (CR-03)', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'cancelled_at' => now()]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.credit-request.store', $jobOrder));

    $response->assertStatus(422);
    expect(AccountsReceivable::count())->toBe(0);
});

test('requesting OnCredit as the very first pricing action validates and snapshots pricing input (CR-01)', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.credit-request.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => false,
        'discount_type' => 'flat',
        'discount_value' => 100,
    ]);

    $response->assertRedirect(route('cashier.dashboard'));

    $freshJobOrder = $jobOrder->fresh();
    expect((float) $freshJobOrder->total_amount)->toBe(900.0);
    expect($freshJobOrder->payment_status)->toBe(PaymentStatus::CreditPendingApproval);

    $accountsReceivable = AccountsReceivable::where('job_order_id', $jobOrder->id)->first();
    expect((float) $accountsReceivable->balance)->toBe(900.0);
});

test('requesting OnCredit as the very first pricing action rejects a discount exceeding the configured cap (CR-01)', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.credit-request.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => false,
        'discount_type' => 'percentage',
        'discount_value' => 50,
    ]);

    $response->assertSessionHasErrors('discount_value');
    expect($jobOrder->fresh()->total_amount)->toBeNull();
    expect(AccountsReceivable::count())->toBe(0);
});

test('requesting OnCredit as the very first pricing action rejects a missing line amount instead of silently pricing at zero (CR-01)', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.credit-request.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'rush_fee_applied' => false,
    ]);

    $response->assertSessionHasErrors('line_amount');
    expect($jobOrder->fresh()->total_amount)->toBeNull();
    expect(AccountsReceivable::count())->toBe(0);
});
