---
phase: 08-expenses-reporting
plan: 04
subsystem: reporting
tags: [laravel, dompdf, blade, pdf, audit, openspout, blocked]

# Dependency graph
requires:
  - phase: 08-expenses-reporting
    plan: 03
    provides: "ReportRegistry (entitlement), ReportBuilder (rows()/summary(), uncapped)"
  - phase: 07-accounts-receivable
    provides: "CollectionLetterController::show(), AccountsReceivableAgingBracket::letterBody()"
provides:
  - "AuditLogger::recordReportExport() -- D-02's non-model-driven export audit write"
  - "resources/views/reports/layout.blade.php -- shared A4/DejaVu Sans PDF shell (page-numbered footer via dompdf's isPhpEnabled/page_text, since no CSS-only page-counter exists in the installed dompdf version)"
  - "ReportExportController::exportPdf -- entitlement-gated PDF export for all five report keys, audited before rendering"
  - "CollectionLetterController::pdf() -- D-03's bounded PDF extension, show()/CollectionLetter.vue untouched"
  - "barryvdh/laravel-dompdf ^3.1 (resolved v3.1.2) installed and verified working"
affects: [08-05-reports-ui]

# Tech tracking
tech-stack:
  added: ["barryvdh/laravel-dompdf ^3.1 (resolved v3.1.2, dompdf/dompdf v3.1.6)"]
  patterns:
    - "dompdf page-number footer via a <script type=\"text/php\"> block + Pdf::setOption('isPhpEnabled', true) scoped per-instance (never global config) -- verified against the installed dompdf v3.1.6 source (Canvas::page_text, PhpEvaluator, Options::isPhpEnabled), since this dompdf version has no CSS Paged Media @bottom-center/counter(page) margin-box support"
    - "resources/views/reports/layout.blade.php's Range/Generated-by meta lines wrapped in @isset so non-report PDFs (the collection letter) can extend the same shell without synthesizing fake range data"
    - "Every report/letter Blade interpolation uses {{ }}; addslashes() specifically on the two strings interpolated into the PHP-eval footer script, since that text becomes eval()'d PHP source, not just HTML"

key-files:
  created:
    - resources/views/reports/layout.blade.php
    - resources/views/reports/sales.blade.php
    - resources/views/reports/cancellations.blade.php
    - resources/views/reports/production-status.blade.php
    - resources/views/reports/expenses.blade.php
    - resources/views/reports/financial-summary.blade.php
    - resources/views/reports/collection-letter.blade.php
    - app/Http/Controllers/Reports/ReportExportController.php
    - tests/Feature/AccountingStaff/CollectionLetterPdfTest.php
  modified:
    - app/Support/AuditLogger.php
    - app/Http/Controllers/AccountingStaff/CollectionLetterController.php
    - resources/js/pages/accounting-staff/AccountsReceivable/Show.vue
    - routes/owner.php
    - routes/portals.php
    - composer.json
    - composer.lock
    - .planning/phases/08-expenses-reporting/deferred-items.md

key-decisions:
  - "dompdf page numbering uses the isPhpEnabled/<script type=\"text/php\"> mechanism (Canvas::page_text with {PAGE_NUM}/{PAGE_COUNT} substitution), scoped per PDF instance via ->setOption(), never dompdf.php's global config -- confirmed via source read that this dompdf version has no CSS-only page-counter alternative"
  - "CollectionLetterController::pdf() adds a THIRD guard beyond show()'s two verbatim-copied lines: abort_if(bracket === Current, 404). A PDF has no equivalent of show()'s 200-with-pastDue-false screen state, and AccountsReceivableAgingBracket::Current->letterBody() throws by design, so an uncaught LogicException (500) would result without this guard. Documented explicitly since the plan's action text described only two copied lines but its own acceptance test required the Current-bracket 404."
  - "Task 2 (openspout/openspout xlsx export) NOT executed -- composer require fails because ext-zip is unavailable for PHP 8.4 in this sandboxed environment (no apt package, no root/sudo, phpize/php-config toolchain mismatched). Forcing with --ignore-platform-reqs was rejected per the plan's explicit instruction not to force platform-incompatible installs; it would also not actually work, since OpenSpout\\Writer\\XLSX\\Writer calls PHP's ZipArchive at runtime regardless of what Composer's platform check allows. Composer auto-reverted composer.json/composer.lock on failure -- no dirty state."

patterns-established:
  - "Export controllers call AuditLogger::recordReportExport()/AuditLogger's export method before any byte of the download response is built, matching recordAuthEvent()'s direct-AuditLog::create() shape"

requirements-completed: []

# Metrics
duration: 20min
completed: 2026-09-10
---

# Phase 8 Plan 4: PDF Export Pipeline + Collection Letter PDF Summary (PARTIAL -- Task 2 blocked)

**Every role-scoped report downloads as a real, audited PDF via barryvdh/laravel-dompdf, and the collection letter gained a bounded "Download Letter (PDF)" action -- but Excel export (openspout, Task 2) could not be installed in this environment because PHP 8.4's `ext-zip` extension is unavailable here, with no apt package or root access to add it.**

## Performance

- **Duration:** ~20 min (investigation of the ext-zip blocker included)
- **Started:** 2026-09-10T01:15:00Z (approx.)
- **Completed:** 2026-09-10T01:42:00Z
- **Tasks:** 2 of 3 completed (Task 1, Task 3); Task 2 blocked, not attempted beyond the failed `composer require`
- **Files modified:** 18 (across the two completed tasks)

## Accomplishments
- `barryvdh/laravel-dompdf` ^3.1 installed (resolved v3.1.2, `dompdf/dompdf` v3.1.6), confirmed via `composer show` before any facade code was written
- `AuditLogger::recordReportExport()` -- D-02's one deliberate non-model-driven audit write, matching `recordAuthEvent()`'s exact shape
- `resources/views/reports/layout.blade.php` -- the app's first non-mail Blade layout: A4/18mm margins, DejaVu Sans, a letterhead + document title + optional range/generated-by meta lines, and a page-numbered footer (verified against the installed dompdf source, since this version has no CSS-only page-counter)
- Five report Blade views (`sales`, `cancellations`, `production-status`, `expenses`, `financial-summary`), every interpolation escaped via `{{ }}` (verified: zero `{!!` occurrences)
- `ReportExportController::exportPdf` -- entitlement-gated identically to `ReportController::index()`, audits before rendering, calls `ReportBuilder` uncapped
- `reports.export.pdf` wired into the owner-only `role:owner` group and the cashier/production_staff/accounting_staff groups -- confirmed absent from `role:owner,admin`
- `CollectionLetterController::pdf()` -- D-03's bounded extension: show()'s two guards copied verbatim, plus a third Current-bracket guard; `show()` and `CollectionLetter.vue` left completely untouched (verified via `git diff --stat`)
- "Download Letter (PDF)" button added to `AccountsReceivable/Show.vue` under the same `v-if` as the existing print button
- 5 new feature tests for the collection letter PDF, including the Current-bracket 404-vs-200 divergence from `show()`
- Manually smoke-tested every PDF view (sales with rows, sales empty, financial-summary, production-status with a null due date, collection-letter) via `Pdf::...->setWarnings(true)->render()` before committing, since `ext-zip`'s absence made it worth over-verifying the dompdf half rather than assuming it also had an environment gap

## Task Commits

Each completed task was committed atomically:

1. **Task 1: Install dompdf + PDF export pipeline** - `0ff1172` (feat)
3. **Task 3: Collection letter PDF extension (D-03)** - `131a810` (feat)

**Task 2: Install openspout + Excel export + export route + export tests** -- NOT executed. `composer require openspout/openspout:^5.0` failed dependency resolution (`ext-zip` missing); Composer automatically reverted `composer.json`/`composer.lock`, so there is no partial/dirty state to commit or revert.

**Plan metadata:** (this commit)

## Files Created/Modified
- `app/Support/AuditLogger.php` - `recordReportExport()`
- `resources/views/reports/layout.blade.php` - shared PDF shell, range/generated-by meta lines made `@isset`-conditional
- `resources/views/reports/{sales,cancellations,production-status,expenses,financial-summary}.blade.php` - one table view per report key
- `resources/views/reports/collection-letter.blade.php` - the demand-letter PDF content
- `app/Http/Controllers/Reports/ReportExportController.php` - `exportPdf()` (Task 1); `exportXlsx()` NOT added (Task 2 blocked)
- `app/Http/Controllers/AccountingStaff/CollectionLetterController.php` - `pdf()` action added, `show()` untouched
- `resources/js/pages/accounting-staff/AccountsReceivable/Show.vue` - "Download Letter (PDF)" button
- `routes/owner.php` - `owner.reports.export.pdf` in the owner-only group
- `routes/portals.php` - `{role}.reports.export.pdf` for cashier/production_staff/accounting_staff, plus `accounting-staff.accounts-receivable.collection-letter.pdf`
- `tests/Feature/AccountingStaff/CollectionLetterPdfTest.php` - 5 tests
- `composer.json`/`composer.lock` - `barryvdh/laravel-dompdf` added
- `.planning/phases/08-expenses-reporting/deferred-items.md` - full investigation record of the `ext-zip` blocker

## Decisions Made
- dompdf's page-number footer uses `<script type="text/php">` + `Canvas::page_text()` (via `{PAGE_NUM}`/`{PAGE_COUNT}` string substitution), enabled per-PDF-instance with `->setOption('isPhpEnabled', true)` rather than the app-wide `config/dompdf.php` default -- verified against the installed dompdf v3.1.6 source (`Options::$isPhpEnabled` defaults `false`; `Css/Stylesheet.php`'s `@page` parser has no `@bottom-center`/margin-box support to fall back on).
- `CollectionLetterController::pdf()` adds a guard beyond the plan's literal "copy two lines verbatim" instruction: `abort_if($bracket === AccountsReceivableAgingBracket::Current, 404)`. Without it, a Current-bracket entry would reach `$bracket->letterBody()`, which throws `LogicException` by design (uncaught -> 500), and the plan's own acceptance test explicitly requires a 404 for this case. Documented as a clarification, not a scope change.
- Task 2 (openspout Excel export) deliberately not attempted beyond the failed `composer require` -- see Deviations/Issues below. No exportXlsx code was written, since writing untested code against a class (`OpenSpout\Writer\XLSX\Writer`) that cannot load in this environment would violate the plan's own TDD gate (RED/GREEN must both be real) and this project's testing-best-practices skill ("the tests must pass").

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] layout.blade.php's range/generated-by meta lines made conditional**
- **Found during:** Task 3 (writing collection-letter.blade.php)
- **Issue:** Task 1's `layout.blade.php` unconditionally rendered `$from`/`$to`/`$generatedAt`/`$generatedBy`. The collection letter (Task 3) extends the same layout per the plan's own artifact description ("the shared shell every report PDF and the collection letter PDF extend") but has no date-range concept -- rendering it unconditionally would force synthesizing meaningless range data for a single-entry demand letter.
- **Fix:** Wrapped both meta lines in `@isset($from, $to)` / `@isset($generatedAt, $generatedBy)`. No behavior change for the five report views, which always pass all four variables.
- **Files modified:** resources/views/reports/layout.blade.php
- **Verification:** Manual `Pdf::loadView(...)->render()` smoke test for both a report view (with range) and collection-letter.blade.php (without), both rendered without warnings.
- **Committed in:** 131a810 (Task 3 commit)

**2. [Rule 2 - Missing Critical] CollectionLetterController::pdf() guards the Current aging bracket**
- **Found during:** Task 3, while implementing the guards
- **Issue:** The plan's `<action>` text describes copying only `show()`'s two guard lines verbatim, but its own acceptance test requires a Current-bracket entry to 404 on the pdf route. Without an explicit guard, `pdf()` would call `$bracket->letterBody()` unconditionally (unlike `show()`, which ternary-guards it on `$pastDue`), throwing an uncaught `LogicException` (500) for a Current entry instead of a clean 404.
- **Fix:** Added `abort_if($bracket === AccountsReceivableAgingBracket::Current, 404);` as a third guard, after computing `agingBracket()`.
- **Files modified:** app/Http/Controllers/AccountingStaff/CollectionLetterController.php
- **Verification:** `tests/Feature/AccountingStaff/CollectionLetterPdfTest.php`'s Current-bracket test asserts 404 on `pdf` and 200 on `show()` for the same entry.
- **Committed in:** 131a810 (Task 3 commit)

---

**Total deviations:** 2 auto-fixed (1 blocking-fix, 1 missing-critical-guard). Both necessary for correctness; no scope creep.

## Issues Encountered

**Task 2 could not be executed: `ext-zip` PHP extension unavailable for PHP 8.4 in this environment.**

`composer require openspout/openspout:^5.0 --no-interaction` fails Composer's dependency resolution because `openspout/openspout` requires `ext-zip`, which is not loaded for this environment's PHP 8.4 CLI. Full investigation (see `.planning/phases/08-expenses-reporting/deferred-items.md` for the complete record):

- No `zip.so` exists for PHP 8.4's extension API (`20240924`); only PHP 7.4 and 8.2's extension directories have one.
- `apt-cache search php8.4` lists only 16 packages, none of them `php8.4-zip` or `php8.4-dev`; `apt-get install php8.4-zip --dry-run` confirms the package isn't locatable in this sandbox's apt cache.
- `phpize`/`php-config` on `PATH` resolve to the PHP 8.2 toolchain, not 8.4, so building the extension from PECL source isn't viable without the right headers either.
- No `sudo`/root access is available in this environment.

Per this plan's explicit constraint ("if a package's stable release does not support Laravel 13, STOP and report rather than forcing it with `--ignore-platform-reqs`"), this was not forced through. Forcing it would also not have worked functionally: `OpenSpout\Writer\XLSX\Writer` calls PHP's `ZipArchive` class at runtime to build the `.xlsx` container regardless of what Composer's platform check allows, so every export would fatal-error even if the package were installed.

Composer automatically reverted `composer.json`/`composer.lock` to their pre-attempt state on failure -- there is no dirty dependency state left behind.

**This is very likely a sandbox-only gap.** `ext-zip` is a near-universal PHP extension and is expected to already be present on Laravel Cloud (the project's intended host) and on any standard PHP 8.4 install. Once a host with `ext-zip` loaded is available, Task 2 (`composer require openspout/openspout:^5.0`, `ReportExportController::exportXlsx`, the `reports.export.xlsx` routes in `routes/owner.php`/`routes/portals.php`, and `tests/Feature/Reports/ReportExportTest.php`) can proceed exactly as specified in `08-04-PLAN.md`.

## User Setup Required

**Action needed to unblock Task 2:** install a PHP extension providing `ZipArchive` that loads under this environment's PHP 8.4 CLI (extension API `20240924`) -- e.g. `sudo apt-get install php8.4-zip` if/when that package becomes available in this sandbox's apt sources, or build it via PECL once `php8.4-dev` headers and a matching `phpize`/`php-config` toolchain are present. Verify with `php -m | grep -i zip` before re-running `composer require openspout/openspout:^5.0 --no-interaction`.

## Next Phase Readiness
- PDF export (`ReportExportController::exportPdf`) and the collection letter PDF are fully functional, tested, and ready for Plan 08-05's UI to link to.
- **RPT-05 is NOT fully satisfied** -- only the PDF half is done. `requirements-completed` is deliberately left empty in this summary's frontmatter; do not mark RPT-05 complete in `REQUIREMENTS.md` until Task 2 lands.
- Plan 08-05 (Reports UI) can proceed for the on-screen report views and PDF export buttons, but must either stub/hide the "Export Excel" button or accept that it 404s/is absent until Task 2 completes -- flag this explicitly to whoever picks up 08-05.
- `resources/views/reports/layout.blade.php`'s `@isset`-conditional range/generated-by lines and the `isPhpEnabled` per-instance pattern are now the established conventions for any future PDF view in this app.

---
*Phase: 08-expenses-reporting*
*Completed: 2026-09-10 (partial -- Task 2 of 3 blocked by environment)*

## Self-Check: PASSED

All 9 created files verified present on disk. Both task commit hashes (`0ff1172`, `131a810`) verified present in `git log`. Test suite after this plan's changes: 490 tests, 487 passed, 3 pre-existing skips, 0 failures (baseline was 485/482/3 before this plan).
