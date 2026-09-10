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
