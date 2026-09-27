---
phase: 07-accounts-receivable
plan: 08
subsystem: payments
tags:
    [
        laravel,
        larastan,
        pest,
        accounts-receivable,
        payment-status,
        terminal-state-guards,
    ]

# Dependency graph
requires:
    - phase: 07-accounts-receivable
      provides: 07-06/07-07's AR-04 write-off guards, WriteOffApprovalController's locked-re-read + outstandingBalance pattern
provides:
    - 'JobOrder::outstandingBalance() as the single source of truth for the derived outstanding balance, routed through by 11 call sites across 9 files'
    - 'CreditApprovalController::approve() guarded against CR-01 (settled-while-pending race), both preventively (PaymentController) and authoritatively (CreditApprovalController)'
    - 'ConfirmPaymentIntent guarded against overwriting an already-WrittenOff payment_status (closes the previously-deferred 6th payment_status writer)'
    - 'CollectionStatusController::update() locked-re-read boundary matching every other mutating AR controller in the phase'
    - 'CollectionLetterController::show() 404s for a Paid/WrittenOff collection_status'
    - 'Cashier Dashboard action dropdown offers a reachable payment action for on_credit/credit_rejected, and a non-actionable item for credit_pending_approval'
    - 'Final grep-based audit confirming the six-mutator/11-site enumerated surface is exhausted against post-edit code'
affects: [07-accounts-receivable-verification, future-ar-gap-closure-if-any]

tech-stack:
    added: []
    patterns:
        - 'JobOrder::outstandingBalance() as the model-level single source of truth for total_amount minus completed transactions, safe on both eager-loaded and bare instances'
        - 'Locked re-read + outstandingBalance()-authoritative guard, mirrored across WriteOffApprovalController and CreditApprovalController'
        - 'Silent no-op WrittenOff protection in background/webhook handlers (ConfirmPaymentIntent) vs. abort_if in user-facing requests (PaymentController)'

key-files:
    created:
        - tests/Unit/Models/JobOrderTest.php
    modified:
        - app/Models/JobOrder.php
        - app/Http/Controllers/AccountingStaff/AccountsReceivableController.php
        - app/Http/Controllers/AccountingStaff/CollectionLetterController.php
        - app/Http/Controllers/AccountingStaff/CollectionStatusController.php
        - app/Http/Controllers/Owner/WriteOffApprovalController.php
        - app/Http/Controllers/Owner/CreditApprovalController.php
        - app/Http/Controllers/Cashier/CreditRequestController.php
        - app/Http/Controllers/Cashier/PaymentController.php
        - app/Http/Controllers/Cashier/ReceiptController.php
        - app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php
        - app/Actions/POS/ConfirmPaymentIntent.php
        - app/Console/Commands/SendAccountsReceivableReminders.php
        - app/Mail/AccountsReceivableReminder.php
        - resources/js/pages/cashier/Dashboard.vue
        - .planning/phases/07-accounts-receivable/deferred-items.md
        - tests/Feature/AccountingStaff/CollectionLetterTest.php
        - tests/Feature/Owner/CreditApprovalTest.php
        - tests/Feature/Cashier/RecordPaymentTest.php
        - tests/Feature/Cashier/CashierPagesTest.php
        - tests/Unit/Actions/ConfirmPaymentIntentTest.php

key-decisions:
    - 'Kept the pre-refactor $amountPaid/$completedTransactions variables in ReceiptController and CollectionLetterController where they still feed other props, only replacing the balance-assignment expression itself'
    - "Documented (not fixed) a new, same-root-cause Larastan finding at SavePricingAndPaymentRequest.php:69 -- the plan's route-model-binding gap now also flags the new outstandingBalance() call, growing the pre-existing tolerated baseline from 5 to 6 errors across the same 3 files"
    - "Reworded CollectionStatusController's docblock to avoid literal 'DB::transaction'/'lockForUpdate' substrings that would have doubled the acceptance-criteria grep counts"

requirements-completed: [AR-01, AR-02, AR-03, AR-04]

# Metrics
duration: ~7min (commit-span; actual wall-clock longer due to tool-call overhead)
completed: 2026-09-09
---

# Phase 07 Plan 08: Terminate the AR-04 write-off/settlement-integrity defect class Summary

**Extracted `JobOrder::outstandingBalance()` as the single source of truth for the derived balance (11 call sites across 9 files), closed the last three unguarded `payment_status` writers (`CreditApprovalController`, `PaymentController`'s `CreditPendingApproval` gap, `ConfirmPaymentIntent`'s WrittenOff gap), hardened the two untouched AR-03 controllers, gave On-Credit/Rejected balances a reachable Cashier payment action, and closed with a grep-based audit proving the enumerated surface is exhausted.**

## Performance

- **Tasks:** 5 completed (Task 5 audit-only, no file changes)
- **Files modified:** 19 (1 created, 18 modified)

## Accomplishments

- `JobOrder::outstandingBalance()` collapses 11 prior independent reimplementations of `total_amount - completed_transactions` across 9 files into one model method
- `CreditApprovalController::approve()` now re-derives the job order's balance under lock and rejects the approval if already settled (CR-01, authoritative half)
- `PaymentController::store()`/`edit()` reject payment while a credit request is `credit_pending_approval` (CR-01, preventive half)
- `ConfirmPaymentIntent` can no longer overwrite an already-`written_off` job order's `payment_status` from either the Completed or Failed/expired branch -- closes the previously-deferred 6th `payment_status` writer
- `CollectionStatusController::update()` now runs inside a locked re-read + `DB::transaction()`, matching every other mutating AR controller in the phase
- `CollectionLetterController::show()` 404s for a Paid/WrittenOff `collection_status`, not just a non-Active `status`
- Cashier Dashboard offers "Collect Balance" (on_credit) / "Process Payment" (credit_rejected) links and a disabled "Awaiting Owner Approval" item (credit_pending_approval)
- Final audit: `payment_status`/`collection_status` write sites and `outstandingBalance()` call sites re-enumerated against post-edit code and confirmed exhausted; full suite (456 tests, up from the 445 baseline) passes with 0 failures

## Task Commits

Each task was committed atomically:

1. **Task 1: Extract the derived outstanding-balance computation onto JobOrder** - `c2fcd83` (feat)
2. **Task 2: Guard the three remaining unguarded/partially-guarded payment_status writers** - `d5bd134` (feat)
3. **Task 3: Harden the two untouched AR-03 controllers** - `26028ba` (feat)
4. **Task 4: Give an approved/rejected On-Credit balance a reachable payment action** - `44a4626` (feat)
5. **Task 5: Final enumeration audit** - no commit (audit-only task, modifies no application files; verification is documented below and in this Summary)

**Plan metadata:** (this commit)

## Files Created/Modified

- `app/Models/JobOrder.php` - Added `outstandingBalance(): float`, the single source of truth for the derived balance
- `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php` - `deriveRow()` routes through `outstandingBalance()`
- `app/Http/Controllers/AccountingStaff/CollectionLetterController.php` - `show()` routes `amountDue` through `outstandingBalance()`; added Paid/WrittenOff `collection_status` 404 guard
- `app/Http/Controllers/AccountingStaff/CollectionStatusController.php` - `update()` wrapped in `DB::transaction()` + locked re-read
- `app/Http/Controllers/Owner/WriteOffApprovalController.php` - both `index()` and `approve()` route through `outstandingBalance()`
- `app/Http/Controllers/Owner/CreditApprovalController.php` - `approve()` adds a locked `JobOrder` re-read + `outstandingBalance() <= 0.0` settlement guard, re-snapshots the AR row's balance
- `app/Http/Controllers/Cashier/CreditRequestController.php` - `store()` routes through `outstandingBalance()`
- `app/Http/Controllers/Cashier/PaymentController.php` - `edit()`/`store()` route through `outstandingBalance()`; both add a `CreditPendingApproval` 422 guard
- `app/Http/Controllers/Cashier/ReceiptController.php` - `show()`'s `$balance` assignment routes through `outstandingBalance()`
- `app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php` - `withValidator()`'s if-branch routes through `outstandingBalance()`
- `app/Actions/POS/ConfirmPaymentIntent.php` - both branches skip the `payment_status` overwrite when already `WrittenOff`
- `app/Console/Commands/SendAccountsReceivableReminders.php` - `processOne()` routes through `outstandingBalance()`
- `app/Mail/AccountsReceivableReminder.php` - `outstandingBalance()` (private method) delegates to the model method
- `resources/js/pages/cashier/Dashboard.vue` - action dropdown gains on_credit/credit_rejected/credit_pending_approval branches
- `.planning/phases/07-accounts-receivable/deferred-items.md` - documented the new Larastan finding count and marked the 6th-writer item RESOLVED
- `tests/Unit/Models/JobOrderTest.php` (new) - 4 tests for `outstandingBalance()`
- `tests/Feature/AccountingStaff/CollectionLetterTest.php` - added the Paid/WrittenOff 404 test
- `tests/Feature/Owner/CreditApprovalTest.php` - added the CR-01 settled-transaction test
- `tests/Feature/Cashier/RecordPaymentTest.php` - added the CreditPendingApproval-cannot-pay test
- `tests/Feature/Cashier/CashierPagesTest.php` - added the on-credit dashboard/payment-page-reachability tests
- `tests/Unit/Actions/ConfirmPaymentIntentTest.php` - added the WrittenOff-not-reverted test

## Decisions Made

- Kept `$amountPaid`/`$completedTransactions` local variables in `ReceiptController` and `CollectionLetterController` where they still feed other Inertia props (`amountPaid`), only replacing the specific balance-assignment expression per the plan's explicit instruction
- Did not touch `PaymentController::store()`'s later, separate `remaining_balance` return-array line (uses `$newAmountPaid`, a different in-memory projection, not one of the 11 enumerated sites) or `SavePricingAndPaymentRequest`'s `else` branch (operates on a not-yet-persisted computed total)
- Reworded `CollectionStatusController`'s docblock to say "a single database transaction"/"a freshly locked re-read" instead of literally "`DB::transaction()`"/"`lockForUpdate()`" -- the literal strings would have doubled the acceptance-criteria grep counts for those exact substrings

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] The plan's Larastan-impact prediction for SavePricingAndPaymentRequest.php was incorrect -- documented rather than "fixed" per explicit plan scope fence**

- **Found during:** Task 1 (routing `withValidator()`'s if-branch through `outstandingBalance()`)
- **Issue:** The plan's action text asserted that replacing `$jobOrder->total_amount - $amountPaid` with `$jobOrder->outstandingBalance()` at this one call site would not add a new distinct Larastan finding ("the gap moves ... it does not add a new distinct finding"). Empirically (confirmed by reverting the line and re-running `composer types:check` with a cleared result cache), this was wrong: the original two `total_amount` property accesses on lines 68-69 of this method somehow collapsed to a single reported error, while the new `outstandingBalance()` method call on line 69 is reported as its own distinct `method.notFound` finding, growing the file from 3 to 4 findings and the project-wide pre-existing baseline from 5 to 6 (still the same 3 files, same underlying route-model-binding type-inference root cause).
- **Fix:** Did NOT add a `@var` type hint or otherwise "fix" the underlying Larastan gap -- the plan explicitly forbids this ("do not attempt to fix the underlying Larastan gap itself, it is explicitly out of scope for this plan"). Instead, documented the corrected finding count in `deferred-items.md` so Task 5's later audit and any future dedicated fix plan account for 4 sites in this file, not 3.
- **Files modified:** `.planning/phases/07-accounts-receivable/deferred-items.md`
- **Verification:** `composer types:check` / `vendor/bin/phpstan analyse --debug` (with `/tmp/phpstan` cache cleared to rule out staleness) consistently reports exactly 6 errors across the same 3 pre-existing, unrelated files after every subsequent task's edits (Tasks 2, 3, 4) -- confirming no further NEW findings were introduced beyond this one documented instance.
- **Committed in:** `c2fcd83` (Task 1 commit)

---

**Total deviations:** 1 auto-fixed/documented (1 bug in the plan's own predicted verification outcome)
**Impact on plan:** No scope creep. The underlying Larastan route-model-binding gap remains exactly as out-of-scope as the plan specified; only the documented count changed to match reality.

## Issues Encountered

- The worktree had no `vendor/`, `.env`, `database/database.sqlite`, or `public/build/` -- ran `composer install`, copied `.env` and `public/build/` from the main checkout, and created an empty SQLite file before any test/Pint/Larastan/type-check command would run.
- `composer types:check` initially failed with `Undefined constant "Larastan\Larastan\LARAVEL_VERSION"` when run without `--debug`; this appears to be an interaction between Laravel Pao's compact-output wrapper and Larastan's `bootstrap.php` app-resolution step in a freshly-installed vendor tree. Running with `--debug` (or via `php vendor/bin/phpstan analyse --debug` directly) reliably produced real, actionable output; used that form for every subsequent Larastan check in this plan.
- `npm run types:check` initially failed with ~90 `Cannot find module '@/routes'`/`'@/actions/...'` errors -- Wayfinder's generated route/action TypeScript files are gitignored and hadn't been generated in this fresh worktree. Ran `php artisan wayfinder:generate --with-form` (matching the project's documented convention) before Task 4's verification; `npm run types:check` then passed cleanly (exit 0).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- AR-01 through AR-04 requirements are now closed with a confirmed-exhausted enumerated surface (Task 5's audit), ending the "rounds 1/2 didn't terminate" failure pattern from 07-06/07-07
- The out-of-scope items remain: `WriteOffRequestController::store()`'s own unlocked-read defect (WR-07) and the reminder-command warnings (WR-01/WR-02/WR-03), both explicitly fenced out of this plan
- A future small fix plan for the pre-existing Larastan route-model-binding gap (`CreateCreditRequestRequest.php`, `SavePricingAndPaymentRequest.php` -- now 4 sites, `UpdateSystemConfigurationRequest.php`) is still recommended, tracked in `deferred-items.md`

---

_Phase: 07-accounts-receivable_
_Completed: 2026-09-09_

## Self-Check: PASSED

- FOUND: app/Models/JobOrder.php
- FOUND: app/Http/Controllers/Owner/CreditApprovalController.php
- FOUND: app/Http/Controllers/AccountingStaff/CollectionStatusController.php
- FOUND: resources/js/pages/cashier/Dashboard.vue
- FOUND: tests/Unit/Models/JobOrderTest.php
- FOUND commit: c2fcd83
- FOUND commit: d5bd134
- FOUND commit: 26028ba
- FOUND commit: 44a4626
