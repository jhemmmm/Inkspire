---
phase: 05-pos-payments
plan: 07
subsystem: payments
tags: [laravel, inertia, vue3, pest, release-gate, rbac]

# Dependency graph
requires:
    - phase: 05-pos-payments (05-01)
      provides: job_orders.payment_status column, PaymentStatus enum
    - phase: 05-pos-payments (05-06)
      provides: PaymentStatus::OnCredit as a release-eligible active-credit state
provides:
    - job_orders.released_at nullable timestamp column
    - JobOrderReleaseController — the single server-enforced gate deciding whether a job order can be handed to the customer
    - Release to Customer action on Frontline Staff's Queue List
affects: []

# Tech tracking
tech-stack:
    added: []
    patterns:
        - "Release-gate controller re-validates payment_status server-side on every request via abort_if/abort_unless, matching RBAC-02's established 'not just hidden navigation' precedent — the UI predicate that decides whether to render a button is a pure convenience mirror of the same rule the controller enforces independently"

key-files:
    created:
        - database/migrations/2026_09_04_110000_add_released_at_to_job_orders_table.php
        - app/Http/Controllers/FrontlineStaff/JobOrderReleaseController.php
        - app/Http/Requests/FrontlineStaff/ReleaseJobOrderRequest.php
        - tests/Feature/FrontlineStaff/ReleaseGateTest.php
    modified:
        - app/Models/JobOrder.php
        - app/Http/Controllers/FrontlineStaff/QueueEntryController.php
        - resources/js/pages/frontline-staff/QueueList.vue
        - routes/portals.php

key-decisions:
    - "Renamed the generated migration's auto-timestamp filename to the plan's specified 2026_09_04_110000 prefix, keeping it sequenced correctly among the phase's other payment-related migrations"
    - "Blocked-message branching implemented as a single abort_unless with a match() expression selecting the CreditPendingApproval-specific copy vs. the generic 'not fully paid' copy, both routed through bootstrap/app.php's existing 422-to-flashed-toast exception bridge — no bespoke response mechanism introduced"

patterns-established:
    - 'Pattern: a release/hand-over gate is implemented as its own single-purpose controller (not folded into an existing controller), re-checking the authoritative model state independently of any UI-side eligibility check'

requirements-completed: [POS-09]

# Metrics
duration: ~25min
completed: 2026-09-05
---

# Phase 5 Plan 7: Server-enforced release gate Summary

**`JobOrderReleaseController` blocks hand-over of any job order that isn't Paid or actively OnCredit, enforced independently of the UI, closing out all nine POS requirements for Phase 5.**

## Performance

- **Duration:** ~25 min (includes worktree environment setup — see Issues Encountered)
- **Started:** 2026-09-05T00:32:32Z (base commit)
- **Completed:** 2026-09-05T00:43:55Z
- **Tasks:** 2/2 completed
- **Files modified:** 8 (4 created, 4 modified)

## Accomplishments

- `released_at` nullable timestamp column added to `job_orders`, giving the release action real one-time-only tracking instead of a repeatable no-op
- `JobOrderReleaseController::store` is the sole place POS-09's gate is decided — releases only `Paid`/`OnCredit` job orders, rejects every other `payment_status` with a 422 (routed through the app's existing abort-to-flashed-toast bridge), and rejects a second release attempt on an already-released job order
- Frontline Staff's Queue List gained a "Release to Customer" button per job-order row, contextual on `payment_status` and `released_at`, submitting via a plain `Form` with no confirmation dialog (per UI-SPEC — routine, not destructive)
- A direct/crafted POST to the release route is rejected identically to the UI-gated path, verified by a dedicated test

## Task Commits

Each task was committed atomically:

1. **Task 1: Backend — server-enforced release gate** - `d5c8074` (feat)
2. **Task 2: Frontend — Release to Customer action on Queue List** - `328e20e` (feat)

_No plan-metadata commit yet — SUMMARY.md commit follows this file._

## Files Created/Modified

**Task 1 (backend):**

- `database/migrations/2026_09_04_110000_add_released_at_to_job_orders_table.php` - nullable `released_at` timestamp
- `app/Models/JobOrder.php` - `released_at` cast + `@property` annotation
- `app/Http/Requests/FrontlineStaff/ReleaseJobOrderRequest.php` - `authorize()` returns `true` (role-gated by route middleware), empty `rules()`
- `app/Http/Controllers/FrontlineStaff/JobOrderReleaseController.php` - the release gate itself
- `routes/portals.php` - `frontline-staff.job-orders.release` route
- `tests/Feature/FrontlineStaff/ReleaseGateTest.php` - 9 cases: Paid succeeds, OnCredit succeeds, every blocked status (parameterized) returns 422 with `released_at` left null, a direct-POST bypass attempt, double-release rejection

**Task 2 (frontend):**

- `app/Http/Controllers/FrontlineStaff/QueueEntryController.php` - `jobOrders` eager-load now selects `payment_status`/`released_at`
- `resources/js/pages/frontline-staff/QueueList.vue` - "Release to Customer" `Button`/`Form`, `isReleaseEligible()` render predicate

## Decisions Made

- Renamed the artisan-generated migration filename from its auto-timestamp to the plan's specified `2026_09_04_110000` prefix, keeping the phase's migration ordering intentional and matching the plan's literal filename.
- Implemented the two blocked-message variants (generic vs. credit-pending) as a single `abort_unless(..., match($jobOrder->payment_status) { ... })` call, following the plan's explicit instruction to prefer the existing `abort_if`/global-handler pattern over inventing a bespoke `->with()` response shape.

## Deviations from Plan

None — plan executed exactly as written. `php artisan wayfinder:generate --with-form --no-interaction` was run to generate `JobOrderReleaseController`'s TypeScript action (a routine, expected step consistent with every prior phase's frontend task, not a deviation — the generated `resources/js/actions/**` and `resources/js/routes/**` output is gitignored per this repo's `.gitignore`, so no generated file appears in either task's commit).

## Issues Encountered

- **Worktree had no `vendor/`, `node_modules/`, `.env`, or database file** (same as every prior Phase 5 worktree-executed plan). Copied `vendor/` fully from the main checkout (symlinking previously caused a fatal autoloader-realpath collision, per 05-01's documented finding), symlinked `node_modules/`, created `.env` from `.env.example`, generated an app key, created a fresh worktree-local SQLite DB, ran `migrate:fresh --seed`, and `npm run build` once for the Vite manifest before any tests could pass.
- **`composer types:check` (Larastan)** surfaced the same pre-existing, out-of-scope findings already documented in 05-01/05-06 (`QueueEntryController.php` match-arm warning — a different match expression than the one this plan touched, `SavePricingAndPaymentRequest.php`/`UpdateSystemConfigurationRequest.php` route-model-binding `object|string` inference). None in this plan's own new files. Left untouched per the SCOPE BOUNDARY rule; not part of this plan's `<verification>` gate (only Pint, tests, and `npm run types:check` are).

## Next Phase Readiness

- All nine POS-01 through POS-09 requirements are now complete — Phase 5 (POS & Payments) is functionally done pending final phase-level review.
- Phase 6 (Production Monitoring & Public Tracking) can proceed independently — per D-15, this plan deliberately gates only the release/hand-over action, never production-stage advancement, so Phase 6's `status` lifecycle transitions are unaffected by `payment_status`/`released_at`.
- No blockers.

---

_Phase: 05-pos-payments_
_Completed: 2026-09-05_

## Self-Check: PASSED

All 5 files claimed as created/modified in this summary were verified present via `git ls-files`. Both task commit hashes (`d5c8074`, `328e20e`) and the docs commit (`c679411`) were verified present in `git log --oneline --all`.
