# Deferred Items — Phase 08 Expenses & Reporting

Out-of-scope issues discovered during execution. Not fixed per deviation-rules scope boundary (only auto-fix issues directly caused by the current task's changes).

## Pre-existing Larastan (level 7) failures — not touched by Plan 08-01

`composer types:check` reports 7 pre-existing errors in files unrelated to Plan 08-01's Expense work:

- `app/Http/Requests/Cashier/CreateCreditRequestRequest.php:40` — `Access to an undefined property (object|string)::$total_amount.`
- `app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php:42,64,68,69` — undefined property/method access on `(object|string)`
- `app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php:20` — `Access to an undefined property (object|string)::$type.`
- `app/Mail/AccountsReceivableReminder.php:125` — `Match expression does not handle remaining value: App\Enums\AccountsReceivableCollectionStatus::Cancelled`

None of these files are in Plan 08-01's `files_modified` list and none were modified by this plan. Verified pre-existing by confirming the errors reference classes/lines with no relationship to `Expense`/`ExpenseValidationRules`/`ExpenseController`.
