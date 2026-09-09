---
phase: 07-accounts-receivable
reviewed: 2026-09-09T00:00:00Z
depth: standard
files_reviewed: 56
files_reviewed_list:
  - app/Actions/POS/ConfirmPaymentIntent.php
  - app/Concerns/AccountsReceivableValidationRules.php
  - app/Console/Commands/SendAccountsReceivableReminders.php
  - app/Enums/AccountsReceivableAgingBracket.php
  - app/Enums/AccountsReceivableCollectionStatus.php
  - app/Enums/PaymentStatus.php
  - app/Http/Controllers/AccountingStaff/AccountsReceivableController.php
  - app/Http/Controllers/AccountingStaff/CollectionLetterController.php
  - app/Http/Controllers/AccountingStaff/CollectionStatusController.php
  - app/Http/Controllers/AccountingStaff/WriteOffRequestController.php
  - app/Http/Controllers/Cashier/CancellationController.php
  - app/Http/Controllers/Cashier/CreditRequestController.php
  - app/Http/Controllers/Cashier/PaymentController.php
  - app/Http/Controllers/Cashier/ReceiptController.php
  - app/Http/Controllers/Owner/CreditApprovalController.php
  - app/Http/Controllers/Owner/WriteOffApprovalController.php
  - app/Http/Requests/AccountingStaff/RequestWriteOffRequest.php
  - app/Http/Requests/AccountingStaff/UpdateCollectionStatusRequest.php
  - app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php
  - app/Http/Requests/Owner/ApproveWriteOffRequest.php
  - app/Http/Requests/Owner/RejectWriteOffRequest.php
  - app/Mail/AccountsReceivableReminder.php
  - app/Models/AccountsReceivable.php
  - app/Models/JobOrder.php
  - app/Policies/AccountsReceivablePolicy.php
  - database/factories/AccountsReceivableFactory.php
  - database/migrations/2026_09_08_090000_add_aging_and_collection_columns_to_accounts_receivable_table.php
  - database/seeders/SystemConfigurationSeeder.php
  - resources/js/config/nav/accounting-staff.ts
  - resources/js/config/nav/owner.ts
  - resources/js/pages/accounting-staff/AccountsReceivable/Index.vue
  - resources/js/pages/accounting-staff/AccountsReceivable/Show.vue
  - resources/js/pages/accounting-staff/CollectionLetter.vue
  - resources/js/pages/cashier/Dashboard.vue
  - resources/js/pages/frontline-staff/Dashboard.vue
  - resources/js/pages/owner/WriteOffRequests.vue
  - resources/views/mail/accounts-receivable-reminder.blade.php
  - routes/console.php
  - routes/owner.php
  - routes/portals.php
  - tests/Feature/AccountingStaff/AccountsReceivableListTest.php
  - tests/Feature/AccountingStaff/CollectionLetterTest.php
  - tests/Feature/AccountingStaff/CollectionStatusTest.php
  - tests/Feature/AccountingStaff/WriteOffRequestTest.php
  - tests/Feature/AccountsReceivable/AgingBracketTest.php
  - tests/Feature/Cashier/CancellationFeeTest.php
  - tests/Feature/Cashier/CashierPagesTest.php
  - tests/Feature/Cashier/CreditRequestTest.php
  - tests/Feature/Cashier/RecordPaymentTest.php
  - tests/Feature/Console/SendAccountsReceivableRemindersTest.php
  - tests/Feature/Owner/CreditApprovalTest.php
  - tests/Feature/Owner/WriteOffApprovalTest.php
  - tests/Unit/Actions/ConfirmPaymentIntentTest.php
  - tests/Unit/Mail/AccountsReceivableReminderMailableTest.php
  - tests/Unit/Models/JobOrderTest.php
  - tests/Unit/SystemConfigurationTest.php
findings:
  critical: 2
  warning: 2
  info: 2
  total: 6
status: issues_found
---

# Phase 07: Code Review Report

**Reviewed:** 2026-09-09T00:00:00Z
**Depth:** standard
**Files Reviewed:** 56
**Status:** issues_found

## Summary

Reviewed the full AR-01..AR-04 file set (aging list, reminder command, collection
status/letters, write-off approval) plus the 07-08 gap-closure round that
centralized `JobOrder::outstandingBalance()` and added `payment_status`
terminal-state guards. The centralization itself, and the guards added to
`ConfirmPaymentIntent`, `CreditRequestController::store`,
`CreditApprovalController::approve/reject`, and `WriteOffApprovalController::
approve/reject`, are correctly implemented: each of those six mutators takes a
locked re-read of both the `AccountsReceivable` row and the `JobOrder` row
inside its `DB::transaction()` and re-verifies state before writing, closing
the TOCTOU window a plain unlocked read would leave open. Test coverage for
those six paths (including the two explicit races simulated in
`WriteOffApprovalTest.php`) is genuine and well-targeted.

However, the "guard every payment_status write path" effort was not actually
exhaustive: `PaymentController::store` — the highest-traffic payment-status
mutator in the whole system — never takes a lock or re-verifies state inside
its own `DB::transaction()`, so it can silently reopen a written-off job order
or double-book a payment under concurrent requests (CR-01, below). Separately,
centralizing the derived balance into `JobOrder::outstandingBalance()` (D-16)
carried forward a pre-existing flaw — it does not exclude
`TransactionType::CancellationFee` transactions from the sum — into several
brand-new AR surfaces that didn't exist before this phase (aging list,
reminder emails, collection letters, write-off/credit approval balance
checks), and `CancellationController` does not block cancelling an On-Credit
job order, so the two combine into a real under-statement of what a customer
still owes (CR-02, below). Two further consistency gaps (write-off request
locking, cancellation-fee double-submit) round out the findings.

## Critical Issues

### CR-01: PaymentController's payment_status writes are the one mutator in this phase never guarded by a locked re-read

**File:** `app/Http/Controllers/Cashier/PaymentController.php:94-184` (cash/bank branch) and `:202-307` (`storePaymongoIntent`)

**Issue:** Every other AR-adjacent `payment_status` mutator added or touched in
the 07-08 round takes a locked re-read of the job order *inside* its
`DB::transaction()` and re-verifies the terminal-state guards there
(`ConfirmPaymentIntent::__invoke` lines 32-48, `CreditRequestController::
store` lines 49-64, `CreditApprovalController::approve` lines 55-70,
`WriteOffApprovalController::approve` lines 92-106). `PaymentController::
store` does not: the `abort_if($jobOrder->payment_status === WrittenOff/Paid/
CreditPendingApproval)` checks (lines 96-111) run once, before
`DB::transaction()`, against the unlocked route-model-bound `$jobOrder`. The
closures at lines 119 and 276 then reuse that same in-memory object — never
re-querying it under `lockForUpdate()` — before writing a new `payment_status`
and creating a `Transaction`.

Concretely reachable races:
- A `WriteOffApprovalController::approve()` call and a `PaymentController::
  store()` call for the same job order can interleave so that the write-off
  approval's `payment_status = WrittenOff` write is immediately overwritten by
  the payment controller's `payment_status = Paid/PartiallyPaid` write,
  silently reopening a booked loss — the exact scenario the phase's own
  `ConfirmPaymentIntent` guard and test (`tests/Unit/Actions/
  ConfirmPaymentIntentTest.php:74-86`, "does not revert the terminal state")
  were written to prevent, but which does not exist for this controller.
- Two concurrent submissions of the same Cash/Bank payment (double-click,
  slow-network retry) both read `payment_status = Unpaid` before either
  commits, and both create a `Completed` `Transaction` row — double-booking
  the payment and driving `payment_status` past `Paid` incorrectly.

**Fix:** Mirror `CreditRequestController::store`'s pattern — re-fetch the job
order under `lockForUpdate()` as the first statement inside each
`DB::transaction()` closure, and repeat the terminal-state `abort_if()` checks
against that locked instance before writing:

```php
$result = DB::transaction(function () use ($request, $jobOrder): array {
    $jobOrder = JobOrder::query()->whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();

    abort_if($jobOrder->payment_status === PaymentStatus::Paid, 422, 'This job order is already fully paid.');
    abort_if($jobOrder->payment_status === PaymentStatus::WrittenOff, 422, __('This job order has been written off and cannot accept further payments.'));
    abort_if($jobOrder->payment_status === PaymentStatus::CreditPendingApproval, 422, __('This job order has an On-Credit request awaiting Owner approval. Resolve it before recording a payment.'));

    // ... existing body, unchanged ...
});
```

Apply the same re-fetch-and-re-check at the top of `storePaymongoIntent`'s
`DB::transaction()` closure (line 276) before it writes
`payment_status = PendingConfirmation`.

### CR-02: `JobOrder::outstandingBalance()` commingles cancellation fees with the print-job debt, understating what an On-Credit customer still owes

**File:** `app/Models/JobOrder.php:162-169`; consumed by `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php:144`, `app/Http/Controllers/AccountingStaff/CollectionLetterController.php:40`, `app/Http/Controllers/Owner/WriteOffApprovalController.php:39,104`, `app/Console/Commands/SendAccountsReceivableReminders.php:56`, `app/Mail/AccountsReceivableReminder.php:117`

**Issue:** `outstandingBalance()` sums *every* `Completed` transaction
regardless of `type` — it does not exclude `TransactionType::CancellationFee`
(`app/Enums/TransactionType.php:10`; confirmed no call site anywhere in `app/`
filters transactions by type before summing). Separately,
`CancellationController::store` (`app/Http/Controllers/Cashier/
CancellationController.php:30-49`) blocks cancellation only for `Paid`,
`PendingConfirmation`, and `WrittenOff` — it does **not** block cancelling a
job order whose `payment_status` is `OnCredit` (i.e. one with an Active,
aging `AccountsReceivable` row). `CreditRequestController::store`'s own
`abort_unless` status allowlist (`ReadyForProduction`, `DesignApproved`,
`ForProduction`, `Printing`, `QualityCheck`, `ReadyForPickup`) overlaps
`CancellationController`'s `$designStarted` set, so an On-Credit job order
that still has design-started work can legitimately reach cancellation.

When that happens and the existing down payment doesn't already cover the
configured cancellation fee, `CancellationController::store` creates a brand
new `Completed` `Transaction` of type `CancellationFee` (lines 58-68). That
transaction is now indistinguishable, to `outstandingBalance()`, from a real
payment toward the job's `total_amount` — so every AR surface built on top of
it (the aging list's "Outstanding" column, the printed collection letter's
"Amount Due", the reminder email's "Outstanding Balance", and the
`abort_if($outstandingBalance <= 0.0, ...)` settlement guards in both
`WriteOffApprovalController::approve()` and `CreditApprovalController::
approve()`) will report a lower balance than the customer actually owes on
their credit account by exactly the cancellation fee amount — potentially
even flipping `collection_status` to `Paid` via `SendAccountsReceivableReminders::processOne()` (line 58) or silently blocking a legitimate
write-off/credit approval, even though no money was actually applied toward
the print-job debt.

This directly contradicts the invariant `cashier/Dashboard.vue` documents and
relies on for its own UI copy (lines 103-113 of that file): *"cancelling
never writes off an existing On-Credit balance — the fee-netting logic above
only ever looks at completed Transactions, so an Active AccountsReceivable is
untouched by this action."* The Vue-side netting logic is indeed scoped
correctly (it only reads `amount_paid`/`accounts_receivable.balance`
separately), but the server-side `outstandingBalance()` that the AR pages
actually render from is not scoped the same way, so the invariant is broken
one layer down.

**Fix:** Exclude `CancellationFee` (and any other non-debt transaction types)
from the sum in `outstandingBalance()`:

```php
public function outstandingBalance(): float
{
    $debtTypes = [TransactionType::DownPayment->value, TransactionType::FullPayment->value];

    $amountPaid = (float) ($this->relationLoaded('transactions')
        ? $this->transactions->whereIn('type', $debtTypes)->where('status', TransactionStatus::Completed->value)->sum('amount')
        : $this->transactions()->whereIn('type', $debtTypes)->where('status', TransactionStatus::Completed->value)->sum('amount'));

    return $this->total_amount !== null ? round((float) $this->total_amount - $amountPaid, 2) : 0.0;
}
```

or, if `CancellationController` is meant to block On-Credit cancellations
entirely instead, add `abort_if($jobOrder->payment_status === PaymentStatus::OnCredit, ...)` there and require the AR to be resolved first.

## Warnings

### WR-01: WriteOffRequestController::store has no transaction or locked re-read, unlike every sibling AR mutator in this phase

**File:** `app/Http/Controllers/AccountingStaff/WriteOffRequestController.php:25-45`

**Issue:** `CollectionStatusController::update` explicitly documents (lines
17-28) that its locked re-read "matches every other mutating AR controller's
locked-re-read boundary in this phase (CR-04)." `WriteOffRequestController::
store` — a sibling mutating AR controller in the same phase, gated by the
same route group — does not follow that pattern: it reads `$accountsReceivable`
via unlocked route-model binding, checks `status`/`collection_status`/
`write_off_requested_at` against that unlocked read, and writes directly with
no `DB::transaction()` wrapper at all. Two concurrent write-off request
submissions for the same entry (double-click, retried request) can both pass
the `abort_if($accountsReceivable->write_off_requested_at !== null, ...)`
guard and both `save()`, with the second silently overwriting the first's
`write_off_reason`/`write_off_requested_by`/`write_off_requested_at` — the
current DB state then attributes the write-off request to the wrong
Accounting Staff member with the wrong stated reason (recoverable only by
reading `audit_trail` directly, which the Owner-facing approval queue never
does).

**Fix:** Wrap the guard-and-write in `DB::transaction()` against a locked
re-read, matching `CollectionStatusController::update`:

```php
public function store(RequestWriteOffRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
{
    DB::transaction(function () use ($request, $accountsReceivable): void {
        $accountsReceivable = AccountsReceivable::query()->whereKey($accountsReceivable->id)->lockForUpdate()->firstOrFail();

        abort_unless($accountsReceivable->status === AccountsReceivableStatus::Active, 422, __('This receivable is not active.'));
        abort_if(in_array($accountsReceivable->collection_status, [...], true), 422, __('This entry is already closed and cannot be written off.'));
        abort_if($accountsReceivable->write_off_requested_at !== null, 422, __('A write-off request is already pending for this entry.'));

        $accountsReceivable->forceFill([...])->save();
    });

    // ...
}
```

### WR-02: CancellationController::store has no locked re-read; a double-submitted cancel can double-charge the cancellation fee

**File:** `app/Http/Controllers/Cashier/CancellationController.php:30-87`

**Issue:** The `abort_if($jobOrder->cancelled_at !== null, ...)` guard (line
32) runs on an unlocked route-model-bound read, before `DB::transaction()`
(line 51). Inside the transaction, `$jobOrder` is never re-fetched under
`lockForUpdate()`, and `cancelled_at` is never re-checked. Two concurrent
cancel requests for the same job order (double-click on "Confirm
Cancellation," or a client retry after a slow response) can both pass the
initial `cancelled_at === null` check and both reach the `$designStarted`
branch, each creating its own `Completed` `CancellationFee` `Transaction` for
the same job order before either commits — over-charging the customer.

**Fix:** Add a locked re-read as the first statement of the `DB::transaction()`
closure and re-check `cancelled_at` against it, matching the pattern used in
`CreditRequestController::store`.

## Info

### IN-01: WriteOffApprovalController::approve()'s flash message reads a stale, non-eager-loaded variable

**File:** `app/Http/Controllers/Owner/WriteOffApprovalController.php:92-118`

**Issue:** The `DB::transaction()` closure at line 92 captures
`$accountsReceivable` by value (`use ($accountsReceivable)`) and reassigns a
locally-scoped variable of the same name to the locked re-read (line 93). That
reassignment does not propagate to the outer `$accountsReceivable` used at
line 115 (`$accountsReceivable->jobOrder->number`) — the flash message reads
`jobOrder` off the original, un-eager-loaded, pre-transaction instance,
triggering an extra lazy-loaded query outside the transaction. `number` is
immutable so this happens to render correctly today, but it's a fragile
pattern — a future field read here that *does* change inside the transaction
(e.g. `balance`) would silently show stale data.

**Fix:** Capture the needed display value (e.g. `$jobOrder->number`) from
inside the transaction closure and pass it out via a `use (&$jobOrderNumber)`
reference or return value, rather than reading through the outer
`$accountsReceivable`.

### IN-02: Unreachable `default` branches in AccountsReceivableReminder's copy methods

**File:** `app/Mail/AccountsReceivableReminder.php:76-97`

**Issue:** `subjectFor()` and `leadFor()` both have a `default => ...` arm in
their `match($this->bracket)` expressions, but `$bracket` is only ever
constructed from `AccountsReceivableAgingBracket::reminderBearing()`
(`OneToFifteen`, `SixteenToThirty`, `ThirtyOneToSixty`, `NinetyPlus`) —
`SendAccountsReceivableReminders::processOne()` never instantiates this
Mailable for `Current` or `SixtyOneToNinety`. The `default` arms are dead
code that will never execute in production.

**Fix:** Either make the `match` exhaustive over the 4 real cases (dropping
`default`) so a future 5th bracket triggers a loud `UnhandledMatchError`
instead of silently reusing the OneToFifteen-style copy, or leave a comment
noting the `default` is defensive-only or safe to remove.

---

_Reviewed: 2026-09-09T00:00:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
