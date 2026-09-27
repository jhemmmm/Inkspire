# Technology Stack

**Analysis Date:** 2026-08-31

## Languages

**Primary:**

- PHP 8.4.3 (composer.json requires `^8.3`; local CLI is 8.4.3) - Backend application logic in `app/`, `config/`, `database/`, `routes/`
- TypeScript (strict mode, `target: ESNext`, `module: ESNext`) - Frontend Inertia/Vue application in `resources/js/`

**Secondary:**

- Vue 3 Single-File Components (`.vue`) - Pages/components in `resources/js/pages/`, `resources/js/components/`
- Blade - Not used for page rendering (Inertia handles views); check `resources/views/mail/*` for any mail templates

## Runtime

**Environment:**

- PHP 8.4 (CLI runtime observed: `PHP 8.4.3`)
- Node.js v22.12.0 (frontend build/dev tooling)

**Package Manager:**

- PHP: Composer, lockfile `composer.lock` present
- JS: pnpm (`pnpm-workspace.yaml` present) with npm lockfile `package-lock.json` also present — check which is authoritative before installing; `.npmrc` present with custom settings

## Frameworks

**Core:**

- Laravel Framework 13.29.0 (`laravel/framework`) - Backend application framework
- Inertia.js Laravel adapter 3.3.1 (`inertiajs/inertia-laravel`) - Server-driven SPA bridge, routes rendered via `Route::inertia()` in `routes/web.php`
- `@inertiajs/vue3` ^3.0.0 + `@inertiajs/vite` ^3.0.0 - Client-side Inertia adapter and Vite plugin
- Vue 3.5.13 - Frontend component framework
- Laravel Fortify 1.39.0 (`laravel/fortify`) - Authentication backend (login, password reset, two-factor auth) wired via `app/Providers/FortifyServiceProvider.php`
- Laravel Wayfinder 0.1.21 (`laravel/wayfinder`) + `@laravel/vite-plugin-wayfinder` - Generates TypeScript route/controller helpers into `resources/js/routes/` and `resources/js/actions/`

**Testing:**

- Pest 5.1.3 (`pestphp/pest`) + `pestphp/pest-plugin-laravel` 5.0.1 - PHP test framework, config in `phpunit.xml`, tests in `tests/`
- Mockery 1.6.15 - Mocking library for Pest/PHPUnit tests
- FakerPHP 1.24.1 - Test data generation, used in factories (`database/factories/`)

**Build/Dev:**

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

**Critical:**

- `laravel/framework` 13.29.0 - Application foundation (routing, ORM, service container, queues, etc.)
- `inertiajs/inertia-laravel` 3.3.1 + `@inertiajs/vue3` - Full-stack page rendering bridge; no separate REST/GraphQL API layer detected
- `laravel/fortify` 1.39.0 - All authentication flows (login, registration disabled/enabled per `config/fortify.php` features, password reset, two-factor auth, password confirmation)
- `reka-ui` 2.9.8 + `class-variance-authority` 0.7.1 + `tailwind-merge` 3.2.0 + `clsx` 2.1.1 - UI primitive/component composition stack (shadcn-vue style component library under `resources/js/components/ui`, per `components.json`)
- `@lucide/vue` 1.17.0 - Icon set
- `@vueuse/core` 12.8.2 - Vue composition utilities
- `vue-sonner` 2.0.0 - Toast notifications

**Infrastructure:**

- `laravel/wayfinder` + `@laravel/vite-plugin-wayfinder` - Type-safe route/action generation bridging PHP routes to TypeScript
- `@laravel/multiplex` 0.4.1 (optional dependency) - Likely supports concurrent dev/watch tooling
- `vite-plus` 0.3.0 - Wraps Vite with `lint`/`fmt`/`check` tasks configured directly in `vite.config.ts`

## Configuration

**Environment:**

- Configured via `.env` (present, not committed — gitignored) and `.env.example` (template, committed)
- Key env vars: `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_URL`, `DB_CONNECTION` (default `sqlite`), `SESSION_DRIVER` (`database`), `QUEUE_CONNECTION` (`database`), `CACHE_STORE` (`database`), `BROADCAST_CONNECTION` (`log`), `FILESYSTEM_DISK` (`local`), `MAIL_MAILER` (`log`)
- No `REDIS_*`, `AWS_*`, mail provider, or third-party API keys populated in `.env.example` beyond placeholders — this is a stock Laravel starter kit configuration with no live external services wired up yet

**Build:**

- `vite.config.ts` - Vite/Inertia/Vue/Tailwind/Wayfinder plugin configuration, plus `vite-plus` lint/fmt/check options
- `tsconfig.json` - TypeScript strict mode, `ESNext` target/module
- `pint.json` - PHP formatting preset (`laravel`)
- `phpstan.neon` - Static analysis, level 7, scans `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`
- `phpunit.xml` - Pest/PHPUnit test suite configuration
- `components.json` - shadcn-vue style component generator config

## Platform Requirements

**Development:**

- PHP >= 8.3 (project targets/runs on 8.4)
- Node.js (v22.12.0 observed) with pnpm as workspace package manager
- SQLite by default for local development database (`database/database.sqlite`)
- `composer run dev` (or `npm run dev` + `php artisan serve`) starts the local dev environment; `composer.json` `dev` script runs `php artisan dev`

**Production:**

- No explicit deployment target configured in-repo (no `Procfile`, `fly.toml`, `render.yaml`, or Laravel Cloud config detected)
- Laravel Forge/Cloud/Sail compatible given standard Laravel structure; `laravel/sail` is present as a dev dependency for Docker-based environments
- Database driver is environment-driven (`DB_CONNECTION`) and supports switching from SQLite to MySQL/MariaDB/PostgreSQL via `config/database.php` without code changes

---

_Stack analysis: 2026-08-31_
