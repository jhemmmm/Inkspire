---
phase: 04-artist-workflow-design-editor
plan: 01
subsystem: api
tags: [laravel, inertia, job-orders, artist-portal, enums, form-requests]

# Dependency graph
requires:
  - phase: 03-job-order-intake-auto-assignment
    provides: JobOrderStatus enum (Intake/ValidationFailed/ReadyForProduction/Assigned), JobOrder model, JobOrderFactory, assigned_artist_id column, AuditObserver coverage
provides:
  - InConsultation JobOrderStatus case
  - job_orders.consultation_notes / queue_deprioritized_at / not_appeared columns
  - JobOrderFactory::assignedTo(User $artist) test-support state
  - JobOrderWorkspaceController (show, updateConsultation) with ownership + status guards
  - JobOrderQueueController (index, next, forward, notAppear) — artist's own queue + Next/Forward/Not-Appear transitions
  - artist.job-orders.show / .consultation.update / .next / .forward / .not-appear routes
  - Placeholder artist/JobOrderWorkspace.vue (Vite-manifest-satisfying stub; Plan 04-02 replaces it)
affects: ["04-02 (Vue Dashboard + JobOrderWorkspace page)", "04-03 (design editor, extends JobOrderStatus with InDesign)", "04-05 (review cycle, extends JobOrderStatus with PendingReview/DesignApproved)"]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Body-less FormRequest shared across multiple route-implied transitions (UpdateJobOrderQueuePositionRequest reused by next/forward/notAppear)"
    - "Explicit allow-list status guard (InConsultation/in_design) instead of a deny-list on Assigned, so future enum cases are rejected by default"
    - "Server-side re-derivation of the 'oldest eligible' id (oldestEligibleId()) rather than trusting a client-tampered route parameter"

key-files:
  created:
    - database/migrations/2026_09_02_082141_add_consultation_and_queue_columns_to_job_orders_table.php
    - app/Http/Controllers/Artist/JobOrderWorkspaceController.php
    - app/Http/Controllers/Artist/JobOrderQueueController.php
    - app/Http/Requests/Artist/UpdateConsultationNotesRequest.php
    - app/Http/Requests/Artist/UpdateJobOrderQueuePositionRequest.php
    - resources/js/pages/artist/JobOrderWorkspace.vue
    - tests/Feature/Artist/ConsultationNotesTest.php
    - tests/Feature/Artist/QueueControlsTest.php
  modified:
    - app/Enums/JobOrderStatus.php
    - app/Models/JobOrder.php
    - database/factories/JobOrderFactory.php
    - routes/portals.php

key-decisions:
  - "assignedTo() factory state only defaults status to Assigned when the base definition() status (Intake) hasn't already been overridden by the caller's create() array — preserves an explicit status override like 'in_consultation' instead of clobbering it in afterCreating()"
  - "Added a minimal artist/JobOrderWorkspace.vue placeholder in this backend-only plan, since Inertia's root Blade template (@vite(\"resources/js/pages/{$page['component']}.vue\")) hard-resolves the page component through the Vite manifest even for a plain GET/Inertia render in feature tests — without it, ConsultationNotesTest's 'view workspace' assertion 500s regardless of backend correctness"

patterns-established:
  - "Pattern: forward()/notAppear() use an explicit InConsultation/in_design allow-list rather than a not-Assigned deny-list, so any status the enum gains later (PendingReview, DesignApproved) is rejected by construction with no further controller edit required"

requirements-completed: [JOB-03, JOB-09]

# Metrics
duration: 30min
completed: 2026-09-02
---

# Phase 4 Plan 01: Artist Consultation & Queue Controls Backend Summary

**InConsultation job order status, consultation-notes save endpoint, and Next/Forward/Not-Appear queue transitions with server-re-derived ordering and an explicit status allow-list**

## Performance

- **Duration:** ~30 min
- **Started:** 2026-09-02T16:06:51+08:00 (branched from c0f3463)
- **Completed:** 2026-09-02T16:32:10+08:00
- **Tasks:** 3 completed
- **Files modified:** 10 (4 modified, 6 created; plus 1 deferred-items.md log)

## Accomplishments
- Added `InConsultation` to `JobOrderStatus` and three additive columns (`consultation_notes`, `queue_deprioritized_at`, `not_appeared`) to `job_orders` via a clean, reversible migration.
- `JobOrderWorkspaceController` renders the per-job-order workspace and saves consultation notes, both gated by an ownership `abort_unless`, with the save additionally gated by an `in_consultation` status guard.
- `JobOrderQueueController` renders the artist's own dashboard queue (excluding the three Type-A-only statuses) and implements Next (server-re-derived oldest-first claim, with an explicit not_appeared manual-resume bypass), Forward, and Not-Appear — the latter two using an explicit `InConsultation`/`in_design` allow-list per the plan's checker-fix requirement, never a deny-list on `Assigned`.
- `artist.dashboard` now renders real data via `JobOrderQueueController@index` while keeping its exact route name, so `UserRole::portalRoute()` and `RoleBoundaryTest` required no changes.
- Full Pest coverage: 4 consultation-notes tests + 10 queue-control tests, all passing, plus a `RoleBoundaryTest` regression check.

## Task Commits

Each task was committed atomically:

1. **Task 1: Domain layer — migration, enum, model, factory state** - `01bffde` (feat)
2. **Task 2: Job Order Workspace — page render & consultation notes save** - `759c6bc` (feat)
3. **Task 3: Artist dashboard queue & Next/Forward/Not-Appear** - `1c1a328` (feat)

**Plan metadata:** committed alongside this SUMMARY (see below)

## Files Created/Modified
- `database/migrations/2026_09_02_082141_add_consultation_and_queue_columns_to_job_orders_table.php` - Adds `consultation_notes` (nullable text), `queue_deprioritized_at` (nullable timestamp), `not_appeared` (boolean, default false) to `job_orders`
- `app/Enums/JobOrderStatus.php` - Adds `InConsultation = 'in_consultation'` case
- `app/Models/JobOrder.php` - `consultation_notes` added to `#[Fillable]`; `queue_deprioritized_at`/`not_appeared` cast but left outside `#[Fillable]` (system-computed, written via `forceFill()`)
- `database/factories/JobOrderFactory.php` - New `assignedTo(User $artist)` state; status-preserving fix (see Deviations)
- `app/Http/Controllers/Artist/JobOrderWorkspaceController.php` - `show()`/`updateConsultation()` with ownership + status guards
- `app/Http/Requests/Artist/UpdateConsultationNotesRequest.php` - `consultation_notes` nullable/string/max:5000
- `app/Http/Controllers/Artist/JobOrderQueueController.php` - `index()`/`next()`/`forward()`/`notAppear()` + private `oldestEligibleId()` helper
- `app/Http/Requests/Artist/UpdateJobOrderQueuePositionRequest.php` - Body-less, shared across next/forward/notAppear
- `routes/portals.php` - `artist.dashboard` swapped from `Route::inertia` to `JobOrderQueueController@index`; added `job-orders.show`, `.consultation.update`, `.next`, `.forward`, `.not-appear`
- `resources/js/pages/artist/JobOrderWorkspace.vue` - Minimal placeholder page (see Deviations)
- `tests/Feature/Artist/ConsultationNotesTest.php` - 4 tests covering workspace view + consultation-notes save/guard
- `tests/Feature/Artist/QueueControlsTest.php` - 10 tests covering dashboard render + Next/Forward/Not-Appear + ownership/status guards

## Decisions Made
- `assignedTo()`'s status default only applies when the caller didn't already override `status` via `create([...])` — see Deviations for why this was necessary.
- Added a minimal Vue placeholder for `artist/JobOrderWorkspace` in this backend-only plan (rationale in Deviations) rather than skip the workspace-view assertion from `ConsultationNotesTest`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `JobOrderFactory::assignedTo()` clobbered a caller-supplied status override**
- **Found during:** Task 2 (`ConsultationNotesTest`)
- **Issue:** The plan's literal instruction for `assignedTo()` was an unconditional `forceFill(['status' => Assigned, ...])` inside `afterCreating()`. Since `afterCreating()` runs after the row is already inserted with `create()`'s state overrides applied, a call like `JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_consultation'])` (used by the plan's own Task 2/3 test descriptions) had its `'in_consultation'` override silently reverted back to `'assigned'`.
- **Fix:** `assignedTo()` now only defaults status to `Assigned` when the job order's current status is still the factory's base `Intake` default; otherwise it respects whatever the caller already set.
- **Files modified:** `database/factories/JobOrderFactory.php`
- **Verification:** `ConsultationNotesTest` (4/4) and `QueueControlsTest` (10/10) pass; `FactoryStatesTest` regression-checked (4/4, unaffected).
- **Committed in:** `759c6bc` (Task 2 commit)

**2. [Rule 3 - Blocking] Missing `artist/JobOrderWorkspace.vue` broke the workspace-view test with a 500**
- **Found during:** Task 2 (`ConsultationNotesTest`)
- **Issue:** This plan is deliberately backend-only (Plan 04-02 owns the real Vue page), but `resources/views/app.blade.php` calls `@vite(["resources/js/pages/{$page['component']}.vue"])`, which hard-resolves the requested page component through the Vite manifest on every full-page Inertia render — including in feature tests that assert `assertOk()` + `component(...)`. With no `.vue` file at that path, the test 500'd with `ViteManifestNotFoundException`/`ViteException` regardless of backend correctness.
- **Fix:** Added a minimal placeholder `resources/js/pages/artist/JobOrderWorkspace.vue` (same "nothing here yet" shape as the existing `Dashboard.vue` placeholder), rebuilt assets (`npm run build`). Plan 04-02 (`files_modified` already lists this exact path) replaces it with the full page.
- **Files modified:** `resources/js/pages/artist/JobOrderWorkspace.vue`
- **Verification:** `ConsultationNotesTest` (4/4) passes; `npm run types:check` clean.
- **Committed in:** `759c6bc` (Task 2 commit)

**3. [Environment, not code — logged, not fixed] Larastan/`composer types:check` fails to bootstrap in this worktree**
- **Found during:** Task 3 verification pass
- **Issue:** `phpstan/phpstan`'s turbo-ext binary fails to load (`GLIBC_2.33 not found`), which cascades into `Undefined constant "Larastan\Larastan\LARAVEL_VERSION"`. Loading `bootstrap/app.php` and bootstrapping the Kernel in isolation works correctly, ruling out an application-code cause — this is a toolchain/environment incompatibility in this sandboxed worktree, not something this plan's changes introduced.
- **Fix:** Not auto-fixed (out of scope per the Scope Boundary rule — pre-existing, environment-level, not part of this plan's `<verification>` block). Logged to `.planning/phases/04-artist-workflow-design-editor/deferred-items.md`.
- **Files modified:** `.planning/phases/04-artist-workflow-design-editor/deferred-items.md` (new)
- **Committed in:** `1c1a328` (Task 3 commit)

---

**Total deviations:** 3 (1 Rule 1 bug fix, 1 Rule 3 blocking fix, 1 logged-but-deferred environment issue)
**Impact on plan:** Both code fixes were necessary for the plan's own tests to pass as written; neither expands scope beyond what Task 2/3 already required. The deferred Larastan issue does not affect any of this plan's `<verification>` or `<acceptance_criteria>` requirements.

## Issues Encountered
- Fresh worktree had no `vendor/`, `node_modules/`, `.env`, or `database/database.sqlite` — ran `composer install`, `npm install`, `cp .env.example .env`, `php artisan key:generate`, and created the SQLite file before any Artisan/Pest command would run. `package-lock.json`'s `name` field was reverted after `npm install` renamed it to the worktree directory name (`agent-a7d864421a82e1b82`) as an unwanted side effect.
- Worktree branch was initialized from a stale single-commit `init` snapshot missing 257 files of prior-phase work (RBAC, auth, job orders, tests) — corrected via `git reset --hard` to the expected base commit `c0f3463` per the mandatory branch-check protocol (working tree was clean, so no work was lost).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Plan 04-02 can now build `artist/Dashboard.vue` and the full `artist/JobOrderWorkspace.vue` directly against this plan's five endpoints and their exact prop shapes (`jobOrders` array on `index`, `jobOrder` object on `show`).
- Plan 04-03 (design editor) can extend `JobOrderStatus` with `InDesign` — the `forward()`/`notAppear()` allow-list already recognizes `'in_design'` by string value with no further controller change needed.
- Plan 04-05 (review cycle) can extend `JobOrderStatus` with `PendingReview`/`DesignApproved` — both are already rejected by the allow-list's construction, satisfying the plan's cross-plan invariant note.
- No blockers. The Larastan environment issue (deferred-items.md) should be spot-checked on a non-sandboxed machine before Phase 4 closes, but does not block any downstream plan.

---
*Phase: 04-artist-workflow-design-editor*
*Completed: 2026-09-02*
