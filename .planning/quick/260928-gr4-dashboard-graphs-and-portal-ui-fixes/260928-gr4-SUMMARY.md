---
phase: quick-260928-gr4
plan: 01
status: complete
date: 2026-09-28
---

# Quick Task 260928-gr4: Summary

- **Graphs.** Every Reports page now has a chart above its table. The chart is built server-side
  from the full row set, not the 100 rows the table shows. Admin and Accounting dashboards show
  revenue vs expenses for the last 14 days. Admin also shows open job orders by stage. The charts
  are plain SVG with a hover and arrow-key readout and a screen-reader table.
- **Date range.** "This Week" became a rolling "Last 7 Days" (on a Monday it covered only today), and
  "This Month" on the 1st no longer jumps back to "Today". Loading shows a spinner and a busy card. Validation, default ranges and the expense date
  use the Asia/Manila business date. Before this, midnight–8am Manila failed silently against
  UTC `today`.
- **Cursor.** A global base rule gives buttons `cursor: pointer`.
- **Receipt.** The balance is boxed and set at `text-3xl`. VATable Sales and VAT come from the new
  `vat_percentage` setting (default 12, prices VAT-inclusive, 0 hides it).
- **Artist.** Accept opens a confirmation dialog. Continue buttons have the primary fill.
- **Expenses.** Record Expense is visible again. PageHeader is `shrink-0`.
- **Pagination.** Changing page scrolls to the top.
- **New Visit.** Recent Orders shows the past 7 days, with rush orders in their own table.
- **Reports on mobile.** The workspace grid is `grid-cols-1`, so the report card no longer runs off
  a 375px screen.

## Verification

- New and updated tests: report charts (trend, monthly switch, breakdown, financial pair, stages),
  business-date filters and expense date, dashboard chart props, receipt VAT, 7-day history.
  The seeder count went from 16 to 17. The "capped at 20" history test was replaced by the 7-day
  window test.
- Affected suites: 509/510 pass. The failure is
  `ReportExportTest › xlsx export … not capped at 100 rows`, which fails the same way on `main`.
- `vue-tsc`, Pint and the changed-file lint/format pass. PHPStan reports only the 6 errors that
  already exist on `main`.
- Browser (headless Chrome against a seeded SQLite copy on :8011): preset selection and spinner,
  chart hover and keyboard readouts, accept dialog cancel and confirm, Record Expense dialog,
  receipt, New Visit tables, pagination scroll (1117 → 0), dark mode and 375px.
