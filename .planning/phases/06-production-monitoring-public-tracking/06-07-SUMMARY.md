---
phase: 06-production-monitoring-public-tracking
plan: 07
subsystem: job-order-production
tags: [eloquent, inertia, vue, tdd, cashier, artist, frontline-staff]

# Dependency graph
requires:
  - phase: 06-04
    provides: "EnterProduction action wired into DesignEditorController::approve()/Public DesignReviewController::approve() — DesignApproved is now transient, immediately followed by ForProduction in the same transaction"
  - phase: 06-01
    provides: "JobOrderStatus::ForProduction/Printing/QualityCheck/ReadyForPickup enum cases"
  - phase: 06-02
    provides: "Job order number display precedent on Cashier/Frontline Vue pages this plan's status-badge edits sit alongside"
provides:
  - "CancellationController's $designStarted fee-eligibility array recognizes all four production statuses, so cancelling a job order past DesignApproved still collects the Phase 5 cancellation fee (POS-07)"
  - "PerformanceReportController's completed-jobs query recognizes all four production statuses (JOB-10), so an artist's jobsCompleted/avgRevisions/slaAdherence never permanently zero out after 06-04 ships"
  - "JobOrderQueueController's artist-dashboard query excludes all four production statuses, so a job order that has advanced into production no longer piles up in the artist's own queue"
  - "cashier/Dashboard.vue, artist/Dashboard.vue, frontline-staff/QueueList.vue, frontline-staff/NewVisit.vue all render a real badge/label for a job order at any of the four production statuses instead of a blank or stale one"
affects: [06-08]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Shared POST_APPROVAL_STATUSES array constant in a Vue page component (artist/Dashboard.vue), used by multiple status-derivation functions plus a template v-else-if, replacing several independent single-value string comparisons that would otherwise need updating in lockstep"
    - "v-else Badge fallback appended to an existing v-if/v-else-if status chain (cashier/Dashboard.vue, frontline-staff/QueueList.vue, frontline-staff/NewVisit.vue) as the mechanical fix for 'no branch renders for this status' — a general pattern for any status enum that keeps growing"

key-files:
  created: []
  modified:
    - app/Http/Controllers/Cashier/CancellationController.php
    - resources/js/pages/cashier/Dashboard.vue
    - tests/Feature/Cashier/CancellationFeeTest.php
    - app/Http/Controllers/Artist/PerformanceReportController.php
    - tests/Feature/Artist/PerformanceReportTest.php
    - app/Http/Controllers/Artist/JobOrderQueueController.php
    - resources/js/pages/artist/Dashboard.vue
    - resources/js/pages/frontline-staff/QueueList.vue
    - resources/js/pages/frontline-staff/NewVisit.vue
    - tests/Feature/Artist/QueueControlsTest.php

key-decisions:
  - "PerformanceReportController's whereIn() keeps DesignApproved in the list alongside the four production statuses (not replaced) — backward compatibility with rows that predate 06-04's automatic-advance wiring, and with this suite's own pre-existing factory-shortcut tests that create job orders directly at design_approved"
  - "Every new test in this plan drives its job order through the real artist.job-orders.design.approve route (never a factory-injected 'status' => 'design_approved' shortcut) per the plan's explicit checker-mandated requirement — that shortcut is exactly what let the original regression pass unnoticed in 06-04"

requirements-completed: [PROD-01, PROD-02]

# Metrics
duration: ~45min (includes one-time worktree environment bootstrap: composer install, npm ci, npm run build)
completed: 2026-09-07
---

# Phase 6 Plan 7: Downstream DesignApproved/ReadyForProduction Consumer Fixes Summary

**Closed the regression 06-04's automatic EnterProduction wiring introduced across four downstream consumers — Cashier's cancellation fee, Artist's Performance Report, Artist's own queue exclusion, and four staff-facing status badges — all re-audited exhaustively and fixed together since they share one root cause: DesignApproved is now a transient, never-observed status on the real approve() path.**

## Performance

- **Duration:** ~45 min (includes one-time worktree bootstrap: `composer install`, `npm ci`, `npm run build` — this worktree had none of vendor/, node_modules/, .env, or built assets)
- **Tasks:** 3 completed (Tasks 1 and 2 via TDD: test → feat; Task 3 as a single auto commit with tests included)
- **Files modified:** 10 (3 controllers, 4 Vue pages, 3 test files)

## Accomplishments

- `CancellationController`'s `$designStarted` fee-eligibility array now recognizes `ForProduction`/`Printing`/`QualityCheck`/`ReadyForPickup` alongside the existing three — a job order cancelled anywhere from `DesignApproved` through `ReadyForPickup` still collects the Phase 5 cancellation fee (POS-07), proven by driving a job order through the real `approve()` route into `ForProduction` before cancelling
- `PerformanceReportController`'s completed-jobs query re-keyed from a single `where('status', DesignApproved)` to a `whereIn()` covering `DesignApproved` plus all four production statuses (JOB-10) — `jobsCompleted`/`avgRevisions`/`slaAdherence` never permanently zero out once 06-04 ships, proven with all three metrics asserted explicitly (not just `jobsCompleted`) against the real approve flow
- `JobOrderQueueController`'s artist-dashboard `whereNotIn()` exclusion extended to also exclude all four production statuses — a job order that has advanced into production no longer piles up forever in the artist's own queue with nothing they can do about it
- Four Vue pages (`cashier/Dashboard.vue`, `artist/Dashboard.vue`, `frontline-staff/QueueList.vue`, `frontline-staff/NewVisit.vue`) all extended so a job order at any of the four new production statuses renders a real badge/label — never a blank cell or a stale "Assigned" fallback — and the Artist Dashboard's Actions cell renders "View" instead of a stale claim/forward/not-appear control for any post-approval status
- Every new test in this plan drives its job order through the real `artist.job-orders.design.approve` route (never a factory-injected `'status' => 'design_approved'` shortcut), per the plan's explicit checker-mandated requirement — that exact shortcut is what let 06-04's original regression pass unnoticed

## Task Commits

Each task was committed atomically:

1. **Task 1: Cashier cancellation-fee and Dashboard status compatibility (POS-07)** - `b28641c` (test, RED) → `95cf2df` (feat, GREEN)
2. **Task 2: Artist Performance Report survives automatic production advancement (JOB-10)** - `7e1a1a4` (test, RED) → `df4b8eb` (feat, GREEN)
3. **Task 3: Artist queue exclusion and staff-facing status displays** - `e4902f2` (feat, includes new tests — not a TDD-gated task per plan frontmatter)

## Files Created/Modified

- `app/Http/Controllers/Cashier/CancellationController.php` - `$designStarted` array extended to 7 values (added `ForProduction`, `Printing`, `QualityCheck`, `ReadyForPickup`)
- `resources/js/pages/cashier/Dashboard.vue` - `DESIGN_STARTED_STATUSES` mirrored to match; 4 new `jobOrderStatusLabel()` cases; `v-else` `<Badge variant="secondary">` fallback added to the Status-column chain
- `tests/Feature/Cashier/CancellationFeeTest.php` - new test driving a job order through the real `approve()` route into `ForProduction`, then asserting the cancellation fee is still collected
- `app/Http/Controllers/Artist/PerformanceReportController.php` - completed-jobs query re-keyed from `where('status', DesignApproved)` to `whereIn('status', [DesignApproved, ForProduction, Printing, QualityCheck, ReadyForPickup])`
- `tests/Feature/Artist/PerformanceReportTest.php` - new test asserting `jobsCompleted`/`avgRevisions`/`slaAdherence` all still count a job order approved through the real flow
- `app/Http/Controllers/Artist/JobOrderQueueController.php` - `whereNotIn('status', [...])` extended to 7 values (added the four production statuses)
- `resources/js/pages/artist/Dashboard.vue` - new `POST_APPROVAL_STATUSES` constant; `statusBadgeVariant()`/`statusBadgeClass()` now check membership instead of a single `design_approved` comparison; `statusLabel()` gained 4 new cases; Actions cell's final `v-else-if` now checks `POST_APPROVAL_STATUSES.includes(...)`
- `resources/js/pages/frontline-staff/QueueList.vue` - `v-else` `<Badge variant="secondary">In Production</Badge>` appended to the per-job-order status chain
- `resources/js/pages/frontline-staff/NewVisit.vue` - identical `v-else` `<Badge variant="secondary">In Production</Badge>` fallback appended to the post-submit confirmation panel's status chain
- `tests/Feature/Artist/QueueControlsTest.php` - new dataset test (4 production statuses) plus one end-to-end test proving the artist dashboard exclusion holds against the real `approve()` flow

## Decisions Made

- Kept `DesignApproved` in `PerformanceReportController`'s `whereIn()` list rather than replacing it, for backward compatibility with pre-06-04 rows and this suite's own pre-existing factory-shortcut tests (see key-decisions in frontmatter).
- Every new test in this plan uses the real `approve()` HTTP route rather than a factory-injected status shortcut, per the plan's explicit instruction — this is the same shortcut that let 06-04's original regression pass unnoticed, so the checker mandated real-flow coverage everywhere this plan touches.

## Deviations from Plan

None — plan executed exactly as written. All three tasks' acceptance criteria (grep counts, test files, pint/types:check) were met without needing any Rule 1-4 fixes.

## Issues Encountered

- **`composer types:check` (Larastan) does not exit 0 project-wide** — 5 pre-existing errors remain, unchanged from 06-01/06-02/06-04's documented state (`.planning/phases/06-production-monitoring-public-tracking/deferred-items.md`): `property.notFound`/`method.notFound` errors in three Phase 5 Cashier/Owner Form Requests (`CreateCreditRequestRequest.php`, `SavePricingAndPaymentRequest.php` x3, `UpdateSystemConfigurationRequest.php`). None of this plan's three modified controllers (`CancellationController.php`, `PerformanceReportController.php`, `JobOrderQueueController.php`) appear in the error list — verified via scoped `phpstan analyse` runs against each, all reporting 0 errors. Not fixed, per scope-boundary rule and this phase's own established precedent of deferring the same items.
- Fresh worktree required full environment bootstrap (`composer install`, `.env`, `database.sqlite`, `php artisan migrate`, `npm ci`, `npm run build`) before any test/build command would run — same one-time cost documented by every prior 06-* plan's summary in this worktree, not repeated project state.
- Full project-wide test suite: 327 tests, 324 passed, 3 skipped, 0 failures (baseline before this plan: 320 tests, 317 passed, 3 skipped — the +7 delta is exactly this plan's own new tests: 1 in `CancellationFeeTest.php`, 1 in `PerformanceReportTest.php`, 4 dataset instances + 1 end-to-end in `QueueControlsTest.php`).

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Every downstream consumer of `DesignApproved`/`ReadyForProduction` that this phase's own audit could find (Cashier cancellation fee, Cashier Dashboard status, Artist Performance Report, Artist queue exclusion, Artist Dashboard status/actions, Frontline Queue/New-Visit status badges) is now correct against the real `approve()` → `EnterProduction` flow, not just factory-shortcut test setups.
- No known remaining consumer of the pre-06-04 status shape was left unfixed within this plan's scope. If 06-08 (or any later plan) introduces a new surface that branches on job order status, it should include all four production statuses from the start rather than relying on a `DesignApproved`-only check, per the pattern this plan repeatedly had to retrofit.

---

_Phase: 06-production-monitoring-public-tracking_
_Completed: 2026-09-07_

## Self-Check: PASSED

All 10 claimed modified files verified present on disk; all 5 claimed commit hashes (`b28641c`, `95cf2df`, `7e1a1a4`, `df4b8eb`, `e4902f2`) verified present in `git log --oneline --all`.
