<?php

use App\Enums\AccountsReceivableStatus;
use App\Enums\PaymentStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\SystemConfiguration;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia;

test('a staff role can neither view nor act on the OnCredit requests queue', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::CreditPendingApproval->value]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create();

    $this->actingAs($cashier)->get(route('admin.credit-requests.index'))->assertForbidden();
    $this->actingAs($cashier)->patch(route('admin.credit-requests.approve', $accountsReceivable))->assertForbidden();
});

test('an admin approving an OnCredit request flips the receivable active and the job order on_credit', function () {
    $admin = User::factory()->admin()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::CreditPendingApproval->value]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create(['balance' => 1000]);

    $response = $this->actingAs($admin)->patch(route('admin.credit-requests.approve', $accountsReceivable));

    $response->assertRedirect();
    expect($accountsReceivable->fresh()->status)->toBe(AccountsReceivableStatus::Active);
    expect($accountsReceivable->fresh()->approved_by)->toBe($admin->id);
    expect($accountsReceivable->fresh()->approved_at)->not->toBeNull();
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::OnCredit);
});

test('approving an OnCredit request stamps due_at from credit_term_days (D-02)', function () {
    $admin = User::factory()->admin()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::CreditPendingApproval->value]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create(['balance' => 1000]);

    $this->actingAs($admin)->patch(route('admin.credit-requests.approve', $accountsReceivable));

    $fresh = $accountsReceivable->fresh();
    expect($fresh->due_at)->not->toBeNull();
    expect($fresh->due_at->diffInSeconds($fresh->approved_at->copy()->addDays(30)))->toBeLessThan(1);
});

test('changing credit_term_days after approval does not retroactively change an already-approved due_at (Pitfall 6)', function () {
    $admin = User::factory()->admin()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::CreditPendingApproval->value]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create(['balance' => 1000]);

    $this->actingAs($admin)->patch(route('admin.credit-requests.approve', $accountsReceivable));

    $dueAtBefore = $accountsReceivable->fresh()->due_at;

    SystemConfiguration::query()->where('key', 'credit_term_days')->update(['value' => 60]);
    SystemConfiguration::invalidate('credit_term_days');

    expect($accountsReceivable->fresh()->due_at->equalTo($dueAtBefore))->toBeTrue();
});

test('an admin rejecting an OnCredit request flips both to their rejected states with no other job order field touched', function () {
    $admin = User::factory()->admin()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create([
        'total_amount' => 1000,
        'payment_status' => PaymentStatus::CreditPendingApproval->value,
        'description' => 'Tarpaulin, 3x5ft',
    ]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create(['balance' => 1000]);

    $response = $this->actingAs($admin)->patch(route('admin.credit-requests.reject', $accountsReceivable));

    $response->assertRedirect();
    expect($accountsReceivable->fresh()->status)->toBe(AccountsReceivableStatus::Rejected);
    expect($accountsReceivable->fresh()->approved_by)->toBe($admin->id);
    expect($accountsReceivable->fresh()->approved_at)->not->toBeNull();

    $freshJobOrder = $jobOrder->fresh();
    expect($freshJobOrder->payment_status)->toBe(PaymentStatus::CreditRejected);
    expect($freshJobOrder->total_amount)->toEqual(1000);
    expect($freshJobOrder->description)->toBe('Tarpaulin, 3x5ft');
});

test('approving an already-resolved credit request is rejected and does not re-approve it (CR-05)', function () {
    $admin = User::factory()->admin()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::OnCredit->value]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create([
        'balance' => 1000,
        'status' => AccountsReceivableStatus::Active,
        'approved_by' => $admin->id,
        'approved_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($admin)->patch(route('admin.credit-requests.approve', $accountsReceivable));

    $response->assertStatus(422);
    expect($accountsReceivable->fresh()->status)->toBe(AccountsReceivableStatus::Active);
});

test('rejecting an already-approved credit request is rejected and does not flip the job order back (CR-05)', function () {
    $admin = User::factory()->admin()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::OnCredit->value]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create([
        'balance' => 1000,
        'status' => AccountsReceivableStatus::Active,
        'approved_by' => $admin->id,
        'approved_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($admin)->patch(route('admin.credit-requests.reject', $accountsReceivable));

    $response->assertStatus(422);
    expect($accountsReceivable->fresh()->status)->toBe(AccountsReceivableStatus::Active);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::OnCredit);
});

test('approving an already-rejected credit request is rejected and does not reverse the prior decision (CR-05)', function () {
    $admin = User::factory()->admin()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::CreditRejected->value]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create([
        'balance' => 1000,
        'status' => AccountsReceivableStatus::Rejected,
        'approved_by' => $admin->id,
        'approved_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($admin)->patch(route('admin.credit-requests.approve', $accountsReceivable));

    $response->assertStatus(422);
    expect($accountsReceivable->fresh()->status)->toBe(AccountsReceivableStatus::Rejected);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::CreditRejected);
});

test('approving a credit request fails when the job order was already settled by a real transaction (CR-01)', function () {
    $admin = User::factory()->admin()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::CreditPendingApproval->value]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create(['balance' => 1000]);
    Transaction::factory()->for($jobOrder)->create(['amount' => 1000]);

    $response = $this->actingAs($admin)->patch(route('admin.credit-requests.approve', $accountsReceivable));

    $response->assertStatus(422);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::CreditPendingApproval);
    expect($accountsReceivable->fresh()->status)->toBe(AccountsReceivableStatus::PendingApproval);
});

test('a cashier can request OnCredit for an eligible job order, posting the remaining outstanding balance', function () {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    // No transactions exist yet, so pricing is still editable
    // (JobOrder::pricingIsEditable()) — the pricing fields must be
    // submitted alongside the credit request, same as the very first
    // pricing/payment visit.
    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.credit-request.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => false,
    ]);

    $response->assertRedirect(route('cashier.dashboard'));

    $freshJobOrder = $jobOrder->fresh();
    expect($freshJobOrder->payment_status)->toBe(PaymentStatus::CreditPendingApproval);

    $accountsReceivable = AccountsReceivable::where('job_order_id', $jobOrder->id)->first();
    expect($accountsReceivable)->not->toBeNull();
    expect($accountsReceivable->status)->toBe(AccountsReceivableStatus::PendingApproval);
    expect((float) $accountsReceivable->balance)->toBe(1000.0);
    expect($accountsReceivable->requested_by)->toBe($cashier->id);
});

test('the accounts_receivable table enforces at most one row per job order at the database level (WR-04)', function () {
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000]);
    AccountsReceivable::factory()->for($jobOrder)->create(['balance' => 1000]);

    expect(fn () => AccountsReceivable::factory()->for($jobOrder)->create(['balance' => 500]))
        ->toThrow(QueryException::class);
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

test('the queue ships the customer name the page renders', function () {
    // Regression: the eager load selected `jobOrder:id,number,description`
    // without `queue_entry_id`, so Eloquent could not match the nested
    // queueEntry. `job_order.queue_entry` serialised as null and the page's
    // `queue_entry.customer.name` threw, rendering a blank screen for every
    // pending request.
    $admin = User::factory()->admin()->create();
    $jobOrder = JobOrder::factory()->create(['payment_status' => PaymentStatus::CreditPendingApproval->value]);
    AccountsReceivable::factory()->for($jobOrder)->create(['status' => AccountsReceivableStatus::PendingApproval->value]);

    $this->actingAs($admin)
        ->get(route('admin.credit-requests.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('creditRequests', 1)
            ->whereNot('creditRequests.0.job_order.queue_entry', null)
            ->has('creditRequests.0.job_order.queue_entry.customer.name'));
});

test('approving a credit request for a cancelled job order is refused', function () {
    $admin = User::factory()->admin()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::CreditPendingApproval->value, 'cancelled_at' => now()]);
    $accountsReceivable = AccountsReceivable::factory()->for($jobOrder)->create(['balance' => 1000]);

    $response = $this->actingAs($admin)->patch(route('admin.credit-requests.approve', $accountsReceivable));

    $response->assertStatus(422);
    expect($accountsReceivable->fresh()->status)->toBe(AccountsReceivableStatus::PendingApproval);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::CreditPendingApproval);
});
