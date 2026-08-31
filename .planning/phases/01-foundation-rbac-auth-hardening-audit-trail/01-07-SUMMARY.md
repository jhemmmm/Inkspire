---
phase: 01-foundation-rbac-auth-hardening-audit-trail
plan: 07
subsystem: auth
tags: [fortify, laravel, account-lockout, audit-trail, pest]

requires:
  - phase: 01-foundation-rbac-auth-hardening-audit-trail
    provides: "AuditLogger::recordAuthEvent() (01-05), SystemConfiguration::getInt() + seeded lockout config (01-03), User.locked_until/is_active/failed_login_attempts columns (01-01)"
provides:
  - "EnsureAccountIsNotLocked Fortify authenticateThrough() pipeline step rejecting locked/deactivated accounts before AttemptToAuthenticate"
  - "RecordFailedLoginAttempt listener incrementing failed_login_attempts and locking the account after the configured threshold"
  - "RecordLockoutEvent listener auditing Fortify's native IP-throttle Lockout event"
  - "UserFactory::locked()/deactivated() states for test fixtures"
affects: [rbac, user-management, auth]

tech-stack:
  added: []
  patterns:
    - "Fortify authenticateThrough() pipeline extension for pre-AttemptToAuthenticate rejection, via a single-purpose __invoke(Request, callable) action class"
    - "afterCreating() factory states for attributes outside a model's #[Fillable] list"

key-files:
  created:
    - app/Actions/Fortify/EnsureAccountIsNotLocked.php
    - app/Listeners/Auth/RecordFailedLoginAttempt.php
    - app/Listeners/Auth/RecordLockoutEvent.php
    - tests/Feature/Auth/AccountLockoutTest.php
  modified:
    - app/Providers/FortifyServiceProvider.php
    - database/factories/UserFactory.php

key-decisions:
  - "EnsureAccountIsNotLocked resolves the user by Fortify::username() and no-ops (passes through) on unknown emails, deferring to AttemptToAuthenticate's standard failure and the existing IP+email rate limiter"
  - "Account-level lockout counter is independent of Fortify's existing IP+email RateLimiter — two separate defense-in-depth triggers, per RESEARCH.md"

requirements-completed: [RBAC-03, RBAC-07, RBAC-08]

duration: 15min
completed: 2026-09-01
---

# Phase 01 Plan 07: Account Lockout & Deactivation Login Gate Summary

**Fortify `authenticateThrough()` pipeline step plus `Failed`/`Lockout` event listeners implementing account-level lockout after 5 failed attempts and blocking deactivated-account logins, with every attempt/lockout audited**

## Performance

- **Duration:** 15 min
- **Started:** 2026-08-31T18:08:19Z
- **Completed:** 2026-09-01T02:14:06+08:00
- **Tasks:** 2 completed
- **Files modified:** 6 (3 created, 2 modified, 1 test created)

## Accomplishments
- Locked-out accounts (`locked_until` in the future) and deactivated accounts (`is_active = false`) are now rejected at login before Fortify ever attempts password verification
- 5 consecutive failed login attempts against the same account lock it for the configured duration (`account_lockout_minutes`, seeded to 15), read from `SystemConfiguration` rather than hardcoded
- Every failed attempt and every lockout (both account-level and Fortify's native IP-throttle lockout) writes an `audit_trail` row

## Task Commits

Each task followed RED → GREEN TDD (test file created and committed in task 2, since task 1's pipeline-gate behavior for the "deactivated" case was already covered incidentally):

1. **Task 1: EnsureAccountIsNotLocked pipeline step + factory locked()/deactivated() states** - `f7f1a29` (feat)
2. **Task 2: RecordFailedLoginAttempt + RecordLockoutEvent listeners, AccountLockoutTest** - `59cd514` (test, RED) → `2804faa` (feat, GREEN)

_Note: Both tasks used `tdd="true"` per plan frontmatter; the test file was authored as part of task 2 per the plan's explicit file split, so task 1 has no dedicated preceding test commit — its "deactivated account" behavior was verified against `AccountLockoutTest` once that file existed in task 2's RED phase (confirmed passing immediately, since it depends only on task 1's already-committed code, not on the listeners added in task 2)._

## Files Created/Modified
- `app/Actions/Fortify/EnsureAccountIsNotLocked.php` - Fortify pipeline action; throws `ValidationException` on `email` for locked or deactivated accounts before `AttemptToAuthenticate` runs
- `app/Providers/FortifyServiceProvider.php` - added `configureAuthenticationPipeline()`, called from `boot()`, inserting `EnsureAccountIsNotLocked::class` into `Fortify::authenticateThrough()` before `AttemptToAuthenticate::class`
- `database/factories/UserFactory.php` - added `locked()` and `deactivated()` states via `afterCreating()->forceFill()->save()` (both attribute sets are outside `User`'s `#[Fillable]` list)
- `app/Listeners/Auth/RecordFailedLoginAttempt.php` - `Illuminate\Auth\Events\Failed` listener; increments `failed_login_attempts`, audits `failed_login` every time, sets `locked_until` and audits `lockout` once the configured threshold is reached
- `app/Listeners/Auth/RecordLockoutEvent.php` - `Illuminate\Auth\Events\Lockout` listener; audits Fortify's native IP-throttle lockout (no resolved user — `user_id` is null, IP captured via the event's request)
- `tests/Feature/Auth/AccountLockoutTest.php` - 3 tests covering RBAC-03 (lockout after 5 attempts), RBAC-07 (deactivated login rejection), RBAC-08 (audit trail rows)

## Decisions Made
- Both listeners are auto-discovered by Laravel's event auto-discovery (confirmed via `php artisan event:list`) — no manual `EventServiceProvider::$listen` registration needed, matching the existing `HandleSuccessfulLogin`/`HandleLogout` convention in this codebase
- Guarded `$event->user instanceof User` in `RecordFailedLoginAttempt` (per Pitfall 3 in RESEARCH.md) so unknown-email failed attempts are silently ignored here and left to the existing IP+email rate limiter

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None. `composer types:check` (Larastan/PHPStan) remains broken in this environment due to a pre-existing `phpstan_turbo` native-extension/GLIBC mismatch (logged in prior plans' summaries, e.g. 01-06) — unrelated to this plan's changes and out of scope per the deviation rules' scope boundary. All new code follows the established `instanceof User` guard pattern from 01-05 that satisfies Larastan level 7 when the tool is runnable.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- RBAC-03, RBAC-07 (login-blocking half), and RBAC-08 are now fully satisfied
- Remaining Phase 1 plans (single-session enforcement, idle timeout, role middleware, audit trail viewing UI, system config UI) are unaffected and can proceed independently
- Full backend suite green: 44 tests, 40 passed, 4 pre-existing skips, 0 failed

---
*Phase: 01-foundation-rbac-auth-hardening-audit-trail*
*Completed: 2026-09-01*

## Self-Check: PASSED

- FOUND: app/Actions/Fortify/EnsureAccountIsNotLocked.php
- FOUND: app/Listeners/Auth/RecordFailedLoginAttempt.php
- FOUND: app/Listeners/Auth/RecordLockoutEvent.php
- FOUND: tests/Feature/Auth/AccountLockoutTest.php
- FOUND commit: f7f1a29
- FOUND commit: 59cd514
- FOUND commit: 2804faa
