<?php

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('collection status follows the aging bracket', function (AccountsReceivableAgingBracket $bracket, AccountsReceivableCollectionStatus $expected) {
    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000]);
    $accountsReceivable = AccountsReceivable::factory()->atBracket($bracket)->for($jobOrder)->create(['balance' => 1000]);

    expect($accountsReceivable->collectionStatus())->toBe($expected);
})->with([
    'not yet due' => [AccountsReceivableAgingBracket::Current, AccountsReceivableCollectionStatus::Pending],
    '1-15 days' => [AccountsReceivableAgingBracket::OneToFifteen, AccountsReceivableCollectionStatus::Pending],
    '16-30 days' => [AccountsReceivableAgingBracket::SixteenToThirty, AccountsReceivableCollectionStatus::FollowUp],
    '31-60 days' => [AccountsReceivableAgingBracket::ThirtyOneToSixty, AccountsReceivableCollectionStatus::WarningSent],
    '61-90 days' => [AccountsReceivableAgingBracket::SixtyOneToNinety, AccountsReceivableCollectionStatus::WarningSent],
    '90+ days' => [AccountsReceivableAgingBracket::NinetyPlus, AccountsReceivableCollectionStatus::Collections],
]);

test('a settled balance reads Paid immediately, without waiting for the nightly reminder run', function () {
    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000]);
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::NinetyPlus)->for($jobOrder)->create(['balance' => 1000]);

    expect($accountsReceivable->collectionStatus())->toBe(AccountsReceivableCollectionStatus::Collections);

    Transaction::factory()->for($jobOrder)->create(['amount' => 1000]);

    // No ar:send-reminders run in between -- the stored column is still
    // 'pending', and the entry must not need a cron pass to close.
    expect($accountsReceivable->fresh()->collectionStatus())->toBe(AccountsReceivableCollectionStatus::Paid)
        ->and($accountsReceivable->fresh()->collection_status)->toBe(AccountsReceivableCollectionStatus::Pending);
});

test('a part payment leaves the entry chased at its aging bracket', function () {
    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000]);
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::SixteenToThirty)->for($jobOrder)->create(['balance' => 1000]);
    Transaction::factory()->for($jobOrder)->create(['amount' => 999]);

    expect($accountsReceivable->fresh()->collectionStatus())->toBe(AccountsReceivableCollectionStatus::FollowUp);
});

test('a terminal status the system wrote beats whatever the aging says', function (AccountsReceivableCollectionStatus $terminal) {
    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000]);
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::NinetyPlus)->for($jobOrder)->create(['balance' => 1000]);
    $accountsReceivable->forceFill(['collection_status' => $terminal->value])->save();

    expect($accountsReceivable->fresh()->collectionStatus())->toBe($terminal)
        ->and($accountsReceivable->fresh()->isClosed())->toBeTrue();
})->with([
    'written off' => [AccountsReceivableCollectionStatus::WrittenOff],
    'cancelled' => [AccountsReceivableCollectionStatus::Cancelled],
]);

test('the aging list shows the derived status and moves a settled entry into the closed list', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();

    $overdue = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::ThirtyOneToSixty)
        ->for(JobOrder::factory()->create(['total_amount' => 1000]))->create(['balance' => 1000]);

    $settledJobOrder = JobOrder::factory()->create(['total_amount' => 1000]);
    $settled = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::ThirtyOneToSixty)->for($settledJobOrder)->create(['balance' => 1000]);
    Transaction::factory()->for($settledJobOrder)->create(['amount' => 1000]);

    $response = $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.index'));

    $response->assertOk();
    $response->assertInertia(function (Assert $page) use ($overdue, $settled) {
        $open = collect($page->toArray()['props']['receivables']);
        $closed = collect($page->toArray()['props']['closedReceivables']);

        expect($open->firstWhere('id', $overdue->id)['collection_status'])->toBe('warning_sent');
        expect($open->pluck('id'))->not->toContain($settled->id);
        expect($closed->firstWhere('id', $settled->id)['collection_status'])->toBe('paid');

        // A settled balance must not keep inflating the aging buckets.
        $summaries = collect($page->toArray()['props']['bracketSummaries'])->keyBy('bracket');
        expect((float) $summaries['thirty_one_to_sixty']['total'])->toBe(1000.0);
        expect($summaries['thirty_one_to_sixty']['count'])->toBe(1);
    });
});

test('there is no endpoint left for setting a collection status by hand', function () {
    expect(Route::has('accounting-staff.accounts-receivable.collection-status.update'))->toBeFalse();
});

test('an unpriced job order is never read as settled', function () {
    // outstandingBalance() returns 0.0 when total_amount is null, which must
    // not be mistaken for a paid balance.
    $jobOrder = JobOrder::factory()->create(['total_amount' => null]);
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::SixteenToThirty)->for($jobOrder)->create(['balance' => 1000]);

    expect($accountsReceivable->collectionStatus())->toBe(AccountsReceivableCollectionStatus::FollowUp)
        ->and($accountsReceivable->isClosed())->toBeFalse();
});
