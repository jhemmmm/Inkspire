<?php

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Enums\TransactionStatus;
use App\Mail\AccountsReceivableReminder;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\Transaction;
use Illuminate\Support\Facades\Mail;

test('an entry with no prior bracket that is now past due gets exactly one reminder and stamps the bracket', function () {
    Mail::fake();

    $receivable = AccountsReceivable::factory()
        ->for(JobOrder::factory(['total_amount' => 5000]))
        ->atBracket(AccountsReceivableAgingBracket::OneToFifteen)
        ->create();

    $this->artisan('ar:send-reminders')->assertExitCode(0);

    Mail::assertSent(AccountsReceivableReminder::class, 1);
    Mail::assertSent(AccountsReceivableReminder::class, fn ($mail) => $mail->receivable->is($receivable) && $mail->bracket === AccountsReceivableAgingBracket::OneToFifteen);

    expect($receivable->fresh()->last_reminder_bracket)->toBe(AccountsReceivableAgingBracket::OneToFifteen);
});

test('running the command again the same day sends no duplicate reminder', function () {
    Mail::fake();

    AccountsReceivable::factory()
        ->for(JobOrder::factory(['total_amount' => 5000]))
        ->atBracket(AccountsReceivableAgingBracket::OneToFifteen)
        ->create();

    $this->artisan('ar:send-reminders')->assertExitCode(0);
    Mail::assertSent(AccountsReceivableReminder::class, 1);

    $this->artisan('ar:send-reminders')->assertExitCode(0);
    Mail::assertSent(AccountsReceivableReminder::class, 1);
});

test('an entry that advances to a new bracket gets exactly one new reminder and the stamp advances', function () {
    Mail::fake();

    $receivable = AccountsReceivable::factory()
        ->for(JobOrder::factory(['total_amount' => 5000]))
        ->atBracket(AccountsReceivableAgingBracket::SixteenToThirty)
        ->create();

    $receivable->forceFill(['last_reminder_bracket' => AccountsReceivableAgingBracket::OneToFifteen->value])->save();

    $this->artisan('ar:send-reminders')->assertExitCode(0);

    Mail::assertSent(AccountsReceivableReminder::class, fn ($mail) => $mail->receivable->is($receivable) && $mail->bracket === AccountsReceivableAgingBracket::SixteenToThirty);

    expect($receivable->fresh()->last_reminder_bracket)->toBe(AccountsReceivableAgingBracket::SixteenToThirty);
});

test('an entry in the display-only 61-90 band receives no mail and its stamp is left unchanged', function () {
    Mail::fake();

    $receivable = AccountsReceivable::factory()
        ->for(JobOrder::factory(['total_amount' => 5000]))
        ->atBracket(AccountsReceivableAgingBracket::SixtyOneToNinety)
        ->create();

    $this->artisan('ar:send-reminders')->assertExitCode(0);

    Mail::assertNothingSent();
    expect($receivable->fresh()->last_reminder_bracket)->toBeNull();
});

test('an entry whose derived balance has reached zero is marked paid and receives no mail', function () {
    Mail::fake();

    $jobOrder = JobOrder::factory(['total_amount' => 5000])->create();

    $receivable = AccountsReceivable::factory()
        ->for($jobOrder)
        ->atBracket(AccountsReceivableAgingBracket::NinetyPlus)
        ->create();

    Transaction::factory()->for($jobOrder)->create([
        'status' => TransactionStatus::Completed->value,
        'amount' => 5000,
    ]);

    $this->artisan('ar:send-reminders')->assertExitCode(0);

    Mail::assertNothingSent();
    expect($receivable->fresh()->collection_status)->toBe(AccountsReceivableCollectionStatus::Paid);
});

test('an entry already paid or written off is excluded from the query entirely', function () {
    Mail::fake();

    $receivable = AccountsReceivable::factory()
        ->for(JobOrder::factory(['total_amount' => 5000]))
        ->atBracket(AccountsReceivableAgingBracket::NinetyPlus)
        ->create();

    $receivable->forceFill(['collection_status' => AccountsReceivableCollectionStatus::WrittenOff->value])->save();

    $this->artisan('ar:send-reminders')->assertExitCode(0);

    Mail::assertNothingSent();
    expect($receivable->fresh()->last_reminder_bracket)->toBeNull();
});

test('a mail transport failure is isolated and never blocks the bracket stamp', function () {
    Mail::shouldReceive('to')->once()->andThrow(new Exception('Resend transport failure'));

    $receivable = AccountsReceivable::factory()
        ->for(JobOrder::factory(['total_amount' => 5000]))
        ->atBracket(AccountsReceivableAgingBracket::OneToFifteen)
        ->create();

    $this->artisan('ar:send-reminders')->assertExitCode(0);

    expect($receivable->fresh()->last_reminder_bracket)->toBe(AccountsReceivableAgingBracket::OneToFifteen);
});

test('the command is registered on the daily schedule', function () {
    $this->artisan('schedule:list')
        ->assertExitCode(0)
        ->expectsOutputToContain('ar:send-reminders');
});
