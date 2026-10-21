<?php

use App\Enums\AccountsReceivableAgingBracket;
use App\Mail\AccountsReceivableReminder;
use App\Mail\JobOrdersReceived;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use App\Models\QueueEntry;

test('the order receipt email subject names the job order without a dash', function () {
    $entry = QueueEntry::factory()->create();
    JobOrder::factory()->for($entry)->create(['number' => 'JO-2026-0226']);

    $subject = (new JobOrdersReceived($entry->load('jobOrders')))->envelope()->subject;

    expect($subject)->toBe('We received your order: JO-2026-0226');
});

test('the receivables reminder subject reads as label and colon', function () {
    $receivable = AccountsReceivable::factory()->atBracket(AccountsReceivableAgingBracket::NinetyPlus)->create();

    $subject = (new AccountsReceivableReminder($receivable, AccountsReceivableAgingBracket::NinetyPlus))->envelope()->subject;

    expect($subject)->toStartWith('Final notice: ')->not->toContain('—');
});
