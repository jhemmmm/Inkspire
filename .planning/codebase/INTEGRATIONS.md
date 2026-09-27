# External Integrations

**Analysis Date:** 2026-08-31

## APIs & External Services

No third-party business/product APIs are integrated yet. This is a freshly scaffolded Laravel + Inertia + Vue starter kit (`laravel/vue-starter-kit`). All integration points below are Laravel framework-level connectors that are configured but not populated with live credentials.

**Notifications (configured, unused):**

- Slack - `config/services.php` defines `slack.notifications.bot_user_oauth_token` / `channel`, sourced from `SLACK_BOT_USER_OAUTH_TOKEN` / `SLACK_BOT_USER_DEFAULT_CHANNEL` env vars (not set in `.env.example`)

## Data Storage

**Databases:**

- SQLite (default/active) - `config/database.php` `'default' => env('DB_CONNECTION', 'sqlite')`, file at `database/database.sqlite`
    - Client/ORM: Eloquent (Laravel's built-in ORM)
    - Alternative connections pre-configured but inactive: MySQL, MariaDB, PostgreSQL, SQL Server (all in `config/database.php`)
- Migrations: `database/migrations/` (standard Laravel schema, e.g. `users`, `sessions`, `cache`, `jobs` tables per starter kit conventions)

**File Storage:**

- Local filesystem - `config/filesystems.php` default disk `local` (private, `storage/app/private`) and `public` disk (`storage/app/public`, served at `/storage`)
- AWS S3 - configured in `config/filesystems.php` (`s3` disk) via `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL`, `AWS_ENDPOINT`; env vars present but blank in `.env.example` — not actively used

**Caching:**

- Database cache store (default) - `config/cache.php` `'default' => env('CACHE_STORE', 'database')`
- Redis available but inactive - `REDIS_CLIENT`/`REDIS_HOST`/`REDIS_PORT`/`REDIS_PASSWORD` present in `.env.example`, not selected as active driver

## Authentication & Identity

**Auth Provider:**

- Custom (Laravel Fortify) - `laravel/fortify` 1.39.0 handles all auth flows
    - Implementation: `app/Providers/FortifyServiceProvider.php` wires Inertia views for login (`auth/Login`), password reset request/confirm (`auth/ForgotPassword`, `auth/ResetPassword`), and password confirmation (`auth/ConfirmPassword`)
    - Custom password reset action: `app/Actions/Fortify/ResetUserPassword.php`
    - Two-factor authentication support present (`User` model exposes `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`; `app/Http/Requests/Settings/TwoFactorAuthenticationRequest.php`, `app/Http/Controllers/Settings/SecurityController.php`)
    - Session-based `web` guard against the Eloquent `users` provider (`config/auth.php`)
    - Login rate limiting: 5 attempts/minute per email+IP (`FortifyServiceProvider::configureRateLimiting`)
    - No OAuth/social login (no Socialite or similar package in `composer.json`)

## Monitoring & Observability

**Error Tracking:**

- None configured (no Sentry, Bugsnag, Flare, etc. in `composer.json`)

**Logs:**

- Laravel's default logging stack (`config/logging.php`), channel `stack` → `single` by default (`LOG_CHANNEL`, `LOG_STACK` env vars)
- Laravel Pail (`laravel/pail`) available as a dev dependency for local real-time log tailing

## CI/CD & Deployment

**Hosting:**

- Not configured in-repo — no deployment manifests detected (no `Procfile`, Laravel Cloud config, Forge recipes, or Dockerfile at repo root)
- `laravel/sail` present for local Docker-based development only

**CI Pipeline:**

- `.github/` directory present — check `.github/workflows/` for GitHub Actions configuration (not enumerated in this pass; confirm presence of workflow YAML files before assuming CI is active)
- Composer script `ci:check` runs `npm run check`, `npm run types:check`, and `@test` (Pint check + Larastan + Pest) — intended CI entry point

## Environment Configuration

**Required env vars (core framework):**

- `APP_KEY`, `APP_URL`, `APP_ENV`
- `DB_CONNECTION` (+ driver-specific `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` if not SQLite)
- `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE`, `BROADCAST_CONNECTION`, `FILESYSTEM_DISK`
- `MAIL_MAILER` (+ SMTP/provider-specific vars if not `log`)

**Optional/unused integration env vars (present as placeholders in `.env.example`):**

- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_USE_PATH_STYLE_ENDPOINT` (S3)
- `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`
- `POSTMARK_API_KEY`, `RESEND_API_KEY` (referenced in `config/services.php`, not in `.env.example`)
- `SLACK_BOT_USER_OAUTH_TOKEN`, `SLACK_BOT_USER_DEFAULT_CHANNEL` (referenced in `config/services.php`, not in `.env.example`)

**Secrets location:**

- `.env` (present locally, gitignored) — never read/quoted per security policy
- No dedicated secrets manager, vault, or `.env.*.local` layering detected

## Webhooks & Callbacks

**Incoming:**

- None — only routes defined are `routes/web.php` (`/`, `/dashboard`) and `routes/settings.php`, `routes/console.php`; Fortify auth routes are registered by the package itself. No webhook endpoints detected.

**Outgoing:**

- None detected

---

_Integration audit: 2026-08-31_
