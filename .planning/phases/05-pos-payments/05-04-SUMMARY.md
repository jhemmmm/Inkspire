---
phase: 05-pos-payments
plan: 04
subsystem: payments
tags:
    [
        laravel,
        inertia,
        vue3,
        pest,
        pos,
        payments,
        paymongo,
        gcash,
        maya,
        reconciliation,
        dropdown-menu,
    ]

# Dependency graph
requires:
    - phase: 05-pos-payments (plan 05-03)
      provides: ConfirmPaymentIntent idempotency Action, PaymongoWebhookController, PayMongo Payment Intent QR flow, JobOrderPayment.vue's "Check Payment Status" TODO anchor
provides:
    - ReconciliationController — store() (manual reconciliation, shared by Cashier and Accounting Staff) and index() (Accounting Staff's pending-confirmation list)
    - cashier.job-orders.reconcile / accounting-staff.job-orders.reconcile routes, both resolving to the same controller method
    - ConfirmPaymentIntent's failure branch now recomputes payment_status instead of leaving a job order stuck on PendingConfirmation forever
    - Accounting Staff Dashboard — first real surface for that role, replacing the empty placeholder
    - Cashier Dashboard's Actions column converted to a per-row DropdownMenu (Process Payment / Check Payment Status / View Receipt)
affects: [05-05, 05-06, 05-07, 07-accounts-receivable, 08-reporting]

# Tech tracking
tech-stack:
    added: []
    patterns:
        - "Two routes resolving to the same controller method makes Wayfinder's per-controller actions export (resources/js/actions/.../Controller.ts) a URI-keyed dictionary rather than a callable — use the named-route helper (resources/js/routes/{prefix}/job-orders.ts) instead, which stays a plain callable with .form()/.url()"
        - "A no-body reconciliation POST triggered from inside another page's already-open <Form> (or from a DropdownMenuItem with no navigable href) uses router.post() with a local processing ref, not a nested <Form> — HTML forbids <form> inside <form>"
        - "Shared Cashier/Accounting Staff mutation routes with role-specific post-success destinations branch on $request->user()->role inside the controller rather than assuming one caller's UX for both — Accounting Staff has no route access to Cashier's role:cashier-gated pages"

key-files:
    created:
        - app/Http/Controllers/Cashier/ReconciliationController.php
        - tests/Feature/Cashier/ReconciliationTest.php
        - resources/js/config/nav/accounting-staff.ts
    modified:
        - app/Actions/POS/ConfirmPaymentIntent.php
        - routes/portals.php
        - tests/Feature/Webhooks/PaymongoWebhookTest.php
        - tests/Unit/Actions/ConfirmPaymentIntentTest.php
        - resources/js/pages/cashier/JobOrderPayment.vue
        - resources/js/pages/cashier/Dashboard.vue
        - resources/js/pages/accounting-staff/Dashboard.vue

key-decisions:
    - "cancelled is treated as the Payment Intent's failed/expired outcome and every other non-terminal status (awaiting_payment_method, awaiting_next_action, processing) as still pending — PayMongo's API exposes no distinct 'expired' status; re-verify once the user's PayMongo sandbox keys are available"
    - "Success redirect is role-aware ($request->user()->role === UserRole::Cashier), not the plan's literal unconditional to_route('cashier.job-orders.receipt.show', ...) — that route sits behind role:cashier middleware, so an unconditional redirect would 403 for Accounting Staff"
    - 'Used named-route helpers (@/routes/cashier/job-orders, @/routes/accounting-staff/job-orders) instead of ReconciliationController.store.form() — Wayfinder exports a URI-keyed dictionary, not a callable, when two routes share one controller method'

patterns-established:
    - "Pattern: shared dual-role mutation controllers branch post-action redirects on the acting user's role, never assume the caller is always the role with the richer follow-up UX"

requirements-completed: [POS-04]

# Metrics
duration: ~14min
completed: 2026-09-05
---

# Phase 5 Plan 4: Manual Reconciliation & Accounting Staff Dashboard Summary

**Shared `ReconciliationController` (store + index) reused by Cashier and Accounting Staff, both routed through the exact same `ConfirmPaymentIntent` idempotency boundary the webhook uses; Accounting Staff's portal gets its first real surface.**

## Performance

- **Duration:** ~14 min
- **Started:** 2026-09-05T00:33:00+08:00 (worktree environment setup — vendor/node_modules copy, .env, sqlite db, build)
- **Completed:** 2026-09-05T00:46:44+08:00
- **Tasks:** 2/2
- **Files modified:** 10 (3 created, 7 modified)

## Accomplishments

- `ReconciliationController::store()` — one controller method mounted from both `role:cashier` and `role:accounting_staff` route groups, calling `Paymongo::paymentIntent()->find()` and resolving through `ConfirmPaymentIntent` (Plan 05-03's shared idempotency boundary), never a separate independently-written guard
- Three PayMongo outcomes handled: `succeeded` → confirms and flashes "Payment confirmed."; `cancelled` → confirms as failed and flashes D-13's exact "choose a different payment method" copy; anything else → no state change, flashes the "not received yet" copy
- `ConfirmPaymentIntent`'s failure branch extended to recompute `payment_status` from any other completed transactions (falls back to `Unpaid`/`PartiallyPaid`) instead of leaving the job order permanently stuck on `PendingConfirmation` — this also fixes the webhook path, since both callers share the Action
- `ReconciliationController::index()` + rewritten `accounting-staff/Dashboard.vue` — Accounting Staff's first real dashboard, listing job orders `payment_status = pending_confirmation` with Job Order/Customer/Amount/Payment Method/Created At columns and a working "Check Payment Status" action
- `JobOrderPayment.vue`'s QR sub-view "Check Payment Status" button wired for real (was a TODO comment anchor since Plan 05-03), with a `Spinner` while in flight; success redirects the Cashier to the Receipt page
- Cashier Dashboard's Actions column converted from a single conditional `Link` to a `DropdownMenu` (`MoreHorizontal` trigger, `sr-only` "Actions" label) now covering three conditional actions: Process Payment / Check Payment Status / View Receipt
- Full test suite (226 tests, 223 passed / 3 pre-existing skips) green; `npm run types:check` and `composer types:check` (this plan's files) both clean

## Task Commits

Each task was committed atomically:

1. **Task 1: Backend — reconciliation controller for both roles** - `dfeeb1c` (feat)
2. **Task 2: Frontend — wire Check Payment Status everywhere, build Accounting Staff Dashboard** - `891bdd9` (feat)

_No plan-metadata commit yet — SUMMARY.md commit follows this file._

## Files Created/Modified

**Task 1 (backend):**

- `app/Http/Controllers/Cashier/ReconciliationController.php` - `store()` — shared manual reconciliation action for both roles
- `app/Actions/POS/ConfirmPaymentIntent.php` - failure branch now recomputes `payment_status` (Unpaid/PartiallyPaid) instead of leaving it stuck on PendingConfirmation
- `routes/portals.php` - `cashier.job-orders.reconcile` and `accounting-staff.job-orders.reconcile` routes, same controller/method, two role-scoped names
- `tests/Feature/Cashier/ReconciliationTest.php` - 7 cases: succeeded (full + down payment), still-pending, failed/cancelled, cross-role (Cashier route), 422 (not pending), 403 (wrong role)
- `tests/Unit/Actions/ConfirmPaymentIntentTest.php` - updated the failure-path expectation (Unpaid, not stuck PendingConfirmation) + new PartiallyPaid-fallback case
- `tests/Feature/Webhooks/PaymongoWebhookTest.php` - updated the failed-payment webhook test to match the corrected shared failure-path behavior

**Task 2 (frontend):**

- `app/Http/Controllers/Cashier/ReconciliationController.php` - added `index()` (Accounting Staff's pending-confirmation list) and made the success redirect role-aware
- `routes/portals.php` - `accounting-staff.dashboard` now points at `ReconciliationController::index()` instead of the static `Route::inertia()` placeholder
- `resources/js/pages/cashier/JobOrderPayment.vue` - real "Check Payment Status" button (`router.post`), `Spinner` while in flight
- `resources/js/pages/cashier/Dashboard.vue` - Actions column is now a `DropdownMenu`; "Check Payment Status" item added for `pending_confirmation` rows
- `resources/js/pages/accounting-staff/Dashboard.vue` - full rewrite: real `Table` + empty state, replaces the placeholder
- `resources/js/config/nav/accounting-staff.ts` - single "Dashboard" nav item, matching every other portal's first item shape

## Decisions Made

- `cancelled` is this plan's failed/expired PayMongo Payment Intent outcome; every other non-terminal status is treated as still pending (documented MEDIUM-confidence assumption, consistent with `05-RESEARCH.md`'s own caveat on PayMongo's exact status vocabulary — re-verify against a real sandbox delivery once keys are available).
- Success redirect branches on `$request->user()->role === UserRole::Cashier` rather than the plan's literal unconditional `to_route('cashier.job-orders.receipt.show', ...)` snippet — Accounting Staff has no route access to that `role:cashier`-gated page (T-05-12), so an unconditional redirect would 403.
- Used named-route helpers (`@/routes/cashier/job-orders`, `@/routes/accounting-staff/job-orders`) instead of `ReconciliationController.store.form()` for the frontend wiring — Wayfinder's per-controller actions export becomes a URI-keyed dictionary (not a callable) once two distinct routes share one controller method.
- "Check Payment Status" wired via `router.post()`, not a nested `<Form>`, in both `JobOrderPayment.vue` (already inside the page's single outer `<Form>`) and the Dashboard `DropdownMenuItem`s (no navigable href to wrap).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `ConfirmPaymentIntent`'s failure branch left `payment_status` permanently stuck on `PendingConfirmation`**

- **Found during:** Task 1, implementing the D-13 failed/expired branch
- **Issue:** The plan's own action text explicitly required extending `ConfirmPaymentIntent`'s failure branch to recompute `payment_status`, but the plan's `files_modified` frontmatter list omitted `app/Actions/POS/ConfirmPaymentIntent.php` and the two existing tests that encode the old (stuck) behavior (`tests/Unit/Actions/ConfirmPaymentIntentTest.php`, `tests/Feature/Webhooks/PaymongoWebhookTest.php`). Leaving those tests unchanged after the fix would have broken the suite.
- **Fix:** Added an `else` branch mirroring the `Completed` branch's shape — sums other completed transactions for the job order, falls back to `PartiallyPaid` if any exist, else `Unpaid`. Updated both existing tests' expectations to match (this also corrects the webhook's failure path, since both callers share the Action) and added a new unit test for the `PartiallyPaid` fallback case.
- **Files modified:** `app/Actions/POS/ConfirmPaymentIntent.php`, `tests/Unit/Actions/ConfirmPaymentIntentTest.php`, `tests/Feature/Webhooks/PaymongoWebhookTest.php`
- **Verification:** Full suite green (226 tests, 223 passed / 3 pre-existing skips).
- **Committed in:** `dfeeb1c` (Task 1 commit)

**2. [Rule 1 - Bug] Plan's literal unconditional receipt redirect on reconciliation success would 403 for Accounting Staff**

- **Found during:** Task 2, wiring the frontend redirect requirement
- **Issue:** The plan's option (a) code sketch was `to_route('cashier.job-orders.receipt.show', $jobOrder)` unconditionally on success. `cashier.job-orders.receipt.show` sits inside the `role:cashier` middleware group only — an Accounting Staff user reconciling from their own dashboard would be redirected into a route they're blocked from, producing a 403 instead of the intended "return to dashboard" UX.
- **Fix:** Branched the redirect on `$request->user()->role === UserRole::Cashier`: Cashier gets the receipt redirect, Accounting Staff gets `back()` (their own dashboard).
- **Files modified:** `app/Http/Controllers/Cashier/ReconciliationController.php`
- **Verification:** `ReconciliationTest.php`'s accounting-staff success case asserts a redirect (not a 403); manual route-list inspection confirmed `cashier.job-orders.receipt.show` middleware.
- **Committed in:** `891bdd9` (Task 2 commit)

**3. [Rule 1 - Bug] `ReconciliationController.store.form(jobOrder.id)` (as literally suggested by the plan) is not a callable**

- **Found during:** Task 2, wiring `JobOrderPayment.vue`'s button
- **Issue:** Wayfinder generates a URI-keyed dictionary export (`{ '/cashier/...': fn, '/accounting-staff/...': fn }`) for `store` in the per-controller actions file whenever two distinct routes resolve to the same controller method, not a single callable with `.form()`.
- **Fix:** Imported the named-route helpers instead (`reconcile` from `@/routes/cashier/job-orders` and `@/routes/accounting-staff/job-orders`), each a plain callable with `.url()`/`.form()`, matching this codebase's existing "prefer named route imports" convention.
- **Files modified:** `resources/js/pages/cashier/JobOrderPayment.vue`, `resources/js/pages/cashier/Dashboard.vue`, `resources/js/pages/accounting-staff/Dashboard.vue`
- **Verification:** `npm run types:check` clean; `npm run build` succeeds with no unresolved-import errors.
- **Committed in:** `891bdd9` (Task 2 commit)

---

**Total deviations:** 3 auto-fixed (all Rule 1 — bug fixes correcting behavior the plan's own text intended but whose literal code sketch or file list would have broken)
**Impact on plan:** All fixes stayed inside this plan's stated scope (reconciliation + Accounting Staff dashboard). No architectural changes, no scope creep.

## Issues Encountered

- **Same worktree cold-start situation as Plans 05-01/05-02/05-03** (no `vendor/`, `node_modules/`, `.env`, or build output). Resolved identically: full copy of `vendor`/`node_modules` from the main checkout, `composer dump-autoload`, `.env` from `.env.example` + `key:generate`, worktree-local SQLite DB, `migrate:fresh --seed`, `npm run build` (run twice — once after Task 1's route additions, once after Task 2's further route changes, to keep Wayfinder-generated helpers current).
- **Worktree base drift at spawn time:** this worktree's initial HEAD (`351fb0d "init"`, a single orphan commit) did not descend from the expected base commit (`ba6f3ac`). Corrected via the sanctioned `git reset --hard` inside the mandatory `<worktree_branch_check>` setup step (working tree was clean at that point, nothing lost) — same pattern documented in Plan 05-03's SUMMARY.
- **Manual verification of the "Accounting Staff login shows pending list" acceptance criterion** was not performed via a live browser session in this environment (no interactive browser tooling available to this agent) — verified instead via the feature test suite (`ReconciliationTest.php`'s accounting-staff cases exercise the exact same controller/query path the Dashboard page renders) and `npm run types:check`/`npm run build` succeeding with no unresolved imports.

## User Setup Required

None - no external service configuration required beyond what Plan 05-03 already documented (PayMongo sandbox keys for live end-to-end testing).

## Next Phase Readiness

- `ReconciliationController` (both `store()` and `index()`) is complete and shared cleanly between the two roles — no further changes anticipated from later POS plans.
- Cashier Dashboard's `DropdownMenu` Actions column pattern is now established for future plans (05-05 cancellation, 05-07 release) that need to add more conditional per-row actions — extend the same `DropdownMenu`/`DropdownMenuItem` structure rather than reverting to single inline buttons.
- The PayMongo Payment Intent status vocabulary assumption (`cancelled` = failed/expired, everything else = pending) is flagged MEDIUM confidence — re-verify against a real PayMongo sandbox delivery once the user provisions test-mode keys, and adjust `ReconciliationController::store()`'s status branch if the actual vocabulary differs.
- No blockers.

---

_Phase: 05-pos-payments_
_Completed: 2026-09-05_

## Self-Check: PASSED

All 10 files claimed as created/modified (ReconciliationController.php, ReconciliationTest.php, config/nav/accounting-staff.ts, ConfirmPaymentIntent.php, routes/portals.php, PaymongoWebhookTest.php, ConfirmPaymentIntentTest.php, JobOrderPayment.vue, cashier/Dashboard.vue, accounting-staff/Dashboard.vue) plus this SUMMARY.md were verified present via `ls -la`. Both commit hashes (`dfeeb1c`, `891bdd9`) were verified present in `git log`.
