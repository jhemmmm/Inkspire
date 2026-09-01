# Deferred Items — Phase 2

Pre-existing issues discovered during execution that are out of scope for the current task
(not caused by this plan's changes).

## 02-01: Larastan — `UpdateSystemConfigurationRequest::rules()` undefined property access

- **File:** `app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php:20`
- **Issue:** `Access to an undefined property (object|string)::$type.` Larastan (level 7) cannot narrow `$this->route('configuration')`'s type on the `SystemConfiguration::$type` property access.
- **Pre-existing:** Yes — this file was created in Phase 1 (commit `65c0607`, plan 01-12) and is untouched by Plan 02-01. `vendor/bin/phpstan analyse` scoped to only Plan 02-01's own new/modified files (`app/Models/Customer.php`, `app/Concerns/CustomerValidationRules.php`, `app/Http/Requests/FrontlineStaff/*`, `app/Http/Controllers/FrontlineStaff/CustomerController.php`) passes with 0 errors; the error only surfaces when running the full-project `composer types:check`.
- **Status:** Not fixed. Out of scope per plan scope-boundary rules. Revisit during a future Owner/system-configuration-touching plan or a dedicated cleanup pass.
