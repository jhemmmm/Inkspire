<?php

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a pending approval or rejected AR row never appears in the aging list (D-01)', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();

    $pending = AccountsReceivable::factory()->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();
    $rejected = AccountsReceivable::factory()->rejected()->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();

    $response = $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.index'));

    $response->assertOk();
    $response->assertInertia(function (Assert $page) use ($pending, $rejected) {
        $page->component('accounting-staff/AccountsReceivable/Index');

        $ids = collect($page->toArray()['props']['receivables'])->pluck('id')
            ->merge(collect($page->toArray()['props']['closedReceivables'])->pluck('id'));

        expect($ids)->not->toContain($pending->id);
        expect($ids)->not->toContain($rejected->id);
    });
});

test('outstanding balance is derived from completed transactions, never the stored balance column (D-16)', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();

    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000]);
    $accountsReceivable = AccountsReceivable::factory()->active()->for($jobOrder)->create(['balance' => 1000]);
    Transaction::factory()->for($jobOrder)->create(['amount' => 400]);

    $response = $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.index'));

    $response->assertOk();
    $response->assertInertia(function (Assert $page) use ($accountsReceivable) {
        $row = collect($page->toArray()['props']['receivables'])->firstWhere('id', $accountsReceivable->id);

        expect($row)->not->toBeNull();
        expect((float) $row['balance'])->toBe(600.0);
        expect((float) $row['credit_extended'])->toBe(1000.0);
    });
});

test('each Active entry lands in the correct one of the six bracketSummaries buckets (D-03)', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();

    $current = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::Current)->for(JobOrder::factory()->create(['total_amount' => 500]))->create(['balance' => 500]);
    $oneToFifteen = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::OneToFifteen)->for(JobOrder::factory()->create(['total_amount' => 1000]))->create(['balance' => 1000]);
    $ninetyPlus = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::NinetyPlus)->for(JobOrder::factory()->create(['total_amount' => 2000]))->create(['balance' => 2000]);

    $response = $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.index'));

    $response->assertOk();
    $response->assertInertia(function (Assert $page) use ($current, $oneToFifteen, $ninetyPlus) {
        $summaries = collect($page->toArray()['props']['bracketSummaries'])->keyBy('bracket');

        expect($summaries)->toHaveCount(6);
        expect((float) $summaries['current']['total'])->toBe(500.0);
        expect($summaries['current']['count'])->toBe(1);
        expect((float) $summaries['one_to_fifteen']['total'])->toBe(1000.0);
        expect($summaries['one_to_fifteen']['count'])->toBe(1);
        expect((float) $summaries['ninety_plus']['total'])->toBe(2000.0);
        expect($summaries['ninety_plus']['count'])->toBe(1);
        expect((float) $summaries['sixteen_to_thirty']['total'])->toBe(0.0);
        expect($summaries['sixteen_to_thirty']['count'])->toBe(0);
    });
});

test('a written off entry appears in closedReceivables, not receivables, and is excluded from every bracket total (D-14/UI-SPEC)', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();

    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000]);
    $writtenOff = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::NinetyPlus)->for($jobOrder)->create(['balance' => 1000]);
    $writtenOff->forceFill(['collection_status' => AccountsReceivableCollectionStatus::WrittenOff->value])->save();

    $response = $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.index'));

    $response->assertOk();
    $response->assertInertia(function (Assert $page) use ($writtenOff) {
        $openIds = collect($page->toArray()['props']['receivables'])->pluck('id');
        $closedIds = collect($page->toArray()['props']['closedReceivables'])->pluck('id');

        expect($openIds)->not->toContain($writtenOff->id);
        expect($closedIds)->toContain($writtenOff->id);

        $summaries = collect($page->toArray()['props']['bracketSummaries'])->keyBy('bracket');
        expect((float) $summaries['ninety_plus']['total'])->toBe(0.0);
        expect($summaries['ninety_plus']['count'])->toBe(0);
    });
});

test('show 404s for a pending approval entry and 200s for both an open and a closed Active entry', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();

    $pending = AccountsReceivable::factory()->for(JobOrder::factory()->create(['total_amount' => 1000]))->create();
    $open = AccountsReceivable::factory()->active()->for(JobOrder::factory()->create(['total_amount' => 1000]))->create(['balance' => 1000]);
    $closed = AccountsReceivable::factory()->active()->for(JobOrder::factory()->create(['total_amount' => 1000]))->create(['balance' => 1000]);
    $closed->forceFill(['collection_status' => AccountsReceivableCollectionStatus::Paid->value])->save();

    $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.show', $pending))->assertNotFound();

    $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.show', $open))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('accounting-staff/AccountsReceivable/Show'));

    $this->actingAs($accountingStaff)->get(route('accounting-staff.accounts-receivable.show', $closed))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('accounting-staff/AccountsReceivable/Show'));
});
