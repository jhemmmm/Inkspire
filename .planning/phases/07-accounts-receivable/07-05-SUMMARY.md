---
phase: 07-accounts-receivable
plan: 05
subsystem: ui

tags: [laravel, inertia, vue, accounts-receivable, write-off, owner-approval]

# Dependency graph
requires:
  - phase: 07-accounts-receivable
    plan: 01
    provides: "PaymentStatus::WrittenOff terminal case, AccountsReceivableCollectionStatus enum, accounts_receivable write_off_* columns"
  - phase: 07-accounts-receivable
    plan: 02
    provides: "AccountsReceivableController's Show.vue entry detail page with the 07-05 write-off-actions insertion-point HTML comment, accounts-receivable.show route"
  - phase: 07-accounts-receivable
    plan: 04
    provides: "AccountsReceivableValidationRules trait, Show.vue's Collection Status panel and Actions card"
provides:
  - "WriteOffRequestController::store() -- Accounting-side write-off request with mandatory reason, rejecting a non-Active or already-closed-by-collection_status entry (Blocker 2)"
  - "AccountsReceivablePolicy::approveWriteOff()/rejectWriteOff() -- Owner only, matching approve()/reject()'s existing narrowing"
  - "WriteOffApprovalController::index()/approve()/reject() -- Owner's write-off queue with a locked re-read re-checking collection_status inside the same transaction as the pending-request guard (Blocker 2 pending-window race)"
  - "resources/js/pages/owner/WriteOffRequests.vue -- Owner's write-off approval queue, structurally a near-copy of CreditRequests.vue with inverted button polarity"
  - "Show.vue's pending write-off Alert banner and Request Write-Off dialog"
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Blocker-2-style double independent guard: the same closed-by-collection_status condition is checked both at request time (WriteOffRequestController::store()) and re-checked inside the approving transaction's locked re-read (WriteOffApprovalController::approve()), since collection_status and AccountsReceivableStatus are orthogonal (D-09) and a payment can settle the entry in the window between the two"

key-files:
  created:
    - app/Http/Requests/AccountingStaff/RequestWriteOffRequest.php
    - app/Http/Requests/Owner/ApproveWriteOffRequest.php
    - app/Http/Requests/Owner/RejectWriteOffRequest.php
    - app/Http/Controllers/AccountingStaff/WriteOffRequestController.php
    - app/Http/Controllers/Owner/WriteOffApprovalController.php
    - resources/js/pages/owner/WriteOffRequests.vue
    - tests/Feature/AccountingStaff/WriteOffRequestTest.php
    - tests/Feature/Owner/WriteOffApprovalTest.php
  modified:
    - app/Concerns/AccountsReceivableValidationRules.php
    - app/Policies/AccountsReceivablePolicy.php
    - app/Http/Controllers/AccountingStaff/AccountsReceivableController.php
    - routes/portals.php
    - routes/owner.php
    - resources/js/config/nav/owner.ts
    - resources/js/pages/accounting-staff/AccountsReceivable/Show.vue

key-decisions:
  - "Added write_off_reason to AccountsReceivableController::deriveRow()'s shared row shape (select list + @phpstan-type) beyond what 07-02/07-04 needed, since Show.vue's pending write-off banner requires the reason text and no prior plan's controller instructions included it (Rule 2)"
  - "WriteOffApprovalController::approve()'s collection_status re-check sits immediately after the existing write_off_requested_at guard, inside the same lockForUpdate() re-read, so both conditions are evaluated against the freshest possible row (Blocker 2)"
  - "reject() has no collection_status re-check -- rejecting a settled entry is harmless since rejection never touches payment_status, only the three write-off columns"

patterns-established: []

requirements-completed: [AR-04]

# Metrics
duration: 55min
completed: 2026-09-08
---

# Phase 7 Plan 05: Write-Off Request & Owner Approval Summary

**Closes the credit lifecycle: Accounting Staff can request a write-off with a mandatory reason on an Active, not-already-closed entry, and only the Owner (never Admin) can approve or reject it through a locked-re-read queue that independently re-checks the entry hasn't settled to Paid while the request sat pending.**

## Performance

- **Duration:** ~55 min (including fresh-worktree bootstrap: composer/npm install, .env, sqlite db, migrate:fresh, asset build -- this worktree started with no `vendor/`, `node_modules/`, `.env`, or built assets, matching every prior Phase 7 plan's documented gap; also required a `git reset --hard` to correct the worktree base per the branch-check protocol)
- **Started:** 2026-09-08 (worktree base, `06921af`)
- **Completed:** 2026-09-08
- **Tasks:** 3 completed
- **Files modified:** 15 (8 created, 7 modified)

## Accomplishments
- `WriteOffRequestController::store()` rejects a non-Active entry, an already-`paid`/`written_off` entry (Blocker 2 -- checked before the pending-request guard), and a request against an entry with an already-pending write-off, before recording `write_off_reason`/`write_off_requested_by`/`write_off_requested_at`
- `AccountsReceivablePolicy::approveWriteOff()`/`rejectWriteOff()` return `true` only for `UserRole::Owner`, verified against a non-Owner (Admin) actor
- `WriteOffApprovalController::approve()` re-checks `collection_status` isn't already `paid`/`written_off` **inside** the same `lockForUpdate()` re-read that guards against a double-approve, so a payment landing in the window between the write-off request and the Owner's click is caught and never overwritten to Written Off (the exact race the checker flagged in Blocker 2)
- Approving correctly sets `collection_status` to Written Off and the job order's `payment_status` to the new terminal case, while `total_amount` and every `transactions` row stay untouched -- verified by a dedicated test
- Rejecting nulls the three write-off columns only; the entry stays Active and keeps aging, with the full prior state preserved in `audit_trail`
- Owner's `WriteOffRequests.vue` queue reuses `CreditRequests.vue`'s skeleton with the UI-SPEC's inverted destructive polarity: "Approve Write-Off" is destructive (the irreversible act here), "Reject Request" is outline/default
- `Show.vue` gained a pending-write-off `Alert` banner (never destructive) and a plain `Dialog` "Request Write-Off" action, both absent when a request is already pending or the entry is terminal

## Task Commits

Each task was committed atomically:

1. **Task 1: Contracts + Accounting-side write-off request** - `7f8765f` (feat)
2. **Task 2: Owner-side write-off approval/rejection** - `e0da6b3` (feat)
3. **Task 3: Frontend -- write-off request UI, Owner queue, and nav** - `7627baa` (feat)

**Plan metadata:** `479bbb0` (docs: log pre-existing Larastan gap still present at phase close)

_Both Task 1 and Task 2 are `tdd="true"`; test and implementation were committed together in a single commit each, following the identical precedent 07-03/07-04 already established for tasks whose `<action>`/`<behavior>` blocks specify test content and implementation together._

## Files Created/Modified
- `app/Concerns/AccountsReceivableValidationRules.php` - `writeOffReasonRules()` added alongside the existing `collectionStatusRules()`
- `app/Http/Requests/AccountingStaff/RequestWriteOffRequest.php` - route-group-gated FormRequest, mandatory `reason`
- `app/Http/Requests/Owner/ApproveWriteOffRequest.php` / `RejectWriteOffRequest.php` - exact `ApproveCreditRequest`/`RejectCreditRequest` shape, gated via the new Policy abilities
- `app/Policies/AccountsReceivablePolicy.php` - `approveWriteOff()`/`rejectWriteOff()`, Owner-only
- `app/Http/Controllers/AccountingStaff/WriteOffRequestController.php` - `store()`, double closed-entry guard (status + collection_status)
- `app/Http/Controllers/Owner/WriteOffApprovalController.php` - `index()`/`approve()`/`reject()`, locked re-read with the Blocker 2 re-check
- `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php` - `write_off_reason` added to the shared row shape (Rule 2 deviation, see below)
- `routes/portals.php` - one new POST route in the `accounting-staff` group
- `routes/owner.php` - three new routes (`write-off-requests.index/.approve/.reject`)
- `resources/js/config/nav/owner.ts` - "Write-Off Requests" nav item (FileMinus icon) below Credit Requests
- `resources/js/pages/accounting-staff/AccountsReceivable/Show.vue` - pending write-off Alert banner, Request Write-Off Dialog
- `resources/js/pages/owner/WriteOffRequests.vue` - new Owner approval queue page
- `tests/Feature/AccountingStaff/WriteOffRequestTest.php` - 6 tests covering the full `<behavior>` block including the Blocker 2 closed-by-collection_status case
- `tests/Feature/Owner/WriteOffApprovalTest.php` - 6 tests covering admin-forbidden, index scoping, approve/reject happy paths, no-pending-request guard, and the Blocker 2 pending-window race

## Decisions Made
- Added `write_off_reason` to `AccountsReceivableController::deriveRow()`'s shared row shape (`@phpstan-type` alias, `index()`'s select list, and the returned array) since `Show.vue`'s UI-SPEC-mandated pending write-off banner ("Reason given: \"{reason}\"") needs it and no prior plan's controller instructions included it. This is shared by both `index()` and `show()` via the same `deriveRow()` helper, so `index()`'s query now selects one additional column it doesn't render -- a negligible cost for keeping the two methods' row shape identical.
- `WriteOffApprovalController::approve()`'s `collection_status` re-check sits immediately after the existing `write_off_requested_at` guard, inside the same `lockForUpdate()`-protected closure, so a payment that lands in the instant between the Owner's page load and their click is still caught (Blocker 2, the checker-identified race).
- `reject()` deliberately has no `collection_status` re-check -- rejecting an already-settled entry is harmless since rejection only nulls the three write-off columns and never touches `payment_status`, matching the plan's own reasoning.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing critical functionality] Added `write_off_reason` to `AccountsReceivableController`'s row shape**
- **Found during:** Task 3 (building `Show.vue`'s pending write-off banner against the approved UI-SPEC Copywriting Contract)
- **Issue:** The banner's required copy — "Reason given: \"{reason}\"" — has no data source; `deriveRow()` only exposed `write_off_requested_at`, not the reason text itself, and no prior plan (07-02/07-04) needed it.
- **Fix:** Added `write_off_reason` to the `@phpstan-type AccountsReceivableRow` alias, `index()`'s column select list, and the returned array in `deriveRow()`; added the matching field to `Show.vue`'s `AccountsReceivableDetail` TypeScript interface.
- **Files modified:** `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php`, `resources/js/pages/accounting-staff/AccountsReceivable/Show.vue`
- **Verification:** `npm run types:check` passes; the banner renders the real reason text in manual review of the derived prop shape; full Pest suite green
- **Committed in:** `7627baa` (Task 3 commit)

---

**Total deviations:** 1 auto-fixed (1 Rule 2 missing-critical-functionality addition)
**Impact on plan:** Necessary for correctness against the approved, binding UI-SPEC Copywriting Contract. No scope creep -- the only file touched beyond this plan's own instructions is the one controller already central to the phase's Show.vue page, and the change is purely additive (one more field on an existing shared row shape).

## Issues Encountered
- This worktree's HEAD was on a stale single "init" commit rather than the phase's `06921af` tracking commit at spawn time -- corrected via `git reset --hard 06921af` (clean working tree, nothing lost) per the mandatory `<worktree_branch_check>` step before any file was read.
- This worktree had no `vendor/`, `node_modules/`, `.env`, SQLite database, or built frontend assets -- ran `composer install`, `cp .env.example .env`, `php artisan key:generate`, `touch database/database.sqlite`, `php artisan migrate:fresh`, `npm install`, `npm run build`, mirroring every prior Phase 7 plan's identical documented bootstrap gap. `npm install` again briefly renamed `package-lock.json`'s `name` field; reverted with `git checkout -- package-lock.json` before any commit, per 07-01 through 07-04's documented precedent.
- Task 2's `WriteOffApprovalTest`'s two `index()`-rendering tests (admin-can-view, index-scoping) initially failed with a `ViteException: Unable to locate file in Vite manifest` because `owner/WriteOffRequests.vue` (Task 3) didn't exist yet at the point Task 2 was committed -- this app's `app.blade.php` does `@vite([..., "resources/js/pages/{$page['component']}.vue"])`, so a full-page GET requires the specific Vue file to already be built. Confirmed via `git show a1b5549:...` that 07-02 hit this exact same coupling (Task 1's controller/tests committed before Task 2's Vue pages existed). Followed the same established resolution: committed Task 2's backend+tests as planned, then completed Task 3 and rebuilt assets, after which the full `WriteOffApprovalTest.php` file (6/6) and the full suite (`php artisan test --compact`, 439 tests, 436 passed, 3 skipped, 0 failed) both went green.
- `composer types:check` (Larastan level 7) fails on the same 3 pre-existing, unrelated files flagged in every prior Phase 7 plan (`CreateCreditRequestRequest.php`, `SavePricingAndPaymentRequest.php`, `UpdateSystemConfigurationRequest.php` -- a route-model-binding type-inference gap from Phase 5). None of these files are in this plan's `files_modified`; re-logged in `deferred-items.md` as still present at phase close. Every other verification command (`route:list`, `pint --dirty`, the full 439-test Pest suite, `npm run types:check`) passes clean.

## Known Stubs

None. Both new surfaces (Owner's write-off queue, Show.vue's write-off actions) render real, derived data end to end.

## Threat Flags

None -- the plan's own `<threat_model>` fully covers this plan's surface: T-07-05-01 (Elevation of Privilege, Admin-forbidden) verified by test; T-07-05-02 (double-submit/replay) covered by the locked re-read matching `CreditApprovalController`'s CR-05 precedent; T-07-05-03 (tampering with `total_amount`/`transactions`) verified by a dedicated assertion; T-07-05-04 (repudiation) covered by server-stamped `write_off_requested_by`/`write_off_reason`/`write_off_requested_at` plus the existing `AuditObserver`; T-07-05-05 (the pending-window race) is this plan's central mitigation, tested explicitly. No new endpoints, auth paths, or trust boundaries beyond what the threat register already disposed of.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Phase 7 (Accounts Receivable) is now complete: aging substrate (07-01), aging list/entry detail (07-02), reminder command (07-03), collection status/letter (07-04), and write-off request/approval (07-05) all ship and are tested end to end.
- The one pre-existing Larastan gap (3 files, unrelated to Phase 7) remains out of scope and should get its own small fix plan.
- No blockers for Phase 8.

---
*Phase: 07-accounts-receivable*
*Completed: 2026-09-08*

## Self-Check: PASSED

All 15 claimed files verified present on disk; all 4 claimed commit hashes (`7f8765f`, `e0da6b3`, `7627baa`, `479bbb0`) verified present in `git log --oneline --all`.
