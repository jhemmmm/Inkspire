---
phase: 05-pos-payments
reviewed: 2026-09-05T00:00:00Z
depth: standard
files_reviewed: 41
files_reviewed_list:
    - app/Actions/POS/ComputeJobOrderPrice.php
    - app/Actions/POS/ConfirmPaymentIntent.php
    - app/Concerns/PaymentValidationRules.php
    - app/Concerns/PricingValidationRules.php
    - app/Enums/AccountsReceivableStatus.php
    - app/Http/Controllers/Cashier/CancellationController.php
    - app/Http/Controllers/Cashier/CreditRequestController.php
    - app/Http/Controllers/Cashier/DashboardController.php
    - app/Http/Controllers/Cashier/PaymentController.php
    - app/Http/Controllers/Cashier/ReceiptController.php
    - app/Http/Controllers/Cashier/ReconciliationController.php
    - app/Http/Controllers/FrontlineStaff/JobOrderReleaseController.php
    - app/Http/Controllers/FrontlineStaff/QueueEntryController.php
    - app/Http/Controllers/Owner/CreditApprovalController.php
    - app/Http/Controllers/Webhooks/PaymongoWebhookController.php
    - app/Http/Requests/Cashier/CancelJobOrderRequest.php
    - app/Http/Requests/Cashier/CreateCreditRequestRequest.php
    - app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php
    - app/Http/Requests/FrontlineStaff/ReleaseJobOrderRequest.php
    - app/Http/Requests/Owner/ApproveCreditRequest.php
    - app/Http/Requests/Owner/RejectCreditRequest.php
    - app/Models/AccountsReceivable.php
    - app/Models/JobOrder.php
    - app/Models/PricingEntry.php
    - app/Models/SystemConfiguration.php
    - app/Models/Transaction.php
    - app/Policies/AccountsReceivablePolicy.php
    - bootstrap/app.php
    - config/paymongo.php
    - database/factories/AccountsReceivableFactory.php
    - database/migrations/2026_09_04_100000_create_accounts_receivable_table.php
    - database/migrations/2026_09_04_110000_add_released_at_to_job_orders_table.php
    - database/seeders/SystemConfigurationSeeder.php
    - resources/js/components/AppSidebar.vue
    - resources/js/components/AppSidebarHeader.vue
    - resources/js/components/PaymentQrCode.vue
    - resources/js/config/nav/accounting-staff.ts
    - resources/js/config/nav/owner.ts
    - resources/js/pages/accounting-staff/Dashboard.vue
    - resources/js/pages/cashier/Dashboard.vue
    - resources/js/pages/cashier/JobOrderPayment.vue
    - resources/js/pages/cashier/Receipt.vue
    - resources/js/pages/frontline-staff/QueueList.vue
    - resources/js/pages/owner/CreditRequests.vue
    - routes/owner.php
    - routes/portals.php
    - routes/web.php
    - tests/Feature/Cashier/CancellationFeeTest.php
    - tests/Feature/Cashier/ReceiptTest.php
    - tests/Feature/Cashier/ReconciliationTest.php
    - tests/Feature/Cashier/RecordPaymentTest.php
    - tests/Feature/FrontlineStaff/ReleaseGateTest.php
    - tests/Feature/Owner/CreditApprovalTest.php
    - tests/Feature/Webhooks/PaymongoWebhookTest.php
    - tests/Unit/Actions/ConfirmPaymentIntentTest.php
    - tests/Unit/SystemConfigurationTest.php
findings:
    critical: 5
    warning: 5
    info: 2
    total: 12
status: issues_found
---

# Phase 05: Code Review Report

**Reviewed:** 2026-09-05T00:00:00Z
**Depth:** standard
**Files Reviewed:** 56 (listed above; 41 unique source/test files after de-duplication of the required-reading list)
**Status:** issues_found

## Summary

Reviewed Phase 5's pricing, POS payment (Cash/Bank/GCash/Maya), receipt, PayMongo webhook, manual reconciliation, cancellation-with-fee-netting, On-Credit request/approval, and release-gate code. The core happy-path flow (price → pay → receipt; GCash/Maya → webhook/reconcile → paid; request credit → approve → release) is implemented carefully, with real attention to idempotency (`ConfirmPaymentIntent`'s locked re-read) and server-authoritative pricing (`ComputeJobOrderPrice` as the single source of truth, never trusting a client total).

However, five BLOCKER-level gaps were found, all of which break invariants the rest of the codebase clearly intends to enforce:

1. The On-Credit request path (`CreditRequestController`) reads pricing-snapshot input completely unvalidated (confirming the flag raised by the plan-06 executor's own SUMMARY.md), letting a Cashier price a job order at ₱0 or apply an unlimited discount, and risking an uncaught 500 from an FK violation.
2. A failed PayMongo API call during "Generate QR Code" permanently locks in a job order's pricing snapshot with no corresponding transaction, because the pricing `save()` happens outside the try/catch and outside any transaction boundary.
3. `cancelled_at` — explicitly documented elsewhere as "the authoritative no-longer-actionable signal" — is checked only by the Dashboard's listing query, never by the actual payment, credit-request, reconciliation, or release mutation endpoints.
4. The Cashier Dashboard's cancellation-fee dialog calls `.toFixed()` directly on a raw SQL `SUM()` aggregate that (unlike every other money value in this phase) was never cast to a number, which will throw a `TypeError` in the MySQL-backed production environment this project targets.
5. The Owner's credit approve/reject actions never verify the receivable is still `pending_approval` before mutating it, unlike every sibling controller in this phase, allowing a rejected credit request to be silently flipped back to active.

## Critical Issues

### CR-01: On-Credit request path bypasses all pricing validation, including the discount cap

**File:** `app/Http/Requests/Cashier/CreateCreditRequestRequest.php:30-35`, `app/Http/Controllers/Cashier/CreditRequestController.php:41-67`
**Issue:** `CreateCreditRequestRequest::rules()` returns an empty array (deliberately, per the plan). When On-Credit is the _first_ payment action taken on a job order (`$jobOrder->total_amount === null`), `CreditRequestController::store()` reads `pricing_entry_id`, `line_amount`, `rush_fee_applied`, `discount_type`, and `discount_value` straight from `$request->input()` with no validation at all, unlike the identical snapshot logic in `PaymentController::store()`/`storePaymongoIntent()`, which always goes through `SavePricingAndPaymentRequest`'s `PricingValidationRules::pricingRules()`. Concretely, this means:

- `line_amount` has no `required`/`numeric`/`min:0` check — an absent or non-numeric value casts to `0.0`, silently pricing the job order at ₱0.
- `discount_type`/`discount_value` skip the cap-enforcing closure in `PricingValidationRules::pricingRules()` entirely (`discount_cap_percentage`/`discount_cap_flat_amount` from `system_configurations`), so a Cashier can apply an unlimited discount by routing the very first pricing action through On-Credit instead of the Payment card.
- `pricing_entry_id` skips `exists:pricing_database,id`. `job_orders.pricing_entry_id` has a real DB foreign key (`database/migrations/2026_09_04_090001_add_payment_columns_to_job_orders_table.php`), so an invalid/garbage value throws an uncaught `QueryException` (HTTP 500) in production (MySQL, strict mode) instead of a validation error.

This is the exact gap flagged by the plan-06 executor's own SUMMARY.md — confirmed real, and severe enough to be a BLOCKER because it's a live financial-control bypass (unlimited discount, ₱0 pricing) reachable by any Cashier, not just a robustness nit.
**Fix:**

```php
// app/Http/Requests/Cashier/CreateCreditRequestRequest.php
use App\Concerns\PricingValidationRules;

class CreateCreditRequestRequest extends FormRequest
{
    use PricingValidationRules;

    public function rules(): array
    {
        // Only needed the first time a job order is priced — mirror
        // SavePricingAndPaymentRequest's own branch.
        return $this->route('jobOrder')->total_amount !== null
            ? []
            : $this->pricingRules();
    }
}
```

And switch `CreditRequestController::store()` to read `$request->validated(...)` instead of `$request->input(...)` for these fields, matching `PaymentController::store()`.

---

### CR-02: A failed PayMongo API call permanently locks in job order pricing with no transaction to show for it

**File:** `app/Http/Controllers/Cashier/PaymentController.php:186-249`
**Issue:** In `storePaymongoIntent()`, when the job order hasn't been priced yet, pricing is computed and persisted directly:

```php
$jobOrder->forceFill([...])->save();   // line 194-203 — committed immediately, no transaction
```

This happens _before_ the `try { ... } catch (Throwable $e) { ...; return back(); }` block (lines 214-249) that wraps the actual PayMongo `paymentIntent()->create()` / `paymentMethod()->create()` / `attach()` calls. If any of those throw — the exact scenario the catch block exists to handle (PayMongo outage, bad keys, network blip) — the method returns an error toast, but `total_amount`/`base_price_snapshot`/etc. are already saved to the database with **no** corresponding `Transaction` row.

Consequences:

- `SavePricingAndPaymentRequest::rules()` checks `$this->route('jobOrder')->total_amount !== null` to decide whether to validate pricing fields at all. After this failure, that's now true, so a retry (even with Cash) silently skips pricing validation and re-prices nothing — the Cashier is stuck with whatever total_amount happened to be computed from the failed attempt.
- `PaymentController::edit()`'s `hasExistingTransactions` prop is still `false` (no Transaction exists), so the frontend (`JobOrderPayment.vue`, `v-if="!hasExistingTransactions"`) keeps rendering the Pricing card as if it were still editable — misleading the Cashier into thinking a different product/discount can still be chosen, when the backend has already locked in the old total.
- This is inconsistent with the sibling Cash/Bank Transfer path in the same file (`store()`, lines 99-151), where the pricing `forceFill(...)` only happens _inside_ the same `DB::transaction()` closure as the `Transaction::create()` — so a failure there rolls back atomically.

Untested: `tests/Feature/Cashier/RecordPaymentTest.php`'s "a maya payment failing to create a paymongo intent..." test (lines 203-222) only asserts `Transaction::count() === 0` and `payment_status !== PendingConfirmation` — it never asserts `total_amount` stayed `null`, so this regression would not be caught today.
**Fix:** Defer the pricing `save()` until after the PayMongo calls succeed (or wrap the whole method, including the pricing snapshot, in one `DB::transaction()` alongside the eventual `Transaction::create()` so a thrown exception rolls everything back):

```php
$computed = null;
if ($jobOrder->total_amount === null) {
    $computed = ($this->computeJobOrderPrice)(...);
}

try {
    $intent = Paymongo::paymentIntent()->create([...]);
    // ...
} catch (Throwable $e) {
    return back(); // job order still unpriced — safe to retry with different pricing
}

DB::transaction(function () use ($jobOrder, $computed, ...) {
    if ($computed !== null) {
        $jobOrder->forceFill([... $computed])->save();
    }
    Transaction::create([...]);
    $jobOrder->forceFill(['payment_status' => PaymentStatus::PendingConfirmation])->save();
});
```

---

### CR-03: `cancelled_at` is enforced only in the Dashboard listing query, never in the mutation endpoints

**File:** `app/Http/Controllers/Cashier/PaymentController.php:37-41,86-91`, `app/Http/Controllers/Cashier/CreditRequestController.php:29-39`, `app/Http/Controllers/Cashier/ReconciliationController.php:27-41,53-60`, `app/Http/Controllers/FrontlineStaff/JobOrderReleaseController.php:24-33`
**Issue:** `CancellationController::store()` documents `cancelled_at` as "the authoritative 'no longer actionable' signal" (line 68-69), and `DashboardController::index()` filters it out of the Cashier's job-order list (`whereNull('cancelled_at')`, line 29). But none of the actual state-mutating endpoints re-check it:

- `PaymentController::edit()`/`store()` only check `status` is `ReadyForProduction`/`DesignApproved` and `payment_status !== Paid` — a cancelled job order can still keep one of those statuses (cancellation never touches `status`), so a Cashier who navigates directly to `/cashier/job-orders/{id}/payment` (bookmark, back button, guessed/old link) can still record a full payment against an already-cancelled order.
- `CreditRequestController::store()` has the same gap — it checks `status` and `payment_status` but never `cancelled_at`.
- `ReconciliationController::index()`/`store()` never check it either, so a cancelled order with a pending PayMongo intent still surfaces for reconciliation and can still be confirmed to `Paid`.
- `JobOrderReleaseController::store()` checks `released_at` and `payment_status`, but not `cancelled_at` — so if any of the above paths succeeds in paying a cancelled order, it can then also be released.

This is a systemic omission (every one of these controllers carefully guards several _other_ preconditions but misses this one consistently), not an isolated typo, and it directly undermines the "no longer actionable" invariant the rest of the system relies on. No test in this phase exercises "cancel, then try to pay/credit-request/release."
**Fix:** Add the same guard everywhere `cancelled_at` matters, e.g.:

```php
abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
```

at the top of `PaymentController::edit()`/`store()`, `CreditRequestController::store()`, `ReconciliationController::store()` (and exclude cancelled orders from `ReconciliationController::index()`'s query and `JobOrderReleaseController::store()`'s guard chain).

---

### CR-04: Cashier Dashboard's cancellation dialog crashes on `.toFixed()` for a raw SQL aggregate that isn't cast to a number

**File:** `resources/js/pages/cashier/Dashboard.vue:70-85`, `app/Http/Controllers/Cashier/DashboardController.php:30`
**Issue:** `DashboardController::index()` computes `amount_paid` via a raw SQL aggregate:

```php
->withSum(['transactions as amount_paid' => fn ($query) => $query->where('status', TransactionStatus::Completed->value)], 'amount')
```

Unlike every other money value passed to Inertia in this phase (`PaymentController`, `ReceiptController`, `ReconciliationController` all explicitly `(float)`-cast sums before rendering), this one is passed through untouched. Laravel's decimal-cast columns already serialize as strings over the wire — confirmed by `tests/Feature/Cashier/ReceiptTest.php:33` asserting `->where('jobOrder.total_amount', '1000.00')` (a string, not a number) — and MySQL's `SUM()` over a `DECIMAL` column returns a PHP string via PDO. So in the MySQL-backed production environment this project targets (per `CLAUDE.md`: "MySQL in production... currently SQLite in dev"), `jobOrder.amount_paid` arrives client-side as the string `"700.00"`, not the number `700`.

`cancellationDialogBody()` in `cashier/Dashboard.vue` does:

```ts
const downPayment = jobOrder.amount_paid ?? 0;
...
return `... ₱${downPayment.toFixed(2)} covers it ...`;   // line 80
...
return `... ₱${downPayment.toFixed(2)} covers part of it ...`;  // line 85
```

`String.prototype.toFixed` does not exist — calling it on a string throws `TypeError: downPayment.toFixed is not a function`, crashing the render of the cancellation confirmation dialog for exactly the case (job order with an existing down payment, design already started) that the fee-netting feature (D-04/D-05) exists to handle. Every other money value in this phase's Vue code (`accounting-staff/Dashboard.vue`'s `money()`, `Receipt.vue`'s `money()`, `JobOrderPayment.vue`'s `breakdown`) is correctly wrapped in `Number(...)` first; this is the one spot that was missed.
**Fix:** Either cast in the backend to match the rest of the codebase's convention:

```php
'jobOrders' => JobOrder::query()
    ->...
    ->get([...])
    ->through(fn ($jo) => tap($jo, fn ($jo) => $jo->amount_paid = (float) $jo->amount_paid)), // or map after get()
```

or, simpler and consistent with the rest of this file, fix the Vue side:

```ts
const downPayment = Number(jobOrder.amount_paid ?? 0);
```

---

### CR-05: Credit approval/rejection never checks the receivable's current status before mutating it

**File:** `app/Http/Controllers/Owner/CreditApprovalController.php:41-56,63-78`, `app/Policies/AccountsReceivablePolicy.php:27-40`
**Issue:** `CreditApprovalController::approve()` and `reject()` unconditionally `forceFill` the `AccountsReceivable`'s `status`/`approved_by`/`approved_at` and the associated job order's `payment_status`, with no precondition check that the receivable is still `pending_approval`. `AccountsReceivablePolicy::approve()`/`reject()` only check `$actor->role === UserRole::Owner` — no status check either.

Every other state-mutating controller in this phase explicitly guards its preconditions before mutating money-adjacent state (`CancellationController` checks `cancelled_at`/`payment_status`; `CreditRequestController` checks `status`/`payment_status`; `JobOrderReleaseController` checks `released_at`/`payment_status`; `ConfirmPaymentIntent` uses a locked re-read specifically to make double-processing a no-op). This controller has no equivalent guard, so:

- A double-submitted/replayed `approve` or `reject` request (slow network retry, double click, or a stale browser tab with an old URL) can re-execute against an already-resolved receivable with no error.
- More concretely, nothing stops calling `reject` on an already-`active` receivable (flipping the job order back to `credit_rejected` after it was already approved and possibly released), or calling `approve` on an already-`rejected` one (silently reversing the Owner's own prior decision and re-unlocking release via `payment_status = OnCredit`) — directly contradicting the `reject()` docblock's stated rule: "the job order stays flagged Credit Rejected until the Cashier takes a different action" (D-07).

`tests/Feature/Owner/CreditApprovalTest.php` does not exercise either scenario.
**Fix:**

```php
public function approve(ApproveCreditRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
{
    abort_unless(
        $accountsReceivable->status === AccountsReceivableStatus::PendingApproval,
        422,
        __('This credit request has already been resolved.'),
    );

    DB::transaction(function () use ($request, $accountsReceivable): void {
        // ...
    });
    // ...
}
```

Apply the same guard to `reject()`.

## Warnings

### WR-01: Down payment amount has no upper-bound check on the very first pricing/payment visit

**File:** `app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php:51-75`
**Issue:** `withValidator()`'s after-hook only checks `down_payment_amount` against `remainingBalance` when `$jobOrder->total_amount !== null`. On the very first visit (pricing not yet snapshotted), `$remainingBalance` is `null` and the check is skipped entirely — there's no upper bound at all on `down_payment_amount` relative to the total being computed in the very same request. A Cashier can submit a `down_payment_amount` far exceeding the freshly computed total; `PaymentController::store()` will record the full amount as a `Transaction` and immediately flip the job order to `Paid` (since `amount_paid >= total_amount`), silently converting an erroneous over-large "down payment" into a full payment with no warning.
**Fix:** Compute the would-be total inline (or move `ComputeJobOrderPrice` into the validator) so the check also applies on the first visit:

```php
$jobOrder = $this->route('jobOrder');
$total = $jobOrder->total_amount ?? /* recompute from $this->input(...) using ComputeJobOrderPrice */;
if ($downPaymentAmount > $total) {
    $validator->errors()->add('down_payment_amount', __('Down payment cannot exceed the total.'));
}
```

### WR-02: `amount_tendered` is validated but never persisted; Receipt's "Amount Tendered" is permanently dead

**File:** `app/Concerns/PaymentValidationRules.php:27`, `app/Http/Controllers/Cashier/PaymentController.php:128-137`, `app/Http/Controllers/Cashier/ReceiptController.php:42-43`, `resources/js/pages/cashier/Receipt.vue:141-156`
**Issue:** `amount_tendered` is `required_if:payment_method,cash` and validated, and the frontend computes a live "change" preview from it — but `PaymentController::store()`'s `Transaction::create()` never includes it (there's no `amount_tendered` column on `transactions` at all), so it's discarded after validation. `ReceiptController::show()` hard-codes `'amount_tendered' => null` for every transaction, and `Receipt.vue`'s corresponding block (`v-if="latestTransaction?.amount_tendered !== null && ... !== undefined"`) can never render. Either this is an unfinished part of the feature (cash tendered/change should be persisted for drawer reconciliation) or the validation/UI should be simplified to reflect that it's a client-side-only convenience calculation.
**Fix:** Either add an `amount_tendered` column to `transactions` and persist it, or remove the always-dead `v-if` block from `Receipt.vue` and note in `PaymentValidationRules` that the field is display-only.

### WR-03: Cancelling a job order with a payment pending PayMongo confirmation can later "un-cancel" it

**File:** `app/Http/Controllers/Cashier/CancellationController.php:32-33`
**Issue:** `store()` only blocks cancellation when `payment_status === Paid`. A job order with `payment_status === PendingConfirmation` (an in-flight GCash/Maya payment) can still be cancelled. If the webhook or a later manual reconciliation then resolves that same pending transaction as `succeeded`, `ConfirmPaymentIntent` will mark it `Completed` and flip `payment_status` back to `Paid`/`PartiallyPaid` on a job order whose `cancelled_at` is already set — an inconsistent "cancelled but paid" state with no reconciliation path. `CreditRequestController::store()` (line 35-39) already explicitly blocks `PendingConfirmation` for the analogous reason; this controller should too.
**Fix:**

```php
abort_if(
    $jobOrder->payment_status === PaymentStatus::PendingConfirmation,
    422,
    __('This job order has a payment awaiting confirmation. Resolve it before cancelling.'),
);
```

### WR-04: No unique constraint on `accounts_receivable.job_order_id`, and no row locking in `CreditRequestController::store()`

**File:** `database/migrations/2026_09_04_100000_create_accounts_receivable_table.php:14-23`, `app/Http/Controllers/Cashier/CreditRequestController.php:41-82`
**Issue:** `JobOrder::accountsReceivable()` is a `hasOne`, and the code comment on that relationship states "at most one open credit request/receivable per job order (D-08)" — but nothing enforces this at the database level (no `unique('job_order_id')`), and the precondition checks in `CreditRequestController::store()` (lines 29-39) run before the `DB::transaction()`, on an un-locked read. A double-submitted request (double-click, slow network retry) can race past both checks and create two `AccountsReceivable` rows for the same job order.
**Fix:** Add a unique index on `job_order_id` in a follow-up migration, and/or lock the job order row (`JobOrder::query()->whereKey($jobOrder->id)->lockForUpdate()->first()`) inside the transaction before re-checking `payment_status`.

### WR-05: Cancelling an On-Credit job order leaves its AccountsReceivable balance outstanding

**File:** `app/Http/Controllers/Cashier/CancellationController.php:41-71`
**Issue:** The fee-netting calculation (`$existingDownPayment`) sums only completed `Transaction` rows, ignoring any `Active` `AccountsReceivable` balance tied to the job order (an On-Credit job order typically has no completed Transaction at all — the money was never actually collected). Cancelling such a job order charges the full cancellation fee as a new cash Transaction but leaves the original AR balance (potentially the full job cost) untouched and still owed, with no write-off/adjustment step. Whether the customer should still owe the full amount for a cancelled, un-produced job is a business decision that should be made explicit rather than left as a byproduct of two independent code paths that don't know about each other.
**Fix:** At minimum, surface this in the cancellation confirmation dialog when an `Active` AccountsReceivable exists for the job order, and/or add an explicit write-off step.

## Info

### IN-01: Decimal-cast money fields serialize as JSON strings, but TypeScript interfaces type them as `number`

**File:** Multiple (`resources/js/pages/cashier/Dashboard.vue`, `resources/js/pages/accounting-staff/Dashboard.vue`, `resources/js/pages/cashier/JobOrderPayment.vue`, `resources/js/pages/cashier/Receipt.vue`, `resources/js/pages/owner/CreditRequests.vue`)
**Issue:** Every `decimal:2`-cast Eloquent attribute (`total_amount`, `base_price_snapshot`, `rush_fee_amount`, `discount_amount`, `balance`, etc.) and raw SQL aggregate (`withSum`) serializes as a numeric _string_ over the wire (confirmed by `ReceiptTest.php`'s `'jobOrder.total_amount' => '1000.00'` assertion), yet nearly every TypeScript interface in this phase declares these fields `number | null`. Most call sites defensively wrap with `Number(...)` before formatting, masking the mismatch — but CR-04 shows exactly what happens the one time a call site doesn't. `vue-tsc --noEmit` (`npm run types:check`) cannot catch this because the type declarations lie about the real runtime shape.
**Fix:** Either have controllers consistently `(float)`-cast every money value before passing to `Inertia::render()` (already the convention in `PaymentController`/`ReceiptController`/`ReconciliationController`), or type these interface fields as `number | string | null` and centralize the `Number(...)` coercion in one shared helper used everywhere.

### IN-02: The "snapshot pricing on first visit" block is duplicated verbatim three times

**File:** `app/Http/Controllers/Cashier/CreditRequestController.php:50-67`, `app/Http/Controllers/Cashier/PaymentController.php:102-120,186-204`
**Issue:** The same ~15-line `if ($jobOrder->total_amount === null) { ... $this->computeJobOrderPrice(...); $jobOrder->forceFill([...]); }` block is copy-pasted three times across two controllers. CR-02 exists specifically because one of the three copies wasn't wrapped in the same transaction boundary as the other two — a direct symptom of this duplication.
**Fix:** Extract a shared `PriceJobOrder` invokable action (parallel to `ComputeJobOrderPrice`/`ConfirmPaymentIntent`) that takes the job order + validated/raw inputs and performs the forceFill, called identically from all three call sites.

---

_Reviewed: 2026-09-05T00:00:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
