---
phase: 01-foundation-rbac-auth-hardening-audit-trail
plan: 05
subsystem: auth
tags: [laravel, fortify, audit-trail, auth-events, listeners]

# Dependency graph
requires:
    - phase: 01-01
      provides: "audit_trail table, AuditLogger::recordAuthEvent(), User model's forceFill-only guarded columns (current_session_id, last_activity_at, failed_login_attempts, locked_until)"
provides:
    - 'HandleSuccessfulLogin listener: writes login audit_trail row, captures winning session id on users.current_session_id, resets failed_login_attempts/locked_until on every successful login'
    - 'HandleLogout listener: writes logout audit_trail row'
    - 'tests/Feature/Auth/AuthAuditTrailTest.php covering login audit, logout audit, and exactly-one-row-each combined flow'
affects: [01-07, 01-08]

# Tech tracking
tech-stack:
    added: []
    patterns:
        - "Native Laravel auth event listeners (Illuminate\\Auth\\Events\\Login/Logout) auto-discovered under app/Listeners/Auth/ — no manual EventServiceProvider registration"
        - "instanceof App\\Models\\User guard on Authenticatable-typed event properties before calling User-typed helpers (required for Larastan level 7 compliance)"

key-files:
    created:
        - app/Listeners/Auth/HandleSuccessfulLogin.php
        - app/Listeners/Auth/HandleLogout.php
        - tests/Feature/Auth/AuthAuditTrailTest.php
    modified: []

key-decisions:
    - "Added an instanceof App\\Models\\User guard in HandleSuccessfulLogin (plan's literal action snippet called $event->user->forceFill() directly) — Illuminate\\Auth\\Events\\Login::$user is typed Authenticatable, not App\\Models\\User, and AuditLogger::recordAuthEvent() requires ?User; Larastan level 7 rejected the unguarded call"

patterns-established:
    - "Auth event listeners live in app/Listeners/Auth/ and are auto-wired by Laravel 13's event discovery; verified via php artisan event:list rather than manual provider registration"

requirements-completed: [RBAC-04, RBAC-08]

# Metrics
duration: 8min
completed: 2026-09-01
---

# Phase 01 Plan 05: Auth Event Audit Trail (Login/Logout) Summary

**Native Login/Logout event listeners write append-only audit_trail rows and capture the winning session ID for Plan 01-08's single-session enforcement**

## Performance

- **Duration:** 8 min
- **Started:** 2026-09-01T01:48:00+08:00
- **Completed:** 2026-09-01T01:50:26+08:00
- **Tasks:** 2 completed
- **Files modified:** 3 (2 created listeners, 1 created test file)

## Accomplishments

- `HandleSuccessfulLogin` listens on `Illuminate\Auth\Events\Login`, force-fills `current_session_id`, `last_activity_at`, and resets `failed_login_attempts`/`locked_until` on the authenticating user, then writes a `login` audit_trail row
- `HandleLogout` listens on `Illuminate\Auth\Events\Logout`, writes a `logout` audit_trail row (guarded against a null/non-User `$event->user`)
- Both listeners confirmed auto-discovered via `php artisan event:list` — no manual `EventServiceProvider` registration needed in this Laravel 13 app
- `tests/Feature/Auth/AuthAuditTrailTest.php` proves login writes exactly one login row and sets the session id, logout writes exactly one logout row, and a login-then-logout flow produces exactly one row of each (no duplicate registration)

## Task Commits

Each task followed RED → GREEN TDD:

1. **Task 1: HandleSuccessfulLogin listener** — `426ff3e` (test: failing login audit test), `4c501ae` (feat: implementation + type-safety fix)
2. **Task 2: HandleLogout listener** — `fca0a1f` (test: failing logout + combined-flow tests), `a303b63` (feat: implementation)

**Plan metadata:** pending (final docs commit)

_Note: Both tasks used tdd="true" per plan frontmatter; each has a test commit followed by a feat commit._

## Files Created/Modified

- `app/Listeners/Auth/HandleSuccessfulLogin.php` - Login event listener: session capture, lockout-counter reset, login audit write
- `app/Listeners/Auth/HandleLogout.php` - Logout event listener: logout audit write
- `tests/Feature/Auth/AuthAuditTrailTest.php` - Feature tests for both listeners plus a combined exactly-one-row-each assertion

## Decisions Made

- Guarded `$event->user instanceof App\Models\User` in both listeners before touching User-typed helpers, since `Illuminate\Auth\Events\Login`/`Logout`'s `$user` property is typed to the `Authenticatable` interface, not the concrete `User` model. This is required for `AuditLogger::recordAuthEvent(?User $user, ...)`'s type signature and for Larastan level 7 to pass — see Deviations below.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug/type error] Added instanceof User guard in HandleSuccessfulLogin**

- **Found during:** Task 1 (HandleSuccessfulLogin implementation)
- **Issue:** The plan's literal action snippet called `$event->user->forceFill(...)` and passed `$event->user` directly to `AuditLogger::recordAuthEvent()`. `Illuminate\Auth\Events\Login::$user` is typed `Illuminate\Contracts\Auth\Authenticatable`, not `App\Models\User`, so `vendor/bin/phpstan analyse` (Larastan level 7, this project's standing static-analysis bar) failed with `argument.type`: `AuditLogger::recordAuthEvent()` expects `App\Models\User|null`.
- **Fix:** Added `if (! $event->user instanceof User) { return; }` before the forceFill/audit calls, mirroring the guard the plan already specifies for `HandleLogout`. Functionally identical for this single-guard app (only `App\Models\User` authenticates), but now statically type-safe.
- **Files modified:** app/Listeners/Auth/HandleSuccessfulLogin.php
- **Verification:** `vendor/bin/phpstan analyse app/Listeners/Auth/HandleSuccessfulLogin.php` passes with 0 errors; `php artisan test --compact --filter=AuthAuditTrailTest` still passes.
- **Committed in:** 4c501ae (Task 1 feat commit)

---

**Total deviations:** 1 auto-fixed (1 bug/type-safety fix)
**Impact on plan:** Necessary for Larastan level 7 compliance (project's Code Style convention); no behavior change, no scope creep.

## Issues Encountered

None beyond the type-safety fix documented above.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `users.current_session_id` is now populated on every successful login, ready for Plan 01-08's `VerifySingleSession` middleware to compare against.
- Plan 01-07 (Failed/Lockout auth events) can follow the same auto-discovered-listener pattern established here.
- Full test suite (`php artisan test --compact`) passes: 38 tests, 34 passed, 4 skipped (pre-existing Fortify feature-flag skips), 0 failures.

---

_Phase: 01-foundation-rbac-auth-hardening-audit-trail_
_Completed: 2026-09-01_

## Self-Check: PASSED

All created files verified present on disk; all 4 task commit hashes (426ff3e, 4c501ae, fca0a1f, a303b63) verified present in git log.
