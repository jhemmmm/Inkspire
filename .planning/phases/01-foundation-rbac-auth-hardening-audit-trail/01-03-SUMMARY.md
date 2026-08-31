---
phase: 01-foundation-rbac-auth-hardening-audit-trail
plan: 03
subsystem: config
tags: [laravel, eloquent, observer, cache, seeder, pest, sqlite, config01]

# Dependency graph
requires:
  - phase: 01-foundation-rbac-auth-hardening-audit-trail
    provides: audit_trail table + AuditObserver + AuditLogger (plan 01-01)
provides:
  - "system_configurations table (key unique, group, value json, type, label, description) — the key-value business-rule substrate for CONFIG-01"
  - "SystemConfiguration model: getInt/getBool/getArray/getString cached (Cache::rememberForever) typed accessors, invalidate(string $key), audited via #[ObservedBy(AuditObserver::class)]"
  - "SystemConfigurationSeeder: 12 idempotent (updateOrCreate) rows covering every CONFIG-01 business rule, grouped into security / business_rules / file_handling to match the UI-SPEC's 3 tabs"
affects: [01-07, 01-08, 01-12]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Cache::rememberForever(\"config.{key}\", ...) typed accessor pattern for key-value config, with an explicit invalidate() companion since rememberForever has no TTL"
    - "updateOrCreate-based seeders for idempotent reference/config data (safe to re-run without duplicating rows)"

key-files:
  created:
    - database/migrations/2026_08_31_171450_create_system_configurations_table.php
    - app/Models/SystemConfiguration.php
    - database/seeders/SystemConfigurationSeeder.php
    - tests/Unit/SystemConfigurationTest.php
  modified:
    - database/seeders/DatabaseSeeder.php

key-decisions:
  - "tests/Unit/SystemConfigurationTest.php binds itself to Tests\\TestCase + RefreshDatabase via a per-file uses() call, since tests/Unit/ is not globally bound to TestCase in tests/Pest.php (only tests/Feature/ is, per the 01-01 decision) — this keeps the plan's specified file path while still allowing $this->seed(), DB::table(), and Cache access"
  - "default_sla_days is seeded as a single global value (not per-product), matching the plan's explicit CONFIG-01 scope note: no products table exists until Phase 3/5 pricing tables land"

patterns-established:
  - "Any new key-value config lookup goes through SystemConfiguration::getInt/getBool/getArray/getString — never a raw query against system_configurations"
  - "Any future config-write path (Plan 01-12's controller) must call SystemConfiguration::invalidate($key) immediately after saving, since the cache has no TTL"

requirements-completed: [CONFIG-01]

# Metrics
duration: 10min
completed: 2026-08-31
---

# Phase 01 Plan 03: System Configuration Data Substrate Summary

**Built the `system_configurations` key-value table, a cached/typed/audited `SystemConfiguration` model, and a seeder covering all 12 CONFIG-01 business rules grouped to match the UI-SPEC's Security/Business Rules/File Handling tabs.**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-08-31T17:12:55Z (approx., per STATE.md)
- **Completed:** 2026-08-31T17:18:15Z
- **Tasks:** 2/2 completed
- **Files modified:** 5 (4 created, 1 modified)

## Accomplishments
- `system_configurations` migration: `key` (unique), `group`, `value` (json), `type`, `label`, `description` — the exact column set the plan specified
- `SystemConfiguration` model with `getInt`/`getBool`/`getArray`/`getString` static accessors, each backed by `Cache::rememberForever("config.{key}", ...)` and falling back to the caller-supplied default when no row exists; `invalidate(string $key)` companion for future write paths; `#[ObservedBy(AuditObserver::class)]` gives it the same zero-controller-code audit coverage as `User`
- `SystemConfigurationSeeder` inserts exactly 12 rows (4 security, 5 business_rules, 3 file_handling) via `updateOrCreate`, verified idempotent by running `db:seed --class=SystemConfigurationSeeder` twice in a row (still 12 rows)
- `DatabaseSeeder::run()` now calls `SystemConfigurationSeeder` — `php artisan migrate:fresh --seed` produces 12 configuration rows out of the box
- `tests/Unit/SystemConfigurationTest.php` (4 tests, 8 assertions, all passing): default fallback for a missing key, caching verified via `DB::enableQueryLog()` showing zero queries on a second cached read, full seeder idempotency + row-count assertion, and an audit-trail existence assertion proving `#[ObservedBy(AuditObserver::class)]` actually fires (not just declared)

## Task Commits

Each task was committed atomically:

1. **Task: Create system_configurations table and cached, audited SystemConfiguration model** - `3af1a04` (feat)
2. **Task: Seed all 12 CONFIG-01 business-rule keys and prove audit coverage** - `3459d68` (feat)

## Files Created/Modified
- `database/migrations/2026_08_31_171450_create_system_configurations_table.php` - Creates `system_configurations` (key unique, group, value json, type, label, description, timestamps)
- `app/Models/SystemConfiguration.php` - `#[Fillable]` + `#[ObservedBy(AuditObserver::class)]`, `casts()` for `value => array`, `getInt/getBool/getArray/getString` (shared private `resolve()` helper doing the cached raw lookup), `invalidate(string $key)`
- `database/seeders/SystemConfigurationSeeder.php` - 12 `updateOrCreate` rows across `security`/`business_rules`/`file_handling` groups, matching CONFIG-01's requirement text 1:1
- `database/seeders/DatabaseSeeder.php` - Added `$this->call(SystemConfigurationSeeder::class);`
- `tests/Unit/SystemConfigurationTest.php` - 4 tests covering default fallback, caching, seeder idempotency, and audit-trail coverage

## Decisions Made
- Bound `tests/Unit/SystemConfigurationTest.php` to `Tests\TestCase` + `RefreshDatabase` via Pest's per-file `uses()` call rather than editing the global `tests/Pest.php` binding (which only covers `Feature/`, per the 01-01 plan's documented decision) — keeps the plan's specified file path (`tests/Unit/SystemConfigurationTest.php`) working without widening the blast radius of a global Pest config change to unrelated `Unit/Arch` tests.
- Kept `default_sla_days` as a single global config value, exactly as the plan's scope note directs — no per-product override exists yet because no `products`/pricing table exists until Phase 3/5.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] `tests/Unit/` is not bound to `Tests\TestCase`, so the plan's test file needed Laravel bootstrapping added per-file**
- **Found during:** Task 1, writing `tests/Unit/SystemConfigurationTest.php`
- **Issue:** The plan specifies the test at `tests/Unit/SystemConfigurationTest.php`, but (per the 01-01 plan's own documented decision) `tests/Pest.php` only calls `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature')` — `tests/Unit/` gets no Laravel container, no `RefreshDatabase`, no `$this->seed()`, and `DB`/`Cache` facades would resolve against an unbooted container. All of the plan's required assertions (`$this->seed(...)`, `DB::table('audit_trail')`, `DB::enableQueryLog()`) need a booted, migrated Laravel test app.
- **Fix:** Added `uses(TestCase::class, RefreshDatabase::class);` at the top of the test file itself (Pest's per-file binding mechanism), rather than editing the global `tests/Pest.php` binding. This keeps the file at its plan-specified path while giving it exactly the same Laravel bootstrapping that `Feature/` tests get, without changing behavior for the unrelated `tests/Unit/Arch/AuditLogArchTest.php` or `tests/Unit/ExampleTest.php`.
- **Files modified:** `tests/Unit/SystemConfigurationTest.php`
- **Verification:** `php artisan test --compact --filter=SystemConfigurationTest` — 4/4 passed, 8 assertions
- **Committed in:** `3af1a04` (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (blocking)
**Impact on plan:** No scope creep — the fix only makes the plan's own literal test requirements (seeding, DB table assertions, query-log caching proof) actually runnable in this codebase's existing Pest configuration.

## Issues Encountered
- `composer types:check` (Larastan) is flaky in this environment independent of any change in this plan — on consecutive runs with zero code changes it alternates between the pre-existing `UserFactory::withTwoFactor()` `return.missing` error (logged in `deferred-items.md` under 01-01) and an internal `Undefined constant "Larastan\Larastan\LARAVEL_VERSION"` error from Larastan's own `LarastanStubFilesExtension.php`. Neither error references any file this plan touched. Logged as a new entry in `deferred-items.md` under 01-03. `vendor/bin/pint --dirty --format agent` passed cleanly, and the full Pest suite (33 tests: 29 passed, 4 pre-existing skips, 0 failed) passed on every run.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Plan 01-07 (account lockout) and Plan 01-08 (idle timeout) can now read `account_lockout_max_attempts`, `account_lockout_minutes`, and `session_idle_timeout_minutes` via `SystemConfiguration::getInt()` instead of hardcoded literals.
- Plan 01-12 (config CRUD UI) can build directly on this schema and the `invalidate()` helper with no further migration needed — reading, listing by `group`, and updating rows are all already supported by the model as built.
- No blockers.

---
*Phase: 01-foundation-rbac-auth-hardening-audit-trail*
*Completed: 2026-08-31*

## Self-Check: PASSED

All 6 created/modified files verified present on disk; both task commits (`3af1a04`, `3459d68`) verified present in git log.
