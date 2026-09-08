<?php

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\User;

test('Accounting Staff can request a write-off for an Active entry with a mandatory reason', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::NinetyPlus)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $response = $this->actingAs($accountingStaff)->post(
        route('accounting-staff.accounts-receivable.write-off.store', $accountsReceivable),
        ['reason' => 'Business closed permanently — three collection attempts returned undeliverable'],
    );

    $response->assertRedirect();
    $accountsReceivable->refresh();
    expect($accountsReceivable->write_off_reason)->toBe('Business closed permanently — three collection attempts returned undeliverable');
    expect($accountsReceivable->write_off_requested_by)->toBe($accountingStaff->id);
    expect($accountsReceivable->write_off_requested_at)->not->toBeNull();
    expect($accountsReceivable->collection_status)->toBe(AccountsReceivableCollectionStatus::Pending);
});

test('requesting a write-off with a blank reason returns a 422 validation error and writes nothing', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::NinetyPlus)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $response = $this->actingAs($accountingStaff)->post(
        route('accounting-staff.accounts-receivable.write-off.store', $accountsReceivable),
        ['reason' => ''],
    );

    $response->assertSessionHasErrors('reason');
    $accountsReceivable->refresh();
    expect($accountsReceivable->write_off_requested_at)->toBeNull();
});

test('requesting a write-off for an entry with an already-pending request returns 422 and does not overwrite it', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::NinetyPlus)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();
    $accountsReceivable->forceFill([
        'write_off_reason' => 'Original reason',
        'write_off_requested_by' => $accountingStaff->id,
        'write_off_requested_at' => now()->subDay(),
    ])->save();

    $response = $this->actingAs($accountingStaff)->post(
        route('accounting-staff.accounts-receivable.write-off.store', $accountsReceivable),
        ['reason' => 'A different reason'],
    );

    $response->assertStatus(422);
    $accountsReceivable->refresh();
    expect($accountsReceivable->write_off_reason)->toBe('Original reason');
});

test('requesting a write-off for a non-Active entry returns 422', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = AccountsReceivable::factory()->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $response = $this->actingAs($accountingStaff)->post(
        route('accounting-staff.accounts-receivable.write-off.store', $accountsReceivable),
        ['reason' => 'Some reason'],
    );

    $response->assertStatus(422);
    $accountsReceivable->refresh();
    expect($accountsReceivable->write_off_requested_at)->toBeNull();
});

test('requesting a write-off for an Active entry already closed by collection_status returns 422 and writes nothing (Blocker 2)', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::NinetyPlus)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();
    $accountsReceivable->forceFill(['collection_status' => AccountsReceivableCollectionStatus::Paid->value])->save();

    $response = $this->actingAs($accountingStaff)->post(
        route('accounting-staff.accounts-receivable.write-off.store', $accountsReceivable),
        ['reason' => 'Some reason'],
    );

    $response->assertStatus(422);
    $accountsReceivable->refresh();
    expect($accountsReceivable->write_off_requested_at)->toBeNull();
});

test('AccountsReceivablePolicy approveWriteOff/rejectWriteOff return true only for Owner', function () {
    $owner = User::factory()->owner()->create();
    $admin = User::factory()->admin()->create();
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::NinetyPlus)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    expect($owner->can('approveWriteOff', $accountsReceivable))->toBeTrue();
    expect($owner->can('rejectWriteOff', $accountsReceivable))->toBeTrue();
    expect($admin->can('approveWriteOff', $accountsReceivable))->toBeFalse();
    expect($admin->can('rejectWriteOff', $accountsReceivable))->toBeFalse();
});
