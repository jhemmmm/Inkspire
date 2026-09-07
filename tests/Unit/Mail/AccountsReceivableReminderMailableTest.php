<?php

use App\Enums\AccountsReceivableAgingBracket;
use App\Mail\AccountsReceivableReminder;
use App\Models\AccountsReceivable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('the mailable subject escalates with the aging bracket', function () {
    $receivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::NinetyPlus)->create();

    $mail = new AccountsReceivableReminder($receivable, AccountsReceivableAgingBracket::NinetyPlus);

    expect($mail->envelope()->subject)->toStartWith('Final notice');
});
