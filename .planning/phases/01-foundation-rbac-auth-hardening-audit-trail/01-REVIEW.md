---
phase: 01-foundation-rbac-auth-hardening-audit-trail
reviewed: 2026-09-01T00:00:00Z
depth: standard
files_reviewed: 81
files_reviewed_list:
    - app/Actions/Fortify/CaptureAuthenticatedSessionId.php
    - app/Actions/Fortify/EnsureAccountIsNotLocked.php
    - app/Concerns/SystemConfigValidationRules.php
    - app/Enums/UserRole.php
    - app/Http/Controllers/Owner/AuditTrailController.php
    - app/Http/Controllers/Owner/SystemConfigurationController.php
    - app/Http/Controllers/Owner/UserManagementController.php
    - app/Http/Middleware/EnforceIdleSessionTimeout.php
    - app/Http/Middleware/EnsureUserHasRole.php
    - app/Http/Middleware/VerifySingleSession.php
    - app/Http/Requests/Owner/DeactivateUserRequest.php
    - app/Http/Requests/Owner/ReactivateUserRequest.php
    - app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php
    - app/Http/Responses/LoginResponse.php
    - app/Listeners/Auth/HandleLogout.php
    - app/Listeners/Auth/HandleSuccessfulLogin.php
    - app/Listeners/Auth/RecordFailedLoginAttempt.php
    - app/Listeners/Auth/RecordLockoutEvent.php
    - app/Models/AuditLog.php
    - app/Models/SystemConfiguration.php
    - app/Models/User.php
    - app/Observers/AuditObserver.php
    - app/Policies/UserPolicy.php
    - app/Providers/AppServiceProvider.php
    - app/Providers/FortifyServiceProvider.php
    - app/Support/AuditLogger.php
    - bootstrap/app.php
    - database/factories/UserFactory.php
    - database/migrations/2026_08_31_165341_add_rbac_and_lockout_columns_to_users_table.php
    - database/migrations/2026_08_31_165342_create_audit_trail_table.php
    - database/migrations/2026_08_31_171450_create_system_configurations_table.php
    - database/seeders/DatabaseSeeder.php
    - database/seeders/SystemConfigurationSeeder.php
    - resources/js/app.ts
    - resources/js/components/AppSidebar.vue
    - resources/js/components/ui/alert-dialog/AlertDialog.vue
    - resources/js/components/ui/alert-dialog/AlertDialogAction.vue
    - resources/js/components/ui/alert-dialog/AlertDialogCancel.vue
    - resources/js/components/ui/alert-dialog/AlertDialogContent.vue
    - resources/js/components/ui/alert-dialog/AlertDialogDescription.vue
    - resources/js/components/ui/alert-dialog/AlertDialogFooter.vue
    - resources/js/components/ui/alert-dialog/AlertDialogHeader.vue
    - resources/js/components/ui/alert-dialog/AlertDialogTitle.vue
    - resources/js/components/ui/alert-dialog/AlertDialogTrigger.vue
    - resources/js/components/ui/switch/Switch.vue
    - resources/js/components/ui/tabs/Tabs.vue
    - resources/js/components/ui/tabs/TabsContent.vue
    - resources/js/components/ui/tabs/TabsList.vue
    - resources/js/components/ui/tabs/TabsTrigger.vue
    - resources/js/config/nav/owner.ts
    - resources/js/layouts/AppLayout.vue
    - resources/js/layouts/app/AppSidebarLayout.vue
    - resources/js/pages/accounting-staff/Dashboard.vue
    - resources/js/pages/artist/Dashboard.vue
    - resources/js/pages/cashier/Dashboard.vue
    - resources/js/pages/errors/Forbidden.vue
    - resources/js/pages/frontline-staff/Dashboard.vue
    - resources/js/pages/owner/AuditTrail.vue
    - resources/js/pages/owner/Dashboard.vue
    - resources/js/pages/owner/SystemConfiguration.vue
    - resources/js/pages/owner/UserManagement.vue
    - resources/js/pages/production-staff/Dashboard.vue
    - resources/js/types/auth.ts
    - routes/owner.php
    - routes/portals.php
    - routes/web.php
    - tests/Feature/Auth/AccountLockoutTest.php
    - tests/Feature/Auth/AuthAuditTrailTest.php
    - tests/Feature/Auth/AuthenticationTest.php
    - tests/Feature/Auth/IdleTimeoutTest.php
    - tests/Feature/Auth/PasswordComplexityTest.php
    - tests/Feature/Auth/PasswordResetTest.php
    - tests/Feature/Auth/SingleSessionTest.php
    - tests/Feature/Owner/AuditTrailTest.php
    - tests/Feature/Owner/SystemConfigurationTest.php
    - tests/Feature/Owner/UserManagementTest.php
    - tests/Feature/RoleBoundaryTest.php
    - tests/Feature/Settings/SecurityTest.php
    - tests/Pest.php
    - tests/Unit/Arch/AuditLogArchTest.php
    - tests/Unit/SystemConfigurationTest.php
findings:
    critical: 1
    warning: 5
    info: 3
    total: 9
status: issues_found
---

# Phase 01: Code Review Report

**Reviewed:** 2026-09-01T00:00:00Z
**Depth:** standard
**Files Reviewed:** 81
**Status:** issues_found

## Summary

Reviewed the RBAC/auth-hardening/audit-trail foundation phase: Fortify pipeline customizations (lockout, single-session, idle timeout), the Owner/Admin portals (User Management, Audit Trail, System Configuration), the `AuditLogger`/`AuditObserver` append-only audit trail, and the associated migrations, factories, seeders, routes, and tests.

The single-session, idle-timeout, and account-lockout mechanisms are well built and covered by targeted tests; the existing test suite (66 tests) passes. However, hands-on verification (temporary, non-committed Pest tests run against this checkout and removed immediately after, confirmed via `git status`) uncovered a real data-exposure defect in the audit trail, plus several correctness/robustness gaps that the current test suite does not catch:

- The generic `AuditObserver::created()`/`deleted()` methods capture a model's **raw, unfiltered attributes** (bypassing Eloquent's `#[Hidden]` protection) into `audit_trail.old_values`/`new_values`. For the `User` model this persists the bcrypt password hash and `remember_token` in plain JSON, and `AuditTrailController` serves that JSON straight through to the browser via Inertia props with no field-level redaction. Confirmed empirically: a factory-created user's audit `new_values` column contained `"password":"$2y$04$..."`.
- `AuditTrailController::index()` passes unvalidated `from`/`to` query parameters straight into `Request::date()`, which throws `Carbon\Exceptions\InvalidFormatException` on any unparsable value — confirmed this produces an HTTP 500 for a request as simple as `?from=not-a-date`.
- `UserFactory::withTwoFactor()` is a stub with an empty body but a `: static` return type; calling it throws a fatal `Error`. This is currently masked because Fortify's two-factor feature flag is off, so the only caller (`AuthenticationTest`) is skipped — confirmed the stub throws when invoked directly.
- A single login writes 3 `audit_trail` rows for one logical event (`login`, plus two `updated` rows from two separate `forceFill()->save()` calls) — confirmed via test — diluting the audit trail's usefulness as an accountability tool.
- `SystemConfigurationSeeder` never invalidates the `SystemConfiguration` cache after `updateOrCreate()`, undermining its own "idempotent and safe to re-run" design goal for changing existing values.
- A leftover starter-kit `/dashboard` route is reachable by every authenticated role (no `role` middleware) and `Forbidden.vue`'s "Return to your dashboard" link targets it instead of the user's own portal — conflicting with the project's "each role with its own dedicated portal" constraint.

One boolean-configuration hypothesis I initially suspected (string `"false"` round-tripping through the `array`-cast `value` column as truthy) was tested directly and **disproven** — Laravel's `boolean` validation rule rejects the literal strings `"true"`/`"false"`, so no bug exists there; not included below.

## Critical Issues

### CR-01: Password hashes and other hidden User attributes leak into the audit trail and are shipped to the browser

**File:** `app/Observers/AuditObserver.php:13-16` (and `:34-37`), `app/Models/AuditLog.php`, `app/Http/Controllers/Owner/AuditTrailController.php:19-34`

**Issue:** `AuditObserver::created()` and `::deleted()` call `$model->getAttributes()`, which returns the model's **raw, un-hidden** attribute array — bypassing the `#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]` protection declared on `App\Models\User`. Because `AuditObserver` is attached to `User` via `#[ObservedBy(AuditObserver::class)]`, every `User::create()` (currently only reachable via `UserFactory`/seeders, but this is a generic, reusable observer that will also fire for any future "create staff user" HTTP endpoint) persists the bcrypt password hash and `remember_token` into `audit_trail.new_values` as plain JSON.

`AuditLog` has no `#[Hidden]` attributes and `AuditTrailController::index()` selects/returns full `AuditLog` rows (no column restriction) directly as Inertia props. `resources/js/pages/owner/AuditTrail.vue`'s own `AuditEntry` interface types `old_values`/`new_values` as arbitrary records, and `tests/Feature/Owner/AuditTrailTest.php` ("preserve the D-03 before/after value shape") already proves `new_values.<field>` is present and readable in the actual Inertia response payload — i.e. this isn't just a theoretical DB-level concern, the raw JSON is served to the browser on every Owner/Admin page visit to Audit Trail.

**Verified with a temporary, non-committed test** (`User::factory()->create()` then read back `audit_trail.new_values`):

```
new_values: {"name":"Mr. Ayden Mertz MD","email":"...","email_verified_at":"...",
"password":"$2y$04$L52YE4w\/pCDf\/0MCJDdg6ONJcQxiaDa4wdI0NPCKoRFyaO\/7Dya.e",
"remember_token":"WlXrjXDv3u","role":"owner","updated_at":"...","created_at":"...","id":1}
```

An Owner/Admin browser session (or anyone who compromises one, e.g. via XSS or a stolen session) can harvest every staff member's password hash straight out of the Audit Trail page's network payload and run it through an offline cracker — completely defeating the purpose of `#[Hidden]` on `User` and the strong password policy configured in `AppServiceProvider`.

**Fix:** Never persist a model's raw attribute bag verbatim. Filter out the model's own hidden/sensitive keys (and any global sensitive-key denylist) before writing to `AuditLog`, e.g.:

```php
// app/Observers/AuditObserver.php
public function created(Model $model): void
{
    AuditLogger::recordMutation('created', $model, null, $this->redact($model, $model->getAttributes()));
}

public function deleted(Model $model): void
{
    AuditLogger::recordMutation('deleted', $model, $this->redact($model, $model->getAttributes()), null);
}

private function redact(Model $model, array $attributes): array
{
    return array_diff_key($attributes, array_flip($model->getHidden()));
}
```

Apply the same redaction inside `updated()`'s `getChanges()`/`getOriginal()` extraction for defense in depth (a future column added to `$hidden` should never require remembering to also update this observer). Consider additionally excluding `old_values`/`new_values` from the `AuditLog` payload sent to the frontend for actions where they aren't needed, rather than shipping full row JSON unconditionally.

## Warnings

### WR-01: Audit trail filters crash with a 500 on malformed date input

**File:** `app/Http/Controllers/Owner/AuditTrailController.php:24-25`

**Issue:** `$request->date('from')` / `$request->date('to')` are called directly on unvalidated query-string input. `Illuminate\Support\Traits\InteractsWithData::date()` throws `Carbon\Exceptions\InvalidFormatException` when the value can't be parsed, and nothing in this controller catches or validates it. Confirmed with a temporary test: `GET /owner/audit-trail?from=not-a-date` returns HTTP 500 instead of a graceful validation error, for an internal page a logged-in Owner/Admin could trivially hit by hand-editing the URL or bookmarking a stale filtered link.

**Fix:** Validate filter input before use, e.g. via a lightweight `FormRequest` or manual `Validator::make($request->only(['from','to']), ['from' => 'nullable|date', 'to' => 'nullable|date'])->validate()`, or wrap the parse:

```php
->when($request->filled('from'), function ($q) use ($request) {
    $from = rescue(fn () => $request->date('from'), report: false);
    $from && $q->whereDate('created_at', '>=', $from);
})
```

### WR-02: `UserFactory::withTwoFactor()` is a broken stub that throws when called

**File:** `database/factories/UserFactory.php:51`

**Issue:** `public function withTwoFactor(): static {}` has an empty body but declares a non-nullable `static` return type. PHP throws `Error: A function with return type must return a value` at call time. Confirmed directly: `User::factory()->withTwoFactor()->create()` throws. The only caller, `tests/Feature/Auth/AuthenticationTest.php:33`, is currently silently skipped (`skipUnlessFortifyHas(Features::twoFactorAuthentication())`, and `config/fortify.php` only enables `resetPasswords()`), which is why the full suite passes today. The moment two-factor auth is enabled in a later phase, this test will fail with a fatal error instead of exercising real 2FA setup.

**Fix:** Implement the state properly (e.g. set `two_factor_secret`/`two_factor_confirmed_at` via `afterCreating`, mirroring the `locked()`/`deactivated()` pattern already in this file), or remove the stub and the caller until 2FA is actually built, rather than leaving a method that crashes on invocation.

### WR-03: `SystemConfigurationSeeder` doesn't invalidate the config cache it writes through

**File:** `database/seeders/SystemConfigurationSeeder.php:123-128`, `app/Models/SystemConfiguration.php:97-100`

**Issue:** The seeder uses `updateOrCreate()` and its docblock explicitly claims it is "idempotent and safe to re-run." `SystemConfiguration::getInt/getBool/getArray/getString` cache resolved values with `Cache::rememberForever()`, and the model's own `invalidate()` helper exists specifically because "a stale cached value would otherwise persist indefinitely." `SystemConfigurationController::update()` correctly calls `invalidate()` after each write, but the seeder never does. If the seeder is re-run in an environment where a value was already read (and thus cached) earlier — e.g. to roll out a new default for an existing key — the change silently has no effect until something else clears the cache.

**Fix:** Invalidate after each write in the seeder loop:

```php
foreach ($configurations as $configuration) {
    SystemConfiguration::updateOrCreate(['key' => $configuration['key']], $configuration);
    SystemConfiguration::invalidate($configuration['key']);
}
```

### WR-04: Leftover generic `/dashboard` route bypasses the per-role portal design, and `Forbidden.vue` links to it

**File:** `routes/web.php:7-9`, `resources/js/pages/errors/Forbidden.vue:4,28`

**Issue:** `routes/web.php` still registers the starter-kit's generic `Route::inertia('dashboard', 'Dashboard')->name('dashboard')` under only `['auth', 'verified']` — no `role` middleware. This means every authenticated user, regardless of role, can visit `/dashboard` and get the same generic placeholder page, which directly contradicts this project's explicit constraint that RBAC gives "each role with its own dedicated portal (not a shared layout with filtered nav)." `tests/Feature/RoleBoundaryTest.php` never exercises this route, so it's an untested gap in the RBAC boundary this phase is meant to establish.

Compounding this, `resources/js/pages/errors/Forbidden.vue` — the page every role sees after a 403 — links "Return to your dashboard" to this same generic `dashboard()` route (`@/routes`) instead of the signed-in user's own role-specific portal (`role.portalRoute()`/`ownerNavItems`-style routes used elsewhere). A Cashier who gets a 403 and clicks "Return to your dashboard" lands on the generic starter-kit page, not `/cashier/dashboard`.

**Fix:** Either remove the generic `/dashboard` route/page (preferred, since it has no purpose once every role has a dedicated portal) or gate it behind the same role-aware redirect used by `LoginResponse`. Fix the Forbidden page to link to the user's own portal, e.g. pass the resolved portal route from `bootstrap/app.php`'s exception handler alongside `role`:

```php
// bootstrap/app.php
return Inertia::render('errors/Forbidden', [
    'role' => $request->user()?->role?->value,
    'dashboardHref' => $request->user() ? route($request->user()->role->portalRoute()) : route('login'),
])->toResponse($request)->setStatusCode(403);
```

### WR-05: A single login writes 3 audit_trail rows for one logical event, diluting the audit trail

**File:** `app/Listeners/Auth/HandleSuccessfulLogin.php:25-29`, `app/Actions/Fortify/CaptureAuthenticatedSessionId.php:26-29`, `app/Observers/AuditObserver.php:18-29`

**Issue:** Because `AuditObserver` is attached to `User` and fires on every `save()`, and login touches the `User` row twice via two separate `forceFill()->save()` calls (once in `HandleSuccessfulLogin` for `last_activity_at`/`failed_login_attempts`/`locked_until`, once in `CaptureAuthenticatedSessionId` for `current_session_id`), each successful login produces two `updated` audit rows in addition to the explicit `login` row that `AuditLogger::recordAuthEvent` already writes. Confirmed via test: one login by a fresh user produced `created`, `updated` (`last_activity_at`), `login`, `updated` (`current_session_id`) — 3 rows for what is conceptually one event. Over the lifetime of the system this means the `updated` filter in the Owner Audit Trail UI (meant to surface real business-data mutations, per the D-03 before/after design) will be flooded with routine session bookkeeping for every login by every user, making it harder to spot the mutations the feature actually exists to surface.

**Fix:** Either suppress `AuditObserver` for known session-bookkeeping-only changes (e.g. skip logging when the only changed keys are `current_session_id`/`last_activity_at`/`failed_login_attempts`/`locked_until` and the action is `updated`), or route these specific saves through `saveQuietly()`/a dedicated non-observed update path, since the explicit `login`/`logout`/`failed_login`/`lockout` audit rows already capture the meaningful signal for authentication events.

## Info

### IN-01: Login-lockout messaging enables account enumeration

**File:** `app/Actions/Fortify/EnsureAccountIsNotLocked.php:27-39`

**Issue:** A locked account gets `"Too many failed attempts... locked for :minutes minutes"` and a deactivated account gets `"This account has been deactivated"`, both distinct from the generic invalid-credentials message an unknown email receives. This lets an unauthenticated actor confirm whether a given email exists in the system (and its lock/active state) without a valid password. This is a common, often-accepted UX tradeoff for internal staff lockout messaging, but worth a deliberate call-out since it wasn't discussed as a tradeoff anywhere in the reviewed files.

**Fix:** If enumeration resistance matters for this system, use a single generic message for locked/deactivated/unknown-email cases and rely on the audit trail (already recording `failed_login`/`lockout`) for legitimate troubleshooting instead of surfacing state in the response.

### IN-02: `UserFactory` defaults every plain `create()` call to the highest-privilege role

**File:** `database/factories/UserFactory.php:26-36`

**Issue:** `definition()` sets `'role' => UserRole::Owner->value` as the default. Any test or seed call that does `User::factory()->create()` without an explicit role state (e.g. several tests in `PasswordResetTest`, `PasswordComplexityTest`, `SecurityTest`, `AccountLockoutTest`) unintentionally creates an Owner-privileged user. In a 7-role RBAC system, defaulting a factory to the most-privileged role is a smell: it can mask permission bugs in tests that don't realize they're exercising Owner-level access, and inverts the usual "least privilege by default" convention.

**Fix:** Default `definition()` to the lowest-privilege role (e.g. `FrontlineStaff`) and require tests that need Owner/Admin behavior to opt in explicitly via `->owner()`/`->admin()`, consistent with how the other five role states are already used everywhere else in this codebase.

### IN-03: `users.last_activity_at` doesn't track ongoing activity despite its name

**File:** `app/Listeners/Auth/HandleSuccessfulLogin.php:26`, `database/migrations/2026_08_31_165341_add_rbac_and_lockout_columns_to_users_table.php:21`

**Issue:** The `users.last_activity_at` column is written once, at login. The actual per-request idle-activity tracking used by `EnforceIdleSessionTimeout` lives entirely in the session (`last_activity_at` session key, a different value with the same name), which is never written back to this DB column. As written, `users.last_activity_at` really means "time of last login," not "last activity," which will likely confuse whoever builds a future "last seen" report against this column.

**Fix:** Either rename the column to `last_login_at` to match its actual semantics, or have `EnforceIdleSessionTimeout` periodically sync it to the DB if a true cross-session "last activity" signal is needed later.

---

_Reviewed: 2026-09-01T00:00:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
