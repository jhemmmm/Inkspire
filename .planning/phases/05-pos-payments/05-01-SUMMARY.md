---
phase: 05-pos-payments
plan: 01
subsystem: payments
tags: [laravel, inertia, vue3, pest, pricing, pos, payments, decimal-cast]

# Dependency graph
requires:
    - phase: 03-job-order-intake-auto-assignment
      provides: job_orders table, JobOrderStatus enum (ready_for_production, design_approved)
    - phase: 01-foundation-rbac-auth-hardening-audit-trail
      provides: role:cashier middleware group, SystemConfiguration model/pattern, AuditObserver
provides:
    - pricing_database catalog table + PricingEntry model
    - transactions ledger table + Transaction model (job_order_id NOT NULL, no standalone sales)
    - job_orders payment columns (payment_status, pricing_entry_id, base_price_snapshot, rush_fee_amount, discount_type/value/amount, total_amount, cancelled_at)
    - PaymentStatus/PaymentMethod/TransactionType/TransactionStatus enums
    - ComputeJobOrderPrice server-authoritative pricing action
    - Cashier Dashboard + Job Order Payment page (Cash/Bank Transfer, full or down payment)
affects:
    [
        05-02,
        05-03,
        05-04,
        05-05,
        05-06,
        05-07,
        07-accounts-receivable,
        08-reporting,
    ]

# Tech tracking
tech-stack:
    added: []
    patterns:
        - 'Price snapshot at POS time (base_price_snapshot/rush_fee_amount/discount_amount/total_amount), never live-recomputed from pricing_database on read'
        - 'SavePricingAndPaymentRequest conditionally validates pricing fields only when job_orders.total_amount is still null, ignoring resubmitted pricing on follow-up/balance visits'
        - 'SystemConfiguration::getFloat() added alongside existing getInt/getBool/getArray/getString for decimal-typed config keys'

key-files:
    created:
        - app/Actions/POS/ComputeJobOrderPrice.php
        - app/Http/Controllers/Cashier/PaymentController.php
        - app/Http/Controllers/Cashier/DashboardController.php
        - app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php
        - app/Concerns/PricingValidationRules.php
        - app/Concerns/PaymentValidationRules.php
        - app/Models/PricingEntry.php
        - app/Models/Transaction.php
        - resources/js/pages/cashier/JobOrderPayment.vue
    modified:
        - app/Models/JobOrder.php
        - app/Models/SystemConfiguration.php
        - routes/portals.php
        - resources/js/pages/cashier/Dashboard.vue

key-decisions:
    - "Reordered generated migration timestamps so create_pricing_database_table runs before add_payment_columns_to_job_orders_table, since the latter's pricing_entry_id FK constrains against pricing_database"
    - "Cashier Dashboard's Actions column implements only the single 'Process Payment' link this plan (per PLAN.md Task 3 literal instruction); the full contextual DropdownMenu from UI-SPEC is deferred to Plans 05-02 through 05-07 as each adds its own action"

patterns-established:
    - 'Pattern: money-computation Actions (app/Actions/POS/**) are pure, non-transactional, return an array shape — persistence and DB::transaction() wrapping stays in the controller'
    - 'Pattern: FormRequest conditionally narrows its own rules() based on already-persisted model state (total_amount !== null) rather than a separate FormRequest class per visit type'

requirements-completed: [POS-01, POS-02, POS-05]

# Metrics
duration: 70min
completed: 2026-09-04
---

# Phase 5 Plan 1: POS pricing and Cash/Bank Transfer payments Summary

**Server-authoritative pricing snapshot (`ComputeJobOrderPrice`) plus a combined Pricing+Payment Cashier page that records Cash/Bank Transfer payments, full or as a tracked down payment, against `ready_for_production`/`design_approved` job orders.**

## Performance

- **Duration:** ~70 min (includes worktree environment setup — see Issues Encountered)
- **Started:** 2026-09-04T20:32:02+08:00 (base commit)
- **Completed:** 2026-09-04T21:42:13+08:00
- **Tasks:** 3/3 completed
- **Files modified:** 30 (16 created, 14 modified)

## Accomplishments

- Three new tables (`pricing_database`, `transactions`) and one additive-column migration (`job_orders` payment columns) applied cleanly
- `ComputeJobOrderPrice` action implements the exact base price + rush fee + discount + total formula, snapshotting the Cashier's confirmed line amount rather than trusting a live catalog price or a client-submitted total
- `SavePricingAndPaymentRequest` correctly distinguishes a first-ever pricing/payment visit from a follow-up balance visit, ignoring resubmitted pricing fields once `total_amount` is set
- Cashier can price a job order (catalog pick, adjustable line amount, rush fee toggle, capped discount) and record a Cash or Bank Transfer payment — full or down — in one submission, with `payment_status`/remaining balance derived from summed completed transactions
- Full Cashier Dashboard + Job Order Payment Vue pages wired end-to-end through Wayfinder-generated routes

## Task Commits

Each task was committed atomically:

1. **Task 1: Data foundation — payment schema, enums, models, factories, seeder** - `7634ce7` (feat)
2. **Task 2: Backend — price computation and Cash/Bank Transfer payment recording** - `c433a94` (feat)
3. **Task 3: Frontend — Cashier Dashboard and Job Order Payment page** - `7b478a1` (feat)

_No plan-metadata commit yet — SUMMARY.md commit follows this file._

## Files Created/Modified

**Task 1 (schema/models):**

- `database/migrations/2026_09_04_090000_create_pricing_database_table.php` - catalog table
- `database/migrations/2026_09_04_090001_add_payment_columns_to_job_orders_table.php` - payment_status, pricing_entry_id FK, snapshot columns, total_amount, cancelled_at
- `database/migrations/2026_09_04_090002_create_transactions_table.php` - ledger table, job_order_id NOT NULL
- `app/Enums/{PaymentStatus,PaymentMethod,TransactionType,TransactionStatus}.php`
- `app/Models/PricingEntry.php`, `app/Models/Transaction.php` - both `#[ObservedBy(AuditObserver::class)]`
- `app/Models/JobOrder.php` - `pricingEntry()`/`transactions()` relations, payment-column casts
- `app/Models/SystemConfiguration.php` - `getFloat()` helper
- `database/factories/PricingEntryFactory.php`, `database/factories/TransactionFactory.php`
- `database/seeders/PricingDatabaseSeeder.php` - 8-row starter catalog
- `database/seeders/SystemConfigurationSeeder.php` - `discount_cap_percentage`/`discount_cap_flat_amount` keys added
- `database/seeders/DatabaseSeeder.php` - calls `PricingDatabaseSeeder`

**Task 2 (backend):**

- `app/Actions/POS/ComputeJobOrderPrice.php`
- `app/Concerns/PricingValidationRules.php`, `app/Concerns/PaymentValidationRules.php`
- `app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php`
- `app/Http/Controllers/Cashier/DashboardController.php`, `app/Http/Controllers/Cashier/PaymentController.php`
- `routes/portals.php` - `cashier.dashboard`, `cashier.job-orders.payment.edit`/`.store`
- `tests/Feature/Cashier/PricingComputationTest.php`, `tests/Feature/Cashier/RecordPaymentTest.php`
- `tests/Unit/SystemConfigurationTest.php` - seeded-row count assertion updated 12→14

**Task 3 (frontend):**

- `resources/js/config/nav/cashier.ts`
- `resources/js/pages/cashier/Dashboard.vue` - replaced placeholder with eligible job orders table
- `resources/js/pages/cashier/JobOrderPayment.vue` - Pricing + Payment cards, single combined submit
- `tests/Feature/Cashier/CashierPagesTest.php` - Inertia render coverage for both pages

## Decisions Made

- Reordered the three Task 1 migrations from the plan's literal filename order (`090000_add_payment_columns`, `090001_create_pricing_database`, `090002_create_transactions`) to (`090000_create_pricing_database`, `090001_add_payment_columns`, `090002_create_transactions`), since `add_payment_columns_to_job_orders_table`'s `pricing_entry_id` foreign key constrains against `pricing_database`, which must exist first. Filenames were renamed to preserve the plan's intended date but corrected ordering.
- Cashier Dashboard's Actions column ships with only the single "Process Payment" link this plan, following PLAN.md Task 3's literal instruction rather than UI-SPEC's full five-action `DropdownMenu` (which explicitly depends on capabilities Plans 05-02 through 05-07 haven't built yet).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Reordered Task 1 migration timestamps**

- **Found during:** Task 1
- **Issue:** Plan's literal filenames ordered `add_payment_columns_to_job_orders_table` (090000) before `create_pricing_database_table` (090001), but the former's `pricing_entry_id` foreign key constrains against `pricing_database`, which wouldn't exist yet when the migration ran
- **Fix:** Swapped the two migrations' timestamps (pricing_database now 090000, add_payment_columns now 090001); create_transactions stays 090002
- **Files modified:** the two migration files (renamed)
- **Verification:** `php artisan migrate:fresh --seed` runs clean, `php artisan migrate:status` shows all three `Ran`
- **Committed in:** `7634ce7` (Task 1 commit)

**2. [Rule 1 - Bug] Fixed pre-existing test broken by the new seeder rows**

- **Found during:** Task 2 (running the full test suite as a regression check)
- **Issue:** `tests/Unit/SystemConfigurationTest.php` hardcoded an assertion of `12` seeded `system_configurations` rows; Task 1 added two new rows (`discount_cap_percentage`, `discount_cap_flat_amount`) to `SystemConfigurationSeeder`, making the real count 14
- **Fix:** Updated the test's expected count from 12 to 14 and renamed the test description to match
- **Files modified:** `tests/Unit/SystemConfigurationTest.php`
- **Verification:** `php artisan test --compact --filter=SystemConfigurationTest` passes (8/8)
- **Committed in:** `c433a94` (Task 2 commit)

**3. [Rule 1 - Bug] Fixed a PHPDoc return-type mismatch flagged by static analysis**

- **Found during:** Task 2, running `composer types:check`
- **Issue:** `SavePricingAndPaymentRequest::rules()`'s `@return` annotation didn't include `\Closure` even though the merged `pricingRules()`/`paymentRules()` return value can contain a closure-based discount-cap validation rule
- **Fix:** Widened the `@return` annotation to include `\Closure`
- **Files modified:** `app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php`
- **Verification:** re-ran `composer types:check` — this specific error no longer appears
- **Committed in:** `c433a94` (Task 2 commit)

---

**Total deviations:** 3 auto-fixed (1 blocking/migration-order, 1 bug/pre-existing-test, 1 bug/type-annotation)
**Impact on plan:** All three were necessary corrections uncovered while implementing the plan as specified — no scope creep, no architectural changes.

## Issues Encountered

- **Worktree had no `vendor/`, `node_modules/`, or `.env`.** This is a parallel git-worktree execution with no prior setup; created `.env` from `.env.example`, generated an app key, and provisioned a worktree-local SQLite database. Initially symlinked `vendor`/`node_modules` from the main checkout for speed, but this caused a PHP fatal error (`Cannot redeclare class ComposerAutoloaderInit...`) — packages whose `vendor/bin/*` scripts self-locate the autoloader relative to their own realpath resolved back to the _main repo's_ `vendor/autoload.php` instead of the worktree's, which would have silently run tests against the wrong `app/` code. Resolved by fully copying `vendor` (160MB) into the worktree and regenerating the autoloader locally (`composer dump-autoload`). Also ran `npm run build` once to generate `public/build/manifest.json` (gitignored, absent in a fresh worktree), which several existing Inertia-page-rendering feature tests require.
- **`composer types:check` (Larastan/PHPStan) intermittently failed with an unrelated "Undefined constant LARAVEL_VERSION" error**, traced to a shared `/tmp/phpstan` result-cache directory (not worktree-isolated) most likely left in an inconsistent state by a concurrent/prior run. This is an environment artifact, not a code defect — one genuine finding it did surface (deviation #3 above) was fixed; the remaining noise (an unrelated pre-existing `QueueEntryController.php` match-arm warning and the same route-model-binding `object|string` inference pattern already present in `UpdateSystemConfigurationRequest.php`) was left alone as out-of-scope/pre-existing, consistent with the SCOPE BOUNDARY rule. PHPStan is not part of this plan's `<verification>` gate (only Pint, tests, and `npm run types:check` are), so this did not block completion.
- **No PHP-side automated test previously covered `PaymentController::edit`'s GET Inertia render** (the plan's Task 3 acceptance criteria only asked for a manual browser check). Added `tests/Feature/Cashier/CashierPagesTest.php` covering both the Dashboard and Job Order Payment page renders — this both closes a real coverage gap and served as the automated substitute for the plan's "Manual verification: `php artisan serve` + logging in as a seeded Cashier..." acceptance criterion, since no interactive browser tool was available in this execution context.

## Next Phase Readiness

- The shared data foundation (`pricing_database`, `transactions`, `job_orders` payment columns, `ComputeJobOrderPrice`) that every remaining Phase 5 plan depends on is in place and tested.
- Plan 05-02 (receipts) can read `job_orders.total_amount`/`transactions` directly — no further schema changes needed for that.
- Plan 05-03 (GCash/Maya via PayMongo) will widen `PaymentValidationRules::paymentRules()`'s `payment_method` allowed-values list (currently narrowed to Cash/Bank Transfer only, by design) and add its own `TransactionStatus::PendingConfirmation` handling on top of the `Transaction`/`JobOrder` models already built here.
- Plan 05-05 (cancellation) and 05-07 (release) will each extend `DashboardController::index`'s eligibility query with additional `whereNull()` guards, per the one-line PHPDoc comment left in that method.
- No blockers.

---

_Phase: 05-pos-payments_
_Completed: 2026-09-04_

## Self-Check: PASSED

All 16 files claimed as created/modified in this summary were verified present via `git ls-files`. All 3 task commit hashes (`7634ce7`, `c433a94`, `7b478a1`) were verified present in `git log`.
