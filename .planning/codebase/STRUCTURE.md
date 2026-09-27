# Codebase Structure

**Analysis Date:** 2026-08-31

## Directory Layout

```
inkspire/
├── app/                        # PHP application code (PSR-4: App\)
│   ├── Actions/Fortify/        # Custom Fortify action classes
│   ├── Concerns/                # Shared traits (validation rule sets)
│   ├── Console/Commands/        # Custom Artisan commands (currently empty)
│   ├── Http/
│   │   ├── Controllers/         # Thin controllers, grouped by feature (Settings/)
│   │   ├── Middleware/          # HandleAppearance, HandleInertiaRequests
│   │   └── Requests/            # FormRequest validation classes, grouped by feature
│   ├── Models/                  # Eloquent models (only User.php currently)
│   └── Providers/               # Service providers (AppServiceProvider, FortifyServiceProvider)
├── bootstrap/                   # Framework bootstrap (app.php config, cache/)
├── config/                      # Laravel config files (app, auth, fortify, inertia, ...)
├── database/
│   ├── factories/               # Model factories (UserFactory)
│   ├── migrations/               # Schema migrations (users, cache, jobs)
│   ├── seeders/                  # DatabaseSeeder
│   └── database.sqlite           # Default local SQLite DB file
├── demo/                        # Standalone static HTML/JS demo assets, NOT part of the Laravel app
├── public/                      # Web root; `public/build/` holds Vite-compiled assets
├── resources/
│   ├── css/                     # Tailwind entry CSS
│   ├── js/                      # Vue 3 + Inertia frontend (see below)
│   └── views/                   # Single Blade root template (app.blade.php)
├── routes/                      # web.php, settings.php, console.php
├── storage/                     # Framework-managed logs, cache, compiled views
├── tests/
│   ├── Feature/                 # Feature tests (HTTP + Inertia assertions), mirrors app/ structure
│   │   ├── Auth/
│   │   └── Settings/
│   ├── Unit/                    # Unit tests
│   ├── Pest.php                 # Pest configuration/helpers
│   └── TestCase.php
├── vendor/                      # Composer dependencies (not committed logic)
├── node_modules/                # NPM dependencies
├── artisan                      # Artisan CLI entry point
├── composer.json / composer.lock
├── package.json / package-lock.json / pnpm-workspace.yaml
├── vite.config.ts               # Vite build config (Vue, Tailwind, Wayfinder plugins)
├── tsconfig.json
├── phpunit.xml / pint.json / phpstan.neon
└── .ai/ (if present) / .claude/skills/  # Project rules and Claude Code skills
```

## Directory Purposes

**`app/Http/Controllers/Settings/`:**

- Purpose: HTTP controllers for authenticated user self-service (profile, security)
- Contains: `ProfileController.php`, `SecurityController.php` — each with `edit`/`update`(/`destroy`) methods only
- Key files: `app/Http/Controllers/Settings/ProfileController.php`, `app/Http/Controllers/Settings/SecurityController.php`

**`app/Http/Requests/Settings/`:**

- Purpose: Authorization + validation for settings forms
- Contains: `ProfileUpdateRequest.php`, `ProfileDeleteRequest.php`, `PasswordUpdateRequest.php`, `TwoFactorAuthenticationRequest.php`
- Key files: each pairs with a `Concerns` trait for actual rule definitions

**`app/Concerns/`:**

- Purpose: Reusable validation rule builder traits, shared across multiple Form Requests
- Contains: `ProfileValidationRules.php`, `PasswordValidationRules.php`

**`app/Actions/Fortify/`:**

- Purpose: Override points for Fortify's pluggable auth actions
- Contains: `ResetUserPassword.php` (registered in `FortifyServiceProvider`)

**`app/Providers/`:**

- Purpose: Bootstrap/configure framework and package behavior
- Contains: `AppServiceProvider.php` (date/DB/password defaults), `FortifyServiceProvider.php` (Fortify views + rate limiting)

**`resources/js/pages/`:**

- Purpose: Inertia page components — one file per route/screen, rendered by `Inertia::render('path/Name', ...)` using the same string path
- Contains: `Welcome.vue`, `Dashboard.vue`, `auth/*.vue` (Login, ForgotPassword, ResetPassword, ConfirmPassword), `settings/*.vue` (Appearance, Profile, Security)
- Naming: subfolder name determines the layout applied by `resources/js/app.ts` (`auth/*` → `AuthLayout`, `settings/*` → `AppLayout` + `SettingsLayout`)

**`resources/js/layouts/`:**

- Purpose: Page chrome/wrappers composed around page components
- Contains: `AppLayout.vue`, `AuthLayout.vue`, `app/AppHeaderLayout.vue`, `app/AppSidebarLayout.vue`, `auth/AuthSplitLayout.vue`, `auth/AuthCardLayout.vue`, `auth/AuthSimpleLayout.vue`, `settings/Layout.vue`

**`resources/js/components/`:**

- Purpose: App-specific composite components (not generic UI primitives)
- Contains: `AppSidebar.vue`, `AppHeader.vue`, `NavMain.vue`, `NavUser.vue`, `UserMenuContent.vue`, `DeleteUser.vue`, `AppearanceTabs.vue`, `InputError.vue`, `AlertError.vue`, `PasswordInput.vue`, etc.

**`resources/js/components/ui/`:**

- Purpose: Generic shadcn-vue/reka-ui-based design-system primitives, one subfolder per component family
- Contains: `alert/`, `avatar/`, `badge/`, `breadcrumb/`, `button/`, `card/`, `checkbox/`, `collapsible/`, `dialog/`, `dropdown-menu/`, `input/`, `input-otp/`, `label/`, `navigation-menu/`, `select/`, `separator/`, `sheet/`, `sidebar/`, `skeleton/`, `sonner/`, `spinner/`, `tooltip/`
- Each subfolder: one or more `.vue` files + an `index.ts` barrel export
- Generated/managed via shadcn-vue CLI (`components.json` defines aliases and style `new-york-v4`)

**`resources/js/actions/` and `resources/js/routes/`:**

- Purpose: Laravel Wayfinder-generated TypeScript wrappers for backend routes/controllers
- Contains: mirrors PHP namespace structure (`App/Http/Controllers/...`, `Laravel/Fortify/Http/Controllers/...`, `Illuminate/Routing/...`, `Inertia/...`)
- Generated: Yes — do not hand-edit; regenerate via Wayfinder Vite plugin/Artisan command when routes change

**`resources/js/composables/`:**

- Purpose: Vue composition-API reusable stateful logic
- Contains: `useAppearance.ts` (theme persistence), `useInitials.ts` (avatar initials), `useCurrentUrl.ts`

**`resources/js/lib/`:**

- Purpose: Small framework-agnostic client-side utilities
- Contains: `utils.ts` (class merging helpers, likely `cn()`), `flashToast.ts` (Inertia flash → toast bridge)

**`resources/js/types/`:**

- Purpose: Shared TypeScript type definitions
- Contains: `global.d.ts`, `vue-shims.d.ts`, `ui.ts`, `navigation.ts`, `auth.ts`, `index.ts` (barrel)

**`demo/`:**

- Purpose: Standalone static demo (HTML/JS/CSS + images) unrelated to the Laravel/Inertia app build pipeline — not referenced by `vite.config.ts` or Laravel routes
- Contains: `index.html`, `queue-display.html`, `main.js`, `style.css`, image assets

**`tests/Feature/`:**

- Purpose: HTTP-level tests using Pest, asserting Inertia component/props via `Inertia\Testing\AssertableInertia`
- Contains: `Auth/AuthenticationTest.php`, `Auth/PasswordConfirmationTest.php`, `Auth/PasswordResetTest.php`, `DashboardTest.php`, `ExampleTest.php`, `Settings/ProfileUpdateTest.php`, `Settings/SecurityTest.php`
- Mirrors: `app/Http/Controllers/**` structure by feature area

## Key File Locations

**Entry Points:**

- `public/index.php`: HTTP front controller (framework default)
- `resources/js/app.ts`: Frontend Inertia app bootstrap
- `artisan`: CLI entry point
- `resources/views/app.blade.php`: Single root Blade template hosting the SPA

**Configuration:**

- `bootstrap/app.php`: Middleware stack, routing registration, exception handling
- `config/*.php`: Standard Laravel config (`app.php`, `auth.php`, `fortify.php`, `inertia.php`, `session.php`, etc.)
- `vite.config.ts`: Frontend build config (Vue plugin, Tailwind plugin, Wayfinder plugin)
- `.env` / `.env.example`: Environment configuration (never read/quote contents)
- `components.json`: shadcn-vue component generation config
- `tsconfig.json`: TypeScript config, path aliases (`@/*` → `resources/js/*`)

**Core Logic:**

- `app/Http/Controllers/Settings/`: Settings feature controllers
- `app/Http/Requests/Settings/`: Settings validation
- `app/Concerns/`: Shared validation traits
- `app/Providers/FortifyServiceProvider.php`: Auth view/rate-limit wiring
- `app/Models/User.php`: Sole domain model

**Testing:**

- `tests/Feature/`: Pest HTTP/Inertia feature tests
- `tests/Unit/`: Pest unit tests
- `tests/Pest.php`: Pest bootstrap/helper config
- `phpunit.xml`: PHPUnit/Pest runner config
- `database/factories/UserFactory.php`: Test data factory

## Naming Conventions

**Files:**

- PHP classes: PascalCase matching class name, one class per file (`ProfileController.php`, `ProfileUpdateRequest.php`) — standard Laravel/PSR-4
- Vue components: PascalCase `.vue` files (`AppSidebar.vue`, `TextLink.vue`)
- Vue page components: PascalCase, path mirrors the string passed to `Inertia::render()` (e.g. `Inertia::render('settings/Profile')` → `resources/js/pages/settings/Profile.vue`)
- TypeScript composables: camelCase prefixed with `use` (`useAppearance.ts`, `useInitials.ts`)
- TS utility/lib files: camelCase (`utils.ts`, `flashToast.ts`)
- Generated Wayfinder files: mirror PHP namespace path exactly, including `index.ts` barrel files per namespace level

**Directories:**

- Feature-grouped subfolders under `Controllers/`, `Requests/` (e.g. `Settings/`) reflect the route group/feature area
- Page subfolders under `resources/js/pages/` (`auth/`, `settings/`) double as layout-selection keys in `app.ts` — this is a load-bearing convention, not just organization
- UI primitives live one directory per component family under `components/ui/<name>/`, always with an `index.ts` barrel

## Where to Add New Code

**New Feature (e.g. a new settings section or resource):**

- Route: add to `routes/web.php` or a new `routes/<feature>.php` (require it from `routes/web.php`)
- Controller: `app/Http/Controllers/<Feature>/` (namespace-matched directory) following the thin `edit`/`update`/`destroy` pattern seen in `Settings/`
- Validation: `app/Http/Requests/<Feature>/*Request.php`, extracting shared rules into `app/Concerns/` if reused
- Model: `app/Models/<Model>.php` with a matching `database/migrations/*_create_<table>_table.php` and `database/factories/<Model>Factory.php`
- Frontend page: `resources/js/pages/<feature>/<Name>.vue`, matching the string passed to `Inertia::render()`
- Tests: `tests/Feature/<Feature>/<Name>Test.php` mirroring the controller location

**New Vue Component:**

- App-specific composite component: `resources/js/components/<Name>.vue`
- Generic reusable primitive: `resources/js/components/ui/<name>/<Name>.vue` + `index.ts` barrel (use shadcn-vue CLI conventions per `components.json`)

**New Layout:**

- `resources/js/layouts/<Name>.vue` or `resources/js/layouts/<group>/<Name>.vue`; register the mapping in the `layout` resolver in `resources/js/app.ts` if it's a new page-name prefix

**Utilities:**

- Shared client-side helpers: `resources/js/lib/`
- Shared reactive/composition logic: `resources/js/composables/`
- Shared PHP validation logic: `app/Concerns/`
- Shared TypeScript types: `resources/js/types/`

## Special Directories

**`vendor/`:**

- Purpose: Composer PHP dependencies
- Generated: Yes
- Committed: No (gitignored)

**`node_modules/`:**

- Purpose: NPM dependencies
- Generated: Yes
- Committed: No (gitignored)

**`public/build/`:**

- Purpose: Vite-compiled frontend assets served in production
- Generated: Yes (via `npm run build`)
- Committed: No (gitignored) — must run `npm run build`/`npm run dev` for frontend changes to appear

**`storage/`:**

- Purpose: Framework-managed logs, framework cache, compiled Blade views, file uploads (if configured)
- Generated: Yes (mostly)
- Committed: No (structure/gitkeep only, per `.gitignore`)

**`bootstrap/cache/`:**

- Purpose: Framework config/route/event cache
- Generated: Yes
- Committed: No

**`demo/`:**

- Purpose: Standalone static HTML/JS/CSS demo unrelated to the Laravel app's build or runtime
- Generated: No
- Committed: Yes — treat as reference/prototype material, not part of the live application

**`.claude/skills/`:**

- Purpose: Project-specific Claude Code skills (Fortify, Inertia+Vue, Tailwind, testing, Laravel best practices, convention inference)
- Generated: No
- Committed: Yes — consult before implementing in these domains

---

_Structure analysis: 2026-08-31_
