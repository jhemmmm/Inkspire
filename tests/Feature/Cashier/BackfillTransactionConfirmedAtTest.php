<?php

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

function backfillTransactionConfirmedAt(): void
{
    (require database_path('migrations/2026_10_02_194443_backfill_transaction_confirmed_at.php'))->up();
}

test('the backfill stamps a completed payment that lost its confirmed_at with the time it was recorded', function () {
    $transaction = Transaction::factory()->create();
    DB::table('transactions')->where('id', $transaction->id)->update(['confirmed_at' => null, 'created_at' => '2026-10-02 16:54:58']);

    backfillTransactionConfirmedAt();

    expect($transaction->fresh()->confirmed_at->toDateTimeString())->toBe('2026-10-02 16:54:58');
});

test('the backfill leaves an existing confirmed_at alone', function () {
    $transaction = Transaction::factory()->create(['confirmed_at' => '2026-10-01 09:00:00', 'created_at' => '2026-09-30 09:00:00']);

    backfillTransactionConfirmedAt();

    expect($transaction->fresh()->confirmed_at->toDateTimeString())->toBe('2026-10-01 09:00:00');
});

test('the backfill never stamps a payment that has not cleared', function (string $status) {
    $transaction = Transaction::factory()->create(['status' => $status, 'confirmed_at' => null]);

    backfillTransactionConfirmedAt();

    expect($transaction->fresh()->confirmed_at)->toBeNull();
})->with(['pending_confirmation', 'failed']);
