---
phase: 07-accounts-receivable
verified: 2026-09-09T07:00:00Z
status: gaps_found
score: 3/4 roadmap success criteria fully verified; AR-04 (and its adjacent write-off-integrity fault line) still not fully stable
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: "3/4 roadmap success criteria; AR-04 failed on write-off closure integrity (round 2 fault line)"
  gaps_closed:
    - "Settlement race in WriteOffApprovalController::approve() — approve() now recomputes the derived outstanding balance on a locked JobOrder re-read (not just the lag-prone collection_status flag) and aborts 422 if it's already <= 0. Verified by direct code read (lines 106-113) and by independently re-running the new regression test, which records a REAL cash payment through the payment route (not a forceFill) and asserts the subsequent approve() call 422s."
    - "CreditRequestController::store() missing WrittenOff guard — both the unlocked pre-check (line 43) and the locked re-read (line 60) now abort_if payment_status === WrittenOff. Verified by direct code read and by independently re-running the new CreditRequestTest.php, which POSTs to the real route and asserts 422, AccountsReceivable::count() === 0, and payment_status unchanged."
    - "PaymentController::edit() missing the terminal-state guard its own store() carried — edit() now aborts 422 for WrittenOff (line 50), reusing store()'s exact message. Verified by direct code read and the extended RecordPaymentTest.php assertion."
  gaps_remaining:
    - "AR-04's 'stable, correct terminal state' claim is still not fully achieved. A fresh, independently-confirmed code review found four further defects on the identical writer/reader fault line that motivated the last two gap-closure rounds, none of which are in 07-07's closed scope: (1) CollectionStatusController::update() — the only mutating AR controller with no DB::transaction()/lockForUpdate() — can silently revert an already-written-off entry's collection_status back to an open value on a stale, unlocked read, resuming reminder escalation for a booked loss (CR-04, new); (2) CollectionLetterController::show() has no collection_status guard (its two sibling AR controllers both have one), so a demand/final-notice letter renders in full for an already-paid or already-written-off entry via a bookmarkable GET (CR-03, new); (3) a real cash/bank/PayMongo payment recorded while a Phase-5 credit REQUEST (not a write-off request) is pending is silently reversed back to on_credit when the Owner later approves it, because neither PaymentController::store() nor CreditApprovalController::approve() guard PaymentStatus::CreditPendingApproval (CR-01, new — same bug class as the settlement race 07-07 closed for write-off, unaudited for credit approval); (4) the Cashier Dashboard's action dropdown has no branch for on_credit/credit_pending_approval/credit_rejected, so an approved On-Credit balance has zero reachable UI path to be paid off — the only reachable terminal state for any receivable today is write-off (CR-02, new, pre-existing since Phase 5 but never revisited by Phase 7's AR work)."
  regressions: []
gaps:
  - truth: "Owner-approved write-off closes an AR balance as a stable, correct terminal state that cannot be silently reversed by any other AR surface (AR-04 / phase goal's 'closes ... by an Owner-approved write-off')"
    status: failed
    reason: "07-07 correctly closed its declared scope (settlement race in approve(), missing WrittenOff guard in CreditRequestController, missing guard in PaymentController::edit()) — confirmed independently by direct code read and by re-running the targeted regression tests (20/20 pass). But a fresh code review found, and this verification independently confirmed by reading the code, that CollectionStatusController::update() — an AR-03 feature, untouched by any of the three write-off gap-closure rounds — is the only mutating controller in this phase with no locked re-read. A concurrent write-off approval landing between page load and this update's write is silently reversed: the entry's collection_status flips back to an open value, reminders resume via ar:send-reminders' whereNotIn('collection_status', [paid, written_off]) filter, and the two columns (job_orders.payment_status = written_off vs. accounts_receivable.collection_status = something open) permanently disagree with no reconciling code path. This is the same class of bug (missing locked re-read before a mutating write) that CR-04/WR-07 in earlier rounds were created to close, just on a controller none of the three prior rounds touched."
    artifacts:
      - path: "app/Http/Controllers/AccountingStaff/CollectionStatusController.php"
        issue: "update() (lines 22-38) has no DB::transaction()/lockForUpdate() — confirmed by direct read, zero occurrences of either in the file. Both guards (status === Active, collection_status not in [Paid, WrittenOff]) evaluate the route-model-bound instance from the request's own unlocked SELECT, with no re-read before the forceFill()->save() write."
      - path: "app/Http/Controllers/AccountingStaff/CollectionLetterController.php"
        issue: "show() (lines 22-24) guards only status === Active — confirmed by direct read, no collection_status check exists, unlike its two AR-controller siblings (CollectionStatusController, WriteOffRequestController) which both check in_array(collection_status, [Paid, WrittenOff]). A written-off entry's status column never changes (only collection_status and the job order's payment_status do — confirmed by reading WriteOffApprovalController::approve()), so this guard passes and a full-face-value demand/final-notice letter renders for an already-booked loss."
    missing:
      - "CollectionStatusController::update() needs the same DB::transaction() + lockForUpdate() re-read boundary WriteOffApprovalController/CreditRequestController already established, with both existing guards re-checked against the freshly-locked row before the write"
      - "A regression test that updates collection_status to written_off inside the request lifecycle (simulating the interleaving), then asserts the original update() call is rejected rather than silently succeeding — the existing CollectionStatusTest.php only covers the case where the terminal state is already set before the request begins, not a race"
      - "CollectionLetterController::show() needs the same in_array(collection_status, [Paid, WrittenOff]) guard its two siblings already carry"
  - truth: "An On-Credit balance can be paid down and closed through the existing Cashier POS flow, not solely through Owner-approved write-off (D-15/D-16's premise, and the phase goal's implicit 'tracked ... through' full lifecycle)"
    status: failed
    reason: "This is not one of AR-01..AR-04's four literal checklist items, and Phase 7's own CONTEXT explicitly scoped 'recording payments' out of this phase ('Phase 5's PaymentController is the single money-entry path — this phase reads transactions, never writes them'). Weighed carefully against that scoping: the defect is nonetheless real, independently confirmed, and directly contradicts this phase's own on-screen claims and its foundational design decisions (D-15: 'An AR balance is paid down through the existing Cashier POS flow'; D-16: 'a Cashier payment shrinks the AR entry with zero AR-side code'). Both decisions assume the payment UI path already works; it does not. AccountsReceivable/Show.vue tells Accounting Staff 'Payments are recorded at the Cashier counter' — that statement is false for every on_credit job order today. Verdict: this is a genuine, confirmed gap that undermines the phase goal's 'tracked ... through ... collections' arc (an entry can be marked Collections/Follow-up by Accounting, but the customer literally cannot be made to pay through this system's UI), even though it predates Phase 7 and is adjacent to, not squarely inside, AR-01..AR-04's text. Recommend closing it as part of the next gap-closure round rather than deferring it silently, since Phase 8's reporting will otherwise show every receivable resolving via write-off, never via payment — a misleading picture of the business."
      artifacts:
      - path: "resources/js/pages/cashier/Dashboard.vue"
        issue: "The action-dropdown v-if/v-else-if chain (lines 378-429) branches only on payment_status === 'unpaid' | 'partially_paid' (Process Payment), 'pending_confirmation' (Check Payment Status), and 'paid' (View Receipt). There is no branch for 'on_credit', 'credit_pending_approval', or 'credit_rejected' — confirmed by direct read of the full dropdown block. PaymentController::edit() is only ever linked from this one dropdown chain (confirmed by grep across resources/js) so an approved On-Credit job order has zero reachable link to the payment page anywhere in the Cashier portal; only 'Cancel Job Order' remains available if the order is still cancellable."
      - path: "app/Http/Controllers/Owner/CreditApprovalController.php"
        issue: "approve() (lines 42-70) re-reads and locks the AccountsReceivable row but never re-reads or re-checks the job order's payment_status. Combined with PaymentController::store() (lines 93-109) guarding only Paid and WrittenOff — not CreditPendingApproval — a real cash/bank/PayMongo payment recorded while a credit request is pending is silently overwritten back to on_credit when the Owner approves the (now-stale) request, reopening a fully-paid balance as a live receivable."
    missing:
      - "A payment action reachable from the Cashier Dashboard for on_credit (and ideally credit_rejected) job orders, linking to PaymentController::edit(), so 'collections' has a functioning payment closure path and D-15/D-16's premise is actually true"
      - "A guard in PaymentController::store()/edit() for PaymentStatus::CreditPendingApproval (mirroring the WrittenOff guard's shape), and a re-check of the job order's settlement state inside CreditApprovalController::approve()'s locked transaction, so a payment landing during the pending-approval window cannot be silently reversed"
human_verification: []
---

# Phase 7: Accounts Receivable Verification Report

**Phase Goal:** An On-Credit balance is tracked from creation through aging, escalating reminders, collections, and Owner-approved write-off — the accounting follow-through on Phase 5's credit path.
**Verified:** 2026-09-09
**Status:** gaps_found
**Re-verification:** Yes — after gap-closure plan 07-07 (round 2 write-off closure integrity)

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Accounting Staff can view outstanding balances grouped into aging brackets — AR-01 | ✓ VERIFIED | `AccountsReceivableController::index()` (read directly) groups Active entries into open/closed sets, buckets the open set into six `AccountsReceivableAgingBracket` cases with per-bracket totals/counts, and renders `accounting-staff/AccountsReceivable/Index`. Unchanged by 07-07 (not in its `key-files` list); no regression found. |
| 2 | System automatically sends escalating reminder notifications as an AR entry crosses each aging bracket — AR-02 | ✓ VERIFIED | `SendAccountsReceivableReminders.php` (read directly) — daily command, idempotent `last_reminder_bracket` rank comparison, sends to Accounting Staff + Owner only (D-06), never the customer. Untouched by 07-06/07-07. Non-blocking pre-existing gaps (WR-01 failed-send stamping, WR-02/WR-03 column/type issues) remain open but do not block this truth. |
| 3 | Accounting Staff can update an AR entry's collection status and generate a printable collection letter — AR-03 | ✓ VERIFIED (with confirmed, non-regressed but still-open integrity gaps) | `CollectionStatusController`/`CollectionLetterController` both exist and function for the primary path (tested, passing). But independently confirmed: `CollectionStatusController::update()` has no locked re-read (can race a concurrent write-off — see Gap 1) and `CollectionLetterController::show()` has no terminal-`collection_status` guard (letter prints for closed entries — see Gap 1). Both are real defects that this verification confirmed by reading the code, not by trusting the review. |
| 4 | Owner can approve a write-off of an AR balance, closing it as a stable, correct terminal state — AR-04 (phase goal's "closes ... by an Owner-approved write-off") | ✗ FAILED | 07-07 closed its own declared scope correctly (verified below, 20/20 targeted tests pass, confirmed by direct code read of all three touched files). But `CollectionStatusController::update()`'s missing locked re-read (Gap 1) means an approved write-off is not durably terminal — a subsequent (even accidental) collection-status update can silently revert it, resuming reminder escalation for a booked loss with no reconciling path. |

**Score:** 3/4 roadmap success criteria fully verified; AR-04 fails for a third time, on a fault line adjacent to but distinct from the two prior rounds.

### 07-07 Gap Closure Verification (Re-verification Focus)

Each of the three gaps 07-07 was scoped to close was independently re-verified by reading the current code and re-running the targeted tests myself, not by trusting `07-07-SUMMARY.md`:

| Gap (from prior VERIFICATION.md) | Fix Claimed | Independently Confirmed | Evidence |
|---|---|---|---|
| Settlement race: `approve()` trusted `collection_status`, not the derived balance | Locked `JobOrder` re-read + `outstandingBalance` computation, `abort_if(outstandingBalance <= 0.0, ...)` | ✓ CLOSED | `WriteOffApprovalController.php:106-113` read directly — locked re-read inside the SAME `DB::transaction()`, matches `index()`'s computation exactly. New test (`WriteOffApprovalTest.php`, "approving a write-off fails when a real payment settled the job order while the request was pending") records a real Cash payment via `cashier.job-orders.payment.store`, then asserts `owner.write-off-requests.approve` returns 422. Re-ran: PASS. |
| `CreditRequestController::store()` had no `WrittenOff` guard | `abort_if(payment_status === WrittenOff, ...)` added in both the unlocked pre-check and locked re-read | ✓ CLOSED | `CreditRequestController.php:43` (pre-check) and `:60` (locked re-read), both read directly. New file `tests/Feature/Cashier/CreditRequestTest.php` POSTs to the real route on a `written_off` job order, asserts 422, `AccountsReceivable::count() === 0`, `payment_status` unchanged. Re-ran: PASS. |
| `PaymentController::edit()` missing the guard `store()` already had | `abort_if(payment_status === WrittenOff, ...)` added to `edit()`, reusing `store()`'s exact message | ✓ CLOSED | `PaymentController.php:50` read directly, message string identical to `store()`'s (line 109). `RecordPaymentTest.php`'s extended written-off test now checks `edit()` first. Re-ran: PASS. |

**Full suite independently re-run:** `php artisan test --compact` → `{"tests":445,"passed":442,"assertions":2098,"skipped":3}` — matches the claimed figures exactly, zero failures. Targeted re-run of `WriteOffApprovalTest.php` + `CreditRequestTest.php` + `RecordPaymentTest.php`: 20/20 passing.

**No regressions found in 07-07's own scope.** However, this re-verification went beyond regression-checking 07-07's scope (per the task's instruction to weigh the fresh `07-REVIEW.md` findings) and independently confirmed four further defects — see Gaps below.

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Http/Controllers/Owner/WriteOffApprovalController.php` | Owner approve/reject, stable terminal state, derived-balance guard | ✓ VERIFIED | `approve()`/`reject()` both confirmed correct by direct read; derived-balance guard present and authoritative (lines 106-113). |
| `app/Http/Controllers/Cashier/CreditRequestController.php` | Terminal-state guard for `WrittenOff` in both pre-check and locked re-read | ✓ VERIFIED | Confirmed present at lines 43 and 60. |
| `app/Http/Controllers/Cashier/PaymentController.php` | Terminal-state guard for `WrittenOff` in both `store()` and `edit()` | ✓ VERIFIED (for `WrittenOff`) / ⚠️ PARTIAL (for `CreditPendingApproval`) | `WrittenOff` guarded in both methods (lines 50, 109). Neither method guards `CreditPendingApproval` — a payment can be recorded while a credit request is pending (CR-01, new finding, see Gap 2). |
| `app/Http/Controllers/AccountingStaff/CollectionStatusController.php` | Locked mutation, cannot resurrect a closed entry | ✗ MISSING GUARD | No `DB::transaction()`/`lockForUpdate()` anywhere in the file — confirmed by direct read. Guards evaluate an unlocked, request-time-bound row. |
| `app/Http/Controllers/AccountingStaff/CollectionLetterController.php` | Terminal-state guard preventing letters for closed entries | ✗ MISSING GUARD | Only guards `status === Active`; no `collection_status` check — confirmed by direct read, unlike both AR-controller siblings. |
| `resources/js/pages/cashier/Dashboard.vue` | Reachable payment action for `on_credit` job orders | ✗ MISSING | Action dropdown has no branch for `on_credit`/`credit_pending_approval`/`credit_rejected` — confirmed by direct read of the full v-if/v-else-if chain (lines 378-429). |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `WriteOffApprovalController::approve` | Real settlement state | Derived outstanding balance, locked re-read | ✓ WIRED | Confirmed by direct read; test independently re-run and passing. |
| `CreditRequestController::store` | `PaymentStatus::WrittenOff` | `abort_if` terminal-state guard (pre-check + locked re-read) | ✓ WIRED | Confirmed by direct read; test independently re-run and passing. |
| `PaymentController::edit` | `PaymentStatus::WrittenOff` | `abort_if` terminal-state guard | ✓ WIRED | Confirmed by direct read; test independently re-run and passing. |
| `CollectionStatusController::update` | A locked, re-read `AccountsReceivable` row | `DB::transaction()` + `lockForUpdate()` | ✗ NOT WIRED | Confirmed by direct read — no such boundary exists anywhere in the file. |
| `CollectionLetterController::show` | `collection_status` terminal check | `abort_if`/`abort_unless` guard | ✗ NOT WIRED | Confirmed by direct read — only `status` is checked. |
| Cashier Dashboard action dropdown | `PaymentController::edit` | `on_credit` branch in the v-if chain | ✗ NOT WIRED | Confirmed by direct read — no branch exists; `PaymentController.edit` is unreachable from the UI for this status. |
| `CreditApprovalController::approve` | Job order's current `payment_status` | Locked re-read + settlement guard | ✗ NOT WIRED | Confirmed by direct read — `approve()` only re-reads/locks the `AccountsReceivable` row, never the job order. |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Full test suite passes | `php artisan test --compact` (re-run independently) | `{"tests":445,"passed":442,"assertions":2098,"skipped":3}` | ✓ PASS (matches claimed figures) |
| 07-07's targeted regression tests pass | `vendor/bin/pest tests/Feature/Owner/WriteOffApprovalTest.php tests/Feature/Cashier/CreditRequestTest.php tests/Feature/Cashier/RecordPaymentTest.php` | `{"tests":20,"passed":20,"assertions":111}` | ✓ PASS |
| `CollectionStatusController::update()` has no locking | `grep -n "lockForUpdate\|DB::transaction" app/Http/Controllers/AccountingStaff/CollectionStatusController.php` | no matches | ✗ FAIL (confirms CR-04) |
| `CollectionLetterController::show()` guards `collection_status` | direct code read of lines 22-24 | only `status === Active` checked | ✗ FAIL (confirms CR-03) |
| Cashier Dashboard offers a payment action for `on_credit` | direct read of the action-dropdown v-if chain | no `on_credit` branch found | ✗ FAIL (confirms CR-02) |
| `PaymentController::store()`/`CreditApprovalController::approve()` guard `CreditPendingApproval` | direct code read | neither guards it | ✗ FAIL (confirms CR-01) |
| No debt markers in phase-relevant files | `grep -n "TBD\|FIXME\|XXX\|TODO\|HACK\|PLACEHOLDER"` across all 8 controllers/Dashboard.vue touched by this analysis | no matches | ✓ PASS |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| AR-01 | 07-01 (substrate), 07-02 | Aging brackets view | ✓ SATISFIED | Confirmed by direct read of `AccountsReceivableController::index()`; unchanged since prior verification. |
| AR-02 | 07-01 (substrate), 07-03 | Escalating reminders | ✓ SATISFIED | Confirmed by direct read of `SendAccountsReceivableReminders.php`; unchanged since prior verification. |
| AR-03 | 07-01 (substrate), 07-04 | Collection status + printable letter | ✓ SATISFIED (with two confirmed, non-blocking-to-the-checklist-item but real integrity gaps: CR-03, CR-04) | Core feature works and is tested; the two gaps affect AR-04's terminal-state durability more than AR-03's own checklist wording. |
| AR-04 | 07-01 (substrate), 07-05, 07-06, 07-07 (gap closures) | Owner-approved write-off | ✗ BLOCKED | 07-07 closed its declared scope (confirmed). CR-04's missing locked re-read in a sibling AR-03 controller means an approved write-off is not yet durably terminal. |

No orphaned requirements — all four AR-01..AR-04 IDs declared across plan frontmatter (07-01 through 07-07) match `.planning/REQUIREMENTS.md`'s Phase 7 mapping exactly. (Note: `.planning/REQUIREMENTS.md`'s own checkboxes show AR-01/02/03 unchecked and AR-04 checked — this is a stale tracking artifact in that file, not evidence of anything; it was not updated to reflect 07-02/07-03/07-04's completion and should not be read as a phase-status signal.)

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `app/Http/Controllers/AccountingStaff/CollectionStatusController.php` | 22-38 | Mutating controller with no `DB::transaction()`/`lockForUpdate()` — the only one in the phase's AR-mutation surface without it | 🛑 Blocker | A concurrent write-off approval can be silently reversed by a routine collection-status update, un-terminating a booked loss with no reconciling path |
| `app/Http/Controllers/AccountingStaff/CollectionLetterController.php` | 22-24 | Missing `collection_status` guard alongside the existing `status` guard, unlike both AR-controller siblings | ⚠️ Warning | A demand/final-notice collection letter renders for an already-paid or already-written-off entry via a bookmarkable GET |
| `resources/js/pages/cashier/Dashboard.vue` | 378-429 | Exhaustive-looking `v-if`/`v-else-if` action chain silently omits `on_credit`/`credit_pending_approval`/`credit_rejected` | ⚠️ Warning (real, but adjacent to AR-01..AR-04's literal text — see Gap 2's discussion) | An approved On-Credit balance has no reachable UI path to be paid off; write-off becomes the only reachable terminal state for every receivable |
| `app/Http/Controllers/Owner/CreditApprovalController.php` | 42-70 | `approve()` never re-reads or re-checks the job order's `payment_status` before writing `payment_status = OnCredit` | ⚠️ Warning | A job order paid in full while a credit request is pending gets silently reversed back to `on_credit` on approval |
| `app/Http/Controllers/Cashier/PaymentController.php` | 93-109 | `store()` guards `Paid`/`WrittenOff` but not `CreditPendingApproval` | ⚠️ Warning | The asymmetric half of the CR-01 race — nothing stops a payment landing while a credit request sits pending |

No unresolved `TBD`/`FIXME`/`XXX` debt markers found in any file read during this verification.

### Human Verification Required

None. All findings above were verified directly by reading source files, independently re-running the test suite and targeted test files, and cross-checking against the fresh code review's claims line-by-line rather than trusting either the review or the SUMMARYs. No visual/UX/real-time behavior needed human judgment for this round.

### Gaps Summary

**What 07-07 actually fixed (confirmed, not just claimed):** the exact three defects it was scoped to — the write-off settlement race, `CreditRequestController`'s missing `WrittenOff` guard, and `PaymentController::edit()`'s reachable entry point — are genuinely closed, verified by direct code reads of all three files and by independently re-running both new and extended regression tests (20/20 pass, and the full 445-test suite is green with zero failures, matching the claimed numbers exactly).

**What is still not achieved, found by this round's independent verification (going beyond 07-07's own scope, per this task's explicit instruction to weigh the fresh code review against the phase goal):**

1. **`CollectionStatusController::update()` (AR-03's own feature) can silently reverse an approved write-off.** It is the only mutating AR controller in the entire phase with no `DB::transaction()`/`lockForUpdate()` boundary — every other mutating controller in this phase (`WriteOffApprovalController`, `CreditRequestController`, `CreditApprovalController`) has one. A write-off approved between this controller's route-model-bind read and its write is silently overwritten, resuming reminder escalation on a booked loss. This is the identical bug class (missing locked re-read before a terminal-adjacent write) that motivated the 07-06 and 07-07 rounds, on a controller neither round touched. This directly fails AR-04's "stable, correct terminal state" bar.

2. **`CollectionLetterController::show()` has no `collection_status` guard**, unlike its two siblings. A dunning letter — a customer-facing document — prints in full for an already-settled or already-written-off entry.

3. **`CreditApprovalController::approve()` / `PaymentController::store()` do not guard `PaymentStatus::CreditPendingApproval`.** A real cash/bank/PayMongo payment recorded while a Phase-5 credit request sits pending is silently reversed back to `on_credit` on Owner approval — the same bug class as #1, just on the sibling approval flow. Confirmed real by direct code read; not covered by any existing test.

4. **The Cashier Dashboard has no reachable UI action to pay off an `on_credit` job order.** Weighed explicitly against Success Criterion 1 and the phase goal's "tracked ... through ... collections" wording, per this task's instruction: this is NOT one of AR-01..AR-04's four literal checklist items, and Phase 7's own CONTEXT explicitly scoped payment-recording UI out of this phase, treating it as an already-working Phase 5 capability. It is not. The practical effect is that every On-Credit balance's only reachable closure path today is Owner-approved write-off — "collections" (Accounting following up, a customer agreeing to pay) has no way to actually land a payment through this system's UI, directly contradicting this phase's own on-screen text ("Payments are recorded at the Cashier counter") and D-15/D-16's foundational assumption. **Verdict: this is a real, confirmed gap that should be closed before the phase is considered complete**, even though it sits at the Phase 5/Phase 7 boundary rather than squarely inside AR-01..AR-04's text — Phase 8's reporting will otherwise show every receivable resolving via write-off and never via payment, which misrepresents the business.

**Overall assessment:** AR-01, AR-02 stand verified and unchanged. AR-03's core feature works but shares a fault line with AR-04's durability problem. AR-04 is blocked for a third round — not by a regression of 07-07's own fixes, but by newly-surfaced defects on the identical "every writer/reader of a terminal state" fault line that the last two rounds were created to close, just in controllers neither round's scope reached. This looks like the same class of well-scoped, mechanical follow-up as 07-06 and 07-07 (an additive guard clause, or a locked-re-read boundary, matching an already-established shape in a sibling controller) rather than a re-architecture. Recommend a third and hopefully final gap-closure round — routed through `/gsd-plan-phase --gaps` — before Phase 8 begins, since Phase 8's reporting explicitly reads `payment_status`/`collection_status` as ground truth and needs these columns to be genuinely, durably terminal. The already-logged deferred item (`ConfirmPaymentIntent` reverting `written_off`) remains correctly out of scope and is not re-litigated here.

---

*Verified: 2026-09-09*
*Verifier: Claude (gsd-verifier)*
