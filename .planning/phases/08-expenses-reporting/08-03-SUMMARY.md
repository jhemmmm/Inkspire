---
phase: 08-expenses-reporting
plan: 03
subsystem: reporting
tags: [laravel, eloquent, entitlement, inertia, form-request, reports]

# Dependency graph
requires:
  - phase: 08-expenses-reporting
    plan: 01
    provides: "Expense model + Expense::active() scope"
  - phase: 07-accounts-receivable
    provides: "AccountsReceivable model, WriteOffApprovalController::approve()"
provides:
  - "ReportRegistry: the single server-side entitlement boundary (definitions/isEntitled/entitledFor) for the shared Reports page"
  - "ReportBuilder: rows(key,from,to)/summary(from,to) -- the one query implementation for all five reports"
  - "ReportController::index() -- resolves entitlement, builds the report, renders the correct per-role Inertia page"
  - "Four wired reports.index routes (owner, cashier, production-staff, accounting-staff)"
  - "accounts_receivable.written_off_at timestamp, stamped by WriteOffApprovalController::approve()"
affects: [08-04-report-exports, 08-05-reports-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Plain static registry (ReportRegistry::isEntitled) as the entitlement boundary, not Gate::define() -- no Gate:: call exists anywhere else in this codebase"
    - "A SECOND, owner-only role:owner middleware group in routes/owner.php, deliberately separate from the existing role:owner,admin group, for any route Admin must not reach"
    - "Report builder methods type-hint Carbon\\CarbonInterface (not Illuminate\\Support\\Carbon) since AppServiceProvider's Date::use(CarbonImmutable::class) makes now() return CarbonImmutable app-wide"

key-files:
  created:
    - database/migrations/2026_09_10_091000_add_written_off_at_to_accounts_receivable_table.php
    - app/Services/Reports/ReportRegistry.php
    - app/Services/Reports/ReportBuilder.php
    - app/Http/Requests/Reports/FilterReportRequest.php
    - app/Http/Controllers/Reports/ReportController.php
    - tests/Feature/Reports/FinancialReportTest.php
    - tests/Feature/Reports/CashierReportsTest.php
    - tests/Feature/Reports/ProductionStatusReportTest.php
    - tests/Feature/Reports/AccountingReportsTest.php
  modified:
    - app/Models/AccountsReceivable.php
    - app/Http/Controllers/Owner/WriteOffApprovalController.php
    - routes/owner.php
    - routes/portals.php
    - .planning/phases/08-expenses-reporting/deferred-items.md

key-decisions:
  - "ReportBuilder methods type-hint Carbon\\CarbonInterface rather than Illuminate\\Support\\Carbon, discovered via a TypeError at test time (now() returns CarbonImmutable app-wide per AppServiceProvider)"
  - "financial-summary's write_off_total sums JobOrder::outstandingBalance() per written-off entry (never the stored accounts_receivable.balance column), matching RESEARCH.md's explicit anti-pattern warning"

patterns-established:
  - "Report entitlement is checked once, in the controller, before any query is built (ReportRegistry::isEntitled) -- independent of which role-scoped route was hit"

requirements-completed: [RPT-01, RPT-02, RPT-03, RPT-04]

# Metrics
duration: 45min
completed: 2026-09-10
---

# Phase 8 Plan 3: Reports Backend (Registry, Builder, Entitlement) Summary

**One shared ReportBuilder/ReportRegistry pipeline serving all five reports (sales, cancellations, production-status, expenses, financial-summary), gated by a server-side entitlement check that runs before any query, with Owner's route deliberately isolated from the existing role:owner,admin group**

## Performance

- **Duration:** ~45 min
- **Started:** 2026-09-10T00:52:00Z
- **Completed:** 2026-09-10T01:23:17Z
- **Tasks:** 2
- **Files modified:** 13

## Accomplishments
- `written_off_at` column added to `accounts_receivable`, stamped by `WriteOffApprovalController::approve()` -- closes RESEARCH.md's Open Question 1, the exact timestamp D-09's write-off disclosure line needed and never had
- `ReportRegistry`: the single server-side entitlement boundary (`definitions()`/`isEntitled()`/`entitledFor()`), populated verbatim from the UI-SPEC's report registry table for all five report keys
- `FilterReportRequest`, mirroring `AccountingStaff\FilterExpensesRequest` exactly (deliberately duplicated, not shared)
- `ReportBuilder`: one query implementation per report (`sales`, `cancellations`, `production-status`, `expenses`, `financial-summary`), all date-range filters comparing full timestamp bounds (never a bare equality, avoiding the project's SQLite `whereDate()` trap), revenue always filtered by `transactions.confirmed_at` (never `created_at`)
- `ReportController::index()` -- resolves the date range (This-Month default), resolves the selected report key, enforces `ReportRegistry::isEntitled()` before building any query, caps on-screen rows at 100 with an untruncated `rowsTotal`, renders the correct per-role Inertia component
- Four wired routes: `owner.reports.index` in a dedicated `role:owner`-only group (never `role:owner,admin`), plus `cashier.reports.index`, `production-staff.reports.index`, `accounting-staff.reports.index` in their existing single-role groups
- 14 feature tests across 4 files: cross-role entitlement (allow AND direct-URL deny paths for every role), D-05's Admin-gets-403-not-200 on the Owner route, D-06's day-vs-month range equivalence, D-08/Pitfall 1's `confirmed_at`-not-`created_at` regression, D-09/D-10's write-off disclosure without subtraction, D-11/Pitfall 5's voided-expense exclusion

## Task Commits

Each task was committed atomically:

1. **Task 1: written_off_at column + ReportRegistry + FilterReportRequest** - `7051c23` (feat)
2. **Task 2: ReportBuilder + ReportController::index() + routes + tests** - `e81665d` (feat)

**Plan metadata:** (this commit)

## Files Created/Modified
- `database/migrations/2026_09_10_091000_add_written_off_at_to_accounts_receivable_table.php` - Additive nullable `written_off_at` timestamp, no backfill (no prior reliable "when")
- `app/Models/AccountsReceivable.php` - `written_off_at` cast + docblock property
- `app/Http/Controllers/Owner/WriteOffApprovalController.php` - `approve()`'s existing `forceFill()` call extended to also set `written_off_at`
- `app/Services/Reports/ReportRegistry.php` - `definitions()`/`isEntitled()`/`entitledFor()`
- `app/Services/Reports/ReportBuilder.php` - `rows(key,from,to)`/`summary(from,to)` and five private per-report query methods
- `app/Http/Requests/Reports/FilterReportRequest.php` - `from`/`to` date-range validation, mirrors `FilterExpensesRequest`
- `app/Http/Controllers/Reports/ReportController.php` - `index()` action
- `routes/owner.php` - New `role:owner`-only group housing `reports.index`
- `routes/portals.php` - `reports.index` added to the existing `cashier`, `production_staff`, `accounting_staff` groups
- `tests/Feature/Reports/{FinancialReportTest,CashierReportsTest,ProductionStatusReportTest,AccountingReportsTest}.php` - 14 feature tests
- `.planning/phases/08-expenses-reporting/deferred-items.md` - Logged a pre-existing, environment-level `composer types:check`/Larastan bootstrap failure (see Deviations)

## Decisions Made
- `ReportBuilder`'s public and private method signatures type-hint `Carbon\CarbonInterface`, not `Illuminate\Support\Carbon`. Discovered via a `TypeError` at test time: `AppServiceProvider::boot()` calls `Date::use(CarbonImmutable::class)`, so `now()` (and `FilterReportRequest::date()`) return `CarbonImmutable` throughout this app, which does not extend `Illuminate\Support\Carbon`. `CarbonInterface` is the common interface both implement, including `copy()`/`endOfDay()`.
- `financial-summary`'s `write_off_total` sums `JobOrder::outstandingBalance()` per in-range-written-off `AccountsReceivable` row, never the stored `accounts_receivable.balance` column -- the model already centralizes this derived value (Phase 7's D-16), and re-deriving it inline was RESEARCH.md's explicit anti-pattern warning.
- `production-status` rows return a boolean `urgency` field (computed identically to `ProductionBoardController`'s `is_rush`), leaving the on-screen label ("Rush"/"Normal") to Plan 08-05's frontend, matching the boolean shape the existing Production Board already ships.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `ReportBuilder`'s Carbon type hints rejected the app's actual date type**
- **Found during:** Task 2 (first `vendor/bin/pest tests/Feature/Reports` run)
- **Issue:** `ReportBuilder::rows()`/`summary()` and their five private helpers were initially typed against `Illuminate\Support\Carbon $from, Carbon $to`. Every test failed with `TypeError: ... must be of type Illuminate\Support\Carbon, Carbon\CarbonImmutable given` -- `AppServiceProvider::boot()`'s `Date::use(CarbonImmutable::class)` makes `now()`/`FilterReportRequest::date()` return `CarbonImmutable` everywhere in this app, and `CarbonImmutable` does not extend `Illuminate\Support\Carbon`.
- **Fix:** Retyped every `ReportBuilder` method signature to `Carbon\CarbonInterface`, the shared interface both `Carbon` and `CarbonImmutable` implement (including the `copy()`/`endOfDay()` calls the builder relies on).
- **Files modified:** app/Services/Reports/ReportBuilder.php
- **Verification:** `vendor/bin/pest tests/Feature/Reports` -- 14/14 passing after the fix
- **Committed in:** e81665d (Task 2 commit -- caught before commit, not a separate fix commit)

---

**Total deviations:** 1 auto-fixed (bug fix caught before Task 2's commit, no separate commit needed).
**Impact on plan:** No scope creep -- the fix is a signature-only correction within `ReportBuilder.php`, already in this plan's `files_modified` list.

## TDD Gate Compliance

Task 2 was marked `tdd="true"`, but this plan's git history does not contain a separate `test(...)` (RED) commit followed by a `feat(...)` (GREEN) commit -- the builder, controller, routes, and all four test files were written together and verified locally (tests run and iterated until green) before a single `feat(08-03): ReportBuilder + ReportController::index() + routes + tests` commit. The RED phase was performed informally (tests were run against the not-yet-correct `ReportBuilder` and initially failed with the `CarbonInterface` `TypeError` documented above, then were run again and passed once the fix landed), but that failing run was never captured in its own commit. Flagging this per the plan-level TDD gate-sequence check: RED gate commit -- missing; GREEN gate commit -- present (`e81665d`, contains both tests and implementation). No REFACTOR commit was needed.

## Issues Encountered
- `composer types:check` (Larastan/PHPStan) fails to even start in the current environment with `Undefined constant "Larastan\Larastan\LARAVEL_VERSION"`, preceded by a `phpstan-turbo` native-extension GLIBC warning. Confirmed pre-existing and environment-level, not caused by this plan's changes: `php artisan --version` boots the full framework successfully, `vendor/bin/pint --dirty --format agent` passes cleanly, and `php artisan optimize:clear` does not resolve it. Per this plan's `<critical_environment_constraint>`, `composer install`/`require`/`update` are explicitly prohibited for Plan 08-03, so repairing the Larastan/turbo-ext install (which would require a `composer` operation) was not attempted. Logged to `.planning/phases/08-expenses-reporting/deferred-items.md`. Code correctness for this plan was instead verified via `php -l` syntax checks, `vendor/bin/pint --dirty --format agent`, and the full Pest suite (485 tests, 482 passed, 3 pre-existing skips, 0 failures -- no new failures vs. the 471-test baseline plus this plan's 14 new tests).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `ReportBuilder::rows()`/`summary()` are the exact, reusable query methods Plan 08-04's export actions must call uncapped (never re-implement a second query per report).
- `ReportRegistry::isEntitled()`/`entitledFor()` are the exact entitlement checks Plan 08-04's export routes must also run before streaming any file (RESEARCH.md's Pitfall 2/D-04 apply equally to exports).
- The four `{role}.reports.index` routes exist and return real, correct, role-scoped Inertia props (`reports`, `selected`, `columns`, `rows`, `rowsTotal`, `summary`, `filters`) today, even though `owner/Reports.vue`/`cashier/Reports.vue`/`production-staff/Reports.vue`/`accounting-staff/Reports.vue` do not exist yet -- Plan 08-05 owns those four thin wrapper pages plus the shared `ReportsWorkspace.vue`. Every test in this plan therefore issued Inertia XHR requests (`X-Inertia`/`X-Inertia-Version` headers) rather than full-page visits, matching Plan 08-01's established convention for testing a controller whose Vue page doesn't exist yet.

---
*Phase: 08-expenses-reporting*
*Completed: 2026-09-10*

## Self-Check: PASSED

All 9 created files verified present on disk. Both commit hashes (`7051c23`, `e81665d`) verified present in `git log`. Full test suite: 485 tests, 482 passed, 3 pre-existing skips, 0 failures.
