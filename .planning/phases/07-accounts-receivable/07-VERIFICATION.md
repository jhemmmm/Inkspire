---
phase: 07-accounts-receivable
verified: 2026-09-09T00:00:00Z
status: gaps_found
score: 3/4 roadmap success criteria fully verified; AR-04 fails on write-off closure integrity (new fault line)
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: "3/4 (3.5/4 substantive) roadmap success criteria"
  gaps_closed:
    - "An approved write-off never leaving the Owner's queue (old CR-02) -- approve() now nulls write_off_requested_at and index() additionally excludes paid/written_off rows; verified by direct code read and passing WriteOffApprovalTest.php cases"
    - "The still-live Reject button erasing an approved write-off's reason/requester/timestamp (old CR-01) -- reject() now aborts 422 when collection_status is already WrittenOff; verified by direct code read and passing test"
    - "A written-off job order remaining cancellable/payable (old CR-03) -- CancellationController::store and PaymentController::store both now abort 422 for PaymentStatus::WrittenOff, and Dashboard.vue's canCancelJobOrder() hides the Cancel action; verified by direct code read and passing tests"
  gaps_remaining:
    - "AR-04 write-off closure integrity is still not achieved -- two NEW, independently-reachable defects on the same fault line replace the three that were closed: (1) WriteOffApprovalController::approve() guards only the denormalized collection_status flag, not the derived balance, so a job order paid in cash/bank-transfer/PayMongo minutes before an Owner's approval click can still be booked as a written-off loss, since no payment path writes collection_status (only the daily ar:send-reminders cron does); (2) CreditRequestController::store -- the third writer of payment_status -- has no WrittenOff guard, so one POST flips a written-off job order back to credit_pending_approval and creates a second, duplicate AccountsReceivable row for the same job order, silently reinstating the loss as a live receivable if the Owner then approves it."
  regressions: []
gaps:
  - truth: "Owner can approve a write-off of an AR balance, closing it as a stable, correct terminal state (AR-04 / phase goal's 'closes ... by an Owner-approved write-off')"
    status: failed
    reason: "07-06 correctly closed the three previously-identified gaps (queue not clearing, reject erasing an approved entry, written-off order remaining cancellable/payable), confirmed by direct code read and passing regression tests. But a fresh code review found, and this verification independently confirmed by reading the code, two new defects on the exact same fault line -- the write-off's terminal state is still not durable end-to-end."
    artifacts:
      - path: "app/Http/Controllers/Owner/WriteOffApprovalController.php"
        issue: "approve() (lines 90-105) guards only in_array($accountsReceivable->collection_status, [Paid, WrittenOff]) -- a denormalized flag that no payment path (PaymentController::store, ConfirmPaymentIntent) writes; only the daily ar:send-reminders cron sets collection_status=paid. A job order paid via cash/bank-transfer/PayMongo while a write-off request is pending can still be approved and marked written_off up to 24h later, overwriting payment_status from Paid to WrittenOff and booking a settled sale as a loss."
      - path: "app/Http/Controllers/Cashier/CreditRequestController.php"
        issue: "store() (lines 29-47, repeated in the locked re-read at lines 57-63) checks payment_status against Paid and [PendingConfirmation, CreditPendingApproval] only -- WrittenOff passes both checks. One POST to job-orders.credit-request.store flips payment_status from written_off to credit_pending_approval and creates a second AccountsReceivable row for the same job order while the first stays collection_status=written_off, silently duplicating the receivable and letting the Owner reinstate the booked loss as live credit by approving it."
      - path: "app/Http/Controllers/Cashier/PaymentController.php"
        issue: "edit() (lines 35-49) guards only cancelled_at and status, never payment_status -- the Pricing + Payment page (including the On-Credit dialog wired to CreditRequestController::store) still renders in full for a written-off job order, reachable via a plain bookmarkable GET."
    missing:
      - "approve() must guard on the derived outstanding balance (the same computation index() already performs), not just the collection_status flag, inside the locked transaction"
      - "CreditRequestController::store must abort_if payment_status === WrittenOff, both in the initial check and inside its locked re-read, mirroring the guard shape 07-06 already used in CancellationController/PaymentController"
      - "PaymentController::edit must also guard on payment_status === WrittenOff so the page 422s instead of rendering a dead/exploitable form"
      - "A regression test that records a real payment (via the payment route, not forceFill on collection_status) against an on_credit job order with a pending write-off, then asserts approve() returns 422 and payment_status stays Paid"
      - "A regression test asserting a written-off job order cannot be POSTed to job-orders.credit-request.store (AccountsReceivable::count() unchanged, payment_status unchanged)"
human_verification: []
---

# Phase 7: Accounts Receivable Verification Report

**Phase Goal:** An On-Credit balance is tracked from creation through aging, escalating reminders, collections, and Owner-approved write-off — the accounting follow-through on Phase 5's credit path.
**Verified:** 2026-09-09
**Status:** gaps_found
**Re-verification:** Yes — after gap-closure plan 07-06 (commits `293059c`, `c98c34c`)

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Accounting Staff can view outstanding balances grouped into aging brackets — AR-01 | ✓ VERIFIED (regression check) | No files supporting this truth (`AccountsReceivableController.php`, aging enum, `Index.vue`/`Show.vue`) appear in 07-06's `key-files` list or git history since the prior verification; confirmed via `git log` that the last commits touching `AccountsReceivableController.php` predate 07-06. Prior verification's direct-read findings stand unchanged. |
| 2 | System automatically sends escalating reminder notifications as an AR entry crosses each aging bracket — AR-02 | ✓ VERIFIED (regression check) | `SendAccountsReceivableReminders.php` untouched by 07-06 (confirmed via `git log`). Prior verification's findings stand unchanged (including its noted non-blocking WR-02/WR-03/WR-04 gaps, still present, still non-blocking to the core truth). |
| 3 | Accounting Staff can update an AR entry's collection status and generate a printable collection letter — AR-03 | ✓ VERIFIED (regression check, with a real but non-blocking gap) | `CollectionStatusController.php`/`CollectionLetterController.php` untouched by 07-06. Prior verification's findings stand unchanged, including the still-open, non-blocking WR-05 (collection letter renders for already-closed entries via direct URL). |
| 4 | Owner can approve a write-off of an AR balance, closing it as a stable, correct terminal state — AR-04 (phase goal's "closes ... by an Owner-approved write-off") | ✗ FAILED | 07-06 genuinely closed all three previously-identified defects (verified below). But two new, independently-reachable defects on the same fault line were found by a fresh code review and independently confirmed here by reading the code directly: (a) `WriteOffApprovalController::approve()` guards the denormalized `collection_status` flag, not the derived balance — no payment path writes `collection_status`, only the daily `ar:send-reminders` cron does, so a job order paid in the last 24h can still be approved as a write-off, overwriting `payment_status` from `Paid` to `WrittenOff`; (b) `CreditRequestController::store` — the third writer of `payment_status`, never touched by 07-06 — has no `WrittenOff` guard, so one POST reverses a written-off job order back to `credit_pending_approval` and creates a duplicate `AccountsReceivable` row. Both are reachable through existing routes with no new UI needed (a plain POST/PATCH), and neither is covered by a regression test — the existing "settled while pending" test simulates settlement via `forceFill(['collection_status' => Paid])` rather than a real payment, confirmed by reading the test at lines 135-152. |

**Score:** 3/4 roadmap success criteria fully verified; AR-04 fails on write-off closure integrity for a second time, on a new fault line.

### Gap Closure Verification (Re-verification Focus)

Each of the three previously-reported gaps was independently re-verified by reading the current code, not by trusting `07-06-SUMMARY.md` or `07-REVIEW.md`'s claims:

| Old Gap | Fix Claimed | Independently Confirmed | Evidence |
|---|---|---|---|
| Approved write-off never leaves Owner's queue (old CR-02) | `approve()` nulls `write_off_requested_at`; `index()` excludes `paid`/`written_off` | ✓ CLOSED | `WriteOffApprovalController.php:100-103` — `forceFill(['collection_status' => WrittenOff, 'write_off_requested_at' => null])`; `index()` line 29 — `whereNotIn('collection_status', [Paid, WrittenOff])`. Test `WriteOffApprovalTest.php:102-115` passing (re-ran: PASS). |
| Stale Reject button erases an approved write-off (old CR-01) | `reject()` aborts 422 if `collection_status === WrittenOff` | ✓ CLOSED | `WriteOffApprovalController.php:127-132` — `abort_if($accountsReceivable->collection_status === WrittenOff, 422, ...)` runs before the nulling write. Test `WriteOffApprovalTest.php:117-133` passing (re-ran: PASS). |
| Written-off job order stays cancellable/payable (old CR-03) | `CancellationController`/`PaymentController::store` treat `WrittenOff` as terminal; `Dashboard.vue` hides Cancel | ✓ CLOSED | `PaymentController.php:108` — `abort_if($jobOrder->payment_status === PaymentStatus::WrittenOff, 422, ...)`. `CancellationController.php` confirmed to carry the equivalent third `abort_if` (per 07-06-SUMMARY and grep, matches pattern used elsewhere). Tests `RecordPaymentTest.php:245-259`, `CancellationFeeTest.php:147-157` passing (re-ran both files: PASS, 32/32 total across the three touched test files). |

**No regressions found** — the three closed gaps stay closed under direct re-read; the fix for CR-03 additionally correctly excludes `WrittenOff` from the Cashier dashboard's Cancel action via the new `canCancelJobOrder()` helper (confirmed by reading `Dashboard.vue`'s per-07-06-SUMMARY key-files list; not independently re-read line-by-line but consistent with the passing `CancellationFeeTest.php`).

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Http/Controllers/Owner/WriteOffApprovalController.php` | Owner approve/reject, stable terminal state | ⚠️ PARTIAL | `approve()`/`reject()` correctly implement the CR-01/CR-02 fixes from 07-06 (confirmed). But `approve()`'s settlement guard checks the wrong signal (`collection_status`, not derived balance) — a new closure-integrity gap. |
| `app/Http/Controllers/Cashier/CancellationController.php` | Terminal-state guard includes `WrittenOff` | ✓ VERIFIED | Confirmed present per 07-06-SUMMARY and passing `CancellationFeeTest.php` (`a written-off job order cannot be cancelled` test passes). |
| `app/Http/Controllers/Cashier/PaymentController.php` | Terminal-state guard includes `WrittenOff` | ⚠️ PARTIAL | `store()` correctly guards `WrittenOff` (line 108, confirmed). `edit()` does not (lines 35-49 checked directly — no `payment_status` guard at all), so the payment page still renders for a written-off order. |
| `app/Http/Controllers/Cashier/CreditRequestController.php` | Third `payment_status` writer, should treat `WrittenOff` as terminal | ✗ MISSING GUARD | Confirmed by direct read: `store()` (lines 29-47, repeated 57-63) checks only `Paid` and `[PendingConfirmation, CreditPendingApproval]` — `WrittenOff` is absent from both checks in both the unlocked pre-check and the locked re-read. Not in 07-06's scope (`key-files` list does not include this file). |
| `resources/js/pages/cashier/Dashboard.vue` | Cancel action hidden for `written_off` | ✓ VERIFIED (per SUMMARY + passing test) | `canCancelJobOrder()` helper added per 07-06-SUMMARY; consistent with passing `CancellationFeeTest.php`. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `WriteOffApprovalController::approve` | Real settlement state | Derived outstanding balance (total − completed transactions) | ✗ NOT WIRED | `approve()` only checks the denormalized `collection_status` column, never recomputes the balance the way `index()` already does at lines 40-42. No payment-recording path (`PaymentController::store`, PayMongo webhook confirmation) writes `collection_status` — only the daily `ar:send-reminders` cron does. |
| `PaymentStatus::WrittenOff` | `CreditRequestController::store` | terminal-state `abort_if` | ✗ NOT WIRED | Confirmed by direct read — no such guard exists in either the pre-check or the locked re-read. |
| `PaymentStatus::WrittenOff` | `PaymentController::edit` | terminal-state `abort_if` | ✗ NOT WIRED | Confirmed by direct read — `edit()` only checks `cancelled_at` and `status`. |
| `PaymentStatus::WrittenOff` | `PaymentController::store`, `CancellationController::store` | terminal-state `abort_if` | ✓ WIRED | Confirmed by direct read (line 108 in `PaymentController.php`; equivalent guard in `CancellationController.php` per SUMMARY + passing test). |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Full test suite passes | `php artisan test --compact` (re-run independently) | `{"tests":443,"passed":440,"assertions":2089,"skipped":3}` | ✓ PASS (matches claimed figures, independently re-run) |
| 07-06's targeted regression tests pass | `php artisan test --compact tests/Feature/Owner/WriteOffApprovalTest.php tests/Feature/Cashier/CancellationFeeTest.php tests/Feature/Cashier/RecordPaymentTest.php` | `{"tests":32,"passed":32}` | ✓ PASS |
| `CreditRequestController::store` guards against `WrittenOff` | `grep -n "PaymentStatus::" app/Http/Controllers/Cashier/CreditRequestController.php` | Only `Paid`, `PendingConfirmation`, `CreditPendingApproval` referenced; no `WrittenOff` | ✗ FAIL (confirms new CR-02 / review's CR-02) |
| `WriteOffApprovalController::approve()` guards on real settlement, not just a flag | direct code read of lines 90-105 | Only `collection_status` checked, no balance recomputation | ✗ FAIL (confirms new CR-01 / review's CR-01) |
| No dedicated feature test exists for `job-orders.credit-request.store` guard behavior against a written-off order | `grep -rl "credit-request" tests/Feature/` | No `CreditRequestTest.php` file exists; route only referenced incidentally in 2 unrelated test files | ✗ FAIL — confirms the gap is untested |
| No debt markers in newly-relevant files | `grep -n "TBD\|FIXME\|XXX\|TODO\|HACK\|PLACEHOLDER"` across `CreditRequestController.php`, `PaymentController.php`, `WriteOffApprovalController.php`, `CancellationController.php` | no matches | ✓ PASS |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| AR-01 | 07-01 (substrate), 07-02 | Aging brackets view | ✓ SATISFIED | Unchanged since prior verification; regression-checked, files untouched by 07-06 |
| AR-02 | 07-01 (substrate), 07-03 | Escalating reminders | ✓ SATISFIED | Unchanged since prior verification; regression-checked, files untouched by 07-06 |
| AR-03 | 07-01 (substrate), 07-04 | Collection status + printable letter | ✓ SATISFIED (WR-05 gap still open, non-blocking) | Unchanged since prior verification |
| AR-04 | 07-01 (substrate), 07-05, 07-06 (gap closure) | Owner-approved write-off | ✗ BLOCKED | 07-06 closed the three previously-reported defects, but two new defects on the identical fault line (settlement-guard correctness; third `payment_status` writer never hardened) mean the write-off is still not a stable, correct terminal state |

No orphaned requirements — all four AR-01..AR-04 IDs declared across plan frontmatter (07-01 through 07-06) match `.planning/REQUIREMENTS.md`'s Phase 7 mapping exactly.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `app/Http/Controllers/Owner/WriteOffApprovalController.php` | 93-98 | Terminal-state guard checks a denormalized flag (`collection_status`) instead of the authoritative derived value (outstanding balance) that the same controller's `index()` already computes correctly | 🛑 Blocker | A job order paid within the last ~24h (before the daily cron reconciles `collection_status`) can be booked as a write-off loss, silently reversing a real, completed sale |
| `app/Http/Controllers/Cashier/CreditRequestController.php` | 42-47, 58-63 | New terminal enum case (`PaymentStatus::WrittenOff`, added in 07-05) not taught to a third writer of the same column | 🛑 Blocker | A written-off job order can be put back on credit via a single POST, reversing "This can't be undone" and duplicating the `AccountsReceivable` row for the same job order |
| `app/Http/Controllers/Cashier/PaymentController.php` | 35-49 | `edit()` missing the terminal-state guard its own `store()` (same class) now has | ⚠️ Warning | The payment page (including the On-Credit dialog that enables the blocker above) still renders for a written-off job order via a bookmarkable GET |
| `tests/Feature/Owner/WriteOffApprovalTest.php` | 135-152 | Test fakes the effect (`collection_status` flag) rather than the cause (a real payment) it claims to guard against | ⚠️ Warning | Gives false confidence that the settlement race is covered; it is not |
| `app/Http/Controllers/AccountingStaff/CollectionLetterController.php` | 24 | Missing `collection_status` guard alongside `status` guard (carried over, unchanged since prior verification) | ⚠️ Warning | Dunning letter still renders for settled/written-off entries via direct URL (WR-05) |

No unresolved `TBD`/`FIXME`/`XXX` debt markers found in phase-modified files.

### Human Verification Required

None. All findings above were verified directly by reading source files, re-running the test suite, and cross-checking against the fresh code review; no visual/UX/real-time behavior needed human judgment.

### Gaps Summary

07-06 did genuinely close all three previously-reported defects (approved write-offs leaving the queue; the stale Reject button; cancel/pay on a written-off order) — confirmed independently here, not just by trusting the SUMMARY. The full Pest suite is green (440 passed, 3 skipped, 0 failed), matching the claim.

However, AR-04 ("Owner can approve a write-off of an AR balance," and the phase goal's "closes ... by an Owner-approved write-off") is still not achieved. A fresh code review found, and this verification independently confirmed by reading the code, two new defects on the exact same fault line the 07-06 fix was scoped to:

1. **`WriteOffApprovalController::approve()` trusts a stale flag instead of the real balance.** `collection_status` is only ever set to `paid` by the daily `ar:send-reminders` cron — no payment-recording path writes it. So a job order paid in cash, by bank transfer, or via a confirmed PayMongo webhook can still be approved for write-off up to 24 hours later, overwriting `payment_status` from `Paid` to `WrittenOff` and booking a settled sale as a bad-debt loss. The one test that looks like it covers this (`WriteOffApprovalTest.php:135-152`) simulates settlement by directly `forceFill`-ing `collection_status`, not by recording a real payment — confirmed by reading the test.

2. **`CreditRequestController::store` — the third writer of `payment_status`, never touched by 07-06 — has no `WrittenOff` guard.** One POST to the existing `job-orders.credit-request.store` route flips a written-off job order's `payment_status` back to `credit_pending_approval` and creates a second, duplicate `AccountsReceivable` row while the first stays `collection_status = written_off`. If the Owner then approves the new request, the booked loss is silently reinstated as a live receivable with a fresh aging clock. `PaymentController::edit` also lacks any `payment_status` guard, so the page hosting the On-Credit dialog that triggers this still renders in full for a written-off order via a plain bookmarkable GET.

Both are reachable through existing routes with no new UI required, neither is covered by a regression test, and both directly contradict the phase goal's implicit contract that a write-off is a durable, correct terminal state. This is not a new architectural problem — it is the same class of gap (a new terminal enum case not taught to every existing writer/reader of the column it terminates) that 07-06 was created to close, just on a different set of writers. AR-04 is assessed as FAILED for a second time.

**This looks like a well-scoped, mechanical follow-up, not a re-architecture** — the same guard-clause pattern 07-06 already established (`abort_if($x->payment_status === PaymentStatus::WrittenOff, 422, ...)`) needs to be applied to `CreditRequestController::store` (both the pre-check and the locked re-read) and `PaymentController::edit`, and `WriteOffApprovalController::approve()`'s settlement guard needs to check the derived balance rather than the denormalized flag, mirroring the computation `index()` already performs. Recommend routing this back through `/gsd-plan-phase --gaps` for a second closure plan before Phase 8 begins, since Phase 8's reporting explicitly reads `payment_status`/AR data as ground truth.

---

*Verified: 2026-09-09*
*Verifier: Claude (gsd-verifier)*
