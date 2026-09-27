---
phase: 02-customer-queue-management
plan: 04
subsystem: queue-management
tags: [laravel, inertia, vue3, wayfinder, reka-ui, pest]

# Dependency graph
requires:
    - phase: 02-customer-queue-management (plan 02-01)
      provides: 'customers table + Customer model, frontline-staff route group/portal shell, Form Request + Validation Concern trait pattern'
    - phase: 02-customer-queue-management (plan 02-02)
      provides: 'queue_entries/job_orders tables, QueueStatus/JobOrderType/JobOrderStatus enums, QueueEntryController::store(), JobOrderValidationRules trait'
provides:
    - 'QueueEntryController::index()/callNext()/markDone()/addJobOrder() — the manual Waiting->Serving->Done workflow (D-05/D-08) and the always-available add-job-order-to-any-visit action (D-15/D-18)'
    - 'resources/js/pages/frontline-staff/QueueList.vue — internal, authenticated queue list with contextual transition buttons and a per-row Add Job Order dialog, the third frontline-staff nav destination'
    - 'app/Concerns/JobOrderValidationRules::jobOrderRules() — the flat, single-row validation variant reused by AddJobOrderRequest'
affects: [02-05]

# Tech tracking
tech-stack:
    added: []
    patterns:
        - 'Server-side abort_unless() on the current DB status, not client-side button visibility, is the actual state-machine-bypass control (T-02-12) — proven by dedicated 422 tests for skipped-stage transitions'
        - "reka-ui form primitives (RadioGroup) accept a `name` prop that renders a hidden native input mirroring their v-model value when nested inside a real <form> element — lets Inertia's uncontrolled <Form> component (native FormData serialization) correctly submit a RadioGroup selection without switching to useForm(), even across a Dialog's Teleported DOM subtree"

key-files:
    created:
        - app/Http/Requests/FrontlineStaff/UpdateQueueEntryStatusRequest.php
        - app/Http/Requests/FrontlineStaff/AddJobOrderRequest.php
        - tests/Feature/FrontlineStaff/QueueStatusTransitionTest.php
        - tests/Feature/FrontlineStaff/AddJobOrderToVisitTest.php
        - resources/js/pages/frontline-staff/QueueList.vue
    modified:
        - app/Concerns/JobOrderValidationRules.php
        - app/Http/Controllers/FrontlineStaff/QueueEntryController.php
        - routes/portals.php
        - resources/js/config/nav/frontline-staff.ts

key-decisions:
    - "addJobOrder() has zero status precondition on the target QueueEntry by design (D-15/D-18) — verified by a dedicated test asserting a Done entry's job_orders count increases by one"
    - "QueueList.vue's per-row Add Job Order dialog uses Inertia's <Form> component (not useForm()) per the plan's explicit instruction, with a small local reactive map (entry id -> 'type_a'|'type_b') driving only the conditional file-field visibility; the RadioGroup's name='type' prop supplies the actual submitted value via reka-ui's native hidden-input mirror, so no controlled-form JS state duplication was needed for the actual POST body"

patterns-established:
    - 'Pattern: state-machine transition endpoints (callNext/markDone) use abort_unless(...422...) against the current enum value before mutating, paired with a body-less FormRequest whose target state is implied entirely by which named route was hit'

requirements-completed: [QUEUE-03, QUEUE-04]

# Metrics
duration: ~10min
completed: 2026-09-02
---

# Phase 2 Plan 04: Internal Queue List & Status Workflow Summary

**Frontline Staff internal queue view with manual Waiting->Serving->Done transitions (server-enforced via abort_unless, not just hidden buttons) and a per-row Add Job Order dialog that works on Done visits exactly as well as Waiting ones.**

## Performance

- **Duration:** ~10 min (task work; excludes upfront context-loading reads)
- **Started:** 2026-09-02T00:11:13+08:00 (approx., from prior plan's completion commit)
- **Completed:** 2026-09-02T00:18:44+08:00
- **Tasks:** 2/2 completed
- **Files modified:** 9 (5 created, 4 modified)

## Accomplishments

- `QueueEntryController::index()` — today's queue (`queue_date = currentBusinessDate()`), eager-loading `customer:id,name`, rendering `frontline-staff/QueueList` — the authenticated, PII-permitted counterpart to the public display Plan 02-05 will build
- `callNext()`/`markDone()` — each guarded by `abort_unless($queueEntry->status === ExpectedStatus, 422, ...)` before mutating, so a direct/stale request that tries to skip a stage (e.g. Waiting straight to Done, or acting on a Done entry) is rejected server-side, not merely hidden by the UI's contextual button rendering (T-02-12)
- `addJobOrder()` — deliberately carries **no** status precondition on the target `QueueEntry`; a Done visit accepts a new job order exactly like a Waiting one (D-15/D-18), proven by a test asserting the job order count increases on a Done entry
- `JobOrderValidationRules::jobOrderRules()` — the flat, single-row sibling to the existing `jobOrdersRules()` nested-array variant, reused by the new `AddJobOrderRequest`
- `QueueList.vue` — a `Table` of today's entries (Queue Number, Customer, Status badge color-mapped per UI-SPEC, Actions), with "Call Next"/"Mark Done" rendered contextually by status and an "Add Job Order" icon button + `Dialog` on every row including Done ones
- `frontline-staff` nav gains a third destination ("Queue"), alongside Dashboard and New Visit
- All 29 `FrontlineStaff` feature tests pass (11 new: 6 status-transition, 5 add-job-order); full project suite: 97 passed / 3 pre-existing skips / 0 failed (up from 86 passed before this plan)
- `npm run types:check` exits 0; `vendor/bin/phpstan analyse` scoped to this plan's new/modified PHP files passes with 0 errors

## Task Commits

Each task was committed atomically:

1. **Task 1: Queue status transitions & add-job-order backend** - `0f658f8` (feat)
2. **Task 2: Internal queue list UI** - `4acaccf` (feat)

## Files Created/Modified

- `app/Concerns/JobOrderValidationRules.php` - added `jobOrderRules()`, the flat single-row validation variant for adding one job order to an existing visit
- `app/Http/Requests/FrontlineStaff/UpdateQueueEntryStatusRequest.php` - body-less FormRequest for `callNext`/`markDone`; target state implied by the route, not client input
- `app/Http/Requests/FrontlineStaff/AddJobOrderRequest.php` - delegates to `jobOrderRules()`
- `app/Http/Controllers/FrontlineStaff/QueueEntryController.php` - `index()`, `callNext()`, `markDone()`, `addJobOrder()` added alongside the existing `store()`
- `routes/portals.php` - `queue-entries.index` (GET), `.call-next`/`.mark-done` (PATCH), `.job-orders.store` (POST), all inside the existing `frontline-staff` role group
- `tests/Feature/FrontlineStaff/QueueStatusTransitionTest.php` - Waiting->Serving, Serving->Done, skipped-stage 422 rejections (dataset over serving/done), non-staff 403
- `tests/Feature/FrontlineStaff/AddJobOrderToVisitTest.php` - add-to-Waiting, add-to-Done (D-15/D-18 core case), Type A file requirement, file storage, non-staff 403
- `resources/js/pages/frontline-staff/QueueList.vue` - the internal queue Table + per-row Add Job Order dialog
- `resources/js/config/nav/frontline-staff.ts` - added the "Queue" nav entry

## Decisions Made

- `QueueList.vue`'s Add Job Order dialog keeps the plan-specified `<Form v-bind="QueueEntryController.addJobOrder.form(entry.id)">` (uncontrolled, native-FormData) pattern rather than switching to `useForm()` like `NewVisit.vue`'s job order rows — the RadioGroup's `name="type"` prop (a documented reka-ui `FormFieldProps` feature) renders a hidden native input mirroring its `v-model`, which is picked up correctly by the native form even through the Dialog's Teleported DOM subtree, since Teleport preserves internal DOM nesting. A local reactive map keyed by queue entry id drives only the conditional file-field visibility; it duplicates no submission logic.
- Both transition test files use Pest's `->with([...])` dataset feature for the serving/done 422-rejection case (2 scenarios in one test) rather than two separate `test()` calls, matching this project's existing terse-but-complete FrontlineStaff test style.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

- The plan's Task 2 acceptance criterion `grep -c "queueEntriesIndex" resources/js/config/nav/frontline-staff.ts` returns 1 is not literally satisfiable together with the plan's own action text (`import { index as queueEntriesIndex } ...` plus `href: queueEntriesIndex()`) — any correct implementation produces a count of 2 (one import occurrence, one usage occurrence). Not a deviation requiring a fix; the underlying intent (a working, correctly-wired nav entry) is fully met and verified via `npm run types:check` and manual inspection.
- Larastan's pre-existing `UpdateSystemConfigurationRequest.php:20` finding (logged by Plan 02-01, unrelated to this plan) was not touched.
- `resources/js/actions/App/Http/Controllers/FrontlineStaff/QueueEntryController.ts` and `resources/js/routes/frontline-staff/queue-entries/*` were regenerated via `php artisan wayfinder:generate --with-form --no-interaction` but are gitignored, consistent with how 02-01/02-02 handled the same generated output.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `QueueEntryController::index()`'s `queueEntries` shape (id, customer_id, queue_number, status, customer.name) is live and tested — Plan 02-05's public queue display can either reuse the same underlying query pattern (minus PII) or build its own status-only equivalent per D-09.
- The full Waiting->Serving->Done lifecycle plus the always-open add-job-order path are both real, server-enforced, and tested — the queue is no longer a write-once log (T-02-13/T-02-14 dispositions from the threat model both hold as implemented).
- No blockers.

---

_Phase: 02-customer-queue-management_
_Completed: 2026-09-02_

## Self-Check: PASSED

All 9 plan files (5 created, 4 modified) verified present on disk; both task commits (`0f658f8`, `4acaccf`) verified present in git log.
