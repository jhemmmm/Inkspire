---
phase: 06-production-monitoring-public-tracking
plan: 04
subsystem: job-order-production
tags: [eloquent, actions, larastan, tdd, cashier, artist, design-review]

# Dependency graph
requires:
  - phase: 06-01
    provides: "job_orders.due_at, production_logs table + ProductionLog model, JobOrderStatus::ForProduction/Printing/QualityCheck/ReadyForPickup"
  - phase: 06-02
    provides: "JobOrder number assignment already wired into QueueEntryController's create paths"
provides:
  - "app/Actions/JobOrder/EnterProduction.php: invokable Action that stamps ForProduction + due_at and writes the first system-authored production_logs row, inside its own DB::transaction()"
  - "Every Type A job order whose file passes validation (addJobOrder, store, replaceFile) automatically lands at ForProduction, not ReadyForProduction"
  - "Every Type B job order approved via either real approve() path (Artist in-person, or the signed public design-review link) automatically lands at ForProduction, not DesignApproved"
  - "Cashier Dashboard listing, Payment edit/store, and Credit Request store all recognize ForProduction/Printing/QualityCheck/ReadyForPickup as payable/priceable/credit-eligible, alongside the pre-existing ReadyForProduction/DesignApproved"
affects: [06-05, 06-06, 06-07, 06-08]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Invokable Action wrapping a status write + a dependent audit-observed row create in one DB::transaction(), modeled on AssignArtistToJobOrder — a nested DB::transaction() called from inside an existing outer transaction (DesignEditorController::approve(), Public DesignReviewController::approve()) becomes a savepoint, not a separate commit boundary"
    - "A match expression's default arm used specifically to satisfy Larastan's exhaustiveness check against enum cases that are structurally unreachable at that particular call site, with a PHPDoc explaining why they're unreachable there (rather than adding real per-case copy for statuses this call site can never actually observe)"

key-files:
  created:
    - app/Actions/JobOrder/EnterProduction.php
    - tests/Feature/JobOrder/EnterProductionTest.php
    - tests/Feature/Cashier/ProductionCompatibilityTest.php
  modified:
    - app/Http/Controllers/FrontlineStaff/QueueEntryController.php
    - app/Http/Controllers/FrontlineStaff/JobOrderController.php
    - app/Http/Controllers/Artist/DesignEditorController.php
    - app/Http/Controllers/Public/DesignReviewController.php
    - app/Http/Controllers/Cashier/DashboardController.php
    - app/Http/Controllers/Cashier/PaymentController.php
    - app/Http/Controllers/Cashier/CreditRequestController.php
    - tests/Feature/Artist/DesignReviewTest.php
    - tests/Feature/Public/DesignReviewTest.php
    - tests/Feature/FrontlineStaff/JobOrderProcessingTest.php

key-decisions:
  - "Closed the pre-existing QueueEntryController match.unhandled Larastan gap (deferred since 06-01) here, per this plan's explicit ownership of that call site — added a real ForProduction arm plus a documented default arm for the remaining structurally-unreachable cases"
  - "Fixed three stale ReadyForProduction assertions in tests/Feature/FrontlineStaff/JobOrderProcessingTest.php (not in this plan's files_modified list) as a direct, mechanical consequence of the EnterProduction wiring — the same class of fix the plan explicitly applied to Artist/Public DesignReviewTest.php, just one file the plan's own audit missed"

requirements-completed: [PROD-01, PROD-02]

# Metrics
duration: ~35min (includes one-time worktree environment bootstrap: composer install, npm ci, npm run build)
completed: 2026-09-07
---

# Phase 6 Plan 4: Automatic Production Entry + Cashier Compatibility Summary

**A Type A file passing validation or a Type B design being approved now automatically and immediately advances the job order to ForProduction (via a new EnterProduction action), with due_at stamped and a system-authored production_logs row — and Cashier's pricing/payment/credit-request flows keep working across all four new production stages.**

## Performance

- **Duration:** ~35 min (majority was one-time worktree bootstrap: `composer install`, `npm ci`, `npm run build` — this worktree had none of vendor/, node_modules/, .env, or built assets)
- **Tasks:** 2 completed (both TDD: RED test commit -> GREEN implementation commit)
- **Files modified:** 13 (1 created action, 2 created test files, 10 modified controllers/tests)

## Accomplishments

- New `EnterProduction` invokable Action: stamps `ForProduction` + `due_at` (from `SystemConfiguration::getInt('default_sla_days', 3)`) and writes exactly one system-authored `ProductionLog` row, all inside one `DB::transaction()`
- Wired into all four call sites that previously left a job order at `ReadyForProduction`/`DesignApproved`: `QueueEntryController::applyIntakeOutcome()` (covers both `addJobOrder()` and `store()`), `JobOrderController::replaceFile()`, `DesignEditorController::approve()`, and `Public\DesignReviewController::approve()`
- Fixed the one crash-level regression this wiring would otherwise introduce: `QueueEntryController`'s post-outcome toast `match` now has a `ForProduction` arm, so `addJobOrder()` no longer throws `UnhandledMatchError` for a passing Type A file — proven end-to-end through the real HTTP route, not just a unit-level status check
- Closed the pre-existing `match.unhandled` Larastan gap on that same `match` (open since 06-01) by adding a documented `default` arm for the remaining, structurally unreachable-here `JobOrderStatus` cases
- Extended Cashier's Dashboard listing, Payment edit/store, and Credit Request store to recognize `ForProduction`/`Printing`/`QualityCheck`/`ReadyForPickup`, so a job order that has automatically advanced into production remains fully payable, priceable, and credit-request-eligible — Phase 5 D-15 held forward through this phase's new stages
- Updated every test assertion this wiring made stale (two explicitly named by the plan, three more in a sibling test file the plan's own audit missed) rather than leaving them silently wrong

## Task Commits

Each task was committed atomically via TDD (test -> feat):

1. **Task 1: EnterProduction action, wired into all four status-transition call sites** - `1e9a3ca` (test, RED) -> `8c68f63` (feat, GREEN)
2. **Task 2: Keep Cashier pricing/payment/credit-request working through the new production statuses** - `d8568f4` (test, RED) -> `0510d3c` (feat, GREEN)

## Files Created/Modified

- `app/Actions/JobOrder/EnterProduction.php` - new invokable Action; stamps `ForProduction`/`due_at`, creates the first `ProductionLog` row
- `app/Http/Controllers/FrontlineStaff/QueueEntryController.php` - `applyIntakeOutcome()` calls `EnterProduction` after a Type A pass; toast `match` gains a `ForProduction` arm plus a `default` arm
- `app/Http/Controllers/FrontlineStaff/JobOrderController.php` - `replaceFile()` calls `EnterProduction` after a Type A pass
- `app/Http/Controllers/Artist/DesignEditorController.php` - `approve()` calls `EnterProduction` inside its existing transaction
- `app/Http/Controllers/Public/DesignReviewController.php` - gained a constructor; `approve()` calls `EnterProduction` inside its existing transaction
- `app/Http/Controllers/Cashier/DashboardController.php` - listing `whereIn('status', [...])` extended to six values
- `app/Http/Controllers/Cashier/PaymentController.php` - both `edit()`/`store()` eligibility `in_array()` checks extended to six values
- `app/Http/Controllers/Cashier/CreditRequestController.php` - `store()` eligibility `in_array()` check extended to six values
- `tests/Feature/JobOrder/EnterProductionTest.php` - direct action coverage (status/due_at/single ProductionLog row) plus one end-to-end assertion per call site
- `tests/Feature/Cashier/ProductionCompatibilityTest.php` - dataset tests over the four new statuses for dashboard listing, payment edit/store, and credit-request store
- `tests/Feature/Artist/DesignReviewTest.php`, `tests/Feature/Public/DesignReviewTest.php` - terminal-status assertions updated from `DesignApproved` to `ForProduction`
- `tests/Feature/FrontlineStaff/JobOrderProcessingTest.php` - three stale `ReadyForProduction` assertions updated to `ForProduction` (see Deviations)

## Decisions Made

- The `default` arm in `jobOrderOutcomeToastMessage()`'s match returns a generic `__('Job order added.')` string — it exists purely to satisfy Larastan's exhaustiveness check against `JobOrderStatus` cases this call site can never actually reach (a freshly created job order's status here is always `ReadyForProduction`/`ForProduction`/`ValidationFailed`/`Assigned`/`Intake`), not to give real per-case copy for statuses like `DesignApproved` or `Printing` that this specific method never sees.
- `default_sla_days` is read via the existing `SystemConfiguration::getInt('default_sla_days', 3)` helper with no new config key — matches the identical call already used by `PerformanceReportController`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Closed the pre-existing QueueEntryController match.unhandled Larastan gap this plan's own wiring widened**

- **Found during:** Task 1, running `composer types:check` after wiring `EnterProduction` in
- **Issue:** `jobOrderOutcomeToastMessage()`'s `match ($jobOrder->status)` was already non-exhaustive before this plan (documented in `deferred-items.md` since 06-01). Adding the required `ForProduction` arm (needed to prevent the `UnhandledMatchError` the plan explicitly calls out) still left the match non-exhaustive against the enum's other cases, so Larastan continued to fail. The orchestrator's environment note explicitly flagged this as in-scope: "your plan explicitly owns QueueEntryController's post-outcome toast match — so that particular match.unhandled error IS in scope for you to fix."
- **Fix:** Added a `default => __('Job order added.')` arm, documented with a PHPDoc explaining that the remaining `JobOrderStatus` cases are structurally unreachable at this call site.
- **Files modified:** `app/Http/Controllers/FrontlineStaff/QueueEntryController.php`
- **Verification:** `composer types:check` no longer reports this file; scoped `phpstan analyse` against all 8 files this plan touches reports 0 errors.
- **Committed in:** `8c68f63` (Task 1 GREEN commit)

**2. [Rule 1 - Bug] Fixed three stale ReadyForProduction assertions in a test file not in this plan's files_modified list**

- **Found during:** Task 1, running the full `tests/Feature/FrontlineStaff` sweep after wiring `EnterProduction` in
- **Issue:** `tests/Feature/FrontlineStaff/JobOrderProcessingTest.php` (not listed in this plan's `files_modified`, and not owned by 06-07 either) contains three assertions that a Type A job order passing validation reaches `ReadyForProduction` — via `addJobOrder()`, `replaceFile()`, and the combined `store()` test. Once `EnterProduction` is wired into all three of those exact code paths, each assertion became a direct, mechanical false-negative — not a pre-existing or unrelated failure, but a same-file consequence of this plan's own wiring, exactly analogous to the two test files the plan explicitly named for the same fix.
- **Fix:** Updated the three assertions (and the first test's description) from `JobOrderStatus::ReadyForProduction` to `JobOrderStatus::ForProduction`, leaving every other assertion in the file (Type B, validation-failed, audit-trail, forbidden-role) untouched.
- **Files modified:** `tests/Feature/FrontlineStaff/JobOrderProcessingTest.php`
- **Verification:** `vendor/bin/pest tests/Feature/FrontlineStaff` — 154 tests passed (0 failures) after the fix.
- **Committed in:** `8c68f63` (Task 1 GREEN commit)

---

**Total deviations:** 2 auto-fixed (both Rule 1 — direct, mechanical consequences of this plan's own intended wiring, not scope creep)
**Impact on plan:** Both fixes were necessary to leave the suite green after the intended behavior change. No architectural changes, no files touched outside the direct blast radius of Task 1's wiring.

## Issues Encountered

- **`composer types:check` (Larastan) does not exit 0 project-wide** — 5 pre-existing errors remain, unchanged from 06-01/06-02's documented state (`.planning/phases/06-production-monitoring-public-tracking/deferred-items.md`): `property.notFound`/`method.notFound` errors in three Phase 5 Cashier/Owner Form Requests (`CreateCreditRequestRequest.php`, `SavePricingAndPaymentRequest.php` x3, `UpdateSystemConfigurationRequest.php`). Verified via a scoped `phpstan analyse` against all 8 files this plan created/modified — 0 errors. The previously-deferred 6th error (`QueueEntryController`'s `match.unhandled`) is now resolved, per this plan's explicit ownership of that call site (see Deviations above) — `deferred-items.md` updated to reflect this.
- Fresh worktree required full environment bootstrap (`composer install`, `.env`, `database.sqlite`, `php artisan migrate`, `npm ci`, `npm run build`) before any test/build command would run — same one-time cost documented by 06-01/06-02's summaries, not repeated project state.
- Full project-wide test suite: 313 tests, 310 passed, 3 skipped, 0 failures (baseline before this plan: 291 tests, 288 passed, 3 skipped — the +22 delta is exactly this plan's own new tests: 6 in `EnterProductionTest.php` + 16 dataset instances in `ProductionCompatibilityTest.php`).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Every Type A validation-pass and every Type B design-approval now reaches `ForProduction` with `due_at` stamped and a first `production_logs` row — the Production Board (06-05/06-06) has real, observable data to render.
- Cashier's payable/priceable/credit-eligible surface now spans the full production lifecycle, so 06-05/06-06's board updates and 06-07's remaining consumer fixes (Artist Performance Report, Artist queue exclusion, Cashier/Artist/Frontline status badges) build on a correct foundation.
- 06-07 (already planned, not this plan's scope) still owns: `CancellationController`'s `$designStarted` fee-eligibility list, `PerformanceReportController`'s completion query, `JobOrderQueueController`'s exclusion list, and four Vue status-badge/label gaps (`cashier/Dashboard.vue`, `artist/Dashboard.vue`, `frontline-staff/QueueList.vue`, `frontline-staff/NewVisit.vue`) — none of those files were touched here, per this plan's explicit scope boundary.

---

_Phase: 06-production-monitoring-public-tracking_
_Completed: 2026-09-07_

## Self-Check: PASSED

All 14 claimed files verified present on disk; all 4 claimed commit hashes (`1e9a3ca`, `8c68f63`, `d8568f4`, `0510d3c`) verified present in `git log --oneline --all`.
