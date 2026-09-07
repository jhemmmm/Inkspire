<?php

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\User;

test('Accounting Staff can set collection_status to follow_up on an Active entry and it is audited', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::OneToFifteen)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $response = $this->actingAs($accountingStaff)->patch(
        route('accounting-staff.accounts-receivable.collection-status.update', $accountsReceivable),
        ['collection_status' => AccountsReceivableCollectionStatus::FollowUp->value],
    );

    $response->assertRedirect();
    $accountsReceivable->refresh();
    expect($accountsReceivable->collection_status)->toBe(AccountsReceivableCollectionStatus::FollowUp);

    $this->assertDatabaseHas('audit_trail', [
        'auditable_type' => AccountsReceivable::class,
        'auditable_id' => $accountsReceivable->id,
    ]);
});

test('collection_status can never be set to paid or written_off from client input', function (string $forbidden) {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::OneToFifteen)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $response = $this->actingAs($accountingStaff)->patch(
        route('accounting-staff.accounts-receivable.collection-status.update', $accountsReceivable),
        ['collection_status' => $forbidden],
    );

    $response->assertSessionHasErrors('collection_status');
    $accountsReceivable->refresh();
    expect($accountsReceivable->collection_status)->toBe(AccountsReceivableCollectionStatus::Pending);
})->with([
    'paid' => [AccountsReceivableCollectionStatus::Paid->value],
    'written_off' => [AccountsReceivableCollectionStatus::WrittenOff->value],
]);

test('updating collection_status on a non-Active entry returns 422 with the closed-entry message', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = AccountsReceivable::factory()->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $response = $this->actingAs($accountingStaff)->patch(
        route('accounting-staff.accounts-receivable.collection-status.update', $accountsReceivable),
        ['collection_status' => AccountsReceivableCollectionStatus::FollowUp->value],
    );

    $response->assertStatus(422);
});

test('updating collection_status on an entry already Paid or Written Off returns 422', function (string $terminal) {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::OneToFifteen)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();
    $accountsReceivable->forceFill(['collection_status' => $terminal])->save();

    $response = $this->actingAs($accountingStaff)->patch(
        route('accounting-staff.accounts-receivable.collection-status.update', $accountsReceivable),
        ['collection_status' => AccountsReceivableCollectionStatus::FollowUp->value],
    );

    $response->assertStatus(422);
})->with([
    'paid' => [AccountsReceivableCollectionStatus::Paid->value],
    'written_off' => [AccountsReceivableCollectionStatus::WrittenOff->value],
]);
