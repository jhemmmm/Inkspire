---
phase: 06-production-monitoring-public-tracking
plan: 05
subsystem: ui
tags: [inertia, vue, eloquent, polling, tdd, frontline-staff]

# Dependency graph
requires:
  - phase: 06-01
    provides: "JobOrderStatus::ReadyForPickup, job_orders.number, job_orders.due_at"
  - phase: 06-02
    provides: "JobOrder number assignment wired into every creation path"
  - phase: 06-04
    provides: "Automatic production entry — job orders now flow through ForProduction/Printing/QualityCheck to reach ReadyForPickup"
provides:
  - "app/Http/Controllers/FrontlineStaff/DashboardController.php: index() renders frontline-staff/Dashboard with a readyForPickup job-order list (status=ready_for_pickup, released_at/cancelled_at null)"
  - "QueueEntryController::index() readyForPickup summary prop ({ count, items }) built from one cloned base query"
  - "routes/portals.php: frontline-staff.dashboard now routed through DashboardController@index (route name unchanged)"
  - "A real frontline-staff/Dashboard.vue (Ready for Pickup table with a second Release to Customer entry point) replacing the Phase-1 placeholder"
  - "A polled ready-for-pickup banner on frontline-staff/QueueList.vue"
affects: [06-06, 06-07, 06-08]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Cloned Eloquent query builder ($query->clone()) reused for both a count() and a limited get() so neither call mutates the other's query state — same base WHERE clause, two independent executions"
    - "Model::withoutTimestamps() used in test setup to backdate updated_at for ordering assertions, since a plain forceFill()->save() would have its explicit updated_at overwritten by Eloquent's own timestamp management"

key-files:
  created:
    - app/Http/Controllers/FrontlineStaff/DashboardController.php
    - tests/Feature/FrontlineStaff/ReadyForPickupAlertTest.php
  modified:
    - app/Http/Controllers/FrontlineStaff/QueueEntryController.php
    - routes/portals.php
    - resources/js/pages/frontline-staff/Dashboard.vue
    - resources/js/pages/frontline-staff/QueueList.vue

key-decisions:
  - "Dashboard.vue's isReleaseEligible() checks payment_status only (paid/on_credit), omitting the released_at === null check that QueueList.vue's equivalent guard carries — the DashboardController query already filters whereNull('released_at') server-side, and released_at isn't part of this page's prop shape at all (Task 1's explicit column allowlist omits it), so the field doesn't exist client-side to check"
  - "Banner/dashboard copy pluralizes and grammatically adapts for count=1 (\"is waiting\" vs \"are waiting\", \"1 job order\" vs \"n job orders\") since the Copywriting Contract's template assumes 2+ items but PROD-03 can legitimately produce exactly one"

requirements-completed: [PROD-03]

# Metrics
duration: ~25min (includes one-time worktree environment bootstrap: composer install, npm ci, npm run build)
completed: 2026-09-07
---

# Phase 6 Plan 5: Frontline Ready-for-Pickup Alert Summary

**A derived, polled Ready for Pickup list on the Frontline Dashboard (with a second Release to Customer entry point) plus a matching banner on the Queue page — both self-correct on every 5-second poll with nothing stored.**

## Performance

- **Duration:** ~25 min (includes one-time worktree bootstrap: `composer install`, `npm ci`, `npm run build` — this worktree had none of vendor/, node_modules/, .env, or built assets)
- **Tasks:** 2 completed (Task 1 was TDD: RED test commit -> GREEN implementation commit)
- **Files modified:** 6 (2 created, 4 modified)

## Accomplishments

- `DashboardController::index()` renders a `readyForPickup` list filtered to `status=ready_for_pickup` with `released_at`/`cancelled_at` both null, eager-loading `queueEntry.customer:id,name` for the customer column
- `QueueEntryController::index()` gains a `readyForPickup` summary prop (`{ count, items }`) built from a single base query, cloned separately for `count()` and the two-oldest `items` fetch so neither execution disturbs the other
- `routes/portals.php`'s `frontline-staff.dashboard` route now goes through `DashboardController@index` instead of `Route::inertia`, matching the cashier/accounting-staff precedent, route name unchanged
- Replaced the Phase-1 placeholder `frontline-staff/Dashboard.vue` with a real Ready for Pickup table (Job Order / Customer / Description / Ready Since / Payment / Actions), polling every 5000ms via `usePoll({ only: ['readyForPickup'] })`, reusing the exact cashier `Dashboard.vue` payment-status badge chain and the exact `QueueList.vue` Release-to-Customer `Form` block as a second entry point to the same Phase 5 gated action
- Added a polled ready-for-pickup `Alert` banner to `QueueList.vue` (PackageCheck icon, pluralized heading, first-two-names body, link to the Dashboard) so staff already on that page see the same self-correcting alert without navigating away
- Proved the self-correction behavior end-to-end: a job order manually sent back from `ReadyForPickup` to `QualityCheck` disappears from both the Dashboard list and the Queue page summary on the very next query, with no code path needed to clear it

## Task Commits

Each task was committed atomically; Task 1 used TDD (test -> feat):

1. **Task 1: Ready-for-pickup queries — Frontline Dashboard controller + Queue banner data** - `a551cca` (test, RED) -> `5c54a83` (feat, GREEN)
2. **Task 2: Frontline Dashboard UI + Queue page banner (D-13, D-14)** - `b7192d0` (feat) -> `452c137` (style: formatting fix)

## Files Created/Modified

- `app/Http/Controllers/FrontlineStaff/DashboardController.php` - new controller; `index()` renders the readyForPickup list
- `app/Http/Controllers/FrontlineStaff/QueueEntryController.php` - `index()` gains the `readyForPickup` summary prop via a cloned base query
- `routes/portals.php` - `frontline-staff.dashboard` swapped from `Route::inertia` to `DashboardController@index`
- `resources/js/pages/frontline-staff/Dashboard.vue` - full rewrite: real Ready for Pickup table, polling, Release to Customer second entry point
- `resources/js/pages/frontline-staff/QueueList.vue` - added `readyForPickup` prop, polling, and the ready-for-pickup `Alert` banner
- `tests/Feature/FrontlineStaff/ReadyForPickupAlertTest.php` - 7 tests covering both endpoints' filtering, exclusions, and self-correction on send-back

## Decisions Made

- `Dashboard.vue`'s release-eligibility predicate omits the `released_at === null` check that `QueueList.vue`'s carries — `released_at` isn't part of this page's prop shape (Task 1's explicit `get([...])` allowlist excludes it) and the backend query already guarantees it's null for every row in this list, so the check would be dead code referencing an undefined field.
- Banner and Dashboard copy handle the singular case (exactly one ready-for-pickup order) with correct grammar ("1 job order ... is waiting") even though the Copywriting Contract's example templates assume 2+ items, since PROD-03 makes a count of exactly 1 a normal, expected state.
- Test setup uses `JobOrder::withoutTimestamps()` to backdate `updated_at` for the "oldest two" ordering assertion — a plain `forceFill(['updated_at' => ...])->save()` has its explicit value overwritten by Eloquent's own automatic timestamp management on save.

## Deviations from Plan

None — plan executed as written. The two items above are implementation decisions made within the plan's own explicit field lists and copy contract, not gaps, bugs, or scope changes.

## Issues Encountered

- **`npm run check` (vp check) flagged one formatting issue** in the newly-written `Dashboard.vue` (a `TableEmpty` line exceeding the wrapped-attribute convention). Not part of this plan's required verification commands, but scoped-fixed via `npx vp check --fix resources/js/pages/frontline-staff/Dashboard.vue` (touched only that one file) and committed separately (`452c137`) so the diff stays attributable.
- **`composer types:check` (Larastan) does not exit 0 project-wide** — the same 5 pre-existing errors documented in `deferred-items.md` since 06-01/06-04 (`property.notFound`/`method.notFound` in three Phase 5 Cashier/Owner Form Requests), unrelated to any file this plan touches. A scoped `phpstan analyse` against this plan's 3 modified/created PHP files (`DashboardController.php`, `QueueEntryController.php`, `routes/portals.php`) reports 0 errors.
- Fresh worktree required full environment bootstrap (`composer install`, `.env`, `database.sqlite`, `php artisan migrate`, `npm ci`, `npm run build`) before any test/build command would run — same one-time cost documented by every prior Phase 6 plan's summary, not repeated project state.
- Full project-wide test suite: 320 tests, 317 passed, 3 skipped, 0 failures (baseline before this plan: 313 tests, 310 passed, 3 skipped — the +7 delta is exactly this plan's own new `ReadyForPickupAlertTest.php` tests).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- The Frontline Ready-for-Pickup alert (PROD-03) is fully live: derived, polled, self-correcting, with zero new stored state.
- 06-06 (Production Board, next wave) also touches `routes/portals.php` — this plan's only change there was the `frontline-staff.dashboard` line and one new `use` import, kept additive and scoped per the orchestrator's guidance.
- 06-07 (next wave) also touches `resources/js/pages/frontline-staff/QueueList.vue` — this plan's only additions were the `readyForPickup` prop, the `usePoll` call, and the banner block above the existing table; no restructuring of surrounding code.

---

_Phase: 06-production-monitoring-public-tracking_
_Completed: 2026-09-07_

## Self-Check: PASSED

All 6 claimed files verified present on disk; all 4 claimed commit hashes (`a551cca`, `5c54a83`, `b7192d0`, `452c137`) verified present in `git log --oneline --all`.
