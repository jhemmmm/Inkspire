---
phase: 01-foundation-rbac-auth-hardening-audit-trail
fixed_at: 2026-09-01T02:59:07Z
review_path: .planning/phases/01-foundation-rbac-auth-hardening-audit-trail/01-REVIEW.md
iteration: 1
findings_in_scope: 6
fixed: 6
skipped: 0
status: all_fixed
---

# Phase 01: Code Review Fix Report

**Fixed at:** 2026-09-01T02:59:07Z
**Source review:** .planning/phases/01-foundation-rbac-auth-hardening-audit-trail/01-REVIEW.md
**Iteration:** 1

**Summary:**

- Findings in scope: 6 (CR-01, WR-01, WR-02, WR-03, WR-04, WR-05 — `critical_warning` scope; IN-01/IN-02/IN-03 excluded per instructions)
- Fixed: 6
- Skipped: 0

All fixes were applied in an isolated git worktree, verified with `vendor/bin/pint --dirty --format agent`, `php -l`, `vue-tsc --noEmit`, and the full Pest suite (69 passed, 3 skipped — up from the pre-fix baseline of 62 passed, 4 skipped), then committed atomically. Each new/changed test was confirmed to fail against the pre-fix code and pass against the fix (regression-proven), except where noted.

## Fixed Issues

### CR-01: Password hashes and other hidden User attributes leak into the audit trail and are shipped to the browser

**Files modified:** `app/Observers/AuditObserver.php`, `tests/Feature/Owner/AuditTrailTest.php`
**Commit:** e7e25d0
**Applied fix:** Added a private `redact()` helper to `AuditObserver` that strips a model's own `#[Hidden]` keys (via `$model->getHidden()`) from the attribute arrays before they're passed to `AuditLogger::recordMutation()`. Applied to all three observer methods (`created()`, `updated()`, `deleted()`) for defense in depth, per the review's guidance, so a future column added to `$hidden` never requires remembering to also update this observer. Added two regression tests proving `password`/`remember_token`/`two_factor_secret`/`two_factor_recovery_codes` no longer appear in `audit_trail.old_values`/`new_values` on User create/delete, and that the Inertia response to the Owner Audit Trail page no longer exposes `new_values.password`/`new_values.remember_token`. Confirmed both new tests fail on the pre-fix observer (`Expecting [...] not to have key 'password'` failure) and pass on the fix.

### WR-01: Audit trail filters crash with a 500 on malformed date input

**Files modified:** `app/Http/Controllers/Owner/AuditTrailController.php`, `app/Http/Requests/Owner/FilterAuditTrailRequest.php` (new), `tests/Feature/Owner/AuditTrailTest.php`
**Commit:** 46c2fd4
**Applied fix:** Created `FilterAuditTrailRequest` (a `FormRequest`, matching the project's established validation-via-FormRequest convention — no other controller in this codebase validates inline) with `user: nullable|integer`, `action: nullable|string`, `from: nullable|date`, `to: nullable|date` rules, and swapped it in as `AuditTrailController::index()`'s type-hinted parameter in place of the raw `Request`. Added two regression tests asserting `?from=not-a-date` and `?to=not-a-date` now redirect back with a validation error (`assertSessionHasErrors`) instead of throwing. Confirmed both tests reproduce the exact `Carbon\Exceptions\InvalidFormatException` ("Could not parse 'not-a-date'...") on the pre-fix controller and pass after the fix.

### WR-02: `UserFactory::withTwoFactor()` is a broken stub that throws when called

**Files modified:** `database/factories/UserFactory.php`, `tests/Feature/Auth/AuthenticationTest.php`
**Commit:** 8319cfc
**Applied fix:** Removed the empty-bodied `withTwoFactor(): static {}` stub and its only caller test. This deviates from the review's _primary_ suggestion (implement it via `afterCreating()`, mirroring `locked()`/`deactivated()`) because that fix is not actually viable against the current codebase state: the `users` table has no `two_factor_secret`/`two_factor_confirmed_at` columns (no migration adds them), and `App\Models\User` does not use Fortify's `TwoFactorAuthenticatable` trait. Fortify's `RedirectIfTwoFactorAuthenticatable` explicitly requires `in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user))` before it will ever route to the 2FA challenge — so the caller test could never pass regardless of factory state, and attempting to fill `two_factor_secret` via `afterCreating()` would throw a SQL "no such column" error instead of the current PHP `Error`. This applied the review's own documented fallback ("or remove the stub and the caller until 2FA is actually built"). Verified no other references to `withTwoFactor` remain in the codebase and the full suite still passes (69 passed, 3 skipped — one fewer skipped test than before, since the removed test was previously always skipped).

### WR-03: `SystemConfigurationSeeder` doesn't invalidate the config cache it writes through

**Files modified:** `database/seeders/SystemConfigurationSeeder.php`, `tests/Unit/SystemConfigurationTest.php`
**Commit:** 97f8199
**Applied fix:** Added `SystemConfiguration::invalidate($configuration['key'])` immediately after each `updateOrCreate()` call in the seeder loop, exactly as suggested. Added a regression test that pre-warms the cache with a stale value, re-runs the seeder, and asserts `getInt()` immediately reflects the freshly-seeded value rather than the stale cached one. Confirmed the test fails (`Failed asserting that 1 is identical to 5`) against the pre-fix seeder and passes after the fix.

### WR-04: Leftover generic `/dashboard` route bypasses the per-role portal design, and `Forbidden.vue` links to it

**Files modified:** `routes/web.php`, `bootstrap/app.php`, `resources/js/pages/errors/Forbidden.vue`, `tests/Feature/DashboardTest.php`, `tests/Feature/RoleBoundaryTest.php`
**Commit:** c9284d0
**Applied fix:** Chose the review's second offered option (role-aware redirect) over outright removal, since `dashboard()` from `@/routes` is still referenced by several other starter-kit components (`AppHeader.vue`, `AppSidebar.vue`, `Welcome.vue`, the orphan `Dashboard.vue` page) whose Wayfinder-generated route helper would break if the named route were deleted — removing it cleanly would have required touching those unrelated files too, which is out of scope for a Warning-level, narrowly-targeted fix. Instead: (1) `routes/web.php`'s `/dashboard` route now redirects to `route($request->user()->role->portalRoute())` instead of rendering the generic placeholder page — the underlying RBAC bypass concern (every role sees a shared, non-role-specific page) is resolved because every role is now bounced straight to their own portal; (2) `bootstrap/app.php`'s 403 exception handler now passes a `dashboardHref` prop (resolved via the same `portalRoute()` pattern `LoginResponse` already uses) alongside `role`; (3) `Forbidden.vue` now links to `props.dashboardHref` instead of the generic `dashboard()` route helper. Updated the pre-existing `DashboardTest` assertion (`assertOk()` → `assertRedirect(...)`, since the route's behavior intentionally changed) and added a new `RoleBoundaryTest` case asserting the 403 page's `dashboardHref` Inertia prop resolves to the signed-in user's own portal route. Confirmed both changed/new tests fail against the pre-fix code (`Property [dashboardHref] does not exist`, and a 200 instead of a redirect) and pass after the fix. Verified with `vue-tsc --noEmit` (zero errors, matching the pre-fix baseline) that removing the `dashboard` import from `Forbidden.vue` didn't break anything else.

### WR-05: A single login writes 3 audit_trail rows for one logical event, diluting the audit trail

**Files modified:** `app/Listeners/Auth/HandleSuccessfulLogin.php`, `app/Actions/Fortify/CaptureAuthenticatedSessionId.php`, `tests/Feature/Auth/AuthAuditTrailTest.php`
**Commit:** 2145746
**Applied fix:** Chose the review's second offered option (route the specific bookkeeping saves through a non-observed path) over the first (hardcode a column-name allowlist into the generic, reusable `AuditObserver`), since coupling the model-agnostic observer to `User`-specific column names felt like the wrong layer for this fix. Changed both `HandleSuccessfulLogin` and `CaptureAuthenticatedSessionId`'s `->save()` calls to `->saveQuietly()`, with inline comments explaining why (routine session bookkeeping, not a business-data mutation; the explicit `login` audit row already captures the meaningful signal). Added a regression test asserting a single login produces zero `updated` audit_trail rows for the user. Confirmed the test fails (`Failed asserting that 2 is identical to 0`, matching the review's exact reproduction) against the pre-fix code and passes after the fix. Verified `SingleSessionTest`, `IdleTimeoutTest`, and `AccountLockoutTest` (all of which depend on `current_session_id`/`last_activity_at`/`failed_login_attempts`/`locked_until` being persisted correctly) still pass — `saveQuietly()` only suppresses model _events_, not the actual database write.

## Skipped Issues

None — all 6 in-scope findings were fixed.

## Out of Scope (not attempted, per fix_scope: critical_warning)

- **IN-01**: Login-lockout messaging enables account enumeration
- **IN-02**: `UserFactory` defaults every plain `create()` call to the highest-privilege role
- **IN-03**: `users.last_activity_at` doesn't track ongoing activity despite its name

---

_Fixed: 2026-09-01T02:59:07Z_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
