---
phase: 06-production-monitoring-public-tracking
plan: 06
subsystem: ui
tags: [inertia, vue, eloquent, polling, tdd, production-staff, larastan]

# Dependency graph
requires:
  - phase: 06-01
    provides: "job_orders.due_at, production_logs table + ProductionLog model, JobOrderStatus::ForProduction/Printing/QualityCheck/ReadyForPickup"
  - phase: 06-04
    provides: "EnterProduction wiring — job orders now automatically reach ForProduction, giving the board real data to render"
  - phase: 06-05
    provides: "routes/portals.php's production-staff group precedent + the 5000ms usePoll pattern shared with the other two polled surfaces"
provides:
  - "app/Http/Controllers/ProductionStaff/ProductionBoardController.php: index() lists ForProduction/Printing/QualityCheck/ReadyForPickup job orders, excluding released/cancelled, with a server-computed is_rush boolean"
  - "app/Http/Controllers/ProductionStaff/ProductionStageController.php: advance()/sendBack() move a job order exactly one production stage forward or back, each writing a ProductionLog row under a database lock"
  - "The real production-staff/Dashboard.vue — rush banner, four stage stat cards, filter tabs, urgency-coded table, Advance/Send Back actions — replacing the Phase-1 placeholder"
  - "resources/js/config/nav/production-staff.ts: the Production Board nav entry"
affects: [06-07, 06-08]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Sequence-boundary array offset access must be checked against a literal-resolvable expression (count(SEQUENCE) - 1), not a function-call result (array_key_last()), for Larastan to statically narrow the post-abort_if offset as safe — abort_if($x === 0, ...) narrows correctly since 0 is a literal, but abort_if($x === array_key_last(...), ...) does not, since PHPStan can't treat a function call's return value as a compile-time literal for narrowing purposes"
    - "A page-level 'stale-move' Alert sourced from the same router.on('flash', ...) event the global toast listener uses (resources/js/lib/flashToast.ts), scoped to the component's own onMounted/onUnmounted lifecycle, for surfacing a business-rule-conflict 422 message (which arrives as a flashed error toast via bootstrap/app.php's exception responder, not a validation errors bag) as a persistent on-page element in addition to the dismissable toast"

key-files:
  created:
    - app/Http/Controllers/ProductionStaff/ProductionBoardController.php
    - app/Http/Controllers/ProductionStaff/ProductionStageController.php
    - app/Http/Requests/ProductionStaff/AdvanceProductionStageRequest.php
    - app/Http/Requests/ProductionStaff/SendBackProductionStageRequest.php
    - app/Concerns/ProductionLogValidationRules.php
    - resources/js/config/nav/production-staff.ts
    - tests/Feature/ProductionStaff/ProductionBoardTest.php
    - tests/Feature/ProductionStaff/StageAdvancementTest.php
  modified:
    - routes/portals.php
    - resources/js/pages/production-staff/Dashboard.vue
    - app/Models/JobOrder.php

key-decisions:
  - "Added an @property bool|null $is_rush PHPDoc entry to JobOrder, matching the existing amount_paid precedent — Larastan level 7 flagged the dynamically-set virtual attribute as property.notFound without it"
  - "Rewrote advance()'s SEQUENCE-end boundary check from array_key_last(self::SEQUENCE) to count(self::SEQUENCE) - 1 so Larastan can statically prove the subsequent offset access is safe, mirroring sendBack()'s already-correctly-narrowed literal-0 boundary check"
  - "Flash/DB::transaction() closures return [$jobOrder, $status] tuples so the success toast (built from the fresh $jobOrder->number and the resolved stage) is flashed after the transaction commits, not from inside it, matching the plan's described sequencing"

requirements-completed: [PROD-01, PROD-02]

# Metrics
duration: ~50min (includes one-time worktree environment bootstrap: composer install, npm ci, npm run build)
completed: 2026-09-07
---

# Phase 6 Plan 6: Production Board Summary

**The Production Board itself — Production Staff can see every job order in production color-coded by urgency (never from rush_fee_applied) and move it exactly one stage forward or back with a mandatory, logged Send Back reason, via a new ProductionBoardController/ProductionStageController pair and a real production-staff/Dashboard.vue replacing its placeholder.**

## Performance

- **Duration:** ~50 min (includes one-time worktree bootstrap: `composer install`, `npm ci`, `npm run build` — this worktree had none of vendor/, node_modules/, .env, or built assets)
- **Tasks:** 3 completed (Task 1 and Task 3 were TDD: RED test commit -> GREEN implementation commit; Task 2 was a single UI commit)
- **Files modified:** 11 (7 created, 4 modified — see key-files)

## Accomplishments

- `ProductionBoardController::index()` lists exactly the job orders that belong on the board (`ForProduction`/`Printing`/`QualityCheck`/`ReadyForPickup`, excluding `released_at`/`cancelled_at`), each carrying a server-authoritative `is_rush` boolean derived purely from `due_at` vs. today — never from `rush_fee_applied` (D-05/D-07)
- The board never selects or eager-loads `payment_status`/`total_amount` — verified by a dedicated response-content test, matching the UI-SPEC's "no payment hint on this surface" rule
- A real `production-staff/Dashboard.vue`: rush banner (amber, never destructive, Zap icon + "Rush" text always paired with colour), four stage stat cards, client-side filter tabs (All/Rush/four stages — the whole board is a handful of rows, never a server round-trip to filter), and an urgency-coded table with per-row Urgency/Stage badges that carry text, not colour alone
- `ProductionStageController::advance()`/`sendBack()` move a job order exactly one stage in the fixed `SEQUENCE`, re-reading the row under `lockForUpdate()` inside `DB::transaction()` before writing a `ProductionLog` row — closing the double-submit race that would otherwise log two transitions for one logical move
- Both sequence boundaries (`advance()` at `ReadyForPickup`, `sendBack()` at `ForProduction`) reject with the exact Copywriting Contract message ("This job order already moved on...") and write no log row — proven by dedicated 422/flash-toast tests
- Send Back requires a mandatory `reason` (`ProductionLogValidationRules`), persisted on the `ProductionLog` row alongside the acting user's id; a cancelled job order rejects both actions regardless of its `status`
- Dashboard.vue's Actions cell: an Advance button labelled with the real destination stage, a plain `Dialog` (never `AlertDialog` — corrective, not destructive) for Send Back with a required Reason `Textarea`, and a page-level stale-move `Alert` (reusing the existing `AlertError.vue` component) sourced from the same flash-toast event the global toast listener uses

## Task Commits

Each task was committed atomically; Tasks 1 and 3 used TDD (test -> feat):

1. **Task 1: Production Board controller, route, and nav** - `2c4586c` (test, RED) -> `4e6fdc7` (feat, GREEN)
2. **Task 2: Production Board UI — stat cards, filter tabs, urgency-coded table** - `7b6a3c2` (feat)
3. **Task 3: Stage advancement — advance() and sendBack()** - `3d33222` (test, RED) -> `66b560f` (feat, GREEN)

## Files Created/Modified

- `app/Http/Controllers/ProductionStaff/ProductionBoardController.php` - `index()` listing query with the `is_rush` computation
- `app/Http/Controllers/ProductionStaff/ProductionStageController.php` - `advance()`/`sendBack()`, the fixed `SEQUENCE`, `stageLabel()`
- `app/Http/Requests/ProductionStaff/AdvanceProductionStageRequest.php` - body-less by design (target stage is server-derived)
- `app/Http/Requests/ProductionStaff/SendBackProductionStageRequest.php` - `reason` required via the shared trait
- `app/Concerns/ProductionLogValidationRules.php` - `sendBackReasonRules()` trait
- `resources/js/config/nav/production-staff.ts` - Production Board nav entry
- `resources/js/pages/production-staff/Dashboard.vue` - full rewrite: board UI + stage actions
- `routes/portals.php` - `production-staff.dashboard` swapped from `Route::inertia` to the controller; two new PATCH routes added
- `app/Models/JobOrder.php` - `@property bool|null $is_rush` PHPDoc entry (Larastan)
- `tests/Feature/ProductionStaff/ProductionBoardTest.php` - 12 tests covering fields, urgency boundary, exclusions, payment-leak boundary, role gating
- `tests/Feature/ProductionStaff/StageAdvancementTest.php` - 13 tests covering forward/back movement, both sequence boundaries, mandatory-reason validation, cancelled rejection, role gating

## Decisions Made

- `is_rush` documented via `@property` PHPDoc on `JobOrder`, matching the `amount_paid` precedent exactly (both are dynamically-set virtual attributes computed in a controller, never persisted columns)
- `advance()`'s end-of-sequence boundary check uses `count(self::SEQUENCE) - 1` instead of the plan's literally-described `array_key_last(self::SEQUENCE)` — a direct, mechanical Larastan-narrowing fix (see Deviations)
- The success/error toast for both `advance()` and `sendBack()` is flashed after `DB::transaction()` returns (not from inside the closure), matching the plan's described "after the transaction" sequencing — achieved by having the transaction closure return the data the toast needs

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Documented the virtual `is_rush` attribute on `JobOrder` for Larastan**

- **Found during:** Task 1, running a scoped `phpstan analyse` against the new controller
- **Issue:** `ProductionBoardController::index()`'s `->each(fn (JobOrder $jobOrder) => $jobOrder->is_rush = ...)` sets a dynamic attribute Larastan doesn't know about, reporting `property.notFound`
- **Fix:** Added `@property bool|null $is_rush` to `JobOrder`'s docblock, identical in shape to the pre-existing `amount_paid` entry
- **Files modified:** `app/Models/JobOrder.php`
- **Verification:** Scoped `phpstan analyse` against the controller + model reports 0 errors
- **Committed in:** `4e6fdc7` (Task 1 GREEN commit)

**2. [Rule 1 - Bug] Fixed a Larastan-unprovable array offset access at the advance() sequence boundary**

- **Found during:** Task 3, running a scoped `phpstan analyse` against the new controller
- **Issue:** `abort_if($currentIndex === array_key_last(self::SEQUENCE), ...)` followed by `self::SEQUENCE[$currentIndex + 1]` reported `offsetAccess.notFound` — Larastan/PHPStan does not treat `array_key_last()`'s return value as a compile-time literal for the purposes of narrowing `$currentIndex`'s type after the `abort_if` check, unlike `sendBack()`'s boundary check against the literal `0`, which narrowed correctly and left that offset access clean
- **Fix:** Rewrote the boundary comparison as `$currentIndex === count(self::SEQUENCE) - 1` — `count()` on a `private const` literal array resolves to a literal int for PHPStan's type inference, restoring the same narrowing `sendBack()` already had. No behavior change: `count(self::SEQUENCE) - 1` and `array_key_last(self::SEQUENCE)` are numerically identical for this fixed 4-element array.
- **Files modified:** `app/Http/Controllers/ProductionStaff/ProductionStageController.php`
- **Verification:** Scoped `phpstan analyse` against all 5 Task 3 PHP files reports 0 errors; `vendor/bin/pest tests/Feature/ProductionStaff/StageAdvancementTest.php` still passes 13/13 after the change
- **Committed in:** `66b560f` (Task 3 GREEN commit)

---

**Total deviations:** 2 auto-fixed (both Rule 1 — Larastan-driven correctness fixes with no behavior change)
**Impact on plan:** Both fixes were necessary for `composer types:check` to report zero new errors from this plan's own files. No scope creep — no plan files were touched beyond what Tasks 1/3 specified, and neither fix altered any tested behavior.

## Issues Encountered

- **`composer types:check` (Larastan) does not exit 0 project-wide** — the same 5 pre-existing errors documented in `deferred-items.md` since 06-01/06-04/06-05 (`property.notFound`/`method.notFound` in three Phase 5 Cashier/Owner Form Requests), unrelated to any file this plan touches. A scoped `phpstan analyse` against every PHP file this plan created/modified (`ProductionBoardController.php`, `ProductionStageController.php`, both Form Requests, `ProductionLogValidationRules.php`, `routes/portals.php`, `JobOrder.php`) reports 0 errors.
- Fresh worktree required full environment bootstrap (`composer install`, `.env`, `database.sqlite`, `php artisan migrate`, `npm ci`, `npm run build`) before any test/build command would run — same one-time cost documented by every prior Phase 6 plan's summary, not repeated project state.
- Full project-wide test suite: 345 tests, 342 passed, 3 skipped, 0 failures (baseline before this plan: 320 tests, 317 passed, 3 skipped — the +25 delta is exactly this plan's own new tests: 12 in `ProductionBoardTest.php` + 13 in `StageAdvancementTest.php`).
- `routes/portals.php` was also touched by the concurrently-executing 06-05 (merged before this plan started) and is being touched concurrently by 06-07 in a parallel worktree per the orchestrator's guidance — this plan's only changes were the `production-staff` group's route line swap and two new PATCH routes, kept additive and scoped to that one group.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- The Production Board (PROD-01/PROD-02) is fully live: a job order automatically entering production via 06-04's `EnterProduction` wiring is now visible, urgency-coded, and actionable by Production Staff — closing the last gap in the "job order flows correctly end-to-end" core value.
- 06-07 (concurrent parallel plan, not this plan's scope) owns fixing stale `DesignApproved`/`ReadyForProduction` status-display consumers elsewhere in Cashier/Artist/Frontline surfaces — none of those files were touched here, per this plan's explicit scope boundary.
- 06-08 (next wave, if any) can rely on: a real, queryable `production_logs` audit trail with both system-authored (06-04) and staff-authored (this plan) rows, ready for any future reprint/waste reporting.

---

_Phase: 06-production-monitoring-public-tracking_
_Completed: 2026-09-07_

## Self-Check: PASSED

All 12 claimed files verified present on disk; all 6 claimed commit hashes
(`2c4586c`, `4e6fdc7`, `7b6a3c2`, `3d33222`, `66b560f`, `77a86fb`) verified
present in `git log --oneline --all`.
