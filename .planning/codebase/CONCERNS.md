# Codebase Concerns

**Analysis Date:** 2026-08-31

## Context

This repository is the stock Laravel + Inertia + Vue starter kit (`composer.json` name is still `laravel/vue-starter-kit`) with a single git commit (`init`). It currently ships only authentication and account-settings scaffolding (`app/Models/User.php`, `app/Http/Controllers/Settings/*`, Fortify wiring). No domain/business models, controllers, or migrations exist yet beyond `users`, `cache`, and `jobs`. Most items below are pre-existing starter-kit issues that will surface immediately once real feature work begins, plus one active test-suite-breaking bug.

## Known Bugs

**RefreshDatabase disabled — 17 of 27 feature tests fail:**
- Symptoms: Every feature test that touches the database fails with `SQLSTATE[HY000]: General error: 1 no such table: users` against the in-memory sqlite test database.
- Files: `tests/Pest.php:18` — `->use(RefreshDatabase::class)` is commented out on the `Feature` test binding.
- Trigger: Run `php artisan test --compact` (or `vendor/bin/pest`). Confirmed: 17 errors, 6 passed, 4 skipped out of 27 tests as of this analysis.
- Affected suites: `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/Auth/PasswordConfirmationTest.php`, `tests/Feature/Auth/PasswordResetTest.php`, `tests/Feature/DashboardTest.php`, `tests/Feature/Settings/ProfileUpdateTest.php`, `tests/Feature/Settings/SecurityTest.php`.
- Workaround: None currently applied.
- Fix approach: Uncomment `->use(RefreshDatabase::class)` in `tests/Pest.php:18` so migrations run against the `:memory:` sqlite connection configured in `phpunit.xml:26-28`. This is a one-line fix and should be applied before any further test-driven work, since CI/agents will otherwise see false failures on unrelated changes.

**SecurityController::edit does not return props its own test expects:**
- Symptoms: `tests/Feature/Settings/SecurityTest.php` asserts Inertia props `canManageTwoFactor` and `twoFactorEnabled` (and `missing('twoFactorEnabled')` / `missing('requiresConfirmation')` in the disabled-feature case), but `app/Http/Controllers/Settings/SecurityController.php:18-25` only ever returns `passwordRules`.
- Files: `app/Http/Controllers/Settings/SecurityController.php`, `tests/Feature/Settings/SecurityTest.php`.
- Trigger: Currently masked — `Features::twoFactorAuthentication()` is not enabled in `config/fortify.php`, so all three of the 2FA-specific tests call `$this->skipUnlessFortifyHas(...)` (`tests/TestCase.php:10`) and are silently skipped rather than failing.
- Impact: If two-factor authentication is ever enabled in `config/fortify.php`, the security settings page will render without the two-factor UI state the frontend (`resources/js/pages/settings/Security.vue`) and tests expect, and the masked tests will start failing.
- Fix approach: When implementing 2FA, update `SecurityController::edit` to compute `canManageTwoFactor` (via `Features::enabled(Features::twoFactorAuthentication())`) and `twoFactorEnabled` (via `$request->user()->hasEnabledTwoFactorAuthentication()` from `Laravel\Fortify\TwoFactorAuthenticatable`, if that trait is added to `App\Models\User`) before shipping the feature. `App\Models\User` currently declares `two_factor_secret`/`two_factor_recovery_codes`/`two_factor_confirmed_at` in its PHPDoc but does not `use TwoFactorAuthenticatable`, so two-factor auth is not actually wired up end-to-end yet.

## Tech Debt

**Product application code does not exist yet:**
- Issue: The entire domain layer implied by the untracked `demo/` mockups (a queue-management / business-display product — see `demo/index.html`, `demo/queue-display.html`, `demo/main.js`) has no corresponding Laravel implementation. `app/Models/` contains only `User.php`; `database/migrations/` contains only the three default Laravel tables.
- Files: `app/Models/User.php`, `database/migrations/*`, `demo/*`.
- Impact: Not a "bug" but a scoping concern — any codebase map or plan produced today reflects a bare starter kit, not the target product. Re-run codebase mapping after the first domain migrations/models land.
- Fix approach: N/A — tracked here for awareness when planning the next phase of work.

**Untracked, large reference assets living in repo root:**
- Files: `demo/index.html` (13,043 lines / 681KB), `demo/main.js` (8,659 lines / 306KB), `demo/queue-display.html` (763 lines / 21KB), `demo/style.css` (2,181 lines / 45KB), `demo/business_logo.png`, `demo/logo.png` — combined ~1.3MB, all untracked per `git status`.
- Impact: These appear to be a static HTML/CSS/JS mockup of the intended product (queue display / business dashboard), not part of the Laravel/Vue app. Because they're untracked, they won't be committed unless explicitly added, but their presence in the project root (rather than e.g. `.planning/` or a `design/` reference folder) risks accidental inclusion in `git add -A` workflows, and their scale (24k+ lines) makes them expensive to load into context.
- Fix approach: If these are reference-only mockups, move them outside the repo root (e.g. `.planning/reference/`) or add `demo/` to `.gitignore`. If they are meant to be ported into the Vue app, treat them as the source-of-truth spec for upcoming phases rather than shippable code.

**Empty/placeholder registration methods:**
- Files: `app/Providers/FortifyServiceProvider.php:21-24` (`register()` is empty), `routes/console.php` (only the default `inspire` Artisan command).
- Impact: None currently; flagged only because empty scaffolding can mask missing wiring later (e.g. forgetting to register a real service).

**Unusual/uncommon dev dependencies present without documented purpose:**
- Files: `composer.json:14` (`laravel/chisel: ^0.1.0`, a production dependency) and `composer.json:25` (`laravel/pao: ^1.0.6`, dev dependency).
- Impact: Both are early-stage/low-adoption Laravel ecosystem packages. `laravel/chisel` is a production dependency, so if it turns out to be unused it adds unnecessary attack surface and update burden.
- Fix approach: Confirm active usage before extending; remove if unused once real feature work clarifies whether they're needed.

## Security Considerations

**`.env` present with a live `APP_KEY` in a working directory that also contains `.mcp.json` pointing at absolute local paths:**
- Risk: `.env` correctly appears in `.gitignore:15` and is not tracked by git (verified via `git ls-files` and `git log --diff-filter=A -- .env`), so no secret leakage into version control currently exists. `APP_DEBUG=true` (`.env:4`) is appropriate for local dev but must not ship to production.
- Files: `.env`, `.env.example`.
- Current mitigation: `.gitignore` excludes `.env*` variants (`.env`, `.env.backup`, `.env.production`).
- Recommendation: Add an explicit production-readiness check (e.g. CI assertion that `APP_DEBUG=false` and `APP_ENV=production` in deployed environments) once a deployment pipeline exists — none exists yet in this repo.

**Login rate limiting is IP+username based only:**
- Files: `app/Providers/FortifyServiceProvider.php:73-77`.
- Current mitigation: `RateLimiter::for('login', ...)` limits to 5 attempts/minute per `username|ip` key — reasonable default from the starter kit.
- Recommendation: No change needed now; note if requirements call for stricter brute-force protection (e.g. account lockout) as the app matures.

**Password update route relies on `throttle:6,1` but security page requires fresh password confirmation via `RequirePassword` middleware:**
- Files: `routes/settings.php:18-24`.
- Current mitigation: `RequirePassword::class` middleware on `GET settings/security` correctly gates access to sensitive settings.
- No action needed; documented for completeness since this pattern should be replicated for any future sensitive-settings routes.

## Test Coverage Gaps

**Two-factor authentication has zero executing test coverage:**
- What's not tested: All three 2FA-specific assertions in `tests/Feature/Settings/SecurityTest.php` (lines 8-61) are skipped because `Features::twoFactorAuthentication()` is not enabled by default in `config/fortify.php`.
- Files: `tests/Feature/Settings/SecurityTest.php`, `config/fortify.php`, `app/Models/User.php` (missing `TwoFactorAuthenticatable` trait).
- Risk: The prop mismatch documented above (`canManageTwoFactor`/`twoFactorEnabled` not returned by the controller) will ship undetected if 2FA is enabled without re-verifying these tests actually pass (not skip).
- Priority: Medium — only relevant once 2FA is turned on, but should be resolved before that happens.

**No domain-level test coverage exists (by design, at this stage):**
- What's not tested: Nothing beyond auth/settings scaffolding, since no domain code exists yet.
- Files: `tests/Feature/`, `tests/Unit/ExampleTest.php` (default Pest example, untouched).
- Priority: Low — expected for a starter-kit-stage repo; re-assess once domain models/controllers land.

## Fragile Areas

**Test suite database wiring (`tests/Pest.php`) is a single point of failure for all feature tests:**
- Files: `tests/Pest.php:17-19`.
- Why fragile: A single commented-out trait use silently downgrades the entire feature suite from "tests real behavior" to "tests fail with unrelated SQL errors," which is easy to misdiagnose as a broken environment rather than a one-line config issue.
- Safe modification: Restore `->use(RefreshDatabase::class)`; verify with `php artisan test --compact` that failures drop to 0 unrelated-to-real-behavior errors before making further test changes.

---

*Concerns audit: 2026-08-31*
