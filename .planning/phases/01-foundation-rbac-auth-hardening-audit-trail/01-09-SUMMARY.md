---
phase: 01-foundation-rbac-auth-hardening-audit-trail
plan: 09
subsystem: auth
tags: [rbac, inertia, vue, middleware, pest, wayfinder]

# Dependency graph
requires:
  - phase: 01-foundation-rbac-auth-hardening-audit-trail
    provides: "Plan 01-04's owner.php route pattern, EnsureUserHasRole middleware, errors/Forbidden.vue, and the Owner-only seed of RoleBoundaryTest.php"
provides:
  - "routes/portals.php with 5 independent role-gated route groups (frontline_staff, artist, cashier, production_staff, accounting_staff)"
  - "5 empty portal Dashboard.vue pages (frontline-staff, artist, cashier, production-staff, accounting-staff), each with navItems: []"
  - "Full 7-role x 7-portal RoleBoundaryTest.php matrix proving every role reaches its own portal and is 403'd from every other role's portal"
affects: [rbac, portal-scaffolding, later-phase-role-nav]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "One Route::middleware(['auth','role:{value}'])->prefix('{slug}')->name('{slug}.') group per role, each independently gated — never inherited/shared middleware across portal namespaces"
    - "Empty portal shell: defineOptions({ layout: { navItems: [], breadcrumbs: [...] } }) for roles whose real screens land in later phases"
    - "Pest dataset-driven cross-role boundary test: [factoryState, ownPortalRouteName] tuples iterated in a nested loop for full N x N 403 coverage"

key-files:
  created:
    - routes/portals.php
    - resources/js/pages/frontline-staff/Dashboard.vue
    - resources/js/pages/artist/Dashboard.vue
    - resources/js/pages/cashier/Dashboard.vue
    - resources/js/pages/production-staff/Dashboard.vue
    - resources/js/pages/accounting-staff/Dashboard.vue
  modified:
    - routes/web.php
    - tests/Feature/RoleBoundaryTest.php

key-decisions:
  - "Reused Plan 01-04's route-group-per-portal pattern verbatim for the 5 new roles, keeping middleware role value (snake_case) and URL/route-name slug (kebab-case) as two intentionally distinct strings"
  - "Cross-role 403 test implemented as one test with a nested loop over the 7-entry dataset rather than 42 separate Pest test cases, per plan's explicit acceptance of this structure for RBAC-02 coverage"

requirements-completed: [RBAC-01, RBAC-02]

# Metrics
duration: 8min
completed: 2026-09-01
---

# Phase 01 Plan 09: Remaining 5 Role Portals + Full 7-Role Boundary Matrix Summary

**Scaffolded the last 5 role-gated portals (Frontline Staff, Artist, Cashier, Production Staff, Accounting Staff) as empty dashboard shells and extended RoleBoundaryTest.php into a full 7-role x 7-portal parametrized matrix, closing out RBAC-01 and RBAC-02 for all 7 roles.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-09-01T01:31:00Z
- **Completed:** 2026-09-01T01:39:00Z
- **Tasks:** 2 completed
- **Files modified:** 8 (7 created, 2 modified — routes/web.php and tests/Feature/RoleBoundaryTest.php overlap with the created-file count as "modified")

## Accomplishments
- Every one of the 7 roles now has its own dedicated portal route, each independently gated by `role:{value}` middleware — no shared/inherited authorization assumption anywhere in the route set (closes RESEARCH.md Pitfall 4).
- `RoleBoundaryTest.php` now proves, for all 7 roles, both "reaches own portal" (200 OK) and "blocked from every other role's portal" (403, `errors/Forbidden`) — 10 tests, 57 assertions, all passing.
- Confirmed via `php artisan route:list` that each new route (`frontline-staff.dashboard`, `artist.dashboard`, `cashier.dashboard`, `production-staff.dashboard`, `accounting-staff.dashboard`) carries its own distinct `role:` middleware argument matching `UserRole`'s backing value.

## Task Commits

Each task was committed atomically:

1. **Task 1: 5 remaining role route groups and empty portal Dashboard pages** - `8c49ee4` (feat)
2. **Task 2: Full 7-role parametrized RoleBoundaryTest** - `e3b838f` (test)

_Note: Task 2 was marked `tdd="true"` in the plan, but its underlying implementation (the 5 portal routes) was built in Task 1 of this same plan — see TDD Gate Compliance below for why the test passed on first run instead of following a RED-then-GREEN sequence._

## Files Created/Modified
- `routes/portals.php` - 5 independent `Route::middleware(['auth','role:{value}'])` groups, one per remaining role
- `routes/web.php` - added `require __DIR__.'/portals.php';` after the existing `owner.php` require
- `resources/js/pages/frontline-staff/Dashboard.vue` - empty portal home, `navItems: []`, "Frontline Staff Dashboard" Display title
- `resources/js/pages/artist/Dashboard.vue` - empty portal home, `navItems: []`, "Artist Dashboard" Display title
- `resources/js/pages/cashier/Dashboard.vue` - empty portal home, `navItems: []`, "Cashier Dashboard" Display title
- `resources/js/pages/production-staff/Dashboard.vue` - empty portal home, `navItems: []`, "Production Staff Dashboard" Display title
- `resources/js/pages/accounting-staff/Dashboard.vue` - empty portal home, `navItems: []`, "Accounting Staff Dashboard" Display title
- `tests/Feature/RoleBoundaryTest.php` - extended with a 7-entry `[factoryState, ownPortalRouteName]` dataset, an "own portal" test, and a nested-loop cross-role 403 test

## Decisions Made
- Kept the `role:{value}` (snake_case enum backing value) vs. URL/route-name slug (kebab-case) distinction exactly as specified in the plan — e.g. `role:frontline_staff` middleware on the `frontline-staff.*` route group — since conflating the two would silently break either the middleware role check or the `portalRoute()` route-name lookup in `UserRole`.
- Used plain placeholder body text ("There's nothing here yet — your tools will appear in a later phase.") instead of the `PlaceholderPattern` grid used on Owner's dashboard, per UI-SPEC's distinction between Owner's dashboard (real content coming in later plans) and these 5 intentionally-empty portals.
- Regenerated Wayfinder with `--with-form` (matching the established project convention from Plan 01-04, recorded in STATE.md) so the new route helper modules stay consistent with `vite.config.ts`'s `formVariants: true`, even though these dashboard-only routes don't currently need `.form()`.

## Deviations from Plan

None - plan executed exactly as written.

## TDD Gate Compliance

Task 2 was declared `tdd="true"`, but its `<action>` only ever touches `tests/Feature/RoleBoundaryTest.php` — there is no separate `<implementation>` step, because the routes under test (the 5 new portal route groups) were already built and committed in Task 1 of this same plan. As a result, running the extended test suite passed immediately (10/10 tests, 57 assertions) rather than following a RED-then-GREEN sequence. This is expected given the plan's task ordering (implementation in Task 1, exhaustive test coverage in Task 2), not a violation of the fail-fast RED rule — that rule guards against a test passing before *any* implementation exists; here the implementation existed and was verified by a prior, separately-committed task. No `git log` RED/GREEN gate pair exists for this task; there is a single `test(01-09): ...` commit (`e3b838f`) instead.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All 7 roles now have a dedicated portal route and land there on login (RBAC-01 complete for every role).
- All 7 roles are server-side blocked from every other role's portal, proven by an exhaustive automated test (RBAC-02 complete for every role).
- The 5 new empty portal shells are ready for later-phase plans to populate with role-specific nav items and real screens (Frontline queue/intake, Artist workflow, Cashier POS, Production monitoring, Accounting AR/expenses) without any further route-gating work needed.

---
*Phase: 01-foundation-rbac-auth-hardening-audit-trail*
*Completed: 2026-09-01*
