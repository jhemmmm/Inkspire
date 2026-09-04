---
phase: 05-pos-payments
plan: 05
subsystem: payments
tags: [laravel, inertia, vue3, pest, pos, payments, cancellation, alert-dialog]

# Dependency graph
requires:
  - phase: 05-01
    provides: pricing_database/transactions ledger, JobOrder payment columns, Cashier Dashboard, SystemConfiguration::getFloat()
  - phase: 05-04
    provides: Cashier Dashboard DropdownMenu with Check Payment Status item (pattern extended here)
provides:
  - CancellationController@store — cancels a job order, computing a flat cancellation_fee_amount server-side, netting any existing completed down payment against it (D-04/D-05)
  - cancellation_fee_amount system_configurations business rule
  - Cancel Job Order AlertDialog on the Cashier Dashboard with a four-variant pre-confirmation body
affects: [05-06, 05-07, 07-accounts-receivable, 08-reporting]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "AlertDialogTrigger as-child wraps a DropdownMenuItem with @select.prevent, nested inside its own AlertDialog root, to combine a destructive confirm dialog with a per-row dropdown action (avoids the DropdownMenu-closes-before-AlertDialog-opens focus-trap conflict)"
    - "Client-side dialog-body text mirrors a server-authoritative computation (design-started status set + fee/down-payment comparison) via a page-level prop, rather than a second round trip, for a fuller pre-confirmation UX"

key-files:
  created:
    - app/Http/Controllers/Cashier/CancellationController.php
    - app/Http/Requests/Cashier/CancelJobOrderRequest.php
    - tests/Feature/Cashier/CancellationFeeTest.php
  modified:
    - database/seeders/SystemConfigurationSeeder.php
    - routes/portals.php
    - app/Http/Controllers/Cashier/DashboardController.php
    - resources/js/pages/cashier/Dashboard.vue
    - tests/Unit/SystemConfigurationTest.php

key-decisions:
  - "CancellationFee shortfall transactions default payment_method to Cash, since the confirm-only AlertDialog has no method picker (plan-flagged discretion call)"
  - "DashboardController::index also gained amount_paid (withSum) and a cancellationFeeAmount page prop beyond the plan's literal whereNull('cancelled_at') instruction, to support the frontend's four-variant dialog body (plan's Task 2 explicitly names this as the preferred option (a))"

patterns-established:
  - "Pattern: money-moving confirm-only actions inside a DropdownMenu use AlertDialogTrigger as-child wrapping the DropdownMenuItem (not the reverse), with @select.prevent on the item, to avoid Radix's dropdown-closes-before-dialog-opens conflict"

requirements-completed: [POS-07]

# Metrics
duration: 45min
completed: 2026-09-05
---

# Phase 5 Plan 5: Job order cancellation with fee computation and netting Summary

**`CancellationController` cancels a job order, computing a flat `cancellation_fee_amount` server-side only when design work has started, netting any existing down payment against it — surfaced via a four-variant pre-confirmation `AlertDialog` nested inside the Cashier Dashboard's `DropdownMenu`.**

## Performance

- **Duration:** ~45 min (includes worktree environment setup — see Issues Encountered)
- **Completed:** 2026-09-05T17:01:49Z
- **Tasks:** 2/2 completed
- **Files modified:** 9 (3 created, 6 modified)

## Accomplishments
- `cancellation_fee_amount` business rule seeded alongside the existing `business_rules` group
- `CancellationController@store` gates on already-cancelled/already-fully-paid, determines design-started status per D-04's exact status set (`InDesign`/`PendingReview`/`DesignApproved`), and either cancels for free or collects/nets a cancellation fee per D-05 — the fee is always read server-side via `SystemConfiguration::getFloat()`, never trusted from the client (T-05-13)
- `DashboardController::index` now excludes cancelled job orders (`whereNull('cancelled_at')`) and exposes per-row `amount_paid` plus a page-level `cancellationFeeAmount`, so the Cashier Dashboard can compute the correct dialog body before the Cashier confirms
- Cancel Job Order `AlertDialog` added to each eligible Dashboard row's `DropdownMenu`, matching `DesignOverrides.vue`'s Trigger/Content/Header/Footer/Form-submit shape, with a `destructive`-styled trigger and confirm button per UI-SPEC

## Task Commits

Each task was committed atomically:

1. **Task 1: Backend — cancellation with fee computation and down-payment netting** - `56b66e6` (feat)
2. **Task 2: Frontend — Cancel Job Order AlertDialog** - `6e94388` (feat)

_No plan-metadata commit yet — SUMMARY.md commit follows this file._

## Files Created/Modified

**Task 1 (backend):**
- `database/seeders/SystemConfigurationSeeder.php` - added `cancellation_fee_amount` row
- `app/Http/Requests/Cashier/CancelJobOrderRequest.php` - `authorize()` true (route-gated), empty `rules()`
- `app/Http/Controllers/Cashier/CancellationController.php` - `store()` gate/compute/net/cancel
- `routes/portals.php` - `cashier.job-orders.cancel` POST route
- `app/Http/Controllers/Cashier/DashboardController.php` - `whereNull('cancelled_at')`, `amount_paid` withSum, `cancellationFeeAmount` prop
- `tests/Feature/Cashier/CancellationFeeTest.php` - 7 cases covering no-fee/full-fee/netted/shortfall/already-cancelled/already-paid
- `tests/Unit/SystemConfigurationTest.php` - seeded-row count updated 14→15

**Task 2 (frontend):**
- `resources/js/pages/cashier/Dashboard.vue` - Cancel Job Order `AlertDialog` + `cancellationDialogBody()` computing one of four Copywriting Contract variants
- `tests/Feature/Cashier/CancellationFeeTest.php` - added a case proving a cancelled job order drops off the Dashboard listing

## Decisions Made

- Cancellation-fee shortfall `Transaction`s always record `payment_method = Cash`, since the confirm-only `AlertDialog` has no payment-method selector (per the plan's own flagged discretion call — the simplest correct behavior matching the UI's confirm-only interaction).
- `DashboardController::index` was extended beyond the plan's literal `whereNull('cancelled_at')` instruction to also add `withSum` for `amount_paid` and a `cancellationFeeAmount` page prop — this directly implements the plan's Task 2 preferred option (a) ("pass `cancellation_fee_amount` as a page-level prop... Prefer (a) for the fuller, spec-accurate dialog-body experience"), even though `DashboardController.php` wasn't listed in the plan's top-level `files_modified` frontmatter (a plan-text/frontmatter mismatch — the action text for both Task 1 and Task 2 is unambiguous about the need).
- The Cancel Job Order `AlertDialog` is composed as `AlertDialogTrigger as-child` wrapping a `DropdownMenuItem` (with `@select.prevent`), each wrapped in its own per-row `AlertDialog` root, rather than controlling dialog `open` state externally via a ref. This is the standard Radix/shadcn-documented fix for the "DropdownMenu closes before AlertDialog can open" conflict, and it still matches the plan's literal "AlertDialogTrigger → AlertDialogContent" shape requirement.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Regenerated Wayfinder actions/routes after adding the new controller route**
- **Found during:** Task 2
- **Issue:** `resources/js/actions/App/Http/Controllers/Cashier/CancellationController.ts` didn't exist yet (gitignored, generated) — Task 2's `CancellationController.store.form()` call would fail to resolve
- **Fix:** Ran `php artisan wayfinder:generate --with-form --no-interaction` (matching the project's documented `--with-form` convention from Phase 1)
- **Files modified:** none tracked (generated output is gitignored)
- **Verification:** `npm run types:check` passes; generated file inspected to confirm `store.form(jobOrder.id)` shape
- **Committed in:** n/a (gitignored, not committed)

**2. [Rule 1 - Bug] Fixed pre-existing test broken by the new seeder row**
- **Found during:** Task 1
- **Issue:** `tests/Unit/SystemConfigurationTest.php` hardcoded an assertion of 14 seeded `system_configurations` rows; this plan's `cancellation_fee_amount` addition makes the real count 15
- **Fix:** Updated the test's expected count 14→15 and its description
- **Files modified:** `tests/Unit/SystemConfigurationTest.php`
- **Verification:** `php artisan test --compact --filter=SystemConfigurationTest` passes (8/8)
- **Committed in:** `56b66e6` (Task 1 commit)

---

**Total deviations:** 2 auto-fixed (1 blocking/generated-artifact, 1 bug/pre-existing-test)
**Impact on plan:** Both were necessary corrections uncovered while implementing the plan as specified — no scope creep, no architectural changes.

## Issues Encountered

- **Worktree had no `vendor/`, `node_modules/`, `.env`, or SQLite database.** Same class of issue documented in Plan 05-01's SUMMARY: this is a parallel git-worktree execution starting from a fresh checkout. Copied `vendor/` (161MB) and `node_modules/` (498MB) fully from the main checkout (not symlinked, per 05-01's documented `Cannot redeclare class ComposerAutoloaderInit...` lesson), ran `composer dump-autoload`, copied `.env` and created a fresh worktree-local SQLite database, ran `migrate:fresh --seed`, and ran `npm run build` once to generate `public/build/manifest.json`. Also discovered the worktree's initial `HEAD` was on a stale/orphan "init" commit (unrelated to Phase 5's history) rather than the expected base commit — corrected via `git reset --hard` to the phase's actual latest commit before starting any work, per the mandatory pre-work branch check.
- **`composer types:check` (Larastan/PHPStan) reports 5 pre-existing errors**, all in files this plan didn't touch (`QueueEntryController.php`, `SavePricingAndPaymentRequest.php`, `UpdateSystemConfigurationRequest.php`) — the same route-model-binding `object|string` inference pattern already documented as out-of-scope noise in Plan 05-01's SUMMARY. PHPStan is not part of this plan's `<verification>` gate (only the feature test filter and `npm run types:check` are), so this did not block completion; left alone per the SCOPE BOUNDARY rule.

## Next Phase Readiness

- `CancellationController` and the `cancellation_fee_amount` config are in place; any future plan needing to know a job order is inactive can rely on `cancelled_at` being non-null.
- Plan 05-07 (release) can extend `DashboardController::index`'s query with its own additional guard, following the same pattern this plan and Plan 05-01 established.
- No blockers.

---
*Phase: 05-pos-payments*
*Completed: 2026-09-05*

## Self-Check: PASSED

All 8 files claimed as created/modified (CancellationController.php, CancelJobOrderRequest.php, CancellationFeeTest.php, SystemConfigurationSeeder.php, routes/portals.php, DashboardController.php, cashier/Dashboard.vue, SystemConfigurationTest.php) plus this SUMMARY.md were verified present via `ls -la`. Both commit hashes (`56b66e6`, `6e94388`) were verified present in `git log`. Plan verification gates re-run: `php artisan test --compact --filter=CancellationFee` (8/8 passed), `php artisan route:list --name=cashier.job-orders.cancel` (route registered), `npm run types:check` (clean), full suite `php artisan test --compact` (234 tests, 231 passed, 3 pre-existing skips, 0 failures).
