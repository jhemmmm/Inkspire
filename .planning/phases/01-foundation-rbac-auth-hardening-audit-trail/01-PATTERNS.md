# Phase 1: Foundation — RBAC, Auth Hardening & Audit Trail - Pattern Map

**Mapped:** 2026-08-31
**Files analyzed:** 34 (backend: 20, frontend: 14)
**Analogs found:** 30 / 34

## File Classification

| New/Modified File                                                                                                    | Role                         | Data Flow                                   | Closest Analog                                                                                                                        | Match Quality                                                               |
| -------------------------------------------------------------------------------------------------------------------- | ---------------------------- | ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------- |
| `app/Enums/UserRole.php`                                                                                             | model (enum)                 | transform                                   | none (no enum exists yet)                                                                                                             | no-analog                                                                   |
| `app/Models/User.php` (modify: add role/is_active/lockout/session cols to casts + `#[ObservedBy]`)                   | model                        | CRUD                                        | itself (existing file)                                                                                                                | exact                                                                       |
| `app/Models/AuditLog.php`                                                                                            | model                        | event-driven                                | `app/Models/User.php` (attribute-based metadata style only; no CRUD-model analog exists)                                              | role-match                                                                  |
| `app/Models/SystemConfiguration.php`                                                                                 | model                        | CRUD (cached read)                          | `app/Models/User.php` (casts() convention)                                                                                            | role-match                                                                  |
| `app/Observers/AuditObserver.php`                                                                                    | service (observer)           | event-driven                                | none (no observer exists)                                                                                                             | no-analog                                                                   |
| `app/Support/AuditLogger.php`                                                                                        | service (helper/facade-free) | event-driven                                | none (no `app/Support/` dir yet)                                                                                                      | no-analog                                                                   |
| `app/Actions/Fortify/EnsureAccountIsNotLocked.php`                                                                   | middleware (pipeline step)   | request-response                            | `app/Actions/Fortify/ResetUserPassword.php`                                                                                           | exact (same dir/contract shape, both single-purpose Fortify action classes) |
| `app/Listeners/Auth/RecordFailedLoginAttempt.php`                                                                    | service (event listener)     | event-driven                                | none (no `Listeners/` dir yet) — closest behavioral analog is `FortifyServiceProvider::configureRateLimiting()`'s counter-style logic | no-analog                                                                   |
| `app/Listeners/Auth/HandleSuccessfulLogin.php`                                                                       | service (event listener)     | event-driven                                | none                                                                                                                                  | no-analog                                                                   |
| `app/Listeners/Auth/HandleLogout.php`                                                                                | service (event listener)     | event-driven                                | none                                                                                                                                  | no-analog                                                                   |
| `app/Listeners/Auth/RecordLockoutEvent.php`                                                                          | service (event listener)     | event-driven                                | none                                                                                                                                  | no-analog                                                                   |
| `app/Http/Middleware/EnsureUserHasRole.php`                                                                          | middleware                   | request-response                            | `app/Http/Middleware/HandleAppearance.php` (simplest middleware shape)                                                                | role-match                                                                  |
| `app/Http/Middleware/VerifySingleSession.php`                                                                        | middleware                   | request-response                            | `app/Http/Middleware/HandleAppearance.php`                                                                                            | role-match                                                                  |
| `app/Http/Middleware/EnforceIdleSessionTimeout.php`                                                                  | middleware                   | request-response                            | `app/Http/Middleware/HandleAppearance.php`                                                                                            | role-match                                                                  |
| `app/Http/Controllers/Owner/AuditTrailController.php`                                                                | controller                   | request-response (read-only, filtered list) | `app/Http/Controllers/Settings/ProfileController.php` (`edit` method: `Inertia::render()` with props)                                 | role-match                                                                  |
| `app/Http/Controllers/Owner/UserManagementController.php`                                                            | controller                   | CRUD                                        | `app/Http/Controllers/Settings/ProfileController.php` (full CRUD shape: edit/update/destroy)                                          | exact                                                                       |
| `app/Http/Controllers/Owner/SystemConfigurationController.php`                                                       | controller                   | CRUD                                        | `app/Http/Controllers/Settings/SecurityController.php` (edit + update, `Inertia::flash('toast', ...)`)                                | exact                                                                       |
| `app/Http/Requests/Owner/DeactivateUserRequest.php`                                                                  | request (FormRequest)        | request-response                            | `app/Http/Requests/Settings/ProfileDeleteRequest.php`                                                                                 | exact                                                                       |
| `app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php`                                                       | request (FormRequest)        | request-response                            | `app/Http/Requests/Settings/PasswordUpdateRequest.php` (trait-delegated `rules()`)                                                    | exact                                                                       |
| `app/Concerns/SystemConfigValidationRules.php`                                                                       | utility (validation trait)   | transform                                   | `app/Concerns/ProfileValidationRules.php` / `app/Concerns/PasswordValidationRules.php`                                                | exact                                                                       |
| `app/Providers/AppServiceProvider.php` (modify: remove `isProduction()` gate on `Password::defaults()`)              | config                       | transform                                   | itself (existing file)                                                                                                                | exact                                                                       |
| `app/Providers/FortifyServiceProvider.php` (modify: insert `EnsureAccountIsNotLocked` into pipeline)                 | config                       | request-response                            | itself (existing file)                                                                                                                | exact                                                                       |
| `bootstrap/app.php` (modify: register middleware aliases, Inertia 403 exception render)                              | config                       | request-response                            | itself (existing file)                                                                                                                | exact                                                                       |
| `database/migrations/..._add_rbac_and_lockout_columns_to_users_table.php`                                            | migration                    | CRUD (schema)                               | `database/migrations/0001_01_01_000000_create_users_table.php`                                                                        | role-match                                                                  |
| `database/migrations/..._create_audit_trail_table.php`                                                               | migration                    | CRUD (schema)                               | `database/migrations/0001_01_01_000000_create_users_table.php`                                                                        | role-match                                                                  |
| `database/migrations/..._create_system_configurations_table.php`                                                     | migration                    | CRUD (schema)                               | `database/migrations/0001_01_01_000000_create_users_table.php`                                                                        | role-match                                                                  |
| `database/factories/UserFactory.php` (modify: add per-role states)                                                   | test (factory)               | transform                                   | itself (existing file — `unverified()` state is the pattern to copy)                                                                  | exact                                                                       |
| `database/seeders/SystemConfigurationSeeder.php`                                                                     | migration (seeder)           | batch                                       | none (no seeders exist yet)                                                                                                           | no-analog                                                                   |
| `resources/js/pages/owner/AuditTrail.vue`                                                                            | component (page)             | request-response (filtered read-only list)  | `resources/js/pages/settings/Security.vue` (page-level `<Form>`/props shape, minus the form since this is read-only)                  | role-match                                                                  |
| `resources/js/pages/owner/UserManagement.vue`                                                                        | component (page)             | CRUD                                        | `resources/js/pages/settings/Profile.vue` + `resources/js/components/DeleteUser.vue` (destructive confirm dialog)                     | exact                                                                       |
| `resources/js/pages/owner/SystemConfiguration.vue`                                                                   | component (page)             | CRUD                                        | `resources/js/pages/settings/Security.vue` (Form + toast pattern)                                                                     | exact                                                                       |
| `resources/js/pages/{role}/Dashboard.vue` (×5: frontline-staff, artist, cashier, production-staff, accounting-staff) | component (page)             | request-response                            | `resources/js/pages/Dashboard.vue`                                                                                                    | exact                                                                       |
| `resources/js/pages/errors/Forbidden.vue`                                                                            | component (page)             | request-response                            | `resources/js/pages/auth/ForgotPassword.vue` (centered `AuthLayout`-style single-message page)                                        | role-match                                                                  |
| `resources/js/layouts/OwnerLayout.vue` (or reuse `AppSidebarLayout` with owner-specific `AppSidebar` variant)        | component (layout)           | request-response                            | `resources/js/layouts/app/AppSidebarLayout.vue`                                                                                       | exact                                                                       |
| `resources/js/components/OwnerSidebar.vue` (role-specific nav array, one per portal or parametrized)                 | component                    | request-response                            | `resources/js/components/AppSidebar.vue`                                                                                              | exact                                                                       |
| `resources/js/pages/auth/Login.vue` (modify: add locked/deactivated `Alert`)                                         | component (page)             | request-response                            | itself (existing file) — `AlertError.vue` for the destructive Alert                                                                   | exact                                                                       |

## Pattern Assignments

### `app/Actions/Fortify/EnsureAccountIsNotLocked.php` (middleware/pipeline-step, request-response)

**Analog:** `app/Actions/Fortify/ResetUserPassword.php`

**Imports pattern** (lines 1-8):

```php
namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
```

For the new action, mirror this shape but implement `__invoke(Request $request, callable $next)` (Fortify pipeline convention, not a `Contracts\*` interface) since this is a pipeline step, not a `resetUserPasswordsUsing`-style override. Use `Laravel\Fortify\Fortify::username()` to resolve the login field, matching `FortifyServiceProvider::configureRateLimiting()`'s use of `Fortify::username()`:

```php
// app/Providers/FortifyServiceProvider.php:74
$throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
```

**Core pattern** — single-purpose class, one public method, no controller boilerplate, throws `ValidationException` rather than returning a response (matches Fortify's own `EnsureLoginIsNotThrottled` convention referenced in RESEARCH.md Pattern 1).

**Error handling pattern:** Use `ValidationException::withMessages(['email' => [...]])` (the same field-level error convention Fortify's login form already expects via `errors.email`/`InputError`), not a flash/redirect — this keeps parity with how `Login.vue` already renders `errors.email` (see `resources/js/pages/auth/Login.vue:56`).

---

### `app/Http/Middleware/EnsureUserHasRole.php`, `VerifySingleSession.php`, `EnforceIdleSessionTimeout.php` (middleware, request-response)

**Analog:** `app/Http/Middleware/HandleAppearance.php`

**Full file for reference** (lines 1-23):

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->cookie('appearance') ?? 'system');

        return $next($request);
    }
}
```

**Core pattern:** invokable-style `handle(Request $request, Closure $next): Response` method, one-line PHPDoc, curly braces always, explicit return type. `EnsureUserHasRole` additionally needs variadic `string ...$roles` params (per RESEARCH.md's Code Examples section — copy that exact snippet, it is already codebase-convention-consistent: `abort_if(...)`, no `use` of a `Rule`/`Gate` facade needed for this simple case).

**Registration pattern** — mirror how `HandleAppearance` is registered globally in `bootstrap/app.php`:

```php
// bootstrap/app.php:19-23
$middleware->web(append: [
    HandleAppearance::class,
    HandleInertiaRequests::class,
    AddLinkHeadersForPreloadedAssets::class,
]);
```

New middleware that must run on _every_ authenticated request (`VerifySingleSession`, `EnforceIdleSessionTimeout`) should be registered similarly via `$middleware->web(append: [...])` guarded by an `auth`-check inside `handle()` (they must no-op for guests), while `EnsureUserHasRole` is registered as a named alias (per RESEARCH.md Code Examples):

```php
$middleware->alias([
    'role' => \App\Http\Middleware\EnsureUserHasRole::class,
]);
```

---

### `app/Http/Controllers/Owner/UserManagementController.php` (controller, CRUD)

**Analog:** `app/Http/Controllers/Settings/ProfileController.php`

**Imports pattern** (lines 1-13):

```php
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
```

New controller under `app/Http/Controllers/Owner/` should follow this same import ordering: framework classes first, then app classes (Requests, then Controller base).

**Core CRUD pattern — index/list with Inertia render** (adapt from `edit`, lines 20-26):

```php
public function edit(Request $request): Response
{
    return Inertia::render('settings/Profile', [
        'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
        'status' => $request->session()->get('status'),
    ]);
}
```

`UserManagementController::index()` should follow this exact shape: `Inertia::render('owner/UserManagement', [...])` with filter/pagination props.

**Destructive-action pattern — "deactivate" instead of "destroy"** (adapt from `destroy`, lines 49-61):

```php
public function destroy(ProfileDeleteRequest $request): RedirectResponse
{
    $user = $request->user();

    Auth::logout();

    $user->delete();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
}
```

Adapt to a `deactivate(DeactivateUserRequest $request, User $user): RedirectResponse` that does `$user->update(['is_active' => false])` (never `->delete()`, per PROJECT.md's "never hard-delete" constraint) then `Inertia::flash('toast', [...])` + `back()`, matching `SecurityController::update()`'s flash convention below rather than `ProfileController::destroy()`'s logout/redirect convention (this is an admin acting on another user's row, not self-deletion).

**Success feedback pattern** (from `SecurityController::update`, lines 30-39):

```php
public function update(PasswordUpdateRequest $request): RedirectResponse
{
    $request->user()->update([
        'password' => $request->password,
    ]);

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

    return back();
}
```

---

### `app/Http/Controllers/Owner/SystemConfigurationController.php` (controller, CRUD)

**Analog:** `app/Http/Controllers/Settings/SecurityController.php` (full file, lines 1-40, shown above under UserManagementController — reuse both `edit()`'s prop-passing and `update()`'s flash+`Cache::forget()` pattern per RESEARCH.md Pitfall 5).

---

### `app/Http/Requests/Owner/DeactivateUserRequest.php` and `UpdateSystemConfigurationRequest.php` (request, request-response)

**Analog:** `app/Http/Requests/Settings/PasswordUpdateRequest.php`

**Full pattern** (lines 1-25):

```php
namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }
}
```

Every new FormRequest must: `use` a `Concerns\*ValidationRules` trait, define `rules(): array` with the array-shape PHPDoc, and (per project convention — no `authorize()` override observed in any existing request, meaning they all implicitly return `true` from `FormRequest`'s default) only add an explicit `authorize(): bool` override where role-based authorization is actually needed (e.g. `DeactivateUserRequest` should check the acting user is Owner/Admin and enforce Open Question 2's narrow rule: Owner-only may deactivate Owner/Admin).

---

### `app/Concerns/SystemConfigValidationRules.php` (utility, transform)

**Analog:** `app/Concerns/ProfileValidationRules.php` (full file, lines 1-52, shown above in Read output)

**Core pattern:** trait with one `{subject}Rules()` method per validated field, all methods `protected`, all return `array<int, ValidationRule|array<mixed>|string>` with array-shape PHPDoc. `SystemConfigValidationRules::valueRules(string $type)` should switch on the config's `type` column value (`integer`/`decimal`/`boolean`/`string`/`array`) to build the right rule array, following the same `Rule::unique(...)->ignore(...)` conditional-building style seen in `emailRules()`:

```php
// app/Concerns/ProfileValidationRules.php:39-50
protected function emailRules(?int $userId = null): array
{
    return [
        'required',
        'string',
        'email',
        'max:255',
        $userId === null
            ? Rule::unique(User::class)
            : Rule::unique(User::class)->ignore($userId),
    ];
}
```

---

### `app/Models/User.php` (modify), `AuditLog.php`, `SystemConfiguration.php` (model, CRUD)

**Analog:** `app/Models/User.php` (existing, full file above)

**Attribute-based metadata pattern** (lines 27-45):

```php
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

Apply the exact same `#[Fillable]`/`#[Hidden]` + `casts(): array` style (not legacy `$fillable`/`$casts` properties) to `User`'s modification and to `AuditLog`/`SystemConfiguration`. Add `#[ObservedBy(AuditObserver::class)]` to `User` per RESEARCH.md Pattern 5's exact code example (only if `User` mutations should be audited — confirm scope during planning). `@property` PHPDoc block (lines 15-26) must be extended with the new columns (`role`, `is_active`, `failed_login_attempts`, `locked_until`, `current_session_id`, `last_activity_at`).

**`AuditLog` — no analog exists for the "no mutation methods" append-only shape**; RESEARCH.md's Pattern 5 code example is the primary source, not a codebase analog:

```php
class AuditLog extends Model
{
    protected $table = 'audit_trail';
    public $timestamps = false;
    protected $casts = ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
}
```

Note this uses the legacy `protected $casts` property, not `casts(): array` — flag during planning whether to use the Laravel-11+ method style (matching `User`'s convention) instead, since CLAUDE.md's casts-method convention should take priority over the RESEARCH.md snippet's older style.

---

### `app/Providers/FortifyServiceProvider.php` (modify, config)

**Analog:** itself — existing structure to extend, not replace (full file above).

**Insertion point:** add a new `configureAuthenticationPipeline(): void` private method (mirroring the existing `configureActions()`/`configureViews()`/`configureRateLimiting()` one-method-per-concern style) called from `boot()`:

```php
public function boot(): void
{
    $this->configureActions();
    $this->configureViews();
    $this->configureRateLimiting();
    $this->configureAuthenticationPipeline(); // NEW
}
```

Body follows RESEARCH.md Pattern 1's `Fortify::authenticateThrough(...)` snippet verbatim.

---

### `app/Providers/AppServiceProvider.php` (modify, config)

**Analog:** itself (full file above).

**Fix required (Pitfall 1):** in `configureDefaults()`, remove the `app()->isProduction() ? ... : null` ternary around `Password::defaults()` so complexity rules apply in every environment:

```php
// BEFORE (app/Providers/AppServiceProvider.php:40-48)
Password::defaults(fn (): ?Password => app()->isProduction()
    ? Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()
    : null,
);
```

Keep the same fluent builder chain and `Password::min(...)` value; only remove the environment gate (min length may become `SystemConfiguration`-driven per CONFIG-01, decide during planning — RESEARCH.md flags this as acceptable future work, not a Phase 1 blocker).

---

## Shared Patterns

### Success feedback (`Inertia::flash('toast', ...)`)

**Source:** `app/Http/Controllers/Settings/ProfileController.php:41`, `app/Http/Controllers/Settings/SecurityController.php:36`
**Apply to:** `UserManagementController` (deactivate/reactivate), `SystemConfigurationController` (update)

```php
Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);
```

### Form Request + Validation Concern trait pair

**Source:** `app/Http/Requests/Settings/PasswordUpdateRequest.php` + `app/Concerns/PasswordValidationRules.php`
**Apply to:** `DeactivateUserRequest` + (new authorization check, no trait needed if it's a single boolean policy check), `UpdateSystemConfigurationRequest` + `SystemConfigValidationRules`

### Attribute-based Eloquent metadata (`#[Fillable]`, `#[Hidden]`, `#[ObservedBy]`, `casts(): array`)

**Source:** `app/Models/User.php:27-45`
**Apply to:** `User` (modify), `AuditLog`, `SystemConfiguration` — every new/modified model in this phase must use PHP 8 attributes and the `casts()` method, never legacy `protected $fillable`/`protected $casts` properties.

### Inertia page component structure (`defineOptions({ layout: {...} })`, `<Form v-bind="Controller.method.form()">`)

**Source:** `resources/js/pages/settings/Security.vue` (full file above)
**Apply to:** `owner/SystemConfiguration.vue`, `owner/UserManagement.vue` — use generated Wayfinder controller imports (e.g. `import UserManagementController from '@/actions/App/Http/Controllers/Owner/UserManagementController'`), never hardcoded URLs.

### Destructive confirmation dialog (`alert-dialog` semantics per UI-SPEC, existing `Dialog` pattern to adapt)

**Source:** `resources/js/components/DeleteUser.vue` (full file above)
**Apply to:** `owner/UserManagement.vue`'s "Deactivate Account" action — same `<Form>` + `DialogHeader`/`DialogFooter` structure, but per UI-SPEC swap the base `Dialog` primitives for the new `alert-dialog` shadcn component (destructive/irreversible-action semantics), and use the UI-SPEC's exact copy: _"Deactivate {user_name}'s account? They will immediately lose the ability to log in. This does not delete their data and can be reversed by reactivating the account."_

### Destructive/Alert-variant error state (account locked, session invalidated)

**Source:** `resources/js/components/AlertError.vue` (full file above)
**Apply to:** `resources/js/pages/auth/Login.vue` — add an `Alert` (destructive variant) above the `<Form>` for the "account locked" / "account deactivated" states, distinct from the existing per-field `InputError` usage (lines 56, 79 of `Login.vue`), per UI-SPEC's explicit "use an Alert (destructive variant) above the form, not just an inline InputError."

### Sidebar/portal layout shell reuse

**Source:** `resources/js/layouts/app/AppSidebarLayout.vue` + `resources/js/components/AppSidebar.vue` (full files above)
**Apply to:** every new `resources/js/pages/{role}/*.vue` page — reuse `AppShell`/`AppContent`/`AppSidebarHeader`/`Toaster` composition from `AppSidebarLayout.vue`, but build a **new sidebar component per role** (or one parametrized sidebar fed a role-specific `NavItem[]` array) rather than reusing the single hardcoded `mainNavItems` array in the current `AppSidebar.vue` — per UI-SPEC's locked "dedicated portal, not shared-layout-with-filtered-nav" decision. `app.ts`'s `layout: (name) => {...}` switch (lines 12-22) must be extended with a case per `resources/js/pages/{role}/` prefix, following the existing `name.startsWith('settings/')` pattern.

### Route middleware group + named routes

**Source:** `routes/settings.php` (full file above), `routes/web.php`
**Apply to:** new `routes/owner.php` (or an `owner.` group appended to `routes/web.php`) — `Route::middleware(['auth', 'role:owner,admin'])->prefix('owner')->name('owner.')->group(...)` per RESEARCH.md's Code Examples section; every portal namespace needs the analogous group with `role:{roles}`.

### Feature test structure (Pest, `actingAs`, `assertInertia`, `assertSessionHasErrors`)

**Source:** `tests/Feature/Settings/SecurityTest.php`, `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/DashboardTest.php` (full files above)
**Apply to:** all new Wave 0 test files listed in RESEARCH.md's Validation Architecture section (`AccountLockoutTest.php`, `SingleSessionTest.php`, `IdleTimeoutTest.php`, `RoleBoundaryTest.php`, `AuditTrailTest.php`, `SystemConfigurationTest.php`). Copy the `test('description', function () { ... })` closure style, `User::factory()->create()` / `actingAs($user)`, and `assertInertia(fn (Assert $page) => $page->component(...)->where(...))` shape verbatim.
**Blocking prerequisite:** `tests/Pest.php:18` — uncomment `->use(RefreshDatabase::class)` before writing any of the above (Pitfall 2).

### Migration structure (anonymous class, `up()`/`down()`)

**Source:** `database/migrations/0001_01_01_000000_create_users_table.php` (full file above)
**Apply to:** all three new migrations — same `return new class extends Migration { public function up(): void {...} public function down(): void {...} }` anonymous-class shape, `Schema::create`/`Schema::table` + `Schema::dropIfExists` pairing.

### Factory state pattern (`unverified()`)

**Source:** `database/factories/UserFactory.php:36-41`

```php
public function unverified(): static
{
    return $this->state(fn (array $attributes) => [
        'email_verified_at' => null,
    ]);
}
```

**Apply to:** new per-role factory states, e.g. `owner()`, `admin()`, `artist()`, etc. — one `->state(fn (array $attributes) => ['role' => UserRole::X])` method per role, plus a `locked()` state (`failed_login_attempts`, `locked_until`) and `deactivated()` state (`is_active => false`) for the Wave 0 tests to construct fixtures directly.

## No Analog Found

Files with no close match in the codebase (planner should use RESEARCH.md patterns instead):

| File                                             | Role                     | Data Flow    | Reason                                                                                                                                                                               |
| ------------------------------------------------ | ------------------------ | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `app/Enums/UserRole.php`                         | model (enum)             | transform    | No PHP enum exists anywhere in `app/` yet; use RESEARCH.md's "Role enum" Code Example verbatim (TitleCase keys per CLAUDE.md convention)                                             |
| `app/Observers/AuditObserver.php`                | service (observer)       | event-driven | No Eloquent observer exists; use RESEARCH.md Pattern 5's full code example                                                                                                           |
| `app/Support/AuditLogger.php`                    | service (helper)         | event-driven | No `app/Support/` directory exists yet; new base directory (flag per CLAUDE.md "don't create new base folders without approval" — confirm with user/planner before creating)         |
| `app/Listeners/Auth/*.php` (4 files)             | service (event listener) | event-driven | No `app/Listeners/` directory exists yet (also a new base folder — same approval flag as above); use RESEARCH.md Patterns 2/3 code examples                                          |
| `database/seeders/SystemConfigurationSeeder.php` | migration (seeder)       | batch        | No seeders exist in `database/seeders/` beyond the framework default `DatabaseSeeder.php` (not read in this pass — check it during planning for the `run()` registration convention) |

## Metadata

**Analog search scope:** `app/`, `routes/`, `database/migrations/`, `database/factories/`, `resources/js/pages/`, `resources/js/layouts/`, `resources/js/components/`, `tests/Feature/`
**Files scanned:** ~45 (all existing `app/*.php`, all existing Vue pages/layouts/components referenced in CONTEXT.md/RESEARCH.md, all existing migrations, `tests/Pest.php`, 3 feature test files, `UserFactory.php`, `bootstrap/app.php`, `routes/web.php` + `routes/settings.php`)
**Pattern extraction date:** 2026-08-31
