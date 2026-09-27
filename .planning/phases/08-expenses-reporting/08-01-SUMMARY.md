---
phase: 08-expenses-reporting
plan: 01
subsystem: database
tags: [laravel, eloquent, form-request, audit-trail, inertia]

# Dependency graph
requires:
    - phase: 01-foundation-rbac-auth-hardening-audit-trail
      provides: 'AuditObserver, AuditLogger, SystemConfiguration (expense_categories config), role:accounting_staff route-group middleware'
provides:
    - 'Expense model + expenses table (category, amount, expense_date, description, recorded_by, voided_at, void_reason)'
    - 'ExpenseController with index/store/update/void, wired under accounting-staff.expenses.* routes'
    - 'Expense::active() scope excluding voided rows from every sum'
affects: [08-03-financial-reports, 08-02-expenses-ui]

# Tech tracking
tech-stack:
    added: []
    patterns:
        - "Void-with-reason via forceFill()->save() on columns outside #[Fillable], captured for free by AuditObserver's updated() hook"
        - "Category allowlist sourced live from SystemConfiguration::getArray('expense_categories'), never hardcoded"

key-files:
    created:
        - database/migrations/2026_09_10_090000_create_expenses_table.php
        - app/Models/Expense.php
        - database/factories/ExpenseFactory.php
        - app/Concerns/ExpenseValidationRules.php
        - app/Http/Requests/AccountingStaff/StoreExpenseRequest.php
        - app/Http/Requests/AccountingStaff/UpdateExpenseRequest.php
        - app/Http/Requests/AccountingStaff/VoidExpenseRequest.php
        - app/Http/Requests/AccountingStaff/FilterExpensesRequest.php
        - app/Http/Controllers/AccountingStaff/ExpenseController.php
        - tests/Feature/AccountingStaff/ExpenseTest.php
    modified:
        - routes/portals.php

key-decisions:
    - "ExpenseFactory::voided() uses afterCreating()+forceFill(), matching AccountsReceivableFactory's established convention for factory states writing to columns outside #[Fillable]"
    - 'No voided_by column — audit_trail already records who performed the void via AuditObserver/auth()->id(), a dedicated column would be redundant'

patterns-established:
    - "Pattern: void-with-mandatory-reason guarded by abort_if($model->voided_at !== null, 422, ...) before forceFill()->save() — reused from WriteOffRequestController's closed-state guard shape"

requirements-completed: [EXP-01]

# Metrics
duration: 20min
completed: 2026-09-10
---

# Phase 8 Plan 1: Expenses Data Layer Summary

**Expense model + expenses table with an Accounting-Staff-only create/edit/void vertical slice, audited for free via `#[ObservedBy(AuditObserver::class)]`**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-09-10T00:30:00Z
- **Completed:** 2026-09-10T00:50:36Z
- **Tasks:** 2
- **Files modified:** 11

## Accomplishments

- New `expenses` table (genuinely new domain table, not an additive column) with category/amount/expense_date/description/recorded_by and a discretionary voided_at/void_reason pair
- `Expense` model with an `active()` scope that is the single place every future report query excludes voided rows
- `ExpenseController` with index (This-Month-default date range, server-computed total excluding voided rows), store, update (blocks editing a voided expense), and void (mandatory reason, survives and stays visible)
- Full feature test suite: 13 tests covering create/validate/edit/void/audit-trail/cross-role-403/historical-category-preservation, all passing

## Task Commits

Each task was committed atomically:

1. **Task 1: Contracts — migration, model, factory, validation rules** - `adbb6ef` (feat)
2. **Task 2: ExpenseController (index/store/update/void), routes, tests** - `b0946dd` (feat)

**Plan metadata:** (this commit)

## Files Created/Modified

- `database/migrations/2026_09_10_090000_create_expenses_table.php` - New `expenses` table
- `app/Models/Expense.php` - Model with `#[Fillable]`, `#[ObservedBy(AuditObserver::class)]`, `recordedBy()` relation, `scopeActive()`
- `database/factories/ExpenseFactory.php` - Factory + `voided()` state
- `app/Concerns/ExpenseValidationRules.php` - `expenseRules()` (category/amount/expense_date/description) + `voidReasonRules()`
- `app/Http/Requests/AccountingStaff/StoreExpenseRequest.php` - Store validation
- `app/Http/Requests/AccountingStaff/UpdateExpenseRequest.php` - Update validation
- `app/Http/Requests/AccountingStaff/VoidExpenseRequest.php` - Void-reason validation
- `app/Http/Requests/AccountingStaff/FilterExpensesRequest.php` - Date-range filter validation for `index()`
- `app/Http/Controllers/AccountingStaff/ExpenseController.php` - index/store/update/void actions
- `routes/portals.php` - Four `accounting-staff.expenses.*` routes added to the existing `role:accounting_staff` group
- `tests/Feature/AccountingStaff/ExpenseTest.php` - 13 feature tests

## Decisions Made

- `ExpenseFactory::voided()` uses `afterCreating()` + `forceFill()->save()` rather than `state()`, matching `AccountsReceivableFactory`'s established convention (confirmed by reading that factory directly) since `voided_at`/`void_reason` sit outside `#[Fillable]`.
- No `voided_by` column was added — `audit_trail` already captures who performed the void action via `AuditObserver`, so a dedicated column would duplicate that record.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Worktree environment required a real `composer install` instead of a symlinked `vendor/`**

- **Found during:** Task 2 (running the feature test suite)
- **Issue:** The worktree's `vendor/` was initially symlinked to the main repo's `vendor/`. This caused Laravel's `Application::inferBasePath()` (which resolves the base path from Composer's registered autoloader paths, and PHP's `__DIR__` resolves through symlinks to the physical target) to resolve to the main repo's path instead of the worktree's, producing a broken/empty service container (`app()` returned a bare `Illuminate\Container\Container`, not the booted `Application`) specifically under Pest's test runner.
- **Fix:** Removed the `vendor` symlink and ran a real `composer install --no-interaction --prefer-dist` inside the worktree so all autoloader paths are self-contained. Also copied `.env` and created an isolated `database/database.sqlite`, and copied `public/build` (pre-built frontend assets) from the main repo so full-page Inertia responses to already-existing pages (e.g. the 403 `errors/Forbidden` page) render correctly in tests.
- **Files modified:** None (environment-only; `vendor/`, `node_modules`, `.env`, `public/build`, `database/database.sqlite` are all gitignored)
- **Verification:** `vendor/bin/pest tests/Feature/AccountingStaff/WriteOffRequestTest.php` (pre-existing analog test) now passes; full suite `php artisan test --compact` passes 468/471 (3 pre-existing skips)

**2. [Rule 1 - Bug] Test suite needed `X-Inertia`/`X-Inertia-Version` headers and manual config seeding for `index()` assertions**

- **Found during:** Task 2 (writing `ExpenseTest.php`)
- **Issue:** `Expenses/Index.vue` does not exist yet (it is out of this plan's scope — a later frontend plan owns it), so a full-page (non-XHR) Inertia GET to `index()` fails at Vite-manifest-lookup time. `RefreshDatabase` also runs migrations only, not seeders, so `expense_categories` config doesn't exist by default in tests, causing category validation to reject every value including valid ones.
- **Fix:** GET-index tests send `X-Inertia: true` plus a matching `X-Inertia-Version` header (computed the same way `Inertia\Middleware::version()` does, via `hash_file('xxh128', manifest.json)`) to receive the JSON page-data response instead of a full HTML render, and assert directly on `$response->json('props.*')`. Added a `seedExpenseCategories()` test helper (matching `CancellationFeeTest::seedCancellationFee()`'s established convention) called by every test that exercises category validation.
- **Files modified:** tests/Feature/AccountingStaff/ExpenseTest.php
- **Verification:** All 13 tests pass
- **Committed in:** b0946dd (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (1 blocking environment fix, 1 bug fix in test infrastructure)
**Impact on plan:** No production code scope creep. The environment fix was necessary to run tests at all in this worktree; the test-infrastructure fix was necessary because this plan is deliberately backend-only (frontend page is a separate plan) and RefreshDatabase doesn't seed.

## Issues Encountered

- PHPStan (`composer types:check`) reports 7 pre-existing errors in files unrelated to this plan (`app/Http/Requests/Cashier/CreateCreditRequestRequest.php`, `app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php`, `app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php`, `app/Mail/AccountsReceivableReminder.php`). Verified pre-existing and out of scope for this plan; logged to `.planning/phases/08-expenses-reporting/deferred-items.md` per the deviation-rules scope boundary, not fixed.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `Expense` model and `Expense::active()` scope are ready for Plan 08-03's report queries to consume directly.
- The Expenses ledger UI (`accounting-staff/Expenses/Index.vue`) is not yet built — a later plan in this phase owns that page; `ExpenseController::index()`'s prop contract (`rows`, `total`, `activeCount`, `voidedCount`, `categories`, `filters`) is the contract that page must bind to.

---

_Phase: 08-expenses-reporting_
_Completed: 2026-09-10_

## Self-Check: PASSED

All 10 created files verified present on disk. All 3 commit hashes (`adbb6ef`, `b0946dd`, `35c4f55`) verified present in `git log`.
