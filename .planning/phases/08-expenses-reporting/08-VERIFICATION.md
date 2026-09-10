---
phase: 08-expenses-reporting
verified: 2026-09-10T12:00:00Z
status: passed
score: 11/11 must-haves verified
overrides_applied: 0
---

# Phase 8: Expenses & Reporting Verification Report

**Phase Goal:** Every role can see the numbers that matter to them — expenses get logged and every module's data rolls up into exportable, role-scoped reports — completing the operational picture now that every upstream module produces real data.
**Verified:** 2026-09-10
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Accounting Staff can record an expense with a category, amount, and date (EXP-01) | ✓ VERIFIED | `app/Http/Controllers/AccountingStaff/ExpenseController.php::store()` creates via `StoreExpenseRequest`; `ExpenseValidationRules::expenseRules()` requires `category` (allowlisted via `SystemConfiguration::getArray('expense_categories')`), `amount` (`gt:0`), `expense_date` (`before_or_equal:today`). UI dialog in `resources/js/pages/accounting-staff/Expenses/Index.vue`. `tests/Feature/AccountingStaff/ExpenseTest.php` passes (part of the 38/38-non-skipped-passing Expense+Reports test run). |
| 2 | Accounting Staff can edit a non-voided expense; editing a voided one is blocked (D-11) | ✓ VERIFIED | `ExpenseController::update()` — `abort_if($expense->voided_at !== null, 422, ...)` guard confirmed in code, at least 2 occurrences (update + void) per plan's acceptance criteria. |
| 3 | Accounting Staff can void an expense with a mandatory reason; row survives, excluded from sums, stays visible | ✓ VERIFIED | `ExpenseController::void()` uses `forceFill(['voided_at'=>now(),'void_reason'=>...])->save()`; `Expense::scopeActive()` (`whereNull('voided_at')`) is the single exclusion point, used by `index()`'s `total`, `ReportBuilder::summary()`'s `expenses_total`, and `ReportExportController::tabularXlsxRows()`'s Total row (after the CR-01 fix). |
| 4 | Every expense create/edit/void is captured in `audit_trail` via `AuditObserver`, zero bespoke audit code | ✓ VERIFIED | `Expense` model carries `#[ObservedBy(AuditObserver::class)]`; no bespoke audit call exists in `ExpenseController`. |
| 5 | Only Accounting Staff can create/edit/void expenses; every other role is blocked (D-12) | ✓ VERIFIED | `routes/portals.php`: `expenses.*` routes sit inside `Route::middleware(['auth','role:accounting_staff'])->prefix('accounting-staff')` group only. Cross-role 403 asserted in `ExpenseTest.php`. |
| 6 | Owner can view all 5 reports; every other role is entitlement-limited server-side, including via direct URL query param (D-04) | ✓ VERIFIED | `ReportRegistry::definitions()` declares all 5 keys with explicit `roles` arrays; `ReportController::index()` calls `abort_unless(ReportRegistry::isEntitled($user,$key), 403)` before any query, independent of which role-scoped route was hit. Verified in code and by passing cross-role 403 tests in `CashierReportsTest`, `ProductionStatusReportTest`, `AccountingReportsTest`, `FinancialReportTest` (Admin 403 on `owner.reports.index`). |
| 7 | Cashier: Sales + Cancellations only; Production Staff: Production Status only; Accounting Staff: Sales, Expenses, Summary of Sales & Expenses only | ✓ VERIFIED | `ReportRegistry::definitions()['roles']` matches exactly: sales→[Owner,Cashier,AccountingStaff]; cancellations→[Owner,Cashier]; production-status→[Owner,ProductionStaff]; expenses→[Owner,AccountingStaff]; financial-summary→[Owner,AccountingStaff]. Confirmed by passing `reports` prop assertions in each role's test file. |
| 8 | Revenue computed from `confirmed_at` at `TransactionStatus::Completed`, never `created_at` | ✓ VERIFIED | `ReportBuilder.php` — every sales/cancellation/financial-summary query filters `where('status', TransactionStatus::Completed->value)->whereBetween('confirmed_at', [...])`, orders by `confirmed_at`. `FinancialReportTest` has an explicit Pitfall-1 regression test for a transaction confirmed outside the requested range. |
| 9 | Financial/profit report splits revenue (job sales vs cancellation fees), discloses write-offs separately, does NOT subtract write-offs from profit (D-09/D-10) | ✓ VERIFIED | `ReportBuilder::summary()` computes `job_sales`, `cancellation_fees`, `revenue_total`, `expenses_total`, `result = revenue_total - expenses_total`, `write_off_total` (via `JobOrder::outstandingBalance()`, never re-derived) as a separate, undeducted figure. `financial-summary.blade.php` and `ReportsWorkspace.vue` render the write-off `Alert` only when `writeOffTotal > 0`, distinct from `result`. |
| 10 | RPT-04's Daily/Monthly variants are the same registry entry ranged differently, not four separate report types (D-06) | ✓ VERIFIED | `AccountingReportsTest.php` asserts the same `sales` query ranged to a single day vs. a full month returns the day's subset — one registry entry, `ReportController`/`ReportExportController` share the identical range-resolution logic. |
| 11 | Any role-scoped report can be exported to PDF or Excel, both entitlement-gated identically to on-screen, both audit-logged before streaming begins, exports never capped at 100 | ✓ VERIFIED | `ReportExportController::exportPdf()`/`exportXlsx()` both call `abort_unless(ReportRegistry::isEntitled(...))` before any query and `AuditLogger::recordReportExport()` before building `$data`/streaming. `openToFile('php://output')` used (never `openToBrowser()`, grep confirms 0 occurrences). `ReportExportTest.php` asserts exactly one new `audit_trail` row per export, zero on 403, `Content-Type`/`Content-Disposition` correctness, and the CR-01 regression (voided expense excluded from xlsx Total row). |

**Score:** 11/11 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Models/Expense.php` | Expense model with `#[Fillable]`, `#[ObservedBy]`, `scopeActive()` | ✓ VERIFIED | Present, 69 lines, all three elements confirmed by direct read. |
| `app/Http/Controllers/AccountingStaff/ExpenseController.php` | index/store/update/void | ✓ VERIFIED | Present, 112 lines, all four actions confirmed, guard logic correct. |
| `database/migrations/2026_09_10_090000_create_expenses_table.php` | expenses table schema | ✓ VERIFIED | Present; migrated successfully as part of `php artisan test` run (full suite green). |
| `app/Services/Reports/ReportRegistry.php` | definitions()/isEntitled()/entitledFor() | ✓ VERIFIED | Present, 88 lines, all 5 report keys, entitlement logic confirmed. |
| `app/Services/Reports/ReportBuilder.php` | rows()/summary() shared query implementation | ✓ VERIFIED | Present, 203 lines, `confirmed_at`/`outstandingBalance()` usage confirmed. |
| `app/Http/Controllers/Reports/ReportController.php` | index() resolving entitlement + role-specific render | ✓ VERIFIED | Present, 71 lines, entitlement check before query, correct component match. |
| `app/Http/Controllers/Reports/ReportExportController.php` | exportPdf/exportXlsx, entitlement + audit-gated | ✓ VERIFIED | Present, 256 lines. CR-01 fix (voided-row exclusion from Total) present and tested. |
| `resources/views/reports/layout.blade.php` | shared A4/DejaVu Sans shell | ✓ VERIFIED | Present, 102 lines, escaping confirmed (`grep -rc '{!!' resources/views/reports/` → 0). |
| `app/Support/AuditLogger.php::recordReportExport()` | one-off, non-model-driven audit write | ✓ VERIFIED | Present; calls `AuditLog::create()` only, matches append-only invariant. |
| `resources/js/components/reports/DateRangeControl.vue` | shared preset/custom date-range control | ✓ VERIFIED | Present, 206 lines, reused unmodified by both Expenses page and ReportsWorkspace. |
| `resources/js/components/reports/ReportsWorkspace.vue` | shared master/detail Reports UI | ✓ VERIFIED | Present, 747 lines; zero role-conditional logic (`grep -ic role` → 0); export buttons are plain `<a>` anchors, no `target="_blank"`, no toast/click handler. |
| `resources/js/pages/{owner,cashier,production-staff,accounting-staff}/Reports.vue` | thin per-role wrappers | ✓ VERIFIED | All 4 present, 0 `v-if` each, mount `<ReportsWorkspace v-bind="$props" ...>` with role-specific Wayfinder URLs. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `ExpenseValidationRules` | `SystemConfiguration::getArray` | `Rule::in(SystemConfiguration::getArray('expense_categories', []))` | ✓ WIRED | Confirmed present in `app/Concerns/ExpenseValidationRules.php:21`. |
| `ExpenseController::void` | `Expense` model | `forceFill(['voided_at'=>now(), 'void_reason'=>...])->save()` | ✓ WIRED | Confirmed. |
| `routes/owner.php` | `EnsureUserHasRole` middleware | second, owner-only `['auth','role:owner']` group, never `role:owner,admin` | ✓ WIRED | Confirmed by direct read of `routes/owner.php` — `reports.index`, `reports.export.pdf`, `reports.export.xlsx` all sit in the dedicated `role:owner` block (lines 34-38), separate from the `role:owner,admin` block above it. |
| `ReportBuilder` | `Transaction` model | `whereBetween('confirmed_at', [...])` | ✓ WIRED | Confirmed at multiple points in `ReportBuilder.php`. |
| `ReportExportController` | `AuditLogger::recordReportExport` | called before `Pdf::loadView()->download()` / before `streamDownload()` begins | ✓ WIRED | Confirmed — audit call precedes `$data` build (PDF) and `$sheetRows` build (xlsx) in both methods. |
| `ReportExportController::exportXlsx` | `OpenSpout\Writer\XLSX\Writer` | `openToFile('php://output')` inside `streamDownload()`, never `openToBrowser()` | ✓ WIRED | Confirmed; `grep -c "openToBrowser"` → 0, `openToFile('php://output')` present. |
| `owner/Reports.vue` | `ReportsWorkspace.vue` | `<ReportsWorkspace v-bind="props" ...>` | ✓ WIRED | Confirmed, and mirrored in the other 3 wrapper pages. |
| `ReportsWorkspace.vue` | `DateRangeControl.vue` | imported and mounted unmodified from Plan 08-02 | ✓ WIRED | Confirmed via import + template usage. |

### Route Verification (live `route:list`)

12 report routes confirmed live: `{owner,cashier,production-staff,accounting-staff}.reports.index`, `.reports.export.pdf`, `.reports.export.xlsx` — exactly 4 index + 4 pdf + 4 xlsx, one set per entitled role, matching plan acceptance criteria.

### Behavioral Spot-Checks / Test Execution

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Full regression suite | `php artisan test --compact` | 500 tests, 493 passed, 7 skipped, 0 failures | ✓ PASS (matches documented baseline — 3 pre-existing skips + 4 zip-gated skips, expected on this environment) |
| Phase-08 test files directly | `vendor/bin/pest tests/Feature/AccountingStaff/ExpenseTest.php tests/Feature/Reports/ tests/Feature/AccountingStaff/CollectionLetterPdfTest.php` | 42 tests, 38 passed, 4 skipped (zip-gated), 0 failed | ✓ PASS |
| Pint formatting on phase-08 files | `vendor/bin/pint --test --format agent` | 6 unrelated pre-existing files flagged (tests/Feature/Auth/*, AccountsReceivableListTest, ExampleTest x2, LoginResponse.php) — zero phase-08 files flagged | ✓ PASS (no phase-08 regressions) |
| Composer packages resolved | `composer show barryvdh/laravel-dompdf` / `composer show openspout/openspout` | v3.1.2 / v5.11.3 | ✓ PASS |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|----------|
| EXP-01 | 08-01, 08-02 | Accounting Staff can record an expense with category/amount/date | ✓ SATISFIED | Full create/edit/void cycle exists, tested, role-scoped. |
| RPT-01 | 08-03, 08-05 | Owner can view financial/profit reports | ✓ SATISFIED | `financial-summary` report entitled to Owner; UI renders figure block. |
| RPT-02 | 08-03, 08-05 | Cashier can view Daily Sales & Cancellation reports | ✓ SATISFIED | `sales`/`cancellations` entitled to Cashier; D-06 confirms daily/monthly are the same ranged query. |
| RPT-03 | 08-03, 08-05 | Production Staff can view a Production Status report | ✓ SATISFIED | `production-status` entitled solely to Owner+ProductionStaff. |
| RPT-04 | 08-03, 08-05 | Accounting Staff can view Daily/Monthly Sales, Daily/Monthly Expenses, Summary of Sales & Expenses | ✓ SATISFIED | `sales`/`expenses`/`financial-summary` entitled to Accounting Staff; range-based Daily/Monthly equivalence tested. |
| RPT-05 | 08-04, 08-05 | Any role-scoped report can be exported to PDF or Excel | ✓ SATISFIED | Both export formats implemented, entitlement-gated identically to on-screen, audit-logged, tested. |

**Note on REQUIREMENTS.md Traceability table:** The bottom "Traceability" table lists `RPT-01 through RPT-05 | Phase 8 | Pending`, which contradicts the per-requirement checkboxes above it (all marked `[x]` Complete). This is a pre-existing, unmaintained convention — every other completed phase (1 through 7) shows the identical "Pending" status in that same table despite being long-delivered and checkbox-complete. Not a phase-08-introduced defect; flagged as informational only, not a gap.

### Anti-Patterns Found

No debt markers (TBD/FIXME/XXX/TODO/HACK) found in any phase-08 file. The only `placeholder` matches in `Expenses/Index.vue` are HTML input `placeholder` attributes (UI hint text), not stub/debt markers — correctly excluded per the stub-detection guidance (values flow to real form submission, not hollow rendering).

No hardcoded empty-data anti-patterns found in wired components — `rows`/`summary`/`reports` all originate from live Eloquent queries traced end-to-end (`ReportBuilder` → `ReportController`/`ReportExportController` → Inertia props → Vue template rendering, no static fallback).

### Known Open Advisories (from 08-REVIEW.md, consciously left open, not gaps)

- **WR-01** (Warning): Report/export date ranges have no maximum span — potential unbounded memory use on a mature dataset. Confirmed still open in `FilterReportRequest.php` (only `before_or_equal`/`after_or_equal` validation, no ceiling). Left as a design decision for the user per review disposition.
- **WR-02** (Warning): Editing an expense becomes impossible once its category is removed from `expense_categories` config, because `UpdateExpenseRequest` reuses the live-list `Rule::in(...)`. Confirmed still open in `ExpenseValidationRules.php`. Left as a design decision for the user.
- **WR-03** (Warning): `NavItem.roles` client-side filter is the only thing hiding "Reports" from Admin in the shared Owner/Admin portal — cosmetic only, backed by a real server-side `role:owner`-only route (confirmed: `FinancialReportTest` asserts Admin 403 on direct URL). Confirmed pattern present in `owner.ts`/`AppSidebar.vue`. Left as a design decision for the user.
- **CR-01** (Critical): FIXED — confirmed fixed in commit `1979eb2`, with a regression test (`tests/Feature/Reports/ReportExportTest.php` — "the xlsx expenses Total row excludes voided expenses").

### Human Verification Required

None outstanding. Plan 08-05 Task 3's blocking human-verify checkpoint (full cross-role browser walkthrough of Reports access, both export formats, and the collection letter PDF) was completed and the human responded "approved" (confirmed in `08-05-SUMMARY.md`: "Task 3 checkpoint: human ran the full cross-role/export verification and responded 'approved'"). Per this verifier's operating instructions, that checkpoint's outcome is treated as human-verified, not outstanding — no `human_needed` items remain.

### Gaps Summary

None. All 11 derived observable truths (roadmap Success Criteria expanded into testable behaviors) are verified against actual, running code — not SUMMARY.md claims. The full regression suite (500 tests, 493 passed, 7 skipped as expected, 0 failures) confirms no regressions. The one Critical finding from code review (CR-01) was fixed and regression-tested. The three open Warnings are consciously accepted design deviations, not defects, per the review's own disposition and the user-approved environmental facts for this verification. `audit_trail` append-only and `transactions.job_order_id` NOT NULL invariants both hold. Entitlement is enforced identically and server-side on the on-screen read path and both export paths, for all 5 report keys, verified in both source code and passing automated cross-role tests.

---

_Verified: 2026-09-10_
_Verifier: Claude (gsd-verifier)_
