---
phase: 06-production-monitoring-public-tracking
plan: 02
subsystem: job-order-intake
tags: [inertia, vue, eloquent, larastan, tdd]

# Dependency graph
requires:
  - phase: 06-01
    provides: "JobOrder::nextNumberForYear()/currentNumberingYear(), job_orders.number column (already backfilled+unique)"
provides:
  - "Every job order created via frontline-staff.queue-entries.store() or .job-orders.store() (addJobOrder) is assigned a real JO-{year}-#### number at creation time, sequential within its numbering year"
  - "Frontline Queue, Cashier Dashboard, and Owner Credit Requests all render the job order number (tabular-nums span) alongside the description they already showed"
  - "Cashier Dashboard and Owner Credit Requests Inertia responses carry the job order's number field"
affects: [06-03, 06-04, 06-05, 06-06, 06-07, 06-08]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Number assignment happens inline inside the existing DB::transaction() at job-order create() time, calling JobOrder::nextNumberForYear(JobOrder::currentNumberingYear()) — the generator's own lockForUpdate() nests safely inside the surrounding transaction (no separate locking scheme needed)"
    - "Two-line tabular-nums stack (number above description) inside a single existing table cell/row slot — enriches an existing column instead of adding a new one, matching UI-SPEC's 'identifier swap only, no new columns' framing"

key-files:
  created:
    - tests/Feature/FrontlineStaff/JobOrderNumberAssignmentTest.php
  modified:
    - app/Http/Controllers/FrontlineStaff/QueueEntryController.php
    - app/Http/Controllers/Cashier/DashboardController.php
    - app/Http/Controllers/Owner/CreditApprovalController.php
    - resources/js/pages/frontline-staff/QueueList.vue
    - resources/js/pages/cashier/Dashboard.vue
    - resources/js/pages/owner/CreditRequests.vue

key-decisions:
  - "Added 'number' to Cashier DashboardController's get() column allowlist per the plan's literal instruction, even though it is currently a no-op: withSum() pre-populates the query builder's $columns with table.* before get() runs, and Laravel's Query\\Builder::onceWithColumns() only applies the get() argument when $columns is still null — so the Cashier Dashboard response already carried every column (including number) before this change. Kept the explicit addition anyway since it documents intent and survives a future refactor that removes withSum()."

requirements-completed: [TRACK-01]

# Metrics
duration: ~20min (includes one-time worktree environment bootstrap: composer install, npm ci, npm run build)
completed: 2026-09-06
---

# Phase 6 Plan 2: Job Order Number Assignment + Display Summary

**Wires `JobOrder::nextNumberForYear()` into `QueueEntryController::store()`/`addJobOrder()` and surfaces the resulting `JO-{year}-####` number on Queue, Cashier Dashboard, and Owner Credit Requests.**

## Performance

- **Duration:** ~20 min (includes one-time worktree bootstrap: `composer install`, `npm ci`, `npm run build` — this worktree had none of vendor/, node_modules/, .env, or built assets)
- **Tasks:** 2 completed
- **Files modified:** 7 (1 created test, 3 modified controllers, 3 modified Vue pages)

## Accomplishments

- Every job order created through the Frontline intake flow (`store()`, one-or-many job orders per visit) or added mid-visit (`addJobOrder()`) now gets a real, sequential, per-year `JO-{year}-####` number at the moment of creation — proven by a dedicated test that submits two job orders in one visit and asserts distinct sequential numbers, and a second test that continues the sequence correctly from a pre-existing highest number.
- The Frontline Queue, Cashier Dashboard, and Owner Credit Requests pages all render the number in a `tabular-nums` span next to the description they already showed, with zero existing information removed.
- Confirmed via a scoped Larastan run that no new static-analysis issues were introduced by this plan's controller/model-adjacent files.

## Task Commits

Each task was committed atomically (Task 1 used TDD: test → feat):

1. **Task 1: Assign job order numbers at creation; expose number in Cashier and Owner selects** - `f498af8` (test, RED) → `3364b12` (feat, GREEN)
2. **Task 2: Display the job order number on Queue, Cashier Dashboard, and Owner Credit Requests** - `8147870` (feat)

## Files Created/Modified

- `tests/Feature/FrontlineStaff/JobOrderNumberAssignmentTest.php` - covers sequential number assignment on `store()`/`addJobOrder()`, and number exposure on the Cashier Dashboard and Owner Credit Requests Inertia responses
- `app/Http/Controllers/FrontlineStaff/QueueEntryController.php` - `store()`/`addJobOrder()` now pass `'number' => JobOrder::nextNumberForYear(JobOrder::currentNumberingYear())` into each job order's `create()` array; `index()`'s eager-loaded `jobOrders` column list now includes `number`
- `app/Http/Controllers/Cashier/DashboardController.php` - `index()`'s `get([...])` column list now includes `number` (see key-decisions re: this being a documented no-op today)
- `app/Http/Controllers/Owner/CreditApprovalController.php` - `index()`'s `jobOrder` eager-load now selects `id,number,description` instead of `id,description`
- `resources/js/pages/frontline-staff/QueueList.vue` - `JobOrderRecord.number: string | null`; number rendered in a `tabular-nums` span immediately before the description
- `resources/js/pages/cashier/Dashboard.vue` - `CashierJobOrder.number: string | null`; Job Order cell now stacks number above description in a `flex flex-col`
- `resources/js/pages/owner/CreditRequests.vue` - `CreditRequest.job_order.number: string | null`; identical two-line stack applied to the Job Order cell

## Decisions Made

- Kept the explicit `'number'` addition to `DashboardController::index()`'s column allowlist even though it has no observable effect today, per the plan's literal instruction and to guard against a future refactor removing `withSum()` (see key-decisions above for the full mechanism).
- No new database queries, indexes, or schema changes — this plan is purely wiring an existing generator (06-01) into existing create-paths and existing eager-loads/`get()` calls.
- **Left `REQUIREMENTS.md`'s TRACK-01 checkbox unmarked.** TRACK-01's actual text is "A customer can enter a job order number on a public, unauthenticated page and see the order's current status" — the public tracking page itself. This plan only assigns/displays the number on _internal, authenticated_ staff surfaces (Frontline Queue, Cashier Dashboard, Owner Credit Requests); it builds none of the public-facing lookup page. The `requirements: [TRACK-01]` in this plan's frontmatter (and `requirements-completed` below, per the summary template's mechanical instruction to copy that field) reflects that this plan is a _necessary prerequisite_ for TRACK-01 (a customer can't look up a number that was never assigned), not that TRACK-01 is fully satisfied. Ran `requirements mark-complete TRACK-01` once, saw it flip the checkbox, recognized the mismatch against the actual requirement text, and reverted via `git checkout -- .planning/REQUIREMENTS.md` before staging anything — the checkbox should be flipped by whichever later Phase 6 plan actually ships the public tracking page.

## Deviations from Plan

None — plan executed exactly as written. One noteworthy (non-deviation) finding is documented above and in Issues Encountered: the Cashier Dashboard's column-restricted `get([...])` call was already a no-op before this plan due to `withSum()`'s interaction with `Illuminate\Database\Query\Builder::onceWithColumns()`.

## Issues Encountered

- **`withSum()` silently defeats a subsequent `get([columns])` column restriction.** `JobOrder::query()->withSum(...)` populates the query builder's underlying `$columns` with `table.*` plus the aggregate subselect before the query ever executes. Laravel's `Query\Builder::onceWithColumns()` (which backs `get($columns)`) only applies its argument when `$columns` is still `null` at call time — so by the time `DashboardController::index()` calls `->get(['id', 'description', ...])`, the restriction is a no-op and every column (including `number`, both before and after this plan) is already present in the response. Confirmed via `php artisan tinker` reproducing the exact controller query and inspecting `getAttributes()`. This is a pre-existing Laravel/Eloquent interaction unrelated to this plan's changes — not fixed or altered, since the acceptance criterion ("Cashier Dashboard response includes number") was already met either way, and the explicit column addition remains harmless documentation of intent.
- **`composer types:check` (Larastan) does not exit 0 project-wide** — 6 pre-existing errors, unchanged in nature from Wave 1's documented state (`.planning/phases/06-production-monitoring-public-tracking/deferred-items.md`): 1 `match.unhandled` in `QueueEntryController::jobOrderOutcomeToastMessage()` (now at line 183, shifted by +2 from this plan's added lines above it in `store()`/`addJobOrder()` — same unresolved arms as before, not touched by this plan) and 5 pre-existing Phase 5 `property.notFound`/`method.notFound` errors in Cashier/Owner Form Requests. Verified via a scoped `phpstan analyse` against only this plan's touched files (`DashboardController.php`, `CreditApprovalController.php`, `JobOrder.php`) — 0 errors. Not fixed, per scope-boundary rule and Wave 1's own precedent of deferring the same items.
- Fresh worktree required full environment bootstrap (`composer install`, `.env`, `database.sqlite`, `php artisan migrate`, `npm ci`, `npm run build`) before any test/build command would run — same one-time cost documented by Wave 1's summary, not repeated project state.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Job orders created from this point forward always carry a real `number`, satisfying the substrate every remaining Phase 6 plan (production board, public tracking page, receipt QR) needs to look one up by.
- The Larastan `match.unhandled` gap in `QueueEntryController::jobOrderOutcomeToastMessage()` remains open and unowned — still flagged (per Wave 1's note) for whichever plan next touches Frontline intake-outcome messaging or production-stage status copy.

---

_Phase: 06-production-monitoring-public-tracking_
_Completed: 2026-09-06_

## Self-Check: PASSED

All 7 claimed files verified present on disk; all 3 claimed commit hashes (`f498af8`, `3364b12`, `8147870`) verified present in `git log --oneline --all`.
