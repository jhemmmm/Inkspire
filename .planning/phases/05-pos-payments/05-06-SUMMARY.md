---
phase: 05-pos-payments
plan: 06
subsystem: payments
tags:
    [laravel, inertia, vue3, pest, accounts-receivable, on-credit, policy, rbac]

# Dependency graph
requires:
    - phase: 05-pos-payments (05-01)
      provides: job_orders payment columns, Transaction/PaymentStatus enums, ComputeJobOrderPrice, Cashier Job Order Payment page
    - phase: 05-pos-payments (05-05)
      provides: JobOrderPayment.vue's established Cashier submission conventions, cancelled_at eligibility precedent
provides:
    - accounts_receivable table + AccountsReceivable model/factory (balance-only, no due date/term per D-09)
    - AccountsReceivableStatus enum (pending_approval/active/rejected)
    - AccountsReceivablePolicy — this codebase's second Owner-exclusive-not-Admin authorization check (after DesignFilePolicy::unlock)
    - Cashier On-Credit request flow (CreditRequestController) and Owner Credit Requests approval queue (CreditApprovalController)
    - JobOrder::accountsReceivable() HasOne relation
affects: [05-07, 07-accounts-receivable]

# Tech tracking
tech-stack:
    added: []
    patterns:
        - "Owner-exclusive-not-Admin Policy gate matching DesignFilePolicy::unlock()'s shape: route-group middleware stays role:owner,admin for page visibility, FormRequest::authorize() narrows the mutation to Owner only via Policy::can()"
        - "On-Credit request reads pricing-snapshot input via $request->input() (not validated()) when total_amount is null, since CreateCreditRequestRequest::rules() is deliberately empty per D-08's open-eligibility model"

key-files:
    created:
        - database/migrations/2026_09_04_100000_create_accounts_receivable_table.php
        - app/Enums/AccountsReceivableStatus.php
        - app/Models/AccountsReceivable.php
        - database/factories/AccountsReceivableFactory.php
        - app/Policies/AccountsReceivablePolicy.php
        - app/Http/Controllers/Cashier/CreditRequestController.php
        - app/Http/Controllers/Owner/CreditApprovalController.php
        - app/Http/Requests/Cashier/CreateCreditRequestRequest.php
        - app/Http/Requests/Owner/ApproveCreditRequest.php
        - app/Http/Requests/Owner/RejectCreditRequest.php
        - tests/Feature/Owner/CreditApprovalTest.php
        - resources/js/pages/owner/CreditRequests.vue
    modified:
        - app/Models/JobOrder.php
        - routes/portals.php
        - routes/owner.php
        - resources/js/pages/cashier/JobOrderPayment.vue
        - resources/js/config/nav/owner.ts

key-decisions:
    - "Added protected $table = 'accounts_receivable' to the model — Eloquent's default pluralization guesses accounts_receivables, but the migration/ERD both use the singular accounts_receivable"
    - "CreditRequestController::store redirects via to_route('cashier.dashboard') instead of back(), since the submitting <Form> lives on the Job Order Payment page itself — back() would return there (now stale), not the Dashboard UI-SPEC specifies"
    - "On Credit's AlertDialog confirm button calls router.post() directly (not a nested <Form>) since it sits inside the page's single outer Pricing+Payment <Form>, matching the existing checkPaymentStatus() precedent for HTML's no-nested-forms rule"

patterns-established:
    - "Pattern: an AlertDialog-gated action embedded inside a larger single-page <Form> submits via router.post() from its confirm button's @click handler, not a nested <Form>, collecting the needed field values from the same refs the outer form's inputs are bound to"

requirements-completed: [POS-08]

# Metrics
duration: 40min
completed: 2026-09-05
---

# Phase 5 Plan 6: On-Credit request and Owner approval Summary

**Cashier can request On-Credit for a job order's full outstanding balance from the existing Job Order Payment page; only the Owner (never Admin) can approve or reject it, posting a balance-only `accounts_receivable` row via a Policy-gated approval queue.**

## Performance

- **Duration:** ~40 min (includes worktree environment setup — see Issues Encountered)
- **Started:** 2026-09-05T00:03:00Z (approx, first commit 08:03:54+08:00)
- **Completed:** 2026-09-05T00:29:10Z
- **Tasks:** 3/3 completed
- **Files modified:** 17 (12 created, 5 modified)

## Accomplishments

- `accounts_receivable` table, `AccountsReceivableStatus` enum, `AccountsReceivable` model/factory — balance-only, no due date/term column (Phase 7's job to add)
- `AccountsReceivablePolicy::approve()`/`reject()` — this codebase's second Owner-exclusive-not-Admin authorization check, directly matching `DesignFilePolicy::unlock()`'s shape
- `CreditRequestController@store` — open-eligibility On-Credit request (D-08), posts the correct _remaining_ outstanding balance (not the original total) when a down payment already exists
- `CreditApprovalController@index/approve/reject` — Admin can view the queue, only Owner can mutate it; approval/rejection both wrapped in `DB::transaction()` per the Money-Moving precedent
- Cashier's Job Order Payment page gained a fifth "On Credit" payment method with its own `AlertDialog` confirmation; Owner gained a full Credit Requests approval queue reachable from the nav

## Task Commits

Each task was committed atomically:

1. **Task 1: Data foundation — accounts_receivable schema, model, policy** - `06689f0` (feat)
2. **Task 2: Backend — Cashier credit request and Owner approve/reject** - `891634f` (feat)
3. **Task 3: Frontend — On Credit option and Owner Credit Requests page** - `28dffd3` (feat)

_No plan-metadata commit yet — SUMMARY.md commit follows this file._

## Files Created/Modified

**Task 1 (schema/models):**

- `database/migrations/2026_09_04_100000_create_accounts_receivable_table.php` - balance-only AR table
- `app/Enums/AccountsReceivableStatus.php` - `PendingApproval`/`Active`/`Rejected`
- `app/Models/AccountsReceivable.php` - explicit `$table`, casts, `jobOrder()`/`requestedBy()`/`approvedBy()` relations
- `database/factories/AccountsReceivableFactory.php` - `active()`/`rejected()` states via `afterCreating()`/`forceFill()`
- `app/Policies/AccountsReceivablePolicy.php` - Owner-only `approve()`/`reject()`
- `app/Models/JobOrder.php` - `accountsReceivable(): HasOne`

**Task 2 (backend):**

- `app/Http/Controllers/Cashier/CreditRequestController.php`
- `app/Http/Controllers/Owner/CreditApprovalController.php`
- `app/Http/Requests/Cashier/CreateCreditRequestRequest.php`, `app/Http/Requests/Owner/{Approve,Reject}CreditRequest.php`
- `routes/portals.php`, `routes/owner.php` - new routes
- `tests/Feature/Owner/CreditApprovalTest.php` - 5 cases (admin-forbidden, owner-approve, owner-reject, cashier-request, duplicate-request-blocked)

**Task 3 (frontend):**

- `resources/js/pages/cashier/JobOrderPayment.vue` - "On Credit" `RadioGroupItem` + `AlertDialog` confirm flow
- `resources/js/pages/owner/CreditRequests.vue` - approval queue (new)
- `resources/js/config/nav/owner.ts` - "Credit Requests" nav entry

## Decisions Made

- Added `protected $table = 'accounts_receivable'` to the model since Eloquent's default pluralization convention would resolve to `accounts_receivables`, which doesn't exist (caught by the first test run — see Deviations).
- `CreditRequestController::store` uses `to_route('cashier.dashboard')` instead of `back()`, per the plan's own explicit instruction to verify this rather than assume — the submitting `<Form>` lives on the Job Order Payment page itself, so `back()` would return there (now stale post-request), not the Dashboard the UI-SPEC specifies.
- Followed the plan's literal instruction that `CreateCreditRequestRequest::rules()` returns `[]` even for the pricing-snapshot fields read when `total_amount` is null; the controller reads those via `$request->input()` rather than `$request->validated()` since there's nothing to validate against. See Threat Flags below.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Added explicit `$table` property to `AccountsReceivable`**

- **Found during:** Task 2 (running the new feature tests)
- **Issue:** `AccountsReceivable::create()` failed with `SQLSTATE[HY000]: no such table: accounts_receivables` — Eloquent's default snake_case-plural table-name convention resolves `AccountsReceivable` to `accounts_receivables`, but the migration (and the approved 12-table ERD) both use the singular `accounts_receivable`
- **Fix:** Added `protected $table = 'accounts_receivable';` to the model with a PHPDoc explaining the mismatch
- **Files modified:** `app/Models/AccountsReceivable.php`
- **Verification:** `php artisan test --compact --filter=OnCredit` passes
- **Committed in:** `891634f` (Task 2 commit — bundled with the Task 2 files since it was caught while writing Task 2's tests)

---

**Total deviations:** 1 auto-fixed (1 blocking/table-name mismatch)
**Impact on plan:** Necessary correction uncovered while implementing the plan as specified — no scope creep, no architectural change.

### Unintended tool side-effect (caught and reverted, not a deviation from the plan)

Running `npm run check:fix` (project's documented auto-fix command, per `CLAUDE.md`'s "Run `npm run check:fix`... to auto-fix") reformatted ~150 files across the entire repository (all `.planning/*.md`, `.claude/skills/*.md`, `CLAUDE.md`, `README.md`, `boost.json`, `.mcp.json`, and three unrelated Vue pages) — Prettier's markdown formatter added blank lines around headers repo-wide, well outside this plan's scope. Caught immediately via `git status --short` before committing; reverted every file not touched by this plan's own tasks via targeted `git checkout -- <path>` calls, verified `git status --short` showed only the 5 intended files afterward, then re-ran `npm run types:check` and the full test suite to confirm nothing broke from the revert. No unintended file made it into any commit.

## Issues Encountered

- **Worktree had no `vendor/`, `node_modules/`, `.env`, or database file** (same as every prior Phase 5 worktree-executed plan). Copied `vendor/` fully from the main checkout (symlinking previously caused a fatal autoloader-realpath collision, per 05-01's documented finding), symlinked `node_modules/` (no equivalent realpath issue for Node resolution), created `.env` from `.env.example`, generated an app key, created a fresh worktree-local SQLite DB, ran `migrate:fresh --seed`, and `npm run build` once for the Vite manifest before any tests could pass.
- **`composer types:check` (Larastan)** surfaced the same pre-existing, out-of-scope findings documented in 05-01/05-05 (`QueueEntryController.php` match-arm warning, `SavePricingAndPaymentRequest.php`/`UpdateSystemConfigurationRequest.php` route-model-binding `object|string` inference) — none in this plan's own files, left untouched per the SCOPE BOUNDARY rule. Not part of this plan's `<verification>` gate.
- **The plan's own Task 1 acceptance-criteria tinker command** (`echo App\Models\AccountsReceivable::factory()->make()->status;`) fails with "Object... could not be converted to string" — this is not a defect; every other casted-enum model in this codebase (verified against `Transaction::factory()->make()->status`) fails identically with a bare `echo`, since plain PHP backed enums aren't `Stringable`. Verified the underlying behavior instead via `->status->value`, which correctly prints `pending_approval`.

## Threat Flags

| Flag                   | File                                                       | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| ---------------------- | ---------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| threat_flag: Tampering | `app/Http/Controllers/Cashier/CreditRequestController.php` | When `total_amount` is null, the controller reads pricing-snapshot inputs (`line_amount`, `rush_fee_applied`, `discount_type`, `discount_value`) via `$request->input()` with no `FormRequest` validation, since `CreateCreditRequestRequest::rules()` is deliberately empty per the plan's literal D-08 instruction. Unlike `PaymentController::store()`'s equivalent branch (validated via `PricingValidationRules::pricingRules()` — required/numeric/min/discount-cap checks), a malformed or negative `line_amount` here is only cast to `float`/`bool`, not range-checked, before flowing into `ComputeJobOrderPrice` and being persisted as the job order's price snapshot. The AR `balance` itself remains fully server-computed and untamperable (T-05-15's actual mitigation target), but the upstream pricing snapshot it derives from has weaker input validation than the equivalent non-credit path. Left as specified since the plan explicitly calls for an empty `rules()`; flagging for a follow-up plan to decide whether to extend `CreateCreditRequestRequest` with the same `PricingValidationRules` trait used elsewhere. |

## Next Phase Readiness

- `accounts_receivable` and `AccountsReceivablePolicy` are in place; Plan 05-07 (release) can now check `payment_status === PaymentStatus::OnCredit` as one of its release-eligible states.
- Phase 7 (Accounts Receivable aging/reminders) has its sole entry point into the system: every AR row originates from an approved On-Credit request created here.
- No blockers.

---

_Phase: 05-pos-payments_
_Completed: 2026-09-05_

## Self-Check: PASSED

All 12 files claimed as created in this summary were verified present via `git ls-files`. All 3 task commit hashes (`06689f0`, `891634f`, `28dffd3`) were verified present in `git log --oneline --all`.
