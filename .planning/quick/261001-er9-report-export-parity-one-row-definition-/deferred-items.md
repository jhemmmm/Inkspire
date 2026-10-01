# Deferred Items — Quick Task 261001-er9 (Report export parity)

**Resolved 2026-10-01 in `f253aa3`:** the orchestrator anchored the test mid-month with `travelTo()`, the same way its neighbours in that file are anchored. `tests/Feature/Reports` is 38/38 green. The orchestrator also removed the bare "Total" row from Production Status exports in `9d5e30b`. The original note is kept below for the record.

Out-of-scope issue discovered during execution. Not fixed by the executor per deviation-rules scope boundary (only auto-fix issues directly caused by the current task's changes).

## Pre-existing date-boundary flaky test — `AccountingReportsTest.php` (not touched by this task)

`php artisan test --compact tests/Feature/Reports` (the full directory, run as part of this task's required verification) fails one test outside the scope of this task:

```
AccountingReportsTest::the same sales report ranged to a single day vs. a full month
returns the days subset, not a separate report type (D-06)
Failed asserting that actual size 1 matches expected size 2.
```

The test creates one transaction `confirmed_at: $today` and a second `confirmed_at: $today->copy()->subDays(3)`, then asserts a "month to date" range (`from: start of month`, `to: today`) returns both rows. On any day where `now()->subDays(3)` crosses into the previous calendar month -- the 1st, 2nd, or 3rd of the month -- the second transaction falls outside the "start of month" boundary and the assertion fails. Today is 2026-10-01, so `subDays(3)` lands on 2026-09-28, before `startOfMonth()` (2026-10-01).

Confirmed unrelated to this task:

- The test exercises `ReportController::index()` → `ReportBuilder::rows('sales', ...)`, both of which this task's `<scope_guard>` explicitly forbids touching (`ReportBuilder.php`, `ReportController.php` stay untouched).
- `tests/Feature/Reports/ReportExportTest.php` (this task's actual target) is 15/15 green.
- Restoring the pre-change `ReportExportController.php`/Blade views and re-running the same test reproduces the identical failure, confirming it predates this task's commits.

Not fixed here because fixing it would mean changing `AccountingReportsTest.php`'s fixture dates (e.g. anchoring both transactions inside the same calendar month rather than a fixed `subDays(3)` offset from "today"), which is a test-isolation defect in a file this task has no mandate to touch. Flag for a future quick task or the next time `tests/Feature/Reports` is in scope.
