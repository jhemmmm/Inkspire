---
phase: 07-accounts-receivable
reviewed: 2026-09-08T22:34:54Z
depth: standard
files_reviewed: 49
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
  - app/Http/Controllers/Cashier/CreditRequestController.php
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
  - tests/Feature/Cashier/CreditRequestTest.php
  - tests/Feature/Cashier/RecordPaymentTest.php
  - tests/Feature/Console/SendAccountsReceivableRemindersTest.php
  - tests/Feature/Owner/CreditApprovalTest.php
  - tests/Feature/Owner/WriteOffApprovalTest.php
  - tests/Unit/Mail/AccountsReceivableReminderMailableTest.php
  - tests/Unit/SystemConfigurationTest.php
findings:
  critical: 4
  warning: 9
  info: 6
  total: 19
status: issues_found
---

# Phase 07: Code Review Report

**Reviewed:** 2026-09-08T22:34:54Z
**Depth:** standard
**Files Reviewed:** 49
**Status:** issues_found

## Summary

Reviewed the full Accounts Receivable surface: aging derivation, the daily reminder command, collection status/letters, the write-off request/approval lifecycle, and the adjacent Cashier payment/credit/cancellation writers that share the `payment_status` / `collection_status` fault line.

The write-off approval path (the one hardened over two prior gap-closure rounds) is now genuinely solid: locked re-reads, derived-balance authority, stale-reject protection. The defects that remain are in the **surfaces those rounds did not revisit** — the same class of stale-read and missing-terminal-state guards, just in different controllers:

- `CollectionStatusController` is the only mutating controller in this phase with **no** locked re-read, and its guard runs against a route-model-bound row read before the request. An Owner approving a write-off concurrently is silently reversed, putting a booked loss back into open AR with reminders resuming (CR-04).
- `CollectionLetterController` is the only AR controller with **no** terminal-`collection_status` guard, so a dunning letter can be printed for a paid or written-off account (CR-03).
- `PaymentController` guards `paid` and `written_off` but not `credit_pending_approval`, and `CreditApprovalController::approve()` never re-derives the balance the way `WriteOffApprovalController::approve()` now does — so a fully paid job order can be flipped back to `on_credit` with a receivable posted for money already in the till (CR-01).
- There is no UI path anywhere in the Cashier portal to record a payment against an `on_credit` job order, which means the AR lifecycle this phase builds has no settle path (CR-02).

Underneath those: the reminder command records a reminder as sent when the send threw, the derived-balance computation is copy-pasted verbatim in five files, and `total_amount` crosses the Inertia boundary as a `decimal:2` string while both Vue interfaces declare it `number`.

## Narrative Findings (AI reviewer)

## Critical Issues

### CR-01: A payment taken while a credit request is pending is silently reversed to `on_credit` on approval

**File:** `app/Http/Controllers/Cashier/PaymentController.php:108-109`, `app/Http/Controllers/Owner/CreditApprovalController.php:44-64`

**Issue:** `PaymentController::store()` (and `edit()`, line 37-50) guards `cancelled_at`, `status`, `PaymentStatus::Paid` and `PaymentStatus::WrittenOff` — but **not** `PaymentStatus::CreditPendingApproval`. `CreditRequestController::store()` blocks the reverse direction (line 44-48: "This job order already has a payment action pending"), so the asymmetry is clearly unintended.

Sequence:
1. Cashier requests On-Credit → `payment_status = credit_pending_approval`, AR row created `pending_approval` with `balance = 1000`.
2. Before the Owner acts, the customer pays cash. `POST /cashier/job-orders/{id}/payment` passes every guard → `Transaction` created, `payment_status = paid`.
3. Owner approves the still-pending credit request. `CreditApprovalController::approve()` re-reads under lock but only checks `status === PendingApproval` — it never looks at the job order at all. It writes `payment_status = on_credit` and stamps `due_at`.

Result: a fully-paid job order is booked as an open receivable for ₱1000 already collected. Consequences compound — the terminal `paid` state is destroyed (same class as the deferred `ConfirmPaymentIntent` defect, but reachable through a normal Cashier action), and because `CancellationController::store()` only blocks cancellation when `payment_status === Paid` (line 33), the now-`on_credit` order becomes cancellable again and can be charged a cancellation fee. The stored `balance` column is also stale (it was computed before the payment), so the collection letter's "Credit Extended" line overstates the debt.

Note this is *not* covered by the deferred `ConfirmPaymentIntent` item in `deferred-items.md` — this is a direct cash/bank-transfer path plus a missing re-check in `CreditApprovalController`, and `CreditApprovalTest.php` has no test for a payment landing during the pending window.

**Fix:** Guard both directions, and make the approval authoritative on the derived balance the way `WriteOffApprovalController::approve()` already is.

```php
// PaymentController::store() and ::edit() — alongside the existing guards
abort_if(
    $jobOrder->payment_status === PaymentStatus::CreditPendingApproval,
    422,
    __('This job order has an On-Credit request awaiting Owner approval. Resolve it before recording a payment.'),
);

// CreditApprovalController::approve(), inside the locked transaction
$jobOrder = JobOrder::query()->whereKey($accountsReceivable->job_order_id)->lockForUpdate()->firstOrFail();

abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));

$amountPaid = (float) $jobOrder->transactions()->where('status', TransactionStatus::Completed->value)->sum('amount');
$outstandingBalance = $jobOrder->total_amount !== null
    ? round((float) $jobOrder->total_amount - $amountPaid, 2)
    : 0.0;

abort_if($outstandingBalance <= 0.0, 422, __('This job order was settled before the credit request could be approved.'));

$accountsReceivable->forceFill([
    'status' => AccountsReceivableStatus::Active,
    'balance' => $outstandingBalance, // re-snapshot; the request-time value may be stale
    'approved_by' => $request->user()->id,
    'approved_at' => now(),
    'due_at' => now()->addDays(SystemConfiguration::getInt('credit_term_days', 30)),
])->save();

$jobOrder->forceFill(['payment_status' => PaymentStatus::OnCredit])->save();
```

---

### CR-02: There is no way to record a payment against an `on_credit` job order — the AR lifecycle has no settle path

**File:** `resources/js/pages/cashier/Dashboard.vue:378-429`

**Issue:** The Cashier Dashboard action dropdown is an exhaustive `v-if` / `v-else-if` chain keyed on `payment_status`:

- `unpaid` / `partially_paid` → "Process Payment"
- `pending_confirmation` → "Check Payment Status"
- `paid` → "View Receipt"
- everything else → nothing (only "Cancel Job Order")

`on_credit`, `credit_pending_approval` and `credit_rejected` therefore have **no** action. `PaymentController.edit(...)` is the only link to the payment page anywhere in `resources/js` (verified by grep), so once an Owner approves a credit request the job order becomes a dead end in the Cashier portal.

This breaks the phase's own premise. `AccountsReceivable/Show.vue:226-228` tells Accounting Staff "Payments are recorded at the Cashier counter", and `Show.vue:302-306` says "This does not stop reminder emails — only payment or an approved write-off does". Neither is achievable: the only way an AR entry can reach `collection_status = paid` today is `SendAccountsReceivableReminders::processOne()` auto-closing it, which requires a payment that cannot be recorded. Every receivable's only reachable terminal state is write-off.

The server side already permits it — `PaymentController::store()` accepts `on_credit` (it is not in any abort list) — so this is purely a missing dropdown branch.

**Fix:** Extend the payment branch to cover the credit states that still owe money.

```vue
<DropdownMenuItem
    v-if="
        jobOrder.payment_status === 'unpaid' ||
        jobOrder.payment_status === 'partially_paid' ||
        jobOrder.payment_status === 'on_credit' ||
        jobOrder.payment_status === 'credit_rejected'
    "
    as-child
>
    <Link :href="PaymentController.edit(jobOrder.id).url" :data-test="`process-payment-${jobOrder.id}-link`">
        {{ jobOrder.payment_status === 'on_credit' ? 'Collect Balance' : 'Process Payment' }}
    </Link>
</DropdownMenuItem>
```

Add a feature test asserting an `on_credit` job order can be paid in full and that the AR entry then reports a zero derived balance.

---

### CR-03: A collection letter can be printed for a paid or written-off entry

**File:** `app/Http/Controllers/AccountingStaff/CollectionLetterController.php:22-30`

**Issue:** `show()` guards only `AccountsReceivableStatus::Active`. It is the sole AR controller with no terminal-`collection_status` check — both siblings have one (`CollectionStatusController.php:25-29`, `WriteOffRequestController.php:28-32`). `Show.vue:318` hides the "Print Collection Letter" button when `isTerminal`, but the route is a plain `GET` and remains directly reachable (bookmark, browser back, refresh of an already-open tab).

For a `written_off` entry the letter renders at full face value with the 90+ final-notice body — "this account will be endorsed for collection and may be written off as a loss" — for a balance the Owner has already booked as a loss. For a `paid` entry it renders a demand letter showing "Amount Due ₱0.00". Both are customer-facing documents. `CollectionLetterTest.php` has no case for either state.

**Fix:** Mirror the sibling guard.

```php
abort_unless($accountsReceivable->status === AccountsReceivableStatus::Active, 404);
abort_if(
    in_array($accountsReceivable->collection_status, [
        AccountsReceivableCollectionStatus::Paid,
        AccountsReceivableCollectionStatus::WrittenOff,
    ], true),
    404,
);
```

---

### CR-04: `CollectionStatusController` mutates on an unlocked stale read and can resurrect a written-off entry

**File:** `app/Http/Controllers/AccountingStaff/CollectionStatusController.php:22-33`

**Issue:** This is the only mutating controller in the phase with neither a `DB::transaction()` nor a `lockForUpdate()` re-read — `CreditApprovalController`, `WriteOffApprovalController` and `CreditRequestController` all have both, added specifically to close this class of defect in earlier rounds. The guards on lines 24-29 evaluate the route-model-bound instance, hydrated from the request's own `SELECT`, with no re-read before the write.

Interleaving:
1. Accounting Staff opens `AccountsReceivable/Show` for an entry with a pending write-off (`collection_status = pending`).
2. Owner approves the write-off → `collection_status = written_off`, `payment_status = written_off`.
3. Accounting Staff clicks "Update Status" → `collections`. Their bound model still reads `pending`, both guards pass, `forceFill(['collection_status' => 'collections'])->save()` lands.

The booked loss is reversed on the AR side while the job order stays `payment_status = written_off`. Concretely: the entry re-enters `AccountsReceivableController::index()`'s open set and its bracket totals (line 69-80), and `SendAccountsReceivableReminders::handle()`'s `whereNotIn('collection_status', [paid, written_off])` (line 41-44) starts escalating it again — reminder emails for a written-off balance. The two columns now permanently disagree with no code path to reconcile them.

The same shape applies to `WriteOffRequestController::store()` (see WR-07), but there the blast radius is smaller.

**Fix:** Use the phase's established locked-re-read boundary.

```php
public function update(UpdateCollectionStatusRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
{
    DB::transaction(function () use ($request, $accountsReceivable): void {
        $accountsReceivable = AccountsReceivable::query()
            ->whereKey($accountsReceivable->id)
            ->lockForUpdate()
            ->firstOrFail();

        abort_unless($accountsReceivable->status === AccountsReceivableStatus::Active, 422, __('This entry is closed and its collection status can\'t be changed.'));
        abort_if(
            in_array($accountsReceivable->collection_status, [
                AccountsReceivableCollectionStatus::Paid,
                AccountsReceivableCollectionStatus::WrittenOff,
            ], true),
            422,
            __('This entry is closed and its collection status can\'t be changed.'),
        );

        $accountsReceivable->forceFill(['collection_status' => $request->validated('collection_status')])->save();
    });

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Collection status updated.')]);

    return back();
}
```

Add a test that forces `collection_status` to `written_off` after route binding resolves (i.e. update the row inside the request lifecycle, or assert the locked re-read via a second in-test update before the patch) and asserts a 422.

## Warnings

### WR-01: A failed reminder send is recorded as sent, permanently consuming that bracket's escalation

**File:** `app/Console/Commands/SendAccountsReceivableReminders.php:84-90`

**Issue:** The `try`/`catch` swallows the throw, then execution falls through to `forceFill(['last_reminder_bracket' => ..., 'last_reminder_sent_at' => now()])->save()` unconditionally. Two separate problems:

1. `last_reminder_sent_at` is surfaced to Accounting Staff as "Last Reminder Sent" (`Show.vue:259-263`). Stamping it when the transport threw makes the UI assert a delivery that never happened.
2. Because `last_reminder_bracket` advances, the `rank()` comparison on line 78 will never fire again for that bracket. A transient SMTP outage on the single day an entry crosses into 31-60 means the 31-60 escalation is lost forever — the next email is only sent if the entry survives to 90+.

The zero-recipient case has the same effect and is easier to hit than a transport outage: `reminderRecipients()` returns an empty `Collection` when no active Accounting Staff or Owner exists, `Mail::to(collect([]))->send(...)` throws "An email must have a To/Cc/Bcc header", the catch swallows it, and every entry in the run gets stamped as reminded.

`SendAccountsReceivableRemindersTest.php:111-122` encodes the current behavior deliberately, so this needs a decision rather than a blind patch — but stamping `last_reminder_sent_at` on failure is wrong under any policy.

**Fix:** At minimum, only stamp on success; the daily cadence makes retry natural and the duplicate risk is bounded by one email.

```php
if ($this->reminderRecipients()->isEmpty()) {
    $this->warn('No active Accounting Staff or Owner to notify; skipping reminders.');

    return;
}

try {
    Mail::to($this->reminderRecipients())->send(new AccountsReceivableReminder($receivable, $bracket));
} catch (\Throwable $e) {
    report($e);

    return; // retry on tomorrow's run rather than consuming the bracket
}

$receivable->forceFill(['last_reminder_bracket' => $bracket->value, 'last_reminder_sent_at' => now()])->save();
```

If the "never block the stamp" policy is intentional, split the columns: advance `last_reminder_bracket` but leave `last_reminder_sent_at` untouched on failure, and update the test name/assertion to say so.

---

### WR-02: `index()`'s column allowlist omits a column `deriveRow()` reads — `last_reminder_sent_at` is always null in the list payload

**File:** `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php:60` and `:171`

**Issue:** `index()` selects `['id','job_order_id','balance','status','collection_status','due_at','write_off_reason','write_off_requested_at']`. The shared `deriveRow()` reads `$accountsReceivable->last_reminder_sent_at` on line 171. Eloquent returns `null` for an unselected attribute silently — no exception, no warning — so every row in the index payload reports `last_reminder_sent_at: null` regardless of the stored value.

`show()` uses route-model binding (all columns) so it is correct, which is exactly why this hides: the two callers of `deriveRow()` disagree and only one is wrong. `Index.vue` does not render the field today, so nothing is visibly broken — but the declared `AccountsReceivableRow` phpstan shape (line 27) promises it, and the first person to add a "Last Reminder" column to the list table will ship a silently-empty column. The class docblock (lines 32-40) already documents having been bitten by this exact allowlist problem with `queue_entry_id`.

**Fix:** Add the column to the select list so the allowlist matches what `deriveRow()` actually reads.

```php
->get(['id', 'job_order_id', 'balance', 'status', 'collection_status', 'due_at', 'write_off_reason', 'write_off_requested_at', 'last_reminder_sent_at']);
```

---

### WR-03: `total_amount` crosses the Inertia boundary as a string while both Vue interfaces declare it `number`

**File:** `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php:156`; `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue:26`; `resources/js/pages/accounting-staff/AccountsReceivable/Show.vue:34`

**Issue:** `JobOrder::casts()` declares `'total_amount' => 'decimal:2'`, so the attribute serializes as the string `"1000.00"`. `deriveRow()` passes it through raw on line 156 while casting its neighbour on the very next relevant line (`'credit_extended' => (float) $accountsReceivable->balance`, line 164). `CancellationFeeTest.php:199` already pins this: `->where('jobOrders.0.accounts_receivable.balance', '1000.00')` — a string.

Both Vue interfaces declare `total_amount: number | null`. Today nothing breaks because every consumer either wraps in `Number()` (`money()`) or uses `-`, which coerces. But the type is a lie, and the first `+` or `.toFixed()` written against it produces string concatenation or a `TypeError`. `cashier/Dashboard.vue:82-88` documents this exact hazard as a prior defect (CR-04) and coerces defensively; this phase reintroduced the un-coerced shape.

**Fix:** Cast at the boundary, consistent with the sibling field.

```php
'total_amount' => $accountsReceivable->jobOrder->total_amount !== null
    ? (float) $accountsReceivable->jobOrder->total_amount
    : null,
```

---

### WR-04: The derived-balance computation is duplicated verbatim across five files

**File:** `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php:145-148`; `app/Http/Controllers/AccountingStaff/CollectionLetterController.php:32-35`; `app/Http/Controllers/Owner/WriteOffApprovalController.php:40-43` and `:108-111`; `app/Console/Commands/SendAccountsReceivableReminders.php:57-63`; `app/Mail/AccountsReceivableReminder.php:118-124`

**Issue:** The same five lines — sum Completed transactions, subtract from `total_amount`, `round(..., 2)`, fall back to `0.0` on null — appear identically in six places across five files, each with its own comment claiming it "matches `ReceiptController::show()`'s identical derivation". This is the exact fault line called out as having already produced two rounds of defects: the derived-balance guard added to `WriteOffApprovalController::approve()` in round 07-07 had to be hand-copied because there was no shared implementation, and `CreditApprovalController::approve()` (CR-01 above) was missed precisely because there was nothing central to add it to.

Any future change — netting out `TransactionType::CancellationFee`, handling refunds, changing rounding — must be found and applied in six places or the surfaces silently disagree.

**Fix:** Put it on the model (or a small support class) and call it everywhere.

```php
// app/Models/JobOrder.php
public function outstandingBalance(): float
{
    $amountPaid = (float) ($this->relationLoaded('transactions')
        ? $this->transactions->where('status', TransactionStatus::Completed->value)->sum('amount')
        : $this->transactions()->where('status', TransactionStatus::Completed->value)->sum('amount'));

    return $this->total_amount !== null
        ? round((float) $this->total_amount - $amountPaid, 2)
        : 0.0;
}
```

Add a unit test covering the null-`total_amount`, no-transactions, and partially-paid cases once, instead of implicitly across six feature tests.

---

### WR-05: A cancelled job order's receivable keeps aging, keeps escalating, and can still be approved

**File:** `app/Http/Controllers/Cashier/CancellationController.php:30-50`; `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php:57-60`; `app/Console/Commands/SendAccountsReceivableReminders.php:38-46`; `app/Http/Controllers/Owner/CreditApprovalController.php:44-64`

**Issue:** No AR surface filters or flags `job_orders.cancelled_at`. Two distinct paths:

1. **Post-approval cancellation.** `CancellationController::store()` blocks `paid`, `pending_confirmation` and `written_off` but permits `on_credit`. `cashier/Dashboard.vue:103-114` shows this is deliberate ("will NOT be written off by cancelling — follow up on collection separately"). But the AR aging list, `Show`, and the reminder command give Accounting Staff no signal that the job order behind the balance is cancelled — and CR-03 aside, a collection letter for it prints normally.

2. **Cancellation during pending approval.** `CreditRequestController::store()` explicitly refuses to create a credit request for a cancelled job order (line 29, tested at `CreditApprovalTest.php:176`), but `CreditApprovalController::approve()` never re-checks `cancelled_at`. Cancel after requesting → Owner approves → an Active receivable with a `due_at` is posted against a cancelled job order and immediately begins escalating. The asymmetry means the create-side guard is trivially bypassed by reordering two actions.

Note also that `CancellationController` writes a `cancellation_fee` `Transaction` with `status = completed`, and every derived-balance computation in WR-04 sums *all* Completed transactions regardless of `type` — so a cancellation fee is silently credited against the customer's outstanding receivable.

**Fix:** Add the missing `cancelled_at` re-check in `CreditApprovalController::approve()` (see CR-01's snippet), and surface cancellation on the AR side:

```php
// AccountsReceivableController::eagerLoads() — add cancelled_at
'jobOrder:id,number,description,total_amount,queue_entry_id,cancelled_at',

// deriveRow()
'job_order' => [
    // ...
    'cancelled_at' => $accountsReceivable->jobOrder->cancelled_at,
],
```

Render a "Job Order Cancelled" badge in `Index.vue` / `Show.vue`, and decide explicitly whether the cancellation fee should be excluded from the outstanding-balance sum (`->whereNot('type', TransactionType::CancellationFee->value)`).

---

### WR-06: The Collection Letter page drops the Accounting Staff sidebar and hardcodes its breadcrumb href

**File:** `resources/js/pages/accounting-staff/CollectionLetter.vue:22-26`

**Issue:** `defineOptions({ layout: { breadcrumbs: [{ title: 'Collection Letter', href: '#' }] } })` passes no `navItems`. `AppSidebar.vue:32` falls back to `defaultNavItems` — the starter-kit stub pointing at the generic `dashboard()` route — so an Accounting Staff member printing a letter loses their portal nav and is offered a link out of their portal. Both sibling pages pass `accountingStaffNavItems` (`Index.vue:52`, `Show.vue:56`).

The `href: '#'` also violates the documented convention ("referencing generated route helpers for `href` values — never hardcoded URL strings"); every other breadcrumb in the phase uses a Wayfinder helper.

**Fix:**

```ts
import { accountingStaffNavItems } from '@/config/nav/accounting-staff';
import { index as accountsReceivableIndex, show } from '@/routes/accounting-staff/accounts-receivable';

const props = defineProps<{ /* ... */ accountsReceivableId: number }>(); // add to the controller payload

defineOptions({ layout: { navItems: accountingStaffNavItems } });

setLayoutProps({
    breadcrumbs: [
        { title: 'Accounts Receivable', href: accountsReceivableIndex() },
        { title: 'Collection Letter', href: collectionLetterShow.url(props.accountsReceivableId) },
    ],
});
```

---

### WR-07: `WriteOffRequestController::store()` mutates on an unlocked read — concurrent requests overwrite each other

**File:** `app/Http/Controllers/AccountingStaff/WriteOffRequestController.php:25-39`

**Issue:** Same shape as CR-04 with a smaller blast radius. All three guards (lines 27-33) evaluate the route-model-bound instance, and the `forceFill(...)->save()` runs outside any transaction. Two Accounting Staff members (or one double-click) both observe `write_off_requested_at === null`, both pass, and the second write silently overwrites the first's `write_off_reason` and `write_off_requested_by` — the audit trail records both, but the Owner reviewing the queue sees only the survivor. The "already pending" guard tested at `WriteOffRequestTest.php:40-57` is therefore only correct for serialized requests.

**Fix:** Wrap in `DB::transaction()` with a `lockForUpdate()` re-read before the three guards, matching `WriteOffApprovalController::approve()`.

---

### WR-08: A write-off can be requested for an entry that is already settled, creating a permanently un-approvable queue item

**File:** `app/Http/Controllers/AccountingStaff/WriteOffRequestController.php:27-33`

**Issue:** The request side guards `status` and `collection_status`, but never the derived balance — the guard that round 07-07 established as *authoritative* on the approve side precisely because `collection_status` lags by up to 24 hours (only the daily cron reconciles it). So the common case is reachable: a customer pays, the cron has not run, Accounting Staff requests a write-off, the request is accepted, and the Owner's `approve()` then rejects it with 422 "settled or closed before the write-off could be approved" — with no way to clear the entry from the queue except `reject()`, which the copy frames as an Owner decision rather than a cleanup.

**Fix:** Apply the same derived-balance guard on the request side.

```php
$outstandingBalance = $accountsReceivable->jobOrder->outstandingBalance(); // see WR-04

abort_if($outstandingBalance <= 0.0, 422, __('This balance has already been settled and cannot be written off.'));
```

---

### WR-09: Admin is shown enabled Approve/Reject buttons that always 403

**File:** `resources/js/pages/owner/WriteOffRequests.vue:149-245`

**Issue:** `routes/owner.php:11` gates the group at `role:owner,admin`, and `AccountsReceivablePolicy::approveWriteOff()` deliberately narrows the mutation to Owner only. `WriteOffApprovalController::index()` acknowledges this in its docblock, but the page renders both destructive actions unconditionally — an Admin gets a full confirmation dialog for an irreversible loss booking and then a 403. `WriteOffApprovalTest.php:26-39` asserts the 403 but nothing asserts the buttons are hidden.

**Fix:** Pass the capability from the controller and gate the buttons.

```php
return Inertia::render('owner/WriteOffRequests', [
    'writeOffRequests' => $writeOffRequests,
    'canDecide' => $request->user()->role === UserRole::Owner,
]);
```

```vue
<div v-if="canDecide" class="flex justify-end gap-2"> ... </div>
<span v-else class="text-muted-foreground text-sm">Owner decision required</span>
```

## Info

### IN-01: `credit_term_days` accepts `0`

**File:** `database/seeders/SystemConfigurationSeeder.php:119-126`, `app/Concerns/SystemConfigValidationRules.php:19`

**Issue:** The new key falls under the generic `['required', 'integer', 'min:0']` rule. Setting it to `0` makes every subsequently approved credit due at the instant of approval — `due_at = now()`, `isFuture()` false, bracket `one_to_fifteen`, reminder fired on the next cron run.

**Fix:** Key the rule on the configuration key for the ones where zero is meaningless (`credit_term_days`, `default_sla_days`), or use `min:1`.

---

### IN-02: Bracket labels are off by one against the thresholds

**File:** `app/Models/AccountsReceivable.php:124-130`

**Issue:** `$daysPastDue <= 90 => SixtyOneToNinety` with `default => NinetyPlus` means `ninety_plus` actually begins at 91 days, not 90. Symmetrically, day 0 (due date passed by minutes) falls into `one_to_fifteen`, and the reminder email subject renders "is 0 days past due". `AgingBracketTest.php:44-45` pins the current behavior, so this is a labelling decision rather than a silent bug — but the mail copy reads badly at the boundary.

**Fix:** Either rename the case/labels, or floor the "past due" check at 1 full day (`$this->due_at->addDay()->isFuture()`).

---

### IN-03: Empty `rules()` bodies with placeholder comments

**File:** `app/Http/Requests/Owner/ApproveWriteOffRequest.php:23-28`, `app/Http/Requests/Owner/RejectWriteOffRequest.php:23-28`

**Issue:** Both return `[ // ]` — a scaffold artifact. These requests exist purely for `authorize()`, which is fine, but the placeholder comment reads as unfinished work.

**Fix:** `return [];` with a one-line PHPDoc noting the request carries no payload.

---

### IN-04: `agingBracket()` and `daysPastDue()` duplicate the same computation

**File:** `app/Models/AccountsReceivable.php:116-144`

**Issue:** Both re-implement the null/future check and `(int) $this->due_at->diffInDays(now())`. Small, but they must stay in lockstep — a fix to one (see IN-02) silently diverges the other.

**Fix:** Have `agingBracket()` call `daysPastDue()` and `match` on the nullable result.

---

### IN-05: The mailable's only test asserts the subject prefix

**File:** `tests/Unit/Mail/AccountsReceivableReminderMailableTest.php:11-17`

**Issue:** One assertion, on `envelope()->subject`. `content()` and `resources/views/mail/accounts-receivable-reminder.blade.php` are never rendered, so a missing `with` key, a typo in a blade variable, or a `number_format(null)` on `outstandingBalance` would only surface in production. The file also lives under `tests/Unit/` while pulling in `RefreshDatabase` and hitting the database — the project guidance is that most tests should be feature tests.

**Fix:** Add `$mail->render()` (or `assertSeeInHtml`) coverage for at least the 1-15 and 90+ brackets, and move the file to `tests/Feature/Mail/`.

---

### IN-06: The seeder's new key is only covered by a row count

**File:** `tests/Unit/SystemConfigurationTest.php:32-43`

**Issue:** The test asserts `count() === 16` and spot-checks two unrelated keys. A typo in `credit_term_days` would keep the count at 16 while every caller silently fell back to the hardcoded `30` default in `CreditApprovalController::approve()` and the backfill migration — with no test failing.

**Fix:** Add `expect(SystemConfiguration::getInt('credit_term_days', 0))->toBe(30);` to the seeder test.

---

_Reviewed: 2026-09-08T22:34:54Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
