---
phase: 07-accounts-receivable
reviewed: 2026-09-08T16:54:12Z
depth: standard
files_reviewed: 47
files_reviewed_list:
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
  - app/Http/Controllers/Cashier/PaymentController.php
  - app/Http/Controllers/Owner/CreditApprovalController.php
  - app/Http/Controllers/Owner/WriteOffApprovalController.php
  - app/Http/Requests/AccountingStaff/RequestWriteOffRequest.php
  - app/Http/Requests/AccountingStaff/UpdateCollectionStatusRequest.php
  - app/Http/Requests/Owner/ApproveWriteOffRequest.php
  - app/Http/Requests/Owner/RejectWriteOffRequest.php
  - app/Mail/AccountsReceivableReminder.php
  - app/Models/AccountsReceivable.php
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
  - tests/Feature/Cashier/RecordPaymentTest.php
  - tests/Feature/Console/SendAccountsReceivableRemindersTest.php
  - tests/Feature/Owner/CreditApprovalTest.php
  - tests/Feature/Owner/WriteOffApprovalTest.php
  - tests/Unit/Mail/AccountsReceivableReminderMailableTest.php
  - tests/Unit/SystemConfigurationTest.php
findings:
  critical: 2
  warning: 12
  info: 11
  total: 25
status: issues_found
---

# Phase 7: Code Review Report (re-review after gap-closure 07-06)

**Reviewed:** 2026-09-08T16:54:12Z
**Depth:** standard
**Files Reviewed:** 47
**Status:** issues_found

## Summary

**Prior findings verified closed.** All three blockers from the previous review are genuinely fixed in the current tree, with tests:

- **CR-01 (reject erases an approved write-off)** — `WriteOffApprovalController::reject()` now aborts 422 when `collection_status === WrittenOff`, inside the locked re-read (`WriteOffApprovalController.php:128-132`). Covered by `WriteOffApprovalTest.php:117-133`.
- **CR-02 (approved write-offs stay queued forever)** — `approve()` nulls `write_off_requested_at` in the same write (`:102`) and `index()` additionally excludes `paid`/`written_off` rows (`:29`). Covered by `WriteOffApprovalTest.php:102-115` and `:135-152`.
- **CR-03 (written-off order can be cancelled or paid)** — `CancellationController.php:39` and `PaymentController.php:108` both abort 422 for `WrittenOff`, and `cashier/Dashboard.vue:125-130` hides the Cancel action. Covered by `CancellationFeeTest.php:147-157` and `RecordPaymentTest.php:245-259`.

**Two new blockers remain in the same fault line, both about the write-off's terminality.**

First, the CR-02 fix made the Owner's approve path depend entirely on a *denormalized* settlement flag. `approve()` re-checks `collection_status`, but nothing sets `collection_status = paid` at payment time — only the nightly `ar:send-reminders` command does (`SendAccountsReceivableReminders.php:65-69`). A Cashier taking payment on an On-Credit balance in the morning leaves the AR row reading `pending`; the Owner approving the still-queued write-off an hour later marks a **fully paid** job order `written_off` and books the settled amount as a loss. The existing "settled while pending" test fakes settlement by hand-setting `collection_status`, so it asserts the flag, not the money.

Second, the CR-03 fix hardened two of the three writers of `payment_status` but missed the third: `CreditRequestController::store` (`:42-47`) has no `WrittenOff` guard. A written-off job order can therefore be put back on credit — flipping `payment_status` from `written_off` to `credit_pending_approval` and creating a *second* `AccountsReceivable` row for the same job order. `PaymentController::edit` also has no terminal guard, so the page hosting that form renders for a written-off order.

Everything else from the prior review that was not in 07-06's scope is unchanged and re-listed below: the `last_reminder_sent_at` column-allowlist drop, the lossy mail-failure stamp, the missing empty-recipient guard, the `null total_amount → Paid` close, the unguarded collection letter, the non-atomic write-off request, and the five-way duplicated balance derivation. Two further orphaned-state defects introduced by the shape of the CR-02 fix are new (WR-07, WR-08).

## Structural Findings (fallow)

_No structural pre-pass payload was supplied for this review._

## Narrative Findings (AI reviewer)

## Critical Issues

### CR-01: Approving a write-off can book a fully paid job order as a loss

**File:** `app/Http/Controllers/Owner/WriteOffApprovalController.php:93-104`
**Issue:** The only settlement guard inside the locked transaction is

```php
abort_if(in_array($accountsReceivable->collection_status, [Paid, WrittenOff], true), 422, ...);
```

`collection_status` is a denormalized flag. Nothing in the payment path writes it: `PaymentController::store` (`:158-162`) sets `payment_status` and creates the `Transaction` but never touches the AR row; `ConfirmPaymentIntent` (`app/Actions/POS/ConfirmPaymentIntent.php:43-47`) likewise. The **only** writer of `collection_status = paid` is `SendAccountsReceivableReminders::processOne()` (`:65-69`), scheduled `->daily()` (`routes/console.php:12`).

So there is a window of up to 24 hours in which the derived outstanding balance is `0.00` while `collection_status` is still `pending`/`follow_up`/`collections`. In that window:

1. `WriteOffApprovalController::index()` still lists the entry (its filter is the same stale flag) — visibly with `balance => 0.0`, since `index()` *does* derive the balance correctly at `:39-42`.
2. `approve()` passes both guards, sets `collection_status = written_off`, and overwrites the job order's `payment_status` from `paid` to `written_off` (`:104`).

The result is a settled sale reported as a bad-debt loss, and a job order whose `payment_status` contradicts its own completed transactions. It is not recoverable through the UI: `CollectionStatusController::update` and `WriteOffRequestController::store` both refuse to touch a `written_off` entry, and `reject()` now aborts on `written_off` too (the CR-01 fix).

Reachable via `POST /cashier/job-orders/{jobOrder}/payment` on an `on_credit` job order (the endpoint accepts it — `PaymentController.php:95-108` only excludes `Paid`/`WrittenOff`), or via a PayMongo webhook confirming a pending intent.

`WriteOffApprovalTest.php:135-152` looks like it covers this, but it simulates settlement with `forceFill(['collection_status' => Paid])` — it asserts the flag is honored, never that a real payment is.

**Fix:** Guard on the derived balance — the same value `index()` already computes and displays — inside the locked transaction:

```php
$accountsReceivable = AccountsReceivable::query()->whereKey($accountsReceivable->id)
    ->with('jobOrder.transactions:id,job_order_id,amount,status')
    ->lockForUpdate()->firstOrFail();

abort_if($accountsReceivable->write_off_requested_at === null, 422, __('No write-off request is pending for this entry.'));
abort_if(
    in_array($accountsReceivable->collection_status, [AccountsReceivableCollectionStatus::Paid, AccountsReceivableCollectionStatus::WrittenOff], true),
    422,
    __('This entry was settled or closed before the write-off could be approved.'),
);
abort_if(
    $accountsReceivable->outstandingBalance() <= 0.0, // see WR-10 — one shared derivation
    422,
    __('This balance has already been settled in full and cannot be written off.'),
);
```

Add a test that records a real payment (`cashier.job-orders.payment.store`) against an `on_credit` job order with a pending write-off, then asserts the Owner's approve returns 422 and `payment_status` stays `paid`. See also WR-09: closing the AR entry at payment time removes the window entirely.

### CR-02: A written-off job order can be put back on credit, reversing the write-off

**File:** `app/Http/Controllers/Cashier/CreditRequestController.php:42-47` (enabled by `app/Http/Controllers/Cashier/PaymentController.php:35-49`; root cause is the `PaymentStatus::WrittenOff` case added at `app/Enums/PaymentStatus.php:14`)
**Issue:** Plan 07-06 taught `CancellationController` and `PaymentController::store` that `WrittenOff` is terminal, but `CreditRequestController::store` — the third writer of `payment_status` — was not updated. Its guards are:

```php
abort_if($jobOrder->payment_status === PaymentStatus::Paid, 422, ...);
abort_if(in_array($jobOrder->payment_status, [PendingConfirmation, CreditPendingApproval], true), 422, ...);
```

`WrittenOff` passes. The locked re-read at `:55-63` repeats the same two guards, so it does not catch it either. Consequences of one POST:

- `payment_status` flips `written_off → credit_pending_approval` (`:104`), undoing the state the Owner confirmed as "This can't be undone" (`owner/WriteOffRequests.vue:168`).
- A **second** `AccountsReceivable` row is created for the same job order (`:97-102`) while the first is still `collection_status = written_off`. Both then appear in the Accounting aging list — the old one under Closed, the new one open — for the same money.
- If the Owner approves, `payment_status` becomes `on_credit` and the loss is silently reinstated as a live receivable, with a fresh `due_at` and a fresh aging clock.

Reachable from the UI: `PaymentController::edit` (`:35-49`) checks `cancelled_at` and `status` only — never `payment_status` — so `GET /cashier/job-orders/{jobOrder}/payment` renders the full pricing/payment page for a written-off order, including the On-Credit dialog wired to `CreditRequestController.store` (`cashier/JobOrderPayment.vue:242,676`). The dashboard has no link there, but the URL is a plain GET (bookmarkable, in browser history, shareable).

**Fix:** Add the terminal guard to both, matching the shape 07-06 already used:

```php
// CreditRequestController::store — and repeat inside the locked re-read
abort_if(
    $jobOrder->payment_status === PaymentStatus::WrittenOff,
    422,
    __('This job order has been written off and cannot be put back on credit.'),
);

// PaymentController::edit
abort_if(
    $jobOrder->payment_status === PaymentStatus::WrittenOff,
    422,
    __('This job order has been written off.'),
);
```

Better still, add a single `PaymentStatus::isTerminal(): bool` (`Paid`, `WrittenOff`) and route every guard through it, so the next status added cannot be missed in one of four places. Add tests: "a written-off job order cannot be put on credit" (asserting `AccountsReceivable::count()` is unchanged) and "the payment page 422s for a written-off job order".

## Warnings

### WR-01: `index()` drops `last_reminder_sent_at` from the row shape it declares

**File:** `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php:60` vs `:171`
**Issue:** The `get([...])` column allowlist omits `last_reminder_sent_at`, but `deriveRow()` reads `$accountsReceivable->last_reminder_sent_at` (`:171`) and the declared `AccountsReceivableRow` shape promises it (`:27`). Eloquent returns `null` for an unselected attribute instead of failing, so every list row silently reports "never reminded". `Index.vue` does not render it today, so the bug is latent — but any future use in the list is silently wrong, and `Model::preventAccessingMissingAttributes()` would turn it into a hard `MissingAttributeException`. Unchanged since the previous review.
**Fix:** Add `'last_reminder_sent_at'` to the `get()` list (keeping it in sync with `deriveRow()`), or move the field out of `deriveRow()` into `show()` the way `approved_at` is handled.

### WR-02: A failed reminder email is stamped as sent and never retried

**File:** `app/Console/Commands/SendAccountsReceivableReminders.php:84-90`
**Issue:** `catch (\Throwable $e) { report($e); }` is followed unconditionally by the bracket stamp. Because the next run only sends when `bracket->rank() > lastBracket->rank()` (`:78`), one transport hiccup means that bracket's escalation is *never* sent — the 90+ "write-off decision needed" notice can be lost with nothing but a log line. `AccountsReceivableReminder` already uses `Queueable`, but the command sends synchronously, bypassing the queue's retry/backoff. Unchanged since the previous review.
**Fix:** Queue the mail so failures retry, and stamp only after a successful hand-off:

```php
try {
    Mail::to($recipients)->queue(new AccountsReceivableReminder($receivable, $bracket));
} catch (\Throwable $e) {
    report($e);

    return; // leave the stamp untouched so the next run retries
}

$receivable->forceFill(['last_reminder_bracket' => $bracket->value, 'last_reminder_sent_at' => now()])->save();
```

`SendAccountsReceivableRemindersTest.php:111-122` currently locks in the lossy behavior and must be rewritten alongside.

### WR-03: No guard for an empty recipient list; the recipient query runs once per receivable

**File:** `app/Console/Commands/SendAccountsReceivableReminders.php:85,99-105`
**Issue:** `reminderRecipients()` is called inside `processOne()`, re-running the same `users` query for every row. If no active Accounting Staff or Owner exists (all deactivated via `is_active`), the collection is empty and Symfony's mailer throws "An email must have a To..., Cc or Bcc header" — swallowed by the `catch` while WR-02 still advances the stamp. Every reminder is then permanently lost with no operator-visible signal beyond `report()`. Unchanged since the previous review.
**Fix:** Resolve recipients once in `handle()` and bail out loudly when empty:

```php
$recipients = $this->reminderRecipients();

if ($recipients->isEmpty()) {
    $this->error('No active Accounting Staff or Owner to notify — no reminders sent.');

    return self::FAILURE;
}
```

### WR-04: A null `total_amount` closes a live receivable as Paid

**File:** `app/Console/Commands/SendAccountsReceivableReminders.php:61-69`
**Issue:** When `jobOrder->total_amount` is `null`, `$balance` falls back to `0.0` and the next branch (`$balance <= 0`) writes the terminal `collection_status = paid`. `Paid` is unreachable from the UI afterwards (`CollectionStatusController::update` aborts 422), so an unpriced job order permanently closes a real receivable with no route back short of a manual DB edit. The same `null → 0.0` fallback in `AccountsReceivableController::deriveRow()` (`:146-148`) renders such a row as "Settled" in the list (`Index.vue:298-301`). Unchanged since the previous review.
**Fix:** Do not conflate "unpriced" with "settled":

```php
if ($receivable->jobOrder->total_amount === null) {
    report(new RuntimeException("AR {$receivable->id} has no job order total; skipping."));

    return;
}
```

and surface an explicit "Total not set" state rather than `0.0` in `deriveRow()`.

### WR-05: Collection letter renders for already Paid / Written Off entries

**File:** `app/Http/Controllers/AccountingStaff/CollectionLetterController.php:24`
**Issue:** The only guard is `status === AccountsReceivableStatus::Active`; `collection_status` is not checked, and a `paid` or `written_off` entry is still `Active`. `GET /accounting-staff/accounts-receivable/{id}/collection-letter` therefore renders a full dunning letter ("Continued non-payment will affect your eligibility…", "may be written off as a loss") for a customer who has already settled or whose debt was forgiven. `Show.vue:318` hides the button when terminal, but the route is a plain GET and directly reachable. Unchanged since the previous review.
**Fix:**

```php
abort_if(
    in_array($accountsReceivable->collection_status, [
        AccountsReceivableCollectionStatus::Paid,
        AccountsReceivableCollectionStatus::WrittenOff,
    ], true),
    404,
);
```

### WR-06: Write-off request guard is not atomic

**File:** `app/Http/Controllers/AccountingStaff/WriteOffRequestController.php:27-39`
**Issue:** The three preconditions and the subsequent write are unsynchronized statements with no `lockForUpdate()` and no transaction — unlike the approve/reject paths, which were deliberately hardened with exactly that (`WriteOffApprovalController.php:90-91,124-125`). Two concurrent submissions (double click, retried request) both pass the guard; the later write overwrites the first requester's reason and timestamp, and two `audit_trail` rows are produced for one logical request. `WriteOffRequestTest.php:40-57` only covers the sequential case. Unchanged since the previous review.
**Fix:** Mirror the approval path — wrap in `DB::transaction()` with a `lockForUpdate()` re-read before the three guards.

### WR-07: Approving a write-off erases the request timestamp and hides the reason

**File:** `app/Http/Controllers/Owner/WriteOffApprovalController.php:100-104`, `resources/js/pages/accounting-staff/AccountsReceivable/Show.vue:159,191-198`
**Issue:** The CR-02 fix chose to null `write_off_requested_at` on approval and overload that column as the "still pending" flag. Two consequences on the most consequential financial action in the phase:

1. The requested-at timestamp is destroyed on the row. It survives only in `audit_trail.old_values`, which is not surfaced anywhere in the AR UI.
2. `Show.vue` gates the entire write-off panel on `hasPendingWriteOff` (`write_off_requested_at !== null`). Once approved, that is false, so `write_off_reason` — still stored on the row and still shipped in the payload (`AccountsReceivableController.php:169`) — is rendered nowhere. Accounting Staff looking at a written-off entry sees a "Written Off" badge, a "closed by the system" note, and no reason, no requester, no approver.

There is still no record of *who* approved the loss: `approved_by`/`approved_at` belong to the credit decision and continue to point at the original credit approver, so a reader cannot distinguish "credit approved by X" from "loss authorized by X".
**Fix:** Stop using a data column as a state flag. Add `write_off_approved_by` / `write_off_approved_at`, keep `write_off_requested_at` intact on approval, and let `index()`'s `collection_status` filter (already in place at `:29`) be the sole queue gate. Then key `Show.vue`'s pending alert on `collection_status !== 'written_off' && write_off_requested_at !== null`, and add a separate "Written off on {date}, approved by {name}, reason: …" panel for the terminal state.

### WR-08: A settled entry with a pending write-off request is stuck showing "awaiting Owner approval" forever

**File:** `app/Console/Commands/SendAccountsReceivableReminders.php:65-69`, `resources/js/pages/accounting-staff/AccountsReceivable/Show.vue:159,191-198`, `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue:327-330`
**Issue:** `processOne()` sets `collection_status = paid` without clearing the write-off columns. `WriteOffApprovalController::index()` then excludes the row (CR-02's fix), so the Owner can never approve or reject it — but `write_off_requested_at` stays populated forever. `Show.vue` renders "…is awaiting Owner approval. Reminder emails continue until it's approved." on an entry that is settled and closed, and `Index.vue` renders a "Write-Off Pending" badge next to a "Paid" badge. Both terminal paths (`CollectionStatusController`, `WriteOffRequestController`) refuse to touch a closed entry, so there is no way to clear the state.
**Fix:** Clear the request when the entry settles:

```php
if ($balance <= 0) {
    $receivable->forceFill([
        'collection_status' => AccountsReceivableCollectionStatus::Paid->value,
        'write_off_requested_at' => null,
    ])->save();

    return;
}
```

(If WR-07 is adopted, gate the Show/Index badges on `collection_status` instead, which fixes both at once.) Add a test asserting the pending badge disappears after an entry settles.

### WR-09: Nothing closes the AR entry at payment time — settlement is reconciled only by a daily cron

**File:** `app/Http/Controllers/Cashier/PaymentController.php:158-162`, `app/Actions/POS/ConfirmPaymentIntent.php:43-47`, `app/Console/Commands/SendAccountsReceivableReminders.php:65-69`
**Issue:** The AR row's `collection_status` is the system's own record of whether a balance is still being chased, but no payment path updates it. Between a counter payment and the next nightly `ar:send-reminders` run, Accounting's aging list shows the entry in the *open* set with a live `Pending`/`Follow-up` badge and an Outstanding cell reading "Settled" — internally contradictory data — and the entry still counts (at ₱0.00) in a bracket bucket. This staleness is the mechanism behind CR-01; even after CR-01 is guarded, the stale display remains.
**Fix:** Recompute and close the AR entry in the same transaction that records a payment, e.g. a small `App\Actions\AR\SettleReceivableIfPaid` invoked from `PaymentController::store` and `ConfirmPaymentIntent`, leaving `ar:send-reminders` as the safety net rather than the primary writer.

### WR-10: The outstanding-balance derivation is copy-pasted in five places

**File:** `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php:145-148`, `app/Http/Controllers/AccountingStaff/CollectionLetterController.php:32-35`, `app/Http/Controllers/Owner/WriteOffApprovalController.php:39-42`, `app/Console/Commands/SendAccountsReceivableReminders.php:57-63`, `app/Mail/AccountsReceivableReminder.php:116-125`
**Issue:** The same "total_amount minus completed transactions, rounded to 2" computation — including the questionable `null → 0.0` fallback from WR-04 — is duplicated verbatim five times in this phase alone, plus `ReceiptController::show()`. Any future change (partial refunds, cancellation-fee transactions, void handling) must be found and applied in six places; missing one produces a silently wrong balance on a customer-facing collection letter or a reminder email. CR-01's fix needs a sixth call site, making the extraction more urgent, not less. Unchanged since the previous review.
**Fix:** Extract one accessor/action — `AccountsReceivable::outstandingBalance(): ?float` or `App\Actions\AR\DeriveOutstandingBalance` — operating on the already-eager-loaded relation, and call it from every site.

### WR-11: Reminder mail body is not covered by tests, only the subject line

**File:** `tests/Unit/Mail/AccountsReceivableReminderMailableTest.php:11-17`
**Issue:** The only assertion is that a `NinetyPlus` subject starts with "Final notice". The markdown view (`resources/views/mail/accounts-receivable-reminder.blade.php`) is never rendered in a test, so a missing `with()` key would surface as an undefined-variable error at send time — inside the `try/catch` of WR-02, which swallows it and stamps the bracket as delivered. `content()` passes eight variables, one of which (`$daysPastDue`) is legitimately `null` for a not-yet-due entry. Unchanged since the previous review.
**Fix:** Add a render assertion (`$mail->assertSeeInHtml(...)` or `$mail->render()`) for at least one reminder-bearing bracket, and assert each bracket's subject rather than only `NinetyPlus`.

### WR-12: The Cashier payment page renders for a written-off job order

**File:** `app/Http/Controllers/Cashier/PaymentController.php:35-49`
**Issue:** `edit()` guards `cancelled_at` and `status` but never `payment_status`. A written-off job order still sitting in a production status therefore renders the full Pricing + Payment page, with the Record Payment form (which 422s on submit — `:108`) and the On-Credit dialog (which does **not** — CR-02). Beyond enabling CR-02, this is a misleading affordance: the page shows a live remaining balance for an account already booked as a loss.
**Fix:** Add the same terminal guard used in `store()` to `edit()`, so the page 422s rather than rendering a dead form. See CR-02 for the shared-helper suggestion.

## Info

### IN-01: The "90+ Days" bucket does not include day 90

**File:** `app/Models/AccountsReceivable.php:128`, `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue:78`
**Issue:** `$daysPastDue <= 90 => SixtyOneToNinety` puts exactly 90 days past due in the "61–90 Days" bucket, so the bucket labelled "90+ Days" actually begins at day 91 (encoded in `AgingBracketTest.php:44-45`). The label and the behavior disagree for one day, and the 90+ escalation email fires a day later than the label implies.
**Fix:** Relabel to "91+ Days", or change the boundary to `$daysPastDue < 90` — whichever matches D-03's intent — and make the label and test agree explicitly.

### IN-02: An entry becomes "1–15 Days" and triggers a reminder at 0 days past due

**File:** `app/Models/AccountsReceivable.php:118-131`, `app/Http/Controllers/Owner/CreditApprovalController.php:61`
**Issue:** `due_at` is stamped as `now()->addDays($creditTermDays)` and so carries a time of day. The moment it passes, `isFuture()` is false and `(int) diffInDays()` is `0`, which falls into `OneToFifteen`. The reminder subject reads "… is 0 days past due" (`AccountsReceivableReminder.php:78`) and `Show.vue:250` displays "Days Past Due: 0".
**Fix:** Normalize `due_at` to end-of-day on approval, or floor `daysPastDue()` at 1 so the first reminder reads "1 day past due".

### IN-03: `CollectionLetter.vue` does not pass `navItems`

**File:** `resources/js/pages/accounting-staff/CollectionLetter.vue:22-26`
**Issue:** Every sibling page passes its portal's nav (`Index.vue:53`, `Show.vue:56`), but the letter page omits it, so `AppSidebarLayout` receives `undefined` and the Accounting Staff sidebar renders empty — no way back except the browser Back button.
**Fix:** `layout: { navItems: accountingStaffNavItems, breadcrumbs: [...] }`.

### IN-04: Only the print button is `print:hidden` on the collection letter

**File:** `resources/js/pages/accounting-staff/CollectionLetter.vue:91-97`
**Issue:** The page renders inside `AppLayout`, so the sidebar, header and breadcrumbs are included in the printed output; only the "Print Letter" button is hidden. A customer-facing letter should print as a clean document.
**Fix:** Add `print:hidden` to the layout chrome, or render this page without `AppLayout` (the resolver in `app.ts` supports a no-layout page).

### IN-05: `credit_extended` is sent to the aging list but never rendered

**File:** `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php:164`, `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue:31`
**Issue:** The field is in the payload and in the TS interface, but unused in the Index template (only `Show.vue:207` renders it). Dead prop. The same file also ships `write_off_reason` and `last_reminder_sent_at` to Index without a corresponding interface field or template use.
**Fix:** Drop the unused fields from the list payload/interface, or render `credit_extended` as its own column.

### IN-06: `v-for` combined with `v-else` on the same element

**File:** `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue:279`, `resources/js/pages/owner/WriteOffRequests.vue:102-106`
**Issue:** `<TableRow v-for="…" v-else :key="…">` relies on Vue 3's "v-if wins over v-for" precedence. It works, but it is the documented anti-pattern and reads as if the `v-else` applied per row.
**Fix:** Wrap the rows in a `<template v-else>` and put `v-for` on the inner `<TableRow>`.

### IN-07: Bracket/status label maps and formatting helpers are duplicated across four pages

**File:** `Index.vue:72-88,118-167`, `Show.vue:70-137`, `CollectionLetter.vue:28-33`, `owner/WriteOffRequests.vue:58-63`
**Issue:** `BRACKET_LABELS`, `COLLECTION_STATUS_LABELS`, `money()`, `agingBadgeProps()` and `collectionStatusBadgeProps()` are byte-identical copies across multiple SFCs. A single label change (e.g. IN-01's "90+ Days") must be made in several files.
**Fix:** Move them into a shared module (e.g. `resources/js/lib/accounts-receivable.ts`) and import.

### IN-08: The aging list is unpaginated

**File:** `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php:57-62`
**Issue:** Every Active receivable — open and closed — is loaded, mapped, sorted in PHP and shipped to the browser on each page load. There is no pagination or date window, so the payload grows without bound as written-off/paid history accumulates in `closedReceivables`. Noted as a scale/maintainability observation only; performance is out of scope for this review.
**Fix:** Paginate, or bound the closed set (e.g. last 90 days) behind a "view all" affordance.

### IN-09: Admin sees write-off action buttons that always 403

**File:** `resources/js/pages/owner/WriteOffRequests.vue:149-245`, `routes/owner.php:24-26`
**Issue:** The route group is `role:owner,admin`, so an Admin can open the queue, but `AccountsReceivablePolicy::approveWriteOff/rejectWriteOff` restrict the mutations to Owner. The page renders both buttons unconditionally, so an Admin gets a 403 error screen on click. This matches the pre-existing `owner/CreditRequests.vue` pattern, so it is consistency-preserving rather than a regression — and `WriteOffApprovalTest.php:26-39` asserts the 403.
**Fix:** Share `auth.user.role` (already available via `usePage()`) and gate the action column on `role === 'owner'` — in both pages together.

### IN-10: `WriteOffApprovalController::index()` does not filter on `status`

**File:** `app/Http/Controllers/Owner/WriteOffApprovalController.php:27-36`
**Issue:** The queue filters on `write_off_requested_at` and `collection_status` but not `status`, and does not select the `status` column. `WriteOffRequestController` only ever sets `write_off_requested_at` on an `Active` entry, so this is unreachable today — but the queue's own defense-in-depth story (documented at `:76-80`) is one predicate short of the one the previous review recommended.
**Fix:** Add `->where('status', AccountsReceivableStatus::Active->value)` to `index()`, and re-check `status` alongside the other guards inside `approve()`'s locked re-read.

### IN-11: A written-off job order gets an empty actions menu on the Cashier dashboard

**File:** `resources/js/pages/cashier/Dashboard.vue:377-431`
**Issue:** With the CR-03 fix, a `written_off` job order matches none of the `Process Payment` / `Check Payment Status` / `View Receipt` branches and `canCancelJobOrder()` is false, so the "⋯" trigger opens an empty `DropdownMenuContent`. Correct behavior, poor affordance.
**Fix:** Hide the trigger when no action applies, or render a disabled "No actions available" item.

---

_Reviewed: 2026-09-08T16:54:12Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
