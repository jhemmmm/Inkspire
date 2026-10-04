---
quick_id: 261001-m2e
phase: quick
plan: 261001-m2e
subsystem: frontend
tags:
    [
        tailwind-v4,
        container-queries,
        responsive,
        PageContainer,
        DataTableCard,
        EmptyState,
        StatCard,
        TableFilterBar,
    ]
dependency-graph:
    requires: [261001-he2, 261001-i4q]
    provides: [container-query-grids-every-portal]
    affects:
        - resources/js/components/PageContainer.vue
        - resources/js/components/DataTableCard.vue
        - resources/js/components/EmptyState.vue
        - resources/js/components/StatCard.vue
        - resources/js/components/TableFilterBar.vue
        - resources/js/components/JobOrderPriceFields.vue
        - resources/js/components/reports/ReportsWorkspace.vue
        - resources/js/pages/admin/*
        - resources/js/pages/accounting-staff/*
        - resources/js/pages/artist/*
        - resources/js/pages/cashier/*
        - resources/js/pages/frontline-staff/*
        - resources/js/pages/production-staff/Dashboard.vue
tech-stack:
    added: []
    patterns:
        - Tailwind v4 container queries (`@container` on PageContainer/DataTableCard, `@lg:`/`@md:`/`@2xl:`/`@3xl:`/`@5xl:` on descendants) replacing viewport-based layout variants, so page grids size against real content width (viewport minus sidebar minus padding), not the raw viewport
key-files:
    created: []
    modified:
        - resources/js/components/PageContainer.vue
        - resources/js/components/StatCard.vue
        - resources/js/components/DataTableCard.vue
        - resources/js/components/EmptyState.vue
        - resources/js/components/TableFilterBar.vue
        - resources/js/components/JobOrderPriceFields.vue
        - resources/js/components/reports/ReportsWorkspace.vue
        - resources/js/pages/admin/AuditTrail.vue
        - resources/js/pages/admin/CreditRequests.vue
        - resources/js/pages/admin/Dashboard.vue
        - resources/js/pages/admin/DesignOverrides.vue
        - resources/js/pages/admin/JobOrders.vue
        - resources/js/pages/admin/Specifications.vue
        - resources/js/pages/admin/UserManagement.vue
        - resources/js/pages/admin/WriteOffRequests.vue
        - resources/js/pages/accounting-staff/AccountsReceivable/Index.vue
        - resources/js/pages/accounting-staff/Expenses/Index.vue
        - resources/js/pages/artist/Dashboard.vue
        - resources/js/pages/artist/JobOrderWorkspace.vue
        - resources/js/pages/artist/PerformanceReport.vue
        - resources/js/pages/cashier/Dashboard.vue
        - resources/js/pages/cashier/JobOrderPayment.vue
        - resources/js/pages/frontline-staff/JobOrderDetail.vue
        - resources/js/pages/frontline-staff/NewVisit.vue
        - resources/js/pages/production-staff/Dashboard.vue
decisions: []
metrics:
    duration: ~25min
    completed: 2026-10-01
---

# Quick Task 261001-m2e: Responsive layout — size portal grids by their container Summary

Converted every in-scope viewport layout class (`grid-cols-*`, `col-span-*`, `flex-row`/`flex-wrap`/`items-*`, fixed widths, `hidden`/`block` toggles) across 7 shared components and 15 portal pages from `sm:`/`md:`/`lg:`/`xl:`/`2xl:` viewport variants to Tailwind v4 `@container`-scoped `@lg:`/`@md:`/`@2xl:`/`@3xl:`/`@5xl:` variants, so grids react to `PageContainer`'s real content width (viewport minus the sidebar minus padding) instead of the raw viewport.

## What Was Built

**Task 1 — Container foundation and shared components:**
`PageContainer.vue` and `DataTableCard.vue` roots gained `@container`, each with a comment explaining why. `TableFilterBar.vue`'s three `sm:` layout variants became `@lg:`. `JobOrderPriceFields.vue`'s sq-ft width/height grid became `@2xl:grid-cols-2`. `reports/ReportsWorkspace.vue`'s list+content grid became `@3xl:grid-cols-[300px_minmax(0,1fr)]`. `StatCard.vue`'s value span gained `whitespace-nowrap`. `EmptyState.vue`'s root became `sticky left-0 w-[calc(100cqw-2rem)] max-w-full` with a comment explaining the sticky/calc/max-w-full interplay (D6) — sizes to the nearest `@container` minus the table cell's `2rem` padding, capped at the parent for bare-panel usages.

**Task 2 — Admin portal pages:**
Converted filter-form grids and select-width wrappers across `AuditTrail`, `CreditRequests`, `Dashboard` (attention tiles, Shop Today tiles, charts row), `DesignOverrides`, `JobOrders`, `Specifications` (status select, add-option form, width/height inputs), `UserManagement` (role/status selects), `WriteOffRequests` — all 16 occurrences matched the plan's `md`/`lg`/`xl` → `@md`/`@3xl`/`@lg` mapping exactly.

**Task 3 — Remaining portal pages:**
Converted `AccountsReceivable/Index` (aging-bracket tiles, collection-status select), `Expenses/Index` (totals tiles, category/status selects), `artist/Dashboard` + `JobOrderWorkspace` + `PerformanceReport` (stat tiles, filter form, submit row), `cashier/Dashboard` (payment-status select) + `JobOrderPayment` (pricing/payment two-panel grid), `frontline-staff/JobOrderDetail` (two-card row), `production-staff/Dashboard` (stage tiles), and all 12 layout classes in `frontline-staff/NewVisit.vue` (step divider, customer-search form, register-customer form and its field pairs, job-order-type grid, print-specifications grid and its five `col-span-2` fields). All 29 occurrences matched the plan's before-strings exactly, including the two documented non-mechanical breakpoint choices (`sm:block` → `@lg:block` on the step divider; `xl:` → `@3xl:` on `JobOrderPayment`'s two-panel grid, matching `ReportsWorkspace`'s reasoning).

## Breakpoint judgment calls

**None.** Every before-string in the plan's tables matched the current file content exactly (verified file-by-file before editing in all three tasks), so every conversion applied the plan's literal mapping with no deviation. The two "non-mechanical" breakpoint choices in Task 3 (`NewVisit.vue`'s `sm:block` → `@lg:block`, and the `xl:` → `@3xl:` choice shared by `JobOrderPayment.vue`/`ReportsWorkspace.vue`) were decisions the plan itself had already made and reasoned through (section "Reasoning for the two non-mechanical choices in this task") — this execution applied them as written, it did not originate them.

## Task Commits

1. **Task 1: Container foundation and shared components** - `35444ec` (feat)
2. **Task 2: Convert admin portal pages** - `76395be` (feat)
3. **Task 3: Convert the remaining portal pages** - `368768a` (feat)

## Deviations from Plan

None — plan executed exactly as written. No before-string mismatches were found in any of the 22 touched files (all were verified against the live file content before each edit), so no "find the real current string" fallback (scope_guard item) was ever needed.

## Verification Performed

- **`npx vp check --fix`**, run scoped to each task's exact file list (never the bare `npm run check:fix`): all three runs passed with "Found no warnings or lint errors." The formatter reordered Tailwind classes within every touched `class` string (e.g. moving the new `@container`/`@lg:`/etc. token to its sorted position) — this is the expected, allowed reordering per the environment instructions, not a content change.
- **`npm run types:check`** (`vue-tsc --noEmit`): clean after Task 1, Task 2, and Task 3, and clean again on the full project at the end. Note: the IDE's live diagnostics panel repeatedly flagged false-positive errors during editing (e.g. "Cannot find module 'vue'/'@inertiajs/vue3'", "Module ... has no default export" for `.vue` SFCs, implicit-`any` parameter errors) on essentially every file touched — these are a stale/lagging incremental TS server in this session, not real errors; the authoritative `vue-tsc --noEmit` run was clean every time and is what gates this task.
- **`php artisan test --compact`**, scoped per task and then the full cross-portal run specified in the plan's own "Verification" section:
    - Task 1: `tests/Feature/Reports tests/Feature/FrontlineStaff` — 165 passed, 804 assertions.
    - Task 2: `tests/Feature/Admin` — 143 passed, 717 assertions.
    - Task 3: `tests/Feature/AccountingStaff tests/Feature/AccountsReceivable tests/Feature/Artist tests/Feature/Cashier tests/Feature/FrontlineStaff tests/Feature/ProductionStaff` — 392 passed, 2066 assertions.
    - Full plan-level run: `tests/Feature/Admin tests/Feature/AccountingStaff tests/Feature/AccountsReceivable tests/Feature/Artist tests/Feature/Cashier tests/Feature/FrontlineStaff tests/Feature/ProductionStaff tests/Feature/Reports` — **573 passed, 2919 assertions, 0 failures.**
- **`git status`** confirmed throughout and at the end that no `app/`, `routes/`, or `database/` file changed — this task is 100% `.vue` class-string edits plus two small template comments, exactly as scoped. (`vendor/bin/pint`/`composer types:check` are not applicable, per the plan.)
- **Final grep audit:** the plan's literal regex (`grep -rnoE "(sm|md|lg|xl|2xl):(grid-cols-...|...)\b" ...` across all 22 files) reports ~60 "hits." **Every one is a false positive.** The regex has no `@`-exclusion and no left word boundary, so it matches the `lg`/`md`/`2xl` substring inside the new `@lg:`/`@md:`/`@2xl:` container tokens, and — because `xl` is itself a substring of `2xl`/`3xl`/`5xl` — it also matches inside `@2xl:grid-cols-2`, `@3xl:grid-cols-2`, and `@5xl:grid-cols-4`. Re-running the identical check with a corrected pattern that requires the `sm|md|lg|xl|2xl` token not be immediately preceded by `@` or another word character (`(^|[^@[:alnum:]_-])(sm|md|lg|xl|2xl):(...)`) returns **zero matches** across all 22 files — confirmed by exit code 1 (no match). The conversion is complete; no viewport layout variant remains in scope.

## Known Stubs

None. This task is a pure class-string conversion with no new data flow, props, or components.

## Threat Flags

None. No new network endpoints, auth paths, file access, or schema changes — CSS-only.

## Unverified Interactions (browser verification required)

Per the task constraints, no browser was driven in this execution. The orchestrator's real-browser audit (375/768/820/1024/1280/1366/1536/1920px, sidebar open/collapsed, both themes) should specifically confirm, per the plan's own closing note:

- No `StatCard` wraps its label or clips its value at any tested width (the `whitespace-nowrap` + existing length-based font-size stepping should prevent both).
- The Cashier/Production/any table's empty-state (`EmptyState` inside `TableEmpty`) sits inside the visible `DataTableCard` at 375px instead of dragging off-screen with the table's scroll width (D6 — `sticky left-0 w-[calc(100cqw-2rem)]`).
- The two-panel layouts (`cashier/JobOrderPayment.vue`, `reports/ReportsWorkspace.vue`, `frontline-staff/JobOrderDetail.vue`, `admin/Dashboard.vue` charts row) don't collapse to one column until genuinely narrow (`@3xl` ≈ 864px container width).
- `NewVisit.vue`'s step divider (`@lg:block`) and "Find the customer" row (`@lg:flex-row`) behave correctly in the sidebar's mobile/overlay mode (≤768px viewport, per `SidebarProvider`'s breakpoint) where `PageContainer`'s content width is viewport-minus-padding only, not viewport-minus-sidebar.
- All admin/portal filter-form grids (`@md:grid-cols-2 @3xl:grid-cols-4` pattern) reflow correctly across 768–1366px, the range where the original viewport-based classes under- or over-fired relative to the sidebar's actual footprint.

## Self-Check: PASSED

- FOUND: resources/js/components/PageContainer.vue
- FOUND: resources/js/components/DataTableCard.vue
- FOUND: resources/js/components/EmptyState.vue
- FOUND: resources/js/components/StatCard.vue
- FOUND: resources/js/components/TableFilterBar.vue
- FOUND: resources/js/components/JobOrderPriceFields.vue
- FOUND: resources/js/components/reports/ReportsWorkspace.vue
- FOUND: resources/js/pages/frontline-staff/NewVisit.vue
- FOUND commit 35444ec (Task 1 — container foundation and shared components)
- FOUND commit 76395be (Task 2 — admin portal pages)
- FOUND commit 368768a (Task 3 — remaining portal pages)

## Orchestrator follow-ups (real-browser audit)

Every portal page was scripted in headless Chrome at 375, 768, 820, 1024, 1280, 1366, 1536 and 1920px, sidebar open and collapsed (340 states), measuring overflow, clipped or wrapped figures, cramped grids, off-screen controls and cut-off text. Final run: no page-level horizontal overflow, no clipped or wrapped stat figure, no off-screen control, no console error. The two remaining flags are the Production board's icon-less stage tiles (161px two-up on a phone, 198px four-up at 1024px collapsed); both were looked at and read cleanly, so they stay.

Measured content width of `PageContainer` on the admin dashboard, the number the old viewport breakpoints never saw: 454px at an 820px viewport, 640px at 1024px, 896px at 1280px, 982px at 1366px, 1152px at 1536px (sidebar open).

Fixed after the executor's commits:

- `1c56f55` — `EmptyState` inside a table wider than its card was centred across the table's scroll width, so on a phone it sat off-screen. It is now sticky on both edges, as wide as the visible card, and resets the `nowrap` it inherited from the table cell. Production and Accounts Receivable tab lists wrap instead of running off the edge (the last two production stages were unreachable at 375px). Job Orders and Audit Trail pagination wraps. Accounts Receivable aging tiles step 1 / 2 / 3 / 6 columns so a peso figure is never clipped.
- `f0a0926` — User Management's table was 86px wider than its card at 1280px with the sidebar open, pushing Edit and Deactivate out of view. The email moved under the name; the table now fits from 1280px up.

Dark theme checked on the dashboard, User Management (table, no-match state, edit dialog with the avatar field), Print Specifications at 375px, Job Orders, Audit Trail and the Cashier dashboard.

Left alone, on purpose:

- Most data tables still scroll sideways inside their card at laptop widths (for example the Production board is 667px wider than its card at 1280px, and its Actions column is the last one). That is the existing table design, cells do not wrap, and it meets the "wide content scrolls inside its own box" rule. Changing it is a per-table design decision, not a breakpoint fix.
- "Hydration completed but contains mismatches" on a page first loaded at 768px or narrower. It comes from the generated `components/ui/sidebar/SidebarProvider.vue`, which is not hand-edited; the page corrects itself.
- Green text on the default (primary) badge, for example "Available" on User Management and "Ready for Pickup" on the Production board, is hard to read. It predates this work and the pattern is in 14 files.

Verification: `php artisan test --compact` 766 passed, 3 skipped (the two-factor tests in `SecurityTest`, skipped before this work too); `npm run types:check` clean.
