<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:

- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
    - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.

- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>

<!-- GSD:project-start source:PROJECT.md -->

## Project

**Inkspire**

Inkspire is a web-based printing management system for SquareFoot Graphics & Ads, replacing their manual/paper workflow (paper queueing, manual pricing, disconnected design tools, paper receipts, manual AR tracking) with one integrated platform. It covers customer queueing, job order management, POS/payments, a design workflow with a built-in web editor, QR-based order tracking for customers, accounts receivable with aging analysis, and reporting/audit trails. It's a modernization of an approved capstone manuscript (originally specified as vanilla PHP + MySQL + AJAX) onto Laravel + Inertia + Vue 3.

**Core Value:** A job order flows correctly end-to-end — a customer queues in, gets a job order created (print-ready or needs-consultation), pays, and the order moves through production to pickup with the right role seeing and doing the right thing at each step. Everything else (reports, AR aging automation, QR polish) matters, but this flow working correctly is what makes the system trustworthy enough to replace the paper process.

### Constraints

- **Tech stack**: Laravel 13 / PHP 8.4 backend (already scaffolded), Inertia v3 + Vue 3.5 frontend (not Blade-primary), MySQL in production (currently SQLite in dev `.env` — switch before real use). Non-negotiable — this is the already-committed stack for the project, not an open choice.
- **RBAC**: exactly 6 roles, single `role` enum column on `users`, one role per user, each role with its own dedicated portal (not a shared layout with filtered nav). The Owner role was removed — it duplicated Admin, which now holds every administrative power.
- **Payments**: PayMongo for GCash/Maya, webhook-confirmed. Cash and Bank Transfer recorded directly, no gateway.
- **Data integrity**: `transactions.job_order_id` NOT NULL (no standalone POS sales); `audit_trail` structurally append-only (no update/delete code paths at all, not just permission checks); `users` deactivate via `is_active`, never hard-delete, never `SoftDeletes`.
- **Hosting**: Laravel Cloud is the intended target (managed MySQL/storage/queue/scheduler) over self-managed VPS — no dedicated DevOps for this project.

<!-- GSD:project-end -->

<!-- GSD:stack-start source:codebase/STACK.md -->

## Technology Stack

## Languages

- PHP 8.4.3 (composer.json requires `^8.3`; local CLI is 8.4.3) - Backend application logic in `app/`, `config/`, `database/`, `routes/`
- TypeScript (strict mode, `target: ESNext`, `module: ESNext`) - Frontend Inertia/Vue application in `resources/js/`
- Vue 3 Single-File Components (`.vue`) - Pages/components in `resources/js/pages/`, `resources/js/components/`
- Blade - Not used for page rendering (Inertia handles views); check `resources/views/mail/*` for any mail templates

## Runtime

- PHP 8.4 (CLI runtime observed: `PHP 8.4.3`)
- Node.js v22.12.0 (frontend build/dev tooling)
- PHP: Composer, lockfile `composer.lock` present
- JS: pnpm (`pnpm-workspace.yaml` present) with npm lockfile `package-lock.json` also present — check which is authoritative before installing; `.npmrc` present with custom settings

## Frameworks

- Laravel Framework 13.29.0 (`laravel/framework`) - Backend application framework
- Inertia.js Laravel adapter 3.3.1 (`inertiajs/inertia-laravel`) - Server-driven SPA bridge, routes rendered via `Route::inertia()` in `routes/web.php`
- `@inertiajs/vue3` ^3.0.0 + `@inertiajs/vite` ^3.0.0 - Client-side Inertia adapter and Vite plugin
- Vue 3.5.13 - Frontend component framework
- Laravel Fortify 1.39.0 (`laravel/fortify`) - Authentication backend (login, password reset, two-factor auth) wired via `app/Providers/FortifyServiceProvider.php`
- Laravel Wayfinder 0.1.21 (`laravel/wayfinder`) + `@laravel/vite-plugin-wayfinder` - Generates TypeScript route/controller helpers into `resources/js/routes/` and `resources/js/actions/`
- Pest 5.1.3 (`pestphp/pest`) + `pestphp/pest-plugin-laravel` 5.0.1 - PHP test framework, config in `phpunit.xml`, tests in `tests/`
- Mockery 1.6.15 - Mocking library for Pest/PHPUnit tests
- FakerPHP 1.24.1 - Test data generation, used in factories (`database/factories/`)
- Vite 8.0.0 with `vite-plus` 0.3.0 wrapper (`vp build`, `vp dev`, `vp check` scripts in `package.json`) - Build tool, config in `vite.config.ts`
- `laravel-vite-plugin` 3.0.0 - Laravel/Vite integration (asset refresh, font loading via `bunny()`)
- Tailwind CSS 4.1.1 with `@tailwindcss/vite` 4.1.11 - Utility-first CSS, entry point `resources/css/app.css`
- `vue-tsc` 2.2.4 - TypeScript type-checking for Vue SFCs (`npm run types:check`)
- Laravel Pint 1.30.5 (`laravel/pint`) - PHP code formatter, preset `laravel` (`pint.json`), run via `vendor/bin/pint --dirty --format agent`
- Larastan 3.10.0 (`larastan/larastan`) - PHPStan-based static analysis for Laravel, level 7, config `phpstan.neon`, run via `npm run types:check` (composer script) or `phpstan analyse`
- Laravel Boost 2.7.0 (`laravel/boost`) - AI-assisted development MCP server/guidelines, config `boost.json`, MCP wiring in `.mcp.json`
- Laravel Pail 1.2.7 - Real-time log tailing (`laravel/pail`)
- Laravel Sail 1.67.0 - Docker development environment (present as dependency; no `docker-compose.yml` observed at repo root beyond what Sail publishes)
- Laravel Pao 1.1.4 (`laravel/pao`) - Agent-optimized PHP testing output
- Laravel Chisel 0.1.1 (`laravel/chisel`) - Toolkit for removing/trimming build artifacts/scripts
- Laravel Tinker 3.0.2 - REPL for debugging

## Key Dependencies

- `laravel/framework` 13.29.0 - Application foundation (routing, ORM, service container, queues, etc.)
- `inertiajs/inertia-laravel` 3.3.1 + `@inertiajs/vue3` - Full-stack page rendering bridge; no separate REST/GraphQL API layer detected
- `laravel/fortify` 1.39.0 - All authentication flows (login, registration disabled/enabled per `config/fortify.php` features, password reset, two-factor auth, password confirmation)
- `reka-ui` 2.9.8 + `class-variance-authority` 0.7.1 + `tailwind-merge` 3.2.0 + `clsx` 2.1.1 - UI primitive/component composition stack (shadcn-vue style component library under `resources/js/components/ui`, per `components.json`)
- `@lucide/vue` 1.17.0 - Icon set
- `@vueuse/core` 12.8.2 - Vue composition utilities
- `vue-sonner` 2.0.0 - Toast notifications
- `laravel/wayfinder` + `@laravel/vite-plugin-wayfinder` - Type-safe route/action generation bridging PHP routes to TypeScript
- `@laravel/multiplex` 0.4.1 (optional dependency) - Likely supports concurrent dev/watch tooling
- `vite-plus` 0.3.0 - Wraps Vite with `lint`/`fmt`/`check` tasks configured directly in `vite.config.ts`

## Configuration

- Configured via `.env` (present, not committed — gitignored) and `.env.example` (template, committed)
- Key env vars: `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_URL`, `DB_CONNECTION` (default `sqlite`), `SESSION_DRIVER` (`database`), `QUEUE_CONNECTION` (`database`), `CACHE_STORE` (`database`), `BROADCAST_CONNECTION` (`log`), `FILESYSTEM_DISK` (`local`), `MAIL_MAILER` (`log`)
- No `REDIS_*`, `AWS_*`, mail provider, or third-party API keys populated in `.env.example` beyond placeholders — this is a stock Laravel starter kit configuration with no live external services wired up yet
- `vite.config.ts` - Vite/Inertia/Vue/Tailwind/Wayfinder plugin configuration, plus `vite-plus` lint/fmt/check options
- `tsconfig.json` - TypeScript strict mode, `ESNext` target/module
- `pint.json` - PHP formatting preset (`laravel`)
- `phpstan.neon` - Static analysis, level 7, scans `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`
- `phpunit.xml` - Pest/PHPUnit test suite configuration
- `components.json` - shadcn-vue style component generator config

## Platform Requirements

- PHP >= 8.3 (project targets/runs on 8.4)
- Node.js (v22.12.0 observed) with pnpm as workspace package manager
- SQLite by default for local development database (`database/database.sqlite`)
- `composer run dev` (or `npm run dev` + `php artisan serve`) starts the local dev environment; `composer.json` `dev` script runs `php artisan dev`
- No explicit deployment target configured in-repo (no `Procfile`, `fly.toml`, `render.yaml`, or Laravel Cloud config detected)
- Laravel Forge/Cloud/Sail compatible given standard Laravel structure; `laravel/sail` is present as a dev dependency for Docker-based environments
- Database driver is environment-driven (`DB_CONNECTION`) and supports switching from SQLite to MySQL/MariaDB/PostgreSQL via `config/database.php` without code changes

<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->

## Conventions

## Naming Patterns

- Controllers: `{Resource}Controller.php` under feature subfolders, e.g. `app/Http/Controllers/Settings/ProfileController.php`, `app/Http/Controllers/Settings/SecurityController.php`
- Form Requests: `{Resource}{Action}Request.php`, e.g. `app/Http/Requests/Settings/ProfileUpdateRequest.php`, `app/Http/Requests/Settings/ProfileDeleteRequest.php`, `app/Http/Requests/Settings/TwoFactorAuthenticationRequest.php`
- Shared validation logic: traits under `app/Concerns/`, e.g. `app/Concerns/PasswordValidationRules.php`, `app/Concerns/ProfileValidationRules.php`
- Actions (Fortify overrides): `app/Actions/Fortify/ResetUserPassword.php`
- Models: singular, `app/Models/User.php`
- Middleware: `app/Http/Middleware/HandleAppearance.php`, `app/Http/Middleware/HandleInertiaRequests.php`
- Page components: PascalCase matching Inertia component path, e.g. `resources/js/pages/settings/Profile.vue`, `resources/js/pages/auth/Login.vue`
- Reusable components: PascalCase, e.g. `resources/js/components/DeleteUser.vue`, `resources/js/components/Heading.vue`, `resources/js/components/InputError.vue`
- shadcn-vue primitives live under `resources/js/components/ui/{component}/` (generated; do not hand-edit — see Special Directories note in STRUCTURE.md)
- Generated Wayfinder route/action helpers live under `resources/js/routes/` and `resources/js/actions/` (do not hand-edit, regenerated by `@laravel/vite-plugin-wayfinder`)
- Controller actions use RESTful verbs: `edit`, `update`, `destroy` (see `app/Http/Controllers/Settings/ProfileController.php`)
- FormRequest rule methods: `rules(): array`
- Validation trait methods: `{subject}Rules()`, e.g. `passwordRules()`, `currentPasswordRules()` in `app/Concerns/PasswordValidationRules.php`
- Test helper methods on `Tests\TestCase`: `skipUnless{Condition}`, e.g. `skipUnlessFortifyHas()` in `tests/TestCase.php`
- camelCase in PHP and TypeScript/Vue.
- Route helper imports aliased to short verbs, e.g. `import { edit } from '@/routes/profile'` in `resources/js/pages/settings/Profile.vue`.
- Full parameter and return type declarations everywhere observed (`function edit(Request $request): Response`).
- PHPDoc `@property` blocks on Eloquent models document virtual/database attributes (`app/Models/User.php`).
- Array shapes documented via PHPDoc, e.g. `@return array<string, ValidationRule|array<mixed>|string>` in `app/Http/Requests/Settings/ProfileUpdateRequest.php`.

## Code Style

- Laravel Pint with the `laravel` preset only (`pint.json`): `{"preset": "laravel"}`.
- Run `vendor/bin/pint --dirty --format agent` after any PHP change (per project CLAUDE.md), not `pint --test`.
- Managed by `vite-plus`'s built-in `fmt`/`lint` config inside `vite.config.ts` (no separate `.prettierrc`/`.eslintrc`).
- Key settings: `printWidth: 80`, `tabWidth: 4`, `singleQuote: true`, `semi: true`, `singleAttributePerLine: false`, `htmlWhitespaceSensitivity: 'css'`.
- Tailwind class sorting enabled via `sortTailwindcss` (functions: `clsx`, `cn`, `cva`; entry point `resources/css/app.css`).
- Lint ignores generated/vendor code: `resources/js/actions/**`, `resources/js/routes/**`, `resources/js/wayfinder/**`, `resources/js/components/ui/*`, `vendor/**`, `node_modules/**`, `public/**`, `bootstrap/ssr/**`.
- Format ignores: `resources/js/components/ui/*`, `resources/views/mail/*`, `composer.json`, `.github/**`.
- Run `npm run check:fix` (maps to `vp check --fix`) to auto-fix; `npm run check` to verify.
- `.editorconfig` sets base indent to 4 spaces, LF line endings, UTF-8, trimmed trailing whitespace, final newline; YAML files use 2-space indent.
- Larastan (`phpstan.neon`) at **level 7**, scanning `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`.
- Run via `composer types:check` → `phpstan analyse`.
- TypeScript strictness enforced by `vue-tsc --noEmit` (`npm run types:check`).

## Import Organization

- Standard PSR-4 `use` statements, alphabetically grouped by convention (framework classes, then app classes), one per line, no aliasing except where a class name would collide.
- Namespace root: `App\` → `app/`; test namespace `Tests\` → `tests/` (see `composer.json` autoload).
- Import order observed in `resources/js/pages/settings/Profile.vue`: framework packages first (`@inertiajs/vue3`), then `vue` core, then generated Wayfinder controllers (`@/actions/...`), then local components (`@/components/...`), then UI primitives (`@/components/ui/...`), then generated route helpers (`@/routes/...`).
- Path Alias: `@/*` → `./resources/js/*` (`tsconfig.json`, `components.json`).
- Additional aliases via `components.json`: `components: @/components`, `composables: @/composables`, `utils: @/lib/utils`, `ui: @/components/ui`, `lib: @/lib`.

## Error Handling

- Validation rules are defined via `FormRequest::rules()` classes under `app/Http/Requests/**`, not inline in controllers.
- Shared/reusable rule sets extracted into traits under `app/Concerns/` and composed into FormRequests, e.g. `ProfileUpdateRequest` uses `ProfileValidationRules::profileRules()`, `PasswordUpdateRequest` presumably uses `PasswordValidationRules`.
- Controllers assume validated input is safe and call `$request->validated()` (see `ProfileController::update`).
- Failed authorization/validation in tests asserted via `assertSessionHasErrors('field')` and `assertSessionHasNoErrors()`.
- Controllers redirect via `to_route()` / `redirect()` / `back()` after mutations, not JSON error payloads (this app is Inertia-driven, not a JSON API).
- Success feedback delivered via `Inertia::flash('toast', ['type' => 'success', 'message' => __('...')])` pattern, e.g. `ProfileController::update`, `SecurityController::update`. Follow this pattern for new mutating actions that need user feedback.
- Destructive actions re-verify state deliberately: `ProfileController::destroy` logs out, deletes user, invalidates session, and regenerates the CSRF token before redirecting.
- Form errors surfaced via Inertia `<Form>` slot props (`errors`, `processing`) and rendered with the shared `<InputError :message="errors.field" />` component (`resources/js/components/InputError.vue`, used in `resources/js/pages/settings/Profile.vue`).
- No custom global error boundary/toast library beyond `vue-sonner` (dependency present in `package.json`) tied to the backend's `Inertia::flash('toast', ...)` convention.

## Comments

- Sparse inline comments; PHPDoc blocks are the primary documentation form (per project CLAUDE.md: "Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.")
- Every public controller method has a one-line PHPDoc summary, e.g. `/** * Show the user's profile settings page. */` in `app/Http/Controllers/Settings/ProfileController.php`.
- Every non-trivial return type gets an `@return` array-shape annotation, especially for validation rule arrays and Eloquent casts.

## Function Design

## Module Design (PHP)

- Uses PHP 8 attributes for Eloquent metadata instead of protected properties: `#[Fillable([...])]` and `#[Hidden([...])]` on the class (`app/Models/User.php`), rather than `protected $fillable` / `protected $hidden`.
- Casts defined via the `casts(): array` method (Laravel 11+ style), not the legacy `protected $casts` property.

## Vue Component Conventions

- `<script setup lang="ts">` with Composition API exclusively.
- Single root element per component (project rule from `inertia-vue/core` guidelines).
- Page-level layout/breadcrumbs configured via `defineOptions({ layout: { breadcrumbs: [...] } })` at the top of `<script setup>`, referencing generated route helpers (`edit()` from `@/routes/profile`) for `href` values — never hardcoded URL strings.
- Forms built with Inertia's `<Form>` component bound to generated Wayfinder controller actions: `v-bind="ProfileController.update.form()"`, exposing `{ errors, processing }` via `v-slot`.
- Interactive elements needing test hooks carry `data-test="..."` attributes, e.g. `data-test="update-profile-button"` in `resources/js/pages/settings/Profile.vue`.
- Reads current user via `usePage()` + `computed(() => page.props.auth.user)`, not local state or props drilling.

## PHP Language Conventions (from project CLAUDE.md)

- Always use curly braces for control structures, even single-line bodies.
- Constructor property promotion for DI: `public function __construct(public GitHub $github) {}`.
- Explicit return types and parameter type hints on all methods.
- TitleCase enum keys (e.g. `FavoritePerson`, `Monthly`).
- Array shape type definitions in PHPDoc blocks over inline comments.

<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->

## Architecture

## System Overview

```text

```

## Component Responsibilities

| Component                          | Responsibility                                                                                                                  | File                                                                                                          |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Inertia SPA bootstrap              | Registers layout-resolution rules and app-wide plugins (theme, flash toasts)                                                    | `resources/js/app.ts`                                                                                         |
| Root Blade template                | Single HTML shell Inertia hydrates into; sets appearance class from cookie                                                      | `resources/views/app.blade.php`                                                                               |
| `HandleInertiaRequests`            | Shares global props (`auth.user`, `name`, `sidebarOpen`) with every Inertia response                                            | `app/Http/Middleware/HandleInertiaRequests.php`                                                               |
| `HandleAppearance`                 | Shares light/dark/system cookie value with Blade views                                                                          | `app/Http/Middleware/HandleAppearance.php`                                                                    |
| Web routes                         | Public/dashboard pages rendered directly via `Route::inertia()`                                                                 | `routes/web.php`                                                                                              |
| Settings routes                    | CRUD-style routes for profile/security backed by controllers                                                                    | `routes/settings.php`                                                                                         |
| Settings controllers               | Handle profile update/delete and password update, return Inertia responses                                                      | `app/Http/Controllers/Settings/ProfileController.php`, `app/Http/Controllers/Settings/SecurityController.php` |
| Fortify service provider           | Wires custom Inertia views for Fortify's built-in auth routes (login, reset password, confirm password) and login rate limiting | `app/Providers/FortifyServiceProvider.php`                                                                    |
| Form Requests                      | Authorize + validate settings input, delegate rule sets to `Concerns` traits                                                    | `app/Http/Requests/Settings/*.php`                                                                            |
| Validation concerns                | Reusable validation rule builders shared across requests                                                                        | `app/Concerns/ProfileValidationRules.php`, `app/Concerns/PasswordValidationRules.php`                         |
| `User` model                       | Sole domain model; auth, factory, casts, fillable/hidden via PHP attributes                                                     | `app/Models/User.php`                                                                                         |
| Wayfinder-generated actions/routes | Type-safe TS wrappers around Laravel routes/controllers, regenerated from PHP                                                   | `resources/js/actions/**`, `resources/js/routes/**`                                                           |
| UI component library               | Reusable shadcn/reka-ui-based primitives (button, dialog, sidebar, etc.)                                                        | `resources/js/components/ui/**`                                                                               |
| Layouts                            | Compose page chrome (sidebar/header shell, auth split screen, settings sub-nav) around page components                          | `resources/js/layouts/**`                                                                                     |

## Pattern Overview

- No traditional Blade views for pages — one root Blade template (`resources/views/app.blade.php`) hosts the Vue SPA; all page content is Vue components under `resources/js/pages/`.
- Controllers return `Inertia::render()` responses instead of JSON API payloads; there is no `app/Http/Controllers/Api` namespace.
- Authentication (login, password reset, password confirmation, 2FA scaffolding) is delegated to `laravel/fortify`; the app only supplies views and one custom action (`ResetUserPassword`).
- Frontend route/action calls use Laravel Wayfinder-generated TypeScript (`resources/js/actions/**`, `resources/js/routes/**`) instead of hand-written URL strings, keeping frontend and backend routes in sync.
- Single Eloquent model (`User`) — this is a starter-kit-scale app; no domain-specific models exist yet beyond auth/user management and settings.

## Layers

- Purpose: Render pages, handle client-side interactivity, form submission via Inertia
- Location: `resources/js/pages/`, `resources/js/layouts/`, `resources/js/components/`
- Contains: `.vue` single-file components, composables (`resources/js/composables/`), small client-side libs (`resources/js/lib/`)
- Depends on: Wayfinder-generated actions/routes (`resources/js/actions`, `resources/js/routes`), shared types (`resources/js/types/`)
- Used by: Browser only (SSR is configured but optional — see `build:ssr` script)
- Purpose: Map URLs to controllers or direct Inertia renders
- Location: `routes/web.php`, `routes/settings.php`, `routes/console.php`
- Contains: Route definitions, route groups with `auth`/`verified`/`throttle` middleware
- Depends on: Controllers, Fortify's own route registration (not in `routes/`, registered by the package)
- Used by: Incoming HTTP requests
- Purpose: Orchestrate request handling — validate input, mutate models, return Inertia responses/redirects
- Location: `app/Http/Controllers/`, `app/Http/Requests/`, `app/Actions/Fortify/`
- Contains: Controller classes (thin — one `edit`/`update`/`destroy` per resource), `FormRequest` subclasses for authorization+validation, Fortify action classes
- Depends on: `app/Concerns/*ValidationRules.php` traits, Eloquent models
- Used by: Routes
- Purpose: Persistence and domain rules
- Location: `app/Models/`
- Contains: `User.php` (only model currently) using PHP 8 attributes (`#[Fillable]`, `#[Hidden]`) instead of legacy `$fillable`/`$hidden` properties
- Depends on: `database/factories/UserFactory.php` for test data
- Used by: Controllers, Fortify actions
- Purpose: Bootstrap app-wide config, share request-scoped data, configure security defaults
- Location: `app/Providers/`, `app/Http/Middleware/`
- Contains: `AppServiceProvider` (date immutability, destructive-command guard, password policy), `FortifyServiceProvider` (custom views, rate limiting), `HandleInertiaRequests`, `HandleAppearance`
- Depends on: Framework services (`Date`, `DB`, `Password`, `RateLimiter`)
- Used by: Registered in `bootstrap/app.php` (middleware) and `bootstrap/providers.php`-style provider array (implicit via `config/app.php`/auto-discovery)

## Data Flow

### Primary Request Path (page visit)

### Form Submission Flow (e.g. profile update)

### Authentication Flow

- No client-side global store (no Pinia/Vuex). State is server-driven: Inertia props per page plus small composables (`useAppearance`, `useInitials`, `useCurrentUrl`) for local/UI-only concerns.
- Theme/appearance persisted via cookie, read on both server (`HandleAppearance` → Blade) and client (`useAppearance.ts`) to avoid flash-of-wrong-theme.

## Key Abstractions

- Purpose: Separate reusable validation rule sets from the request classes that use them, so rules can be shared across multiple requests (e.g. profile update vs. delete needing the same email rules).
- Examples: `app/Http/Requests/Settings/ProfileUpdateRequest.php` uses `app/Concerns/ProfileValidationRules.php`; `app/Http/Requests/Settings/PasswordUpdateRequest.php` likely uses `app/Concerns/PasswordValidationRules.php`.
- Pattern: `FormRequest` class `use`s a `Concerns` trait and calls a `*Rules()` method inside `rules()`.
- Purpose: Give the Vue frontend typed, refactor-safe references to Laravel routes and controller methods, generated from the PHP route table.
- Examples: `resources/js/actions/App/Http/Controllers/Settings/ProfileController.ts`, `resources/js/routes/index.ts`
- Pattern: Do not hand-edit these files — they are generated (see `.claude/skills/wayfinder-development`). Regenerate via the Wayfinder Vite plugin/Artisan command when routes/controllers change.
- Purpose: Automatically wrap pages in the correct chrome without per-page boilerplate.
- Examples: `app.ts:12-23` — pages under `auth/*` get `AuthLayout`, pages under `settings/*` get `[AppLayout, SettingsLayout]` nested layouts, `Welcome` gets no layout, everything else gets `AppLayout`.
- Pattern: New pages must be placed under the correct `resources/js/pages/<namespace>/` subfolder to inherit the intended layout automatically.

## Entry Points

- Location: `public/index.php` (standard Laravel front controller, not modified)
- Triggers: All web requests
- Responsibilities: Bootstraps `bootstrap/app.php`, dispatches through the HTTP kernel/middleware stack defined there
- Location: `resources/js/app.ts`
- Triggers: Loaded by `resources/views/app.blade.php` via Vite
- Responsibilities: Initializes Inertia app, layout resolution, theme, and flash-toast listener
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

- Fat controllers (currently controllers are thin — 1-3 methods, delegate validation to Form Requests).
- Business logic creeping into Vue components instead of composables/backend.

## Error Handling

- Form Requests (`app/Http/Requests/**`) centralize authorization + validation; controllers assume valid input.
- Frontend error display uses `resources/js/components/InputError.vue` and `resources/js/components/AlertError.vue` for consistent field/form-level error rendering.

## Cross-Cutting Concerns

<!-- GSD:architecture-end -->

<!-- GSD:skills-start source:skills/ -->

## Project Skills

| Skill                   | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | Path                                              |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------- |
| fortify-development     | 'ACTIVATE when the user works on authentication in Laravel. This includes login, registration, password reset, email verification, two-factor authentication (2FA/TOTP/QR codes/recovery codes), passkeys, profile updates, password confirmation, or any auth-related routes and controllers. Activate when the user mentions Fortify, auth, authentication, login, register, signup, forgot password, verify email, 2FA, passkeys, WebAuthn, or references app/Actions/Fortify/, CreateNewUser, UpdateUserProfileInformation, FortifyServiceProvider, config/fortify.php, or auth guards. Fortify is the frontend-agnostic authentication backend for Laravel that registers all auth routes and controllers. Also activate when building SPA or headless authentication, customizing login redirects, overriding response contracts like LoginResponse, or configuring login throttling. Do NOT activate for Laravel Passport (OAuth2 API tokens), Socialite (OAuth social login), or non-auth Laravel features.' | `.claude/skills/fortify-development/SKILL.md`     |
| inertia-vue-development | "Develops Inertia.js v3 Vue client-side applications. Activates when creating Vue pages, forms, or navigation; using <Link>, <Form>, useForm, useHttp, setLayoutProps, or router; working with deferred props, prefetching, optimistic updates, instant visits, or polling; or when user mentions Vue with Inertia, Vue pages, Vue forms, or Vue navigation."                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | `.claude/skills/inertia-vue-development/SKILL.md` |
| infer-conventions       | "Use this skill to analyze how a Laravel application is actually written and record its conventions as shared rules. Trigger when the user wants to detect, infer, document, or standardize project conventions or coding style, set up or grow `.ai/rules`, resolve mixed or conflicting patterns (e.g. \"are we using Form Requests or inline validation?\"), or onboard agents and teammates to \"how we do things here\". Covers: a systematic sweep of ~49 Laravel convention dimensions (validation, models, architecture, testing, frontend, database, console), open-ended house-pattern discovery, conflict reporting, and recording rules scoped to the right paths via the Boost `record-rule` MCP tool. Do not use for one-off code review, enforcing formatting a linter already handles, or editing `.ai/rules` files by hand."                                                                                                                                                                        | `.claude/skills/infer-conventions/SKILL.md`       |
| laravel-best-practices  | "Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns."                                                                                                                                                                                                                                                                                                                                                                                      | `.claude/skills/laravel-best-practices/SKILL.md`  |
| tailwindcss-development | "Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS."                                                                                                                                                                                                                                                                                                             | `.claude/skills/tailwindcss-development/SKILL.md` |
| testing-best-practices  | "Laravel test design and review. Use when selecting coverage, naming or structuring tests, choosing assertions or test data, isolating dependencies, testing HTTP or security boundaries, improving suite performance, or reviewing test value. Use framework guidance or search-docs for Pest and PHPUnit syntax."                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  | `.claude/skills/testing-best-practices/SKILL.md`  |
| wayfinder-development   | "Use this skill for Laravel Wayfinder which auto-generates typed functions for Laravel controllers and routes. ALWAYS use this skill when frontend code needs to call backend routes or controller actions. Trigger when: connecting any React/Vue/Svelte/Inertia frontend to Laravel controllers, routes, building end-to-end features with both frontend and backend, wiring up forms or links to backend endpoints, fixing route-related TypeScript errors, importing from @/actions or @/routes, or running wayfinder:generate. Use Wayfinder route functions instead of hardcoded URLs. Covers: wayfinder() vite plugin, .url()/.get()/.post()/.form(), query params, route model binding, tree-shaking. Do not use for backend-only task"                                                                                                                                                                                                                                                                      | `.claude/skills/wayfinder-development/SKILL.md`   |

<!-- GSD:skills-end -->

<!-- GSD:workflow-start source:GSD defaults -->

## UI Changes Require a User-Friendly Check

Every change that alters what a user sees or does must pass this check _before_
you report it as done. It is not a style review — each item is a defect the
shop's staff have actually been handed.

1. **Long lists are searchable.** More than ~10 options is a `SearchableSelect`,
   not a scroll. Staff know the name of the thing they want; let them type it.
2. **Every state is reachable and reversible.** If a choice can be made wrong,
   there is a way back out of it without a page reload.
3. **Nothing important sits below the fold.** Primary actions on a long form are
   sticky. If the submit button is a screen away from the last field, fix it.
4. **Wide content scrolls inside its own box**, never the page. A table's
   horizontal scroll must not drag the heading and filters off-screen.
5. **Keyboard reaches everything a mouse can.** A clickable row needs
   `tabindex` and an `@keyup.enter`.
6. **Numbers that stack in a column get `tabular-nums`**, or the digits jitter
   between rows.
7. **Every screen says what it is for.** A title that restates the sidebar item
   is not orientation; add a description that tells the user what they do here.
8. **Empty states offer the action that fills them.** "No queue entries yet" is
   a dead end; "No queue entries yet — New Visit" is not.
9. **Both themes, both ends of the viewport.** Semantic tokens only
   (`bg-card`, `text-muted-foreground`, `border-border`) — never a raw colour —
   and check 375px as well as desktop.
10. **Interactive widgets are observed working, not just type-checked.** A green
    `types:check` on a component wired to a reka-ui primitive proves the props
    match, not that the thing behaves — library defaults like
    `Combobox.openOnClick: false` only show up in a browser. Drive it: open it,
    type in it, keyboard through it, then change your mind and do it again.
    The "change an existing value" path breaks more often than the first-use one.

### Reuse the shared components

Do not hand-roll a page shell, header, table card, stat tile or empty state.
These exist and are used across every portal:

`PageContainer`, `PageHeader`, `SectionHeading`, `DataTableCard`, `EmptyState`,
`StatCard`, `SearchableSelect` — all in `resources/js/components/`.

Tailwind utilities only. No inline `style=`, no `<style>` blocks, no CSS
modules, and no additions to `app.css` for something a utility already does.

## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:

- `/gsd-quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd-debug` for investigation and bug fixing
- `/gsd-execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->

<!-- GSD:profile-start -->

## Developer Profile

> Profile not yet configured. Run `/gsd-profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.

<!-- GSD:profile-end -->
