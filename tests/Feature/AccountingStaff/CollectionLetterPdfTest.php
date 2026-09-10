<?php

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\Transaction;
use App\Models\User;

test('a past-due Active entry with an open collection status downloads a real PDF', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000]);
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::OneToFifteen)->for($jobOrder)->create();
    Transaction::factory()->for($jobOrder)->create(['amount' => 400]);

    $response = $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.collection-letter.pdf', $accountsReceivable));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect(strlen($response->getContent()))->toBeGreaterThan(0);
});

test('a Current (not-yet-due) entry 404s on the pdf route, unlike show() which returns 200', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::Current)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.collection-letter.pdf', $accountsReceivable))
        ->assertNotFound();

    // show() is untouched -- the same entry still gets a 200 there.
    $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.collection-letter.show', $accountsReceivable))
        ->assertOk();
});

test('a non-Active entry 404s on the pdf route', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = AccountsReceivable::factory()->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.collection-letter.pdf', $accountsReceivable))
        ->assertNotFound();
});

test('a Paid or Written Off entry 404s on the pdf route', function (string $terminal) {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $accountsReceivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::OneToFifteen)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();
    $accountsReceivable->forceFill(['collection_status' => $terminal])->save();

    $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.collection-letter.pdf', $accountsReceivable))
        ->assertNotFound();
})->with([
    'paid' => [AccountsReceivableCollectionStatus::Paid->value],
    'written_off' => [AccountsReceivableCollectionStatus::WrittenOff->value],
]);
