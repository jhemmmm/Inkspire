---
phase: 05-pos-payments
fixed_at: 2026-09-05T01:26:29Z
review_path: .planning/phases/05-pos-payments/05-REVIEW.md
iteration: 1
findings_in_scope: 10
fixed: 10
skipped: 0
status: all_fixed
---

# Phase 05: Code Review Fix Report

**Fixed at:** 2026-09-05T01:26:29Z
**Source review:** .planning/phases/05-pos-payments/05-REVIEW.md
**Iteration:** 1

**Summary:**

- Findings in scope: 10 (5 Critical, 5 Warning — the 2 Info findings, IN-01 and IN-02, are out of scope for this fix pass per `fix_scope: critical_warning`)
- Fixed: 10
- Skipped: 0

An eleventh commit was added beyond the 10 findings: applying CR-04's fix
made a pre-existing dynamic Eloquent attribute (`amount_paid`) into an
explicit typed access, which Larastan (level 7, enforced project-wide per
CLAUDE.md) correctly flagged as a new "undefined property" error. Fixed by
documenting it via `@property` docblock on `JobOrder`, matching the
model's existing convention — filed under CR-04 since it's direct fallout
from that fix, not a new finding.

**Full verification performed beyond the standard 3-tier strategy:**

- `php artisan test --compact` — 261 passed, 3 skipped (same 3 pre-existing
  skips present on the unmodified `main` baseline), 0 failed.
- `npm run types:check` (`vue-tsc --noEmit`) — 0 errors.
- `composer types:check` (Larastan level 7) — same pre-existing baseline
  errors as `main` (5, all in files this pass did not touch or touched
  only to mirror an already-accepted pattern — see note under CR-01/CR-04
  below), 0 _new_ errors after the JobOrder docblock follow-up commit.
- For CR-02, CR-04, WR-01, and WR-05, the exact new test was additionally
  run against the pre-fix code (via `git stash` of just that commit's
  source diff) to confirm it fails without the fix and passes with it —
  proving the test genuinely exercises the bug, not just the happy path.

## Fixed Issues

### CR-01: On-Credit request path bypasses all pricing validation, including the discount cap

**Files modified:** `app/Http/Requests/Cashier/CreateCreditRequestRequest.php`, `app/Http/Controllers/Cashier/CreditRequestController.php`, `tests/Feature/Owner/CreditApprovalTest.php`
**Commit:** `c4f4813`
**Applied fix:** `CreateCreditRequestRequest::rules()` now applies `PricingValidationRules::pricingRules()` (via the trait) whenever `total_amount` is still null, mirroring `SavePricingAndPaymentRequest`'s identical branch exactly as the review's fix suggested. `CreditRequestController::store()` now reads `$request->validated(...)` instead of raw `$request->input(...)` for every pricing field. Added 3 tests: valid first-visit pricing snapshots correctly, an over-cap discount is rejected, and a missing `line_amount` is rejected instead of silently defaulting to ₱0.
**Note:** `CreateCreditRequestRequest.php` now shares a pre-existing, already-accepted Larastan complaint with `SavePricingAndPaymentRequest.php` (`$this->route('jobOrder')` typed as `object|string` rather than `JobOrder`, a known Laravel/PHPStan route-model-binding limitation). This is not a new class of problem — it's the same pattern the codebase already tolerates in the sibling file this fix deliberately mirrors — so it was left as-is rather than introducing an unscoped, codebase-wide typing refactor.

### CR-02: A failed PayMongo API call permanently locks in job order pricing with no transaction to show for it

**Files modified:** `app/Http/Controllers/Cashier/PaymentController.php`, `tests/Feature/Cashier/RecordPaymentTest.php`
**Commit:** `a050408`
**Applied fix:** `storePaymongoIntent()` no longer computes-and-saves the pricing snapshot before the PayMongo API calls. Pricing is now computed (but not persisted) up front, and the actual `forceFill()`+`save()` of the pricing snapshot happens inside the same `DB::transaction()` as `Transaction::create()` and the `payment_status` update — all of which only run after the PayMongo calls succeed. A failed PayMongo call now returns with the job order still fully unpriced. Extended the existing "maya payment failing..." test with an explicit `total_amount` assertion (the review's own noted testing gap).
**Verified regression:** confirmed the new assertion fails against the pre-fix code (`total_amount` was persisted despite the PayMongo failure) and passes after the fix.

### CR-03: `cancelled_at` is enforced only in the Dashboard listing query, never in the mutation endpoints

**Files modified:** `app/Http/Controllers/Cashier/PaymentController.php`, `app/Http/Controllers/Cashier/CreditRequestController.php`, `app/Http/Controllers/Cashier/ReconciliationController.php`, `app/Http/Controllers/FrontlineStaff/JobOrderReleaseController.php`, `tests/Feature/Cashier/RecordPaymentTest.php`, `tests/Feature/Owner/CreditApprovalTest.php`, `tests/Feature/Cashier/ReconciliationTest.php`, `tests/Feature/FrontlineStaff/ReleaseGateTest.php`
**Commit:** `c53c53c`
**Applied fix:** Added `abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'))` to `PaymentController::edit()`/`store()`, `CreditRequestController::store()`, and `ReconciliationController::store()`; excluded cancelled orders from `ReconciliationController::index()`'s query via `whereNull('cancelled_at')`; added the same guard to `JobOrderReleaseController::store()` as a final backstop. Added one "cancel, then try to X" test per endpoint (4 new tests total), directly exercising the exact gap the review called out as untested.

### CR-04: Cashier Dashboard's cancellation dialog crashes on `.toFixed()` for a raw SQL aggregate that isn't cast to a number

**Files modified:** `app/Http/Controllers/Cashier/DashboardController.php`, `resources/js/pages/cashier/Dashboard.vue`, `tests/Feature/Cashier/CancellationFeeTest.php`, `app/Models/JobOrder.php` (follow-up)
**Commits:** `cf72c7f`, `033b4df` (follow-up)
**Applied fix:** Applied both options the review offered, for defense in depth: `DashboardController::index()` now casts the `withSum()` `amount_paid` aggregate to `(float)` (or leaves it `null`) before rendering, matching every other money value in this phase; `Dashboard.vue`'s `cancellationDialogBody()` also wraps it in `Number(...)` defensively, since the backend cast alone can't be regression-tested under this project's SQLite test DB (SQLite already returns numeric types; the bug is MySQL-PDO-specific, per the review's own analysis) — the frontend `Number()` wrap is what actually eliminates the crash regardless of backend serialization quirks. Follow-up commit `033b4df` documents `amount_paid` via `@property` docblock on `JobOrder` to resolve a new Larastan complaint the explicit cast introduced.
**Verified regression:** confirmed the new "casts amount_paid to a float, not a numeric string" test fails against the pre-fix backend code and passes after.

### CR-05: Credit approval/rejection never checks the receivable's current status before mutating it

**Files modified:** `app/Http/Controllers/Owner/CreditApprovalController.php`, `tests/Feature/Owner/CreditApprovalTest.php`
**Commit:** `7e94d85`
**Applied fix:** `approve()`/`reject()` now open a `DB::transaction()`, lock-and-re-read the `AccountsReceivable` row (`lockForUpdate()`), and `abort_unless($accountsReceivable->status === PendingApproval, 422, ...)` before mutating — mirroring `ConfirmPaymentIntent`'s own idempotency boundary, which the review's Issue text explicitly cites as the precedent this controller lacked. Added 3 tests: approving an already-active receivable, rejecting an already-active one, and approving an already-rejected one — all now correctly rejected with 422 and no state change.
**Status note:** marked `fixed: requires human verification` for the _locking_ guarantee specifically. The precondition-check behavior itself (an already-resolved receivable gets a 422) is directly tested and passing. The `lockForUpdate()` race-prevention mechanism — protecting against two _simultaneous_ approve/reject requests — cannot be exercised by a sequential single-process Pest test; a human (or a real concurrency/load test) should confirm this behaves correctly under genuine concurrent load before this is fully trusted in production.

## Warnings

### WR-01: Down payment amount has no upper-bound check on the very first pricing/payment visit

**Files modified:** `app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php`, `tests/Feature/Cashier/RecordPaymentTest.php`
**Commit:** `4c09345`
**Applied fix:** `withValidator()`'s after-hook now recomputes the would-be total via `ComputeJobOrderPrice` (resolved through the container, since FormRequests are constructed independently of controllers) whenever `total_amount` is still null, so the down-payment upper-bound check applies on the first visit too — skipping the check only when the pricing fields themselves already failed their own validation rules (avoiding a meaningless computation from invalid input).
**Verified regression:** confirmed the new test fails against the pre-fix validator (down payment of ₱5,000 against a ₱1,000 total was silently accepted) and passes after.

### WR-02: `amount_tendered` is validated but never persisted; Receipt's "Amount Tendered" is permanently dead

**Files modified:** `app/Concerns/PaymentValidationRules.php`, `app/Http/Controllers/Cashier/ReceiptController.php`, `resources/js/pages/cashier/Receipt.vue`
**Commit:** `e60264d`
**Applied fix:** Chose the review's simpler alternative (remove the dead code, document the field as display-only) over adding a new persisted `transactions.amount_tendered` column — the latter would be unrequested schema for an unconfirmed drawer-reconciliation feature, and CLAUDE.md's project conventions caution against expanding scope without explicit approval. Removed the always-dead `v-if` block and its prop/interface field from `Receipt.vue`, removed the corresponding hard-coded `null` from `ReceiptController::show()`, and added a doc comment on `PaymentValidationRules::paymentRules()` clarifying `amount_tendered` is a client-side-only change-preview value.

### WR-03: Cancelling a job order with a payment pending PayMongo confirmation can later "un-cancel" it

**Files modified:** `app/Http/Controllers/Cashier/CancellationController.php`, `tests/Feature/Cashier/CancellationFeeTest.php`
**Commit:** `bbe8812`
**Applied fix:** Added `abort_if($jobOrder->payment_status === PaymentStatus::PendingConfirmation, 422, ...)` to `CancellationController::store()`, matching the identical guard `CreditRequestController::store()` already had for the same reason. Added a test confirming a job order with a pending PayMongo confirmation cannot be cancelled.

### WR-04: No unique constraint on `accounts_receivable.job_order_id`, and no row locking in `CreditRequestController::store()`

**Files modified:** `database/migrations/2026_09_04_120000_add_unique_index_to_accounts_receivable_job_order_id.php` (new), `app/Http/Controllers/Cashier/CreditRequestController.php`, `tests/Feature/Owner/CreditApprovalTest.php`
**Commit:** `1d3dc8a`
**Applied fix:** Applied both remedies the review suggested: a new migration adds a unique index on `job_order_id` (enforcing at the DB level what `JobOrder::accountsReceivable()`'s own docblock already claimed as a rule), and `CreditRequestController::store()` now locks the job order row (`lockForUpdate()`) and re-checks `cancelled_at`/`payment_status` against the locked read inside the transaction, before creating the receivable. Added a test proving the DB-level unique constraint (`AccountsReceivable::factory()->create()` twice for the same job order throws `QueryException`).
**Status note:** marked `fixed: requires human verification` for the same reason as CR-05 — the `lockForUpdate()` race-prevention mechanism cannot be exercised by a sequential test. The DB-level unique constraint (the stronger, always-correct backstop) _is_ directly tested and passing regardless of the locking behavior, so a race would at worst surface as a `QueryException` rather than a silent duplicate row even if the lock somehow failed to prevent it.

### WR-05: Cancelling an On-Credit job order leaves its AccountsReceivable balance outstanding

**Files modified:** `app/Http/Controllers/Cashier/DashboardController.php`, `resources/js/pages/cashier/Dashboard.vue`, `tests/Feature/Cashier/CancellationFeeTest.php`
**Commit:** `544f989`
**Applied fix:** Chose the review's "at minimum" remedy (surface it in the dialog) rather than adding an explicit write-off step, since whether a cancelled job's AR balance should be written off is an explicit business decision this fix pass isn't positioned to make. `DashboardController::index()` now eager-loads the job order's `Active` `AccountsReceivable`, and `cancellationDialogBody()` appends an explicit warning naming the outstanding balance when one exists. Added 2 tests: the balance is surfaced when an Active receivable exists, and the field is `null` when none does.
**Verified regression:** confirmed the new test fails against the pre-fix backend (the `accounts_receivable` prop didn't exist at all) and passes after.

## Skipped Issues

None — all 10 in-scope findings were fixed.

---

_Fixed: 2026-09-05T01:26:29Z_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
