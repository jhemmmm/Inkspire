---
phase: 07-accounts-receivable
plan: 06
subsystem: backend
gap_closure: true

tags: [laravel, inertia, vue, accounts-receivable, write-off, cashier, gap-closure]

# Dependency graph
requires:
  - phase: 07-accounts-receivable
    plan: 05
    provides: "WriteOffApprovalController::approve()/index()/reject(), PaymentStatus::WrittenOff, owner/WriteOffRequests.vue"
provides:
  - "WriteOffApprovalController::approve() -- nulls write_off_requested_at on success, so an approved write-off leaves the Owner's queue immediately (CR-02)"
  - "WriteOffApprovalController::index() -- defensively excludes paid/written_off collection_status rows as a second, independent layer of protection (CR-02)"
  - "WriteOffApprovalController::reject() -- aborts 422 if collection_status is already written_off, so a stale or replayed Reject click can never erase an approved write-off's reason/requester/timestamp (CR-01)"
  - "CancellationController::store() -- treats PaymentStatus::WrittenOff as terminal alongside Paid/PendingConfirmation (CR-03)"
  - "PaymentController::store() -- treats PaymentStatus::WrittenOff as terminal alongside Paid, checked before the GCash/Maya branch (CR-03)"
  - "cashier/Dashboard.vue's canCancelJobOrder() helper -- Cancel Job Order action never renders for a written_off job order (CR-03)"
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Extracted a canCancelJobOrder(jobOrder) helper function in Dashboard.vue's <script setup>, matching the component's existing per-row helper-function convention (paymentStatusLabel(), cancellationDialogBody()), instead of inlining a two-condition boolean expression directly in the template's v-if -- keeps the template line short enough that Prettier doesn't split the payment_status !== 'written_off' comparison across lines"

key-files:
  created: []
  modified:
    - app/Http/Controllers/Owner/WriteOffApprovalController.php
    - app/Http/Controllers/Cashier/CancellationController.php
    - app/Http/Controllers/Cashier/PaymentController.php
    - resources/js/pages/cashier/Dashboard.vue
    - tests/Feature/Owner/WriteOffApprovalTest.php
    - tests/Feature/Cashier/CancellationFeeTest.php
    - tests/Feature/Cashier/RecordPaymentTest.php
    - .planning/phases/07-accounts-receivable/deferred-items.md

key-decisions:
  - "approve() nulls write_off_requested_at in the SAME forceFill()->save() call that sets collection_status to written_off -- one write inside the existing lockForUpdate() transaction, not a second ->save() call, per the plan's explicit instruction"
  - "index()'s whereNotIn('collection_status', [...]) is a defensive second layer, not a replacement for nulling write_off_requested_at in approve() -- both fixes ship together per CR-02's two independently-reachable causes (approved-but-not-nulled, and settled-while-pending)"
  - "reject()'s new abort_if checks collection_status === WrittenOff specifically (not an in_array with Paid), since rejecting a Paid-but-still-flagged entry was already established as harmless in 07-05 and is out of this plan's scope"
  - "PaymentController::store()'s WrittenOff guard is placed directly after the existing Paid guard and BEFORE the GCash/Maya payment-method branch check, so e-wallet payment attempts are blocked too, not only Cash/Bank Transfer"
  - "Dashboard.vue's v-if was refactored into a canCancelJobOrder() helper function rather than an inline two-condition boolean expression, because the template's deep indentation (44 spaces) pushed the inline `payment_status !== 'paid' && payment_status !== 'written_off'` past Prettier's printWidth:80 and caused it to wrap the '!==' operator onto its own line, splitting 'written_off' from its comparison -- functionally identical behavior, but keeps the acceptance-criteria grep pattern intact and matches this component's established per-row helper convention (paymentStatusLabel(), cancellationDialogBody())"

patterns-established: []

requirements-completed: [AR-04]

# Metrics
duration: 45min
completed: 2026-09-09
---

# Phase 7 Plan 06: Close AR-04's Write-Off Closure-Integrity Gaps (CR-01/CR-02/CR-03) Summary

**Closes three independently-reachable defects the code review and verifier found in the write-off lifecycle: an approved write-off now leaves the Owner's queue immediately and can never be rejected/erased afterward, and a written-off job order can never be cancelled or paid again through the Cashier's existing flows.**

## Performance

- **Duration:** ~45 min (including fresh-worktree bootstrap: composer/npm install, .env, sqlite db, migrate:fresh, asset build -- this worktree started with no `vendor/`, `node_modules/`, `.env`, or built assets, matching every prior Phase 7 plan's documented gap; `npm run check:fix` briefly reformatted 201 unrelated files across the repo with Prettier's markdown-table/CLAUDE.md formatting -- reverted with a batch of literal-path `git checkout --` calls before any commit)
- **Started:** 2026-09-09 (worktree base, `d23d3bc`)
- **Completed:** 2026-09-09
- **Tasks:** 2 completed
- **Files modified:** 7 (0 created, 7 modified) + 1 deferred-items.md docs note

## Accomplishments

- `WriteOffApprovalController::approve()` nulls `write_off_requested_at` in the same `forceFill()->save()` call that sets `collection_status` to `written_off`, so the entry leaves `index()`'s `whereNotNull('write_off_requested_at')` queue query immediately
- `WriteOffApprovalController::index()` additionally excludes `paid`/`written_off` `collection_status` rows via `whereNotIn`, a defensive second layer independent of whether the timestamp was correctly nulled
- `WriteOffApprovalController::reject()` now aborts with 422 (`'This write-off has already been approved and cannot be rejected.'`) if `collection_status` is already `written_off`, mirroring `approve()`'s existing terminal-state guard -- a stale or replayed Reject click can never erase an already-booked loss's `write_off_reason`/`write_off_requested_by`/`write_off_requested_at`
- `CancellationController::store()` gained a third `abort_if` for `PaymentStatus::WrittenOff`, additive alongside the existing `Paid`/`PendingConfirmation` guards -- a written-off job order can no longer be cancelled or charged a cancellation fee
- `PaymentController::store()` gained a second `abort_if` for `PaymentStatus::WrittenOff`, placed before the GCash/Maya branch so it blocks e-wallet payment attempts too -- a written-off job order can no longer accept any new payment
- `cashier/Dashboard.vue` never renders the "Cancel Job Order" menu item for a `written_off` job order (the "Written Off" badge from 07-05 is unaffected)
- 4 new regression tests added: queue-emptiness-after-approval, reject-on-already-written-off-mutates-nothing, cannot-cancel-written-off, cannot-pay-written-off

## Task Commits

Each task was committed atomically:

1. **Task 1: Close the write-off approval/rejection state machine (CR-01, CR-02)** - `293059c` (fix)
2. **Task 2: Make WrittenOff a terminal payment_status everywhere Paid already is (CR-03)** - `c98c34c` (fix)

Both tasks are `tdd="true"`; each commit includes its test additions and implementation together, since the plan's `<action>` blocks specified test content and implementation in the same step (matching 07-05's established precedent for this pairing).

## Files Created/Modified

- `app/Http/Controllers/Owner/WriteOffApprovalController.php` - `approve()` nulls `write_off_requested_at`; `index()` adds `whereNotIn('collection_status', ...)`; `reject()` adds the terminal-state `abort_if`; docblocks corrected to describe the new behavior
- `app/Http/Controllers/Cashier/CancellationController.php` - third `abort_if` for `PaymentStatus::WrittenOff`
- `app/Http/Controllers/Cashier/PaymentController.php` - second `abort_if` for `PaymentStatus::WrittenOff`, before the GCash/Maya branch
- `resources/js/pages/cashier/Dashboard.vue` - new `canCancelJobOrder(jobOrder)` helper function; `AlertDialog`'s `v-if` now calls it instead of the inline `payment_status !== 'paid'` check
- `tests/Feature/Owner/WriteOffApprovalTest.php` - 2 new tests (queue empties after approval; reject on an already-`written_off` entry is a no-op 422)
- `tests/Feature/Cashier/CancellationFeeTest.php` - 1 new test (`a written-off job order cannot be cancelled`)
- `tests/Feature/Cashier/RecordPaymentTest.php` - 1 new test (`a written-off job order cannot be paid`), using `readyForProduction()` and an explicit `Accept: application/json` header to isolate the assertion from the pre-existing status guard
- `.planning/phases/07-accounts-receivable/deferred-items.md` - logged the pre-existing Larastan gap (3 unrelated files) still present at this plan's close

## Decisions Made

- `approve()`'s `write_off_requested_at => null` write shares the existing `forceFill()->save()` call rather than a second write, keeping the fix inside the existing `lockForUpdate()` transaction with no additional round-trip
- `index()`'s `whereNotIn('collection_status', ...)` is intentionally a *second* independent layer -- it also catches the case where an entry settles to `Paid` (e.g. via `ar:send-reminders` or a Cashier payment) while a write-off request sits pending, which nulling `write_off_requested_at` in `approve()` alone would not cover
- `PaymentController::store()`'s new guard sits before the GCash/Maya branch specifically so `WrittenOff` is caught before any PayMongo API call is attempted, not after
- `Dashboard.vue`'s gating logic was extracted to a named helper function rather than left as an inline two-condition template expression, purely to keep the resulting line under Prettier's 80-character printWidth at this component's 44-space template indentation -- behavior is identical to the plan's literal instruction, just implemented via a function call

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking issue] Reverted `npm run check:fix`'s repo-wide reformat**
- **Found during:** Task 2 (formatting the `Dashboard.vue` `v-if` change)
- **Issue:** Running `npm run check:fix` (the project's documented `vp check --fix` command) reformatted 201 files across the entire repository -- `.planning/*.md` tables, `CLAUDE.md`, `.claude/skills/**/*.md`, and several unrelated `.vue` pages -- none of which are in this plan's `files_modified` scope. This is a pre-existing repo-wide Prettier/markdown-formatting drift unrelated to this plan's write-off/cancellation guard fix.
- **Fix:** Identified the full list of unintended modifications via `git status --short`, then reverted every file outside this plan's 5-file scope with literal-path `git checkout --` calls (batched to satisfy the worktree agent's argument-construction safety rules), leaving only the 5 plan-scoped files staged.
- **Files modified:** None beyond the plan's own scope (201 unrelated files were reverted, not modified)
- **Verification:** `git status --short` after the revert showed exactly the 5 plan-scoped files; `npm run check` (verify-only, no writes) independently confirmed those 201 files still have pre-existing formatting drift unrelated to this plan, and confirmed the 5 plan-scoped files are NOT in that pre-existing-drift list
- **Committed in:** N/A (revert happened before either task commit; nothing from the reformat was ever committed)

---

**Total deviations:** 1 auto-fixed (1 Rule 3 blocking-issue correction -- a tooling side effect, not an application code change)
**Impact on plan:** None on scope. The repo-wide Prettier drift is pre-existing and out of this plan's scope (per the plan's own "does NOT re-open 07-01 through 07-05" fence); reverting it was necessary to keep this plan's commits scoped to exactly the files the plan named.

## Issues Encountered

- This worktree's HEAD was on a stale single "init" commit rather than the phase's `d23d3bc` tracking commit at spawn time -- corrected via `git reset --hard d23d3bc` (clean working tree, nothing lost) per the mandatory `<worktree_branch_check>` step before any file was read.
- This worktree had no `vendor/`, `node_modules/`, `.env`, SQLite database, or built frontend assets -- ran `composer install`, `cp .env.example .env`, `php artisan key:generate`, `touch database/database.sqlite`, `php artisan migrate:fresh`, `npm install`, `npm run build`, mirroring every prior Phase 7 plan's identical documented bootstrap gap. `npm install` again briefly renamed `package-lock.json`'s `name` field; reverted with `git checkout -- package-lock.json` before any commit, per 07-01 through 07-05's documented precedent.
- `npm run check:fix` reformatted 201 files far outside this plan's scope (see Deviations above) -- reverted before committing.
- `composer types:check` (Larastan level 7) fails on the same 3 pre-existing, unrelated files flagged in every prior Phase 7 plan (`CreateCreditRequestRequest.php`, `SavePricingAndPaymentRequest.php`, `UpdateSystemConfigurationRequest.php` -- a route-model-binding type-inference gap from Phase 5). None of these files are in this plan's `files_modified`; re-logged in `deferred-items.md`. Every other verification command (`vendor/bin/pint --dirty`, the full 443-test Pest suite (440 passed, 3 skipped), `npm run types:check`) passes clean.

## Known Stubs

None. All modified surfaces render/enforce real, derived state end to end.

## Threat Flags

None -- this plan's own `<threat_model>` fully covers the surface touched: T-07-06-01 through T-07-06-05 (rejecting an already-approved write-off; an approved entry masquerading as pending; cancellation/payment reversing a booked loss; the Cashier UI matching the server-side guard) are all mitigated exactly as disposed, verified by the 4 new regression tests. No new endpoints, auth paths, or trust boundaries were introduced.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- AR-04's closure-integrity gaps (CR-01/CR-02/CR-03) identified in `07-REVIEW.md` and `07-VERIFICATION.md` are now closed: an approved write-off is a stable, correct, terminal state that leaves the Owner's queue and can never be reopened, cancelled, or paid through any Cashier-reachable path.
- The full Pest suite is green (440 passed, 3 skipped, 0 failed) and `npm run types:check` passes clean.
- The one pre-existing Larastan gap (3 files, unrelated to Phase 7's write-off/AR work) remains out of scope across all 6 plans in this phase and should get its own small fix plan.
- Phase 8's reporting can now trust `payment_status`/`collection_status` as ground truth for written-off entries, per this plan's stated purpose.
- No blockers for Phase 8.

---
*Phase: 07-accounts-receivable*
*Completed: 2026-09-09*

## Self-Check: PASSED

All 9 claimed files verified present on disk; all 3 claimed commit hashes (`293059c`, `c98c34c`, `e762dda`) verified present in `git log --oneline --all`.
