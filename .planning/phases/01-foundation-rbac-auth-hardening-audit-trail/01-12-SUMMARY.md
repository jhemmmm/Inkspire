---
phase: 01-foundation-rbac-auth-hardening-audit-trail
plan: 12
subsystem: ui
tags: [inertia, vue, tabs, shadcn-vue, cache-invalidation, system-configuration]

# Dependency graph
requires:
  - phase: 01-foundation-rbac-auth-hardening-audit-trail (Plan 01-03)
    provides: system_configurations table, SystemConfiguration model (getInt/getBool/getArray/getString/invalidate), SystemConfigurationSeeder
  - phase: 01-foundation-rbac-auth-hardening-audit-trail (Plan 01-11)
    provides: Owner/Admin portal pattern (role:owner,admin route group, ownerNavItems, dedicated portal layout)
provides:
  - SystemConfigurationController (edit/update) with type-driven validation and immediate cache invalidation on save
  - SystemConfigValidationRules trait (integer/decimal/boolean/string/array rule sets keyed by the row's own `type` column)
  - owner/SystemConfiguration.vue Tabs UI (Security / Business Rules / File Handling) with per-row independent save
  - CONFIG-01 fully delivered — last requirement of Phase 1
affects: []

# Tech tracking
tech-stack:
  added: [shadcn-vue tabs component, shadcn-vue switch component]
  patterns:
    - "Route-model-binding by a human-facing key column (getRouteKeyName) instead of id, for owner-editable config rows"
    - "Per-row independent Inertia <Form> instead of one giant form, so each config value saves/validates independently"
    - "Array-typed config fields render as a comma-separated text Input, parsed into discrete value[] hidden inputs at render time so the field submits as a real array"
    - "Boolean-typed config fields use a local reactive v-model (not just default-value) so the Switch's visually-hidden checkbox reflects live toggle state for native FormData collection"

key-files:
  created:
    - app/Http/Controllers/Owner/SystemConfigurationController.php
    - app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php
    - app/Concerns/SystemConfigValidationRules.php
    - resources/js/pages/owner/SystemConfiguration.vue
    - resources/js/components/ui/tabs/*.vue
    - resources/js/components/ui/switch/Switch.vue
    - tests/Feature/Owner/SystemConfigurationTest.php
  modified:
    - app/Models/SystemConfiguration.php (added getRouteKeyName)
    - routes/owner.php
    - resources/js/config/nav/owner.ts

key-decisions:
  - "SystemConfigValidationRules::valueRules() returns the full field-keyed rules map (['value' => [...], optionally 'value.*' => [...]]) directly, matching ProfileValidationRules's exact shape, rather than being wrapped in another ['value' => ...] by the FormRequest — the FormRequest's rules() just returns $this->valueRules($type) directly, exactly as ProfileUpdateRequest::rules() returns $this->profileRules(...) directly."
  - "No Policy/authorize() override added — the route group's existing role:owner,admin middleware is the sole authorization mechanism for this screen, matching the threat model's stated disposition (T-01-01 mitigated by routes/owner.php grouping alone, no finer-grained Owner-vs-Admin split requested for config)."

requirements-completed: [CONFIG-01]

duration: 20min
completed: 2026-09-01
---

# Phase 01 Plan 12: System Configuration Screen Summary

**Owner/Admin CRUD screen over `system_configurations` — a Tabs-organized editor (Security / Business Rules / File Handling) where every save is validated by the row's own declared type and immediately invalidates the config cache, closing RESEARCH.md's Pitfall 5.**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-09-01T02:10:00Z (approx)
- **Completed:** 2026-09-01T02:30:00Z (approx)
- **Tasks:** 2 completed
- **Files modified:** 12 (5 backend, 7 frontend)

## Accomplishments
- `SystemConfigurationController::edit()` returns all 11 CONFIG-01 rows grouped by their `group` column (`security`, `business_rules`, `file_handling`), matching the UI-SPEC's 3 tabs with no client-side grouping logic
- `SystemConfigurationController::update()` validates each save against the row's own `type` column and calls `SystemConfiguration::invalidate($key)` immediately after persisting — the new value is live on the very next `getInt()`/`getBool()`/etc. read, with no stale cache and no app restart
- `owner/SystemConfiguration.vue` renders three tabs, each row as an independent `<Form>` with a type-appropriate input (Switch for boolean, numeric Input for integer/decimal, text Input for string, comma-separated Input parsed into `value[]` hidden inputs for array)
- CONFIG-01 is now fully delivered — this is the last of the 11 requirement IDs in Phase 1 (RBAC-01 through 08, AUDIT-01/02, CONFIG-01)

## Task Commits

Each task was committed atomically:

1. **Task 1: SystemConfigurationController + SystemConfigValidationRules + UpdateSystemConfigurationRequest** - `65c0607` (feat)
2. **Task 2 (RED): failing test for System Configuration screen** - `4131f96` (test)
2. **Task 2 (GREEN): SystemConfiguration.vue Tabs UI, nav entry** - `9feac63` (feat)

## TDD Gate Compliance

Task 2 was declared `tdd="true"`. Gate sequence verified in git log:
- RED: `4131f96` — `tests/Feature/Owner/SystemConfigurationTest.php` written and run; failed because `resources/js/pages/owner/SystemConfiguration.vue` did not exist yet (Inertia's testing assertion checks the page file exists on disk). 2 of the 3 tests passed immediately at this point because Task 1 had already implemented the full backend (controller/validation/cache-invalidation) — this is expected given the plan's backend/frontend task split, not an unexpected-pass RED violation.
- GREEN: `9feac63` — Vue page, shadcn `tabs`/`switch` components, and nav entry added; all 3 tests pass.
- No REFACTOR commit was needed (no cleanup required after GREEN).

## Files Created/Modified
- `app/Http/Controllers/Owner/SystemConfigurationController.php` - `edit()` (grouped list) and `update()` (single-key save + cache invalidation)
- `app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php` - delegates to `SystemConfigValidationRules::valueRules($configuration->type)`
- `app/Concerns/SystemConfigValidationRules.php` - type -> validation rules map (integer/decimal/boolean/string/array)
- `app/Models/SystemConfiguration.php` - added `getRouteKeyName(): string { return 'key'; }` for key-based route binding
- `routes/owner.php` - added `system-configuration.edit`/`.update` inside the existing `role:owner,admin` group
- `resources/js/pages/owner/SystemConfiguration.vue` - Tabs UI, per-row independent Form/save
- `resources/js/config/nav/owner.ts` - added 4th nav entry ("System Configuration")
- `resources/js/components/ui/tabs/*.vue`, `resources/js/components/ui/switch/Switch.vue` - shadcn-vue official-registry components
- `tests/Feature/Owner/SystemConfigurationTest.php` - 3 tests: grouped view, valid update takes effect immediately, invalid update rejected and not persisted

## Decisions Made
- `SystemConfigValidationRules::valueRules()` returns the complete field-keyed rules map directly (not double-wrapped), exactly mirroring `ProfileValidationRules`'s established shape.
- No new Policy class — route-group middleware (`role:owner,admin`) is the sole gate, per the threat model's stated mitigation for T-01-01.
- Boolean-typed rows use a local `reactive` `v-model` (not just `default-value`) so the Switch's underlying visually-hidden checkbox correctly reflects live toggle state for Inertia `<Form>`'s native FormData collection on submit. No boolean-type config currently exists in `SystemConfigurationSeeder` (all 11 seeded rows are integer/decimal/array), so this path is implemented for type-completeness per the plan's spec but is not exercised by the current seed data or tests.
- Array-typed rows render as a single comma-separated text `Input`, parsed on every render into discrete `<input type="hidden" name="value[]">` elements so the browser's native FormData serializes it as an actual array, matching the `array` validation rule server-side.

## Deviations from Plan

None - plan executed exactly as written. (One clarification, not a deviation: the FormRequest's `rules()` method returns `$this->valueRules($type)` directly rather than the plan's literal one-line pseudocode `['value' => $this->valueRules($type)]`, in order to satisfy the plan's own explicit instruction to match `ProfileValidationRules`'s exact shape — see Decisions Made.)

## Issues Encountered
- `composer types:check` (Larastan) fails in this environment with `Undefined constant "Larastan\Larastan\LARAVEL_VERSION"` plus a `phpstan_turbo` dynamic library GLIBC mismatch warning. This is a pre-existing, previously-documented environmental issue (see `.planning/phases/01-foundation-rbac-auth-hardening-audit-trail/deferred-items.md`, logged during Plan 01-03), reproduced independent of any file this plan touched. Not fixed — out of scope per the scope-boundary rule. `vendor/bin/pint --dirty`, `php artisan test --compact` (full suite, 62 passed / 4 pre-existing skips / 0 failed), and `npm run types:check` (vue-tsc) all pass cleanly.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- All 11 Phase 1 requirement IDs (RBAC-01 through 08, AUDIT-01/02, CONFIG-01) are now fully delivered.
- Phase 1 foundation (RBAC roles, portals, auth hardening, audit trail, system configuration) is ready as a dependency substrate for Phase 2+.
- No blockers introduced by this plan.

---
*Phase: 01-foundation-rbac-auth-hardening-audit-trail*
*Completed: 2026-09-01*

## Self-Check: PASSED

All created files verified present on disk; all task commit hashes (65c0607, 4131f96, 9feac63) and the summary docs commit (e7b06a8) verified present in `git log`.
