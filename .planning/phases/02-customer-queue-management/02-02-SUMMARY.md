---
phase: 02-customer-queue-management
plan: 02
subsystem: queue-management
tags: [laravel, eloquent, db-locking, pest, inertia, file-upload]

# Dependency graph
requires:
  - phase: 02-customer-queue-management (plan 02-01)
    provides: "customers table + Customer model, frontline-staff route group/portal shell, Form Request + Validation Concern trait pattern"
provides:
  - "queue_entries/job_orders tables + QueueEntry/JobOrder models, enums (QueueStatus, JobOrderType, JobOrderStatus), and factories"
  - "QueueEntry::currentBusinessDate()/nextForBusinessDay() — the single source of truth for the Asia/Manila business-day boundary and the concurrency-safe daily counter, reused by every later Phase 2 plan that needs 'today'"
  - "QueueEntryController::store() — the transactional combined queue-number + job-order intake endpoint (frontline-staff.queue-entries.store)"
  - "CustomerController::index()'s confirmedQueueEntry prop, ready for Plan 02-03's UI to render the post-save confirmation"
affects: [02-03, 02-04, 02-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "DB::transaction() + lockForUpdate() over an indexed date column for a concurrency-safe daily-reset counter, with a composite unique(queue_date, queue_number) index as defense-in-depth — no new table beyond the approved 12-table ERD"
    - "whereDate() (not a plain where()) when comparing against a 'date'-cast Eloquent column in a raw query — the cast's fromDateTime() serializes with a time component ('Y-m-d H:i:s') on write, which SQLite (unlike MySQL's native DATE type) does not truncate back to a bare date on storage"
    - "Nested-array Form Request validation with a wildcard-to-wildcard required_if (job_orders.*.file required_if job_orders.*.type,type_a), resolved per-row by Laravel's built-in dependentRules mechanism, no custom Rule/Validator::after() needed"
    - "UploadedFile::store('job-orders', 'local') using the auto-hashed filename — never getClientOriginalName(), never the public disk"

key-files:
  created:
    - app/Enums/QueueStatus.php
    - app/Enums/JobOrderType.php
    - app/Enums/JobOrderStatus.php
    - app/Models/QueueEntry.php
    - app/Models/JobOrder.php
    - database/factories/QueueEntryFactory.php
    - database/factories/JobOrderFactory.php
    - database/migrations/2026_09_01_154402_create_queue_entries_table.php
    - database/migrations/2026_09_01_154403_create_job_orders_table.php
    - app/Concerns/JobOrderValidationRules.php
    - app/Http/Requests/FrontlineStaff/StoreQueueEntryRequest.php
    - app/Http/Controllers/FrontlineStaff/QueueEntryController.php
    - tests/Feature/FrontlineStaff/QueueNumberGenerationTest.php
    - tests/Feature/FrontlineStaff/QueueEntryIntakeTest.php
    - tests/Feature/FrontlineStaff/JobOrderTypeValidationTest.php
  modified:
    - app/Models/Customer.php
    - app/Http/Requests/FrontlineStaff/SearchCustomersRequest.php
    - app/Http/Controllers/FrontlineStaff/CustomerController.php
    - routes/portals.php

key-decisions:
  - "nextForBusinessDay() uses whereDate('queue_date', $businessDate) rather than a plain where() equality check — discovered during Task 1 verification that the plain form silently never matches on SQLite because the model's 'date' cast reformats the stored value to include a time component on write"
  - "Added explicit BelongsTo<T, $this>/HasMany<T, $this> generic PHPDoc on all three new relation methods (QueueEntry::customer/jobOrders, JobOrder::queueEntry, Customer::queueEntries) so Larastan level 7 has zero findings on this plan's own files"

patterns-established:
  - "Pattern: QueueEntry::currentBusinessDate() is the one and only place the Asia/Manila business-day conversion happens — later plans (internal queue list, public display) must call this, not re-derive it"
  - "Pattern: server-computed columns (queue_number, status) are never declared in a StoreXRequest::rules() array at all, so they can never appear in $request->validated() even if a client tries to submit them"

requirements-completed: [QUEUE-03, QUEUE-04, QUEUE-05]

# Metrics
duration: ~6min
completed: 2026-09-01
---

# Phase 2 Plan 02: Queue Number Generation & Job Order Intake Summary

**Concurrency-safe daily-reset queue counter (DB::transaction + lockForUpdate) and a single atomic save creating one queue entry plus one-or-more Type A/B job orders, with Type A file storage and full audit_trail coverage verified end-to-end.**

## Performance

- **Duration:** ~6 min (task work; excludes upfront context-loading reads)
- **Started:** 2026-09-01T15:45:00Z (approx.)
- **Completed:** 2026-09-01T15:51:22Z
- **Tasks:** 3/3 completed
- **Files modified:** 19 (15 created, 4 modified)

## Accomplishments
- `queue_entries`/`job_orders` tables with a composite `unique(queue_date, queue_number)` index and `job_orders.queue_entry_id` cascading on delete
- `QueueEntry::currentBusinessDate()`/`::nextForBusinessDay()` — the concurrency-safe daily counter, scoped to Asia/Manila without touching global `config('app.timezone')` (D-16/D-17)
- `QueueEntryController::store()` — one `DB::transaction()` creates the `QueueEntry` and every `JobOrder` row atomically; `queue_number`/`status` are always server-computed, never accepted from request input (T-02-05)
- `JobOrderValidationRules::jobOrdersRules()` — nested-array validation with `required_if:job_orders.*.type,type_a` on the file field, verified to resolve per-row (not globally) via a dedicated test
- Type A files stored via `UploadedFile::store('job-orders', 'local')`'s auto-hashed filename, never `getClientOriginalName()`, never the `public` disk (T-02-07)
- `CustomerController::index()` gains `confirmedQueueEntry` (eager-loaded with `jobOrders`), ready for Plan 02-03's confirmation UI
- All 9 new `FrontlineStaff` tests pass, including the AUDIT-01 regression case proving both `QueueEntry` and `JobOrder` creation each write an `audit_trail` row — closing the coverage gap 02-01 left open (it only covered `Customer`)
- Full project suite: 86 passed / 3 pre-existing skips / 0 failed (up from 77 passed before this plan)

## Task Commits

Each task was committed atomically:

1. **Task 1: Queue & job order domain layer** - `8d3ee39` (feat)
2. **Task 2: Job order validation rules & Form Requests** - `8d3cb15` (feat)
3. **Task 3: Transactional intake endpoint, routes & test coverage** - `5f6ed9b` (feat)

## Files Created/Modified
- `app/Enums/QueueStatus.php`, `JobOrderType.php`, `JobOrderStatus.php` - backed enums (`Waiting|Serving|Done`, `TypeA|TypeB`, `Intake`), TitleCase/snake_case per `UserRole`'s pattern
- `database/migrations/2026_09_01_154402_create_queue_entries_table.php` - `customer_id` FK (restrict on delete), `queue_date`, `queue_number`, `status`, composite unique index
- `database/migrations/2026_09_01_154403_create_job_orders_table.php` - `queue_entry_id` FK (cascade on delete), `description`, `type`, `status`, nullable `file_path`
- `app/Models/QueueEntry.php` - `#[Fillable]`/`#[ObservedBy(AuditObserver::class)]`, `customer()`/`jobOrders()` relations, `currentBusinessDate()`/`nextForBusinessDay()` static helpers
- `app/Models/JobOrder.php` - `#[Fillable]`/`#[ObservedBy(AuditObserver::class)]`, `queueEntry()` relation
- `app/Models/Customer.php` - added `queueEntries(): HasMany` (deferred from 02-01)
- `database/factories/QueueEntryFactory.php`, `JobOrderFactory.php` - `serving()`/`done()`/`typeA()` states
- `app/Concerns/JobOrderValidationRules.php` - `jobOrdersRules()` trait, `required_if` interpolated from `JobOrderType::TypeA->value`
- `app/Http/Requests/FrontlineStaff/StoreQueueEntryRequest.php` - `customer_id` + `jobOrdersRules()`
- `app/Http/Requests/FrontlineStaff/SearchCustomersRequest.php` - added optional `queueEntry` id
- `app/Http/Controllers/FrontlineStaff/QueueEntryController.php` - `store()`, the transactional intake endpoint
- `app/Http/Controllers/FrontlineStaff/CustomerController.php` - `index()` gains `confirmedQueueEntry`
- `routes/portals.php` - `frontline-staff.queue-entries.store` inside the existing `role:frontline_staff` group
- `tests/Feature/FrontlineStaff/QueueNumberGenerationTest.php`, `QueueEntryIntakeTest.php`, `JobOrderTypeValidationTest.php` - sequential counter, atomic multi-row save + file storage + confirmation render + AUDIT-01 regression, per-row conditional file requirement

## Decisions Made
- `nextForBusinessDay()` compares with `whereDate('queue_date', ...)` instead of a bare `where()` — the plan's literal code example used `where()`, but that silently never matches on SQLite (see Deviations).
- Added generic type params to the three new relation methods for Larastan level 7 cleanliness, matching CLAUDE.md's static-analysis expectations even though the plan didn't call this out explicitly.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `nextForBusinessDay()`'s plain `where('queue_date', ...)` never matched on SQLite**
- **Found during:** Task 1, verification step (`php artisan tinker` manual check before committing)
- **Issue:** The plan's code example used `->where('queue_date', $businessDate)`. `QueueEntry`'s `'queue_date' => 'date'` cast serializes the value via `fromDateTime()` (format `Y-m-d H:i:s`) on write; SQLite stores that exact string (unlike MySQL's native `DATE` column, which truncates the time portion at write time). Comparing that stored value against a bare `Y-m-d` business-date string with plain string equality never matched, so `max('queue_number')` always returned `null` and every call returned `1` — the counter never advanced.
- **Fix:** Changed the comparison to `->whereDate('queue_date', $businessDate)`, which Laravel translates into a grammar-aware date-extraction comparison that works correctly regardless of the stored format, on both SQLite and MySQL.
- **Files modified:** `app/Models/QueueEntry.php`
- **Verification:** Manual tinker check confirmed `n1=1, n2=2` for two sequential calls on the same business date (previously `n1=1, n2=1`); `QueueNumberGenerationTest` passes.
- **Committed in:** `8d3ee39` (Task 1 commit)

**2. [Rule 2 - Missing Critical] Larastan level 7 generic type params missing on new relation methods**
- **Found during:** Task 3, running `vendor/bin/phpstan analyse` scoped to this plan's files (CLAUDE.md mandates Larastan level 7 compliance; this project's Pint/Larastan discipline is a standing convention, not optional)
- **Issue:** `QueueEntry::customer()`/`::jobOrders()`, `JobOrder::queueEntry()`, and `Customer::queueEntries()` all returned `BelongsTo`/`HasMany` without their generic type parameters, producing 4 `missingType.generics` findings.
- **Fix:** Added `@return BelongsTo<Target, $this>` / `@return HasMany<Target, $this>` PHPDoc to each of the four methods.
- **Files modified:** `app/Models/QueueEntry.php`, `app/Models/JobOrder.php`, `app/Models/Customer.php`
- **Verification:** `vendor/bin/phpstan analyse` scoped to this plan's files passes with 0 errors; full `FrontlineStaff` test suite still green after the change (PHPDoc-only, no behavior change).
- **Committed in:** `5f6ed9b` (Task 3 commit, since the fix was made during Task 3's own verification pass)

---

**Total deviations:** 2 auto-fixed (1 bug, 1 missing-critical/static-analysis)
**Impact on plan:** Both fixes were necessary for correctness (the counter literally didn't work without #1) and for standing project conventions (#2). No scope creep — same files the plan already specified, just corrected/completed.

## Issues Encountered
- Larastan's pre-existing `UpdateSystemConfigurationRequest.php:20` finding (logged in `deferred-items.md` by Plan 02-01) is unrelated to this plan and was not touched.
- `resources/js/actions/App/Http/Controllers/FrontlineStaff/QueueEntryController.ts` and the corresponding `resources/js/routes/frontline-staff/queue-entries/*` were regenerated via `php artisan wayfinder:generate --with-form --no-interaction` but are gitignored (`/resources/js/actions`, `/resources/js/routes`) — not committed, consistent with how 02-01's generated helpers were handled.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `QueueEntryController::store()` and `QueueEntry::currentBusinessDate()`/`::nextForBusinessDay()` are live and tested — Plan 02-03 can build the "New Visit" UI (queue number generation + repeatable job-order rows) directly on top of this endpoint.
- `CustomerController::index()`'s `confirmedQueueEntry` prop is in place and eager-loads `jobOrders`, ready for 02-03's post-save confirmation card.
- No blockers. The SQLite-only concurrency-test-coverage gap for `lockForUpdate()` (documented in `QueueNumberGenerationTest.php`'s own comment and in RESEARCH.md Pattern 1) remains a known, accepted gap — true concurrent-write safety can only be verified against real MySQL, not this Pest/SQLite suite.

---
*Phase: 02-customer-queue-management*
*Completed: 2026-09-01*

## Self-Check: PASSED

All 19 plan files (15 created, 4 modified) plus this SUMMARY.md verified present on disk; all 3 task commits (`8d3ee39`, `8d3cb15`, `5f6ed9b`) verified present in git log.
