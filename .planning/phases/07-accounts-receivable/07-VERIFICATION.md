---
phase: 07-accounts-receivable
verified: 2026-09-08T00:00:00Z
status: gaps_found
score: 3.5/4 roadmap success criteria substantively verified; AR-04 fails on closure integrity
overrides_applied: 0
gaps:
  - truth: "Owner-approved write-off closes an AR balance as a stable, correct terminal state (AR-04 / phase goal's 'closes ... by an Owner-approved write-off')"
    status: failed
    reason: "The single approve() action correctly sets collection_status=written_off and payment_status=written_off without touching total_amount/transactions (tested, verified). But the write-off lifecycle around that action is broken in three independently-reachable ways: (1) approve() never clears write_off_requested_at, so the approved entry never leaves the Owner's queue and still renders live Approve/Reject buttons; (2) reject() has no guard against an already-written-off collection_status, so clicking the still-visible 'Reject Request' button on an approved entry nulls write_off_reason/write_off_requested_by/write_off_requested_at while collection_status stays written_off -- an unrecoverable state, since WriteOffRequestController::store refuses a new request against a written_off entry; (3) PaymentStatus::WrittenOff is not treated as terminal by CancellationController::store or PaymentController::store, so a written-off job order can still be cancelled (charging a cancellation fee against a booked loss) or paid (flipping payment_status back to Paid while the AR row stays written_off), directly contradicting the Owner-facing 'This can't be undone' copy and desynchronizing the job order from the receivable."
      artifacts:
        - path: "app/Http/Controllers/Owner/WriteOffApprovalController.php"
          issue: "approve() (lines 82-101) does not null write_off_requested_at on success; reject() (lines 110-127) has no abort_if guard against collection_status already being WrittenOff"
        - path: "app/Http/Controllers/Cashier/CancellationController.php"
          issue: "store() only aborts for PaymentStatus::Paid/PendingConfirmation -- WrittenOff is missing from the terminal-state guard"
        - path: "app/Http/Controllers/Cashier/PaymentController.php"
          issue: "store() only aborts for PaymentStatus::Paid -- WrittenOff is missing, so a payment can be recorded against a written-off job order"
      missing:
        - "approve() must null write_off_requested_at (and ideally record write_off_approved_by/write_off_approved_at per WR-07) so the entry leaves the Owner's queue once resolved"
        - "reject() must abort_if collection_status is already WrittenOff (mirroring approve()'s existing terminal-state guard), so an approved write-off can never be rejected/erased"
        - "index() should defensively exclude collection_status in [paid, written_off] from the queue query as a second layer of protection"
        - "CancellationController::store and PaymentController::store must both treat PaymentStatus::WrittenOff as terminal alongside Paid"
        - "A regression test asserting the Owner's queue is empty after an approval, and tests asserting a written-off job order cannot be cancelled or paid"
human_verification: []
---

# Phase 7: Accounts Receivable Verification Report

**Phase Goal:** An On-Credit balance is tracked from creation through aging, escalating reminders, collections, and Owner-approved write-off — the accounting follow-through on Phase 5's credit path.
**Verified:** 2026-09-08
**Status:** gaps_found
**Re-verification:** No — initial verification

**Note on phase mode:** ROADMAP.md marks this phase `Mode: mvp`, but the phase goal is written in ROADMAP's standard goal + numbered Success Criteria format, not the `As a ..., I want ..., so that ....` user-story shape the MVP verification pipeline expects (`gsd-sdk query user-story.validate` returns `valid: false` against this goal text). Rather than refuse verification outright, this report proceeds with standard goal-backward verification against the four ROADMAP Success Criteria and each plan's `must_haves` frontmatter — the richer and more precise source of truth already available for this phase — the same approach Phase 1 (also `Mode: mvp` with a non-user-story goal) would need.

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Accounting Staff can view outstanding balances grouped into aging brackets (Current, 1-15/16-30/31-60/61-90/90+ days) — AR-01 | ✓ VERIFIED | `AccountsReceivableController::index()` scopes to `status=Active` (D-01), splits open/closed by `collection_status`, computes six `bracketSummaries` from `AccountsReceivable::agingBracket()` (pure function of `due_at`), derives balance live via total-minus-completed-transactions (D-16, matches `ReceiptController::show()`). `AgingBracketTest.php` covers all boundary days (1/15/16/30/31/60/61/90/91/365). `AccountsReceivableListTest.php` covers D-01 scope exclusion and bracket assignment. `Index.vue`/`Show.vue` render bracket cards, tabs, and entry detail. Confirmed by reading the controller and enum directly, not just the SUMMARY. |
| 2 | System automatically sends escalating reminder notifications as an AR entry crosses each aging bracket, to Accounting Staff + Owner, never the customer — AR-02 | ✓ VERIFIED | `SendAccountsReceivableReminders` (daily-scheduled in `routes/console.php` via `Schedule::command(...)->daily()->withoutOverlapping()->onOneServer()`) queries Active, not-yet-closed entries, computes `agingBracket()`, compares `rank()` against stored `last_reminder_bracket` for idempotency, sends via `AccountsReceivableReminder` Mailable to Accounting Staff + Owner only (`reminderRecipients()` filters by role, excludes deactivated users), wraps `Mail::send()` in try/catch with `report($e)` so a transport failure never blocks the bracket stamp (mirrors 04-13). `SendAccountsReceivableRemindersTest.php` and `AccountsReceivableReminderMailableTest.php` cover this. Non-blocking warnings found independently (WR-02: a failed send is still stamped as delivered with no retry; WR-03: an empty recipient list throws inside the swallowed catch; WR-04: a null `total_amount` closes a live receivable as Paid) — real defects but do not prevent the core escalation mechanism from functioning for the normal case. |
| 3 | Accounting Staff can update an AR entry's collection status and generate a printable collection letter — AR-03 | ✓ VERIFIED (with a real but non-blocking gap) | `CollectionStatusController::update()` restricts to the four human-settable values via `Rule::in()` in `AccountsReceivableValidationRules::collectionStatusRules()` (`paid`/`written_off` excluded, confirmed by grep and by `CollectionStatusTest.php`), and re-checks `status`/`collection_status` server-side independent of client UI. `CollectionLetterController::show()` derives `amountDue` live (never the stored `balance`) and selects `letterBody()` by current bracket, never persisting a letter body. `CollectionLetter.vue` is a print-only page (`window.print()`, no editable field, confirmed by grep). **Independently confirmed gap:** `CollectionLetterController::show()`'s only guard is `status === Active`; it does not check `collection_status`, so a `paid` or `written_off` entry (still `Active`) can still render a full dunning letter via direct URL — WR-05 in the code review, verified true by reading the controller. This does not block the core truth (Accounting Staff can update status and print a letter for an open account) but is a real correctness gap on an edge case. |
| 4 | Owner can approve a write-off of an AR balance, closing it as a stable, correct terminal state — AR-04 (phase goal's "closes ... by an Owner-approved write-off") | ✗ FAILED | The single approve action itself works and is tested: `WriteOffApprovalController::approve()` sets `collection_status=written_off` and `job_order.payment_status=written_off` inside a locked transaction, without touching `total_amount`/transactions (verified by reading the controller and `WriteOffApprovalTest.php`). But independently verified by reading the code (not trusting the review or SUMMARY): (a) `approve()` never nulls `write_off_requested_at`, so the entry never leaves `index()`'s `whereNotNull('write_off_requested_at')` queue — it looks permanently pending; (b) `reject()` has no guard against `collection_status` already being `written_off` (only checks `write_off_requested_at !== null`, which stays true after approval) — clicking "Reject Request" on that stale queue row nulls `write_off_reason`/`write_off_requested_by`/`write_off_requested_at` on an entry that is already a booked loss, and `WriteOffRequestController::store` then permanently refuses re-request since `collection_status` is `written_off` — an unrecoverable, audit-incomplete state; (c) `PaymentStatus::WrittenOff` is not in the terminal-state guard of `CancellationController::store` (only checks `Paid`/`PendingConfirmation`) or `PaymentController::store` (only checks `Paid`), and `cashier/Dashboard.vue` renders "Cancel Job Order" for any `payment_status !== 'paid'`, which includes `written_off` — so a written-off job order can still be cancelled (charging a fee against a booked loss) or paid back to `Paid`, desynchronizing the job order from the AR entry. All three are reachable through the primary UI a normal Owner/Cashier workflow would exercise, not synthetic edge cases, and none is covered by a regression test (`WriteOffApprovalTest.php` never re-checks the queue after approval, and no test exists for cancel/pay on a written-off order). |

**Score:** 3/4 roadmap success criteria fully verified; AR-04 fails on write-off closure integrity (the mechanism to approve works, but the resulting state is not stable/correct).

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Models/AccountsReceivable.php` | `due_at`/collection/write-off casts, `agingBracket()`/`daysPastDue()` | ✓ VERIFIED | Present, matches plan; `#[ObservedBy(AuditObserver::class)]` retained |
| `app/Enums/AccountsReceivableAgingBracket.php` | six-case enum, `rank()`, `reminderBearing()`, `letterBody()` | ✓ VERIFIED | All three methods present |
| `app/Enums/AccountsReceivableCollectionStatus.php` | six D-10 cases | ✓ VERIFIED | Present |
| `app/Enums/PaymentStatus.php` | append-only `WrittenOff` case | ✓ VERIFIED | Appended last, no reordering |
| `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php` | aging list index/show | ✓ VERIFIED | Matches plan; WR-01 (missing `last_reminder_sent_at` in `index()`'s `get()` allowlist vs. `deriveRow()` reading it) confirmed by reading lines 60 and 171 — latent, not currently rendered by `Index.vue` |
| `app/Console/Commands/SendAccountsReceivableReminders.php` | `ar:send-reminders` daily command | ✓ VERIFIED | Matches plan exactly; scheduled in `routes/console.php` |
| `app/Http/Controllers/AccountingStaff/CollectionStatusController.php` | restricted `update()` | ✓ VERIFIED | Matches plan |
| `app/Http/Controllers/AccountingStaff/CollectionLetterController.php` | derived-balance, bracket-driven `show()` | ⚠️ ORPHANED GUARD | Exists and works for open entries; missing `collection_status` guard (WR-05) confirmed by reading the file — `abort_unless` only checks `status`, line 24 |
| `app/Http/Controllers/AccountingStaff/WriteOffRequestController.php` | Accounting-side write-off request | ✓ VERIFIED | Correctly guards Active + not-already-closed + not-already-pending (Blocker 2 fix present, confirmed) |
| `app/Http/Controllers/Owner/WriteOffApprovalController.php` | Owner approve/reject with locked re-read | ✗ INCOMPLETE STATE MACHINE | `approve()`/`reject()` exist, use `lockForUpdate()`, and the single happy-path mutation is correct — but the state machine is incomplete (CR-01/CR-02, confirmed above) |
| `app/Enums/PaymentStatus.php` consumers | `WrittenOff` treated as terminal everywhere `Paid` is | ✗ FAILED | `CancellationController.php:33` and `PaymentController.php:107` guard only on `Paid`(/`PendingConfirmation`) — confirmed by grep and direct read; `WrittenOff` is absent from both |
| `resources/js/pages/owner/WriteOffRequests.vue` | Owner's write-off queue UI | ✓ VERIFIED (exists) | Present, correct inverted-polarity buttons per UI-SPEC — but structurally always shows approved entries too, per the backend gap above |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `Owner\CreditApprovalController::approve()` | `credit_term_days` config | `SystemConfiguration::getInt('credit_term_days', 30)` | WIRED | Confirmed present, stamps `due_at` once at approval |
| `AccountsReceivable::agingBracket()` | `due_at` | `diffInDays(now())` match | WIRED | Pure function, no query, confirmed |
| `Index.vue` | `AccountsReceivableController@index` | Inertia render | WIRED | Confirmed |
| `SendAccountsReceivableReminders` | `AccountsReceivableReminder` Mailable | `Mail::to(...)->send(...)` in try/catch | WIRED | Confirmed, stamp happens outside try/catch (Pitfall 2 honored) |
| `UpdateCollectionStatusRequest` | `AccountsReceivableValidationRules` | `collectionStatusRules()` | WIRED | Confirmed, `paid`/`written_off` excluded from allowlist |
| `CollectionLetterController` | `AccountsReceivableAgingBracket::letterBody()` | bracket-driven body | WIRED | Confirmed, but reachable on already-closed entries (WR-05) |
| `ApproveWriteOffRequest`/`RejectWriteOffRequest` | `AccountsReceivablePolicy::approveWriteOff/rejectWriteOff` | `$this->user()->can(...)` | WIRED | Confirmed Owner-only, Admin gets 403 (tested) |
| `WriteOffApprovalController::approve` | `PaymentStatus::WrittenOff` | `forceFill(['payment_status' => ...])` | WIRED (write side) but NOT WIRED (read/guard side) | The write happens correctly, but no downstream consumer (`CancellationController`, `PaymentController`) checks for this value as terminal — the link exists one-directionally |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Full test suite passes | `php artisan test --compact` | `{"tests":439,"passed":436,"skipped":3}` | ✓ PASS (matches claimed figures, independently re-run) |
| No debt markers in phase-modified write-off files | `grep -n "TBD\|FIXME\|XXX"` across 5 key controllers/commands | no matches | ✓ PASS |
| `CancellationController`/`PaymentController` treat `WrittenOff` as terminal | `grep -n "PaymentStatus::" ...` | Only `Paid`/`PendingConfirmation` referenced | ✗ FAIL (confirms CR-03) |
| `WriteOffApprovalController::reject()` guards against an already-approved entry | direct code read | No `collection_status` check in `reject()` | ✗ FAIL (confirms CR-01) |
| Approved write-off leaves the Owner's queue | direct code read + `WriteOffApprovalTest.php` test list | `approve()` never nulls `write_off_requested_at`; no test asserts queue emptiness post-approval | ✗ FAIL (confirms CR-02) |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| AR-01 | 07-01 (substrate), 07-02 | Aging brackets view | ✓ SATISFIED | Verified above |
| AR-02 | 07-01 (substrate), 07-03 | Escalating reminders | ✓ SATISFIED | Verified above |
| AR-03 | 07-01 (substrate), 07-04 | Collection status + printable letter | ✓ SATISFIED (with WR-05 gap noted) | Verified above |
| AR-04 | 07-01 (substrate), 07-05 | Owner-approved write-off | ✗ BLOCKED | Write-off approval mechanism works in isolation but the surrounding lifecycle is broken (CR-01/CR-02/CR-03) |

No orphaned requirements — all four AR-01..AR-04 IDs declared across plan frontmatter match `.planning/REQUIREMENTS.md`'s Phase 7 mapping exactly.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `app/Http/Controllers/Owner/WriteOffApprovalController.php` | 88-92, 110-127 | Incomplete terminal-state guard (`reject()` lacks the check `approve()` has) | 🛑 Blocker | Approved write-offs are erasable/corruptible via a live UI button (CR-01) |
| `app/Http/Controllers/Owner/WriteOffApprovalController.php` | 82-101, 27-35 | `approve()` doesn't close the request it approves | 🛑 Blocker | Owner's queue never empties of resolved requests (CR-02), the reachable path into CR-01 |
| `app/Enums/PaymentStatus.php` + `app/Http/Controllers/Cashier/CancellationController.php` + `PaymentController.php` | 14 / 33 / 107 | New terminal enum case not recognized by existing consumers | 🛑 Blocker | Written-off job orders can be cancelled or paid, reversing the write-off's financial intent (CR-03) |
| `app/Http/Controllers/AccountingStaff/CollectionLetterController.php` | 24 | Missing `collection_status` guard alongside `status` guard | ⚠️ Warning | Dunning letter renders for settled/written-off entries via direct URL (WR-05) |
| `app/Console/Commands/SendAccountsReceivableReminders.php` | 84-90 | Mail failure stamped as delivered, no retry | ⚠️ Warning | A single transport hiccup permanently skips that bracket's reminder (WR-02) |
| `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php` | 60 vs. 171 | `index()`'s column allowlist omits a field `deriveRow()` reads | ⚠️ Warning | Latent — `last_reminder_sent_at` silently `null` in list rows (WR-01), not currently rendered |

No unresolved `TBD`/`FIXME`/`XXX` debt markers found in phase-modified files.

### Human Verification Required

None. All findings above were verified directly by reading source files and cross-checking against tests; no visual/UX/real-time behavior needed human judgment beyond what the code review already surfaced and this report independently confirmed.

### Gaps Summary

Three of four ROADMAP success criteria (AR-01, AR-02, AR-03) are genuinely and substantively achieved — verified independently by reading controllers, enums, the reminder command, and their tests, not by trusting SUMMARY.md. AR-03 has one real but non-blocking edge-case gap (WR-05).

AR-04 ("Owner can approve a write-off of an AR balance") is where the phase goal is not fully achieved. The mechanical action of approving a write-off is correctly implemented and tested — `collection_status`/`payment_status` are set correctly and money fields are untouched. But the phase goal's language ("closes ... by an Owner-approved write-off") implies a stable, terminal, correct closure, and three independently-verified, UI-reachable defects mean that closure is not durable:

1. An approved write-off never leaves the Owner's queue (CR-02) — it appears permanently actionable.
2. Because of (1), the still-live "Reject Request" button can be clicked on an already-approved entry, nulling its reason/requester/timestamp while the loss stays booked, with no way to re-request (CR-01) — a genuinely unrecoverable, audit-incomplete data state.
3. The new `PaymentStatus::WrittenOff` terminal case was never taught to the two controllers (`CancellationController`, `PaymentController`) that already gate on `Paid` — so a written-off job order can be cancelled or paid, contradicting the Owner-facing "This can't be undone" copy and desynchronizing the job order from the AR entry (CR-03).

These are not hypothetical — all three are reachable through the exact UI surfaces this phase built (the Owner's write-off queue always showing the entry; the Cashier dashboard's existing Cancel/Pay actions, unconditionally rendered for any non-Paid `payment_status`) and none is covered by a regression test. Given this, AR-04 is assessed as FAILED at the goal level even though the isolated "approve a write-off" action itself is correctly implemented and tested.

This looks intentional as a phase-closing gap the code review already surfaced with concrete fixes (see `07-REVIEW.md` CR-01/CR-02/CR-03) rather than a fundamental design flaw — closing it is a small, well-scoped follow-up (a handful of guard clauses across three files plus two regression tests), not a re-architecture. Recommend routing this back through `/gsd-plan-phase --gaps` for a closure plan before Phase 8 begins, since Phase 8's reporting explicitly reads `payment_status`/AR data as ground truth and would otherwise inherit this inconsistency.

---

*Verified: 2026-09-08*
*Verifier: Claude (gsd-verifier)*
