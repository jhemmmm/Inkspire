---
phase: 07-accounts-receivable
plan: 07
subsystem: payments
tags:
    [laravel, pest, accounts-receivable, write-off, on-credit, pos, gap-closure]

# Dependency graph
requires:
    - phase: 07-accounts-receivable (07-06)
      provides: write-off queue clearing (CR-02), stale-reject guard (CR-01), cancel/pay written-off guards (CR-03)
provides:
    - Derived-balance (not just collection_status flag) guard on write-off approval
    - Terminal-state guard on the third payment_status writer (CreditRequestController::store())
    - Terminal-state guard on the reachable entry point into that writer (PaymentController::edit())
    - Full writer/reader audit of every payment_status/collection_status write site in app/
affects: [08-reporting-audit-trail]

# Tech tracking
tech-stack:
    added: []
    patterns:
        - 'Derived-balance guard: recompute total_amount minus sum(Completed transactions) on a locked re-read inside the same DB::transaction() as the primary lock, rather than trusting a denormalized status flag alone'
        - "Additive abort_if(payment_status === WrittenOff, 422, ...) guard clause, reusing the exact message string across every reachable entry point (store() and edit()), matching 07-06's established shape"

key-files:
    created:
        - tests/Feature/Cashier/CreditRequestTest.php
    modified:
        - app/Http/Controllers/Owner/WriteOffApprovalController.php
        - app/Http/Controllers/Cashier/CreditRequestController.php
        - app/Http/Controllers/Cashier/PaymentController.php
        - tests/Feature/Owner/WriteOffApprovalTest.php
        - tests/Feature/Cashier/RecordPaymentTest.php
        - .planning/phases/07-accounts-receivable/deferred-items.md

key-decisions:
    - 'WriteOffApprovalController::approve() now locks and re-reads the JobOrder itself (not just the AccountsReceivable row), computing outstandingBalance identically to index(), as the authoritative settlement check; the existing collection_status check stays as a cheap short-circuit, not a replacement'
    - "CreditRequestController::store()'s WrittenOff guard is additive (a new abort_if line), not merged into the existing in_array() check, per the plan's explicit instruction to preserve every existing guard's exact current behavior/messages"
    - "PaymentController::edit()'s guard reuses store()'s exact message string verbatim, so both enforcement points read as the same terminal-state rule rather than two different messages for the same condition"
    - "Task 3's audit found a 6th payment_status writer defect (ConfirmPaymentIntent can overwrite WrittenOff back to Paid/PartiallyPaid via a late-confirmed GCash/Maya transaction) but did NOT fix it in this plan, per the plan's explicit scope fence: a newly discovered defect is a candidate for a further gap-closure round, not a same-plan scope expansion. Logged in deferred-items.md."

patterns-established:
    - "A terminal payment_status (WrittenOff) must be checked at every writer AND every reachable entry point into that writer, not just the writer itself — the audit process (grep every write site, trace the precondition claimed for each 'unreachable' one against current code) is now the standard verification step for this class of bug"

requirements-completed: [AR-04]

# Metrics
duration: 45min
completed: 2026-09-09
---

# Phase 07 Plan 07: Write-Off Closure Integrity (Round 2 Gap Closure) Summary

**Closed two independently-reachable write-off reversal defects (derived-balance settlement race in approve(), and CreditRequestController's missing WrittenOff guard) with real-trigger regression tests, then audited every payment_status/collection_status writer in the codebase and found a third, distinct 6th-writer defect in ConfirmPaymentIntent that is deliberately left unfixed per the plan's scope fence.**

## Performance

- **Duration:** 45 min
- **Started:** 2026-09-09T06:15:00Z (approx, worktree setup)
- **Completed:** 2026-09-09
- **Tasks:** 3
- **Files modified:** 6 (5 app/test files + deferred-items.md)

## Accomplishments

- `WriteOffApprovalController::approve()` now guards on a derived, locked-re-read outstanding balance (total_amount minus Completed transactions), not just the lag-prone `collection_status` flag — closing the settlement race where a real Cash/Bank/PayMongo-confirmed payment hadn't yet been reconciled by the daily `ar:send-reminders` cron.
- `CreditRequestController::store()` — the third writer of `payment_status`, previously with zero `WrittenOff` awareness — now aborts 422 for a written-off job order in both its unlocked pre-check and locked re-read, matching `PaymentController::store()`'s established shape.
- `PaymentController::edit()` now aborts 422 for a written-off job order before rendering the Pricing + Payment page, closing the reachable bookmarkable-GET entry point into the credit-request reversal defect.
- Both new regression tests exercise the real triggering condition (an actual Cash payment recorded through the payment route; an actual POST to the credit-request route) rather than simulating the effect via `forceFill`.
- Full writer/reader audit: 8 `payment_status` write sites across 5 files, 4 `collection_status` lines (3 writes + 1 serialization read) — every disposition (guarded or structurally unreachable) confirmed against current code, not assumed.
- Audit surfaced a genuine 6th defect (`ConfirmPaymentIntent` can silently reverse `WrittenOff` back to `Paid`/`PartiallyPaid`) — documented, deliberately not fixed here per the plan's scope discipline.

## Task Commits

Each task was committed atomically:

1. **Task 1: Guard WriteOffApprovalController::approve() on the derived outstanding balance** - `537bf02` (fix)
2. **Task 2: Teach CreditRequestController and PaymentController::edit() that WrittenOff is terminal** - `32b3833` (fix)
3. **Task 3: Writer/reader audit** - `832fce5` (docs)

## Files Created/Modified

- `app/Http/Controllers/Owner/WriteOffApprovalController.php` - Adds a locked `JobOrder` re-read + `outstandingBalance` computation (identical shape to `index()`) as the authoritative settlement guard inside `approve()`'s existing `DB::transaction()`
- `tests/Feature/Owner/WriteOffApprovalTest.php` - New test: a real Cashier-recorded Cash payment settles the job order while a write-off request is pending; approval is blocked with 422
- `app/Http/Controllers/Cashier/CreditRequestController.php` - Adds `abort_if(payment_status === WrittenOff, ...)` in both the unlocked pre-check and locked re-read of `store()`
- `app/Http/Controllers/Cashier/PaymentController.php` - Adds the same `WrittenOff` guard to `edit()`, reusing `store()`'s exact message string
- `tests/Feature/Cashier/CreditRequestTest.php` - New file: a written-off job order cannot be placed back on credit (422, no AR row created, `payment_status` unchanged)
- `tests/Feature/Cashier/RecordPaymentTest.php` - Extended the existing written-off test to also assert `edit()` returns 422
- `.planning/phases/07-accounts-receivable/deferred-items.md` - Logged Task 3's audit confirmation and the newly-found 6th-writer defect

## Decisions Made

- The derived-balance guard writes through the same locked `JobOrder` instance the guard read (`$jobOrder->forceFill(...)->save()`), replacing the prior second unlocked lazy-load via the `jobOrder` relation — avoids a second, unlocked read/write of the same row inside one transaction.
- Kept every pre-existing guard's exact wording and ordering unchanged in `CreditRequestController::store()` (Paid check first, then the new WrittenOff check, then the pending-action check) — the fix is additive, not a merged `in_array()`.

## Deviations from Plan

### Auto-fixed Issues

None — plan executed exactly as written for Tasks 1 and 2.

### Environment setup (not a plan deviation, but required before any test could run)

This worktree had no `vendor/`, `.env`, or `public/build/` (all gitignored, not present in a fresh worktree checkout). Verified `composer.lock` was byte-identical to the main repo before copying `vendor/` (rsync, not symlink — a symlinked `vendor/` broke Pest's test-namespace path resolution, causing every test to fail with a spurious "facade root not been set" error), copied `.env`, and copied the prebuilt `public/build/` assets (frontend unchanged by this plan, so reuse is safe). None of this is tracked by git or part of the plan's deliverable; it was local-only test-environment setup.

---

**Total deviations:** 0 auto-fixed in application code.
**Impact on plan:** None — all three tasks match the plan's action/acceptance criteria exactly.

## Issues Encountered

- **Symlinked `vendor/` broke Pest test discovery**: initially symlinked `vendor/` from the main repo for speed; Pest's namespace-from-path resolution followed the symlink's realpath and computed test namespaces relative to the wrong root (e.g. `P\claude\worktrees\agentaf981ac00422783bc\Tests\...` instead of `P\Tests\...`), causing every test to fail with "A facade root has not been set." Resolved by `rsync`-copying `vendor/` into the worktree instead of symlinking (composer.lock was verified identical first).
- **Task 3's audit found a real, previously-undocumented 6th `payment_status` writer defect** in `app/Actions/POS/ConfirmPaymentIntent.php`: a job order can go On-Credit → have a GCash/Maya payment attempted (creating a `pending_confirmation` transaction, since neither `PaymentController::store()` nor `WriteOffRequestController::store()`/`WriteOffApprovalController::approve()` block this combination) → be written off (Task 1's derived-balance guard only sums _Completed_ transactions, so the still-pending transaction doesn't block it) → and then have the late-arriving PayMongo webhook/reconciliation silently overwrite `payment_status` from `written_off` back to `paid`/`partially_paid`. Per the plan's explicit instruction ("a newly discovered 6th defect is a candidate for a further gap-closure round, not a same-plan scope expansion"), this was **not fixed** in this plan — documented in `.planning/phases/07-accounts-receivable/deferred-items.md` for a future gap-closure round.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- AR-04's second-round gaps (settlement race in write-off approval, CreditRequestController's missing terminal-state guard, PaymentController::edit()'s reachable entry point) are closed with real-trigger regression tests.
- A 6th, distinct defect (`ConfirmPaymentIntent` can reverse `WrittenOff`) is documented but unresolved — Phase 8's reporting should NOT yet fully trust `payment_status = written_off` as permanently terminal until that gap-closure round lands; flagged in `deferred-items.md` for prioritization before Phase 8's financial reports are built on top of these columns.
- Full regression suite: 445 tests, 0 failures. `vendor/bin/pint --dirty --format agent` clean. `composer types:check` shows only the 3 pre-existing, unrelated Larastan findings (no new findings in any file this plan touched).

---

_Phase: 07-accounts-receivable_
_Completed: 2026-09-09_

## Self-Check: PASSED

All 7 claimed files found on disk; all 3 task commit hashes (`537bf02`, `32b3833`, `832fce5`) found in git log.
