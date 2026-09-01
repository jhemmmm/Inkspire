---
phase: 01-foundation-rbac-auth-hardening-audit-trail
plan: 11
subsystem: auth
tags: [rbac, policy, authorization, laravel-policy]

# Dependency graph
requires:
  - phase: 01-foundation-rbac-auth-hardening-audit-trail
    provides: "Plan 01-06's Owner User Management screen with a coarse self-only deactivation guard"
  - phase: 01-foundation-rbac-auth-hardening-audit-trail
    provides: "Plan 01-10's audit trail viewer confirming audit_trail rows for user mutations"
provides:
  - "UserPolicy::deactivate()/reactivate() enforcing the Owner-vs-Admin authorization matrix"
  - "PATCH owner/users/{user}/reactivate route + controller action + Vue button, completing the deactivate/reactivate round trip"
affects: [rbac, owner-portal, admin-portal]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Laravel Policy auto-discovery for the User model (no manual Gate::policy() registration needed, no AuthServiceProvider in this app)"
    - "FormRequest::authorize() delegates to $this->user()->can('ability', $this->route('model'))"

key-files:
  created:
    - app/Policies/UserPolicy.php
    - app/Http/Requests/Owner/ReactivateUserRequest.php
  modified:
    - app/Http/Requests/Owner/DeactivateUserRequest.php
    - app/Http/Controllers/Owner/UserManagementController.php
    - routes/owner.php
    - resources/js/pages/owner/UserManagement.vue
    - tests/Feature/Owner/UserManagementTest.php

key-decisions:
  - "UserPolicy only implements deactivate()/reactivate() (not the full CRUD policy boilerplate artisan generated) since no other User ability exists yet in this app"
  - "reactivate() delegates to deactivate() rather than duplicating the Owner/Admin matrix, since the rule is identical in both directions"
  - "Reactivate button uses a plain (non-destructive) secondary-variant Button with no AlertDialog confirmation, per UI-SPEC's framing that no other destructive actions exist in this phase"

patterns-established:
  - "Owner-vs-Admin authorization matrix (no self-action; Owner acts on anyone but self; Admin acts only on the 5 staff roles) now lives in UserPolicy as the canonical rule for any future User-targeting action"

requirements-completed: [RBAC-07]

# Metrics
duration: 15min
completed: 2026-09-01
---

# Phase 01 Plan 11: Reactivate + Owner/Admin Authorization Refinement Summary

**UserPolicy narrows deactivate/reactivate to the real Owner-vs-Admin matrix (no self-action, Admin limited to the 5 staff roles) and adds the reactivate action the UI already promised**

## Performance

- **Duration:** ~15 min
- **Completed:** 2026-09-01
- **Tasks:** 2 completed
- **Files modified:** 7 (2 created, 5 modified)

## Accomplishments
- `UserPolicy::deactivate()`/`reactivate()` replace Plan 01-06's coarse self-only guard with the real Owner-vs-Admin rule: no self-action in either direction, Owner may act on Admin/staff (not self), Admin may act only on the 5 staff roles (not Owner, not another Admin, not self)
- `DeactivateUserRequest::authorize()` now delegates to the policy instead of a manual `isNot()` check
- New `ReactivateUserRequest` + `UserManagementController::reactivate()` let a deactivated account be brought back via `PATCH owner/users/{user}/reactivate`, subject to the same policy rule
- `UserManagement.vue` gained a non-destructive "Reactivate Account" button on inactive rows, fulfilling the confirmation dialog's promise that deactivation "can be reversed by reactivating the account"
- Reactivation is captured in `audit_trail` automatically via the existing `AuditObserver` — no new audit code needed

## Task Commits

Each task was executed test-first (RED then GREEN), matching this project's established TDD commit convention (see Plans 01-07/01-08/01-09):

1. **Task 1: UserPolicy + narrowed DeactivateUserRequest authorization**
   - `9f475ec` test(01-11): add failing tests for narrowed deactivate authorization
   - `caccf8b` feat(01-11): narrow deactivate authorization via UserPolicy
2. **Task 2: Reactivate action — controller, request, route, UI button**
   - `c1175bf` test(01-11): add failing test for reactivate action
   - `bfb5aaf` feat(01-11): add reactivate action for deactivated accounts

_Note: this plan's tasks were originally committed as single combined `feat` commits during execution; they were restructured into separate `test`/`feat` commits (via non-destructive `git reset --soft` + selective re-staging) to match this repo's established TDD commit pattern before finalizing. Final working-tree content is byte-identical to the original combined-commit state (verified via `git diff`)._

## Files Created/Modified
- `app/Policies/UserPolicy.php` - `deactivate()`/`reactivate()` implementing the Owner-vs-Admin authorization matrix
- `app/Http/Requests/Owner/DeactivateUserRequest.php` - `authorize()` now delegates to `UserPolicy::deactivate()`
- `app/Http/Requests/Owner/ReactivateUserRequest.php` - `authorize()` delegates to `UserPolicy::reactivate()`
- `app/Http/Controllers/Owner/UserManagementController.php` - new `reactivate()` action mirroring `deactivate()`
- `routes/owner.php` - `PATCH owner/users/{user}/reactivate` route
- `resources/js/pages/owner/UserManagement.vue` - "Reactivate Account" button for inactive rows
- `tests/Feature/Owner/UserManagementTest.php` - 4 new tests: admin-vs-owner/admin denial, admin-vs-staff allowance, owner-vs-admin allowance, and full reactivate round trip with audit assertion

## Decisions Made
- Kept the artisan-generated `UserPolicy` trimmed to only `deactivate()`/`reactivate()` — the other CRUD policy stubs (`viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`) were removed since no such abilities exist anywhere in the app yet, avoiding dead code
- `reactivate()` reuses `deactivate()`'s logic by delegation rather than duplicating the role matrix, per the plan's explicit instruction
- No `AlertDialog` confirmation on the reactivate button, since it's a corrective (non-destructive) action, consistent with the UI-SPEC's framing

## Deviations from Plan

None - plan executed exactly as written. (See Task Commits note above for a process-only git-history restructuring, not a functional deviation.)

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- RBAC-07 is now fully satisfied: authorization is narrowed to the real Owner-vs-Admin matrix, and the deactivate/reactivate round trip works end-to-end with audit coverage
- Phase 01 has 12 plans total; this was plan 11 of 12 — one plan remains before the phase can transition
- No blockers identified for downstream phases building on `users`/RBAC

---
*Phase: 01-foundation-rbac-auth-hardening-audit-trail*
*Completed: 2026-09-01*

## Self-Check: PASSED

All created/modified files confirmed present on disk; all 4 task commit hashes (9f475ec, caccf8b, c1175bf, bfb5aaf) confirmed present in git log.
