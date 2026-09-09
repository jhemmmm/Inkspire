---
phase: 8
slug: expenses-reporting
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-09-10
---

# Phase 8 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.
> Derived from `08-RESEARCH.md` § Validation Architecture.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 5.1.3 (`pestphp/pest`) + `pestphp/pest-plugin-laravel` 5.0.1 |
| **Config file** | `phpunit.xml` / `tests/Pest.php` |
| **Quick run command** | `php artisan test --compact --filter=Expense` (or `--filter=Report`) |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~60 seconds (full suite, SQLite in-memory) |

No framework install needed — Pest is already configured and used by every prior phase.

---

## Sampling Rate

- **After every task commit:** `php artisan test --compact --filter={Feature}` (narrowest slice covering the just-written behavior)
- **After every plan wave:** `php artisan test --compact` (full suite)
- **Before `/gsd-verify-work`:** Full suite green, plus `vendor/bin/pint --dirty --format agent` and `composer types:check` — this phase adds two new Composer packages whose facades/return types must survive Larastan level 7
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

*Task IDs are assigned during planning. The requirement→behavior→command mapping below is fixed by research; the planner fills in Plan/Wave/Task ID and Threat Ref columns.*

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| TBD | TBD | TBD | EXP-01 | — | Accounting Staff creates an expense with category/amount/date; row persists | feature | `php artisan test --compact --filter="expense can be recorded"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | EXP-01 (D-11) | — | Editing an expense writes an `updated` audit_trail row with old/new values | feature | `php artisan test --compact --filter="editing an expense is audited"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | EXP-01 (D-11) | T-void | Voiding sets `voided_at`/reason, row survives, excluded from every report sum | feature | `php artisan test --compact --filter="voided expense excluded"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | EXP-01 (D-12) | T-access | Owner cannot create/edit/void an expense (403); Owner CAN read the itemized list | feature | `php artisan test --compact --filter="owner cannot record an expense"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | EXP-01 (D-14) | — | Category options reflect `system_configurations.expense_categories`; removing one does not hide historical rows | feature | `php artisan test --compact --filter="expense category options"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | RPT-01 | — | Owner financial/profit report splits job sales vs cancellation fees; write-off disclosure line is not subtracted | feature | `php artisan test --compact --filter="financial report"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | RPT-01 (D-04/D-05) | T-access | Owner sees every report type; Admin gets 403 on the Reports route via direct URL | feature | `php artisan test --compact --filter="admin cannot view reports"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | RPT-02 | — | Cashier Daily Sales scoped to a day range; Cancellation report shows fee/no-fee split | feature | `php artisan test --compact --filter="cashier daily sales"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | RPT-02 | T-access | Cashier cannot view Owner's financial report or Accounting's expense report (403 via direct URL) | feature | `php artisan test --compact --filter="cashier cannot view financial report"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | RPT-03 | — | Production Status report derived from `production_logs` / `job_orders.status` | feature | `php artisan test --compact --filter="production status report"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | RPT-04 (D-06) | — | Sales report ranged to a day = "Daily", ranged to a month = "Monthly" (same query) | feature | `php artisan test --compact --filter="accounting sales report ranges"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | RPT-04 | — | Expenses report excludes voided rows; Summary = revenue minus non-voided expenses | feature | `php artisan test --compact --filter="summary of sales and expenses"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | RPT-05 | — | Export PDF route returns `Content-Type: application/pdf` for an entitled role | feature | `php artisan test --compact --filter="export pdf"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | RPT-05 | — | Export Excel route returns a valid `.xlsx` binary (Content-Type / Content-Disposition) | feature | `php artisan test --compact --filter="export xlsx"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | RPT-05 (D-04) | T-access | Export route denied (403) for a non-entitled role via direct URL, matching on-screen denial | feature | `php artisan test --compact --filter="export denied for wrong role"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | RPT-05 (D-02) | T-repudiation | Every export writes exactly one `audit_trail` row (who, report key, range, format); viewing writes zero | feature | `php artisan test --compact --filter="export writes audit entry"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-08 | — | Revenue totals computed from `confirmed_at`, not `created_at` (GCash txn whose timestamps span two days) | feature | `php artisan test --compact --filter="revenue uses confirmed_at"` | ❌ W0 | ⬜ pending |
| TBD | TBD | TBD | D-03 | — | Collection letter PDF reproduces `CollectionLetterController::show` 404 guards | feature | `php artisan test --compact --filter="collection letter pdf guards"` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/AccountingStaff/ExpenseTest.php` — EXP-01, D-11, D-12, D-14
- [ ] `tests/Feature/Reports/FinancialReportTest.php` — RPT-01, D-08, D-09, D-10, D-04 cross-role denial
- [ ] `tests/Feature/Reports/CashierReportsTest.php` — RPT-02
- [ ] `tests/Feature/Reports/ProductionStatusReportTest.php` — RPT-03
- [ ] `tests/Feature/Reports/AccountingReportsTest.php` — RPT-04, D-06
- [ ] `tests/Feature/Reports/ReportExportTest.php` — RPT-05, D-02
- [ ] `tests/Feature/AccountingStaff/CollectionLetterPdfTest.php` — D-03
- [ ] `database/factories/ExpenseFactory.php` — mirrors `TransactionFactory.php`, needs a `voided()` state
- [ ] No framework install required

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Exported PDF renders legibly (fonts, table layout, page breaks) at real data volume | RPT-05 | dompdf output fidelity is visual; asserting binary content in Pest proves the route works, not that the document reads well | Export each role's report with ≥50 rows seeded; open the PDF and confirm headers repeat, no clipped columns, currency formatting intact |
| Exported `.xlsx` opens without repair prompts in Excel / LibreOffice / Google Sheets | RPT-05 | Full openspout content parsing in-test is disproportionate; header assertions cover the route contract | Export an Accounting Summary report, open in each target app, confirm no "file is corrupt" dialog and numeric cells are numbers, not text |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
