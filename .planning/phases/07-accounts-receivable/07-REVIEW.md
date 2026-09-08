---
phase: 07-accounts-receivable
reviewed: 2026-09-08T00:21:01Z
depth: standard
files_reviewed: 44
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
  - tests/Feature/Console/SendAccountsReceivableRemindersTest.php
  - tests/Feature/Owner/CreditApprovalTest.php
  - tests/Feature/Owner/WriteOffApprovalTest.php
  - tests/Unit/Mail/AccountsReceivableReminderMailableTest.php
  - tests/Unit/SystemConfigurationTest.php
findings:
  critical: 3
  warning: 9
  info: 9
  total: 21
status: issues_found
---

# Phase 7: Code Review Report

**Reviewed:** 2026-09-08T00:21:01Z
**Depth:** standard
**Files Reviewed:** 44
**Status:** issues_found

## Summary

The AR slice is coherent and well-tested for the paths it anticipates: aging math has boundary tests, the reminder command is idempotent per bracket, the Owner-only policy narrowing is enforced and asserted, and balances are derived from completed transactions rather than the stored `balance` column everywhere they are shown.

The defects cluster in the write-off lifecycle and in the blast radius of the new `PaymentStatus::WrittenOff` case.

The write-off state machine is incomplete: `approve()` closes the receivable but never closes the *request*, so approved write-offs stay in the Owner's queue forever. That stale row is a live "Reject Request" button, and `reject()` — unlike `approve()` — has no terminal-state guard, so one click erases `write_off_reason` / `write_off_requested_by` / `write_off_requested_at` from a receivable that has already been booked as a loss. The result is a permanently stuck record: closed as Written Off, no reason on the row, and Accounting cannot re-request because `WriteOffRequestController::store` rejects entries whose `collection_status` is already `written_off`.

Separately, `PaymentStatus::WrittenOff` was added as a terminal status but no consumer was taught it is terminal. `CancellationController` and `PaymentController` still gate only on `PaymentStatus::Paid`, so a written-off job order can be cancelled (charging a cancellation fee against a booked loss) or paid (flipping `payment_status` back to Paid/PartiallyPaid while the AR row stays `written_off`) — directly contradicting the "This can't be undone" copy the Owner confirms against.

Smaller but real: the aging list's column allowlist silently drops `last_reminder_sent_at` from the row shape it claims to return; a `null` `total_amount` makes the reminder command close a live receivable as `Paid`; the collection-letter route will render a dunning letter for an already-settled account; and mail transport failures are stamped as delivered with no retry path.

## Critical Issues

### CR-01: Rejecting an already-approved write-off erases the write-off record

**File:** `app/Http/Controllers/Owner/WriteOffApprovalController.php:110-127`
**Issue:** `approve()` guards against acting on an entry whose `collection_status` is already `Paid`/`WrittenOff` (lines 88-92). `reject()` has no such guard — its only precondition is `write_off_requested_at !== null` (line 115). Because `approve()` never clears `write_off_requested_at` (see CR-02), an already-approved entry still satisfies that precondition and still renders a "Reject Request" button in `owner/WriteOffRequests.vue:202-245`.

One click then nulls `write_off_reason`, `write_off_requested_by` and `write_off_requested_at` on a receivable that is already `collection_status = written_off` with its job order at `payment_status = written_off`. The loss stays booked, but the justification and the requester are gone from the row, and the state is unrecoverable: `WriteOffRequestController::store` (line 28-32) refuses to accept a new request for an entry whose `collection_status` is `written_off`, so Accounting can never restore the reason.

The inline comment at lines 106-108 asserts this is "harmless, since rejection never touches `payment_status`" — that reasoning only holds for an entry settled to `Paid`, not for one already written off.
**Fix:**
```php
DB::transaction(function () use ($accountsReceivable): void {
    $accountsReceivable = AccountsReceivable::query()->whereKey($accountsReceivable->id)->lockForUpdate()->firstOrFail();

    abort_if($accountsReceivable->write_off_requested_at === null, 422, __('No write-off request is pending for this entry.'));
    abort_if(
        $accountsReceivable->collection_status === AccountsReceivableCollectionStatus::WrittenOff,
        422,
        __('This write-off has already been approved and cannot be rejected.'),
    );

    $accountsReceivable->forceFill([
        'write_off_reason' => null,
        'write_off_requested_by' => null,
        'write_off_requested_at' => null,
    ])->save();
});
```

### CR-02: Approved write-offs are never removed from the Owner's queue

**File:** `app/Http/Controllers/Owner/WriteOffApprovalController.php:82-96` (with `index()` at lines 27-35)
**Issue:** `index()` selects every row with `whereNotNull('write_off_requested_at')` and applies no `status` / `collection_status` filter. `approve()` sets `collection_status = written_off` but leaves `write_off_requested_at` populated, so the approved entry remains in the queue permanently, presented as a still-pending request with both action buttons live. Clicking Approve again returns a 422 error screen; clicking Reject succeeds and destroys the record (CR-01).

The same leak occurs for an entry that settles while a request is pending: `SendAccountsReceivableReminders::processOne()` sets `collection_status = paid` (line 66) without touching the write-off columns, so the paid entry also stays queued forever.

The existing test (`tests/Feature/Owner/WriteOffApprovalTest.php:41-54`) only asserts that a *never-requested* entry is absent; it never re-checks the queue after an approve/settle, so this is uncovered.
**Fix:** Close the request when it is resolved, and defensively filter the queue:
```php
// approve()
$accountsReceivable->forceFill([
    'collection_status' => AccountsReceivableCollectionStatus::WrittenOff->value,
    'write_off_approved_by' => $request->user()->id, // see WR-07
    'write_off_approved_at' => now(),
])->save();

// index()
->whereNotNull('write_off_requested_at')
->where('status', AccountsReceivableStatus::Active->value)
->whereNotIn('collection_status', [
    AccountsReceivableCollectionStatus::Paid->value,
    AccountsReceivableCollectionStatus::WrittenOff->value,
])
```
Add a test asserting the queue is empty after an approve and after the entry settles to Paid.

### CR-03: A written-off job order can still be cancelled or paid, silently reversing the write-off

**File:** `app/Enums/PaymentStatus.php:14`, `app/Http/Controllers/Owner/WriteOffApprovalController.php:95` (defect surfaces in `app/Http/Controllers/Cashier/CancellationController.php:32-38` and `app/Http/Controllers/Cashier/PaymentController.php:107`)
**Issue:** Phase 7 introduced `PaymentStatus::WrittenOff` as a terminal state (`owner/WriteOffRequests.vue:168` tells the Owner "This can't be undone") but no downstream guard was updated to treat it as terminal:

- `CancellationController::store` aborts only for `Paid` and `PendingConfirmation`. A written-off job order therefore passes, charges `cancellation_fee_amount`, creates a `Transaction`, and sets `cancelled_at` against a balance already booked as a loss. This is reachable from the UI: `cashier/Dashboard.vue:417-420` renders "Cancel Job Order" for every `payment_status !== 'paid'`, which now includes `written_off` (the phase explicitly added the `written_off` badge to that same table).
- `PaymentController::store` aborts only for `Paid` (line 107) and accepts any status in the production range. Recording a payment flips `payment_status` back to `paid`/`partially_paid` while the AR row stays `collection_status = written_off` — and the reminder command excludes `written_off` rows (`SendAccountsReceivableReminders.php:41-44`), so AR never self-corrects. The books then disagree: the job order reads Paid, the receivable reads Written Off.

**Fix:** Treat `WrittenOff` as terminal everywhere `Paid` is:
```php
// CancellationController::store
abort_if(
    in_array($jobOrder->payment_status, [PaymentStatus::Paid, PaymentStatus::WrittenOff], true),
    422,
    __('This job order is closed and cannot be cancelled from here.'),
);

// PaymentController::store
abort_if(
    in_array($jobOrder->payment_status, [PaymentStatus::Paid, PaymentStatus::WrittenOff], true),
    422,
    __('This job order is closed to further payments.'),
);
```
Also hide the Cancel action for `written_off` in `cashier/Dashboard.vue`, and add feature tests covering "cannot cancel a written-off job order" and "cannot pay a written-off job order".

## Warnings

### WR-01: `index()` drops `last_reminder_sent_at` from the row shape it declares

**File:** `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php:60` vs `:171`
**Issue:** `index()`'s `get([...])` column allowlist omits `last_reminder_sent_at`, but the shared `deriveRow()` reads `$accountsReceivable->last_reminder_sent_at` and the declared `AccountsReceivableRow` shape (line 27) promises it. Eloquent returns `null` for an unselected attribute rather than failing, so every list row silently reports "never reminded". `Index.vue` happens not to render the field today, so the bug is latent — but any future use of it in the list will be silently wrong, and enabling `Model::preventAccessingMissingAttributes()` would turn it into a hard `MissingAttributeException`.
**Fix:** Add `'last_reminder_sent_at'` to the `get()` column list (and keep it in sync with `deriveRow()`), or drop the field from `deriveRow()` and add it only in `show()` the way `approved_at` is handled.

### WR-02: A failed reminder email is stamped as sent and never retried

**File:** `app/Console/Commands/SendAccountsReceivableReminders.php:84-90`
**Issue:** The `catch (\Throwable)` reports the exception and then unconditionally writes `last_reminder_bracket = $bracket`. Because the next run only sends when `rank() > lastBracket->rank()`, a single transport hiccup means the escalation for that bracket is *never* sent — the 90+ "write-off decision needed" notice can be lost with nothing but a log line. `AccountsReceivableReminder` already uses `Queueable`, but the command sends synchronously so the queue's retry/backoff machinery is bypassed.
**Fix:** Hand the mail to the queue so failures retry, and stamp only after a successful hand-off:
```php
try {
    Mail::to($recipients)->queue(new AccountsReceivableReminder($receivable, $bracket));
} catch (\Throwable $e) {
    report($e);

    return; // leave the stamp untouched so the next run retries
}

$receivable->forceFill([...])->save();
```
If synchronous send is required, at minimum record the failure (e.g. a `last_reminder_failed_at`) and allow a retry rather than advancing the stamp. Update `tests/Feature/Console/SendAccountsReceivableRemindersTest.php:111-122`, which currently locks in the lossy behavior.

### WR-03: No guard for an empty recipient list; the recipient query runs once per receivable

**File:** `app/Console/Commands/SendAccountsReceivableReminders.php:85,99-105`
**Issue:** `reminderRecipients()` is called inside `processOne()`, i.e. once per receivable, re-running the same `users` query for every row. Worse, if no active Accounting Staff or Owner exists (all deactivated via `is_active`), the collection is empty and Symfony's mailer throws "An email must have a To..., Cc or Bcc header" — which the `catch` swallows while WR-02 still advances the bracket stamp. The result is a silent, permanent loss of every reminder with no operator-visible signal beyond `report()`.
**Fix:** Resolve recipients once in `handle()`, and bail out loudly when the list is empty:
```php
$recipients = $this->reminderRecipients();

if ($recipients->isEmpty()) {
    $this->error('No active Accounting Staff or Owner to notify — no reminders sent.');

    return self::FAILURE;
}
```

### WR-04: A null `total_amount` closes a live receivable as Paid

**File:** `app/Console/Commands/SendAccountsReceivableReminders.php:61-69`
**Issue:** When `jobOrder->total_amount` is `null`, `$balance` is set to `0.0` and the very next branch (`$balance <= 0`) writes the terminal `collection_status = paid`. `Paid` is unreachable from the UI afterwards — `CollectionStatusController::update` aborts 422 for `Paid`/`WrittenOff` — so an un-priced job order permanently closes a real receivable with no way back short of a manual DB edit. The same `null → 0.0` fallback in `AccountsReceivableController::deriveRow()` (line 146-148) renders the entry as "Settled" in the list.
**Fix:** Do not conflate "unpriced" with "settled":
```php
if ($receivable->jobOrder->total_amount === null) {
    report(new RuntimeException("AR {$receivable->id} has no job order total; skipping."));

    return;
}
```
and surface an explicit "Total not set" state in `deriveRow()` rather than `0.0`.

### WR-05: Collection letter renders for already Paid / Written Off entries

**File:** `app/Http/Controllers/AccountingStaff/CollectionLetterController.php:24`
**Issue:** The only guard is `status === AccountsReceivableStatus::Active`; `collection_status` is not checked. A `paid` or `written_off` entry is still `Active`, so `GET /accounting-staff/accounts-receivable/{id}/collection-letter` renders a full dunning letter ("Continued non-payment will affect your eligibility...") for a customer who has already settled — or whose debt was forgiven. `Show.vue:317-327` hides the button when terminal, but the URL is directly reachable and the route is a plain GET (bookmarkable, shareable, reachable via browser history).
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

**File:** `app/Http/Controllers/AccountingStaff/WriteOffRequestController.php:33-39`
**Issue:** The "already pending" check and the subsequent write are two unsynchronized statements with no `lockForUpdate()` and no transaction, unlike the approve/reject paths that were deliberately hardened (`WriteOffApprovalController.php:85,113`). Two concurrent submissions (double click, retried request) both pass the guard; the later write wins and overwrites the first requester's reason and timestamp, and two `audit_trail` rows are created for what should be one request. The existing test (`WriteOffRequestTest.php:40-57`) only covers the sequential case.
**Fix:** Mirror the approval path — wrap in `DB::transaction()` with a `lockForUpdate()` re-read before the guards.

### WR-07: The receivable row keeps no record of who approved the write-off

**File:** `app/Http/Controllers/Owner/WriteOffApprovalController.php:94-95`
**Issue:** Approving a write-off — the single most consequential financial action in this phase — records nothing on the row about who approved it or when. `approved_by` / `approved_at` belong to the credit decision and are left pointing at the original credit approver, so a reader of the AR row cannot distinguish "credit approved by X" from "loss authorized by X". The only trace is `audit_trail`, which is not surfaced anywhere in the AR UI. Combined with CR-01 (the request columns can be wiped) a written-off entry can end up with zero on-row provenance.
**Fix:** Add `write_off_approved_by` / `write_off_approved_at` columns and populate them in `approve()`; render them on `AccountsReceivable/Show.vue` alongside the existing write-off alert.

### WR-08: The outstanding-balance derivation is copy-pasted in five places

**File:** `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php:145-148`, `app/Http/Controllers/AccountingStaff/CollectionLetterController.php:32-35`, `app/Http/Controllers/Owner/WriteOffApprovalController.php:38-41`, `app/Console/Commands/SendAccountsReceivableReminders.php:57-63`, `app/Mail/AccountsReceivableReminder.php:118-124`
**Issue:** The same "total_amount minus completed transactions, rounded to 2" money computation — including the questionable `null → 0.0` fallback from WR-04 — is duplicated verbatim five times in this phase alone (plus `ReceiptController::show()`). Any future change (partial refunds, cancellation-fee transactions, void handling) must be found and applied in six places; missing one produces a silently wrong balance on a customer-facing collection letter or reminder email. This is exactly the class of drift the project's "AR balances are derived, not stored" invariant is meant to prevent.
**Fix:** Extract a single accessor/action, e.g. `AccountsReceivable::outstandingBalance(): ?float` (or an `App\Actions\AR\DeriveOutstandingBalance` invokable) that operates on the already-eager-loaded relation, and call it from all six sites.

### WR-09: Reminder mail body is not covered by tests, only the subject line

**File:** `tests/Unit/Mail/AccountsReceivableReminderMailableTest.php:11-17`
**Issue:** The only mailable assertion is that a `NinetyPlus` subject starts with "Final notice". The rendered markdown view (`resources/views/mail/accounts-receivable-reminder.blade.php`) is never exercised, so a missing `with()` key would surface as an undefined-variable error at send time — inside the `try/catch` of WR-02, which swallows it and stamps the bracket as delivered. `content()` passes eight variables (including `$daysPastDue`, which is `null` for a not-yet-due entry) with no render test.
**Fix:** Add `$mail->assertSeeInHtml(...)` / `$mail->render()` assertions covering at least one reminder-bearing bracket, and assert each bracket's subject rather than only `NinetyPlus`.

## Info

### IN-01: The "90+ Days" bucket does not include day 90

**File:** `app/Models/AccountsReceivable.php:124-130`, `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue:78`
**Issue:** `$daysPastDue <= 90 => SixtyOneToNinety` puts exactly 90 days past due in the "61–90 Days" bucket, so the bucket labelled "90+ Days" actually begins at day 91 (encoded in `AgingBracketTest.php:44-45`). The label and the behavior disagree for one day, and the 90+ escalation email fires a day later than the label implies.
**Fix:** Either relabel to "91+ Days" or change the boundary to `$daysPastDue < 90`, whichever matches D-03's intent — and make the label and test agree explicitly.

### IN-02: An entry becomes "1–15 Days" and triggers a reminder at 0 days past due

**File:** `app/Models/AccountsReceivable.php:118-131`
**Issue:** `due_at` carries a time of day (`approved_at + credit_term_days`), so the moment it passes, `isFuture()` is false and `(int) diffInDays()` is `0`, which falls into `OneToFifteen`. The reminder fires with the subject "… is 0 days past due" and `Show.vue` displays "Days Past Due: 0".
**Fix:** Either normalize `due_at` to end-of-day on approval, or use `ceil()` / a `>= 1` floor so the first reminder reads "1 day past due".

### IN-03: `CollectionLetter.vue` does not pass `navItems`

**File:** `resources/js/pages/accounting-staff/CollectionLetter.vue:22-26`
**Issue:** Every sibling page passes its portal's nav (`Index.vue:52`, `Show.vue:55`, `cashier/Receipt.vue:45`), but the letter page omits it, so `AppSidebarLayout` receives `undefined` and the Accounting Staff sidebar renders empty — the user has no way back except the browser Back button.
**Fix:** `layout: { navItems: accountingStaffNavItems, breadcrumbs: [...] }`.

### IN-04: Only the print button is `print:hidden` on the collection letter

**File:** `resources/js/pages/accounting-staff/CollectionLetter.vue:91-97`
**Issue:** The page renders inside `AppLayout`, so the sidebar, header and breadcrumbs are included in the printed output; only the "Print Letter" button is hidden. A customer-facing letter should print as a clean document.
**Fix:** Add `print:hidden` to the layout chrome or render this page without `AppLayout` (the layout resolver in `app.ts` supports a no-layout page).

### IN-05: `credit_extended` is sent to the aging list but never rendered

**File:** `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php:164`, `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue:31`
**Issue:** The field is in the payload and the TS interface but unused in the Index template (only `Show.vue` renders it). Dead prop.
**Fix:** Drop it from the list payload/interface, or render it as its own column.

### IN-06: `v-for` combined with `v-else` on the same element

**File:** `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue:279`, `resources/js/pages/owner/WriteOffRequests.vue:102-106`
**Issue:** `<TableRow v-for="…" v-else :key="…">` relies on Vue 3's "v-if wins over v-for" precedence. It works, but it is the documented anti-pattern and reads as if the `v-else` applied per row.
**Fix:** Wrap the rows in a `<template v-else>` and put `v-for` on the inner `<TableRow>`.

### IN-07: Bracket/status label maps and formatting helpers are duplicated across four pages

**File:** `Index.vue:72-88,118-167`, `Show.vue:70-137`, `CollectionLetter.vue:28-33`, `owner/WriteOffRequests.vue:58-63`
**Issue:** `BRACKET_LABELS`, `COLLECTION_STATUS_LABELS`, `money()`, `agingBadgeProps()` and `collectionStatusBadgeProps()` are byte-identical copies in multiple SFCs. A label change (e.g. IN-01's "90+ Days") must be made in several files.
**Fix:** Move them to a shared module (e.g. `resources/js/lib/accounts-receivable.ts`) and import.

### IN-08: The aging list is unpaginated

**File:** `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php:57-60`
**Issue:** Every Active receivable — open and closed — is loaded, mapped, sorted in PHP, and shipped to the browser on each page load. There is no pagination or date-window filter, so the payload grows without bound as written-off/paid history accumulates in `closedReceivables`. Flagged as a scale/maintainability note only; performance is out of scope for this review.
**Fix:** Paginate, or at minimum bound the closed set (e.g. last 90 days) with a "view all" affordance.

### IN-09: Admin sees write-off action buttons that always 403

**File:** `resources/js/pages/owner/WriteOffRequests.vue:149-245`, `routes/owner.php:24-26`
**Issue:** The route group is `role:owner,admin` so an Admin can open the queue, but `AccountsReceivablePolicy::approveWriteOff/rejectWriteOff` restrict the mutations to Owner. The page renders both buttons unconditionally, so an Admin gets a 403 error screen on click. This matches the pre-existing `owner/CreditRequests.vue` pattern, so it is consistency-preserving rather than a regression.
**Fix:** Share `auth.user.role` (already available via `usePage()`) and gate the action column on `role === 'owner'`, in both pages together.

---

_Reviewed: 2026-09-08T00:21:01Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
