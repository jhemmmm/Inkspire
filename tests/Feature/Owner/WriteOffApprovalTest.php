<?php

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Enums\PaymentStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
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

test('an admin can view the write-off requests queue but is forbidden from approving or rejecting', function () {
    $admin = User::factory()->admin()->create();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = pendingWriteOff($accountingStaff);

    $indexResponse = $this->actingAs($admin)->get(route('owner.write-off-requests.index'));
    $indexResponse->assertOk();

    $approveResponse = $this->actingAs($admin)->patch(route('owner.write-off-requests.approve', $accountsReceivable));
    $approveResponse->assertForbidden();

    $rejectResponse = $this->actingAs($admin)->patch(route('owner.write-off-requests.reject', $accountsReceivable));
    $rejectResponse->assertForbidden();
});

test('index() lists only entries with a pending write-off request', function () {
    $owner = User::factory()->owner()->create();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $pending = pendingWriteOff($accountingStaff);
    AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::NinetyPlus)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $response = $this->actingAs($owner)->get(route('owner.write-off-requests.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('owner/WriteOffRequests')
        ->has('writeOffRequests', 1)
        ->where('writeOffRequests.0.id', $pending->id));
});

test('an owner approving a pending write-off sets collection_status and payment_status to written_off without touching total_amount or transactions', function () {
    $owner = User::factory()->owner()->create();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000]);
    $accountsReceivable = pendingWriteOff($accountingStaff, $jobOrder);

    $response = $this->actingAs($owner)->patch(route('owner.write-off-requests.approve', $accountsReceivable));

    $response->assertRedirect();
    $accountsReceivable->refresh();
    expect($accountsReceivable->collection_status)->toBe(AccountsReceivableCollectionStatus::WrittenOff);

    $freshJobOrder = $jobOrder->fresh();
    expect($freshJobOrder->payment_status)->toBe(PaymentStatus::WrittenOff);
    expect((float) $freshJobOrder->total_amount)->toBe(1000.0);
    expect($freshJobOrder->transactions()->count())->toBe(0);
});

test('an owner rejecting a pending write-off nulls the write-off columns and leaves the entry Active and aging', function () {
    $owner = User::factory()->owner()->create();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = pendingWriteOff($accountingStaff);

    $response = $this->actingAs($owner)->patch(route('owner.write-off-requests.reject', $accountsReceivable));

    $response->assertRedirect();
    $accountsReceivable->refresh();
    expect($accountsReceivable->write_off_reason)->toBeNull();
    expect($accountsReceivable->write_off_requested_by)->toBeNull();
    expect($accountsReceivable->write_off_requested_at)->toBeNull();
    expect($accountsReceivable->collection_status)->toBe(AccountsReceivableCollectionStatus::Pending);
    expect($accountsReceivable->status->value)->toBe('active');
});

test('approving or rejecting an entry with no pending write-off request returns 422 and mutates nothing', function () {
    $owner = User::factory()->owner()->create();
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::NinetyPlus)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $approveResponse = $this->actingAs($owner)->patch(route('owner.write-off-requests.approve', $accountsReceivable));
    $approveResponse->assertStatus(422);
    expect($accountsReceivable->fresh()->collection_status)->toBe(AccountsReceivableCollectionStatus::Pending);

    $rejectResponse = $this->actingAs($owner)->patch(route('owner.write-off-requests.reject', $accountsReceivable));
    $rejectResponse->assertStatus(422);
});

test('approving a write-off request fails if the entry was settled while the request was pending', function () {
    $owner = User::factory()->owner()->create();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000, 'payment_status' => PaymentStatus::OnCredit->value]);
    $accountsReceivable = pendingWriteOff($accountingStaff, $jobOrder);

    // Simulate ar:send-reminders (or a Cashier payment) having just closed
    // this entry to Paid in the window between the request and the Owner's
    // click.
    $accountsReceivable->forceFill(['collection_status' => AccountsReceivableCollectionStatus::Paid->value])->save();

    $response = $this->actingAs($owner)->patch(route('owner.write-off-requests.approve', $accountsReceivable));

    $response->assertStatus(422);
    $accountsReceivable->refresh();
    expect($accountsReceivable->collection_status)->toBe(AccountsReceivableCollectionStatus::Paid);
    expect($jobOrder->fresh()->payment_status)->toBe(PaymentStatus::OnCredit);
});
