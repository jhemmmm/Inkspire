<?php

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a Current entry returns 200 with pastDue false instead of a 404', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::Current)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $response = $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.collection-letter.show', $accountsReceivable));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('accounting-staff/CollectionLetter')
        ->where('pastDue', false)
        ->where('letterBody', null),
    );
});

test('a past-due entry returns amountDue equal to the derived outstanding balance, not the stored balance column', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000]);
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::OneToFifteen)->for($jobOrder)->create(['balance' => 1000]);
    Transaction::factory()->for($jobOrder)->create(['amount' => 400]);

    $response = $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.collection-letter.show', $accountsReceivable));

    $response->assertOk();
    $response->assertInertia(function (Assert $page) {
        $props = $page->toArray()['props'];

        expect((float) $props['amountDue'])->toBe(600.0);
        expect((float) $props['creditExtended'])->toBe(1000.0);
        expect($props['pastDue'])->toBeTrue();
        expect($props['letterBody'])->toBeString();
    });
});

test('a non-Active entry 404s', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = AccountsReceivable::factory()->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.collection-letter.show', $accountsReceivable))
        ->assertNotFound();
});

test('a Paid or Written Off entry 404s, not just a non-Active one', function (string $terminal) {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::OneToFifteen)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();
    $accountsReceivable->forceFill(['collection_status' => $terminal])->save();

    $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.collection-letter.show', $accountsReceivable))
        ->assertNotFound();
})->with([
    'paid' => [AccountsReceivableCollectionStatus::Paid->value],
    'written_off' => [AccountsReceivableCollectionStatus::WrittenOff->value],
]);

test('letterBody() returns the correct body per aging bracket, with 61-90 and 90+ sharing the final notice', function () {
    expect(AccountsReceivableAgingBracket::OneToFifteen->letterBody())->toContain('friendly reminder');
    expect(AccountsReceivableAgingBracket::SixteenToThirty->letterBody())->toContain('outstanding for {n} days');
    expect(AccountsReceivableAgingBracket::ThirtyOneToSixty->letterBody())->toContain('formally requesting');

    $sixtyOneToNinety = AccountsReceivableAgingBracket::SixtyOneToNinety->letterBody();
    $ninetyPlus = AccountsReceivableAgingBracket::NinetyPlus->letterBody();

    expect($sixtyOneToNinety)->toBe($ninetyPlus);
    expect($ninetyPlus)->toContain('final notice');
});

test('letterBody() throws for the Current bracket, since a controller bug is the only way to reach it', function () {
    AccountsReceivableAgingBracket::Current->letterBody();
})->throws(LogicException::class);
