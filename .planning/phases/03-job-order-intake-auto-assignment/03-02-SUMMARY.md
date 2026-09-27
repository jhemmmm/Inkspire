---
phase: 03-job-order-intake-auto-assignment
plan: 02
subsystem: ui
tags: [inertia, vue3, wayfinder, tailwindcss, job-orders, badges, dialog]

# Dependency graph
requires:
    - phase: 03-job-order-intake-auto-assignment (plan 03-01)
      provides: 'job_orders.status/validation_failure_reason/assigned_artist_id, JobOrder::assignedArtist() relation, JobOrderController::replaceFile() endpoint, QueueEntryController::index()/CustomerController::index() eager-loading every field this plan reads'
provides:
    - 'ReplaceJobOrderFileDialog.vue: shared, prop-driven Dialog+Form component wired to JobOrderController.replaceFile, reused unmodified across both consuming surfaces'
    - 'NewVisit.vue confirmation card renders all four job-order-status badges, the validation_failed AlertError + Replace File trigger, and the assigned-artist secondary text line'
    - "QueueList.vue gains a durable 'Job Orders' table column with description + type badge + status badge + conditional Replace File trigger per job order"
affects: [04-artist-workflow-design-editor]

# Tech tracking
tech-stack:
    added: []
    patterns:
        - 'Shared Dialog+Form component with a default trigger slot (ReplaceJobOrderFileDialog.vue) so two unrelated call sites (confirmation card, table cell) reuse identical Dialog/Form markup with only the trigger button differing'

key-files:
    created:
        - resources/js/components/ReplaceJobOrderFileDialog.vue
    modified:
        - resources/js/pages/frontline-staff/NewVisit.vue
        - resources/js/pages/frontline-staff/QueueList.vue

key-decisions:
    - 'ReplaceJobOrderFileDialog exposes jobOrderId via a required prop and its trigger via a default slot, letting each caller supply differently-styled trigger buttons (text button in NewVisit.vue, icon-only button in QueueList.vue) around one shared Dialog+Form implementation'
    - "NewVisit.vue's per-job-order-row status Badge/AlertError/assigned-artist block is wrapped in a <template v-for> (not the outer <li>) so the AlertError and Replace File dialog can render as siblings after the <li>, matching the plan's exact DOM structure"

requirements-completed: [JOB-01, JOB-02]

# Metrics
duration: ~15min
completed: 2026-09-01
---

# Phase 3 Plan 2: Job Order Intake & Auto-Assignment (Frontend) Summary

**Frontline Staff now sees each job order's validation/assignment outcome — Awaiting Assignment / Ready for Production / Assigned / Validation Failed — as a status badge in both the New Visit confirmation card and the Queue table, with a shared Replace File dialog for correcting a validation_failed Type A file from either surface.**

## Performance

- **Duration:** ~15 min (including worktree environment setup: composer install, npm install, sqlite migrate, wayfinder:generate, frontend build)
- **Completed:** 2026-09-01T23:05:11Z
- **Tasks:** 2
- **Files modified:** 3 (1 created, 2 modified)

## Accomplishments

- `ReplaceJobOrderFileDialog.vue` created as the single shared Dialog+Form shell wired to `JobOrderController.replaceFile`, consumed unmodified by both `NewVisit.vue` and `QueueList.vue` via a default trigger slot
- `NewVisit.vue`'s confirmation card renders the exact 03-UI-SPEC.md four-state status badge mapping, the `validation_failed` `AlertError` + Replace File trigger, and the "Assigned to {artist_name}" secondary line for `assigned` job orders
- `QueueList.vue` gained a new "Job Orders" table column (between Customer and Status, keeping queue-entry status and job-order status visually distinct) showing description + type badge + status badge + a conditional icon-only Replace File trigger, reusing Task 1's dialog component unchanged
- `npm run types:check` (vue-tsc strict mode) reports zero errors after both tasks
- Full existing Pest suite (128 passed, 3 pre-existing skips) and the `tests/Feature/FrontlineStaff` slice (39/39) pass unmodified, confirming no prop the frontend now reads is missing from Plan 03-01's eager-loaded backend response

## Task Commits

Each task was committed atomically:

1. **Task 1: Shared Replace-File dialog & NewVisit.vue wiring** - `86142ae` (feat)
2. **Task 2: QueueList.vue Job Orders column** - `578cfdb` (feat)

_No plan-metadata commit — worktree mode: SUMMARY.md is committed separately below; STATE.md/ROADMAP.md are updated by the orchestrator after merge._

## Files Created/Modified

- `resources/js/components/ReplaceJobOrderFileDialog.vue` (new) - Prop-driven (`jobOrderId: number`) Dialog+Form shell, default slot for the trigger, `Form v-bind="JobOrderController.replaceFile.form(jobOrderId)"`, no `AlertDialog` wrapper (corrective, non-destructive action per Copywriting Contract)
- `resources/js/pages/frontline-staff/NewVisit.vue` - `ConfirmedJobOrder` interface extended with `status`/`validation_failure_reason`/`assigned_artist`; new `jobOrderStatusLabel()` helper; confirmation card's job-order `<li>` loop now renders a second status `Badge`, an "Assigned to {artist_name}" line for `assigned`, and an `AlertError` + `ReplaceJobOrderFileDialog` trigger for `validation_failed`
- `resources/js/pages/frontline-staff/QueueList.vue` - New `JobOrderRecord` interface, `QueueEntryRecord.job_orders` field, new `jobOrderTypeLabel()` helper, new "Job Orders" `TableHead`/`TableCell` rendering description + type badge + status badge + icon-only Replace File trigger (lucide `RefreshCw`) on `validation_failed` rows; `TableEmpty` colspan updated 4 → 5

## Decisions Made

- Followed the plan's explicit instruction to give `ReplaceJobOrderFileDialog` a default slot rather than a fixed trigger button, since `NewVisit.vue` needs a text `Button` and `QueueList.vue` needs an icon-only `Button` around the identical Dialog+Form shell.
- Structured `NewVisit.vue`'s per-job-order block as `<template v-for>` wrapping the `<li>` plus sibling `<p>`/`<AlertError>`/`<ReplaceJobOrderFileDialog>` elements, since the plan requires the alert and dialog trigger to render directly below (not inside) each `<li>`.

## Deviations from Plan

None functionally — plan executed exactly as written. One documentation-only discrepancy worth recording:

**Acceptance-criteria grep counts undercounted by design, not by bug.** The plan's acceptance criteria for both tasks specify `grep -c "ReplaceJobOrderFileDialog"` (and `grep -c "RefreshCw"` in Task 2) should return `1`, but the required implementation — an `import` statement plus an opening tag plus a closing tag (since `ReplaceJobOrderFileDialog` wraps a `Button` as slot content, it cannot be self-closing) — necessarily produces 3 matching lines in `NewVisit.vue` and `QueueList.vue`, and `RefreshCw` (import + icon usage) produces 2 matching lines in `QueueList.vue`. Verified each occurrence individually via `grep -n`: all are legitimate, non-duplicated usages required by the plan's own `<action>` text. No fix needed or applied — flagging so the discrepancy isn't mistaken for an implementation defect during phase verification.

## Issues Encountered

- **Worktree had no `vendor/`, `node_modules/`, `.env`, sqlite database, or generated Wayfinder actions/routes** — same fresh-worktree gap Plan 03-01 documented. Resolved with `composer install`, `cp .env.example .env`, `php artisan key:generate`, `touch database/database.sqlite`, `php artisan migrate`, `npm install`, `php artisan wayfinder:generate --with-form` (regenerating `JobOrderController.replaceFile.form()`, gitignored per `.gitignore`), and `npm run build` (needed for Inertia-rendering feature tests to avoid `ViteManifestNotFoundException`).
- `npm install` again renamed `package-lock.json`'s `name` field to the worktree's directory name; reverted with `git checkout -- package-lock.json` before any task commit — not part of this plan's diff.
- `vp check --fix` (project's formatter) reformatted whitespace/wrapping in both edited pages after my initial edits (long `v-else-if` conditions wrapped, attribute line-breaks); re-ran `npm run types:check`, the acceptance-criteria greps, `npm run build`, and the FrontlineStaff Pest suite after formatting to confirm nothing regressed.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 3's one vertical slice (JOB-01/JOB-02) is now frontend-complete: a Frontline Staff user can see every job order's validation/assignment outcome immediately after intake and correct a `validation_failed` Type A file from either the confirmation card or the durable Queue table.
- Artist-facing UI (JOB-08/JOB-09 — assigned-jobs list, On Break/End Shift toggle calling `AssignArtistToJobOrder::claimOldestUnassigned()`) remains explicitly out of scope, deferred to Phase 4 per 03-UI-SPEC.md.
- No blockers.

---

_Phase: 03-job-order-intake-auto-assignment_
_Completed: 2026-09-01_

## Self-Check: PASSED

All 3 created/modified source files (`ReplaceJobOrderFileDialog.vue`, `NewVisit.vue`, `QueueList.vue`) plus this SUMMARY.md verified present on disk. All 3 commit hashes (`86142ae`, `578cfdb`, `c856748`) verified present in `git log --oneline --all`.
