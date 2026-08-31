<!-- refreshed: 2026-08-31 -->
# Architecture

**Analysis Date:** 2026-08-31

## System Overview

```text
┌─────────────────────────────────────────────────────────────┐
│                    Browser (Vue 3 SPA)                       │
│  Pages: `resources/js/pages/**`                              │
│  Layouts: `resources/js/layouts/**`                          │
│  UI kit: `resources/js/components/ui/**` (reka-ui/shadcn)    │
└──────────────────┬────────────────────────────────────────────┘
                    │ Inertia visits (XHR, no separate REST API)
                    ▼
┌─────────────────────────────────────────────────────────────┐
│              Inertia root view + middleware                  │
│  `resources/views/app.blade.php`                              │
│  `app/Http/Middleware/HandleInertiaRequests.php`               │
│  `app/Http/Middleware/HandleAppearance.php`                    │
└──────────────────┬────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────┐
│                  Routing (`routes/*.php`)                     │
│  `routes/web.php` (home, dashboard)                            │
│  `routes/settings.php` (profile, security, appearance)         │
│  Fortify auth routes (registered by laravel/fortify package)   │
└──────────────────┬────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────┐
│         Controllers / Fortify Actions / Form Requests         │
│  `app/Http/Controllers/Settings/*Controller.php`               │
│  `app/Http/Requests/Settings/*Request.php`                     │
│  `app/Actions/Fortify/*.php`                                   │
│  `app/Concerns/*ValidationRules.php` (shared validation traits)│
└──────────────────┬────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────────┐
│                Eloquent Models / Database                     │
│  `app/Models/User.php`                                         │
│  `database/migrations/**`, SQLite by default                  │
└─────────────────────────────────────────────────────────────┘
```

## Component Responsibilities

| Component | Responsibility | File |
|-----------|----------------|------|
| Inertia SPA bootstrap | Registers layout-resolution rules and app-wide plugins (theme, flash toasts) | `resources/js/app.ts` |
| Root Blade template | Single HTML shell Inertia hydrates into; sets appearance class from cookie | `resources/views/app.blade.php` |
| `HandleInertiaRequests` | Shares global props (`auth.user`, `name`, `sidebarOpen`) with every Inertia response | `app/Http/Middleware/HandleInertiaRequests.php` |
| `HandleAppearance` | Shares light/dark/system cookie value with Blade views | `app/Http/Middleware/HandleAppearance.php` |
| Web routes | Public/dashboard pages rendered directly via `Route::inertia()` | `routes/web.php` |
| Settings routes | CRUD-style routes for profile/security backed by controllers | `routes/settings.php` |
| Settings controllers | Handle profile update/delete and password update, return Inertia responses | `app/Http/Controllers/Settings/ProfileController.php`, `app/Http/Controllers/Settings/SecurityController.php` |
| Fortify service provider | Wires custom Inertia views for Fortify's built-in auth routes (login, reset password, confirm password) and login rate limiting | `app/Providers/FortifyServiceProvider.php` |
| Form Requests | Authorize + validate settings input, delegate rule sets to `Concerns` traits | `app/Http/Requests/Settings/*.php` |
| Validation concerns | Reusable validation rule builders shared across requests | `app/Concerns/ProfileValidationRules.php`, `app/Concerns/PasswordValidationRules.php` |
| `User` model | Sole domain model; auth, factory, casts, fillable/hidden via PHP attributes | `app/Models/User.php` |
| Wayfinder-generated actions/routes | Type-safe TS wrappers around Laravel routes/controllers, regenerated from PHP | `resources/js/actions/**`, `resources/js/routes/**` |
| UI component library | Reusable shadcn/reka-ui-based primitives (button, dialog, sidebar, etc.) | `resources/js/components/ui/**` |
| Layouts | Compose page chrome (sidebar/header shell, auth split screen, settings sub-nav) around page components | `resources/js/layouts/**` |

## Pattern Overview

**Overall:** Server-driven SPA (Laravel + Inertia.js + Vue 3) — "modular monolith" with no separate REST/JSON API layer. Laravel Fortify supplies authentication backend logic; the app only customizes Fortify's rendered views and rate limits.

**Key Characteristics:**
- No traditional Blade views for pages — one root Blade template (`resources/views/app.blade.php`) hosts the Vue SPA; all page content is Vue components under `resources/js/pages/`.
- Controllers return `Inertia::render()` responses instead of JSON API payloads; there is no `app/Http/Controllers/Api` namespace.
- Authentication (login, password reset, password confirmation, 2FA scaffolding) is delegated to `laravel/fortify`; the app only supplies views and one custom action (`ResetUserPassword`).
- Frontend route/action calls use Laravel Wayfinder-generated TypeScript (`resources/js/actions/**`, `resources/js/routes/**`) instead of hand-written URL strings, keeping frontend and backend routes in sync.
- Single Eloquent model (`User`) — this is a starter-kit-scale app; no domain-specific models exist yet beyond auth/user management and settings.

## Layers

**Presentation (Vue/Inertia):**
- Purpose: Render pages, handle client-side interactivity, form submission via Inertia
- Location: `resources/js/pages/`, `resources/js/layouts/`, `resources/js/components/`
- Contains: `.vue` single-file components, composables (`resources/js/composables/`), small client-side libs (`resources/js/lib/`)
- Depends on: Wayfinder-generated actions/routes (`resources/js/actions`, `resources/js/routes`), shared types (`resources/js/types/`)
- Used by: Browser only (SSR is configured but optional — see `build:ssr` script)

**HTTP / Routing:**
- Purpose: Map URLs to controllers or direct Inertia renders
- Location: `routes/web.php`, `routes/settings.php`, `routes/console.php`
- Contains: Route definitions, route groups with `auth`/`verified`/`throttle` middleware
- Depends on: Controllers, Fortify's own route registration (not in `routes/`, registered by the package)
- Used by: Incoming HTTP requests

**Application (Controllers / Actions / Form Requests):**
- Purpose: Orchestrate request handling — validate input, mutate models, return Inertia responses/redirects
- Location: `app/Http/Controllers/`, `app/Http/Requests/`, `app/Actions/Fortify/`
- Contains: Controller classes (thin — one `edit`/`update`/`destroy` per resource), `FormRequest` subclasses for authorization+validation, Fortify action classes
- Depends on: `app/Concerns/*ValidationRules.php` traits, Eloquent models
- Used by: Routes

**Domain (Models):**
- Purpose: Persistence and domain rules
- Location: `app/Models/`
- Contains: `User.php` (only model currently) using PHP 8 attributes (`#[Fillable]`, `#[Hidden]`) instead of legacy `$fillable`/`$hidden` properties
- Depends on: `database/factories/UserFactory.php` for test data
- Used by: Controllers, Fortify actions

**Cross-cutting (Providers/Middleware):**
- Purpose: Bootstrap app-wide config, share request-scoped data, configure security defaults
- Location: `app/Providers/`, `app/Http/Middleware/`
- Contains: `AppServiceProvider` (date immutability, destructive-command guard, password policy), `FortifyServiceProvider` (custom views, rate limiting), `HandleInertiaRequests`, `HandleAppearance`
- Depends on: Framework services (`Date`, `DB`, `Password`, `RateLimiter`)
- Used by: Registered in `bootstrap/app.php` (middleware) and `bootstrap/providers.php`-style provider array (implicit via `config/app.php`/auto-discovery)

## Data Flow

### Primary Request Path (page visit)

1. Browser requests a URL; Laravel routes it via `routes/web.php` or `routes/settings.php`.
2. `HandleAppearance` and `HandleInertiaRequests` middleware run (`bootstrap/app.php:20-24`), sharing appearance cookie and `auth.user`/`name`/`sidebarOpen` props.
3. Route either calls `Route::inertia()` directly or a controller method that calls `Inertia::render('page/Name', [...])` (e.g. `app/Http/Controllers/Settings/ProfileController.php:20-26`).
4. First load: `resources/views/app.blade.php` is rendered, embedding the Inertia page JSON in a `data-page` div; Vite-built JS boots `resources/js/app.ts`.
5. `app.ts` calls `createInertiaApp`, resolves the page component from `resources/js/pages/`, and picks a layout wrapper based on the page name prefix (`app.ts:12-23`).
6. Subsequent navigations are XHR-based Inertia visits that swap the page component without a full reload.

### Form Submission Flow (e.g. profile update)

1. Vue page component submits via Inertia form helper, calling a Wayfinder-generated action (`resources/js/actions/App/Http/Controllers/Settings/ProfileController.ts`) which encodes the correct method+URL.
2. Laravel routes to `ProfileController::update` (`routes/settings.php:9`), type-hinting `ProfileUpdateRequest` which authorizes and validates using `ProfileValidationRules` (`app/Concerns/ProfileValidationRules.php`).
3. Controller mutates `$request->user()`, saves, optionally resets `email_verified_at`, and flashes a toast via `Inertia::flash('toast', [...])` (`app/Http/Controllers/Settings/ProfileController.php:41`).
4. Controller redirects with `to_route('profile.edit')`; the next Inertia response carries the flashed `toast` data.
5. Client-side, `initializeFlashToast()` listens for Inertia's `flash` event and displays it via `vue-sonner` (`resources/js/lib/flashToast.ts`).

### Authentication Flow

1. Fortify package registers its own routes (login, password reset, password confirmation) — not present in `routes/`.
2. `FortifyServiceProvider::configureViews()` overrides Fortify's default views to render this app's Vue pages (`auth/Login`, `auth/ResetPassword`, `auth/ForgotPassword`, `auth/ConfirmPassword`) instead of Blade (`app/Providers/FortifyServiceProvider.php:47-65`).
3. Fortify handles credential checking, session creation, and rate limiting (`RateLimiter::for('login', ...)`, 5/min per email+IP).
4. Password reset uses a custom action, `ResetUserPassword` (`app/Actions/Fortify/ResetUserPassword.php`), registered via `Fortify::resetUserPasswordsUsing()`.

**State Management:**
- No client-side global store (no Pinia/Vuex). State is server-driven: Inertia props per page plus small composables (`useAppearance`, `useInitials`, `useCurrentUrl`) for local/UI-only concerns.
- Theme/appearance persisted via cookie, read on both server (`HandleAppearance` → Blade) and client (`useAppearance.ts`) to avoid flash-of-wrong-theme.

## Key Abstractions

**Form Request + Validation Concern pair:**
- Purpose: Separate reusable validation rule sets from the request classes that use them, so rules can be shared across multiple requests (e.g. profile update vs. delete needing the same email rules).
- Examples: `app/Http/Requests/Settings/ProfileUpdateRequest.php` uses `app/Concerns/ProfileValidationRules.php`; `app/Http/Requests/Settings/PasswordUpdateRequest.php` likely uses `app/Concerns/PasswordValidationRules.php`.
- Pattern: `FormRequest` class `use`s a `Concerns` trait and calls a `*Rules()` method inside `rules()`.

**Wayfinder-generated route/action wrappers:**
- Purpose: Give the Vue frontend typed, refactor-safe references to Laravel routes and controller methods, generated from the PHP route table.
- Examples: `resources/js/actions/App/Http/Controllers/Settings/ProfileController.ts`, `resources/js/routes/index.ts`
- Pattern: Do not hand-edit these files — they are generated (see `.claude/skills/wayfinder-development`). Regenerate via the Wayfinder Vite plugin/Artisan command when routes/controllers change.

**Inertia page + layout resolution by naming convention:**
- Purpose: Automatically wrap pages in the correct chrome without per-page boilerplate.
- Examples: `app.ts:12-23` — pages under `auth/*` get `AuthLayout`, pages under `settings/*` get `[AppLayout, SettingsLayout]` nested layouts, `Welcome` gets no layout, everything else gets `AppLayout`.
- Pattern: New pages must be placed under the correct `resources/js/pages/<namespace>/` subfolder to inherit the intended layout automatically.

## Entry Points

**HTTP entry point:**
- Location: `public/index.php` (standard Laravel front controller, not modified)
- Triggers: All web requests
- Responsibilities: Bootstraps `bootstrap/app.php`, dispatches through the HTTP kernel/middleware stack defined there

**Frontend entry point:**
- Location: `resources/js/app.ts`
- Triggers: Loaded by `resources/views/app.blade.php` via Vite
- Responsibilities: Initializes Inertia app, layout resolution, theme, and flash-toast listener

**Console entry point:**
- Location: `artisan` (root), routes registered in `routes/console.php`
- Triggers: CLI (`php artisan ...`)
- Responsibilities: Artisan commands; only a demo `inspire` command currently defined

## Architectural Constraints

- **Threading:** Standard PHP-FPM/synchronous request model — no async workers defined beyond Laravel's default queue scaffolding (`config/queue.php`); no custom `app/Console/Commands` beyond the default directory (empty aside from framework defaults).
- **Global state:** No global mutable state in PHP beyond framework-managed singletons (service container bindings in providers). Frontend has no global store; state lives in Inertia page props and composables.
- **Circular imports:** None observed — layered structure (routes → controllers → requests/concerns → models) has no back-references.
- **Single model constraint:** Only `User` exists as an Eloquent model; any new domain concept requires creating its own model, migration, factory, and (if exposed to the frontend) controller + Inertia page — there is no existing precedent for multi-model relationships in this codebase yet.
- **No API layer:** `bootstrap/app.php` only registers `web` routes (no `api.php`); JSON responses are only produced when `shouldRenderJsonWhen` matches (`api/*` paths or `expectsJson()`), but no such routes exist yet.

## Anti-Patterns

### N/A — no anti-patterns identified

This is a fresh, minimal starter-kit codebase (Laravel + Inertia + Vue "vue-starter-kit"). It closely follows Laravel/Inertia/Fortify conventions with no accumulated deviations. As features are added, watch for:
- Fat controllers (currently controllers are thin — 1-3 methods, delegate validation to Form Requests).
- Business logic creeping into Vue components instead of composables/backend.

## Error Handling

**Strategy:** Laravel's default exception handling, customized only to force JSON responses for `api/*` paths or XHR-expecting requests (`bootstrap/app.php:26-29`). Validation errors flow through Inertia's standard mechanism (422 responses become `errors` props automatically re-rendered by Inertia client).

**Patterns:**
- Form Requests (`app/Http/Requests/**`) centralize authorization + validation; controllers assume valid input.
- Frontend error display uses `resources/js/components/InputError.vue` and `resources/js/components/AlertError.vue` for consistent field/form-level error rendering.

## Cross-Cutting Concerns

**Logging:** Default Laravel logging (`config/logging.php`), no custom logging channels added.
**Validation:** Centralized in `app/Http/Requests/**` + `app/Concerns/*ValidationRules.php` traits; password policy also configured globally in `AppServiceProvider::configureDefaults()` (min 12 chars, mixed case, symbols, uncompromised check in production).
**Authentication:** Fully delegated to `laravel/fortify`; app customizes only views (`FortifyServiceProvider`) and one action (`ResetUserPassword`). Session-based auth (not token/API auth) — no Sanctum/Passport configured.

---

*Architecture analysis: 2026-08-31*
