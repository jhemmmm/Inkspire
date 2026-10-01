---
status: complete
---

# Quick Task 261001-er9: Report export parity — one row definition feeds PDF and Excel — Summary

One-liner: Added `App\Support\TableExport` (one `pdf()`/`xlsx()` method pair sharing a single row-shaping contract) and a generic `reports/table.blade.php` view, collapsed `ReportExportController`'s duplicated `xlsxRowValues()`/per-report Blade formatting onto that one contract via a new `exportRowValues()` resolved once for both formats, and deleted the four now-redundant per-report Blade views — so Production Status's Urgency (and every other label) can no longer show "Rush" in PDF but "TRUE" in Excel.

## What changed, per task

**Task 1 — `TableExport` support class + generic `table.blade.php` view:**

- `app/Support/TableExport.php` (new): `final class TableExport` with no instance state (same static-utility shape as `App\Support\AuditLogger`). `pdf(string $title, array $headings, array $rows, array $moneyColumns, ?array $meta, ?array $totalRow, string $filename): Response` merges `$meta` into `reports.layout`'s existing view-data contract and calls `Pdf::loadView('reports.table', $data)`. `xlsx(...): StreamedResponse` moves the existing `response()->streamDownload()` / openspout `Writer`/`Row` block here verbatim (`openToFile('php://output')` inside the closure, never the writer's browser-direct method), running every row (and `$totalRow`, if present) through a new private `xlsxCells()` helper: `null` passes through, a `CarbonInterface` becomes `->toDateString()`, a `$moneyColumns` index is cast `(float)`, everything else passes through unchanged.
- `resources/views/reports/table.blade.php` (new): `@extends('reports.layout')`, same `@if (empty($rows))` empty-state wrapper the four deleted views used. Renders `$headings`/`$rows` generically — `null` → em dash, `CarbonInterface` → `format('M j, Y')`, a `$moneyColumns` index → `₱`+`number_format`, byte-for-byte matching the four deleted views' formatting. A non-null `$totalRow` renders as a final `<strong>`-wrapped `<tr>` (new PDF behavior — no per-report PDF view had a Total row before; `xlsx` already did).

**Task 2 — Collapse `ReportExportController` onto `TableExport`; delete the four per-report Blade views:**

- `exportRowValues(string $reportKey, array $row): array` (replaces `xlsxRowValues()`) now resolves every display label ONCE, for both `exportPdf()` and `exportXlsx()`: `Str::headline()` for `type`/`method`/`payment_status`/`stage`, `Carbon::parse($row['date'])` for sales/cancellations/expenses dates, `$row['urgency'] ? 'Rush' : 'Normal'`, `$row['status'] === 'Voided' ? 'Voided' : 'Active'` (the cell Excel used to leave blank). `production-status`'s `entered_production`/`due` stay `CarbonInterface`/`null` — `TableExport` formats them per-format.
- `moneyColumnIndexes()` (renamed from `xlsxMoneyColumnIndexes()`) and a new `buildTableRows()` (replaces `tabularXlsxRows()`) build the row set AND a `$totalRow` that now feeds **both** `exportPdf()` and `exportXlsx()` — every tabular report (including `production-status`, which has no money columns) now shows a Total row in both formats, not just xlsx.
- `financialSummaryRows()` (replaces `financialSummaryXlsxRows()`) returns the same body rows minus the `['Label', 'Amount']` header, since `TableExport::xlsx()` now supplies that header via `$headings`.
- `exportPdf()`/`exportXlsx()` rewritten: `financial-summary` keeps its own untouched `Pdf::loadView('reports.financial-summary', ...)` / direct `TableExport::xlsx($title, ['Label', 'Amount'], ...)` call; every other report key goes through `buildTableRows()` → `TableExport::pdf()`/`TableExport::xlsx()`.
- Deleted `xlsxRowValues()`, `tabularXlsxRows()`, `financialSummaryXlsxRows()`, `xlsxMoneyColumnIndexes()`, `xlsxDate()`. Removed the now-unused `OpenSpout\*` imports (moved to `TableExport.php`); kept `Symfony\Component\HttpFoundation\{Response,StreamedResponse}` since `exportPdf()`/`exportXlsx()` still declare those return types.
- Deleted `resources/views/reports/{sales,cancellations,production-status,expenses}.blade.php` — grep-confirmed before deletion that nothing besides this controller's dynamic `"reports.{$reportKey}"` view name ever referenced them. `financial-summary.blade.php`, `collection-letter.blade.php`, `layout.blade.php` untouched.

**Task 3 — Extended `ReportExportTest.php` for export parity:**

- 5 new tests: sales xlsx (`Down Payment`/`Gcash` labels), cancellations xlsx (`Partially Paid`), production-status xlsx (`Rush`+`Normal` urgency, `For Production` stage — not `TRUE`/`FALSE`/`for_production`), expenses xlsx (`Active` status, previously blank), and a PDF smoke test for `production-status` (now rendered via `reports/table.blade.php` instead of the deleted dedicated view).
- Added `use App\Enums\{JobOrderStatus,PaymentMethod,PaymentStatus,TransactionType}; use App\Models\ProductionLog;` to the existing `use` block.

## Files changed

- `app/Support/TableExport.php` — new, one `pdf()`/`xlsx()` pair + `xlsxCells()` helper
- `resources/views/reports/table.blade.php` — new, generic tabular report view
- `app/Http/Controllers/Reports/ReportExportController.php` — collapsed onto `TableExport`
- `resources/views/reports/sales.blade.php` — deleted
- `resources/views/reports/cancellations.blade.php` — deleted
- `resources/views/reports/production-status.blade.php` — deleted
- `resources/views/reports/expenses.blade.php` — deleted
- `tests/Feature/Reports/ReportExportTest.php` — 5 new export-parity tests + a 1-line test-isolation fix (see Deviations)

## Commits

1. `db97302` — `feat(reports): add TableExport support class and generic table view`
2. `d2ef081` — `refactor(reports): collapse export controller onto TableExport`
3. `7b9db1c` — `test(reports): assert export parity for sales, cancellations, production-status, expenses`

## Verification results

**`php artisan test --compact tests/Feature/Reports/ReportExportTest.php`** (Task 2 gate, before Task 3's new tests): 9 passed, 1 failed — the pre-existing "100 rows" test (see Deviation 2 below; this was true on the unmodified `git show HEAD`-restored original code too, confirmed by direct comparison). After Task 3's fix + new tests: **15 passed, 61 assertions, 0 failed.**

**`php artisan test --compact tests/Feature/Reports`** (full directory, required by this task's constraints):

```
{"tool":"pest","result":"failed","tests":38,"passed":37,"assertions":135,"duration_ms":2336,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Reports\\AccountingReportsTest::__pest_evaluable_the_same_sales_report_ranged_to_a_single_day_vs__a_full_month_returns_the_days_subset__not_a_separate_report_type__D_06_","file":"/home/user/inkspire/tests/Feature/Reports/AccountingReportsTest.php","line":89,"message":"Failed asserting that actual size 1 matches expected size 2."}]}
```

This is **not** a passing run — reporting it plainly per the task's constraints. 1 failure, in a file this task never touches (`AccountingReportsTest.php`, testing `ReportController`/`ReportBuilder`, both explicitly out of scope per this task's `<scope_guard>`). It is a date-boundary flaky test: it anchors a second transaction at `now()->subDays(3)` and asserts it's still inside "start of month to today" — true every day except the 1st–3rd of a month. Today is 2026-10-01, so `subDays(3)` lands in September, outside the October 1st start-of-month boundary. Confirmed pre-existing by restoring the original (pre-Task-1) `ReportExportController.php` + Blade views via `git show HEAD:...` and re-running the same test: identical failure. Logged in `.planning/quick/261001-er9-report-export-parity-one-row-definition-/deferred-items.md`; not fixed here (would require editing `AccountingReportsTest.php`'s fixture dates, a file outside this task's mandate).

**`tests/Feature/Reports/ReportExportTest.php` alone — the file this task actually owns — is 15/15 green.**

**`vendor/bin/pint --dirty --format agent`** — `{"tool":"pint","result":"passed"}` (ran against uncommitted changes at each task boundary; after final commit, re-verified explicitly against all 4 touched PHP/Blade files via `pint --test --format agent` — also passed).

**`composer types:check`** (Larastan level 7):

```
{"tool":"phpstan","result":"failed","errors":21, ...}
```

Also not a clean pass — reporting plainly. All 21 errors are in files this task never touches (`DashboardController.php`, `FrontlineStaff/JobOrderController.php`, `FrontlineStaff/QueueEntryController.php`, `ReportController.php`, `UpdateSystemConfigurationRequest.php`, `CreateCreditRequestRequest.php`, `SavePricingAndPaymentRequest.php`, `ReportBuilder.php`, `SpecificationOptionFactory.php`, `DatabaseSeeder.php`, `DemoDataSeeder.php`). Confirmed pre-existing: the baseline run before any edit (before Task 1) already reported 22 errors including one inside `ReportExportController.php` itself (`tabularXlsxRows()`'s `list<...>` return-type inference). This task's refactor net-**decreased** the count to 21 by fixing that exact pre-existing error (see Deviation 1) — `TableExport.php` and the rewritten `ReportExportController.php` both report **zero** Larastan errors.

## Decisions Made

- `TableExport`'s `xlsx()`/`pdf()` accept `$title`/`$meta` for signature parity even where unused (no title/range row exists in the xlsx sheet today) — matches the plan's explicit contract rather than diverging signatures per format.
- Kept `use Symfony\Component\HttpFoundation\StreamedResponse;` in `ReportExportController.php` (the plan's text said to remove it "once nothing here references them") — `exportXlsx()`'s own method signature still declares `: StreamedResponse`, so the import is genuinely still used; only the `OpenSpout\*` imports were removed.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] Fixed a pre-existing PHPStan `list<mixed>` inference failure carried into the refactor**

- **Found during:** Task 2, `composer types:check` verification.
- **Issue:** `buildTableRows()`'s `$totalRow` construction (`array_fill(0, count($columns), null)` followed by `$totalRow[0] = 'Total'; $totalRow[$index] = $totals[$index];` for money indexes) made PHPStan lose list-ness tracking, reporting `non-empty-array<int, 'Total'|float|null>` instead of the declared `list<mixed>` return shape. This is the exact same failure mode the pre-existing (pre-Task-1) `tabularXlsxRows()` already had in the codebase's Larastan baseline (22 errors, one of them this one) — carried forward unchanged into the new method by a literal translation of the plan's described logic.
- **Fix:** Rebuilt `$totalRow` via `array_map()` over `array_keys($columns)` instead of indexed mutation — PHPStan correctly infers `list<mixed>` for `array_map` applied to a list input. No behavior change (same values at the same indexes); no `@phpstan-ignore` or type-cast suppression used.
- **Files modified:** `app/Http/Controllers/Reports/ReportExportController.php`.
- **Verification:** `composer types:check` — `ReportExportController.php` error count dropped from 1 (pre-existing) to 0; total baseline errors dropped from 22 to 21.
- **Committed in:** `d2ef081` (Task 2 commit).

**2. [Rule 1 — Bug] Fixed a header leak in the existing "100 rows" xlsx test**

- **Found during:** Task 2 verification (`php artisan test --compact tests/Feature/Reports/ReportExportTest.php`) — 1 of 10 tests failed with `Expected response status code [200] but received 302`, even against the untouched original controller/views (confirmed via `git show HEAD:...` restoration before concluding this was pre-existing, not a regression from this task).
- **Issue:** `withHeaders(reportExportHeaders())` sets Laravel's TestCase `$defaultHeaders`, which **persists for every subsequent request in the same test method** — not just the call it's chained to. The test calls it once for the on-screen preview (`cashier.reports.index`, an Inertia page), then makes a second, unrelated `get()` call for the xlsx export with no `withHeaders()` of its own — but the `X-Inertia: true` header from the first call silently carried over. Inertia's `Middleware::handle()` sees that leaked header on the export request, checks `$response->isOk() && empty($response->getContent())`, finds true for an unsent `StreamedResponse` (content isn't buffered — it streams lazily via the `response()->streamDownload()` closure), and calls `onEmptyResponse()` → `Redirect::back()`, turning a legitimate 200 file download into a 302. A real browser never sends `X-Inertia` on a plain export link click, so this never happens outside this specific two-calls-in-one-test pattern.
- **Fix:** Changed `->withHeaders(reportExportHeaders())->get(route(...))` to `->get(route(...), reportExportHeaders())` for the on-screen-preview call only — Laravel's `get($uri, $headers)` applies `$headers` to that single call without mutating `$defaultHeaders`, so the header no longer leaks into the xlsx request that follows.
- **Files modified:** `tests/Feature/Reports/ReportExportTest.php`.
- **Verification:** Re-ran the test in isolation (and the full file) — passed, 0 regressions, no assertion content changed.
- **Committed in:** `7b9db1c` (Task 3 commit).

---

**Total deviations:** 2 auto-fixed (both Rule 1 — pre-existing bugs surfaced by this task's own verification steps, both confined to files this task was already modifying).
**Impact on plan:** Both fixes were necessary to satisfy the plan's own verification gates ("existing tests must still pass", Larastan clean on touched files) without weakening any assertion or touching any out-of-scope file. No scope creep.

## Issues Encountered

- One out-of-scope, unrelated, date-boundary flaky test (`AccountingReportsTest.php`) fails in the full `tests/Feature/Reports` run today (2026-10-01, the 1st of the month) — see Verification results above and `deferred-items.md`. Not fixed; outside this task's `<scope_guard>` (would require touching `ReportController`/`ReportBuilder`'s test file, a file this task's locked scope explicitly forbids modifying).

## Known Stubs

None — no hardcoded empty/placeholder values introduced. Every cell `TableExport` and `reports/table.blade.php` render comes from `ReportBuilder::rows()`'s real query results via `exportRowValues()`.

## Next Phase Readiness

- `TableExport` is the one place PDF/Excel formatting for any future tabular report should extend — any new report key added to `ReportRegistry` gets both formats automatically correct as long as `exportRowValues()` resolves its labels once.
- Flagged but not actioned: the `AccountingReportsTest.php` date-boundary flakiness (see Deferred Items) will keep intermittently failing CI runs on the 1st–3rd of any month until fixed in a future task that's actually scoped to touch it.

## Self-Check

```
FOUND: app/Support/TableExport.php
FOUND: resources/views/reports/table.blade.php
FOUND: app/Http/Controllers/Reports/ReportExportController.php
MISSING (intentionally deleted): resources/views/reports/sales.blade.php
MISSING (intentionally deleted): resources/views/reports/cancellations.blade.php
MISSING (intentionally deleted): resources/views/reports/production-status.blade.php
MISSING (intentionally deleted): resources/views/reports/expenses.blade.php
FOUND: tests/Feature/Reports/ReportExportTest.php
FOUND commit: db97302
FOUND commit: d2ef081
FOUND commit: 7b9db1c
```

## Self-Check: PASSED
