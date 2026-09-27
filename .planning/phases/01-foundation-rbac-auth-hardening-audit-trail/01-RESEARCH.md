# Phase 1: Foundation — RBAC, Auth Hardening & Audit Trail - Research

**Researched:** 2026-08-31
**Domain:** Laravel 13 / Fortify 1.39 authentication hardening, custom RBAC middleware, Eloquent audit logging, key-value application settings
**Confidence:** HIGH (framework mechanics verified against installed vendor source + official Laravel 13.x docs) / MEDIUM (exact business-rule defaults, e.g. lockout minutes, are product decisions not framework facts)

<user_constraints>

## User Constraints (from CONTEXT.md)

### Locked Decisions

**D-01 (Audit Trail Logging Mechanism):** Mutating actions (Eloquent create/update/delete) are captured via a global model observer/listener pattern — a base trait or interface models opt into, registered once (e.g. in a service provider), rather than explicit `Audit::log(...)` calls scattered through controllers. New domain models added in Phases 2-8 (job_orders, transactions, etc.) get audit coverage automatically by adopting the trait, with no per-controller boilerplate required.

**D-02 (Auth Events):** Auth events (login, logout, failed attempt, lockout) are NOT Eloquent mutations and are not covered by the model observer. They're captured separately via Laravel's built-in auth events (`Illuminate\Auth\Events\Login`, `Logout`, `Failed`, `Lockout`) — Fortify already fires these natively, so no changes to Fortify's own login/logout flow are needed, just event listeners that write audit entries.

**D-03 (Structured Diffs):** Each audit entry for a mutating action stores structured before/after value diffs (e.g. `old_values`/`new_values` JSON columns) — old values on update, full attribute snapshot on create/delete — not just a human-readable description string. This lets Owner/Admin see exactly what changed, matching the audit trail's filterable, detailed-review intent (AUDIT-01).

**D-04 (Append-Only Enforcement Scope):** Append-only enforcement (AUDIT-02) targets the code layer only for Phase 1: the AuditLog model exposes no update/delete code path anywhere (no `::update()`/`::destroy()` calls, no route/controller ever exposes mutation), enforced by never writing that code rather than by DB-level restriction. DB-level enforcement (restricted-privilege grants or a MySQL trigger) is explicitly deferred — Laravel Cloud's managed-MySQL support for a restricted-privilege DB user is unverified per STATE.md, and Phase 1 should not block on verifying that. Revisit as defense-in-depth once Laravel Cloud's privilege model is confirmed.

### Claude's Discretion

Session enforcement (single active session, idle timeout), account lockout mechanism (attempts counter + configurable duration, separate from Fortify's existing IP-based rate limiter), system_configurations table shape (key-value vs fixed-column), and the role-portal routing/middleware pattern were not selected for discussion — these were the other candidate gray areas presented but the user chose to discuss only the audit trail logging mechanism. Claude/downstream agents have discretion on these, informed by: existing Fortify rate-limiter stays as IP-level throttle (unchanged), account-level lockout is a separate, additional mechanism (RBAC-03 is account lockout, distinct from the existing per-IP+email rate limit); system_configurations needs to support diverse value types (percentages, file format lists, durations) across many future phases (CONFIG-01 now, more in Phase 3/4/8) — lean toward whichever shape best supports that growth, decide during planning/research.

**This research resolves the discretion areas as follows (see Architecture Patterns below for full detail):** single-session via a `current_session_id` column + middleware comparison (Pattern 3); idle timeout via a per-request `last_activity_at` session check, distinct from `config('session.lifetime')` (Pattern 4); account lockout via `Illuminate\Auth\Events\Failed`/`Login` listeners plus a new Fortify pipeline step (Patterns 1-2); `system_configurations` as a key-value table grouped to match the UI-SPEC's Tabs, not fixed columns (Pattern 6).

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within Phase 1 scope. No scope-creep suggestions were raised.
</user_constraints>

<phase_requirements>

## Phase Requirements

| ID                    | Description                                                                                                                                                                | Research Support                                                                                                                                                                                      |
| --------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| RBAC-01               | User can log in with a role-scoped account (one of 7 roles) and lands on that role's own dedicated portal                                                                  | Pattern 1 (Fortify pipeline unaffected), `UserRole` enum + `portalRoute()` helper (Code Examples), Recommended Project Structure (per-role `resources/js/pages/{role}/` namespaces)                   |
| RBAC-02               | User is blocked (403) from accessing any route outside their assigned role, enforced server-side on every request                                                          | Pattern/Code Example 1: `EnsureUserHasRole` middleware + `role:` alias applied per portal route group; Pitfall 4; Custom Inertia 403 page code example                                                |
| RBAC-03               | User account locks out after 5 consecutive failed login attempts for a configurable duration                                                                               | Pattern 1 (`EnsureAccountIsNotLocked` pipeline step) + Pattern 2 (`Failed` event listener, counter, `locked_until`) + Pattern 6 seed keys (`account_lockout_max_attempts`, `account_lockout_minutes`) |
| RBAC-04               | User can only have one active session at a time; a new login invalidates the prior session                                                                                 | Pattern 3 (`current_session_id` column + `VerifySingleSession` middleware)                                                                                                                            |
| RBAC-05               | User is logged out automatically after a configurable idle session timeout                                                                                                 | Pattern 4 (`EnforceIdleSessionTimeout` middleware) distinct from passive `session.lifetime`                                                                                                           |
| RBAC-06               | User's password must meet complexity rules                                                                                                                                 | Pitfall 1 (critical: current `AppServiceProvider` config only enforces this in production — must be fixed)                                                                                            |
| RBAC-07               | Owner/Admin can deactivate a user account; deactivated accounts cannot log in; never hard-deleted                                                                          | Pattern 1 (`EnsureAccountIsNotLocked` also checks `is_active`); Open Question 2 (who can deactivate whom)                                                                                             |
| RBAC-08               | Every authentication event is written to the audit trail                                                                                                                   | Pattern 2/3/5 (`AuditLogger::recordAuthEvent()` called from all four auth-event listeners)                                                                                                            |
| AUDIT-01              | Owner/Admin can view a read-only, filterable audit trail of every mutating action and auth event                                                                           | Pattern 5 (`AuditObserver` + auth listeners writing to one shared `audit_trail` shape); Validation Architecture test map                                                                              |
| AUDIT-02              | No update/delete code path exists for any audit entry, for any role                                                                                                        | Pattern 5 `AuditLog` model shape (D-04); Anti-Patterns; Validation Architecture arch-test entry                                                                                                       |
| CONFIG-01             | Owner/Admin can configure rush fee %, DPI thresholds, file formats/size, SLA, artist break duration, session timeout, lockout duration, file retention, expense categories | Pattern 6 (`system_configurations` key-value schema, grouped by UI-SPEC tabs, cached accessor); Open Question 1 (expense_categories shape)                                                            |
| </phase_requirements> |

## Summary

Phase 1 adds no new Composer or npm packages. Every requirement (RBAC-01–08, AUDIT-01–02, CONFIG-01) is achievable with framework-native primitives already present in this codebase: Fortify's documented `authenticateThrough()` pipeline extension point, native `Illuminate\Auth\Events\*` listeners, a PHP enum-backed `role` column, a custom route middleware, Laravel 13's `#[ObservedBy]` attribute (which matches the project's existing convention of using PHP 8 attributes for Eloquent metadata, e.g. `#[Fillable]`/`#[Hidden]` on `User`), and a key-value `system_configurations` table. Two verified pitfalls materially affect planning: (1) `Password::defaults()` in `AppServiceProvider` only enforces complexity rules when `app()->isProduction()` is true — confirmed by reading the installed `Password::default()` source — so RBAC-06 is currently **not** satisfied outside production and must be fixed; (2) `tests/Pest.php:18` has `RefreshDatabase` disabled, which will make every Phase 1 feature test fail with unrelated SQL errors unless fixed first (already flagged in CONCERNS.md/CONTEXT.md, restated here because it blocks the Validation Architecture plan below).

**Primary recommendation:** Hand-roll all five mechanisms (audit trail, account lockout, single-session, idle timeout, key-value config) using native Laravel/Fortify extension points rather than adding third-party packages (`spatie/laravel-activitylog`, `owen-it/laravel-auditing`, `protonemedia/laravel-single-session`, `spatie/laravel-settings`, `spatie/laravel-permission`) — each package either conflicts with the project's structural constraints (append-only audit table with no delete path anywhere, including inside vendor code; single-enum-column RBAC, not a permissions matrix) or adds more surface area than the actual complexity warrants.

## Architectural Responsibility Map

| Capability                                  | Primary Tier                                          | Secondary Tier                              | Rationale                                                                                                                         |
| ------------------------------------------- | ----------------------------------------------------- | ------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| Role-scoped login + portal redirect         | API/Backend (Fortify + custom `LoginResponse`)        | Frontend Server (Inertia page resolution)   | Redirect-by-role must be decided server-side (source of truth for role); frontend only resolves which Vue page/layout to mount    |
| Server-side 403 role enforcement            | API/Backend (route middleware)                        | —                                           | Must never trust hidden nav; enforced per-request before controller runs                                                          |
| Account lockout / failed-attempt counter    | API/Backend (Fortify pipeline + auth event listeners) | Database (users table columns)              | Counter and lockout timestamp are persisted state, but the _decision_ to block happens in the auth pipeline                       |
| Single active session                       | API/Backend (auth event listener + middleware)        | Database (`sessions` table, already exists) | Session identity comparison must happen server-side per request; DB session driver already gives us the storage                   |
| Idle session timeout                        | API/Backend (middleware, per-request check)           | —                                           | Requires an explicit, user-visible logout with a specific message — not just Laravel's passive session-garbage-collection lottery |
| Audit trail capture                         | API/Backend (model observer + event listeners)        | Database (`audit_trail` table)              | Capture must be structural (attached to Eloquent lifecycle + auth events), not something controllers opt into per-action          |
| Audit trail viewing (filterable, read-only) | Frontend Server (Inertia page + filters)              | API/Backend (query/filter logic)            | Read-only list/filter UI; all mutation-prevention lives in the backend model, not hidden in the UI                                |
| System configuration (CONFIG-01)            | API/Backend (settings model + cache)                  | Database (`system_configurations`)          | Values are read on nearly every request (lockout threshold, idle timeout) — must be cached, not queried raw each time             |

## Standard Stack

### Core

No new packages. Everything below is already installed.

| Library                     | Version                                    | Purpose                                                                                       | Why Standard                              |
| --------------------------- | ------------------------------------------ | --------------------------------------------------------------------------------------------- | ----------------------------------------- |
| `laravel/framework`         | 13.29.0 [VERIFIED: composer show --direct] | Enums-as-casts, `#[ObservedBy]` attribute, Gate/Policy/middleware, `Illuminate\Auth\Events\*` | Already the project's core framework      |
| `laravel/fortify`           | 1.39.0 [VERIFIED: composer show --direct]  | Login pipeline (`authenticateThrough`), auth events, rate limiting                            | Already wired in `FortifyServiceProvider` |
| `inertiajs/inertia-laravel` | ^3.0 [VERIFIED: composer.json]             | Server-driven 403 page render, portal page routing                                            | Already the project's rendering bridge    |

### Supporting

| Library                                                                                            | Version                                          | Purpose                                                                                          | When to Use                                                   |
| -------------------------------------------------------------------------------------------------- | ------------------------------------------------ | ------------------------------------------------------------------------------------------------ | ------------------------------------------------------------- |
| `pestphp/pest` + `pestphp/pest-plugin-laravel`                                                     | 5.1.3 / 5.0.1 [VERIFIED: composer show --direct] | Feature tests for auth, RBAC, audit, config                                                      | All Phase 1 tests                                             |
| Laravel's `Cache` facade (database or array driver, already configured via `CACHE_STORE=database`) | n/a                                              | Cache `system_configurations` reads so lockout/idle-timeout checks aren't a DB hit every request | Any config read on the hot path (every authenticated request) |

### Alternatives Considered

| Instead of                                            | Could Use                                                                                                                                         | Tradeoff                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Hand-rolled `AuditObserver` + auth-event listeners    | `owen-it/laravel-auditing` or `spatie/laravel-activitylog` v5 [CITED: github.com/owen-it/laravel-auditing, spatie.be/docs/laravel-activitylog/v5] | Both are mature and add old/new value diffing out of the box, but (a) their internal `Audit`/`Activity` Eloquent models are full models with `update()`/`delete()` available even if the app never calls them — this does not literally satisfy "no update/delete code path exists anywhere" the way a hand-rolled model with those methods never written can; (b) neither natively unifies "model mutation" + "auth event" rows into one filterable table the way AUDIT-01 wants; (c) D-01 already specifies the trait/observer shape, so adopting a package would mean fighting its conventions immediately |
| Custom `EnsureUserHasRole` middleware + PHP enum      | `spatie/laravel-permission`                                                                                                                       | Massive overkill and structurally wrong for this project — PROJECT.md locks "single `role` enum column, one role per user," not a many-to-many roles/permissions/pivot-table model. Adding it would fight the locked architecture                                                                                                                                                                                                                                                                                                                                                                             |
| Hand-rolled single-session column + middleware        | `protonemedia/laravel-single-session` [CITED: github.com/protonemedia/laravel-single-session]                                                     | Small, single-purpose package; reasonable choice, but the entire mechanism is ~15 lines (one migration column + one listener + one middleware) using a table (`sessions`) already in this schema — not worth a new dependency for something this small, and CLAUDE.md requires approval before adding dependencies                                                                                                                                                                                                                                                                                            |
| Key-value `system_configurations` table (hand-rolled) | `spatie/laravel-settings` [CITED: github.com/spatie/laravel-settings]                                                                             | Spatie's package is designed around one PHP settings _class_ per logical group with typed properties serialized to JSON — great DX, but every new setting requires a code change + migration to the settings class, which cuts against CONFIG-01's explicit need to keep adding config keys across Phases 3/4/8 without schema churn. A plain key-value table is the better fit here per Freek Van der Herten's own tradeoff writeup [CITED: freek.dev/1828-store-strongly-typed-settings-in-a-laravel-app]                                                                                                   |

**Installation:** None required.

**Version verification:** `composer show --direct` run 2026-08-31 confirms `laravel/framework` 13.29.0, `laravel/fortify` 1.39.0, `pestphp/pest` 5.1.3 as installed. No package versions need bumping for Phase 1.

## Package Legitimacy Audit

Not applicable — Phase 1 introduces zero new Composer or npm packages. The only new additions are shadcn-vue component files (`table`, `pagination`, `alert-dialog`, `tabs`, `switch`) added via `npx shadcn add {name}` from the already-configured official registry (per `01-UI-SPEC.md` §Registry Safety, "not required" for vetting) — these copy Vue source files into the repo, they are not registry package installs, and they depend only on `reka-ui`/`class-variance-authority`/`tailwind-merge`, all already installed.

**Packages removed due to slopcheck verdict:** none (no packages proposed).
**Packages flagged as suspicious:** none.

## Architecture Patterns

### System Architecture Diagram

```text
┌────────────────────────────────────────────────────────────────────┐
│ Browser                                                              │
│  Login.vue ──(POST /login)──────────────────────────────┐           │
│  {role}/Dashboard.vue  {role}/AuditTrail.vue  etc.       │           │
└───────────────────────────────────────────────────────────┼─────────┘
                                                              ▼
┌────────────────────────────────────────────────────────────────────┐
│ Fortify authenticateThrough() pipeline (FortifyServiceProvider)     │
│  EnsureLoginIsNotThrottled (existing, IP+email/min)                 │
│      ▼                                                              │
│  EnsureAccountIsNotLocked (NEW) ── reads users.locked_until,        │
│      │                            users.is_active                  │
│      ▼ (throws ValidationException if locked/deactivated)          │
│  AttemptToAuthenticate (existing) ── fires Illuminate\Auth\Events\  │
│      │                              {Failed|Login}                  │
│      ▼                                                              │
│  PrepareAuthenticatedSession (existing, regenerates session id)     │
└───────────────────────────────────┬──────────────────────────────────┘
                                     ▼
┌────────────────────────────────────────────────────────────────────┐
│ Auth event listeners (registered once, EventServiceProvider)        │
│  Failed   → RecordFailedLoginAttempt (increments counter, may lock) │
│  Login    → ResetLoginAttempts + InvalidateOtherSessions            │
│             + AuditLogger::recordAuthEvent('login')                 │
│  Logout   → AuditLogger::recordAuthEvent('logout')                  │
│  Lockout  → AuditLogger::recordAuthEvent('lockout')  (native, IP)   │
└───────────────────────────────────┬──────────────────────────────────┘
                                     ▼
┌────────────────────────────────────────────────────────────────────┐
│ Per-request middleware stack (bootstrap/app.php)                    │
│  auth ─▶ VerifySingleSession ─▶ EnforceIdleTimeout ─▶ role:{roles}   │
│  (redirects to /login w/ flash message on any failure)              │
└───────────────────────────────────┬──────────────────────────────────┘
                                     ▼
┌────────────────────────────────────────────────────────────────────┐
│ Controllers (thin) ──▶ Eloquent models with #[ObservedBy(AuditObserver::class)] │
│                                     │                                │
│                                     ▼                                │
│                        AuditObserver::created/updated/deleted        │
│                        writes audit_trail row (old_values/new_values)│
└────────────────────────────────────┬─────────────────────────────────┘
                                      ▼
                        ┌───────────────────────────┐
                        │ audit_trail (append-only)  │
                        │ no ::update()/::destroy()  │
                        │ code path exists anywhere  │
                        └───────────────────────────┘
```

### Recommended Project Structure

```
app/
├── Enums/
│   └── UserRole.php                    # backed enum: Owner, Admin, FrontlineStaff, Artist, Cashier, ProductionStaff, AccountingStaff
├── Actions/Fortify/
│   ├── EnsureAccountIsNotLocked.php    # NEW pipeline step, inserted before AttemptToAuthenticate
│   └── ResetUserPassword.php           # existing
├── Listeners/Auth/
│   ├── RecordFailedLoginAttempt.php    # Illuminate\Auth\Events\Failed
│   ├── HandleSuccessfulLogin.php       # Illuminate\Auth\Events\Login (reset counter, single-session, audit)
│   ├── HandleLogout.php                # Illuminate\Auth\Events\Logout (audit)
│   └── RecordLockoutEvent.php          # Illuminate\Auth\Events\Lockout (native IP-throttle lockout, audit)
├── Http/Middleware/
│   ├── EnsureUserHasRole.php           # NEW, alias 'role'
│   ├── VerifySingleSession.php         # NEW, alias 'single-session'
│   └── EnforceIdleSessionTimeout.php   # NEW, alias 'idle-timeout'
├── Models/
│   ├── User.php                        # + role, is_active, failed_login_attempts, locked_until, current_session_id, last_activity_at
│   ├── AuditLog.php                    # append-only, no update()/delete() usage anywhere in app code
│   └── SystemConfiguration.php         # key-value, cached
├── Observers/
│   └── AuditObserver.php               # created/updated/deleted -> AuditLog::create()
├── Support/
│   └── AuditLogger.php                 # small service: recordAuthEvent(), recordMutation() shared by observer + listeners
└── Concerns/
    └── SystemConfigValidationRules.php # Form Request validation trait, matches existing pattern

database/migrations/
├── ..._add_rbac_and_lockout_columns_to_users_table.php
├── ..._create_audit_trail_table.php
└── ..._create_system_configurations_table.php

resources/js/pages/
├── owner/          # Owner + Admin share this portal per UI-SPEC (nav: Dashboard, Users, Audit Trail, System Config)
├── admin/          # thin wrapper reusing owner/* components where identical, or same namespace with role-gated nav — decide in planning
├── frontline-staff/  Dashboard.vue  (empty portal home, no nav yet)
├── artist/           Dashboard.vue
├── cashier/          Dashboard.vue
├── production-staff/ Dashboard.vue
└── accounting-staff/ Dashboard.vue
```

### Pattern 1: Fortify Authentication Pipeline Extension (Account Lockout + Deactivation Gate)

**What:** Insert a custom invokable action into Fortify's documented `authenticateThrough()` pipeline, before `AttemptToAuthenticate`, to reject locked-out or deactivated accounts with a specific message — without touching Fortify's own login controller.
**When to use:** RBAC-03 (lockout), RBAC-07 (deactivated accounts cannot log in).
**Example (default pipeline, verified against installed Fortify 1.39 docs):**

```php
// Source: https://laravel.com/docs/13.x/fortify#customizing-the-authentication-pipeline [CITED]
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Illuminate\Http\Request;

Fortify::authenticateThrough(function (Request $request) {
    return array_filter([
        config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
        config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
        \App\Actions\Fortify\EnsureAccountIsNotLocked::class, // NEW — insert here
        Features::enabled(Features::twoFactorAuthentication()) ? RedirectIfTwoFactorAuthenticatable::class : null,
        AttemptToAuthenticate::class,
        PrepareAuthenticatedSession::class,
    ]);
});
```

`EnsureAccountIsNotLocked` looks up the user by `Fortify::username()`, and if `!$user->is_active` or `$user->locked_until?->isFuture()`, throws a `ValidationException` with the exact UI-SPEC copy ("Too many failed attempts. This account is locked for {n} minutes..." / a deactivated-account message) and `abort` before `AttemptToAuthenticate` ever runs `Guard::attempt()`. This is additive-only — it does not modify `AttemptToAuthenticate`, `PrepareAuthenticatedSession`, or any Fortify controller, satisfying D-02's "no changes to Fortify's own login/logout flow."

**Verified event-firing behavior** [VERIFIED: vendor source, `laravel/fortify` `AttemptToAuthenticate.php` via GitHub 1.x branch]: on the default (non-`authenticateUsing`) path used here, `Illuminate\Auth\Events\Failed` fires reliably through `Guard::attempt()`'s own internal event dispatch when credentials are wrong — no known 2FA-pipeline-ordering issue applies to this app because `config/fortify.php` only enables `Features::resetPasswords()`; `twoFactorAuthentication()` is not in the features array, so `RedirectIfTwoFactorAuthenticatable` never runs and cannot short-circuit `AttemptToAuthenticate` (the specific bug reported in laravel/fortify#145). Still write a feature test asserting `Failed`/`Login` fire, since this depends on `config/fortify.php` staying as-is.

### Pattern 2: Account Lockout Counter via Auth Event Listeners

**What:** `Illuminate\Auth\Events\Failed` listener increments a `failed_login_attempts` counter on the `User` row (if a user was resolved); when it reaches the configured threshold, sets `locked_until` and writes an audit entry. `Illuminate\Auth\Events\Login` listener resets both to zero/null.
**When to use:** RBAC-03, RBAC-08. Distinct from the existing IP+email `RateLimiter::for('login', ...)` in `FortifyServiceProvider`, which stays unchanged (per CONTEXT.md discretion notes).
**Example:**

```php
// app/Listeners/Auth/RecordFailedLoginAttempt.php
public function handle(Failed $event): void
{
    if (! $event->user instanceof User) {
        return; // unknown email — IP rate limiter already covers this case
    }

    $threshold = SystemConfiguration::getInt('account_lockout_max_attempts', default: 5);
    $duration  = SystemConfiguration::getInt('account_lockout_minutes', default: 15);

    $event->user->increment('failed_login_attempts');

    if ($event->user->failed_login_attempts >= $threshold) {
        $event->user->forceFill(['locked_until' => now()->addMinutes($duration)])->save();
        AuditLogger::recordAuthEvent($event->user, 'lockout', request());
    }
}
```

Note `$event->user` is only non-null when Fortify's guard resolved a real user row for the given credentials before the password check failed — confirm this against the installed `SessionGuard::attempt()`/`Failed` event contract during implementation (it is populated when the user exists but the password is wrong; it is `null` when no user matches the identifier at all).

### Pattern 3: Single Active Session Enforcement

**What:** Store the authoritative session ID on the `users` row at login; a global middleware compares the _current_ session ID against that stored value on every authenticated request and force-logs-out on mismatch with the UI-SPEC's specific copy.
**When to use:** RBAC-04.
**Why this shape over deleting the old `sessions` row directly:** Deleting the prior session row (the naive approach many blog posts show [CITED: jesusamieiro.com/limit-one-session-per-user-in-laravel-5]) silently converts the old browser to an anonymous guest on its next request — Laravel has no data left to know _why_ it lost its session, so it cannot show "signed out because you logged in elsewhere" (a specific UI-SPEC requirement). Storing the winning session ID on the user row lets the stale request's middleware still resolve `auth()->user()` (session data still exists momentarily) and compare IDs before invalidating, so the specific message can be shown.
**Example:**

```php
// app/Listeners/Auth/HandleSuccessfulLogin.php (on Illuminate\Auth\Events\Login)
$event->user->forceFill([
    'current_session_id' => session()->getId(),
    'failed_login_attempts' => 0,
    'locked_until' => null,
])->save();

// app/Http/Middleware/VerifySingleSession.php
public function handle(Request $request, Closure $next): Response
{
    if ($user = $request->user()) {
        if ($user->current_session_id && $user->current_session_id !== session()->getId()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('sessionMessage', __('You were signed out because this account logged in from another device.'));
        }
    }
    return $next($request);
}
```

Requires one new `users.current_session_id` nullable string column. No new package, no new table (the existing `sessions` table from `0001_01_01_000000_create_users_table.php` is unaffected).

### Pattern 4: Idle Session Timeout

**What:** A configurable idle-timeout distinct from `config('session.lifetime')` (currently 120 minutes, `SESSION_LIFETIME` env-driven [VERIFIED: `config/session.php`]) — RBAC-05 needs an explicit, user-facing "your session expired after {n} minutes of inactivity" message, which Laravel's passive session-lottery garbage collection does not provide.
**When to use:** RBAC-05.
**Example:**

```php
// app/Http/Middleware/EnforceIdleSessionTimeout.php
public function handle(Request $request, Closure $next): Response
{
    $timeoutMinutes = SystemConfiguration::getInt('session_idle_timeout_minutes', default: 20);
    $lastActivity = $request->session()->get('last_activity_at');

    if ($lastActivity && now()->diffInMinutes($lastActivity) > $timeoutMinutes) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('sessionMessage', __('Your session expired after :n minutes of inactivity. Log in again to continue.', ['n' => $timeoutMinutes]));
    }

    $request->session()->put('last_activity_at', now());
    return $next($request);
}
```

Order matters: register `VerifySingleSession` and `EnforceIdleSessionTimeout` on the same authenticated middleware group, both after `auth`, before the `role:*` check.

### Pattern 5: Audit Trail via `#[ObservedBy]` Attribute + Auth Event Listeners

**What:** Per D-01, a single `AuditObserver` class attached declaratively via the `#[ObservedBy(AuditObserver::class)]` PHP attribute [VERIFIED: Laravel 10.44+/11+, confirmed still current in Laravel 13 — `Illuminate\Database\Eloquent\Attributes\ObservedBy`], matching this project's existing convention of using PHP 8 attributes for Eloquent metadata (`#[Fillable]`, `#[Hidden]` already on `User`). New domain models in Phases 2–8 opt in with one attribute line — zero controller boilerplate, exactly matching D-01's intent.
**Example:**

```php
// app/Models/User.php (once User itself needs auditing, e.g. is_active toggles)
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[ObservedBy(\App\Observers\AuditObserver::class)]
class User extends Authenticatable { /* ... */ }

// app/Observers/AuditObserver.php
class AuditObserver
{
    public function created(Model $model): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'created',
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_values' => null,
            'new_values' => $model->getAttributes(),
            'ip_address' => request()->ip(),
        ]);
    }

    public function updated(Model $model): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'updated',
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_values' => $model->getOriginal(), // pre-change snapshot of changed keys only, filter via getChanges() keys
            'new_values' => $model->getChanges(),
            'ip_address' => request()->ip(),
        ]);
    }

    public function deleted(Model $model): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'deleted',
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_values' => $model->getAttributes(),
            'new_values' => null,
            'ip_address' => request()->ip(),
        ]);
    }
}
```

`old_values`/`new_values` cast to `array` on `AuditLog` (JSON columns) — directly satisfies D-03. Auth events (`Login`/`Logout`/`Failed`/`Lockout`) are handled by separate listeners (Pattern 2/3 above) calling the same `AuditLogger::recordAuthEvent()` helper so both paths write into the same `audit_trail` table shape, satisfying AUDIT-01's single filterable list of "every mutating action and auth event."

**`AuditLog` model — append-only enforcement (D-04):**

```php
class AuditLog extends Model
{
    protected $table = 'audit_trail';
    public $timestamps = false; // created_at only, set explicitly; no updated_at ever needed
    protected $casts = ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    // No fillable beyond create-time fields. No update()/delete() call exists anywhere
    // in app/ — grep for `AuditLog::` before every future PR to keep this true.
}
```

### Pattern 6: Key-Value `system_configurations` Table

**What:** A single table, one row per config key, grouped to match the UI-SPEC's Tabs (`Security`, `Business Rules`, `File Handling`), typed via a `type` column for UI rendering + validation, cached via `Cache::rememberForever()` invalidated on save.
**When to use:** CONFIG-01, and every later phase that needs a new business rule (DPI thresholds already listed here for Phase 3, SLA for Phase 3/6, etc.) — no migration needed to add a new key, only a seeder/data row.
**Schema:**

```php
Schema::create('system_configurations', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->string('group'); // 'security' | 'business_rules' | 'file_handling'
    $table->json('value');
    $table->string('type'); // 'integer' | 'decimal' | 'boolean' | 'string' | 'array'
    $table->string('label');
    $table->text('description')->nullable();
    $table->timestamps();
});
```

**Example accessor:**

```php
class SystemConfiguration extends Model
{
    protected $casts = ['value' => 'array'];

    public static function getInt(string $key, int $default): int
    {
        return (int) Cache::rememberForever("config.$key", fn () => static::where('key', $key)->value('value') ?? $default);
    }
}
```

Invalidate the cache key in the config controller's `update()` method (`Cache::forget("config.$key")`) right after save, matching the existing `Inertia::flash('toast', ...)` pattern for user feedback.

**Seed values for Phase 1 (defaults, to be confirmed with user during config UI build):**

| key                            | group          | type    | suggested default                                                  |
| ------------------------------ | -------------- | ------- | ------------------------------------------------------------------ |
| `account_lockout_max_attempts` | security       | integer | 5 (matches RBAC-03 literal text)                                   |
| `account_lockout_minutes`      | security       | integer | 15                                                                 |
| `session_idle_timeout_minutes` | security       | integer | 20                                                                 |
| `password_min_length`          | security       | integer | 12 (matches current prod-only `AppServiceProvider` value)          |
| `rush_fee_percentage`          | business_rules | decimal | — (Phase 3 consumer, seed placeholder now)                         |
| `expense_categories`           | business_rules | array   | `["Utilities","Supplies","Rent"]` placeholder — see Open Questions |
| `file_retention_days`          | file_handling  | integer | — (Phase 3/4 consumer)                                             |
| `max_artist_break_minutes`     | business_rules | integer | — (Phase 4 consumer)                                               |

### Anti-Patterns to Avoid

- **Deleting the stale `sessions` row directly on login instead of comparing a stored `current_session_id`:** loses the ability to show the specific "signed out from another device" message the UI-SPEC requires.
- **Relying on `config('session.lifetime')` alone for RBAC-05:** that config is a passive, best-effort garbage-collection lifetime (subject to the `lottery` chance, [VERIFIED: `config/session.php`]), not an active per-request idle check with a distinct user-facing message.
- **Enabling `Features::twoFactorAuthentication()` without re-verifying the Fortify pipeline order:** doing so would reintroduce the exact `RedirectIfTwoFactorAuthenticatable`-before-`AttemptToAuthenticate` ordering issue [CITED: github.com/laravel/fortify/issues/145] that can suppress `Failed` events. Not in scope for Phase 1 (2FA isn't a v1 requirement) — flag if a later phase considers it.
- **Writing any `AuditLog::update()`/`AuditLog::destroy()`/`->save()` on an existing row, even for "fixing a typo":** breaks D-04/AUDIT-02 structurally. If a correction is ever needed, it must be a new compensating row, never a mutation.

## Don't Hand-Roll

| Problem                                         | Don't Build                                | Use Instead                                                                                             | Why                                                                                                       |
| ----------------------------------------------- | ------------------------------------------ | ------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| Password complexity validation                  | Custom regex for uppercase/number/symbol   | `Illuminate\Validation\Rules\Password` (already used in `AppServiceProvider`/`PasswordValidationRules`) | Built-in, tested, supports `uncompromised()` (HaveIBeenPwned check) which a hand-rolled regex never would |
| IP-based login throttling                       | Custom attempt-counting cache logic        | Laravel's `RateLimiter` facade (already configured in `FortifyServiceProvider`)                         | Already correctly implemented; RBAC-03's account-level lockout is _additional_, not a replacement         |
| Attribute diffing for audit "before/after"      | Custom recursive array-diff function       | Eloquent's native `getOriginal()` / `getChanges()` / `getDirty()`                                       | Framework already tracks per-attribute original vs. current state — no custom diff algorithm needed       |
| CSRF protection on config/user-management forms | Custom token middleware                    | Laravel's built-in `VerifyCsrfToken` (already active on the `web` middleware group)                     | Already on by default; nothing to add                                                                     |
| Role check UI hiding                            | Hiding nav items as the _only_ enforcement | Server-side `role` middleware (RBAC-02 explicitly requires this)                                        | Hidden nav is a UX nicety, never a security boundary                                                      |

**Key insight:** Every mechanism this phase needs is either already installed (Fortify, rate limiting, CSRF, `Password` rules) or small enough (single-session, idle timeout, audit observer, key-value config) that reaching for a third-party package would add more integration risk (fighting the package's own conventions/migrations/models) than it removes.

## Common Pitfalls

### Pitfall 1: `Password::defaults()` silently drops complexity rules outside production

**What goes wrong:** RBAC-06 ("password must meet complexity rules") appears satisfied because `AppServiceProvider::configureDefaults()` calls `Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()` — but only `app()->isProduction() ? ... : null` — so in `local`/`testing`/`staging` environments the closure returns `null`.
**Why it happens:** [VERIFIED: read directly from `vendor/laravel/framework/.../Rules/Password.php:166-173`, `laravel/framework` 13.29.0] `Password::default()` does `$password instanceof Rule ? $password : static::min(8)` — a `null` return silently falls back to an 8-character-minimum-only rule with **no** mixed-case/number/symbol/uncompromised requirement.
**How to avoid:** Remove the `app()->isProduction()` conditional (or drive it from `system_configurations` so Owner/Admin can tune min length via CONFIG-01, but always keep at least the complexity _shape_ — mixed case, numbers, symbols — active in every environment). RBAC-06 has no "production only" carve-out in its requirement text.
**Warning signs:** A feature test that creates a user with e.g. `password` (all-lowercase, no symbols/numbers) in the default `testing` environment and it passes validation.

### Pitfall 2: `RefreshDatabase` is disabled — every Phase 1 feature test will fail on unrelated SQL errors

**What goes wrong:** `tests/Pest.php:18` has `->use(RefreshDatabase::class)` commented out. [VERIFIED: CONCERNS.md, confirmed by reading `tests/Pest.php` directly] 17/27 existing feature tests currently fail with `SQLSTATE[HY000]: ... no such table: users`.
**Why it happens:** One commented-out line silently downgrades the whole feature suite.
**How to avoid:** Uncomment it as the very first Phase 1 task, before writing any new feature test, and confirm `php artisan test --compact` drops to 0 unrelated-to-behavior failures.
**Warning signs:** Any new Phase 1 test failing with a "no such table" error instead of an assertion failure.

### Pitfall 3: `Illuminate\Auth\Events\Failed`'s `$event->user` is not always a real user

**What goes wrong:** If the account-lockout listener assumes `$event->user` is always populated, it will throw a null-property error on typo'd/unknown emails.
**Why it happens:** Laravel's `Failed` event carries `$event->user` as the resolved user model _if the guard found a matching row before the password check_, and `null` if no user matched the identifier at all.
**How to avoid:** Guard the listener with `if (! $event->user instanceof User) { return; }` before incrementing any counter (shown in Pattern 2 above). Unknown-email failed attempts are still covered by the existing IP+email rate limiter and, optionally, a generic "unknown identifier" audit row without a `user_id`.
**Warning signs:** 500 errors on login attempts with a non-existent email during testing.

### Pitfall 4: Applying `role:` middleware to a route group doesn't stop a user from _seeing_ a link to it

**What goes wrong:** Teams sometimes treat hiding a sidebar item as sufficient RBAC enforcement and forget the middleware, or forget to also gate the Wayfinder-generated action itself.
**Why it happens:** Inertia SPA navigation makes it easy to visually "hide" a route without realizing the underlying HTTP route is still reachable by typing the URL.
**How to avoid:** Every route group behind a portal namespace (`owner.*`, `artist.*`, etc.) must carry `role:` middleware in `routes/*.php`/`bootstrap/app.php` — write a feature test per portal that logs in as a _wrong_ role and asserts a 403, per RBAC-02's explicit "enforced server-side even via direct URL."
**Warning signs:** A passing UI test with no corresponding feature test that hits the route directly as an unauthorized role.

### Pitfall 5: `Cache::rememberForever()` on `system_configurations` values must be invalidated on save, not just on TTL

**What goes wrong:** If the config controller updates a row but forgets `Cache::forget()`, the old lockout duration / idle timeout keeps applying until the cache store is flushed or the app restarts.
**Why it happens:** `rememberForever` has no TTL to naturally expire it.
**How to avoid:** Always pair every config write with an explicit `Cache::forget("config.$key")` (or a model `saved` event that does this automatically — itself a good use of a _second_, small observer, separate from `AuditObserver`).
**Warning signs:** Config UI shows "Save Changes" toast succeeds but the new lockout minutes don't take effect on the next lockout.

## Code Examples

### Custom role middleware (registered as alias)

```php
// Source: pattern confirmed against https://laravel.com/docs/13.x/authorization#via-middleware [CITED — adapted for role, not policy-based `can:`]
// app/Http/Middleware/EnsureUserHasRole.php
public function handle(Request $request, Closure $next, string ...$roles): Response
{
    $user = $request->user();

    abort_if(! $user || ! in_array($user->role->value, $roles, true), 403);

    return $next($request);
}

// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\EnsureUserHasRole::class,
    ]);
})

// routes/web.php
Route::middleware(['auth', 'role:owner,admin'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('/audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail');
    // ...
});
```

### Role enum (TitleCase keys per project CLAUDE.md convention)

```php
// app/Enums/UserRole.php
enum UserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case FrontlineStaff = 'frontline_staff';
    case Artist = 'artist';
    case Cashier = 'cashier';
    case ProductionStaff = 'production_staff';
    case AccountingStaff = 'accounting_staff';

    public function portalRoute(): string
    {
        return match ($this) {
            self::Owner, self::Admin => 'owner.dashboard',
            self::FrontlineStaff => 'frontline-staff.dashboard',
            self::Artist => 'artist.dashboard',
            self::Cashier => 'cashier.dashboard',
            self::ProductionStaff => 'production-staff.dashboard',
            self::AccountingStaff => 'accounting-staff.dashboard',
        };
    }
}

// app/Models/User.php casts()
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
        'is_active' => 'boolean',
        'locked_until' => 'datetime',
        'last_activity_at' => 'datetime',
    ];
}
```

### Custom Inertia 403 page (matches UI-SPEC's "dedicated Inertia error/response")

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
        if ($response->getStatusCode() === 403 && ! $request->expectsJson()) {
            return Inertia::render('errors/Forbidden', [
                'role' => $request->user()?->role?->value,
            ])->toResponse($request)->setStatusCode(403);
        }
        return $response;
    });
})
```

[ASSUMED — this exact `$exceptions->respond()` shape is a well-known community pattern for Inertia + Laravel 11+/12+ exception handling, but has not been executed against this specific Laravel 13.29 installation in this research session; verify with a feature test asserting an Inertia 403 component renders, not the framework's default JSON/HTML 403.]

## State of the Art

| Old Approach                                                                      | Current Approach                                                                                            | When Changed                                                                                           | Impact                                                                                                                                                                                                |
| --------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `protected $fillable` / `protected $casts` properties                             | `#[Fillable]`/`#[Hidden]` attributes + `casts()` method                                                     | Laravel 11 (casts method), already adopted in this repo's `User` model                                 | New models should follow the same attribute-based style, including `#[ObservedBy]` for audit wiring                                                                                                   |
| Manually calling `Model::observe(Observer::class)` in a service provider `boot()` | `#[ObservedBy(Observer::class)]` class attribute                                                            | Laravel 10.44 [CITED: laravel-news.com/laravel-10-44-0], carried into 13.x                             | Declarative, co-located with the model, matches this project's existing attribute convention — no `AppServiceProvider::boot()` registration needed per model                                          |
| Controller-level `if (!Gate::allows(...)) abort(403)` scattered per action        | `Illuminate\Routing\Attributes\Controllers\Authorize` attribute or route-level `->can()`/`role:` middleware | Available in Laravel 13.x per current docs [CITED: laravel.com/docs/13.x/authorization#via-middleware] | For this phase's role-boundary use case (not per-model policies), a route-group `role:` middleware alias is simpler than per-controller-method attributes and matches RBAC-02's "every route" framing |

**Deprecated/outdated:** None specific to this phase — the starter kit is current (Laravel 13.29, Fortify 1.39) with no legacy patterns to migrate away from.

## Assumptions Log

| #   | Claim                                                                                                                                                                                                                                                             | Section                       | Risk if Wrong                                                                                                                                                                                                                                                                                           |
| --- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A1  | `$event->user` on `Illuminate\Auth\Events\Failed` is populated when the identifier resolves to a real user and null otherwise (training-knowledge description of `SessionGuard`/`EloquentUserProvider` behavior, not re-derived from this session's vendor reads) | Pattern 2 / Pitfall 3         | If wrong, the lockout counter either never increments (silent RBAC-03 failure) or throws on unknown emails — must confirm with a quick `tinker`/test before relying on it                                                                                                                               |
| A2  | The custom Inertia 403 exception-render snippet (`$exceptions->respond()`) works unmodified against Laravel 13.29 + Inertia 3.3                                                                                                                                   | Code Examples                 | If the exact contract changed, the 403 page falls back to Laravel's default error page instead of the UI-SPEC's designed one — cheap to verify with one feature test                                                                                                                                    |
| A3  | Seed default values (5 attempts / 15 min lockout / 20 min idle timeout / 12-char password minimum) are reasonable starting points, not confirmed business requirements beyond RBAC-03's literal "5 consecutive failed attempts"                                   | Pattern 6 seed table          | Low risk — CONFIG-01 makes all of these Owner/Admin-editable after seeding, so a wrong default is a one-click fix, not a rebuild                                                                                                                                                                        |
| A4  | `expense_categories` belongs in `system_configurations` as a JSON array for Phase 1, rather than its own relational table                                                                                                                                         | Pattern 6 / Open Questions    | If Phase 8's EXP-01 needs a real FK from `expenses.category_id` to a categories table (for referential integrity as categories are renamed/retired), this JSON list needs migrating to a table later — flagged explicitly below so Phase 8 planning re-examines it, not silently inherits a wrong shape |
| A5  | Owner and Admin share one portal namespace/nav (not two separate portals) for the three admin-only screens (User Management, System Config, Audit Trail), based on UI-SPEC wording "Owner and Admin are the only roles that see..."                               | Recommended Project Structure | If Owner and Admin are meant to be two fully separate portals with different capabilities (e.g., only Owner can approve write-offs later), building them as one shared namespace now could require a split later — low cost either way at Phase 1's scale (both currently get identical screens)        |

**If this table is empty:** N/A — see rows above.

## Open Questions

1. **Does `expense_categories` need to be its own table now or is a config-list sufficient for Phase 1?**
    - What we know: CONFIG-01 (Phase 1) asks for it to be "configurable"; EXP-01 (Phase 8) is the actual consumer that records an expense "with a category."
    - What's unclear: Whether Phase 8 needs a `category_id` foreign key (for rename-safety, reporting grouping) or a free-text/enum-like value is acceptable.
    - Recommendation: Ship it as a JSON array value in `system_configurations` for Phase 1 (matches every other CONFIG-01 field's shape); flag for re-evaluation when Phase 8 (Expenses & Reporting) is planned, since promoting it to a real table later is a small, isolated migration.

2. **Should Owner and Admin be identical in capability for Phase 1, or does Owner need something Admin doesn't (e.g., could Admin also deactivate an Owner account)?**
    - What we know: RBAC-07 says "Owner/Admin can deactivate a user account" — both roles named together.
    - What's unclear: Whether an Admin should be able to deactivate the Owner (privilege-escalation/lockout risk) or another Admin.
    - Recommendation: Default to Owner-only being able to deactivate another Owner or an Admin account (Admin can deactivate the 5 staff roles only); confirm this narrow authorization rule during planning since it's a policy-method detail (`UserPolicy::deactivate($actor, $target)`), not a research blocker.

3. **Laravel Cloud managed-MySQL restricted-privilege DB user support** — carried forward from STATE.md, already explicitly deferred by CONTEXT.md D-04.
    - What we know: D-04 defers all DB-level append-only enforcement to a later phase; Phase 1 only needs code-layer enforcement.
    - What's unclear: Whether Laravel Cloud's managed MySQL even exposes a mechanism to grant a restricted (`INSERT`-only, no `UPDATE`/`DELETE`) privilege on a single table to the app's DB user.
    - Recommendation: No action needed for Phase 1. Do not attempt to verify this during Phase 1 planning/execution — it's explicitly out of scope per D-04. Revisit only when defense-in-depth hardening is prioritized.

## Environment Availability

| Dependency                                              | Required By                                      | Available                                              | Version                                           | Fallback                                                                                               |
| ------------------------------------------------------- | ------------------------------------------------ | ------------------------------------------------------ | ------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| PHP                                                     | Backend runtime                                  | ✓                                                      | 8.4.3 [VERIFIED: `php -v`]                        | —                                                                                                      |
| Composer                                                | Dependency management                            | ✓                                                      | 2.8.4 [VERIFIED: `composer -V`]                   | —                                                                                                      |
| Node.js                                                 | Frontend build                                   | ✓                                                      | 22.12.0 [VERIFIED: `node -v`]                     | —                                                                                                      |
| npm                                                     | JS package management                            | ✓                                                      | 10.9.0 [VERIFIED: `npm -v`]                       | —                                                                                                      |
| SQLite (pdo_sqlite + CLI)                               | Local dev DB (`DB_CONNECTION=sqlite` per `.env`) | ✓                                                      | 3.31.1 [VERIFIED: `php -m` / `sqlite3 --version`] | —                                                                                                      |
| MySQL                                                   | Production DB (per PROJECT.md constraint)        | Not checked locally — not needed for Phase 1 local dev | —                                                 | Continue on SQLite locally; switch `.env` before real production use, as already flagged in PROJECT.md |
| Laravel Cloud managed MySQL restricted-privilege grants | Deferred defense-in-depth for AUDIT-02           | Unverified, explicitly deferred (D-04)                 | —                                                 | Code-layer-only enforcement for Phase 1 (see D-04)                                                     |

**Missing dependencies with no fallback:** none.
**Missing dependencies with fallback:** Laravel Cloud MySQL privilege model — explicitly deferred, not blocking.

## Validation Architecture

### Test Framework

| Property           | Value                                                                                                                                       |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Framework          | Pest 5.1.3 + pest-plugin-laravel 5.0.1 [VERIFIED: composer show --direct]                                                                   |
| Config file        | `phpunit.xml` (suite config) + `tests/Pest.php` (Pest binding — **currently has `RefreshDatabase` commented out, must be fixed in Wave 0**) |
| Quick run command  | `php artisan test --compact --filter={TestName}` or `vendor/bin/pest --filter={name}`                                                       |
| Full suite command | `php artisan test --compact`                                                                                                                |

### Phase Requirements → Test Map

| Req ID    | Behavior                                                      | Test Type | Automated Command                                                                                                                                                                                            | File Exists? |
| --------- | ------------------------------------------------------------- | --------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------ |
| RBAC-01   | Login redirects each role to its own portal                   | feature   | `php artisan test --filter=redirects_owner_to_owner_dashboard`                                                                                                                                               | ❌ Wave 0    |
| RBAC-02   | Cross-role direct URL access returns 403                      | feature   | `php artisan test --filter=blocks_wrong_role_with_403`                                                                                                                                                       | ❌ Wave 0    |
| RBAC-03   | 5 failed attempts locks account for configured duration       | feature   | `php artisan test --filter=locks_account_after_five_failed_attempts`                                                                                                                                         | ❌ Wave 0    |
| RBAC-04   | New login invalidates prior session                           | feature   | `php artisan test --filter=new_login_invalidates_previous_session`                                                                                                                                           | ❌ Wave 0    |
| RBAC-05   | Idle session times out with message                           | feature   | `php artisan test --filter=idle_session_times_out`                                                                                                                                                           | ❌ Wave 0    |
| RBAC-06   | Weak password rejected in every environment                   | feature   | `php artisan test --filter=password_complexity_enforced_outside_production`                                                                                                                                  | ❌ Wave 0    |
| RBAC-07   | Deactivated user cannot log in, never hard-deleted            | feature   | `php artisan test --filter=deactivated_user_cannot_login`                                                                                                                                                    | ❌ Wave 0    |
| RBAC-08   | Login/logout/failed/lockout write audit rows                  | feature   | `php artisan test --filter=auth_events_write_audit_trail`                                                                                                                                                    | ❌ Wave 0    |
| AUDIT-01  | Owner/Admin can view + filter audit trail by user/action/date | feature   | `php artisan test --filter=owner_can_filter_audit_trail`                                                                                                                                                     | ❌ Wave 0    |
| AUDIT-02  | No update/delete code path for audit entries                  | arch      | `php artisan test --filter=arch_audit_log_has_no_mutation_methods` (Pest `arch()` test asserting `AuditLog` is never referenced with `::update(`/`::destroy(` app-wide, or asserting no route exists for it) | ❌ Wave 0    |
| CONFIG-01 | Owner/Admin can update a business rule and it takes effect    | feature   | `php artisan test --filter=owner_can_update_system_configuration`                                                                                                                                            | ❌ Wave 0    |

### Sampling Rate

- **Per task commit:** `php artisan test --compact --filter={touched test}`
- **Per wave merge:** `php artisan test --compact` (full suite)
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps

- [ ] `tests/Pest.php` — uncomment `->use(RefreshDatabase::class)` (blocking, not optional — every other Wave 0 item depends on this)
- [ ] `tests/Feature/Auth/AccountLockoutTest.php` — covers RBAC-03, RBAC-08
- [ ] `tests/Feature/Auth/SingleSessionTest.php` — covers RBAC-04
- [ ] `tests/Feature/Auth/IdleTimeoutTest.php` — covers RBAC-05
- [ ] `tests/Feature/RoleBoundaryTest.php` — covers RBAC-01, RBAC-02 (parametrized across all 7 roles × a route outside their portal)
- [ ] `tests/Feature/AuditTrailTest.php` — covers AUDIT-01, D-03 (old/new value shape)
- [ ] `tests/Unit/Arch/AuditLogArchTest.php` — covers AUDIT-02 structurally
- [ ] `tests/Feature/SystemConfigurationTest.php` — covers CONFIG-01
- [ ] `database/factories/UserFactory.php` — needs a `role` state per enum case (e.g. `UserFactory::new()->owner()`, `->artist()`, etc.) for all the above tests to construct role-specific users

## Security Domain

### Applicable ASVS Categories

| ASVS Category                 | Applies | Standard Control                                                                                                                                |
| ----------------------------- | ------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| V2 Authentication             | yes     | Fortify + `Password::defaults()` (fixed per Pitfall 1) + account lockout (Pattern 2)                                                            |
| V3 Session Management         | yes     | Single-session enforcement (Pattern 3), idle timeout (Pattern 4), Fortify's existing session-ID regeneration on login                           |
| V4 Access Control             | yes     | `role:` middleware (Pattern 1 code example), enforced server-side on every route, never nav-only                                                |
| V5 Input Validation           | yes     | Form Requests + `Concerns` validation traits (existing project pattern) for User Management and System Configuration forms                      |
| V6 Cryptography               | yes     | Laravel's `Hash` facade / `password` cast to `hashed` (already in `User::casts()`) — never hand-roll password hashing                           |
| V7 Error Handling and Logging | yes     | Append-only `audit_trail` (AUDIT-01/02) is this phase's core V7 control — every auth event and mutation is logged with actor, IP, and timestamp |

### Known Threat Patterns for this stack

| Pattern                                                                                          | STRIDE                 | Standard Mitigation                                                                                                                                                                                                                                                                                                              |
| ------------------------------------------------------------------------------------------------ | ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Credential stuffing / brute force                                                                | Spoofing               | Existing IP+email `RateLimiter` (5/min) **plus** new account-level lockout (Pattern 2) — defense in depth, two independent triggers                                                                                                                                                                                              |
| Session fixation                                                                                 | Spoofing/Tampering     | Fortify already regenerates session ID on login [VERIFIED: `laravel.com/docs/13.x/session#regenerating-the-session-id`] — do not disable this in any custom pipeline step                                                                                                                                                        |
| Privilege escalation via direct URL                                                              | Elevation of Privilege | Server-side `role:` middleware on every portal route group (RBAC-02) — never rely on hidden nav                                                                                                                                                                                                                                  |
| Concurrent session hijacking                                                                     | Spoofing               | Single-active-session enforcement (Pattern 3) — a stolen session cookie used from a second device is invalidated on the legitimate user's next login (not before, but this is the honest limit of a lockout-on-next-login model; note it as a residual risk, not a full mitigation of already-stolen-and-actively-used sessions) |
| Audit log tampering / repudiation                                                                | Repudiation            | Structural append-only enforcement (D-04) — no `update()`/`delete()` code path exists for `AuditLog` anywhere; every row captures actor `user_id` + IP so an action cannot be plausibly denied                                                                                                                                   |
| Account lockout as a DoS vector (attacker locks out a known Owner account by repeatedly failing) | Denial of Service      | Accepted risk for Phase 1 at this shop's scale (small user base, Owner/Admin can always be unlocked by another Admin/Owner manually via User Management — confirm this "manual unlock" affordance is included in planning, since RBAC-03 only specifies lockout _duration_, not an admin override path)                          |

## Sources

### Primary (HIGH confidence)

- `laravel.com/docs/13.x/authorization` — Gate::before, policy filters, `can:` middleware, `Authorize` attribute, Inertia authorization sharing pattern
- `laravel.com/docs/13.x/session` — session drivers, `lifetime`/`expire_on_close`, `invalidate()`/`regenerate()`, session table shape
- `laravel.com/docs/13.x/fortify` — exact default `authenticateThrough()` pipeline order, rate-limiter customization
- Local vendor source read directly: `vendor/laravel/framework/src/Illuminate/Validation/Rules/Password.php` (13.29.0) — confirmed `Password::default()` null-fallback behavior
- `composer show --direct` output (2026-08-31) — installed package versions
- Local repo files read directly: `config/fortify.php`, `config/session.php`, `app/Providers/FortifyServiceProvider.php`, `app/Providers/AppServiceProvider.php`, `app/Models/User.php`, `database/migrations/0001_01_01_000000_create_users_table.php`, `tests/Pest.php`

### Secondary (MEDIUM confidence)

- `github.com/laravel/fortify/issues/145` and `github.com/laravel/fortify/pull/154` — `Failed` event pipeline-ordering issue (confirmed not applicable to this app's current `config/fortify.php`, but noted as an anti-pattern if 2FA is ever enabled)
- `github.com/GrytsenkoAndrey/ed-laravel-attributes-list` / `laravel-news.com/laravel-10-44-0` — `#[ObservedBy]` attribute introduction and usage
- `freek.dev/1828-store-strongly-typed-settings-in-a-laravel-app`, `github.com/spatie/laravel-settings` — key-value vs. typed-column settings tradeoff informing Pattern 6

### Tertiary (LOW confidence)

- `jesusamieiro.com/limit-one-session-per-user-in-laravel-5` and `github.com/protonemedia/laravel-single-session` — general single-session pattern shape (informed the _rejected_ naive approach discussed in Pattern 3, cross-checked against session driver internals rather than taken at face value)
- Community Inertia + Laravel 11+/12+ `$exceptions->respond()` 403-page pattern (A2 in Assumptions Log) — not verified against this specific installation in this session

## Metadata

**Confidence breakdown:**

- Standard stack: HIGH — no new packages, all versions read directly from `composer show --direct`
- Architecture: HIGH for Fortify pipeline/event mechanics (verified against vendor source + official docs); MEDIUM for the exact 403-exception-render snippet (A2, unverified against this installation)
- Pitfalls: HIGH for Pitfall 1 and 2 (both read directly from source/config in this repo); MEDIUM for Pitfall 3 (training-knowledge description of `Failed` event `$event->user`, flagged as A1)

**Research date:** 2026-08-31
**Valid until:** 30 days (stable framework versions; re-verify if `laravel/framework` or `laravel/fortify` receive a minor version bump before planning executes)
