---
phase: 08-expenses-reporting
reviewed: 2026-09-10T00:00:00Z
depth: standard
files_reviewed: 49
files_reviewed_list:
    - app/Concerns/ExpenseValidationRules.php
    - app/Http/Controllers/AccountingStaff/CollectionLetterController.php
    - app/Http/Controllers/AccountingStaff/ExpenseController.php
    - app/Http/Controllers/Owner/WriteOffApprovalController.php
    - app/Http/Controllers/Reports/ReportController.php
    - app/Http/Controllers/Reports/ReportExportController.php
    - app/Http/Requests/AccountingStaff/FilterExpensesRequest.php
    - app/Http/Requests/AccountingStaff/StoreExpenseRequest.php
    - app/Http/Requests/AccountingStaff/UpdateExpenseRequest.php
    - app/Http/Requests/AccountingStaff/VoidExpenseRequest.php
    - app/Http/Requests/Reports/FilterReportRequest.php
    - app/Models/AccountsReceivable.php
    - app/Models/Expense.php
    - app/Services/Reports/ReportBuilder.php
    - app/Services/Reports/ReportRegistry.php
    - app/Support/AuditLogger.php
    - database/factories/ExpenseFactory.php
    - database/migrations/2026_09_10_090000_create_expenses_table.php
    - database/migrations/2026_09_10_091000_add_written_off_at_to_accounts_receivable_table.php
    - database/seeders/DemoDataSeeder.php
    - resources/js/components/AppSidebar.vue
    - resources/js/components/reports/DateRangeControl.vue
    - resources/js/components/reports/ReportsWorkspace.vue
    - resources/js/config/nav/accounting-staff.ts
    - resources/js/config/nav/cashier.ts
    - resources/js/config/nav/owner.ts
    - resources/js/config/nav/production-staff.ts
    - resources/js/pages/accounting-staff/AccountsReceivable/Show.vue
    - resources/js/pages/accounting-staff/Expenses/Index.vue
    - resources/js/pages/accounting-staff/Reports.vue
    - resources/js/pages/cashier/Reports.vue
    - resources/js/pages/owner/Reports.vue
    - resources/js/pages/production-staff/Reports.vue
    - resources/js/types/navigation.ts
    - resources/views/reports/cancellations.blade.php
    - resources/views/reports/collection-letter.blade.php
    - resources/views/reports/expenses.blade.php
    - resources/views/reports/financial-summary.blade.php
    - resources/views/reports/layout.blade.php
    - resources/views/reports/production-status.blade.php
    - resources/views/reports/sales.blade.php
    - routes/owner.php
    - routes/portals.php
    - tests/Feature/AccountingStaff/CollectionLetterPdfTest.php
    - tests/Feature/AccountingStaff/ExpenseTest.php
    - tests/Feature/Reports/AccountingReportsTest.php
    - tests/Feature/Reports/CashierReportsTest.php
    - tests/Feature/Reports/FinancialReportTest.php
    - tests/Feature/Reports/ProductionStatusReportTest.php
    - tests/Feature/Reports/ReportExportTest.php
    - tests/TestCase.php
findings:
    critical: 1
    warning: 3
    info: 1
    total: 5
status: issues_found
---

# Phase 08: Code Review Report

**Reviewed:** 2026-09-10
**Depth:** standard
**Files Reviewed:** 49
**Status:** issues_found

## Summary

Phase 08 (Expenses + Reporting) is a well-structured, deliberately-documented piece of work. The entitlement boundary (`ReportRegistry::isEntitled()`) is checked identically and unconditionally on every read and export path (`ReportController::index()`, `ReportExportController::exportPdf()`/`exportXlsx()`), before any query is built and before any byte streams — confirmed correct by direct code reading and by feature tests that assert 403 + zero audit rows for every cross-role/direct-URL combination tried. The `audit_trail` append-only invariant holds: `AuditLogger::recordReportExport()` only ever calls `AuditLog::create()`, matching every other method on that class, and there is no update/delete path against `AuditLog` anywhere in the reviewed files. `transactions.job_order_id` is set on every `Transaction::factory()->create()` call in `DemoDataSeeder`, including cancellation-fee and write-off transactions — no standalone sale is created. `DemoDataSeeder` is not wired into `DatabaseSeeder::run()` or the test suite. All five report Blade views and the collection letter escape every interpolation via `{{ }}` — zero `{!! !!}` usage found; the one raw-PHP surface (`layout.blade.php`'s dompdf page-number footer `<script type="text/php">` block) only interpolates `config('app.name')` and a hardcoded report title, neither of which is user-controlled, so the `addslashes()` there is defense-in-depth rather than a gap.

One genuine correctness bug was found in the Excel export pipeline: the auto-generated "Total" row for the Expenses report's `.xlsx` export sums voided expense amounts, contradicting the `Expense::active()` convention this phase establishes and applies correctly everywhere else (the on-screen ledger total, the Financial Summary's `expenses_total`). Three further issues are worth fixing but do not block correctness at today's usage scale: an unbounded report date range, a category-rename edit-lock on historical expenses, and a client-side-only nav filter layered onto the pre-existing Admin/Owner shared-portal deviation.

## Critical Issues

### CR-01: Expenses .xlsx export's Total row sums voided expense amounts

**File:** `app/Http/Controllers/Reports/ReportExportController.php:147-174` (specifically the `foreach ($rows as $row)` totals loop, lines 155-162), consuming `app/Services/Reports/ReportBuilder.php:186-202` (`expensesRows()`, which deliberately returns voided rows so they stay visible per D-11).

**Issue:** `ReportBuilder::expensesRows()` intentionally includes voided expenses in its row set (each tagged `'status' => 'Voided'`) so they remain visible in the ledger, matching `Expense::active()`'s design: voided rows are shown everywhere but summed nowhere. `ReportExportController::tabularXlsxRows()` is the shared xlsx-row-building method used by every non-`financial-summary` report key; it builds the Total row generically by summing every row's money-column value (`$totals[$index] += (float) ($values[$index] ?? 0)`), with no awareness of the `expenses` report's void concept. As a result, downloading the Expenses report to Excel produces a Total row that includes voided expense amounts — inconsistent with:

- `ExpenseController::index()`'s `total` prop (uses `Expense::query()->...->active()->sum('amount')`)
- `ReportBuilder::summary()`'s `expenses_total` figure (uses `Expense::query()->active()->...->sum('amount')`)
- The phase's own stated invariant (08-01-SUMMARY.md: "`Expense::active()` scope excluding voided rows from every sum")

Concrete failure scenario: an Accounting Staff member records a ₱5,000 expense by mistake, voids it with a reason, then downloads the Expenses report as Excel for that range. The sheet correctly shows the voided row with a "Voided" status cell, but the Total row at the bottom silently includes the ₱5,000 — overstating total expenses in a document that may be handed to the Owner or used for bookkeeping, with no on-screen equivalent to cross-check against (the on-screen `ReportsWorkspace.vue` table renders no total/sum row for `expenses` at all). This is untested: `tests/Feature/Reports/ReportExportTest.php` never exercises a voided expense through the xlsx export path.

**Fix:** Skip voided expense rows when accumulating the money totals (the row itself must still be written to the sheet):

```php
foreach ($rows as $row) {
    $values = $this->xlsxRowValues($reportKey, $row);
    $sheetRows[] = $values;

    if ($reportKey === 'expenses' && ($row['status'] ?? null) === 'Voided') {
        continue;
    }

    foreach ($moneyIndexes as $index) {
        $totals[$index] += (float) ($values[$index] ?? 0);
    }
}
```

Add a regression test asserting the Total row excludes a voided expense's amount, mirroring `AccountingReportsTest`'s existing on-screen voided-expense assertion.

## Warnings

### WR-01: Report and export date ranges have no upper bound, allowing an unbounded uncapped query/export

**File:** `app/Http/Requests/Reports/FilterReportRequest.php:31-37`, consumed by `app/Http/Controllers/Reports/ReportExportController.php:33-39,78-84` and `app/Http/Controllers/Reports/ReportController.php:27-33`

**Issue:** `from`/`to` are validated only for format and `before_or_equal:today` / `after_or_equal:from` — there is no minimum `from` bound or maximum span. Any entitled, authenticated role can request `from=1970-01-01&to=<today>` on `reports.export.xlsx` (or `.pdf`), and `ReportExportController` calls `ReportBuilder::rows()`/`summary()` uncapped, materializing the entire matching history into memory (`->all()` on the returned Collection, then a second in-memory `$sheetRows` array) before streaming. On a mature installation with years of transactions/expenses this is a real single-request memory/CPU spike from an authenticated-but-otherwise-ordinary staff account, not just a hypothetical.

**Fix:** Add a maximum span check to `FilterReportRequest` (and its duplicate, `FilterExpensesRequest`, for consistency), e.g. a custom rule or `after_or_equal`-style check rejecting a range wider than some project-chosen ceiling (a year, e.g.), with a clear validation message. Alternatively, cap `rows()`/`summary()` with a hard ceiling and surface a "range too wide, narrow it" error before querying.

### WR-02: Editing an expense becomes impossible once its category is removed from `expense_categories` config

**File:** `app/Http/Requests/AccountingStaff/UpdateExpenseRequest.php:29-32` (via `app/Concerns/ExpenseValidationRules.php:18-26`), consumed by `app/Http/Controllers/AccountingStaff/ExpenseController.php:82-91`

**Issue:** `UpdateExpenseRequest::rules()` reuses the exact same `expenseRules()` as `StoreExpenseRequest`, including `'category' => [..., Rule::in(SystemConfiguration::getArray('expense_categories', []))]`, which validates against the _current_ live category list. If the Owner later removes a category from System Configuration, every existing expense recorded under that category becomes permanently un-editable through the normal edit flow: submitting the edit form (even just to fix a typo in the description or correct the amount) fails validation on `category`, because the stored/unchanged category value is no longer in the allowed list. The frontend compounds this — `Expenses/Index.vue`'s Edit dialog (`openEditDialog()`) seeds `editCategory.value = row.category`, but the `<SelectItem v-for="category in categories">` options only list the _current_ categories, so the removed category isn't even selectable in the UI; the user is stuck unable to save any correction without first guessing which current category to reassign it to. `ExpenseTest.php`'s "historical expense keeps its stored category string" test only covers the read path (`index()`), not this edit-path regression.

**Fix:** Either (a) allow the update rule to accept the expense's own current category in addition to the live list (`Rule::in([...currentCategories, $expense->category])`), or (b) explicitly disable the category field on edit once it's out of the current allowlist and surface that state in the UI, rather than letting the form fail silently with a generic validation error.

### WR-03: Client-side `NavItem.roles` filter is the only thing hiding "Reports" from Admin in the shared Owner/Admin portal

**File:** `resources/js/components/AppSidebar.vue:35-41`, `resources/js/types/navigation.ts:9-22`, `resources/js/config/nav/owner.ts:57-66`

**Issue:** Not a security gap — the backend route (`routes/owner.php:34-38`) is in a dedicated `role:owner` group, never `role:owner,admin`, and `FinancialReportTest.php` confirms Admin gets a 403 hitting the route directly. But this phase's fix for D-05 ("Admin has no Reports nav item") is a purely presentational, client-evaluated `roles?: string[]` filter bolted onto a nav array that both Owner and Admin already share via `UserRole::portalRoute()`. This is a reasonable, minimal patch for a deviation that predates this phase (Admin and Owner sharing one portal instead of each role getting its own dedicated portal, per `PROJECT.md`'s RBAC constraint), but it does establish a general-purpose pattern (`NavItem.roles`) that a future feature could reach for to hide a _security-sensitive_ nav item with no corresponding server-side check, trusting the filter alone. Today's one usage is backed by real server enforcement; the pattern itself carries no such guarantee.

**Fix:** No code change required today. Recommend a code comment or `.ai/rules` entry (there is currently no `.ai/rules` directory in this repo) making explicit that `NavItem.roles` is cosmetic-only and every item using it must have an equivalent server-side route/entitlement check — so the next person reaching for this field doesn't assume it's a security boundary.

## Info

### IN-01: `letterBody()` placeholder substitution duplicated between `show()` (pre-existing) and `pdf()` (new)

**File:** `app/Http/Controllers/AccountingStaff/CollectionLetterController.php:87-91`

**Issue:** `pdf()` performs its own `str_replace(['{due date}', '{n}'], ...)` substitution on `$bracket->letterBody()`, which is new in this phase. `show()` (untouched, pre-existing) hands the raw `{due date}`/`{n}` template string to the Vue page and presumably substitutes client-side. This isn't a bug — the two rendering surfaces (PDF vs. Vue) reasonably need to do this differently — but it is a second implementation of the same substitution logic with no shared source of truth, so a future change to the placeholder tokens must be updated in two places (one PHP, one Vue) that aren't visibly linked.

**Fix:** No action required now; worth a comment cross-referencing the two substitution sites, or extracting the token list into one shared constant (e.g. on `AccountsReceivableAgingBracket`) if a third consumer appears.

---

_Reviewed: 2026-09-10_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
