---
status: complete
---

# Quick Task 260927-va3: Fix 5 code-review bugs — pricing lock, credit snapshot, Type A DPI, admin outstanding — Summary

One-liner: Consolidated four disagreeing "can pricing still change?" checks into `JobOrder::pricingIsEditable()`, fixed Type A DPI validation to honor intake `width_ft`/`height_ft`, and excluded written-off orders from the Admin outstanding-balance tile.

## What changed, per bug

**Bugs 1 + 2 + 3 (shared root cause — pricing-lock predicate):**

- Added `JobOrder::pricingIsEditable(): bool` (`app/Models/JobOrder.php`) — the single source of truth for "can this job order's pricing still be changed?" Returns `false` when cancelled, or when `payment_status` is `Paid`, `WrittenOff`, `CreditPendingApproval`, `OnCredit`, or `PendingConfirmation`. `CreditRejected` is deliberately excluded (a rejected credit request leaves the order priced and unpaid, still editable). Otherwise `true` only while no transactions exist, using the loaded `transactions` relation when eager-loaded (mirrors `outstandingBalance()`'s pattern), else a fresh query.
- `PaymentController::store()` (Cash/Bank Transfer path): replaced `if (! $jobOrder->transactions()->exists())` with `if ($jobOrder->pricingIsEditable())`.
- `PaymentController::storePaymongoIntent()` (bug 2 fix): replaced `if ($jobOrder->total_amount === null)` with `if ($jobOrder->pricingIsEditable())` — a rush fee/discount edited on an already-totalled, transaction-less order is now correctly recomputed instead of silently ignored.
- `PaymentController::edit()`: replaced the `hasExistingTransactions` prop with `pricingLocked` (`! $jobOrder->pricingIsEditable()`), so the form the Cashier sees agrees with what `store()` will save — including the On-Credit case the old prop got wrong (an Admin-approved On-Credit order has zero transactions).
- `SavePricingAndPaymentRequest::pricingIsStillEditable()`: collapsed to a one-line delegate to `$this->route('jobOrder')->pricingIsEditable()`. Removed now-unused `PaymentStatus` import.
- `CreditRequestController::store()` (bug 3 fix): replaced `if ($jobOrder->total_amount === null)` with `if ($jobOrder->pricingIsEditable())` — a Cashier requesting On-Credit on an already-totalled, transaction-less order now gets edited pricing snapshotted before the AR balance is computed.
- `CreateCreditRequestRequest::rules()`: replaced `$this->route('jobOrder')->total_amount !== null ? [] : $this->pricingRules()` with `$this->route('jobOrder')->pricingIsEditable() ? $this->pricingRules() : []`.
- `resources/js/pages/cashier/JobOrderPayment.vue`: renamed `hasExistingTransactions` → `pricingLocked` prop throughout (interface, `pricingSummary`/`targetAmount` computeds, the Pricing-card `v-if`, and the Payment-card "Remaining Balance" `v-if`) — grep-confirmed no remaining references anywhere in `resources/js` or `app/`.

**Bug 4 (Type A DPI check ignores intake width/height):**

- `ValidateJobOrderFile::__invoke()` / `checkEffectiveResolution()` (`app/Actions/JobOrder/ValidateJobOrderFile.php`): added `?float $widthFt = null, ?float $heightFt = null` params. When both are present and positive, the print size is computed from `width_ft`/`height_ft` (feet → inches) instead of the `print_size` catalog lookup, and the DPI-failure message now reports `"{W} × {H} ft"` instead of the raw `print_size` string (which was `null`/empty for a custom-size order with no catalog match). Added `private static function formatFeet()` to trim `10.00` → `"10"`, `10.50` → `"10.5"`.
- Updated all three callers to pass `$jobOrder->width_ft`/`height_ft` (cast to `float`, since they're `decimal:2`-cast columns returning strings) or raw request input: `FrontlineStaff/JobOrderController::replaceFile()`, `FrontlineStaff/QueueEntryController::applyIntakeOutcome()`, and `JobOrderValidationRules::rejectUnusableTypeAFiles()` (reads `$prefix.'width_ft'`/`'height_ft'` from raw input since no `JobOrder` instance exists yet at validation time).

**Bug 5 (Admin dashboard counts written-off orders as outstanding):**

- `Admin\DashboardController::unpaidBalances()`: added `use App\Enums\PaymentStatus;` import and `->where('payment_status', '!=', PaymentStatus::WrittenOff->value)` right after `->whereNotNull('total_amount')`, so a written-off balance no longer inflates the dashboard's unpaid count or outstanding-amount tile.

## Files changed

- `app/Models/JobOrder.php` — added `pricingIsEditable()`
- `app/Http/Controllers/Cashier/PaymentController.php` — both pricing gates + `edit()` prop rename
- `app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php` — delegate to shared predicate
- `app/Http/Controllers/Cashier/CreditRequestController.php` — pricing gate
- `app/Http/Requests/Cashier/CreateCreditRequestRequest.php` — pricing gate
- `resources/js/pages/cashier/JobOrderPayment.vue` — prop rename (5 sites)
- `app/Actions/JobOrder/ValidateJobOrderFile.php` — width/height override + `formatFeet()`
- `app/Http/Controllers/FrontlineStaff/JobOrderController.php` — caller update
- `app/Http/Controllers/FrontlineStaff/QueueEntryController.php` — caller update
- `app/Concerns/JobOrderValidationRules.php` — caller update
- `app/Http/Controllers/Admin/DashboardController.php` — written-off exclusion

Tests added/updated:

- `tests/Feature/Cashier/RecordPaymentTest.php` — 2 new tests (bug 2: gcash charges edited pricing; bug 1: cash against on-credit order leaves total untouched)
- `tests/Feature/Cashier/CreditRequestTest.php` — 1 new test (bug 3: credit request snapshots edited pricing)
- `tests/Feature/Cashier/CashierPagesTest.php` — 2 assertions updated/added for `pricingLocked` prop
- `tests/Feature/FrontlineStaff/IntakeCatalogAndRoutingTest.php` — 1 new test (bug 4: custom-size DPI check)
- `tests/Feature/Admin/DashboardTest.php` — 1 new test (bug 5: written-off exclusion)
- `tests/Feature/Admin/CreditApprovalTest.php` — 1 existing test updated (see Deviations)
- `tests/Feature/Cashier/ProductionCompatibilityTest.php` — 1 existing parametrized test updated (see Deviations)

## Verification results

**Targeted tests (Task 1):** `RecordPaymentTest.php`, `CreditRequestTest.php`, `RushFeeAndPartialReceiptTest.php` — 24 passed, 133 assertions.

**Targeted tests (Task 2):** `CashierPagesTest.php` — 7 passed, 64 assertions.

**Targeted tests (Task 3):** `ValidateJobOrderFileTest.php`, `IntakeCatalogAndRoutingTest.php`, `JobOrderTypeValidationTest.php`, `DashboardTest.php` — 31 passed, 147 assertions.

**Full suite:** 709 passed / 713 total, 3514 assertions, 3 skipped. The 1 remaining failure is the known pre-existing, unrelated one: `ReportExportTest` xlsx 100-row-cap export (`assertStatus(200)` gets 302 — environment lacks the xlsx writer's zip extension, per `.planning/phases/08-expenses-reporting/deferred-items.md`).

**Pint:** `vendor/bin/pint --dirty --format agent` — passed, no changes needed after final edits.

**PHPStan:** `vendor/bin/phpstan analyse --no-progress --debug` — 22 errors (baseline was 24; net **decrease**, not an increase). The decrease is incidental: collapsing `SavePricingAndPaymentRequest::pricingIsStillEditable()`'s 3-statement body (each an `object|string`-typed property/method access PHPStan already flagged) into a single `pricingIsEditable()` delegate call (1 flagged access) removed 2 pre-existing "undefined property/method on object|string" errors from `$this->route('jobOrder')`'s untyped return. All remaining 22 errors are pre-existing, unrelated to this task's files' logic (untyped `FormRequest::route()` returns, magic `withSum` aggregate properties, report-builder covariance, etc.).

**TypeScript:** `npm run types:check` (`vue-tsc --noEmit`) — passed, no output/errors.

**Frontend lint/format:** `npm run check:fix` — passed ("Formatting completed for checked files", "Found no warnings or lint errors in 112 files"). Verified via `find -mmin -15` that no files outside this task's intended edit list were changed, other than two Wayfinder-generated files (`resources/js/actions/App/Http/Controllers/Admin/DashboardController.ts`, `resources/js/routes/admin/index.ts`) whose mtimes were touched by a background dev-watcher process but whose content shows zero diff against HEAD — no action needed.

## Deviations from plan

**1. [Rule 1 — test fix] Updated two existing tests that exercised the exact bug-3 gap being fixed**

- **Found during:** Full suite run after Tasks 1–3.
- **Issue:** `tests/Feature/Admin/CreditApprovalTest.php` ("a cashier can request OnCredit for an eligible job order...") and `tests/Feature/Cashier/ProductionCompatibilityTest.php` ("an OnCredit request can still be made for a job order that has already advanced into production") both created a `JobOrder` with `total_amount` seeded directly (no transactions, default `payment_status`) and posted to the credit-request route with an **empty body**, relying on the old `total_amount !== null` check to skip pricing-field validation. Under the corrected `pricingIsEditable()` predicate this state (no transactions) is genuinely still editable — the exact bug-3 gap the plan fixes — so pricing fields are now correctly required, and the empty-body POSTs failed validation (422 instead of redirect).
- **Fix:** Updated both tests to create a `PricingEntry` and submit `pricing_entry_id`, `line_amount => 1000`, `rush_fee_applied => false` alongside the request, matching the already-established pattern used by this file's own "very first pricing action" test (CR-01). Assertions on the resulting `total_amount`/AR balance (`1000.0`) are unchanged since the submitted `line_amount` reproduces the same total.
- **Files modified:** `tests/Feature/Admin/CreditApprovalTest.php`, `tests/Feature/Cashier/ProductionCompatibilityTest.php`.
- **Verified:** Both files pass (33 tests, 136 assertions combined); full suite re-run confirms only the known pre-existing xlsx failure remains.

No other deviations — Tasks 1, 2, and 3 were implemented exactly as specified in the plan.

## Anything unverified

- Nothing left unverified within the scope of this environment. The one skipped/failing area (xlsx export) is explicitly called out in the plan as a known, unrelated, pre-existing gap (no `ext-zip` in this sandbox) and was left untouched.
- Per the plan's constraints, **no commit was made**. All changes remain uncommitted in the working tree, on top of the repository's pre-existing large uncommitted state. No `git add`/`commit`/`stash`/`checkout`/`reset` was run at any point.

## Self-Check

Verifying key file existence and no unintended git-state changes:

```
FOUND: app/Models/JobOrder.php
FOUND: app/Http/Controllers/Cashier/PaymentController.php
FOUND: app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php
FOUND: app/Http/Controllers/Cashier/CreditRequestController.php
FOUND: app/Http/Requests/Cashier/CreateCreditRequestRequest.php
FOUND: resources/js/pages/cashier/JobOrderPayment.vue
FOUND: app/Actions/JobOrder/ValidateJobOrderFile.php
FOUND: app/Http/Controllers/FrontlineStaff/JobOrderController.php
FOUND: app/Http/Controllers/FrontlineStaff/QueueEntryController.php
FOUND: app/Concerns/JobOrderValidationRules.php
FOUND: app/Http/Controllers/Admin/DashboardController.php
FOUND: tests/Feature/Cashier/RecordPaymentTest.php
FOUND: tests/Feature/Cashier/CreditRequestTest.php
FOUND: tests/Feature/Cashier/CashierPagesTest.php
FOUND: tests/Feature/FrontlineStaff/IntakeCatalogAndRoutingTest.php
FOUND: tests/Feature/Admin/DashboardTest.php
FOUND: tests/Feature/Admin/CreditApprovalTest.php
FOUND: tests/Feature/Cashier/ProductionCompatibilityTest.php
```

No commits were created this session (confirmed: this file is written but not staged/committed, per the plan's DO NOT COMMIT instruction).

## Self-Check: PASSED
