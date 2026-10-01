---
quick_id: 261001-i4q
phase: quick
plan: 261001-i4q
subsystem: frontend
tags: [search, filters, accounting-staff, cashier, production-staff, useTableFilter, TableFilterBar]
dependency-graph:
  requires: [261001-he2]
  provides: [client-side-search-filters-non-admin-portals]
  affects:
    - resources/js/pages/accounting-staff/AccountsReceivable/Index.vue
    - resources/js/pages/accounting-staff/Expenses/Index.vue
    - resources/js/pages/cashier/Dashboard.vue
    - resources/js/pages/production-staff/Dashboard.vue
tech-stack:
  added: []
  patterns:
    - Reused useTableFilter + TableFilterBar (from 261001-he2) as a second filter layer composed on top of each page's existing tab/section filter (AND composition)
key-files:
  created: []
  modified:
    - resources/js/pages/accounting-staff/AccountsReceivable/Index.vue
    - resources/js/pages/accounting-staff/Expenses/Index.vue
    - resources/js/pages/cashier/Dashboard.vue
    - resources/js/pages/production-staff/Dashboard.vue
decisions: []
metrics:
  duration: ~45min
  completed: 2026-10-01
---

# Quick Task 261001-i4q: Search and filters on the other-portal pages Summary

Added client-side search + a per-page filter control to the four remaining non-admin list/dashboard pages (Accounts Receivable, Expenses, Cashier Dashboard, Production Board), reusing `useTableFilter`/`TableFilterBar` exactly as Part A (`261001-he2`) established, composed as a second AND layer on top of each page's existing tab/section filter.

## What Was Built

**Task 1 — `accounting-staff/AccountsReceivable/Index.vue`:**
Search across job order number / customer / description, plus a Collection status `Select` (options derived from both `receivables` and `closedReceivables` so the list doesn't reshuffle when the Admin switches tabs). Applied on top of the existing `filteredRows` (bracket/closed tab layer) computed — `visibleRows` is the new output, driving the table body. `TableFilterBar` sits between `</Tabs>` and `<DataTableCard>`. "Clear filters" also resets `activeFilter` back to `all`. Bracket `StatCard` totals keep reading `bracketSummaries` untouched. New "No matches" `TableEmpty` added after the existing three-case empty block, with a `data-test="clear-receivable-filters-button"` Clear filters button.

**Task 2 — `accounting-staff/Expenses/Index.vue`:**
Search across description / category / recorded by, plus a Category `SearchableSelect` (configured categories from the `categories` prop, plus any category still present on a row but no longer configured) and a Status `Select` (Active / Voided, derived from `voided_at`). Applied on top of `props.rows` (the existing server-side date range is untouched; "Clear filters" never touches `DateRangeControl`). `total`/`activeCount`/`voidedCount` StatCard keeps reading server props. New "No matches" empty state added after the existing "no expenses" / "nothing in this range" block, `data-test="clear-expense-filters-button"`.

**Task 3 — `cashier/Dashboard.vue` + `production-staff/Dashboard.vue`:**
- Cashier: search across job order number / customer / description, plus a Payment status `Select` (options derived from the rows present). `paymentGroups` now filters `filteredJobOrders.value` per rush/regular section instead of `props.jobOrders` directly, while a new `baseCount` field (unfiltered per-section count) decides between the all-time-empty state (unchanged copy/no action) and the new "No matches" state (with a per-section Clear filters button, `data-test="clear-cashier-rush-filters-button"` / `clear-cashier-regular-filters-button`).
- Production: search across job order number / customer / description, plus a "Rush only" `Switch` (`data-test="production-rush-only-toggle"`). Applied on top of the existing `filteredJobOrders` (stage/rush tab layer) — `visibleJobOrders` is the new output. `stageCounts` tiles keep summing the whole board. New "No matches" empty state added after the existing tab-empty block, `data-test="clear-production-filters-button"`.

All four pages keep every existing tile/tab/section count and behavior untouched; the new filters compose as an AND on top, and each page's "Clear filters" resets the tab/toggle state too (Accounts Receivable and Production reset their tab back to "all"; Cashier and Expenses have no tab to reset).

## Deviations from Plan

None — plan executed exactly as written. One placement note: in `production-staff/Dashboard.vue`, the new script block (`rushOnly`, `useTableFilter` call, `filtersActive`, `clearFilters`) was inserted immediately after `onTabChange` rather than immediately after the `activeFilterLabel` computed (the plan's stated insertion point, with `onTabChange` sitting between them in the original file). This is a purely cosmetic ordering difference — all top-level `const`/`function` declarations in the script are independent of execution order here (no runtime difference), and `vue-tsc`/tests confirm no behavioral impact. Not logged as a Rule 1-4 deviation since no functional or architectural change occurred, only a few lines of insertion-point drift.

## Verification Performed

- `npx vp check --fix resources/js/pages/accounting-staff/AccountsReceivable/Index.vue resources/js/pages/accounting-staff/Expenses/Index.vue resources/js/pages/cashier/Dashboard.vue resources/js/pages/production-staff/Dashboard.vue` — pass, no warnings/lint errors, scoped to exactly these 4 files (not the bare `npm run check:fix`).
- `npm run types:check` (`vue-tsc --noEmit`) — clean, zero errors on the full project.
  - Note: the IDE's live diagnostics panel repeatedly flagged false-positive errors mid-edit (e.g. "Cannot find module 'vue'", "Module ... has no default export" for `.vue` SFCs, `'row' is of type 'unknown'` inside `useTableFilter` callbacks) throughout this task. These were confirmed stale/incorrect against the real `vue-tsc --noEmit` run after every task, which passed cleanly every time — the IDE's incremental TS server was lagging behind the actual file state in this session. No code changes were made in response to these phantom diagnostics.
- `php artisan test --compact tests/Feature/AccountingStaff tests/Feature/Cashier tests/Feature/ProductionStaff` — **173 passed, 975 assertions**, 0 failures. Individual task-scoped runs also passed:
  - `AccountsReceivableListTest.php` — 5 passed, 64 assertions
  - `ExpenseTest.php` — 14 passed, 41 assertions
  - `CashierDashboardPaidFilterTest.php` + `CashierPagesTest.php` + `ProductionBoardTest.php` — 31 passed, 285 assertions
- `git status` confirmed no `app/`, `routes/`, or `database/` file changed at any point — this task is 100% frontend, four `.vue` files only.

## Unverified Interactions (browser verification required)

Per the task constraints, no browser was driven in this execution. The following must be exercised in a real browser (both themes, 375px and desktop) before this task is considered closed, per CLAUDE.md's "UI Changes Require a User-Friendly Check":

**Accounts Receivable** (`resources/js/pages/accounting-staff/AccountsReceivable/Index.vue`):
- Search input: `id="receivable-collection-status-filter"` sibling search box inside `TableFilterBar` (label "Search receivables", no fixed id — generated via `useId()` inside `TableFilterBar.vue`)
- Collection status select: `#receivable-collection-status-filter`
- Clear filters button: `data-test="clear-receivable-filters-button"` (new matches-empty state) and `data-test="table-filter-clear-button"` (TableFilterBar's own, shown once any filter is active)
- Confirm: tab switch + search + collection-status filter compose (AND), Clear filters resets the bracket/closed tab back to "All" too, bracket `StatCard` totals never change when filtering, "View Entry" row action still works on `visibleRows`

**Expenses** (`resources/js/pages/accounting-staff/Expenses/Index.vue`):
- Category select: `#expense-category-filter` (a `SearchableSelect` combobox — type to filter, not a native `<select>`)
- Status select: `#expense-status-filter`
- Clear filters button: `data-test="clear-expense-filters-button"` and `data-test="table-filter-clear-button"`
- Confirm: date range control is unaffected by Clear filters, Edit/Void row buttons (`data-test="edit-expense-{id}-button"` / `data-test="void-expense-{id}-button"`) still work on filtered rows, total/activeCount/voidedCount StatCard never changes when filtering

**Cashier Dashboard** (`resources/js/pages/cashier/Dashboard.vue`):
- Payment status select: `#cashier-payment-status-filter`
- Clear filters buttons: `data-test="clear-cashier-rush-filters-button"`, `data-test="clear-cashier-regular-filters-button"`, `data-test="table-filter-clear-button"`
- Confirm: each section (rush/regular) keeps its own two-tier empty state and `SectionHeading` stays visible even when that section's filtered result is empty; row actions (`process-payment-{id}-link`, `check-payment-status-{id}-button`, `view-receipt-{id}-link`, `cancel-job-order-{id}-item`) still work on filtered rows

**Production Board** (`resources/js/pages/production-staff/Dashboard.vue`):
- Rush only switch: `data-test="production-rush-only-toggle"` (id `production-rush-only-filter`) — keyboard-reachable via Tab + Space/Enter per the reka-ui `Switch` primitive
- Clear filters button: `data-test="clear-production-filters-button"` and `data-test="table-filter-clear-button"`
- Confirm: search + Rush only toggle compose with the existing stage/rush tabs (e.g. "Printing" tab + Rush only together), Clear filters resets the stage/rush tab back to "All" too, stageCounts tiles never change when filtering, Advance/Send Back row actions (`advance-job-order-{id}-button`, `send-back-job-order-{id}-button`) still work on `visibleJobOrders`

All four pages' search inputs are generated via `TableFilterBar`'s internal `useId()` — they have no fixed `id` but can be reached via their visible label text ("Search receivables", "Search expenses", "Search job orders" ×2) or by `role=searchbox`/`type=search` plus proximity to the page's `DataTableCard`.

## Self-Check: PASSED

- FOUND: resources/js/pages/accounting-staff/AccountsReceivable/Index.vue
- FOUND: resources/js/pages/accounting-staff/Expenses/Index.vue
- FOUND: resources/js/pages/cashier/Dashboard.vue
- FOUND: resources/js/pages/production-staff/Dashboard.vue
- FOUND commit 9741298 (Task 1 — Accounts Receivable)
- FOUND commit c9b3626 (Task 2 — Expenses)
- FOUND commit bb66b63 (Task 3 — Cashier + Production)

## Orchestrator follow-ups (2026-10-01, after a real-browser pass)

Driven in headless Chrome against a seeded SQLite copy, signed in as the accounting, cashier and production demo users in turn.

Verified on all four pages: search narrows and is case-insensitive; each new filter narrows further and can be changed; the "No matches" state appears and Clear resets everything, including the tab/tile filter; server totals (Expenses total, receivable bracket totals, production stage counts) do not move while a filter is active; a category that is on a row but no longer configured ("Courier") is offered as a filter option; the "Rush only" switch toggles by click and by Space; the Cashier page keeps both section headings when nothing matches; no overflow at 375px; no console errors.

No code changes were needed after the pass.

Carried to the responsive task: an empty state inside a wide table is centred on the table's full scroll width, so on a phone it sits off-screen (seen on the Cashier page's empty Rush section). This predates this task.
