# Deferred Items — Phase 07 Accounts Receivable

## From 07-01

- **`AccountsReceivableFactory::rejected()` has the same latent bug `active()` had before this plan's fix**: `'approved_by' => User::factory()->owner()` passes an uninstantiated `UserFactory` to `forceFill()` instead of a created user's id, which throws `Object of class Database\Factories\UserFactory could not be converted to string` the moment any test calls `->rejected()->create()`. Currently unused anywhere in `tests/` or `app/`, so it has never been exercised. Out of scope for 07-01 (only `active()` blocked this plan's tests) — fix when a future plan first uses `->rejected()`.
    - **RESOLVED in 07-02**: `AccountsReceivableListTest::AccountsReceivableListTest.php`'s D-01 scope test needed a Rejected row, exercising this exact bug. Fixed identically to `active()`'s prior fix — `User::factory()->owner()->create()->id` — in `database/factories/AccountsReceivableFactory.php`.
- **`composer types:check` (Larastan level 7) fails on three pre-existing, unrelated files** — `app/Http/Requests/Cashier/CreateCreditRequestRequest.php:40`, `app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php:42,64,68`, `app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php:20` — all `property.notFound`/`method.notFound` errors on `$this->route('jobOrder')`/`$this->route('systemConfiguration')` resolving to Larastan's generic `object|string` route-binding stub type rather than the bound model class. None of these three files are in 07-01's `files_modified` list or touched by this plan; last modified in Phase 5 (`4c09345`). The errors are unrelated to `AccountsReceivable`/`PaymentStatus`/`CreditApprovalController` — root cause looks like a Larastan/PHPStan route-model-binding type-inference gap, not application code. Flagged here since it blocks 07-01's stated `composer types:check exits 0` acceptance line; needs its own investigation/fix plan.
    - **Still present in 07-02** — same three pre-existing files, same three errors, confirmed still out of scope (not in 07-02's `files_modified` either).
    - **Still present in 07-05** — same three pre-existing files, same three errors, confirmed still out of scope (not in 07-05's `files_modified` either). This is the phase's last plan; the gap should get its own small fix plan outside Phase 7.
    - **Still present in 07-06** — same three pre-existing files (`CreateCreditRequestRequest.php`, `SavePricingAndPaymentRequest.php`, `UpdateSystemConfigurationRequest.php`), same 5 errors, confirmed still out of scope (not in 07-06's `files_modified` either). 07-06 is a gap-closure plan for AR-04, not a new plan in the original phase sequence; the gap remains unresolved and should get its own small fix plan.
    - **Still present in 07-07** — same three pre-existing files, same 5 errors, confirmed still out of scope (not in 07-07's `files_modified` either).
    - **07-08 Task 1 grew this to 6 errors (still the identical root cause, one additional manifestation)**: `SavePricingAndPaymentRequest.php`'s `withValidator()` closure replaced its `$jobOrder->total_amount - $amountPaid` reimplementation with `$jobOrder->outstandingBalance()` (per 07-08 Task 1's action, required to close the "no file reimplements the subtraction" gate). Larastan's route-model-binding gap resolves `$this->route('jobOrder')` to `object|string`, so it now also flags `->outstandingBalance()` as `method.notFound` at the new call site (line 69), in addition to the three pre-existing `total_amount`/`transactions()` accesses at lines 42/64/68 in the same file. This is the SAME untyped-route-binding root cause as the other three files (not a new distinct defect class) — confirmed by testing that reverting just this one line drops the count back to 5/3-files. `composer types:check` now reports 6 errors across the same 3 files: `CreateCreditRequestRequest.php:40`, `SavePricingAndPaymentRequest.php:42,64,68,69`, `UpdateSystemConfigurationRequest.php:20`. Not fixed here per this plan's explicit scope fence (adding a `@var` type hint to resolve the route-binding gap is out of scope) — the eventual dedicated fix plan for this gap should account for 4 sites in this file, not 3.

## From 07-07

- **6th `payment_status` writer found by the Task 3 audit, NOT fixed in this plan (scope discipline per the orchestrator fence)**: `app/Actions/POS/ConfirmPaymentIntent.php` can silently overwrite `payment_status` from `written_off` back to `paid`/`partially_paid`. Trace: (1) a job order goes On-Credit (`payment_status = on_credit`, an Active `AccountsReceivable` row exists); (2) a Cashier initiates a GCash/Maya payment against it via `PaymentController::store()` — none of `store()`'s guards (`cancelled_at`, status, `Paid`, `WrittenOff`) block this for an `OnCredit` job order, so `storePaymongoIntent()` creates a `pending_confirmation` Transaction and sets `payment_status = pending_confirmation`; (3) Accounting Staff requests a write-off on the AR row via `WriteOffRequestController::store()`, which checks only the AR row's own `status`/`collection_status`, never the job order's `payment_status` or any in-flight transaction; (4) the Owner approves it via `WriteOffApprovalController::approve()` — Task 1's derived-balance guard only sums _Completed_ transactions, so the still-pending GCash/Maya transaction doesn't count toward the balance and the guard passes, setting `payment_status = written_off`; (5) the PayMongo webhook (or Plan 05-04's manual reconciliation) later confirms the transaction, and `ConfirmPaymentIntent()` recomputes `payment_status` from `Completed` transactions with no `WrittenOff` awareness at all, overwriting it to `paid`/`partially_paid`. Reachable end-to-end through existing routes, no forceFill needed. This is a distinct defect from the two this plan closed (T-07-07-01/T-07-07-02) — not covered by Task 1 or Task 2's guards — and per the plan's explicit scope fence ("a newly discovered 6th defect is a candidate for a further gap-closure round, not a same-plan scope expansion") is deliberately left unfixed here. Needs its own gap-closure round; likely fix shape: guard `ConfirmPaymentIntent()`'s write branches with the same `abort`-free early-return-if-WrittenOff pattern this plan added elsewhere (a locked re-read already exists there), or block `WriteOffRequestController`/`WriteOffApprovalController` from proceeding while any of the job order's transactions are still `pending_confirmation`.
    - **RESOLVED in 07-08**: Task 2 wraps both the Completed and Failed/expired branches of `ConfirmPaymentIntent`'s locked `$jobOrder` re-read in a `payment_status !== WrittenOff` guard, so a confirmed webhook/reconciliation can never overwrite an already-written-off job order's terminal `payment_status`. Covered by a dedicated regression test in `tests/Unit/Actions/ConfirmPaymentIntentTest.php`.

## From round 4 (inline close, 2026-09-09)

Both AR-04 findings from `07-REVIEW.md` were fixed inline rather than through a fifth
gap-closure plan. Rationale recorded below because the _loop itself_ was the real defect.

- **CR-01 `PaymentController` locked re-read — FIXED.** `store()` and `storePaymongoIntent()`
  now re-fetch the job order under `lockForUpdate()` as the first act inside their own
  `DB::transaction()` closures and re-run the `cancelled_at` / `Paid` / `WrittenOff` /
  `CreditPendingApproval` guards against that locked instance, matching the pattern already
  used by `CreditRequestController`, `CreditApprovalController`, `WriteOffApprovalController`,
  `CollectionStatusController` and `ConfirmPaymentIntent`.
    - **Honest limitation:** the TOCTOU race this closes is not covered by an automated test.
      A feature test cannot interleave a second request between the outer guard and the
      transaction commit without contrived instrumentation. The fix is verified by code review
      against the seven sibling implementations of the same pattern, not by a failing-then-passing
      test. If a future round wants proof, it needs a concurrency harness, not another grep audit.

- **CR-02 `outstandingBalance()` / `CancellationFee` commingling — RESOLVED BY BUSINESS RULE,
  not by a type filter.** The reviewer framed this as a missing `whereIn('type', ...)` filter,
  but the underlying question was an unspecified business rule: _what does a customer owe when
  an on-credit job order is cancelled?_ Decision taken 2026-09-09: **fee only — cancelling voids
  the print-job debt.**
    - Implemented as: new terminal `AccountsReceivableCollectionStatus::Cancelled` (no migration —
      `collection_status` is an unconstrained string column), added to the closed-status lists in
      `AccountsReceivableController::index()` and `SendAccountsReceivableReminders`, and
      `CancellationController::store()` now closes any Active receivable under a locked read.
    - `outstandingBalance()` deliberately still has **no** `type` filter. It does not need one:
      `CancellationFee` transactions are created only by `CancellationController` (verified by
      grep — the enum case appears in exactly two files), so a job order carries one **iff** it is
      cancelled, and a cancelled job order's receivable is now closed. No live AR figure can mix
      a fee into a balance. Adding a filter as well would be redundant surface.
    - `Cancelled` is deliberately distinct from `WrittenOff`: a write-off is an Owner-approved
      uncollected loss (AR-04) and must stay reportable as such for Phase 8's financial reports.

### Why this phase looped four times — process defect, not code defect

`execute-phase`'s `code_review_gate` re-reviews **every file in the phase** (56 files) on every
round, not just what the round changed. Rounds 07-06/07-07/07-08 therefore kept re-rolling a
fresh LLM review across Phase 5 code that no gap round had ever touched — `PaymentController`'s
guards and `CancellationController` both predate Phase 7. An LLM reading 56 files of money and
concurrency code reliably returns 2-6 plausible Criticals, the verifier converts any Critical
into `gaps_found`, and the next round begins. There is no fixed point in that arrangement.

Evidence it was the harness and not the codebase: the suite was green at every round (445 → 456
→ 458 tests, zero failures throughout), and the verifier independently confirmed that 07-06,
07-07 and 07-08 each _did_ fully close their declared scope. Real defects were being closed each
round; the gate simply kept widening the net faster than the rounds could close it.

**If Phase 8 shows the same symptom:** scope the review to the round's own diff
(`/gsd-code-review 8 --files=...`) rather than the whole phase, or treat reviewer Criticals in
untouched files as backlog items instead of phase-blocking gaps.
