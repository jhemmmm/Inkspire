<?php

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Mail\CustomerBalanceReminder;
use App\Models\AccountsReceivable;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

function owedReceivable(AccountsReceivableAgingBracket $bracket, ?string $contactEmail = null): AccountsReceivable
{
    $queueEntry = QueueEntry::factory()
        ->for(Customer::factory()->create(['name' => 'Rosa Dimaculangan', 'email' => 'rosa@example.test']))
        ->create();
    $queueEntry->forceFill(['contact_email' => $contactEmail])->save();

    $jobOrder = JobOrder::factory()->for($queueEntry)->create(['number' => 'JO-2026-0042', 'total_amount' => 1500]);

    return AccountsReceivable::factory()->atBracket($bracket)->for($jobOrder)->create(['balance' => 1500])->fresh();
}

function emailCustomer(AccountsReceivable $receivable, ?User $user = null): TestResponse
{
    return test()->actingAs($user ?? User::factory()->accountingStaff()->create())
        ->post(route('accounting-staff.accounts-receivable.customer-email.store', $receivable));
}

test('the email goes to the visit\'s confirmed address, or the customer\'s own', function (?string $contactEmail, string $recipient) {
    Mail::fake();

    emailCustomer(owedReceivable(AccountsReceivableAgingBracket::NinetyPlus, $contactEmail))->assertRedirect();

    Mail::assertSent(CustomerBalanceReminder::class, 1);
    Mail::assertSent(CustomerBalanceReminder::class, fn (CustomerBalanceReminder $mail): bool => $mail->hasTo($recipient));
})->with([
    'counter visit' => [null, 'rosa@example.test'],
    'website order' => ['rosa.web@example.test', 'rosa.web@example.test'],
]);

test('the wording follows how far past due the balance is', function (AccountsReceivableAgingBracket $bracket, string $subject, string $body) {
    $mail = new CustomerBalanceReminder(owedReceivable($bracket));

    $mail->assertHasSubject($subject);
    $mail->assertSeeInOrderInHtml(['Rosa Dimaculangan', $body, 'JO-2026-0042', '1,500.00']);
    $mail->assertDontSeeInHtml('{n}');
    $mail->assertDontSeeInHtml('{due date}');
})->with([
    'not yet due' => [AccountsReceivableAgingBracket::Current, 'Reminder: balance for JO-2026-0042', 'It is not overdue.'],
    '1-15 days' => [AccountsReceivableAgingBracket::OneToFifteen, 'Your balance for JO-2026-0042 is past due', 'friendly reminder that the balance below became due'],
    '16-30 days' => [AccountsReceivableAgingBracket::SixteenToThirty, 'Your balance for JO-2026-0042 is 20 days past due', 'outstanding for 20 days'],
    '31-60 days' => [AccountsReceivableAgingBracket::ThirtyOneToSixty, 'Overdue: please settle your balance for JO-2026-0042', 'formally requesting full settlement'],
    '61-90 days' => [AccountsReceivableAgingBracket::SixtyOneToNinety, 'Urgent: your balance for JO-2026-0042 is 75 days past due', 'our next notice will be a final notice'],
    '90+ days' => [AccountsReceivableAgingBracket::NinetyPlus, 'Final notice: your balance for JO-2026-0042', 'This is a final notice.'],
]);

test('only 90+ days gets the final notice itself', function (AccountsReceivableAgingBracket $bracket) {
    expect($bracket->emailBody())->not->toContain('This is a final notice');
})->with(fn (): array => array_filter(
    AccountsReceivableAgingBracket::cases(),
    fn (AccountsReceivableAgingBracket $bracket): bool => $bracket !== AccountsReceivableAgingBracket::NinetyPlus,
));

test('sending records the time against the staff user and leaves the staff reminders alone', function () {
    Mail::fake();
    $this->freezeSecond();
    $receivable = owedReceivable(AccountsReceivableAgingBracket::NinetyPlus);
    $receivable->forceFill([
        'last_reminder_bracket' => AccountsReceivableAgingBracket::SixteenToThirty,
        'last_reminder_sent_at' => now()->subDays(60),
    ])->save();
    $staff = User::factory()->accountingStaff()->create();

    emailCustomer($receivable, $staff)->assertInertiaFlash('toast.type', 'success');

    $receivable->refresh();
    expect($receivable->customer_emailed_at->equalTo(now()))->toBeTrue()
        ->and($receivable->last_reminder_bracket)->toBe(AccountsReceivableAgingBracket::SixteenToThirty)
        ->and($receivable->last_reminder_sent_at->equalTo(now()->subDays(60)))->toBeTrue();

    $entry = AuditLog::query()->latest('id')->first();
    expect($entry->user_id)->toBe($staff->id)
        ->and($entry->new_values)->toHaveKey('customer_emailed_at');
});

test('an entry with nothing to chase is refused', function (Closure $receivable) {
    Mail::fake();

    emailCustomer($receivable())->assertStatus(422);

    Mail::assertNothingSent();
})->with([
    'credit not approved yet' => [fn () => AccountsReceivable::factory()->for(JobOrder::factory()->create(['total_amount' => 1500]))->create()],
    'credit rejected' => [fn () => AccountsReceivable::factory()->rejected()->for(JobOrder::factory()->create(['total_amount' => 1500]))->create()],
    'paid' => [fn () => tap(owedReceivable(AccountsReceivableAgingBracket::NinetyPlus), fn ($r) => $r->forceFill(['collection_status' => AccountsReceivableCollectionStatus::Paid])->save())],
    'written off' => [fn () => tap(owedReceivable(AccountsReceivableAgingBracket::NinetyPlus), fn ($r) => $r->forceFill(['collection_status' => AccountsReceivableCollectionStatus::WrittenOff])->save())],
    'cancelled' => [fn () => tap(owedReceivable(AccountsReceivableAgingBracket::NinetyPlus), fn ($r) => $r->forceFill(['collection_status' => AccountsReceivableCollectionStatus::Cancelled])->save())],
    'settled by a payment' => [fn () => tap(owedReceivable(AccountsReceivableAgingBracket::NinetyPlus), fn ($r) => Transaction::factory()->for($r->jobOrder)->create(['amount' => 1500]))],
    'unpriced' => [fn () => tap(owedReceivable(AccountsReceivableAgingBracket::NinetyPlus), fn ($r) => $r->jobOrder->forceFill(['total_amount' => null])->save())],
]);

test('a second send within a minute is refused, and allowed after it', function () {
    Mail::fake();
    $receivable = owedReceivable(AccountsReceivableAgingBracket::NinetyPlus);

    emailCustomer($receivable);
    emailCustomer($receivable)->assertInertiaFlash('toast.type', 'error');
    Mail::assertSent(CustomerBalanceReminder::class, 1);

    $this->travel(61)->seconds();
    emailCustomer($receivable)->assertInertiaFlash('toast.type', 'success');
    Mail::assertSent(CustomerBalanceReminder::class, 2);
});

test('a failed send says so, records nothing and can be retried at once', function () {
    $receivable = owedReceivable(AccountsReceivableAgingBracket::NinetyPlus);

    config(['mail.default' => 'not-a-mailer']);
    emailCustomer($receivable)->assertInertiaFlash('toast.type', 'error');
    expect($receivable->fresh()->customer_emailed_at)->toBeNull();

    Mail::fake();
    emailCustomer($receivable)->assertInertiaFlash('toast.type', 'success');
    Mail::assertSent(CustomerBalanceReminder::class, 1);
});

test('only accounting staff may email a customer', function (string $role) {
    Mail::fake();

    emailCustomer(owedReceivable(AccountsReceivableAgingBracket::NinetyPlus), User::factory()->{$role}()->create())
        ->assertForbidden();

    Mail::assertNothingSent();
})->with(['admin', 'cashier', 'frontlineStaff', 'artist', 'productionStaff']);
