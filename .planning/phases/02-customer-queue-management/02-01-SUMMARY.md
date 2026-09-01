---
phase: 02-customer-queue-management
plan: 01
subsystem: customer-management
tags: [laravel, eloquent, inertia, vue3, wayfinder, shadcn-vue, pest]

# Dependency graph
requires:
  - phase: 01-foundation-rbac-auth-hardening-audit-trail
    provides: "AuditObserver/AuditLogger append-only audit substrate, EnsureUserHasRole middleware, frontline-staff route group/portal shell, Form Request + Validation Concern trait pattern"
provides:
  - "customers table + Customer model/factory — the customer_id FK every later Phase 2 plan (QueueEntry, JobOrder) attaches to"
  - "CustomerController::index (LIKE search) / ::store (register) under frontline-staff.new-visit / frontline-staff.customers.store"
  - "resources/js/pages/frontline-staff/NewVisit.vue — the live page Plan 02-02/02-03 extend with queue number generation and Job Orders intake"
  - "resources/js/config/nav/frontline-staff.ts — real sidebar nav pattern for the frontline-staff portal"
affects: [02-02, 02-03, 02-04, 02-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Search-as-filter via router.get(url, params, {preserveState, preserveScroll, replace}) on an Inertia GET page (AuditTrail.vue precedent), reused for customer search"
    - "Zero-result-search-gates-registration UI pattern: a `filters` prop's key presence (not just an empty array) distinguishes 'no search yet' from 'searched, zero results' (D-04)"
    - "Redirect-with-new-id-as-query-param after a create (to_route(..., ['customer' => $id])) instead of back(), so the very next Inertia render can resolve server-side state without client-only memory"

key-files:
  created:
    - database/migrations/2026_09_01_133940_create_customers_table.php
    - app/Models/Customer.php
    - database/factories/CustomerFactory.php
    - app/Concerns/CustomerValidationRules.php
    - app/Http/Requests/FrontlineStaff/SearchCustomersRequest.php
    - app/Http/Requests/FrontlineStaff/StoreCustomerRequest.php
    - app/Http/Controllers/FrontlineStaff/CustomerController.php
    - tests/Feature/FrontlineStaff/CustomerSearchTest.php
    - tests/Feature/FrontlineStaff/CustomerRegistrationTest.php
    - resources/js/pages/frontline-staff/NewVisit.vue
    - resources/js/config/nav/frontline-staff.ts
    - resources/js/components/ui/textarea/Textarea.vue
    - resources/js/components/ui/textarea/index.ts
  modified:
    - routes/portals.php
    - resources/js/pages/frontline-staff/Dashboard.vue

key-decisions:
  - "customers.contact_number is unique at the DB level (string column, not enum) with no ->ignore() in the app-level Rule::unique(), since Phase 2 has no customer-edit flow — every StoreCustomerRequest validation is always a create"
  - "hasSearched is computed from 'q' in props.filters (key presence), not customers.length === 0, matching D-04's actual gate condition and avoiding a false-positive 'no results' state on first page load"

patterns-established:
  - "Pattern: FrontlineStaff Form Requests live under app/Http/Requests/FrontlineStaff/, controllers under app/Http/Controllers/FrontlineStaff/ — new base subdirectories following the existing Owner/Settings sibling convention, no new top-level folders"
  - "Pattern: role-portal nav config files (resources/js/config/nav/{role}.ts) export a `{role}NavItems: NavItem[]` const built from Wayfinder route helpers, consumed by both the Dashboard and every subsequent page's defineOptions({ layout: { navItems } })"

requirements-completed: [QUEUE-01, QUEUE-02]

# Metrics
duration: 123min
completed: 2026-09-01
---

# Phase 2 Plan 01: Customer Search & Registration Summary

**Customer search-by-partial-name-or-contact-number and zero-result-gated registration, backed by a DB-unique `customers` table and a live `NewVisit.vue` page with real sidebar navigation.**

## Performance

- **Duration:** ~123 min (includes an unplanned mid-plan recovery — see Issues Encountered)
- **Started:** 2026-09-01T13:33:46Z
- **Completed:** 2026-09-01T15:36:36Z
- **Tasks:** 3/3 completed
- **Files modified:** 15 (13 created, 2 modified)

## Accomplishments
- `customers` table with DB-level `unique()` on `contact_number` (D-02), `Customer` model wired to the Phase 1 `AuditObserver` — verified end-to-end: `Customer::factory()->create()` writes exactly one `audit_trail` row
- `CustomerController::index` — LIKE-style partial match on `name` OR `contact_number` (D-03), rendering `frontline-staff/NewVisit` with `customers`, `filters`, and `selectedCustomer` props
- `CustomerController::store` — registers a customer and redirects to `frontline-staff.new-visit` with the new customer's id as a query param, so the summary card resolves server-side on the very next render
- `NewVisit.vue` — search bar, results `Table` with click-to-select rows, a `TableEmpty` zero-results state, a "Register New Customer" form gated behind a completed zero-result search (D-04), and a selected-customer summary card — all in one continuous screen per UI-SPEC
- `frontline-staff` portal gets real sidebar navigation (Dashboard, New Visit) for the first time, replacing the empty `navItems: []` placeholder
- All 9 `FrontlineStaff` feature tests pass (4 search, 4 registration, both directions of the 403 role boundary); full project suite: 77 passed / 3 pre-existing skips / 0 failed

## Task Commits

Each task was committed atomically:

1. **Task 1: Customer domain layer (migration, model, factory, validation concern)** - `f1cda13` (feat)
2. **Task 2: Search & registration endpoints** - `5a13bd2` (feat)
3. **Task 3: Search & register UI** - `2430e1e` (feat)

## Files Created/Modified
- `database/migrations/2026_09_01_133940_create_customers_table.php` - `customers` table: `name`, `contact_number` (unique), `email`, `address`, all required
- `app/Models/Customer.php` - `#[Fillable(['name','contact_number','email','address'])]`, `#[ObservedBy(AuditObserver::class)]`, `HasFactory`
- `database/factories/CustomerFactory.php` - PH-style `09#########` contact numbers, unique name/email/address via `fake()`
- `app/Concerns/CustomerValidationRules.php` - `customerRules()` + one `{field}Rules()` method per field, `Rule::unique(Customer::class)` on `contact_number`
- `app/Http/Requests/FrontlineStaff/SearchCustomersRequest.php` - `q` (nullable string), `customer` (nullable, `exists:customers,id`)
- `app/Http/Requests/FrontlineStaff/StoreCustomerRequest.php` - delegates to `CustomerValidationRules::customerRules()`
- `app/Http/Controllers/FrontlineStaff/CustomerController.php` - `index()` (LIKE search + selected-customer resolution), `store()` (register + redirect)
- `routes/portals.php` - added `frontline-staff.new-visit` (GET) and `frontline-staff.customers.store` (POST) inside the existing `role:frontline_staff` group
- `tests/Feature/FrontlineStaff/CustomerSearchTest.php` - partial name match, partial contact match, no-query-key-absent case, 403 for non-frontline-staff
- `tests/Feature/FrontlineStaff/CustomerRegistrationTest.php` - successful register + redirect, duplicate contact_number validation error, required-field validation, 403 for non-frontline-staff
- `resources/js/pages/frontline-staff/NewVisit.vue` - search form, results `Table`/`TableEmpty`, gated registration `Form`, selected-customer summary `Card`
- `resources/js/config/nav/frontline-staff.ts` - `frontlineStaffNavItems` (Dashboard, New Visit)
- `resources/js/components/ui/textarea/Textarea.vue`, `index.ts` - shadcn-vue `Textarea` primitive for the address field
- `resources/js/pages/frontline-staff/Dashboard.vue` - now imports and sets `frontlineStaffNavItems` instead of `navItems: []`

## Decisions Made
- `contact_number` uniqueness has no `->ignore()` clause in `CustomerValidationRules` — Phase 2 introduces no customer-edit flow, so every `StoreCustomerRequest` validation is always a create; this will need revisiting if/when a future phase adds customer editing.
- `hasSearched` on the frontend is `computed(() => 'q' in props.filters)`, not `customers.length === 0` — this correctly distinguishes "page just loaded, no search yet" from "searched and got zero results," which is D-04's actual gate condition for showing the registration form.
- `npx shadcn add textarea` (the base React `shadcn` CLI) produces a `.tsx` file under this Vue project — the correct tool is `npx shadcn-vue@latest add textarea` (per `components.json`'s `shadcn-vue` schema). Documented here as a trap for the next plan that adds a new shadcn-vue component (Plan 02-02's `radio-group`).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] `npx shadcn add textarea` generated a React `.tsx` file instead of Vue SFCs**
- **Found during:** Task 3, adding the `Textarea` UI primitive
- **Issue:** The plan's literal instruction (`npx shadcn add textarea --yes`) invoked the base `shadcn` (React) CLI, which wrote `resources/js/components/ui/textarea.tsx` — wrong framework output, and not the `resources/js/components/ui/textarea/Textarea.vue` + `index.ts` shape every other UI primitive in this project uses.
- **Fix:** Removed the stray `.tsx` file, ran `npx shadcn-vue@latest add textarea --yes` instead (matches `components.json`'s `"$schema": "https://shadcn-vue.com/schema.json"`), which produced the correct `Textarea.vue` + `index.ts` pair with no new `package.json` dependency (confirmed via `git diff --stat package.json package-lock.json` showing no changes).
- **Files modified:** `resources/js/components/ui/textarea/Textarea.vue`, `resources/js/components/ui/textarea/index.ts`
- **Verification:** `npm run types:check` exits 0; files match the shape of every other existing `resources/js/components/ui/*` primitive
- **Committed in:** `2430e1e` (Task 3 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Necessary to get a working Vue component at all; no scope creep — same component, same UI-SPEC-approved registry, just the correct CLI invocation.

## Issues Encountered
- **Accidental global reformat, recovered without data loss.** An early, unscoped `npm run check:fix` (Task 3) formatted the *entire* project tree (not just this plan's files) before erroring out on a pre-existing syntax quirk in `demo/index.html`. This touched ~81 files outside this plan's scope: `.claude/skills/**`, `.mcp.json`, `CLAUDE.md`, `boost.json`, every `.planning/**` doc, and several unrelated Vue pages (`owner/AuditTrail.vue`, `owner/UserManagement.vue`, other roles' `Dashboard.vue` files). All of these were style-only changes (blank-line insertion, quote-style, table alignment) — no semantic content was altered in any of them. Every one of the 81 files was restored to its exact prior committed content via `git show HEAD:<path> > <path>` (all were untouched by any human before this session). `.planning/STATE.md` (which had a genuine pending, uncommitted edit from the orchestrator's begin-phase call, sitting in the working tree before this plan started) was manually restored to that exact pre-format content by hand rather than reverted to `HEAD`, so the orchestrator's edit was preserved intact. `.planning/phases/01-foundation-rbac-auth-hardening-audit-trail/01-CONTEXT.md` (also pre-existing, uncommitted, and explicitly out of scope per this plan's instructions) could not be perfectly restored to its exact pre-format byte content since no snapshot of it existed prior to this session — it was left with only cosmetic Markdown-formatter noise (blank lines after headings, `*text*` → `_text_`) layered on top of its real pre-existing edit; the underlying content is unchanged and nothing was lost. All subsequent `npm run check:fix`/`vp check --fix` invocations in this plan were scoped to explicit file paths to prevent recurrence.
- Larastan (`composer types:check`) fails on a pre-existing, unrelated file (`app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php:20`, from Phase 1 plan 01-12) — logged in `.planning/phases/02-customer-queue-management/deferred-items.md`. `vendor/bin/phpstan analyse` scoped to only this plan's new files passes with 0 errors.
- `php artisan db:table customers` fails in this environment with "The intl PHP extension is required" (same pre-existing gap noted in Phase 1's `01-01-SUMMARY.md`) — verified the schema instead via `php artisan model:show Customer`, which confirmed `name`, `contact_number` (unique), `email`, `address` all present.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `customers` table, `Customer` model, and the live `NewVisit.vue` page are all in place and verified — Plan 02-02 (queue number generation) and 02-03 (Job Orders intake) can extend this same page and controller directly, per the plan's stated "resting state" hand-off.
- `frontline-staff` portal nav pattern (`resources/js/config/nav/frontline-staff.ts`) is established — Plan 02-02+ pages just add entries to this same file rather than inventing a new convention.
- No blockers. One deferred, pre-existing Larastan finding (see `deferred-items.md`) is unrelated to this plan and does not block Plan 02-02.

---
*Phase: 02-customer-queue-management*
*Completed: 2026-09-01*

## Self-Check: PASSED

All 15 plan files (13 created, 2 modified) plus `deferred-items.md` verified present on disk; all 3 task commits (`f1cda13`, `5a13bd2`, `2430e1e`) verified present in git log.
