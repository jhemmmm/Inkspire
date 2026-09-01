---
phase: 01-foundation-rbac-auth-hardening-audit-trail
plan: 08
subsystem: auth
tags: [session-management, middleware, fortify, carbon, pest]

# Dependency graph
requires:
  - phase: 01-foundation-rbac-auth-hardening-audit-trail
    provides: "current_session_id capture on login (Plan 01-05), system_configurations substrate (Plan 01-03)"
provides:
  - "VerifySingleSession middleware enforcing one active session per user"
  - "EnforceIdleSessionTimeout middleware enforcing a configurable idle logout"
  - "CaptureAuthenticatedSessionId Fortify pipeline step (post-session-regeneration id capture)"
affects: [phase-02, phase-03, phase-04, phase-05, phase-06, phase-07, phase-08]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Session-identity capture must happen in a Fortify authentication pipeline step registered after PrepareAuthenticatedSession, never in a Login event listener, since the Login event fires before session id regeneration"
    - "Global session-guard middleware (single-session, idle-timeout) reads thresholds from SystemConfiguration::getInt(), never hardcoded"

key-files:
  created:
    - app/Http/Middleware/VerifySingleSession.php
    - app/Http/Middleware/EnforceIdleSessionTimeout.php
    - app/Actions/Fortify/CaptureAuthenticatedSessionId.php
    - tests/Feature/Auth/SingleSessionTest.php
    - tests/Feature/Auth/IdleTimeoutTest.php
  modified:
    - app/Providers/FortifyServiceProvider.php
    - app/Listeners/Auth/HandleSuccessfulLogin.php
    - bootstrap/app.php

key-decisions:
  - "Moved current_session_id capture from HandleSuccessfulLogin (Login event listener) into a new CaptureAuthenticatedSessionId Fortify pipeline step registered after PrepareAuthenticatedSession, because the Login event fires before Fortify regenerates the session id — capturing it earlier stores a stale id that never matches the id sent to the browser"
  - "Carbon 3's diffInMinutes() defaults to a signed (non-absolute) difference; EnforceIdleSessionTimeout must pass absolute: true or a past last_activity_at timestamp produces a negative diff that can never exceed a positive timeout"
  - "Feature tests asserting session-identity comparisons must explicitly forward the session cookie between sequential $this->post()/$this->get() calls (via withCookie(config('session.cookie'), $id)) and call Auth::forgetGuards() before the follow-up request, since Laravel's test HTTP client does not carry cookies across calls the way a real browser does, and the AuthManager/Guard singleton otherwise returns a stale, pre-DB-update user instance"

patterns-established:
  - "Session-guard middleware pattern: no-op for guests, read threshold from SystemConfiguration, force Auth::logout() + session()->invalidate() + session()->regenerateToken() + redirect to login with a flashed sessionMessage on violation"

requirements-completed: [RBAC-04, RBAC-05]

# Metrics
duration: ~25min (resumed session after a provider rate-limit interruption mid-task-1; original session already landed the RED commit for task 1)
completed: 2026-09-01
---

# Phase 01 Plan 08: Single-Session & Idle-Timeout Enforcement Summary

**VerifySingleSession and EnforceIdleSessionTimeout middleware, both reading thresholds/state from `system_configurations` and `users.current_session_id`, force-logging-out stale or idle sessions with UI-SPEC-exact messages**

## Performance

- **Duration:** ~25 min (this resumed session; a prior session had already landed the RED commit for Task 1 before being cut off by a provider rate limit mid-GREEN)
- **Completed:** 2026-09-01
- **Tasks:** 2 completed
- **Files modified:** 8 (3 created middleware/action files, 2 test files, 3 modified)

## Accomplishments
- `VerifySingleSession` compares the live session id to `users.current_session_id` on every authenticated request and force-logs-out a stale browser with the exact UI-SPEC "logged in from another device" message
- `EnforceIdleSessionTimeout` compares session `last_activity_at` against the Owner-configurable `session_idle_timeout_minutes` (default 20) and force-logs-out with the exact UI-SPEC "session expired after N minutes" message, interpolating the real configured N
- Fixed a real, production-relevant bug in the original `HandleSuccessfulLogin`-based design: session id must be captured **after** Fortify's `PrepareAuthenticatedSession` regenerates it, not in the `Login` event listener that fires before regeneration — introduced `CaptureAuthenticatedSessionId` as a dedicated pipeline step for this

## Task Commits

Each task was committed atomically (Task 1 spans two sessions due to a provider rate-limit interruption between RED and GREEN):

1. **Task 1: VerifySingleSession middleware** — RED: `d350c16` (test, prior session) → GREEN: `8d21833` (feat, this session)
2. **Task 2: EnforceIdleSessionTimeout middleware** — RED: `c925e74` (test) → GREEN: `cc02208` (feat)

## Files Created/Modified
- `app/Http/Middleware/VerifySingleSession.php` - Compares live session id to `users.current_session_id`; force-logout on mismatch
- `app/Http/Middleware/EnforceIdleSessionTimeout.php` - Compares `last_activity_at` against configured idle timeout; force-logout on expiry
- `app/Actions/Fortify/CaptureAuthenticatedSessionId.php` - New Fortify pipeline step; captures the regenerated session id onto the user row after `PrepareAuthenticatedSession`
- `app/Providers/FortifyServiceProvider.php` - Registers `CaptureAuthenticatedSessionId::class` after `PrepareAuthenticatedSession::class` in the authentication pipeline
- `app/Listeners/Auth/HandleSuccessfulLogin.php` - Removed the (now-stale-id-producing) `current_session_id` capture; left a comment pointing to the correct pipeline step
- `bootstrap/app.php` - Registers `VerifySingleSession::class` then `EnforceIdleSessionTimeout::class` on the `web` middleware group
- `tests/Feature/Auth/SingleSessionTest.php` - Two tests: stale session force-logout, matching session unaffected
- `tests/Feature/Auth/IdleTimeoutTest.php` - Two tests: idle-past-timeout force-logout, active-within-window unaffected

## Decisions Made
- Session-id capture belongs in a dedicated post-regeneration Fortify pipeline step, not in a `Login` event listener (see Deviations below) — this is now the established pattern for any future session-identity-dependent capture.
- `EnforceIdleSessionTimeout` registered directly after `VerifySingleSession` in the `web` group so a single-session violation takes precedence if both were ever somehow true simultaneously (matches plan's stated intent; in practice the two conditions are independent).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Session id captured before Fortify's session regeneration produced a permanently-stale stored id**
- **Found during:** Task 1 (VerifySingleSession), inherited from the interrupted prior session
- **Issue:** The plan's literal RESEARCH.md-derived design implicitly relied on `HandleSuccessfulLogin` (a `Login` event listener) to capture `session()->getId()`. The `Login` event fires from `AttemptToAuthenticate`, which runs *before* Fortify's `PrepareAuthenticatedSession` pipeline step regenerates the session id for security (session fixation protection). Capturing the id at that point stores the pre-regeneration id, which never matches the id actually sent back to the browser — every user would be force-logged-out on their very next request.
- **Fix:** Removed the `current_session_id` capture from `HandleSuccessfulLogin` and added a new `CaptureAuthenticatedSessionId` invokable Fortify pipeline step, registered immediately after `PrepareAuthenticatedSession::class` in `FortifyServiceProvider::configureAuthenticationPipeline()`. It captures `session()->getId()` onto the user row once the id is final.
- **Files modified:** `app/Listeners/Auth/HandleSuccessfulLogin.php`, `app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify/CaptureAuthenticatedSessionId.php` (new)
- **Verification:** `php artisan test --compact --filter=SingleSessionTest` passes
- **Committed in:** `8d21833`

**2. [Rule 1 - Bug] SingleSessionTest compared session ids that could never match under Laravel's test HTTP client**
- **Found during:** Task 1 (VerifySingleSession)
- **Issue:** Laravel's `MakesHttpRequests::call()` does not forward cookies between sequential `$this->post()`/`$this->get()` calls the way a real browser does — each simulated request gets a freshly generated session id from `StartSession::getSession()` unless a cookie is explicitly attached. The plan's literal test-writing instructions assumed the test client "still holds the first session's cookie," which is not true by default. Additionally, `AuthManager`/`SessionGuard` cache the resolved user object as a container singleton across the whole test process, so a later `$user->forceFill(...)->save()` on a separately-held model instance does not update what `$request->user()` returns on the next simulated request.
- **Fix:** Both tests now explicitly capture the session id via `$user->fresh()->current_session_id` after login and forward it with `$this->withCookie(config('session.cookie'), $sessionId)` before the follow-up request (correctly simulating "the same browser sends its cookie back"). Both tests also call `Auth::forgetGuards()` before the follow-up request so a fresh guard resolves the user from the database — exactly what a genuinely new HTTP request does in production, where no PHP object state survives between requests.
- **Files modified:** `tests/Feature/Auth/SingleSessionTest.php`
- **Verification:** `php artisan test --compact --filter=SingleSessionTest` passes (2/2); full suite (`php artisan test --compact`) passes 48/48 (4 pre-existing skips)
- **Committed in:** `8d21833`

**3. [Rule 1 - Bug] Carbon 3's diffInMinutes() defaults to a signed difference**
- **Found during:** Task 2 (EnforceIdleSessionTimeout)
- **Issue:** `now()->diffInMinutes($lastActivity)` was written assuming Carbon 2's absolute-by-default behavior. Carbon 3.13.2 (the version actually installed — confirmed via `composer show nesbot/carbon`) changed `diffInMinutes($date = null, bool $absolute = false)` to default to a *signed* difference. For a `$lastActivity` in the past, this produced a negative number (e.g. `-25` for 25 minutes ago), which can never exceed a positive `$timeoutMinutes` threshold — the idle-timeout check silently never fired.
- **Fix:** Pass `absolute: true` explicitly: `now()->diffInMinutes($lastActivity, absolute: true) > $timeoutMinutes`.
- **Files modified:** `app/Http/Middleware/EnforceIdleSessionTimeout.php`
- **Verification:** `php artisan test --compact --filter=IdleTimeoutTest` passes (2/2)
- **Committed in:** `cc02208`

---

**Total deviations:** 3 auto-fixed (all Rule 1 — bugs blocking correct behavior)
**Impact on plan:** All three were necessary for the middleware to actually enforce what the plan and UI-SPEC require in both production and test. No scope creep — no files touched beyond what the plan's `files_modified` list and its two tasks specify (plus the one new pipeline-step file, which exists solely to correctly fulfill Task 1's own stated behavior).

## Issues Encountered
- `composer types:check` (Larastan/PHPStan) remains broken in this environment due to a pre-existing `phpstan_turbo` native-extension/GLIBC mismatch (logged in prior plans' summaries, e.g. 01-06, 01-07) — unrelated to this plan's changes, out of scope per the deviation rules' scope boundary. Both new middleware classes follow the same explicit-return-type, guarded-instanceof conventions established in prior plans that satisfy Larastan level 7 when the tool is runnable.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- RBAC-04 (single active session) and RBAC-05 (idle session timeout) are both fully enforced globally on the `web` middleware group, independent of role — no further plans in this phase depend on this behavior, but every future authenticated route inherits it automatically.
- The `CaptureAuthenticatedSessionId` pipeline-step pattern (capture-after-regeneration) should be followed for any future auth-pipeline state that depends on the final, post-regeneration session id.

---
*Phase: 01-foundation-rbac-auth-hardening-audit-trail*
*Completed: 2026-09-01*

## Self-Check: PASSED

All 8 claimed files found on disk; all 4 claimed commit hashes (`d350c16`, `8d21833`, `c925e74`, `cc02208`) found in git history.
