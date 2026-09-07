---
phase: 07-accounts-receivable
plan: 02
subsystem: ui

tags: [laravel, inertia, vue, accounts-receivable, aging, accounting-staff]

# Dependency graph
requires:
  - phase: 07-accounts-receivable (plan 01)
    provides: "accounts_receivable schema (due_at, aging/collection columns), AccountsReceivableAgingBracket/CollectionStatus enums, AccountsReceivable::agingBracket()/daysPastDue()"
provides:
  - "AccountsReceivableController (index, show) — Accounting Staff's aging list and entry detail, scoped to Active entries, split open/closed, six aging brackets computed server-side"
  - "accounting-staff.accounts-receivable.index/show routes and nav item"
  - "accounting-staff/AccountsReceivable/Index.vue and Show.vue — first real Accounting Staff portal surface beyond the reconciliation placeholder"
affects: [07-03, 07-04, 07-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "@phpstan-type class-level alias for a repeated array shape, used across a private helper's return type and a sibling method's Collection/array parameter type, to keep Larastan level 7 clean without @phpstan-ignore"
    - "Plain-array sort helper (usort) instead of Collection::sort() across a method boundary — Collection's TValue generic is invariant, so a Collection typed with a literal-narrowed shape (post filter()/reject()) cannot be passed to a method declaring the general shape even when structurally identical"
    - "setLayoutProps() for props-dependent breadcrumbs (JobOrderWorkspace.vue precedent) since defineOptions() cannot reference script-setup local bindings — it is hoisted outside setup()"

key-files:
  created:
    - app/Http/Controllers/AccountingStaff/AccountsReceivableController.php
    - resources/js/pages/accounting-staff/AccountsReceivable/Index.vue
    - resources/js/pages/accounting-staff/AccountsReceivable/Show.vue
    - tests/Feature/AccountingStaff/AccountsReceivableListTest.php
  modified:
    - routes/portals.php
    - resources/js/config/nav/accounting-staff.ts
    - database/factories/AccountsReceivableFactory.php

key-decisions:
  - "jobOrder eager-load column allowlist includes queue_entry_id (not just id/number/description/total_amount) so the nested jobOrder.queueEntry.customer load actually resolves — a column-restricted BelongsTo parent silently returns a null relation without its own foreign key present"
  - "Sort helper takes/returns a plain array (usort), not a Collection, to avoid Larastan's Collection-TValue-invariance error when passing a filter()/reject()-narrowed Collection to a method typed with the general row shape"
  - "AccountsReceivableFactory::rejected() bug (uninstantiated UserFactory passed to forceFill) fixed identically to active()'s prior 07-01 fix, since this plan's D-01 scope test needed a Rejected row for the first time"

patterns-established:
  - "@phpstan-type alias for a controller's row-shape array, shared between a private derivation method and a private array-processing helper"

requirements-completed: [AR-01]

# Metrics
duration: 40min
completed: 2026-09-07
---

# Phase 7 Plan 02: Accounts Receivable Aging List & Entry Detail Summary

**Accounting Staff's first real portal surface: a bracket-summary-cards-over-filtered-table aging list (six live-derived aging buckets, open/closed split) plus a single-entry Amounts/Account Details drill-down, both scoped strictly to Owner-approved Active receivables.**

## Performance

- **Duration:** ~40 min (including fresh-worktree bootstrap: composer/npm install, .env, sqlite db, migrate:fresh, asset build)
- **Started:** 2026-09-07T23:00:00Z (approx, worktree setup)
- **Completed:** 2026-09-07T23:40:22Z
- **Tasks:** 2 completed
- **Files modified:** 7 (4 created, 3 modified)

## Accomplishments
- `AccountsReceivableController::index()` returns `receivables`/`closedReceivables`/`bracketSummaries` computed entirely server-side: Active-only (D-01), balance derived live from completed transactions never the stored column (D-16), six aging brackets always present even at zero (D-03)
- `show()` 404s for a non-Active entry (pending/rejected credit requests stay invisible to this role) and renders the same derived row for both an open and a closed entry
- `Index.vue` implements the UI-SPEC's bracket-cards-over-tabs layout exactly: three-tier aging badge palette, monochrome collection-status badges, "Settled" green replacing ₱0.00, write-off-pending indicator, zero polling/export/date-range/search
- `Show.vue` renders the Amounts panel (the only surface showing Credit Extended) and Account Details panel, with explicit HTML-comment insertion points for 07-04's Collection Status panel and 07-05's write-off actions

## Task Commits

Each task was committed atomically:

1. **Task 1: Aging list + entry detail backend, routes, nav** — RED `214f89f` (test) → GREEN `a1b5549` (feat)
2. **Task 2: Aging list and entry detail UI** — `9f38bf8` (feat), follow-up fix `4600362` (fix)

_Task 1 is `tdd="true"`; no refactor commit was needed. Task 2's fix commit addresses a copywriting-contract gap found during self-review._

## Files Created/Modified
- `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php` - index()/show(), shared `eagerLoads()`/`deriveRow()`/`sortRows()` private helpers, `@phpstan-type AccountsReceivableRow` alias
- `routes/portals.php` - two new GET routes in the `accounting-staff` group
- `resources/js/config/nav/accounting-staff.ts` - "Accounts Receivable" nav item (HandCoins icon)
- `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue` - bracket cards, filter tabs, aging table
- `resources/js/pages/accounting-staff/AccountsReceivable/Show.vue` - Amounts + Account Details panels
- `tests/Feature/AccountingStaff/AccountsReceivableListTest.php` - 5 tests covering D-01 scope, D-16 derivation, D-03 bucketing, open/closed split, show() 404/200
- `database/factories/AccountsReceivableFactory.php` - fixed `rejected()`'s latent `approved_by` bug

## Decisions Made
- Used a `@phpstan-type AccountsReceivableRow` class-level alias (Larastan level 7) shared between `deriveRow()`'s return type and `sortRows()`'s parameter/return type, after discovering Laravel's `Collection<TKey,TValue>` template is invariant — a `Collection` typed with a `filter()`/`reject()`-narrowed literal shape could not be passed to a method declaring the general row shape even though the arrays were structurally identical. Resolved by making `sortRows()` operate on a plain `array` via `usort()` instead of `Collection::sort()`, sidestepping the cross-method Collection-typing mismatch entirely rather than fighting it with casts or ignores (both forbidden).
- `jobOrder`'s eager-load column allowlist includes `queue_entry_id` alongside `id,number,description,total_amount` — the plan's example list omitted it, but a column-restricted `BelongsTo` model is missing its own foreign key attribute, so the nested `jobOrder.queueEntry` load would have silently resolved to `null` for every row without it.
- `Show.vue` and `Index.vue` use the codebase's `setLayoutProps()` pattern (established in `JobOrderWorkspace.vue`) for the props-dependent breadcrumb entry, since Vue's `defineOptions()` compiler hoists its argument out of `setup()` and cannot reference locally-declared `const props = defineProps(...)` bindings.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed `AccountsReceivableFactory::rejected()`'s pre-existing `approved_by` bug**
- **Found during:** Task 1 (writing the D-01 scope test, which needs a Rejected AR row)
- **Issue:** `forceFill(['approved_by' => User::factory()->owner(), ...])` passed an uninstantiated `UserFactory` instance instead of a created user's id, throwing `Object of class Database\Factories\UserFactory could not be converted to string` the moment `->rejected()->create()` was exercised — this exact latent bug was flagged (but left unfixed, since unused) in 07-01's `deferred-items.md`
- **Fix:** Changed to `User::factory()->owner()->create()->id`, matching `active()`'s identical 07-01 fix
- **Files modified:** `database/factories/AccountsReceivableFactory.php`
- **Verification:** `AccountsReceivableListTest`'s D-01 scope test passes; full 407-test suite passes
- **Committed in:** `214f89f` (Task 1 RED commit, alongside the test that exercises it)

**2. [Rule 1 - Bug] Added the "Due in {n} days" not-yet-due sub-line**
- **Found during:** Post-implementation self-review of the Copywriting Contract
- **Issue:** `dueSubLine()` only handled the past-due case (`days_past_due !== null`); a Current-bracket row with a future `due_at` rendered no sub-line at all, missing UI-SPEC's explicit "Due cell — sub-line, not yet due: 'Due in {n} days'" contract line
- **Fix:** Added a future-date branch computing days-until-due from `due_at`
- **Files modified:** `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue`
- **Verification:** `npm run types:check` passes; visually matches the Copywriting Contract table
- **Committed in:** `4600362`

---

**Total deviations:** 2 auto-fixed (2 Rule 1 bug fixes)
**Impact on plan:** Both fixes necessary for correctness against this plan's own tests (fix 1) or the approved UI-SPEC's binding Copywriting Contract (fix 2). No scope creep — no files outside this plan's `files_modified` list were changed except the one pre-existing factory bug directly blocking a required test scenario.

## Issues Encountered
- This worktree had no `vendor/`, `node_modules/`, `.env`, SQLite database, or built frontend assets — ran `composer install`, `npm install`, `cp .env.example .env`, `php artisan key:generate`, `touch database/database.sqlite`, `php artisan migrate:fresh`, `php artisan wayfinder:generate --with-form`, and `npm run build` to establish a working baseline, mirroring 07-01's identical bootstrap. `npm install` again renamed `package-lock.json`'s `name` field to the worktree directory name; reverted with `git checkout -- package-lock.json` before any commit, per 07-01's documented precedent.
- `composer types:check` (Larastan level 7) still fails on the same 3 pre-existing, unrelated files first flagged in 07-01 (`CreateCreditRequestRequest.php`, `SavePricingAndPaymentRequest.php`, `UpdateSystemConfigurationRequest.php` — a route-model-binding type-inference gap). Confirmed none of these files are in this plan's `files_modified` and the errors are unchanged from 07-01's baseline; re-logged in `deferred-items.md` rather than fixed, per the scope boundary. Every other verification command (`route:list`, `pint`, the full 407-test Pest suite, `npm run types:check`) passes clean.
- Encountered and fixed two Larastan-level-7 typing issues of my own introduction during Task 1 (nullsafe-on-mixed-type false positive, and Collection's TValue-invariance rejecting a filter()/reject()-narrowed Collection at a typed method boundary) — both resolved by restructuring code (plain-array `usort()` helper, `@phpstan-type` alias) rather than suppressing, before the task's first commit.

## Known Stubs

None. "Last Reminder Sent" reads a real (currently always-null pre-07-03) column rather than a hardcoded placeholder string — it will show real data once 07-03's reminder command starts populating `last_reminder_sent_at`, with no further change needed on this page, exactly as the plan specified.

## Threat Flags

None — the plan's own `<threat_model>` fully covers the two new read-only routes (`role:accounting_staff` middleware, `abort_unless(status === Active, 404)` on `show()`). No new endpoints, mutation paths, or trust boundaries beyond what the threat register already disposed of.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- The aging list and entry detail surfaces are live and tested — 07-03 (reminder command) can populate `last_reminder_sent_at`/`last_reminder_bracket` with no further schema or UI change; 07-04 (collection status update, collection letter) and 07-05 (write-off request/approval) can extend `Show.vue` at the two marked insertion points without restructuring the existing panels.
- No blockers. The one pre-existing Larastan gap (see Issues Encountered) remains orthogonal to Phase 7 and should still get its own small fix plan.

---
*Phase: 07-accounts-receivable*
*Completed: 2026-09-07*
