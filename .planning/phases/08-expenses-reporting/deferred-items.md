# Deferred Items — Phase 08 Expenses & Reporting

Out-of-scope issues discovered during execution. Not fixed per deviation-rules scope boundary (only auto-fix issues directly caused by the current task's changes).

## Pre-existing Larastan (level 7) failures — not touched by Plan 08-01

`composer types:check` reports 7 pre-existing errors in files unrelated to Plan 08-01's Expense work:

- `app/Http/Requests/Cashier/CreateCreditRequestRequest.php:40` — `Access to an undefined property (object|string)::$total_amount.`
- `app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php:42,64,68,69` — undefined property/method access on `(object|string)`
- `app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php:20` — `Access to an undefined property (object|string)::$type.`
- `app/Mail/AccountsReceivableReminder.php:125` — `Match expression does not handle remaining value: App\Enums\AccountsReceivableCollectionStatus::Cancelled`

None of these files are in Plan 08-01's `files_modified` list and none were modified by this plan. Verified pre-existing by confirming the errors reference classes/lines with no relationship to `Expense`/`ExpenseValidationRules`/`ExpenseController`.

## `composer types:check` fails to even start (Plan 08-03, environment-level, not code-related)

`composer types:check` (→ `phpstan analyse`) now fails with a fatal bootstrap error before analysing any file:

```
PHP Warning:  PHP Startup: Unable to load dynamic library '.../vendor/phpstan/phpstan/turbo-ext/linux-gnu-x86_64/phpstan_turbo-8.4.so' (... GLIBC_2.33 not found ...)
In LarastanStubFilesExtension.php line 25:
Undefined constant "Larastan\Larastan\LARAVEL_VERSION"
```

This is a `phpstan-turbo` native-extension/GLIBC mismatch in the current container image, which appears to leave Larastan's `bootstrap.php` unable to fully boot the Kernel and define `LARAVEL_VERSION` before `LarastanStubFilesExtension` reads it. Confirmed NOT caused by Plan 08-03's changes:

- `php artisan --version` boots the full framework successfully (`Laravel Framework 13.30.1`).
- `vendor/bin/pint --dirty --format agent` passes cleanly on every file this plan touched.
- `php artisan optimize:clear` (clearing config/route/view caches) does not resolve it — same failure before and after.
- The failure occurs during PHPStan's own process bootstrap, before it reaches any application file, so it cannot be a consequence of any of this plan's PHP changes.

Per this plan's `<critical_environment_constraint>`, `composer install`/`require`/`update` are explicitly prohibited for Plan 08-03 (no new Composer packages are added by this plan). Repairing the `phpstan-turbo` native extension or the Larastan install would require a `composer` operation, so this is left unresolved and logged here rather than auto-fixed. Verification for this plan instead relied on `php -l` syntax checks, `vendor/bin/pint --dirty --format agent`, and the full Pest suite.

## `ext-zip` PHP extension unavailable for PHP 8.4 in this environment — BLOCKS Plan 08-04 Task 2 (openspout/openspout)

`composer require openspout/openspout:^5.0` fails dependency resolution:

```
Problem 1
  - Root composer.json requires openspout/openspout ^5.0 -> satisfiable by openspout/openspout[v5.0.0, ..., v5.11.3].
  - openspout/openspout[v5.0.0, ..., v5.11.3] require ext-zip * -> it is missing from your system. Install or enable PHP's zip extension.
```

Investigated and confirmed this is a genuine environment gap, not fixable within this plan's permissions:

- `php -m | grep -i zip` returns nothing — the `zip` extension is not loaded for the PHP 8.4 CLI binary (`/usr/bin/php8.4`, extension API `20240924`).
- `/usr/lib/php/20240924/` (PHP 8.4's extension directory) has no `zip.so`. Only the PHP 7.4 (`20190902`) and PHP 8.2 (`20220829`) extension directories have one.
- `apt-cache search php8.4` returns only 16 packages (bcmath, cli, common, curl, gd, gmp, igbinary, imagick, mbstring, mysql, opcache, phpdbg, readline, redis, sqlite3, xml) — no `php8.4-zip`, no `php8.4-dev`. This is a curated/limited package cache in this sandbox image, not a live repo mirror; `apt-get install php8.4-zip --dry-run` confirms `E: Unable to locate package php8.4-zip`.
- Building the extension from source via PECL is not viable either: `phpize`/`php-config` on `PATH` resolve to the PHP 8.2 toolchain (`/usr/bin/phpize8.2` via `/etc/alternatives/phpize`), not PHP 8.4, and `php8.4-dev` (needed headers) isn't installed.
- No `sudo`/root access is available (`sudo -n true` fails with "a password is required"), so none of the above can be installed even if a source were found.

Per this plan's explicit instruction ("if a package's stable release does not support Laravel 13, STOP and report rather than forcing it with `--ignore-platform-reqs`"), and because forcing the install with `--ignore-platform-reqs=ext-zip` would only mask the problem at the Composer layer — `OpenSpout\Writer\XLSX\Writer` genuinely calls PHP's `ZipArchive` at runtime to build the `.xlsx` ZIP container, so the class would still be missing and every export would fatal-error — this was **not** forced through. `composer.json`/`composer.lock` are unchanged (Composer reverted the failed attempt automatically).

**To unblock:** install a `zip` extension that loads under PHP 8.4's `20240924` extension API on this host (e.g. `sudo apt-get install php8.4-zip` once that package is available in this environment's apt sources, or `sudo pecl install zip` with matching `php8.4-dev` headers), then re-run `composer require openspout/openspound:^5.0 --no-interaction` and continue Plan 08-04 Task 2 (`ReportExportController::exportXlsx`, the `reports.export.xlsx` routes, and `tests/Feature/Reports/ReportExportTest.php`). This is very likely a sandbox-only gap — `ext-zip` is a near-universal PHP extension and is expected to already be present on Laravel Cloud.
