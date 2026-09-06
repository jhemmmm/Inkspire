---
phase: 06-production-monitoring-public-tracking
plan: 01
subsystem: database
tags: [eloquent, migrations, sqlite, audit-trail, larastan]

# Dependency graph
requires:
  - phase: 03-job-order-intake-auto-assignment
    provides: JobOrder model, JobOrderStatus enum, JobOrderFactory
  - phase: 02-customer-queue-management
    provides: QueueEntry::nextForBusinessDay() DB::transaction()+lockForUpdate() pattern (D-17), Asia/Manila narrow-scoping precedent (D-16)
provides:
  - "job_orders.number: human-readable JO-{year}-{seq} identifier, backfilled on every existing row"
  - "job_orders.due_at: nullable datetime column for D-06's stored SLA deadline (written by a later plan)"
  - "production_logs table + ProductionLog model: audit-observed stage-transition log with nullable recorded_by"
  - "JobOrder::nextNumberForYear()/currentNumberingYear(): the number generator every future job-order creation path calls"
  - "JobOrderStatus::ForProduction/Printing/QualityCheck/ReadyForPickup: the four PROD-02 production stages"
affects: [06-02, 06-03, 06-04, 06-05, 06-06, 06-07, 06-08]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Migration-time backfill of a new unique column: nullable+unique add, then a same-migration PHP-side backfill loop grouped by a narrowly-scoped Asia/Manila year, matching the live generator's exact output shape"
    - "Year-scoped sequential number generator: DB::transaction() + lockForUpdate() + max('number') LIKE-prefixed, PHP-side substr() suffix extraction instead of SQL SUBSTR/CAST (SQLite/MySQL portability)"

key-files:
  created:
    - database/migrations/2026_09_05_120000_add_number_and_due_at_to_job_orders_table.php
    - database/migrations/2026_09_05_120100_create_production_logs_table.php
    - app/Models/ProductionLog.php
    - database/factories/ProductionLogFactory.php
    - tests/Feature/JobOrder/JobOrderNumberGeneratorTest.php
    - tests/Feature/JobOrder/ProductionLogModelTest.php
  modified:
    - app/Enums/JobOrderStatus.php
    - app/Models/JobOrder.php
    - database/factories/JobOrderFactory.php

key-decisions:
  - "Explicit dropUnique() before dropColumn() in the number/due_at migration's down() — SQLite <3.35 (this repo's local version) has no native ALTER TABLE DROP COLUMN and Laravel's table-recreation fallback otherwise leaves job_orders_number_unique behind pointing at nothing, corrupting the very next insert with a stray UNIQUE violation"
  - "'number' added to JobOrder's #[Fillable] list per the plan's threat-model disposition (T-06-01-01): no Form Request declares a number rule and no controller uses $request->all(), so mass-assignment exposure is real but unreachable from any current or planned endpoint"

requirements-completed: [PROD-01, PROD-02, TRACK-01]

# Metrics
duration: ~40min (includes one-time worktree environment setup: composer install, npm ci, vite build — none of this repeats for later 06-* plans in the same worktree)
completed: 2026-09-05
---

# Phase 6 Plan 1: Schema/Enum/Model Substrate Summary

**Additive job_orders.number/due_at columns (with full backfill), a new audit-observed production_logs table + model, and four new JobOrderStatus production stages — the shared substrate every other Phase 6 plan builds on.**

## Performance

- **Duration:** ~40 min (majority was one-time worktree bootstrap: `composer install`, `npm ci`, `npm run build` — the worktree had no vendor/, node_modules/, .env, or built assets)
- **Tasks:** 2 completed
- **Files modified:** 9 (2 created migrations, 1 created model, 1 created factory, 2 created test files, 2 modified models/factories, 1 modified enum)

## Accomplishments
- Every `job_orders` row (existing and future) now has a unique, human-readable `JO-{year}-{seq}` number — verified against real pre-existing multi-year data, not just an empty table
- `production_logs` exists as a full ERD table + Eloquent model, audit-observed, with a nullable `recorded_by` supporting D-09's system-authored first row
- `JobOrderStatus` recognizes all four PROD-02 production stages
- `JobOrder::nextNumberForYear()` is lock-safe and per-year-scoped, proven by dedicated tests for sequential increment and independent-year sequences

## Task Commits

Each task was committed atomically (Task 2 used TDD: test → feat):

1. **Task 1: Additive migrations + JobOrderStatus enum extension** - `c94a58e` (feat)
2. **Task 2: ProductionLog model + JobOrder number generator + tests** - `a11bf3c` (test, RED) → `00a60a4` (feat, GREEN)

## Files Created/Modified
- `database/migrations/2026_09_05_120000_add_number_and_due_at_to_job_orders_table.php` - adds `number`/`due_at`, backfills every existing row
- `database/migrations/2026_09_05_120100_create_production_logs_table.php` - new `production_logs` table
- `app/Enums/JobOrderStatus.php` - appends `ForProduction`, `Printing`, `QualityCheck`, `ReadyForPickup`
- `app/Models/ProductionLog.php` - new model, `#[ObservedBy(AuditObserver::class)]`, `jobOrder()`/`recordedBy()` relations
- `database/factories/ProductionLogFactory.php` - system-authored-first-row default shape
- `app/Models/JobOrder.php` - `number` fillable, `due_at` cast, `productionLogs()` relation, `nextNumberForYear()`/`currentNumberingYear()`
- `database/factories/JobOrderFactory.php` - default unique `number` per creation
- `tests/Feature/JobOrder/JobOrderNumberGeneratorTest.php` - format, sequential increment, per-year independence
- `tests/Feature/JobOrder/ProductionLogModelTest.php` - audit-trail write, null round-trip, relation resolution

## Decisions Made
- Explicit `dropUnique(['number'])` before `dropColumn()` in the new migration's `down()` — see Deviations below.
- `'number'` is Fillable on `JobOrder` (matches `queue_number` precedent on `QueueEntry`); `due_at` deliberately stays out of Fillable, system-only via `forceFill()` in a later plan.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed a dishonest migration `down()` that corrupted subsequent inserts on SQLite**
- **Found during:** Task 1, while manually verifying the backfill logic against real pre-existing multi-year data (rollback → seed rows across two years → re-migrate → assert correct `JO-{year}-{seq}` assignment)
- **Issue:** This repo's local SQLite (3.31.1) predates native `ALTER TABLE DROP COLUMN` (added in 3.35). Laravel's SQLite grammar falls back to recreating the table, but the plan's original `down()` (`dropColumn(['number', 'due_at'])` alone) left the `job_orders_number_unique` index behind, still pointing at the now-gone column. The very next `JobOrder` insert after a rollback failed with `UNIQUE constraint failed: index 'job_orders_number_unique'` even though the column referenced didn't exist anymore.
- **Fix:** Added `$table->dropUnique(['number']);` immediately before `dropColumn()` in `down()`. Verified by rolling back, inserting three job orders across two different years, re-running the migration, and confirming correct backfilled numbers (`JO-2025-0001`, `JO-2025-0002`, `JO-2026-0001`) with no constraint errors.
- **Files modified:** `database/migrations/2026_09_05_120000_add_number_and_due_at_to_job_orders_table.php`
- **Committed in:** `c94a58e` (Task 1 commit)

**2. [Rule 3 - Blocking] Bootstrapped the fresh worktree's build/runtime environment**
- **Found during:** Start of Task 1 verification — `vendor/bin/pint`/`vendor/bin/pest`/`php artisan migrate` all failed because this worktree had no `vendor/`, no `.env`, and no SQLite database file (all gitignored, never checked out into a fresh worktree)
- **Issue:** Nothing in the plan's scope, but every acceptance-criteria command was blocked without it
- **Fix:** `composer install`, `cp .env.example .env`, `php artisan key:generate`, created `database/database.sqlite`. Later, full-suite verification also required `npm ci` + `npm run build` (the Vite manifest was missing, causing 86 unrelated Inertia-rendering tests to fail with `ViteManifestNotFoundException` before the build — confirmed via a scoped rerun that these were 100% pre-existing render-path failures, not caused by this plan's changes, and resolved cleanly once assets were built)
- **Files modified:** none tracked (`.env`, `database/database.sqlite`, `vendor/`, `node_modules/`, `public/build/` are all gitignored)
- **Committed in:** N/A (no trackable file changes)

---

**Total deviations:** 2 auto-fixed (1 bug, 1 blocking/environment)
**Impact on plan:** Both fixes were necessary to deliver working, verifiable substrate. No scope creep — no plan files were touched beyond what Task 1/2 specified.

## Issues Encountered

- **Pre-existing Larastan failures unrelated to this plan** (logged to `deferred-items.md`, not fixed, per scope-boundary rule):
  - `app/Http/Controllers/FrontlineStaff/QueueEntryController.php:181` — a `match ($jobOrder->status)` expression was already non-exhaustive before this plan (missing `InConsultation`/`InDesign`/`PendingReview`/`DesignApproved` arms; confirmed by re-running phpstan against the pre-Phase-6 enum). This plan's four new `JobOrderStatus` cases add four more unhandled arms to the same already-broken match — belongs to whichever later plan owns Frontline status copy.
  - `app/Http/Requests/Cashier/CreateCreditRequestRequest.php`, `app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php`, `app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php` — pre-existing Phase 5 `property.notFound`/`method.notFound` errors, verified unrelated to any file this plan touches.
  - `composer types:check` run project-wide still reports these 6 pre-existing errors (unchanged in nature/count from before this plan, aside from the QueueEntryController match gaining more unhandled arms as described above); scoped to just this plan's two edited/created model files (`app/Models/JobOrder.php`, `app/Models/ProductionLog.php`), Larastan is clean.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Every downstream Phase 6 plan (production board, tracking page, Frontline alert, receipt QR) can now rely on: a real `number` to look up, `due_at` to stamp, `production_logs` to write, and all four `JobOrderStatus` production-stage cases to sequence through.
- The Larastan `match.unhandled` gap in `QueueEntryController` should be picked up by whichever plan next touches Frontline intake-outcome messaging, since it now has more unhandled arms than before this plan.

---
*Phase: 06-production-monitoring-public-tracking*
*Completed: 2026-09-05*
