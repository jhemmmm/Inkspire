---
phase: 04-artist-workflow-design-editor
plan: 04
subsystem: ui
tags: [inertia, vue, tui-image-editor, wayfinder, artist-portal, design-editor]

# Dependency graph
requires:
    - phase: 04-artist-workflow-design-editor
      provides: "Plan 04-02's JobOrderWorkspace.vue/Dashboard.vue shells and artistNavItems; Plan 04-03's design_files/revision_logs schema, InDesign/PendingReview status cases, DesignEditorController::startDesign/sendForReview endpoints, and JobOrderWorkspaceController's design.initialImageUrl/design.canEdit props"
provides:
    - 'resources/js/components/ToastImageEditor.vue — thin Vue 3 Composition API wrapper over the vanilla tui-image-editor ImageEditor class, exposing exportPng() via defineExpose'
    - 'resources/js/types/tui-image-editor.d.ts — hand-written ambient module declaration for the untyped tui-image-editor package'
    - 'Design card in JobOrderWorkspace.vue: pre-editor choice, mounted editor, Send for Review submission, locked-state note, pending-review note'
    - 'Dashboard.vue in_design/pending_review badge labels and row-action handling'
affects:
    [
        "04-05 (review/lock/override — reads the same design_files.locked_at gate this plan's canEdit prop is derived from)",
        "04-06 (Review section — will render inside the same Job Order Workspace, alongside this plan's Design card)",
    ]

# Tech tracking
tech-stack:
    added: ['tui-image-editor@3.15.3', 'tui-color-picker@2.2.8']
    patterns:
        - 'Vanilla third-party class libraries with no Vue 3 wrapper: mount in onMounted, destroy in onBeforeUnmount, expose an imperative API via defineExpose (ToastImageEditor.vue) — reusable for any future non-Vue canvas/widget library'
        - "Client-generated Blob/File form submission uses useForm() + forceFormData: true, never Inertia's uncontrolled <Form> (which only supports user-picked native file inputs)"
        - 'Background/fire-and-forget status transitions: flip local UI state immediately, fire the PATCH without awaiting a .then(), so the canvas mount is not blocked on a round-trip'

key-files:
    created:
        - resources/js/types/tui-image-editor.d.ts
        - resources/js/components/ToastImageEditor.vue
    modified:
        - package.json
        - package-lock.json
        - resources/js/pages/artist/JobOrderWorkspace.vue
        - resources/js/pages/artist/Dashboard.vue

key-decisions:
    - "Installed only tui-image-editor + tui-color-picker (framework-agnostic core), not @toast-ui/vue-image-editor, which resolves peerDependencies vue@^2.6.14 and conflicts with this project's Vue 3.5.13 (D-09 amended, per 04-RESEARCH.md)"
    - 'Aliased the imported sendForReview route helper to sendForReviewRoute in JobOrderWorkspace.vue to avoid a duplicate-identifier collision with the plan-specified local async function of the same name'
    - "Fresh worktree had no vendor/, node_modules/, .env, database.sqlite, or public/build (none tracked in git, per 04-02/04-03 precedent) — hardlink-copied vendor/ and node_modules/ from the main repo checkout (same filesystem, so cp -al is instant and isolates this worktree's npm install from concurrent sibling worktrees), copied .env/database.sqlite/public/build, ran composer dump-autoload and php artisan wayfinder:generate --with-form"
    - "Worktree branch had NOT been reset to the wave's expected base commit (6cc8d3c) when this agent started — merge-base showed it was still sitting on the bare 'init' commit, missing Plans 04-02/04-03 entirely. Ran the mandated git reset --hard 6cc8d3c... from the branch-check step (the working tree was clean and all untracked dev-infra files were gitignored, so nothing was lost) before any task work began"

patterns-established:
    - "ToastImageEditor.vue's mount/destroy-in-lifecycle-hooks + defineExpose(imperative API) shape is the template for any future non-Vue-native library wrapper in this codebase"

requirements-completed: [JOB-04, JOB-05]

# Metrics
duration: 15min
completed: 2026-09-02
---

# Phase 04 Plan 04: TOAST UI Design Editor Integration Summary

**Vue 3 Composition API wrapper around the vanilla `tui-image-editor` class (hand-typed `.d.ts`, `usageStatistics: false`), wired into the Job Order Workspace's Design card with a client-side-first blank-canvas/reference-image start and a `useForm`/`forceFormData` Send for Review submission.**

## Performance

- **Duration:** ~15 min
- **Completed:** 2026-09-02
- **Tasks:** 2 completed
- **Files modified:** 6 (2 created, 4 modified)

## Accomplishments

- `tui-image-editor@3.15.3` + `tui-color-picker@2.2.8` installed (not the Vue 2-only `@toast-ui/vue-image-editor` wrapper); `npm run types:check` passes cleanly against a hand-written ambient module declaration, zero `@ts-expect-error` needed.
- `ToastImageEditor.vue`: mounts/destroys the vanilla `ImageEditor` class in `onMounted`/`onBeforeUnmount`, loads an existing signed image URL or starts blank, exposes `exportPng()` for a flattened PNG data URI, and opts out of NHN's default hostname telemetry via `usageStatistics: false`.
- Job Order Workspace's new "Design" card: four mutually-exclusive states checked in priority order (pending-review note → locked note → pre-editor choice → mounted editor + Send for Review), matching D-06/D-10/D-11's contract that the editor and an outstanding verdict never render together.
- Both the blank-canvas and reference-image start paths flip the UI to the mounted editor immediately and fire `startDesign` in the background without blocking on the round-trip.
- Send for Review exports the canvas to a flattened PNG, wraps it in a `File`, and posts via `useForm({ file })` + `forceFormData: true` (Inertia's uncontrolled `<Form>` cannot carry a client-generated `Blob`/`File`).
- Dashboard.vue now badges `in_design` ("In Design", `secondary`) and `pending_review` ("Pending Review", `default`), extends the existing Forward/Not-Appear/Continue action branch to also cover `in_design`, and adds a `pending_review` branch that renders only a Continue link (verdict buttons live in the Workspace, added by Plan 04-06).

## Task Commits

1. **Task 1: Install TOAST UI core & build the Vue 3 wrapper** - `6763733` (feat)
2. **(formatting fix on Task 1's files, see below)** - `c299d04` (style)
3. **Task 2: Wire the editor into the Job Order Workspace & Dashboard's in-progress rows** - `ba78994` (feat)

**Plan metadata:** pending (this commit)

## Files Created/Modified

- `package.json` / `package-lock.json` — added `tui-image-editor`, `tui-color-picker`
- `resources/js/types/tui-image-editor.d.ts` — ambient module: `ImageEditorOptions` interface, `ImageEditor` class (constructor, `toDataURL`, `destroy`)
- `resources/js/components/ToastImageEditor.vue` — Composition API wrapper; `initialImageUrl`/`initialImageName` props; `exportPng()` exposed via `defineExpose`
- `resources/js/pages/artist/JobOrderWorkspace.vue` — Design card (pre-editor choice, mounted editor, Send for Review, locked/pending-review notes), `design` prop, `onStartBlankCanvas`/`onReferenceFileChosen`/`sendForReview` handlers
- `resources/js/pages/artist/Dashboard.vue` — `in_design`/`pending_review` badge mapping, extended action branches

## Decisions Made

- Installed only the framework-agnostic `tui-image-editor`/`tui-color-picker` packages, per 04-RESEARCH.md Pitfall 1 (the Vue 2-only `@toast-ui/vue-image-editor` wrapper would break against this project's Vue 3.5.13).
- Aliased the imported `sendForReview` Wayfinder route helper to `sendForReviewRoute` in `JobOrderWorkspace.vue` — see Deviations below.
- Set up this worktree's dev environment (`vendor/`, `node_modules/`, `.env`, `database.sqlite`, `public/build/`, Wayfinder-generated `resources/js/actions`/`resources/js/routes`) by hardlink-copying from the main repo checkout rather than a fresh install, following the pattern 04-02/04-03 already established for fresh worktrees. `node_modules/` was hardlink-copied (not symlinked) specifically so this plan's `npm install` of two new packages stays isolated from any concurrently-running sibling worktree agent, rather than mutating the shared main-repo `node_modules/`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Aliased the imported `sendForReview` route helper to avoid a duplicate-identifier collision**

- **Found during:** Task 2 (wiring `JobOrderWorkspace.vue`)
- **Issue:** The plan's action text specifies both `import { start, sendForReview } from '@/routes/artist/job-orders/design';` and `async function sendForReview() { ... sendForReviewForm.post(sendForReview.url(...), ...) }` — two top-level bindings with the identical name `sendForReview` in the same module scope, which is a TypeScript/JavaScript duplicate-identifier compile error.
- **Fix:** Imported the route helper as `sendForReview as sendForReviewRoute`, keeping the local handler function named `sendForReview` (so the submit button's `@click="sendForReview"` and the Copywriting Contract label are unaffected) and calling `sendForReviewRoute.url(...)` inside it.
- **Files modified:** `resources/js/pages/artist/JobOrderWorkspace.vue`
- **Verification:** `npm run types:check` passes with zero errors.
- **Committed in:** `ba78994` (Task 2 commit)

**2. [Rule 3 - Blocking] Reset worktree branch to the correct wave-3 base commit before starting**

- **Found during:** Pre-task setup, before Task 1
- **Issue:** This worktree's branch was still on the bare `init` commit (`351fb0d`) — `git merge-base HEAD 6cc8d3c...` did not equal `6cc8d3c...`, meaning Plans 04-02 and 04-03's work (this plan's stated dependencies) was entirely absent (no `app/Http/Controllers/Artist/DesignEditorController.php`, no `design.initialImageUrl`/`canEdit` props, no `resources/js/routes/artist/job-orders/design`).
- **Fix:** Ran the `<worktree_branch_check>` step's mandated `git reset --hard 6cc8d3c54f0d34b43716285398aee684a62b0967` — the working tree was clean at that point and every file I had added up to then (hardlink-copied `vendor/`/`node_modules/`, `.env`, `database.sqlite`, Wayfinder-generated files) was gitignored/untracked, so nothing was lost. Regenerated Wayfinder output and `composer dump-autoload` against the corrected base afterward.
- **Files modified:** none (branch pointer only; no working-tree file content lost)
- **Verification:** `git log --oneline -10` shows Plans 04-01/04-02/04-03's commits; `app/Http/Controllers/Artist/DesignEditorController.php` and `resources/js/routes/artist/job-orders/design/index.ts` exist and Wayfinder-generate correctly.
- **Committed in:** n/a (pre-work environment correction, not a code change)

**3. [Style] Reflowed two Task-1 lines to fit the project's 80-column formatter**

- **Found during:** Running `vp check --fix` (scoped to this plan's 4 touched files) as part of Task 2 verification
- **Issue:** `ToastImageEditor.vue`'s `menu` array and `tui-image-editor.d.ts`'s constructor signature exceeded the project's `printWidth: 80` prettier setting.
- **Fix:** Ran `npx vp check --fix` scoped to only this plan's files; the formatter reflowed both onto multiple lines. No logic change.
- **Files modified:** `resources/js/components/ToastImageEditor.vue`, `resources/js/types/tui-image-editor.d.ts`
- **Verification:** `npm run types:check` and all acceptance-criteria greps re-run and still pass after formatting.
- **Committed in:** `c299d04` (separate style commit, kept apart from Task 1's feat commit per the no-amend rule)

---

**Total deviations:** 3 (1 bug fix, 1 blocking environment fix, 1 style/formatting)
**Impact on plan:** All three were necessary for the plan to compile/execute at all (identifier collision, missing dependency commits) or for baseline formatting compliance. No scope creep — no functionality was added beyond what the plan specified.

## Issues Encountered

- The plan's Task 1 acceptance criterion `grep -c "ToastImageEditor" resources/js/pages/artist/JobOrderWorkspace.vue` (should read Task 2's criterion) expected `2`; it returns `3` because the plan's own instruction to declare `const editorRef = ref<InstanceType<typeof ToastImageEditor> | null>(null);` adds a third matching line beyond the `import` and `<ToastImageEditor` usage lines the plan counted. This mirrors the identical, already-documented pattern from Plan 04-02's Summary (`artistNavItems` grep undercount) — expected/correct code, not a deviation requiring a fix.

## User Setup Required

None - no external service configuration required. (`usageStatistics: false` means no NHN account/telemetry opt-out setup is needed either.)

## Next Phase Readiness

- JOB-04 (design editor) and JOB-05 (send for review) are now fully user-deliverable end-to-end (backend contract from Plan 04-03 + this plan's editor UI); marked complete in REQUIREMENTS.md per the deliberate split noted in Plan 04-03's Summary.
- Plan 04-05 (review/lock/override) can rely on `design.canEdit` continuing to derive from `design_files.locked_at` exactly as Plan 04-03 established — this plan added no new lock-authority logic.
- Plan 04-06 (Review section) can append a sibling `Card` to the same `flex flex-col gap-6` wrapper this plan's Design card already lives in, without restructuring.
- Manual browser verification (editor menu bar renders, blank canvas and existing-image loading both work, color picker is styled, no NHN telemetry request in the network tab) is deferred to end-of-phase human verification per this project's `human_verify_mode: "end-of-phase"` config — no Dusk/Playwright in this project per 04-VALIDATION.md's Manual-Only Verifications.
- No blockers.

---

_Phase: 04-artist-workflow-design-editor_
_Completed: 2026-09-02_

## Self-Check: PASSED

- FOUND: resources/js/types/tui-image-editor.d.ts
- FOUND: resources/js/components/ToastImageEditor.vue
- FOUND: resources/js/pages/artist/JobOrderWorkspace.vue
- FOUND: resources/js/pages/artist/Dashboard.vue
- FOUND: .planning/phases/04-artist-workflow-design-editor/04-04-SUMMARY.md
- FOUND: commit 6763733 (Task 1)
- FOUND: commit c299d04 (style fix)
- FOUND: commit ba78994 (Task 2)
