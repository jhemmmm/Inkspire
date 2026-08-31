---
phase: 01-foundation-rbac-auth-hardening-audit-trail
plan: 04
subsystem: auth
tags: [rbac, fortify, inertia, middleware, vue, wayfinder]

# Dependency graph
requires:
  - phase: 01-01
    provides: UserRole enum with portalRoute(), users.role column/cast, per-role UserFactory states
provides:
  - EnsureUserHasRole middleware (role alias) enforcing server-side 403 role boundaries
  - Custom Fortify LoginResponse redirecting each role to its own portal via UserRole::portalRoute()
  - Branded Inertia errors/Forbidden 403 page wired via bootstrap/app.php's exceptions->respond()
  - routes/owner.php (role:owner,admin group) with owner.dashboard as the first live portal route
  - Parametrized AppSidebar/AppSidebarLayout/AppLayout (items/navItems prop) reused by every future portal
  - owner/Dashboard.vue as the first dedicated-portal page
affects: [01-09 (remaining 6 role portals replicate this exact routes/owner.php + nav-config + Dashboard.vue pattern)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "role: middleware alias (variadic string ...$roles, abort_if(403)) applied at route-group level, never per-controller"
    - "Fortify LoginResponse contract override bound in FortifyServiceProvider::register() singleton, redirecting via $user->role->portalRoute()"
    - "Inertia 403 rendered globally via $exceptions->respond() in bootstrap/app.php, not per-route try/catch"
    - "Parametrized sidebar: AppSidebar accepts optional items prop (falls back to defaultNavItems), threaded through AppSidebarLayout's navItems prop and AppLayout's navItems prop — avoids per-role sidebar component duplication"
    - "Per-role nav config lives in resources/js/config/nav/{role}.ts, imported by that role's Dashboard.vue via defineOptions({ layout: { navItems, breadcrumbs } })"

key-files:
  created:
    - app/Http/Middleware/EnsureUserHasRole.php
    - app/Http/Responses/LoginResponse.php
    - resources/js/pages/errors/Forbidden.vue
    - resources/js/config/nav/owner.ts
    - resources/js/pages/owner/Dashboard.vue
    - routes/owner.php
    - tests/Feature/RoleBoundaryTest.php
  modified:
    - bootstrap/app.php
    - app/Providers/FortifyServiceProvider.php
    - resources/js/app.ts
    - resources/js/components/AppSidebar.vue
    - resources/js/layouts/app/AppSidebarLayout.vue
    - resources/js/layouts/AppLayout.vue
    - routes/web.php
    - tests/Feature/Auth/AuthenticationTest.php

key-decisions:
  - "Routed errors/ page names through AuthLayout in app.ts (alongside auth/) so Forbidden.vue's defineOptions({ layout: { title, description } }) actually renders as the intended centered single-message card, instead of silently falling through to the sidebar AppLayout default"
  - "Ran php artisan wayfinder:generate --with-form (not the bare command) to regenerate resources/js/routes and resources/js/actions, matching vite.config.ts's formVariants: true — the bare command strips .form() from every existing route helper"

patterns-established:
  - "Pattern 1: routes/{role}.php + role:{roles} middleware group + {role}/Dashboard.vue + config/nav/{role}.ts is the exact 4-file shape Plan 01-09 replicates for the remaining 6 roles"

requirements-completed: [RBAC-01, RBAC-02]

# Metrics
duration: 35min
completed: 2026-08-31
---

# Phase 1 Plan 04: Role Middleware, Login Redirect & Owner Portal Summary

**Server-side `role:` middleware, a Fortify `LoginResponse` override redirecting by `UserRole::portalRoute()`, a branded Inertia 403 page, and the first live dedicated portal (`owner/Dashboard.vue`) behind a parametrized sidebar shell.**

## Performance

- **Duration:** ~35 min
- **Started:** 2026-08-31T17:15:00Z (approx)
- **Completed:** 2026-08-31T17:41:23Z
- **Tasks:** 2
- **Files modified:** 14 (7 created, 7 modified) plus regenerated `resources/js/routes/`/`resources/js/actions/` (gitignored, not committed)

## Accomplishments
- A factory-default (Owner) user logging in via `POST /login` is redirected to `route('owner.dashboard')`, not the generic `/dashboard`
- Direct URL access to `/owner/dashboard` by a non-owner/admin role (tested with a Cashier) returns HTTP 403 rendering the branded `errors/Forbidden` Inertia page — enforced server-side, never nav-hidden-only
- Sidebar/layout plumbing (`AppSidebar` → `AppSidebarLayout` → `AppLayout`) is now parametrized via an optional `items`/`navItems` prop chain, reused (not duplicated) for every future role portal
- Generic `/dashboard` route remains untouched and still returns 200 for any authenticated user (verified via the existing `DashboardTest` in the full suite run)

## Task Commits

Each task was committed atomically:

1. **Task 1: Role middleware, 403 handling, role-based login redirect** - `794831e` (feat)
2. **Task 2: Parametrized sidebar plumbing, routes/owner.php, Owner Dashboard, RoleBoundaryTest** - `94ae3ad` (feat)

**Plan metadata:** _pending_ (this commit)

## Files Created/Modified
- `app/Http/Middleware/EnsureUserHasRole.php` - `role:` alias middleware, `abort_if(403)` on role mismatch
- `app/Http/Responses/LoginResponse.php` - Fortify `LoginResponse` override, redirects via `$user->role->portalRoute()`
- `app/Providers/FortifyServiceProvider.php` - binds the custom `LoginResponse` singleton in `register()`
- `bootstrap/app.php` - registers the `role` middleware alias; `$exceptions->respond()` renders `errors/Forbidden` on any 403
- `resources/js/pages/errors/Forbidden.vue` - branded 403 page, UI-SPEC copy, "Return to your dashboard" link
- `resources/js/app.ts` - routes `errors/` page names through `AuthLayout` (matches `auth/` pages) so the centered card layout actually applies
- `resources/js/components/AppSidebar.vue` - optional `items` prop, falls back to `defaultNavItems`
- `resources/js/layouts/app/AppSidebarLayout.vue` - optional `navItems` prop, forwarded to `AppSidebar`
- `resources/js/layouts/AppLayout.vue` - optional `navItems` prop, forwarded to the inner sidebar layout
- `resources/js/config/nav/owner.ts` - `ownerNavItems` (Dashboard link) for the Owner portal sidebar
- `resources/js/pages/owner/Dashboard.vue` - first dedicated-portal page, uses `ownerNavItems` + placeholder widgets
- `routes/owner.php` - `role:owner,admin` group, `owner.dashboard` named route
- `routes/web.php` - `require __DIR__.'/owner.php';`
- `tests/Feature/Auth/AuthenticationTest.php` - updated redirect assertion to `owner.dashboard`
- `tests/Feature/RoleBoundaryTest.php` - covers RBAC-01 (owner login redirect) and RBAC-02 (cashier blocked with 403 + `errors/Forbidden` component)

## Decisions Made
- `errors/` page names route through `AuthLayout` in `app.ts` (not the default sidebar `AppLayout`) so the 403 page's `defineOptions({ layout: { title, description } })` actually takes effect as a centered single-message card — the plan's action text specified this exact `defineOptions` shape but didn't call out the `app.ts` switch needed to make it render correctly; added as a minimal, necessary companion change (Rule 3).
- Regenerated Wayfinder output with `--with-form` after adding `routes/owner.php`, matching `vite.config.ts`'s `formVariants: true` plugin option — the bare `wayfinder:generate` command (no flags) strips `.form()` from every existing route helper and would have broken every `<Form v-bind="...form()">` page in the app (caught via `npm run types:check` before committing, never landed in a commit since `resources/js/routes`/`resources/js/actions` are gitignored).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] `errors/` page names not routed to a layout matching Forbidden.vue's defineOptions shape**
- **Found during:** Task 1 (Forbidden.vue creation)
- **Issue:** The plan's action text specifies `defineOptions({ layout: { title: 'Access denied', description: '' } })` for `Forbidden.vue`, matching `auth/ForgotPassword.vue`'s exact shape — but `app.ts`'s layout-resolution switch only special-cased `auth/*` and `settings/*` page names; `errors/Forbidden` would have silently fallen through to the default sidebar `AppLayout`, which doesn't consume `title`/`description` props at all.
- **Fix:** Added `case name.startsWith('errors/'):` alongside `auth/` in `resources/js/app.ts`'s layout switch, routing it to the same `AuthLayout`.
- **Files modified:** `resources/js/app.ts`
- **Verification:** `npm run types:check` passes; page renders via the centered `AuthSimpleLayout` shell as UI-SPEC intends.
- **Committed in:** `794831e` (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Necessary for the plan's own specified `Forbidden.vue` design to actually take visual effect. No scope creep — single line added to an already-in-scope file's existing switch statement.

## Issues Encountered
- Running `php artisan wayfinder:generate` without `--with-form` regenerated `resources/js/routes`/`resources/js/actions` (gitignored) missing `.form()` on every existing route helper, breaking `npm run types:check` across unrelated pages (`Login.vue`, `Profile.vue`, `Security.vue`, etc.). Caught before any commit; re-ran with `--with-form` to match `vite.config.ts`'s `formVariants: true`, confirmed clean `types:check`.
- Running `npm run check:fix` (to format the two touched `.vue` files) reformatted the entire repository's Markdown/JSON files (`.planning/**`, `.claude/skills/**`, `CLAUDE.md`, `boost.json`, `.mcp.json`) via its repo-wide Prettier glob, and also stripped a pre-existing uncommitted one-line addition in `01-CONTEXT.md`. All out-of-scope reformats were reverted file-by-file via `git checkout --`; the pre-existing `01-CONTEXT.md` content line (about `app/Support/`/`app/Listeners/` directory approval) was manually re-applied after the revert. No unintended repo-wide formatting change was committed.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- The `routes/{role}.php` + `role:{roles}` middleware group + `{role}/Dashboard.vue` + `config/nav/{role}.ts` shape proven here is the exact pattern Plan 01-09 replicates for the remaining 6 roles (Frontline Staff, Artist, Cashier, Production Staff, Accounting Staff, and Admin sharing the Owner portal).
- No blockers. Full backend suite green (35 tests, 31 passed, 4 skipped — pre-existing 2FA-feature skips, unrelated to this plan); `npm run types:check` clean.

---
*Phase: 01-foundation-rbac-auth-hardening-audit-trail*
*Completed: 2026-08-31*
