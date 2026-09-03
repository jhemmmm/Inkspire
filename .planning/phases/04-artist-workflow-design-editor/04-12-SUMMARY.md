---
phase: 04-artist-workflow-design-editor
plan: 12
subsystem: ui
tags: [ag-psd, psd, vue, inertia, file-import, artist-workflow]

# Dependency graph
requires:
  - phase: 04-artist-workflow-design-editor
    provides: "Plan 04-04's Import Reference Image flow (onReferenceFileChosen handler, referenceImageUrl ref, ToastImageEditor's :initial-image-url consumer)"
provides:
  - "Client-side .psd detection, parsing, and flattening inside the existing Import Reference Image flow"
  - "ag-psd as a vetted npm dependency for client-side PSD parsing"
affects: []

# Tech tracking
tech-stack:
  added: ["ag-psd ^31.0.2 (client-side PSD parser/flattener, npm)"]
  patterns:
    - "PSD detection gated on file.name.toLowerCase().endsWith('.psd') inside an existing file-input change handler, branching to a dedicated async helper rather than a new UI element or route"
    - "Dual-null-cause failure handling: a local async helper returns null on both a thrown parse exception and a successfully-parsed-but-canvas-less PSD, letting the caller apply one uniform fail-loud toast for both cases"

key-files:
  created: []
  modified:
    - "resources/js/pages/artist/JobOrderWorkspace.vue"
    - "package.json"
    - "package-lock.json"

key-decisions:
  - "ag-psd package legitimacy checkpoint approved by human (npm created 2015, actively maintained, ~219k weekly downloads, official repo matches maintainer, own TS types, 2 small runtime deps, slopcheck [OK])"
  - "PSD parsing/flattening happens entirely client-side via ag-psd, not server-side Imagick, per the user's explicit D-22 choice even though Imagick's PSD delegate was confirmed working"

patterns-established:
  - "Client-side binary parsing failures (thrown exception or missing expected output) resolve to a single null sentinel consumed by one toast.error call, rather than distinct error branches per failure mode"

requirements-completed: [JOB-04]

# Metrics
duration: 6min
completed: 2026-09-03
---

# Phase 04 Plan 12: Client-side PSD Import Summary

**Artist can drop a client-supplied .psd file into the existing "Import Reference Image" picker and have it parsed/flattened entirely client-side via ag-psd, with a specific fail-loud toast on corrupt/unsupported/no-composite files.**

## Performance

- **Duration:** 6 min (Task 2 work; Task 1's checkpoint was pre-approved by the human before this session)
- **Started:** 2026-09-03T12:11:39Z
- **Completed:** 2026-09-03T12:16:19Z
- **Tasks:** 2 (Task 1: package legitimacy checkpoint, pre-approved; Task 2: implementation)
- **Files modified:** 3

## Accomplishments
- Added `ag-psd` as a vetted npm dependency (checkpoint approved prior to this session)
- Extended `onReferenceFileChosen` in `JobOrderWorkspace.vue` to detect `.psd` files by extension and route them through a new `readPsdAsFlattenedDataUrl` helper before falling through to the unchanged non-PSD path
- Wired the flattened PSD's `canvas.toDataURL('image/png')` into the same `referenceImageUrl` ref that `ToastImageEditor.vue` already consumes via `:initial-image-url` — no editor component changes needed
- Both a thrown parse exception and a successfully-parsed-but-canvas-less PSD (e.g. saved without "Maximize Compatibility") now resolve to the same fail-loud toast and file-input reset, per D-23
- Widened the hidden file input's `accept` attribute to `image/*,.psd` with zero new UI elements

## Task Commits

Each task was committed atomically:

1. **Task 1: Package legitimacy checkpoint — ag-psd** - no commit (checkpoint-only, no files modified; approved by human before this session, see Checkpoint Resolution below)
2. **Task 2: Client-side PSD parsing inside the existing reference-image import flow** - `df3b224` (feat)

## Files Created/Modified
- `resources/js/pages/artist/JobOrderWorkspace.vue` - Added `ag-psd`/`vue-sonner` imports, a `readPsdAsFlattenedDataUrl` async helper, an async `onReferenceFileChosen` with a `.psd`-gated branch, and `accept="image/*,.psd"` on the hidden file input
- `package.json` - Added `ag-psd: ^31.0.2` dependency
- `package-lock.json` - Lockfile update from `npm install ag-psd`

## Decisions Made
- Package legitimacy checkpoint (Task 1) was reviewed and approved by the human before this execution session began — see Checkpoint Resolution below. No new package-legitimacy work was performed in this session; the approval was carried forward as-is.
- Kept the dual-null-cause design exactly as specified: `readPsdAsFlattenedDataUrl` returns `null` for both a thrown exception and a missing `psd.canvas`, so the caller needs only one `if (!dataUrl)` branch and one toast message for both failure modes.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Bootstrapped a fresh worktree's missing dependencies to run verification**
- **Found during:** Task 2, running the plan's `<verify>` step (`npm run types:check`)
- **Issue:** This worktree was a fresh git worktree checkout with no `vendor/` (Composer) and no generated `resources/js/actions/`/`resources/js/routes/` (Wayfinder — both gitignored, generated-on-demand). `npm run types:check` failed with `Cannot find module '@/routes/...'` / `'@/actions/...'` errors across ~30 unrelated files, unrelated to this plan's edit but blocking the ability to confirm this plan's specific change didn't introduce new type errors.
- **Fix:** Ran `composer install`, copied `.env.example` to `.env`, ran `php artisan key:generate`, then `php artisan wayfinder:generate --with-form` (per the project's recorded decision that `--with-form` is required to match `vite.config.ts`'s `formVariants:true`). None of these outputs are tracked by git (vendor/, .env, actions/, routes/ are all gitignored) so nothing extra was committed.
- **Files modified:** None committed (all generated/gitignored artifacts)
- **Verification:** `npm run types:check` then passed cleanly (exit 0, no errors)
- **Committed in:** N/A — no trackable file changes from this step

**2. [Rule 1 - Bug] Reverted an out-of-scope repo-wide formatting pass**
- **Found during:** Task 2, running `npm run check:fix` (vite-plus lint/format, per project convention) before committing
- **Issue:** `check:fix` is not scoped to changed files — it reformatted ~130 unrelated tracked files across the whole repo (markdown tables in `.planning/`, skill docs, unrelated `.vue` pages), none of which this task touches. Per the deviation rules' scope boundary, only issues directly caused by this task's changes are in scope.
- **Fix:** Reverted all ~130 unrelated files back to their committed state via `git checkout -- <file>` (one literal command per batch), keeping only the 3 task-relevant files (`package.json`, `package-lock.json`, `resources/js/pages/artist/JobOrderWorkspace.vue`) staged. The one intentional formatting change inside `JobOrderWorkspace.vue` (a `router.patch(...)` call reflowed to multi-line by Prettier's 80-char width) was kept since it's inside this task's own diff.
- **Files modified:** None beyond the 3 already-planned files (everything else reverted, not modified)
- **Verification:** `git status --short` confirmed only the 3 intended files remained modified before commit; `npm run types:check` and all 4 acceptance-criteria greps re-verified passing after the revert
- **Committed in:** N/A — this was a revert, not a change; the resulting commit `df3b224` contains only the intended 3 files

---

**Total deviations:** 2 auto-fixed (1 blocking-environment-setup, 1 bug/scope-correction)
**Impact on plan:** Both were necessary to run the plan's own verification step correctly and to keep the commit scoped to this task's actual work. No functional scope creep — the final diff matches the plan's `<action>` and all 5 `<acceptance_criteria>` exactly.

## Issues Encountered
- Worktree HEAD had drifted to an orphan `init` commit disconnected from `main`'s history (merge-base check failed against the expected base `8602615`). Resolved via the plan-mandated `git reset --hard` to the expected base commit before any files were touched (working tree was clean at that point, so nothing was lost).

## User Setup Required

None - no external service configuration required. `ag-psd` is a pure client-side parsing library with no API keys or server-side setup.

## Next Phase Readiness
- JOB-04's PSD-import extension is complete; Artists can now start a design from either a blank canvas, a standard image, or a client-supplied `.psd` file, all through the same "Import Reference Image" entry point.
- No blockers for subsequent plans. Manual browser verification (real .psd flattening, corrupt-file toast, non-.psd regression) is deferred to end-of-phase human verification per `human_verify_mode: end-of-phase` (this project's configured mode) and Plan 04-04's own precedent.

---
*Phase: 04-artist-workflow-design-editor*
*Completed: 2026-09-03*

## Self-Check: PASSED

- FOUND: resources/js/pages/artist/JobOrderWorkspace.vue
- FOUND: package.json
- FOUND: .planning/phases/04-artist-workflow-design-editor/04-12-SUMMARY.md
- FOUND commit: df3b224
- FOUND commit: 0e23000
