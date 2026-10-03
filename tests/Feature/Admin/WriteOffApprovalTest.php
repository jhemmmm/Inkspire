<?php

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Enums\PaymentStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\User;

function pendingWriteOff(User $requestedBy, ?JobOrder $jobOrder = null): AccountsReceivable
{
    $accountsReceivable = AccountsReceivable::factory()
        ->atBracket(AccountsReceivableAgingBracket::NinetyPlus)
        ->for($jobOrder ?? JobOrder::factory()->create(['total_amount' => 1000]))
        ->create();

    $accountsReceivable->forceFill([
        'write_off_reason' => 'Business closed permanently',
        'write_off_requested_by' => $requestedBy->id,
        'write_off_requested_at' => now()->subDay(),
    ])->save();

    return $accountsReceivable;
}

test('the accounting staff who raised a write-off cannot approve it themselves', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = pendingWriteOff($accountingStaff);

    $this->actingAs($accountingStaff)->get(route('admin.write-off-requests.index'))->assertForbidden();
    $this->actingAs($accountingStaff)->patch(route('admin.write-off-requests.approve', $accountsReceivable))->assertForbidden();
    $this->actingAs($accountingStaff)->patch(route('admin.write-off-requests.reject', $accountsReceivable))->assertForbidden();
});

test('index() lists only entries with a pending write-off request', function () {
    $admin = User::factory()->admin()->create();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $pending = pendingWriteOff($accountingStaff);
    AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::NinetyPlus)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $response = $this->actingAs($admin)->get(route('admin.write-off-requests.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/WriteOffRequests')
        ->has('writeOffRequests', 1)
        ->where('writeOffRequests.0.id', $pending->id));
});

test('an admin approving a pending write-off sets collection_status and payment_status to written_off without touching total_amount or transactions', function () {
    $admin = User::factory()->admin()->create();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000]);
    $accountsReceivable = pendingWriteOff($accountingStaff, $jobOrder);

    $response = $this->actingAs($admin)->patch(route('admin.write-off-requests.approve', $accountsReceivable));

    $response->assertRedirect();
    $accountsReceivable->refresh();
    expect($accountsReceivable->collection_status)->toBe(AccountsReceivableCollectionStatus::WrittenOff);

    $freshJobOrder = $jobOrder->fresh();
    expect($freshJobOrder->payment_status)->toBe(PaymentStatus::WrittenOff);
    expect((float) $freshJobOrder->total_amount)->toBe(1000.0);
    expect($freshJobOrder->transactions()->count())->toBe(0);
});

test('an admin rejecting a pending write-off nulls the write-off columns and leaves the entry Active and aging', function () {
    $admin = User::factory()->admin()->create();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = pendingWriteOff($accountingStaff);

    $response = $this->actingAs($admin)->patch(route('admin.write-off-requests.reject', $accountsReceivable));

    $response->assertRedirect();
    $accountsReceivable->refresh();
    expect($accountsReceivable->write_off_reason)->toBeNull();
    expect($accountsReceivable->write_off_requested_by)->toBeNull();
    expect($accountsReceivable->write_off_requested_at)->toBeNull();
    expect($accountsReceivable->collection_status)->toBe(AccountsReceivableCollectionStatus::Pending);
    expect($accountsReceivable->status->value)->toBe('active');
});

test('approving or rejecting an entry with no pending write-off request returns 422 and mutates nothing', function () {
    $admin = User::factory()->admin()->create();
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::NinetyPlus)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $approveResponse = $this->actingAs($admin)->patch(route('admin.write-off-requests.approve', $accountsReceivable));
    $approveResponse->assertStatus(422);
    expect($accountsReceivable->fresh()->collection_status)->toBe(AccountsReceivableCollectionStatus::Pending);

    $rejectResponse = $this->actingAs($admin)->patch(route('admin.write-off-requests.reject', $accountsReceivable));
    $rejectResponse->assertStatus(422);
});

test('the admin\'s write-off queue no longer includes an entry after it has been approved', function () {
    $admin = User::factory()->admin()->create();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = pendingWriteOff($accountingStaff);

    $this->actingAs($admin)->patch(route('admin.write-off-requests.approve', $accountsReceivable))->assertRedirect();

    $response = $this->actingAs($admin)->get(route('admin.write-off-requests.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/WriteOffRequests')
        ->has('writeOffRequests', 0));
});

test('rejecting an entry whose collection_status is already written_off returns 422 and does not erase the write-off record', function () {
    $admin = User::factory()->admin()->create();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = pendingWriteOff($accountingStaff);
    // Simulate the pre-CR-02-fix stale-row state: collection_status is
    // already written_off but write_off_requested_at is somehow still set.
    $accountsReceivable->forceFill(['collection_status' => AccountsReceivableCollectionStatus::WrittenOff->value])->save();

    $response = $this->actingAs($admin)->patch(route('admin.write-off-requests.reject', $accountsReceivable));

    $response->assertStatus(422);
    $accountsReceivable->refresh();
    expect($accountsReceivable->write_off_reason)->not->toBeNull();
    expect($accountsReceivable->write_off_requested_by)->not->toBeNull();
    expect($accountsReceivable->write_off_requested_at)->not->toBeNull();
    expect($accountsReceivable->collection_status)->toBe(AccountsReceivableCollectionStatus::WrittenOff);
});

test('approving a write-off request fails if the entry was settled while the request was pending', function () {
    $admin = User::factory()->admin()->create();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::OnCredit->value]);
    $accountsReceivable = pendingWriteOff($accountingStaff, $jobOrder);

    // Simulate ar:send-reminders (or a Cashier payment) having just closed
    // this entry to Paid in the window between the request and the Admin's
    // click.
    $accountsReceivable->forceFill(['collection_status' => AccountsReceivableCollectionStatus::Paid->value])->save();

    $response = $this->actingAs($admin)->patch(route('admin.write-off-requests.approve', $accountsReceivable));

    $response->assertStatus(422);
    $accountsReceivable->refresh();
    expect($accountsReceivable->collection_status)->toBe(AccountsReceivableCollectionStatus::Paid);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::OnCredit);
});

test('approving a write-off fails when a real payment settled the job order while the request was pending', function () {
    $admin = User::factory()->admin()->create();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::OnCredit->value]);
    $accountsReceivable = pendingWriteOff($accountingStaff, $jobOrder);

    // A real Cashier-recorded payment settles the job order in full -- not a
    // forceFill on collection_status -- while the write-off request sits
    // pending. The daily ar:send-reminders cron hasn't run yet, so
    // collection_status is still Pending; only the derived-balance guard
    // can catch this.
    $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        // Nothing has been paid against this job order yet, so the Cashier's
        // screen still shows the pricing card and submits it alongside the
        // payment.
        'pricing_entry_id' => PricingEntry::factory()->create(['base_price' => 1000])->id,
        'line_amount' => 1000,
        'rush_fee_applied' => false,
        'payment_method' => 'cash',
        'payment_type' => 'full',
    ])->assertSessionHasNoErrors();

    $response = $this->actingAs($admin)->patch(route('admin.write-off-requests.approve', $accountsReceivable));

    $response->assertStatus(422);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::Paid);
    expect($accountsReceivable->fresh()->collection_status)->toBe(AccountsReceivableCollectionStatus::Pending);
});
