---
phase: 01-foundation-rbac-auth-hardening-audit-trail
plan: 06
subsystem: auth
tags: [rbac, inertia, vue, wayfinder, audit-trail, shadcn-vue, alert-dialog]

# Dependency graph
requires:
    - phase: 01-foundation-rbac-auth-hardening-audit-trail
      provides: 'User model with #[ObservedBy(AuditObserver::class)] (Plan 01-01), role:owner,admin middleware and owner portal shell (Plan 01-04)'
provides:
    - 'Owner/Admin User Management screen (list + deactivate) as a real database-backed vertical slice'
    - 'Proof that AuditObserver captures a real Eloquent mutation (User.is_active flip) with zero controller-side audit-logging code'
    - 'alert-dialog shadcn-vue primitives installed for future destructive-confirmation UI (System Configuration write-offs, etc.)'
affects: [01-07, 01-11, audit-trail-ui, system-configuration]

# Tech tracking
tech-stack:
    added:
        [
            'shadcn-vue alert-dialog primitives (resources/js/components/ui/alert-dialog/)',
        ]
    patterns:
        - 'forceFill()->save() for mutating attributes intentionally excluded from #[Fillable] (is_active), never update()'
        - 'AlertDialog (not plain Dialog) reserved for irreversible/destructive confirmations, wrapping an Inertia <Form v-bind="Controller.action.form(id)">'

key-files:
    created:
        - app/Http/Controllers/Owner/UserManagementController.php
        - app/Http/Requests/Owner/DeactivateUserRequest.php
        - resources/js/pages/owner/UserManagement.vue
        - resources/js/components/ui/alert-dialog/*.vue
        - tests/Feature/Owner/UserManagementTest.php
    modified:
        - routes/owner.php
        - resources/js/config/nav/owner.ts
        - package.json
        - package-lock.json

key-decisions:
    - "Used forceFill(['is_active' => false])->save() instead of update() because is_active is deliberately outside User's #[Fillable] list (Plan 01-01) — update() would silently no-op"
    - 'DeactivateUserRequest::authorize() blocks self-deactivation (self-lockout guard); the finer Owner-vs-Admin authorization rule is deferred to Plan 01-11 per RESEARCH.md Open Question 2'
    - "Reactivation UI intentionally omitted from this task — deferred to Plan 01-11, inactive rows only show a 'Deactivated' badge"
    - 'shadcn-vue CLI install of alert-dialog also normalized package.json/package-lock.json version ranges for @lucide/vue and reka-ui to match already-installed node_modules versions (1.38.0 / 2.10.4) — no new packages, no functional change, corrected pre-existing drift'

patterns-established:
    - 'Deactivate-not-delete: destructive user-management actions flip a boolean via forceFill, never call delete()'
    - 'Automatic-audit-by-construction: no controller in this codebase calls AuditLog::create() or AuditLogger:: directly for model mutations — the AuditObserver on the model is the only path'

requirements-completed: [RBAC-07]

# Metrics
duration: 15min
completed: 2026-08-31
---

# Phase 01 Plan 06: Owner User Management Summary

**Owner/Admin User Management screen backed by a real `forceFill()->save()` deactivate mutation, captured into `audit_trail` automatically via the existing `AuditObserver` with zero manual logging code in the controller.**

## Performance

- **Duration:** ~15 min
- **Completed:** 2026-08-31T18:01:58Z
- **Tasks:** 1 completed
- **Files modified:** 18 (13 created, 5 modified, including 9 generated alert-dialog primitive files)

## Accomplishments

- `UserManagementController@index` lists all users (id, name, email, role, is_active) on a real Owner-portal Inertia page
- `UserManagementController@deactivate` flips `is_active` to `false` via `forceFill()->save()` — never `delete()` — and the mutation is captured into `audit_trail` with `action = 'updated'` purely because `User` carries `#[ObservedBy(AuditObserver::class)]`, proving D-01's "adopt the trait, get audit coverage for free" promise
- Self-deactivation is blocked at the `DeactivateUserRequest::authorize()` layer (403, not silently ignored)
- `owner/UserManagement.vue` renders a plain HTML table with an `alert-dialog`-confirmed "Deactivate Account" destructive action per the UI-SPEC's exact copy contract
- This closes the Walking Skeleton loop: login → role redirect → portal → real DB mutation → automatic audit capture, proven by one feature test file

## Task Commits

1. **Task: UserManagementController + DeactivateUserRequest + UserManagement.vue, end-to-end** - `b701f13` (feat)

**Plan metadata:** pending (docs: complete plan)

## Files Created/Modified

- `app/Http/Controllers/Owner/UserManagementController.php` - `index()` (Inertia list) and `deactivate()` (forceFill is_active=false, Inertia toast flash, `back()`)
- `app/Http/Requests/Owner/DeactivateUserRequest.php` - `authorize()` blocks self-deactivation; `rules()` returns `[]` (route-model-bound `{user}` is the only input)
- `routes/owner.php` - added `owner.users.index` (GET) and `owner.users.deactivate` (PATCH) inside the existing `role:owner,admin` group
- `resources/js/config/nav/owner.ts` - added "User Management" nav item using the Wayfinder-generated `owner/users` `index()` route helper
- `resources/js/pages/owner/UserManagement.vue` - user list table + `AlertDialog`-confirmed deactivate action wired to `UserManagementController.deactivate.form(user.id)`
- `resources/js/components/ui/alert-dialog/*` - shadcn-vue alert-dialog primitives installed via official registry (`npx shadcn-vue@latest add alert-dialog --yes`), no vetting required per UI-SPEC
- `tests/Feature/Owner/UserManagementTest.php` - 3 tests: view list, deactivate flips `is_active` + writes audited `updated` row, self-deactivation forbidden
- `package.json` / `package-lock.json` - version-range normalization for `@lucide/vue` and `reka-ui` (see Decisions)

## Decisions Made

- `forceFill()` over `update()` — see key-decisions above; this is the load-bearing detail that makes the deactivate mutation actually take effect given `is_active` is outside `User`'s `#[Fillable]` list
- Self-deactivation guard lives in `DeactivateUserRequest::authorize()`, not the controller, keeping the controller free of any authorization branching
- No Reactivate button added for deactivated users (explicitly deferred to Plan 01-11 per plan instructions)

## Deviations from Plan

None — plan executed as written. One incidental side effect worth noting (not a deviation from behavior, just bookkeeping): installing `alert-dialog` via the shadcn-vue CLI updated `package.json`/`package-lock.json` version-range constraints for `@lucide/vue` (`^1.17.0` → `^1.38.0`) and `reka-ui` (`^2.9.8` → `^2.10.4`) to match versions already present in `node_modules` — no new packages were installed and no `node_modules` content changed (verified: resolved versions were already `1.38.0`/`2.10.4` before this task ran).

## Issues Encountered

- `composer types:check` (Larastan/PHPStan) fails with a pre-existing, unrelated environment error: the `phpstan_turbo` native extension binary requires `GLIBC_2.33`, which is not present on this host, and a resulting `Undefined constant Larastan\Larastan\LARAVEL_VERSION` error. This is not caused by this plan's changes (verified the error is a binary/glibc mismatch, not a code-level static-analysis failure) and is out of scope per the deviation rules' scope boundary — logged here for visibility, not fixed.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- The Walking Skeleton (login → role redirect → portal → real DB mutation → automatic audit capture) is now proven end-to-end for Owner/Admin User Management.
- Plan 01-11 should add: Reactivate-account UI, finer Owner-vs-Admin authorization split (RESEARCH.md Open Question 2).
- Pre-existing, unrelated: `composer types:check` / Larastan is broken in this environment due to a glibc/native-extension mismatch — needs environment-level investigation before it can gate future plans' static analysis.

## Self-Check: PASSED

All created files verified present; commit `b701f13` verified in git log.

---

_Phase: 01-foundation-rbac-auth-hardening-audit-trail_
_Completed: 2026-08-31_
