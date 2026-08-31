---
phase: 01-foundation-rbac-auth-hardening-audit-trail
plan: 02
subsystem: auth
tags: [laravel, fortify, password-validation, pest]

# Dependency graph
requires:
  - phase: 01-foundation-rbac-auth-hardening-audit-trail
    provides: RBAC/lockout columns, audit_trail table, UserRole enum (plan 01-01)
provides:
  - Password::defaults() enforces min(12), mixed case, letters, numbers, symbols, uncompromised() in every environment (no isProduction() carve-out)
  - Complexity-compliant fixture passwords in SecurityTest and PasswordResetTest
  - PasswordComplexityTest proving weak passwords are rejected outside production
affects: [auth, rbac, security-hardening]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Password::defaults() closure returns Password unconditionally (no environment gate) — security-relevant defaults must apply identically across environments"

key-files:
  created:
    - tests/Feature/Auth/PasswordComplexityTest.php
  modified:
    - app/Providers/AppServiceProvider.php
    - tests/Feature/Settings/SecurityTest.php
    - tests/Feature/Auth/PasswordResetTest.php

key-decisions:
  - "Left DB::prohibitDestructiveCommands(app()->isProduction()) untouched — it is an unrelated production-only guard, not part of the password-complexity fix in scope for this plan"

patterns-established:
  - "Password::defaults() closure returns Password unconditionally (no environment gate) — security-relevant defaults must apply identically across environments"

requirements-completed: [RBAC-06]

# Metrics
duration: 12min
completed: 2026-08-31
---

# Phase 01 Plan 02: Password Complexity Environment-Gate Fix Summary

**Removed the `app()->isProduction()` gate on `Password::defaults()` so the min(12)/mixed-case/digits/symbols/`uncompromised()` rule applies in every environment, closing a gap where RBAC-06 was silently unenforced outside production.**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-08-31T16:57:00Z
- **Completed:** 2026-08-31T17:09:08Z
- **Tasks:** 1 completed
- **Files modified:** 4 (1 created, 3 modified)

## Accomplishments
- `Password::defaults()` in `AppServiceProvider::configureDefaults()` now unconditionally returns the complexity rule (return type narrowed from `?Password` to `Password`)
- Fixed two existing tests (`SecurityTest`, `PasswordResetTest`) whose fixture passwords (`'new-password'`, `'password'`) no longer satisfy the always-on complexity rule
- Added `PasswordComplexityTest` proving a weak password (`'password'`) is rejected via `assertSessionHasErrors('password')` while running in the `testing` environment

## Task Commits

Each task was committed atomically:

1. **Task: Remove production-only gate on password complexity, repair dependent tests** - `35a53ee` (fix)

**Plan metadata:** (pending — final docs commit)

## Files Created/Modified
- `app/Providers/AppServiceProvider.php` - `Password::defaults()` closure no longer branches on `app()->isProduction()`; always returns `Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()`
- `tests/Feature/Settings/SecurityTest.php` - `password can be updated` and `correct password must be provided to update password` now use `'NewP@ssw0rd2026'` instead of `'new-password'`
- `tests/Feature/Auth/PasswordResetTest.php` - `password can be reset with valid token` now uses `'ResetP@ssw0rd2026'` instead of `'password'`; `password cannot be reset with invalid token` left untouched (verified it still fails on the token/email path, not password complexity, since it never used a complexity-compliant password to begin with and Fortify validates email/token before password)
- `tests/Feature/Auth/PasswordComplexityTest.php` (new) - `weak password is rejected outside production` asserts `app()->environment()` is `'testing'` then confirms a weak password update is rejected with a `password` session error

## Decisions Made
- Kept `DB::prohibitDestructiveCommands(app()->isProduction())` unchanged — it's a separate production-only guard unrelated to password complexity and out of this plan's scope. Note: the plan's acceptance criteria bullet `grep -c "isProduction()" ... returns 0` is technically not satisfied (one occurrence remains, for the unrelated `prohibitDestructiveCommands` call) — this is a plan-authoring inconsistency between the bullet and the `<action>` instructions, which explicitly scope the change to only the `Password::defaults()` ternary. Verified via `grep -n "Password::min(12)"` that the password rule itself is unconditional, matching the plan's actual intent.

## Deviations from Plan

None - plan executed exactly as written (aside from the acceptance-criteria grep-count note above, which is a documentation clarification, not a code deviation).

## Issues Encountered
None. `uncompromised()` (HaveIBeenPwned k-anonymity check) executed successfully against strong test passwords with no network-related test flakiness observed in this run.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- RBAC-06 password complexity requirement now holds in every environment; no blockers for subsequent plans in this phase.
- Full test suite green: 29 tests, 25 passed, 4 skipped (Fortify-feature-gated 2FA tests), 0 failed.

---
*Phase: 01-foundation-rbac-auth-hardening-audit-trail*
*Completed: 2026-08-31*

## Self-Check: PASSED

- FOUND: app/Providers/AppServiceProvider.php
- FOUND: tests/Feature/Auth/PasswordComplexityTest.php
- FOUND: .planning/phases/01-foundation-rbac-auth-hardening-audit-trail/01-02-SUMMARY.md
- FOUND: commit 35a53ee
