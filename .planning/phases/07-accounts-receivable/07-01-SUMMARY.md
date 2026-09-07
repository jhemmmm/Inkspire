---
phase: 07-accounts-receivable
plan: 01
subsystem: database

tags: [laravel, eloquent, migrations, enums, accounts-receivable, aging]

# Dependency graph
requires:
  - phase: 05-pos-payments
    provides: "accounts_receivable table (balance-only), CreditApprovalController approve()/reject() flow, PaymentStatus enum"
provides:
  - "accounts_receivable schema extended with due_at/last_reminder_bracket/last_reminder_sent_at/collection_status/write_off_* columns, idempotently backfilled"
  - "AccountsReceivableAgingBracket enum (rank(), reminderBearing()) and AccountsReceivableCollectionStatus enum"
  - "PaymentStatus::WrittenOff terminal case, rendered on Cashier and Frontline Staff dashboards"
  - "AccountsReceivable::agingBracket()/daysPastDue() pure derived-from-due_at methods, writeOffRequestedBy() relation"
  - "credit_term_days business_rules config key (default 30)"
  - "CreditApprovalController::approve() stamps due_at once, at approval time"
affects: [07-02, 07-03, 07-04, 07-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Migration backfill logic extracted into a private, Reflection-testable method (WR-10 shape) since up() is not re-invocable once already migrated under RefreshDatabase"
    - "Stamp-once derived date at the triggering event (due_at at approval), never recomputed from a later config change"

key-files:
  created:
    - database/migrations/2026_09_08_090000_add_aging_and_collection_columns_to_accounts_receivable_table.php
    - app/Enums/AccountsReceivableAgingBracket.php
    - app/Enums/AccountsReceivableCollectionStatus.php
    - tests/Feature/AccountsReceivable/AgingBracketTest.php
  modified:
    - app/Enums/PaymentStatus.php
    - app/Models/AccountsReceivable.php
    - app/Http/Controllers/Owner/CreditApprovalController.php
    - database/factories/AccountsReceivableFactory.php
    - database/seeders/SystemConfigurationSeeder.php
    - resources/js/pages/cashier/Dashboard.vue
    - resources/js/pages/frontline-staff/Dashboard.vue
    - tests/Feature/Owner/CreditApprovalTest.php
    - tests/Unit/SystemConfigurationTest.php

key-decisions:
  - "Extracted the backfill into a private backfillDueDates() method rather than calling up() twice in tests, following the WR-10 precedent (JobOrderNumberBackfillTest) — up()'s schema mutations are not re-invocable once RefreshDatabase has already run the migration"
  - "Fixed AccountsReceivableFactory::active()'s pre-existing bug (approved_by received an uninstantiated UserFactory instead of a created user id) since Task 2 required editing this exact closure and the bug blocked the new idempotency test"

patterns-established:
  - "AccountsReceivableAgingBracket departs from the plain string-enum convention with rank()/reminderBearing() methods, documented inline as a deliberate departure from AccountsReceivableStatus's plain-enum shape"

requirements-completed: [AR-01, AR-02, AR-03, AR-04]

# Metrics
duration: 25min
completed: 2026-09-08
---

# Phase 7 Plan 01: Accounts Receivable Schema & Aging Substrate Summary

**Extended `accounts_receivable` with due_at/aging/collection/write-off columns plus an idempotent backfill, two new string-backed enums (AccountsReceivableAgingBracket with rank()/reminderBearing(), AccountsReceivableCollectionStatus), a `PaymentStatus::WrittenOff` terminal case rendered on both existing payment-status dashboards, and closed the pattern-mapper-flagged gap where CreditApprovalController::approve() never stamped a due date.**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-09-08T07:04:00+08:00 (approx, worktree setup)
- **Completed:** 2026-09-08T07:17:03+08:00
- **Tasks:** 2 completed
- **Files modified:** 14 (7 in Task 1, 7 in Task 2 including deviations)

## Accomplishments
- `accounts_receivable` gained every column D-02/D-03/D-07/D-09/D-13/D-14 need, with a re-runnable backfill that stamps `due_at` on every pre-existing Active row from `approved_at + credit_term_days`
- `AccountsReceivableAgingBracket::agingBracket()`/`daysPastDue()` are pure, query-free functions on the model, correct at every 15/30/60/90-day boundary and matching D-03's five bands
- `CreditApprovalController::approve()` now stamps `due_at` once, at approval, immune to later `credit_term_days` config changes (Pitfall 6)
- `PaymentStatus::WrittenOff` renders correctly on the Cashier and Frontline Staff dashboards instead of a blank cell (regression closure moved from 07-05 per checker Blocker 3)

## Task Commits

Each task was committed atomically:

1. **Task 1: Migration, enums, and the credit_term_days config key** - `557b526` (feat)
2. **Task 2: Model mechanics, due_at stamp at approval, and factory support** - RED `2c2a6fe` (test) → GREEN `74b06cc` (feat)

_TDD task 2 has two commits (test → feat); no refactor commit was needed._

## Files Created/Modified
- `database/migrations/2026_09_08_090000_add_aging_and_collection_columns_to_accounts_receivable_table.php` - new columns + idempotent `backfillDueDates()` private method (WR-10 shape)
- `app/Enums/AccountsReceivableAgingBracket.php` - six-case aging bracket enum with `rank()`/`reminderBearing()`
- `app/Enums/AccountsReceivableCollectionStatus.php` - six-case collection status enum (D-10)
- `app/Enums/PaymentStatus.php` - appended `WrittenOff` case
- `app/Models/AccountsReceivable.php` - new casts, `writeOffRequestedBy()` relation, `agingBracket()`/`daysPastDue()`
- `app/Http/Controllers/Owner/CreditApprovalController.php` - `approve()` now stamps `due_at`
- `database/factories/AccountsReceivableFactory.php` - `active()` stamps `due_at` (and fixes a latent `approved_by` bug); new `atBracket()` state
- `database/seeders/SystemConfigurationSeeder.php` - seeded `credit_term_days` (business_rules, default 30)
- `resources/js/pages/cashier/Dashboard.vue`, `resources/js/pages/frontline-staff/Dashboard.vue` - `written_off` label + badge
- `tests/Feature/Owner/CreditApprovalTest.php` - two new tests for D-02/Pitfall 6
- `tests/Feature/AccountsReceivable/AgingBracketTest.php` - new file, boundary + idempotency tests
- `tests/Unit/SystemConfigurationTest.php` - seeded-key count updated 15→16

## Decisions Made
- Extracted the migration's backfill logic into a private `backfillDueDates()` method (Reflection-invoked in tests) rather than literally calling `up()` twice, since `up()`'s schema-altering `Schema::table()` block is not re-invocable once RefreshDatabase has already migrated it — this exactly mirrors the existing WR-10 precedent in `2026_09_05_120000_add_number_and_due_at_to_job_orders_table.php` / `JobOrderNumberBackfillTest.php`, which the plan's own Context explicitly pointed to ("reuse that shape rather than rediscovering it").

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed `AccountsReceivableFactory::active()`'s pre-existing `approved_by` bug**
- **Found during:** Task 2 (writing the backfill idempotency test)
- **Issue:** `forceFill(['approved_by' => User::factory()->owner(), ...])` passed an uninstantiated `UserFactory` instance instead of a created user's id, throwing `Object of class Database\Factories\UserFactory could not be converted to string` the moment `->active()->create()` was actually exercised (never previously used by any test)
- **Fix:** Changed to `User::factory()->owner()->create()->id`
- **Files modified:** `database/factories/AccountsReceivableFactory.php`
- **Verification:** `AgingBracketTest`'s backfill idempotency test passes
- **Committed in:** `74b06cc` (Task 2 commit)

**2. [Rule 1 - Bug, test-only] Updated `SystemConfigurationTest`'s seeded-key count 15→16**
- **Found during:** Task 2 (full suite regression sweep)
- **Issue:** Task 1 added a 16th `business_rules` key (`credit_term_days`), breaking a pre-existing test that hardcoded `count()` to be `15`
- **Fix:** Updated both assertions and the test name to `16`
- **Files modified:** `tests/Unit/SystemConfigurationTest.php`
- **Verification:** `vendor/bin/pest tests/Unit/SystemConfigurationTest.php` passes; full suite (402 tests) passes
- **Committed in:** `74b06cc` (Task 2 commit)

**3. [Restructure for testability, not a bug] Migration backfill extracted into a private method**
- **Found during:** Task 2 (writing the WR-10-shape idempotency test)
- **Issue:** The plan's literal instruction to `require(...)->up()` twice inside a test fails on SQLite with "duplicate column name" — `up()`'s schema-altering block is not re-invocable once RefreshDatabase has already run the real migration once
- **Fix:** Extracted the backfill loop into a private `backfillDueDates()` method (matching the codebase's existing WR-10 precedent exactly), invoked via `ReflectionMethod` in the test — `up()`/`down()` stay Laravel's expected public surface
- **Files modified:** `database/migrations/2026_09_08_090000_add_aging_and_collection_columns_to_accounts_receivable_table.php`, `tests/Feature/AccountsReceivable/AgingBracketTest.php`
- **Verification:** Idempotency test passes; `php artisan migrate:fresh` runs clean
- **Committed in:** `74b06cc` (Task 2 commit)

---

**Total deviations:** 3 auto-fixed (2 Rule 1 bug fixes, 1 testability restructure matching an established codebase precedent)
**Impact on plan:** All necessary for the plan's own stated tests to pass or for the full suite to stay green. No scope creep — no files outside the plan's `files_modified` list were changed except the one pre-existing regression test (`SystemConfigurationTest.php`) directly broken by Task 1's seeder change.

## Issues Encountered
- This worktree had no `vendor/`, `node_modules/`, `.env`, or SQLite database — ran `composer install`, `npm install`, `cp .env.example .env`, `php artisan key:generate`, `touch database/database.sqlite`, and `php artisan migrate:fresh` to establish a working baseline before any plan work. `npm install` briefly renamed `package-lock.json`'s `name` field to the worktree directory name; reverted with `git checkout -- package-lock.json` before committing.
- `composer types:check` (Larastan level 7) fails on 3 pre-existing files unrelated to this plan (`CreateCreditRequestRequest.php`, `SavePricingAndPaymentRequest.php`, `UpdateSystemConfigurationRequest.php` — a route-model-binding type-inference gap, last touched in Phase 5). Not caused by this plan's changes; logged to `.planning/phases/07-accounts-receivable/deferred-items.md` rather than fixed, per the scope boundary (none of these files are in 07-01's `files_modified`). Every other verification command (`migrate:status`, `pint`, the full 402-test Pest suite, `npm run types:check`) passes clean.

## Known Stubs

None.

## Threat Flags

None — the plan's own `<threat_model>` fully covers the surface this plan touches (`due_at` tamper-resistance via `forceFill`-only writes, no client-supplied `due_at` path, existing `AuditObserver` coverage). No new endpoints, auth paths, or trust boundaries were introduced.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `due_at`, `agingBracket()`, `daysPastDue()`, `collection_status`, and the write-off columns are all live and tested — 07-02 through 07-05 (reminders, collection status UI, collection letters, write-off approval) can build directly on this substrate with no further schema changes expected.
- No blockers. The one pre-existing Larastan gap (see Issues Encountered) is orthogonal to Phase 7 and should get its own small fix plan.

---
*Phase: 07-accounts-receivable*
*Completed: 2026-09-08*

## Self-Check: PASSED

All 15 claimed files verified present on disk; all 4 claimed commit hashes (`557b526`, `2c2a6fe`, `74b06cc`, `bb8e3f8`) verified present in `git log --oneline --all`.
