# Deferred Items — Phase 1

Pre-existing issues discovered during execution that are out of scope for the current task
(not caused by this plan's changes).

## 01-01: Larastan — `UserFactory::withTwoFactor()` missing return statement

- **File:** `database/factories/UserFactory.php:49`
- **Issue:** `public function withTwoFactor(): static {}` has an empty body but declares a `static` return type. Larastan (level 7) flags `return.missing`.
- **Pre-existing:** Yes — this method was already present, unmodified, before Plan 01-01 touched this file (only `definition()` and new role-state methods were added/changed).
- **Status:** Not fixed. Out of scope per plan scope-boundary rules. Revisit when a later plan actually implements two-factor auth factory support (or removes the stub).

## 01-03: Larastan (`composer types:check`) — flaky/broken independent of code changes

- **Observed during:** Plan 01-03, Task 2, pre-commit verification.
- **Issue:** `composer types:check` intermittently fails with two different unrelated errors across consecutive runs with zero code changes in between: (1) the pre-existing `UserFactory::withTwoFactor()` `return.missing` error above, and (2) `In LarastanStubFilesExtension.php line 25: Undefined constant "Larastan\Larastan\LARAVEL_VERSION"` — an internal Larastan/vendor package error, not a finding about application code under `app/`.
- **Pre-existing/environmental:** Yes — reproduced identically with `rm` of local phpstan cache; not caused by any file touched in this plan (`database/migrations/2026_08_31_171450_create_system_configurations_table.php`, `app/Models/SystemConfiguration.php`, `database/seeders/SystemConfigurationSeeder.php`, `database/seeders/DatabaseSeeder.php`, `tests/Unit/SystemConfigurationTest.php`).
- **Status:** Not fixed. Out of scope per plan scope-boundary rules — this is vendor/tooling instability in the Larastan package itself (unrelated to `composer show larastan/larastan` version pinning investigation, which was not undertaken here). Pint (`vendor/bin/pint --dirty`) passed cleanly on all files this plan touched, and the full Pest suite (33 tests, 29 passed, 4 pre-existing skips) passed. Revisit if a future plan needs a clean `types:check` run — may require `composer update larastan/larastan` or a PHPStan cache/vendor reinstall.
