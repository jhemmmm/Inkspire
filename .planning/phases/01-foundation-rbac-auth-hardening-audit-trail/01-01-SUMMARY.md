---
phase: 01-foundation-rbac-auth-hardening-audit-trail
plan: 01
subsystem: auth
tags: [laravel, eloquent, observer, enum, rbac, audit-trail, pest, sqlite]

# Dependency graph
requires: []
provides:
  - "app/Enums/UserRole.php — 7-case backed enum with portalRoute() used by every later RBAC plan"
  - "users table: role, is_active, failed_login_attempts, locked_until, current_session_id, last_activity_at columns"
  - "audit_trail table + AuditLog model + AuditObserver + AuditLogger — structural append-only audit substrate"
  - "Working RefreshDatabase test harness (tests/Pest.php) unblocking every Phase 1 feature test"
  - "UserFactory role states (owner/admin/frontlineStaff/artist/cashier/productionStaff/accountingStaff)"
affects: [01-02, 01-03, 01-04, 01-05, 01-06, 01-07, 01-08, 01-09, 01-10, 01-11, 01-12]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "PHP 8 attribute-based Eloquent metadata (#[Fillable]/#[Hidden]/#[ObservedBy]) plus casts() method — no legacy $fillable/$casts properties"
    - "Global model observer (#[ObservedBy(AuditObserver::class)]) writing structured before/after JSON diffs to a single append-only audit_trail table"
    - "Framework-free grep-based Pest unit test for structural (not just permission-based) guarantees"

key-files:
  created:
    - app/Enums/UserRole.php
    - app/Models/AuditLog.php
    - app/Observers/AuditObserver.php
    - app/Support/AuditLogger.php
    - database/migrations/2026_08_31_165341_add_rbac_and_lockout_columns_to_users_table.php
    - database/migrations/2026_08_31_165342_create_audit_trail_table.php
    - tests/Unit/Arch/AuditLogArchTest.php
  modified:
    - tests/Pest.php
    - database/factories/UserFactory.php
    - resources/js/types/auth.ts
    - app/Models/User.php

key-decisions:
  - "users.role carries a DB-level default (UserRole::Owner) because SQLite rejects ALTER TABLE ADD COLUMN NOT NULL without one; every application creation path still sets role explicitly, so this is a schema safety net, not a behavior change"
  - "AuditLogArchTest is a pure-PHP RecursiveDirectoryIterator grep test, not a Laravel-bootstrapped test, because tests/Unit/ is not bound to Tests\\TestCase in tests/Pest.php (only tests/Feature/ is) — app_path()/File facade are unavailable without a booted container"

patterns-established:
  - "Pattern: any new domain model wanting audit coverage adds #[ObservedBy(AuditObserver::class)] — zero controller boilerplate"
  - "Pattern: AuditLogger::recordMutation()/recordAuthEvent() are the only two entry points into audit_trail; never AuditLog::create() directly outside app/Support/AuditLogger.php or app/Observers/AuditObserver.php"

requirements-completed: [RBAC-01, AUDIT-02]

# Metrics
duration: 30min
completed: 2026-09-01
---

# Phase 1 Plan 01: Foundation Data Substrate Summary

**Fixed the disabled RefreshDatabase test harness, added the 7-role `UserRole` enum, extended `users` with RBAC/lockout/session columns, and built the structural append-only `audit_trail` substrate (`AuditLog` + `AuditObserver` + `AuditLogger`) wired onto `User` via `#[ObservedBy]`.**

## Performance

- **Duration:** ~30 min
- **Started:** 2026-08-31T23:50:00+08:00 (approx.)
- **Completed:** 2026-09-01T00:59:17+08:00
- **Tasks:** 2/2 completed
- **Files modified:** 11 (7 created, 4 modified)

## Accomplishments
- `tests/Pest.php` now runs the full Feature suite against a real migrated SQLite test database — 0 schema errors, 24 passed / 4 pre-existing skips (was 17/27 failing before this plan)
- `UserRole` backed enum (7 cases) with `portalRoute()` — the single source of truth every later RBAC plan (login redirect, role middleware, portal routing) will consume
- `users` table extended with `role`, `is_active`, `failed_login_attempts`, `locked_until`, `current_session_id`, `last_activity_at` — the full schema surface Plans 01-02 through 01-11 need
- `audit_trail` table + `AuditLog` model + `AuditObserver` + `AuditLogger`, wired onto `User` via `#[ObservedBy(AuditObserver::class)]` — verified end-to-end: creating a `User` produces exactly one `audit_trail` row with `action = 'created'` and a full `new_values` JSON snapshot, no controller code involved
- `tests/Unit/Arch/AuditLogArchTest.php` proves AUDIT-02's structural guarantee: no file under `app/` contains `AuditLog::update(` or `AuditLog::destroy(`

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix test database harness, add UserRole enum, add per-role factory states** - `bad7d3d` (feat)
2. **Task 2: Add RBAC/lockout columns, audit_trail table, AuditLog/AuditObserver/AuditLogger, arch test** - `09317ca` (feat)

## Files Created/Modified
- `tests/Pest.php` - Uncommented `->use(RefreshDatabase::class)` for the `Feature` suite
- `app/Enums/UserRole.php` - 7-case backed enum (`owner`, `admin`, `frontline_staff`, `artist`, `cashier`, `production_staff`, `accounting_staff`) with `portalRoute(): string`
- `database/factories/UserFactory.php` - `role` default (`Owner`) in `definition()` + 7 role-selecting state methods
- `resources/js/types/auth.ts` - `User` type extended with `role: string` and `is_active: boolean`
- `database/migrations/2026_08_31_165341_add_rbac_and_lockout_columns_to_users_table.php` - Adds the 6 RBAC/lockout/session columns to `users`
- `database/migrations/2026_08_31_165342_create_audit_trail_table.php` - Creates `audit_trail` (user_id, action, auditable_type/id, old_values/new_values JSON, ip_address, created_at)
- `app/Models/AuditLog.php` - Append-only model: `$table = 'audit_trail'`, `$timestamps = false`, `casts()` for JSON/datetime, `#[Fillable]`
- `app/Support/AuditLogger.php` - `recordMutation()` and `recordAuthEvent()` — the only two writers into `audit_trail`
- `app/Observers/AuditObserver.php` - `created`/`updated`/`deleted` handlers delegating to `AuditLogger::recordMutation()`
- `app/Models/User.php` - Added `role`/`is_active`/`failed_login_attempts`/`locked_until`/`current_session_id`/`last_activity_at` to `@property` block and `casts()`; added `role` to `#[Fillable]`; added `#[ObservedBy(AuditObserver::class)]`
- `tests/Unit/Arch/AuditLogArchTest.php` - Framework-free grep test proving no `AuditLog::update(`/`AuditLog::destroy(` call exists in `app/`

## Decisions Made
- Gave `users.role` a DB-level default of `UserRole::Owner->value` (see Deviations below) — a schema-level safety net only; the plan's intent that "every creation path sets it explicitly" still holds in application code.
- Wrote `AuditLogArchTest` as a pure-PHP `RecursiveDirectoryIterator` test rather than using `Illuminate\Support\Facades\File`/`app_path()`, since `tests/Unit/` is not bootstrapped against `Tests\TestCase` in `tests/Pest.php` (only `Feature/` is) — using Laravel helpers there throws "undefined method Container::path()". This keeps the test in the correct "framework-free logic" category per the project's testing-best-practices skill.
- Removed Laravel's default-scaffolded `restored`/`forceDeleted` observer methods from `AuditObserver` since `User` (and no other Phase 1 model) uses `SoftDeletes` — PROJECT.md explicitly forbids `SoftDeletes` on `users`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `users.role` needed a DB-level default to satisfy SQLite's `ALTER TABLE` constraint**
- **Found during:** Task 2, first `php artisan migrate:fresh` run
- **Issue:** The plan specified `$table->string('role')->after('password');` with no default ("every creation path sets it explicitly"). SQLite rejects `ALTER TABLE ADD COLUMN ... NOT NULL` unless a default is supplied (`SQLSTATE[HY000]: General error: 1 Cannot add a NOT NULL column with default value NULL`), regardless of whether the table has rows. This blocks every migration in the current dev/test environment (SQLite per `.env`/`phpunit.xml`).
- **Fix:** Added `->default(\App\Enums\UserRole::Owner->value)` to the column definition, matching `UserFactory`'s own default role. No application code relies on this default — every factory/seeder path still sets `role` explicitly.
- **Files modified:** `database/migrations/2026_08_31_165341_add_rbac_and_lockout_columns_to_users_table.php`
- **Verification:** `php artisan migrate:fresh --no-interaction` and `php artisan migrate:fresh --seed --no-interaction` both exit 0
- **Committed in:** `09317ca` (Task 2 commit)

**2. [Rule 3 - Blocking] `AuditLogArchTest` scaffolded location and Laravel-helper dependency**
- **Found during:** Task 2, writing the arch test
- **Issue:** `php artisan make:test --pest --unit` scaffolds into `tests/Unit/`, not `tests/Unit/Arch/` (moved manually). More significantly, the plan's literal instruction to use `App_path()`/`Illuminate\Support\Facades\File::allFiles()` fails at runtime because `tests/Unit/` is not extended with `Tests\TestCase` in `tests/Pest.php` (no booted Laravel container), producing `Call to undefined method Illuminate\Container\Container::path()`.
- **Fix:** Rewrote the test using `RecursiveDirectoryIterator`/`RecursiveIteratorIterator` and native `file_get_contents()` — no framework dependency, same grep-for-two-literal-strings behavior the plan specifies.
- **Files modified:** `tests/Unit/Arch/AuditLogArchTest.php`
- **Verification:** `php artisan test --compact --filter=AuditLogArchTest` passes
- **Committed in:** `09317ca` (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (1 bug, 1 blocking)
**Impact on plan:** Both fixes were necessary to make the plan's own acceptance criteria achievable in this environment (SQLite locally/testing, MySQL in production per PROJECT.md). No scope creep — no behavior beyond what the plan specified was added.

## Issues Encountered
- Larastan (`composer types:check`) flagged 5 errors on first run; 4 were introduced by this plan's new code (missing PHPDoc array value types on `AuditLog`, unnecessary nullsafe operator on non-nullable `Request`/`request()` calls in `AuditLogger`) and were fixed directly. The 5th (`UserFactory::withTwoFactor()` — pre-existing empty method with a `static` return type, untouched by this plan) is logged in `.planning/phases/01-foundation-rbac-auth-hardening-audit-trail/deferred-items.md` as out of scope per the scope-boundary rule.
- `php artisan db:table` (Laravel Boost/artisan helper) fails locally with "The intl PHP extension is required" — a pre-existing environment gap unrelated to this plan's changes; verified column lists instead via `Schema::getColumnListing()`, which confirmed all expected columns on both `users` and `audit_trail`.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `UserRole` enum, RBAC/lockout/session columns, and the audit trail substrate are all in place and verified — Plans 01-02 through 01-11 (login redirect, role middleware, account lockout, single session, idle timeout, password complexity, deactivation, audit trail UI, system configuration) can now build directly on this schema and on `AuditLogger`/`AuditObserver` without any further foundational changes.
- No blockers. One open item carried forward from RESEARCH.md (unrelated to this plan): Laravel Cloud's managed-MySQL restricted-privilege grant support for defense-in-depth on `audit_trail` remains unverified and explicitly deferred per D-04 — not a blocker for Phase 1.

---
*Phase: 01-foundation-rbac-auth-hardening-audit-trail*
*Completed: 2026-09-01*

## Self-Check: PASSED

All 13 created/modified files verified present on disk; both task commits (`bad7d3d`, `09317ca`) verified present in git log.
