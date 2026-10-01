---
status: complete
---

# Quick Task 261001-he2: Search and filters on the admin list pages — Summary

One-liner: Client-side search + filters added to six Admin list pages via two new shared primitives — `useTableFilter` (a `matchesSearch` pure matcher plus a reactive filtered-list composable) and `TableFilterBar` (search box + filter slot + "Showing X of Y" + Clear filters) — with `UserManagement.vue` additionally moved off a raw `<table>` onto the `Table`/`TableRow`/`TableCell` primitives and the Admin dashboard's "Locked-Out Accounts" tile now deep-linking into it with `?status=locked`.

## What changed, per task

**Task 1 — Shared primitives: `useTableFilter.ts` + `TableFilterBar.vue`**

- `resources/js/composables/useTableFilter.ts` (new): exports `matchesSearch(term, values)` — case-insensitive substring match across a joined list of nullable strings, normalizing the term with `.trim().toLowerCase()` — and `useTableFilter<T>(rows, searchText, options)`, a small composable returning `{ searchTerm, filtered }` where `filtered` is a `computed` over `toValue(rows)` combining the search match with an array of extra predicate functions. `options.searchTerm` lets two lists on the same page (Specifications' per-category tables) share one search box.
- `resources/js/components/TableFilterBar.vue` (new): a `Card`/`CardContent` wrapping a search `Input` (icon absolute-positioned inside a `relative` wrapper, `pl-9`, matching `JobOrders.vue`'s existing search field exactly), a default slot for page-specific filter controls, a `tabular-nums` "Showing X of Y" count with `aria-live="polite"`, and a conditionally-rendered "Clear filters" button (`data-test="table-filter-clear-button"`). Uses `useId()` for the search input's id (no hardcoded id) and `defineModel<string>('search')` for `v-model:search`.
- Both files pass `npx vp check --fix`, `npm run types:check`, and the plan's standalone `node --experimental-strip-types` check of all four `matchesSearch` assertions.

**Task 2 — `UserManagement.vue` table primitives, search/filters, deep link; `Dashboard.vue` deep-link href**

- `UserManagement.vue`: `defineProps` captured as `const props = defineProps<...>()` (previously uncaptured) so `useTableFilter` could read `() => props.users`. Added `roleFilter`/`statusFilter` refs, `roleFilterOptions`/`statusFilterOptions` (plain `Select` — matches the two dialogs' existing Role control, one consistent control for this page), and `initialStatusFilter()` which reads `?status=locked` off `usePage().url` (no `window`, SSR-safe) and seeds `statusFilter`, ignoring any value outside `active`/`deactivated`/`locked`.
- The raw `<table>` was replaced with `Table`/`TableHeader`/`TableBody`/`TableRow`/`TableCell`/`TableEmpty` primitives, matching `Specifications.vue`'s structure. A `TableFilterBar` sits between `PageHeader` and the table (gated on `users.length > 0`). The `TableBody` now has a three-way chain: `TableEmpty` for the true-empty case ("No users yet"), `TableEmpty` for the no-matches case ("No matches" + a `data-test="clear-user-filters-button"` Clear-filters action), then `TableRow v-for="user in filteredUsers"`. Every existing `data-test` attribute, the avatar cell, and both dialogs are unchanged — only the surrounding markup and the `v-for` source list changed.
- `Dashboard.vue`: the "Locked-Out Accounts" attention tile's `href` changed from `usersIndex()` to `usersIndex({ query: { status: 'locked' } })` using Wayfinder's existing `query` option (same pattern as `reportsIndex({ query: {...} })` elsewhere in the same file).

**Task 3 — The remaining five admin list pages**

- `Specifications.vue`: one shared `TableFilterBar` (search + Status `Select`, rendered unconditionally — this catalog page is always non-empty) above the per-category `Card` loop. One `useTableFilter` instance per category built via `Object.fromEntries(props.categories.map(...))`, all sharing a single `searchTerm` ref. Each category's `CardTitle` now shows `(N of M)`. Each category's `TableBody` keeps its original "no {category} yet" `TableEmpty` and gains a second "No matches" `TableEmpty` (`data-test="clear-specification-filters-button"`); the `TableRow v-for` now iterates `filteredOptionsFor(category.value)`. Existing inline rename, `Switch` toggle, and delete dialog are untouched — they still operate on the real `option` object.
- `DesignOverrides.vue`, `CreditRequests.vue`, `WriteOffRequests.vue`: identical shape — `TableFilterBar` (gated on a non-empty list) with one `SearchableSelect` filter (Artist / Requested By / Requested By respectively — each list can exceed ~10 names), a new "No matches" `TableEmpty` branch with its own `data-test` Clear-filters button, and the `TableRow v-for` switched to the filtered list. Row actions (Unlock Design, Approve/Reject Credit, Approve/Reject Write-Off) are unchanged — they still read the real row object, now sourced from the filtered array.
- `AuditTrail.vue`: component swap only, per D8. The User `Select` block was replaced with `SearchableSelect` bound to the same `selectedUser` ref and a new `userOptions` computed (`{ value: ALL, label: 'All users' }` plus one option per `props.users`). The Action field's plain `Select`, the `visit()`/`clearFilters()` functions, and the PDF/Excel export links (still built from `props.filters`, the server-applied filters) are all untouched.

## Deviations from Plan

None — plan executed exactly as written. No Rule 1-4 auto-fixes were needed; every script addition, import, and template change matched the plan's literal code blocks.

## Files changed

- `resources/js/composables/useTableFilter.ts` — new: `matchesSearch` + `useTableFilter`
- `resources/js/components/TableFilterBar.vue` — new: shared search/filter bar
- `resources/js/pages/admin/UserManagement.vue` — Table primitives, search/filters, `?status=locked` seeding
- `resources/js/pages/admin/Dashboard.vue` — Locked-Out Accounts tile deep-links with `?status=locked`
- `resources/js/pages/admin/Specifications.vue` — shared search + Status filter across all per-category tables
- `resources/js/pages/admin/DesignOverrides.vue` — search + Artist `SearchableSelect`
- `resources/js/pages/admin/CreditRequests.vue` — search + Requested-By `SearchableSelect`
- `resources/js/pages/admin/WriteOffRequests.vue` — search + Requested-By `SearchableSelect`
- `resources/js/pages/admin/AuditTrail.vue` — User `Select` → `SearchableSelect`

No PHP file was created or modified by this task.

## Commits

1. `21fcc15` — `feat(quick-261001-he2): shared client-side table filter primitives` (Task 1)
2. `24251ec` — `feat(quick-261001-he2): search and filters on User Management; dashboard deep link` (Task 2)
3. `4cb043d` — `feat(quick-261001-he2): search and filters on remaining admin list pages` (Task 3)

## Verification results

**`npx vp check --fix` (combined, all 9 changed files, final run — idempotent, zero further changes):**

```
pass: Formatting completed for checked files (1.7s)
pass: Found no warnings or lint errors in 9 files (326ms, 16 threads)
```

**`npm run types:check` (final):** `vue-tsc --noEmit` — no output, zero errors.

**`php artisan test --compact tests/Feature/Admin` (final, full Admin suite):**

```
{"tool":"pest","result":"passed","tests":143,"passed":143,"assertions":717,"duration_ms":18798}
```

143/143 passing, no regressions from Task 2's `UserManagementTest.php`/`DashboardTest.php` run (20/20) through to the full suite.

**Plan's standalone matcher check (final):**

```
matchesSearch: OK
```

(`node --experimental-strip-types`, all four assertions — substring match, no-match, empty-term-matches-everything, and trim+case-insensitivity — passed.)

**No PHP file changed** (`git diff --stat` across all three task commits confirms only `.vue`/`.ts` files under `resources/js/`) — `vendor/bin/pint` and `composer types:check` were not required per the plan's note and were not run.

## Decisions Made

- Followed the plan's explicit discretion calls verbatim: plain `Select` for User Management's Role/Status and Specifications' Status (short fixed lists, matching each page's existing dialog controls), `SearchableSelect` for Artist/Requested-By/Audit-User (open-ended or 10+ option lists).
- `TableFilterBar` is gated with `v-if="<list>.length > 0"` on every page except `Specifications.vue`, which renders it unconditionally since its fixed category cards are always present even on a fresh shop (exactly as the plan specified).

## Issues Encountered

None. The IDE's live TypeScript diagnostics transiently reported large numbers of "Cannot find module" / "implicitly has an 'any' type" errors immediately after several edits (including for untouched imports like `vue` and `@inertiajs/vue3`) — these were stale tsserver state, not real defects: every `npm run types:check` run (the real `vue-tsc --noEmit` compiler) in between and after edits reported zero errors.

## User Setup Required

None — this task has no backend changes, no new dependencies, and no environment configuration.

## Next Phase Readiness

- `useTableFilter`/`TableFilterBar` are now the established pattern for client-side-filterable admin lists; the next quick task (Accounts Receivable, Cashier dashboard, Production board, Expenses — explicitly out of scope here) can reuse both as-is.
- Section 5 (container queries) from the source plan remains a separate, later task — no grid classes were touched here per D10.

## Known Stubs

None. Every filter option list (`roleFilterOptions`, `statusFilterOptions`, `artistOptions`, `requesterOptions`, `userOptions`) is derived from real props already on the page; no new prop, placeholder, or mock data was introduced.

## Threat Flags

None. No new network endpoint, auth path, file access pattern, or schema change was introduced — every change in this task is client-side filtering over data the server already sent.

## Unverified by this executor (real-browser pass required)

Per this task's constraints, no browser was driven. The orchestrator's real-browser check should specifically confirm, in both themes at 375px and desktop, on each of the six pages:

- Typing in the search box filters the list; applying a Select/SearchableSelect filter further narrows it; changing or clearing each filter updates the "Showing X of Y" count correctly.
- The "no matches" `EmptyState` appears when a search/filter combination matches nothing, and its "Clear filters" button resets the page's filters and search term.
- Every row action (Edit/Deactivate/Reactivate on User Management; Unlock Design; Approve/Reject Credit; Approve/Reject Write-Off; the Specifications toggle/inline-edit/delete/add) still works on a filtered list, and the row stays correctly placed (or correctly disappears/reappears per its new state) after the action completes.
- Every new control (search input, Role/Status/Artist/Requested-By selects, Clear filters buttons) is reachable and operable by keyboard alone, with a visible focus ring in both themes.
- Clicking the Admin dashboard's "Locked-Out Accounts" tile navigates to User Management with the Status filter already showing "Locked out" and the table pre-filtered to locked accounts.
- On `AuditTrail.vue`, confirm the new `SearchableSelect` for User behaves identically to the old `Select` for keyboard and screen-reader users, and that Apply/Clear and the PDF/Excel export links still follow the server-applied filters (not live edits in the form).
- At 375px, the `TableFilterBar`'s search field and filter selects stack full-width and wrap cleanly above the "Showing X of Y" line, and wide tables (Specifications, Write-Off Requests) still scroll horizontally inside their own card without dragging the page header off-screen.

## Self-Check

```
FOUND: resources/js/composables/useTableFilter.ts
FOUND: resources/js/components/TableFilterBar.vue
FOUND: resources/js/pages/admin/UserManagement.vue
FOUND: resources/js/pages/admin/Dashboard.vue
FOUND: resources/js/pages/admin/Specifications.vue
FOUND: resources/js/pages/admin/DesignOverrides.vue
FOUND: resources/js/pages/admin/CreditRequests.vue
FOUND: resources/js/pages/admin/WriteOffRequests.vue
FOUND: resources/js/pages/admin/AuditTrail.vue
FOUND commit: 21fcc15
FOUND commit: 24251ec
FOUND commit: 4cb043d
```

## Self-Check: PASSED

## Orchestrator follow-ups (2026-10-01, after a real-browser pass)

Driven in headless Chrome against a seeded SQLite copy (extra locked designs, credit requests and write-off requests seeded there for the purpose).

Verified on all six pages: search narrows and is case-insensitive; each select narrows further; changing an existing select value works; "Showing X of Y" tracks; the "No matches" state appears and both Clear buttons reset everything; Deactivate and Reactivate on a filtered list move the row out of the filter as expected; the dashboard tile opens User Management with Status = Locked out and a bogus `?status=` is ignored; the Audit Trail user select is searchable and the export links still follow the applied filters; Tab reaches every control; no overflow at 375px.

One change: the User Management search placeholder was truncated at desktop width and is now shorter.

Console: the only errors were "Hydration completed but contains mismatches" on pages first loaded at 375px. That also happens on pages this task did not touch, so it predates it; it is picked up in the responsive task.
