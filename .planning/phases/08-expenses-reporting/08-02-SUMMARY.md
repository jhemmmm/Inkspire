---
phase: 08-expenses-reporting
plan: 02
subsystem: ui
tags: [inertia, vue3, wayfinder, tailwind, shadcn-vue]

# Dependency graph
requires:
  - phase: 08-expenses-reporting (plan 01)
    provides: "Expense model, ExpenseController (index/store/update/void), accounting-staff.expenses.* routes, active() scope"
provides:
  - "resources/js/components/reports/DateRangeControl.vue — shared preset + custom date-range control (Today/This Week/This Month/This Quarter/Custom), emits concrete from/to dates"
  - "accounting-staff/Expenses/Index.vue — EXP-01's real-world entry point: ledger, summary card, Record/Edit/Void dialogs"
  - "Expenses nav item on the Accounting Staff portal"
affects: [08-05-reports-workspace]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "DateRangeControl.vue: presets computed client-side, submitted as concrete from/to dates (never a preset name), matching FilterExpensesRequest's two-date contract"
    - "Category Select bound via v-model + a sibling hidden <input name=...> so Inertia's native-form-based <Form> component can read the value at submit time (matches AccountsReceivable/Show.vue's CollectionStatus pattern)"
    - "Amount/date/description fields left uncontrolled via Input/Textarea's :default-value prop (no per-field refs needed) since the <Form> component reads the DOM at submit time"
    - "Single shared, controlled Edit/Void Dialog per dialog type (not one per table row) — row buttons set a ref and open it, avoiding N duplicate Dialog instances in the DOM"

key-files:
  created:
    - resources/js/components/reports/DateRangeControl.vue
    - resources/js/pages/accounting-staff/Expenses/Index.vue
  modified:
    - resources/js/config/nav/accounting-staff.ts

key-decisions:
  - "Ledger empty state always renders the range-scoped copy ('Nothing in this range... between {from} and {to}') rather than trying to distinguish it from the all-time-empty copy ('No expenses recorded'), since ExpenseController::index has no all-time-existence signal separate from the range-scoped rows/activeCount/voidedCount it returns — the range-scoped message is accurate in both cases, the all-time message would sometimes be wrong."
  - "recorded_by binds as a plain string (not a nested {name} object), matching ExpenseController::index's actual serialization ('recorded_by' => $expense->recordedBy->name) rather than the plan text's abstract shape description."

patterns-established:
  - "Pattern: shared date-range control lives in resources/js/components/reports/ and is consumed by both a role's own CRUD ledger page and (later) the shared multi-role Reports workspace — one component, two call sites, never two date pickers."

requirements-completed: [EXP-01]

# Metrics
duration: 33min
completed: 2026-09-10
---

# Phase 8 Plan 2: Expenses UI Summary

**Accounting Staff can record/edit/void an expense end-to-end through a real ledger page, and the phase's one shared date-range control now exists for Plan 08-05 to reuse verbatim**

## Performance

- **Duration:** ~33 min
- **Started:** 2026-09-10T00:29:34Z
- **Completed:** 2026-09-10T01:02:56Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- `DateRangeControl.vue`: five preset buttons (Today/This Week/This Month/This Quarter/Custom) computing concrete dates client-side, emitting `apply` immediately on preset click or on "Apply Range" for Custom; active-preset detection against the `from`/`to` props; inline validation (malformed date, future date, to-before-from) with exact UI-SPEC copy
- `accounting-staff/Expenses/Index.vue`: H1 + "Record Expense" CTA, the shared `DateRangeControl`, a single summary `Card` (total for range, expense count, voided-excluded sub-line), and a ledger `Table` where voided rows stay at full contrast with a "Voided" badge and the reason shown beneath the description
- Record/Edit/Void dialogs, all plain `Dialog`s (no `AlertDialog`) bound via Wayfinder `.form()` helpers to `ExpenseController.store`/`update`/`void`; Void requires a mandatory reason and uses `variant="destructive"`; Edit guards against a stale-voided race with a read-only `Alert` fallback
- "Expenses" nav item (lucide `Wallet`) added to the Accounting Staff portal after "Accounts Receivable"
- `npm run build`, `npm run types:check`, and `npm run check` (vite-plus lint/fmt) all pass clean on the new/modified files

## Task Commits

Each task was committed atomically:

1. **Task 1: Shared DateRangeControl.vue** - `df887ef` (feat)
2. **Task 2: Expenses ledger page + dialogs + nav** - `cdc8633` (feat)
3. **Formatting fixup (both files)** - `a88e8f5` (style)

**Plan metadata:** (this commit)

## Files Created/Modified
- `resources/js/components/reports/DateRangeControl.vue` - Shared preset + custom date-range control, emits `apply` with concrete `from`/`to`
- `resources/js/pages/accounting-staff/Expenses/Index.vue` - Expenses ledger page: summary card, Table, Record/Edit/Void dialogs
- `resources/js/config/nav/accounting-staff.ts` - Added "Expenses" nav item (Wallet icon) after "Accounts Receivable"

## Decisions Made
- Empty-ledger copy always uses the range-scoped "Nothing in this range" message rather than trying to detect the all-time-empty case, since the backend prop contract (`rows`/`total`/`activeCount`/`voidedCount`/`filters`) carries no signal distinguishing "empty this month" from "no expenses have ever been recorded." The range-scoped copy is accurate either way.
- `recorded_by` is typed and bound as a plain `string`, matching `ExpenseController::index`'s actual output (`$expense->recordedBy->name`), not the plan text's abstract `{name}` object shape.
- Edit/Void dialogs are single shared, controlled `Dialog` instances (opened via a ref set by the row's button click) rather than one `Dialog` per table row, avoiding N duplicate Dialog subtrees in the DOM for what is functionally a single-selection modal.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] `npm run check` (vite-plus fmt) flagged formatting issues in both new files**
- **Found during:** Post-Task-2 verification (`<verification>` block: `npm run types:check` and `npm run check`)
- **Issue:** Several JSX/template attribute lines and long import lists exceeded the project's `printWidth: 80` prettier config, and `vp check` reported formatting failures for both `DateRangeControl.vue` and `Expenses/Index.vue`.
- **Fix:** Ran `node_modules/.bin/vp check --fix` scoped to only the two affected files (not the repo-wide `npm run check`, which errors out unrelated to this plan on a pre-existing syntax issue in the untracked `demo/index.html` — see Issues Encountered). Re-ran `npm run types:check` after the fix to confirm no regressions.
- **Files modified:** `resources/js/components/reports/DateRangeControl.vue`, `resources/js/pages/accounting-staff/Expenses/Index.vue`
- **Verification:** `node_modules/.bin/vp check` reports "Found no warnings or lint errors" for the scoped files; `npm run types:check` and `npm run build` both still pass
- **Committed in:** `a88e8f5` (separate `style` commit, per the git-safety protocol against amending prior commits)

**2. [Rule 1 - Bug] Removed a stray leftover `reactive()` binding**
- **Found during:** Task 2, immediately after writing `Expenses/Index.vue` (IDE diagnostics flagged `Cannot find name 'reactive'`)
- **Issue:** An intermediate draft introduced `const categorySelectOptions = reactive(props.categories);` and left it in the file after the final design settled on binding the `categories` prop directly in the template (no local wrapper needed).
- **Fix:** Removed the stray `reactive()` line and pointed both `SelectItem v-for` loops at `categories` directly.
- **Files modified:** `resources/js/pages/accounting-staff/Expenses/Index.vue`
- **Verification:** `npm run types:check` passes with zero errors
- **Committed in:** `cdc8633` (Task 2 commit — caught and fixed before that commit was made)

---

**Total deviations:** 2 auto-fixed (1 blocking formatting fix, 1 bug fix caught pre-commit)
**Impact on plan:** No scope creep — both fixes are mechanical corrections to this plan's own new files, required by the plan's own stated verification step.

## Issues Encountered
- `npm run check` (repo-wide `vp check`) fails on an unrelated, pre-existing syntax error in the untracked `demo/index.html` (a reference asset, not part of the application, already present in `git status` before this plan started). Worked around by scoping `vp check`/`vp check --fix` to only this plan's files, per the deviation-rules scope boundary (out-of-scope pre-existing issues are not fixed). Not logged to `deferred-items.md` since `demo/` is explicitly documented in `PROJECT.md` as a reference-only asset, not application code that would ever need this tooling run against it.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `DateRangeControl.vue` exists at `resources/js/components/reports/` and is ready for Plan 08-05's Reports workspace to mount verbatim — no UI-pattern decisions remain for that plan's range control.
- EXP-01 is now fully closed end-to-end: a real Accounting Staff user can reach Expenses from the portal nav, record/edit/void an expense, and see voided entries stay visible with their reason.
- No backend changes were made or needed in this plan; `ExpenseController`'s prop contract from 08-01 was consumed as-is.

---
*Phase: 08-expenses-reporting*
*Completed: 2026-09-10*

## Self-Check: PASSED

All 3 created/modified files verified present on disk. All 3 commit hashes (`df887ef`, `cdc8633`, `a88e8f5`) verified present in `git log`.
