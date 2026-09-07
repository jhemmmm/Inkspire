<?php

use App\Enums\AccountsReceivableAgingBracket;
use App\Models\AccountsReceivable;
use Illuminate\Support\Facades\DB;

test('agingBracket() returns Current when due_at is null', function () {
    $accountsReceivable = AccountsReceivable::factory()->make(['due_at' => null]);

    expect($accountsReceivable->agingBracket())->toBe(AccountsReceivableAgingBracket::Current);
});

test('agingBracket() returns Current when due_at is in the future', function () {
    $accountsReceivable = AccountsReceivable::factory()->make(['due_at' => now()->addDays(5)]);

    expect($accountsReceivable->agingBracket())->toBe(AccountsReceivableAgingBracket::Current);
});

test('agingBracket() returns the correct bracket at each boundary day past due', function (int $daysPastDue, AccountsReceivableAgingBracket $expected) {
    $accountsReceivable = AccountsReceivable::factory()->make(['due_at' => now()->subDays($daysPastDue)]);

    expect($accountsReceivable->agingBracket())->toBe($expected);
})->with([
    'exactly 1 day past due → OneToFifteen' => [1, AccountsReceivableAgingBracket::OneToFifteen],
    'exactly 15 days past due → OneToFifteen' => [15, AccountsReceivableAgingBracket::OneToFifteen],
    'exactly 16 days past due → SixteenToThirty' => [16, AccountsReceivableAgingBracket::SixteenToThirty],
    'exactly 30 days past due → SixteenToThirty' => [30, AccountsReceivableAgingBracket::SixteenToThirty],
    'exactly 31 days past due → ThirtyOneToSixty' => [31, AccountsReceivableAgingBracket::ThirtyOneToSixty],
    'exactly 60 days past due → ThirtyOneToSixty' => [60, AccountsReceivableAgingBracket::ThirtyOneToSixty],
    'exactly 61 days past due → SixtyOneToNinety' => [61, AccountsReceivableAgingBracket::SixtyOneToNinety],
    'exactly 90 days past due → SixtyOneToNinety' => [90, AccountsReceivableAgingBracket::SixtyOneToNinety],
    'exactly 91 days past due → NinetyPlus' => [91, AccountsReceivableAgingBracket::NinetyPlus],
    'exactly 365 days past due → NinetyPlus' => [365, AccountsReceivableAgingBracket::NinetyPlus],
]);

test('daysPastDue() returns null when not yet due', function () {
    $accountsReceivable = AccountsReceivable::factory()->make(['due_at' => now()->addDays(5)]);

    expect($accountsReceivable->daysPastDue())->toBeNull();
});

test('daysPastDue() returns the correct positive integer when overdue', function () {
    $accountsReceivable = AccountsReceivable::factory()->make(['due_at' => now()->subDays(45)]);

    expect($accountsReceivable->daysPastDue())->toBe(45);
});

test('the backfill migration is idempotent and re-runnable (WR-10 shape)', function () {
    $accountsReceivable = AccountsReceivable::factory()->active()->create();
    $originalDueAt = $accountsReceivable->fresh()->due_at;

    DB::table('accounts_receivable')->where('id', $accountsReceivable->id)->update(['due_at' => null]);
    expect($accountsReceivable->fresh()->due_at)->toBeNull();

    (require database_path('migrations/2026_09_08_090000_add_aging_and_collection_columns_to_accounts_receivable_table.php'))->up();

    $backfilled = $accountsReceivable->fresh()->due_at;
    expect($backfilled)->not->toBeNull();
    expect($backfilled->equalTo($originalDueAt))->toBeTrue();

    // Running it again must be a no-op — no row already carrying due_at is touched.
    (require database_path('migrations/2026_09_08_090000_add_aging_and_collection_columns_to_accounts_receivable_table.php'))->up();

    expect($accountsReceivable->fresh()->due_at->equalTo($backfilled))->toBeTrue();
});
