---
phase: 03-job-order-intake-auto-assignment
plan: 01
subsystem: api
tags: [laravel, pest, eloquent, job-orders, rbac, audit-trail, file-validation, round-robin]

# Dependency graph
requires:
  - phase: 02-customer-queue-management
    provides: QueueEntryController::addJobOrder()/store() creating unvalidated Intake job orders with a stored-but-unchecked file_path
  - phase: 01-foundation-rbac-auth-hardening-audit-trail
    provides: SystemConfiguration threshold storage, AuditObserver auto-wiring via #[ObservedBy], role:frontline_staff middleware
provides:
  - "ValidateJobOrderFile action: DPI/format/size validation for Type A job orders, thresholds from SystemConfiguration, no new Composer dependency"
  - "AssignArtistToJobOrder action: locked oldest-or-null round-robin artist selection for Type B job orders, plus claimOldestUnassigned() for Phase 4"
  - "JobOrderStatus gains ValidationFailed/ReadyForProduction/Assigned resting states"
  - "job_orders.assigned_artist_id / validation_failure_reason and users.is_available / last_assigned_at columns"
  - "JobOrderController::replaceFile() endpoint for re-validating a Type A file in one save"
  - "QueueEntryController::index()/CustomerController::index() eager-loading every field Plan 03-02's frontend needs"
affects: [03-02-job-order-intake-ui, 04-artist-workflow-design-editor]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Invokable single-purpose Action classes under App\\Actions\\{Domain} (extends the existing App\\Actions\\Fortify convention into App\\Actions\\JobOrder)"
    - "DB::transaction()+lockForUpdate() for atomic fair-selection under concurrency (mirrors QueueEntry::nextForBusinessDay())"
    - "forceFill()+saveQuietly() for routine bookkeeping columns kept outside #[Fillable] and outside the audit trail (mirrors last_activity_at)"
    - "forceFill()+afterCreating() factory states for columns outside #[Fillable] (mirrors UserFactory::locked()/deactivated())"

key-files:
  created:
    - app/Actions/JobOrder/ValidateJobOrderFile.php
    - app/Actions/JobOrder/AssignArtistToJobOrder.php
    - app/Http/Controllers/FrontlineStaff/JobOrderController.php
    - app/Http/Requests/FrontlineStaff/ReplaceJobOrderFileRequest.php
    - tests/Feature/JobOrder/FactoryStatesTest.php
    - tests/Feature/JobOrder/ValidateJobOrderFileTest.php
    - tests/Feature/JobOrder/AssignArtistToJobOrderTest.php
    - tests/Feature/FrontlineStaff/JobOrderProcessingTest.php
  modified:
    - app/Enums/JobOrderStatus.php
    - app/Models/JobOrder.php
    - app/Models/User.php
    - database/factories/JobOrderFactory.php
    - database/factories/UserFactory.php
    - app/Http/Controllers/FrontlineStaff/QueueEntryController.php
    - app/Http/Controllers/FrontlineStaff/CustomerController.php
    - routes/portals.php

key-decisions:
  - "DPI validation is raster-only (jpg/png via getimagesize()/exif_read_data()); pdf/ai/eps skip DPI and validate format+size only (D-01)"
  - "assigned_artist_id/validation_failure_reason/is_available/last_assigned_at all sit outside their models' #[Fillable] lists, written only via forceFill() from action/controller code"
  - "last_assigned_at bookkeeping uses saveQuietly() (silenced from audit trail); the job order's own status/assigned_artist_id write uses a normal save() (fully audited)"
  - "Zero available artists leaves the Type B job order at its already-created Intake status with a null assigned_artist_id — no fallback (D-07)"

patterns-established:
  - "applyIntakeOutcome()/jobOrderOutcomeToastMessage() private controller methods branch on JobOrderType to dispatch to the correct action and render the exact Copywriting Contract toast"

requirements-completed: [JOB-01, JOB-02]

# Metrics
duration: 25min
completed: 2026-09-02
---

# Phase 3 Plan 1: Job Order Intake & Auto-Assignment (Backend) Summary

**Type A job orders auto-validate DPI/format/size via PHP's built-in getimagesize()/exif_read_data() (no new dependency), Type B job orders auto-assign to the available Artist with the oldest/null last_assigned_at under a database lock — both synchronous, in the same request that creates the job order.**

## Performance

- **Duration:** ~25 min (including worktree environment setup: composer install, npm install, frontend build, sqlite init)
- **Started:** 2026-09-02T06:36:00+08:00
- **Completed:** 2026-09-02T06:52:39+08:00
- **Tasks:** 3
- **Files modified:** 18

## Accomplishments
- `ValidateJobOrderFile` action correctly separates format/size/DPI failure reasons using only PHP's built-in image-metadata functions, reading every threshold from `SystemConfiguration` (never hardcoded)
- `AssignArtistToJobOrder` action implements the locked "oldest-or-null" round-robin selection (D-06) and the zero-artist no-op case (D-07), plus a fully-tested `claimOldestUnassigned()` entry point ready for Phase 4
- `QueueEntryController::addJobOrder()`/`store()` now call both actions from the exact same intake flow and render outcome-specific toast copy per the UI-SPEC's Copywriting Contract
- New `JobOrderController::replaceFile()` endpoint re-validates a Type A file in one `forceFill()->save()` call, 422s on Type B
- Every status/assignment mutation is directly proven audit-logged by a Pest assertion, not assumed from `#[ObservedBy]` alone

## Task Commits

Each task was committed atomically:

1. **Task 1: Domain layer — enums, migrations, models, factories** - `a6662a3` (feat)
2. **Task 2: Action classes — file validation and artist assignment** - `83e8732` (feat)
3. **Task 3: Controller wiring, replace-file endpoint, routes & integration tests** - `57ac8fb` (feat)

_No plan-metadata commit — worktree mode: SUMMARY.md is committed separately below; STATE.md/ROADMAP.md are updated by the orchestrator after merge._

## Files Created/Modified

- `database/migrations/2026_09_01_224409_add_validation_and_assignment_columns_to_job_orders_table.php` - `job_orders.assigned_artist_id` (nullable FK, nullOnDelete) + `validation_failure_reason` (nullable text)
- `database/migrations/2026_09_01_224410_add_availability_columns_to_users_table.php` - `users.is_available` (boolean, default true) + `last_assigned_at` (nullable timestamp)
- `app/Enums/JobOrderStatus.php` - Added `ValidationFailed`/`ReadyForProduction`/`Assigned` cases
- `app/Models/JobOrder.php` - `assignedArtist(): BelongsTo`, new PHPDoc properties, columns kept outside `#[Fillable]`
- `app/Models/User.php` - `is_available`/`last_assigned_at` casts, new PHPDoc properties, columns kept outside `#[Fillable]`
- `database/factories/JobOrderFactory.php` - `validationFailed()`/`readyForProduction()`/`assigned()` states via `afterCreating()`+`forceFill()`
- `database/factories/UserFactory.php` - `unavailable()` state via `afterCreating()`+`forceFill()`
- `app/Actions/JobOrder/ValidateJobOrderFile.php` - `__invoke(UploadedFile): array{passed, reason}` + public static `resolveDpi()`
- `app/Actions/JobOrder/AssignArtistToJobOrder.php` - `__invoke(JobOrder): ?User` + `claimOldestUnassigned(User): ?JobOrder`
- `app/Http/Controllers/FrontlineStaff/QueueEntryController.php` - Constructor-injects both actions; `applyIntakeOutcome()`/`jobOrderOutcomeToastMessage()`; `index()` eager-loads job order outcome fields
- `app/Http/Controllers/FrontlineStaff/JobOrderController.php` (new) - `replaceFile()` endpoint
- `app/Http/Controllers/FrontlineStaff/CustomerController.php` - `index()` eager-loads job order outcome fields for the confirmation card
- `app/Http/Requests/FrontlineStaff/ReplaceJobOrderFileRequest.php` (new) - `file` required/file only, no mimes/max cap (D-02)
- `routes/portals.php` - `job-orders/{jobOrder}/replace-file` under `role:frontline_staff`
- `tests/Feature/JobOrder/FactoryStatesTest.php`, `ValidateJobOrderFileTest.php`, `AssignArtistToJobOrderTest.php` (new) - 16 tests covering every domain/action outcome branch
- `tests/Feature/FrontlineStaff/JobOrderProcessingTest.php` (new) - 10 integration tests covering the full controller-wired flow, audit assertion, and multi-row regression

## Decisions Made

- Followed the plan's explicit instruction to build `readyForProduction()` via `afterCreating()`+`forceFill()` (matching the other two new `JobOrderFactory` states) even though `status` alone is technically inside `#[Fillable]` — keeps all three post-processing states structurally consistent.
- `ValidateJobOrderFile::__invoke()` calls `getimagesize()` (error-suppressed) even though its return value isn't consumed further, per the plan's explicit T-03-03 DoS-mitigation instruction — the call itself, not its result, is the point (never let a malformed image throw).

## Deviations from Plan

None - plan executed exactly as written. All acceptance-criteria greps, verification commands, and threat-model mitigations (T-03-01 through T-03-06) pass as specified.

## Issues Encountered

- **Worktree had no `vendor/`, `node_modules/`, `.env`, or sqlite database** — this is a fresh git worktree, not a copy of the main repo's local dev state. Resolved by running `composer install`, `cp .env.example .env`, `php artisan key:generate`, `touch database/database.sqlite`, `npm install`, and `npm run build` before any test could pass. Without the frontend build, all 41 pre-existing Inertia-rendering tests failed with `ViteManifestNotFoundException` — confirmed this was purely an environment-setup gap (not an app bug) by rerunning the full baseline suite after `npm run build`: 102 passed, 3 skipped, 0 failures.
- **Larastan (`composer types:check`) fails to bootstrap in this sandboxed worktree when run with its default parallel workers** (`Undefined constant "Larastan\Larastan\LARAVEL_VERSION"`), but succeeds identically to the main repo when run with `--debug` (single-process). Used `vendor/bin/phpstan analyse --no-progress --debug` for static-analysis verification instead; it surfaced only the same single pre-existing, out-of-scope error already present in the main repo baseline (`app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php:20`), confirming zero new static-analysis issues from this plan's changes.
- `npm install` incidentally renamed `package-lock.json`'s `name` field to the worktree's directory name (`agent-af3be9c19da7afe7f`); reverted with `git checkout -- package-lock.json` before any task commit — not part of this plan's diff.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 03-02 (same wave-chain) can now build the frontend UI on top of this plan's endpoints and eager-loaded props: `QueueEntryController::index()` and `CustomerController::index()` both expose `jobOrders.status`, `jobOrders.validation_failure_reason`, `jobOrders.assigned_artist_id`, and `jobOrders.assignedArtist.name` with zero additional requests.
- `JobOrderController::replaceFile()` route (`frontline-staff.job-orders.replace-file`) and its Wayfinder-generated `.form()` helper are ready for 03-02's "Replace File" dialog.
- `AssignArtistToJobOrder::claimOldestUnassigned()` has no caller yet — Phase 4's On Break/End Shift toggle is the intended caller; fully tested and ready to be invoked.
- No blockers.

---
*Phase: 03-job-order-intake-auto-assignment*
*Completed: 2026-09-02*
