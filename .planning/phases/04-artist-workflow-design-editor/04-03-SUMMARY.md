---
phase: 04-artist-workflow-design-editor
plan: 03
subsystem: api
tags: [laravel, eloquent, migrations, inertia, storage, design-workflow]

# Dependency graph
requires:
  - phase: 04-artist-workflow-design-editor
    provides: "Plan 04-01's artist dashboard/queue controls, JobOrderWorkspaceController::show(), and the assignedTo() job-order factory state"
provides:
  - "design_files/revision_logs schema (D-08's single-row/many-row split)"
  - "InDesign/PendingReview/DesignApproved JobOrderStatus cases (D-06's full cycle)"
  - "RecordDesignRevision atomic action"
  - "DesignEditorController::startDesign/sendForReview endpoints with lock + resubmission guards"
  - "JobOrderWorkspaceController::show() design.initialImageUrl/design.canEdit props"
affects: ["04-04 (TOAST UI design editor)", "04-05 (review/lock/override)"]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "locked_at on design_files, not job_orders.status, is the sole lock-authority column (Open Question #2) so Plan 04-05's unlock override is a one-column change"
    - "RecordDesignRevision wraps store+upsert+insert+status-advance in a single DB::transaction(), mirroring AssignArtistToJobOrder's action shape"

key-files:
  created:
    - database/migrations/2026_09_02_084148_create_design_files_table.php
    - database/migrations/2026_09_02_084149_create_revision_logs_table.php
    - app/Models/DesignFile.php
    - app/Models/RevisionLog.php
    - database/factories/DesignFileFactory.php
    - database/factories/RevisionLogFactory.php
    - app/Actions/JobOrder/RecordDesignRevision.php
    - app/Http/Controllers/Artist/DesignEditorController.php
    - app/Http/Requests/Artist/SendForReviewRequest.php
    - app/Http/Requests/Artist/StartDesignRequest.php
    - tests/Feature/Artist/SendForReviewTest.php
    - tests/Feature/Artist/DesignLockTest.php
    - tests/Feature/Artist/DesignEditorTest.php
  modified:
    - app/Enums/JobOrderStatus.php
    - app/Models/JobOrder.php
    - app/Http/Controllers/Artist/JobOrderWorkspaceController.php
    - routes/portals.php

key-decisions:
  - "design_files.job_order_id is unique at the DB level, enforcing D-08's single-current-row rule structurally, not just by convention"
  - "sendForReview's lock guard reads optional($jobOrder->designFile)->locked_at, never $jobOrder->status, so Plan 04-05's Owner unlock only has to flip one column"
  - "Added an explicit pending_review resubmission guard (T-04-16) beyond what the plan's action text literally described, to prevent a second Send for Review from orphaning an earlier unreviewed revision_logs row"

patterns-established:
  - "Body-less FormRequests (StartDesignRequest) for transitions implied entirely by route, matching UpdateQueueEntryStatusRequest's precedent"
  - "SendForReviewRequest tightens to ['required','file','image','mimes:png'] since its only legitimate producer is the app's own canvas export, not an arbitrary upload"

requirements-completed: [JOB-04, JOB-05]

# Metrics
duration: 30min
completed: 2026-09-02
---

# Phase 04 Plan 03: Design Editor Backend Contract Summary

**design_files/revision_logs schema, the InDesign→PendingReview→DesignApproved status cycle, and a guarded startDesign/sendForReview endpoint pair keyed on `design_files.locked_at` as the sole lock authority**

## Performance

- **Duration:** ~30 min
- **Tasks:** 3 completed
- **Files modified:** 17 (13 created, 4 modified)

## Accomplishments
- `design_files` (unique `job_order_id`, `file_path`, `locked_at`) and `revision_logs` (many-per-job-order history trail) tables, plus `DesignFile`/`RevisionLog` models and factories
- Three new `JobOrderStatus` cases (`InDesign`, `PendingReview`, `DesignApproved`) giving D-06's full review cycle a real home in the enum, added together so Plan 04-05 never needs to touch this file again
- `RecordDesignRevision` atomically stores the export, overwrites the single `design_files` row, unconditionally logs a `revision_logs` row, and advances the job order to `pending_review`
- `DesignEditorController::startDesign` — the D-06 first-pass entry point into `in_design` — and `::sendForReview`, guarded on ownership, `Assigned`, an outstanding `pending_review` submission, and `designFile.locked_at`
- `JobOrderWorkspaceController::show()` now exposes `design.initialImageUrl` (a 10-minute signed URL, or null) and `design.canEdit`, the exact props Plan 04-04's canvas mounts against

## Task Commits

Each task was committed atomically:

1. **Task 1: Schema — design_files, revision_logs, status cases, models, factories** - `db88e52` (feat)
2. **Task 2: startDesign/RecordDesignRevision actions & the sendForReview endpoint** - `fd508ba` (feat)
3. **Task 3: Workspace page Design-section props** - `554ed03` (feat)

**Plan metadata:** pending (this commit)

## Files Created/Modified
- `database/migrations/2026_09_02_084148_create_design_files_table.php` - design_files table, unique job_order_id
- `database/migrations/2026_09_02_084149_create_revision_logs_table.php` - revision_logs table, many-per-job-order
- `app/Enums/JobOrderStatus.php` - added InDesign/PendingReview/DesignApproved
- `app/Models/DesignFile.php` - Fillable excludes locked_at; casts locked_at to datetime
- `app/Models/RevisionLog.php` - Fillable excludes outcome/reviewed_at
- `app/Models/JobOrder.php` - added designFile()/revisionLogs() relations
- `database/factories/DesignFileFactory.php` - locked() state via forceFill()
- `database/factories/RevisionLogFactory.php` - approved()/changesRequested() states
- `app/Actions/JobOrder/RecordDesignRevision.php` - atomic store+upsert+insert+advance
- `app/Http/Controllers/Artist/DesignEditorController.php` - startDesign/sendForReview
- `app/Http/Requests/Artist/SendForReviewRequest.php` - required|file|image|mimes:png
- `app/Http/Requests/Artist/StartDesignRequest.php` - body-less
- `app/Http/Controllers/Artist/JobOrderWorkspaceController.php` - design prop extension
- `routes/portals.php` - design/start (PATCH) and design/send-for-review (POST) routes
- `tests/Feature/Artist/SendForReviewTest.php` - 8 cases
- `tests/Feature/Artist/DesignLockTest.php` - 1 case
- `tests/Feature/Artist/DesignEditorTest.php` - 4 cases

## Decisions Made
- Kept the lock guard keyed on `design_files.locked_at` exclusively (never `job_orders.status`), per 04-RESEARCH.md's Open Question #2, so Plan 04-05's Owner unlock override stays a one-column change
- Added a `PendingReview` resubmission guard in `sendForReview` (checker-fix behavior called out explicitly in the plan text) so a second Send for Review while an earlier submission is outstanding is rejected with 422 rather than silently orphaning the earlier `revision_logs` row

## Deviations from Plan

None - plan executed exactly as written, including the two checker-fix guards (pending_review resubmission block, lock check keyed on `designFile.locked_at`) that were already spelled out in the plan's action text.

## Issues Encountered
- This worktree had no `vendor/`, `node_modules/`, `.env`, `database/database.sqlite`, or `public/build/` — none are tracked in git. Symlinking `vendor/` initially broke autoloading (composer's generated `autoload_psr4.php`/`autoload_static.php` resolve `__DIR__` through the symlink back to the main repo's `app/`, so worktree-local classes were invisible to the framework). Fixed by copying `vendor/` into the worktree and running `composer dump-autoload` locally, copying `.env` from the main repo, creating a fresh `database/database.sqlite`, and copying the main repo's built `public/build/` (Vite manifest) so 403/422 error-page rendering in feature tests doesn't hit a `ViteManifestNotFoundException`. All of this is gitignored dev/build infrastructure, not application code — no plan files were affected.

## Next Phase Readiness
- Plan 04-04 (TOAST UI design editor) can mount directly against `design.initialImageUrl`/`design.canEdit`, POST to `artist.job-orders.design.send-for-review`, and PATCH `artist.job-orders.design.start`
- Plan 04-05 (review/lock/override) can append a `review` key to `JobOrderWorkspaceController::show()`'s existing props array and only needs to flip `design_files.locked_at` for its unlock override — no other file in this plan needs revisiting
- No blockers

---
*Phase: 04-artist-workflow-design-editor*
*Completed: 2026-09-02*
