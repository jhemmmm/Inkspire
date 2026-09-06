---
phase: 06-production-monitoring-public-tracking
plan: 03
subsystem: public-tracking
tags: [inertia, vue3, qrcode.vue, laravel, public-routes, pii-boundary]

# Dependency graph
requires:
  - phase: 06-01
    provides: "job_orders.number (JO-{year}-{seq}), JobOrder::nextNumberForYear()'s zero-padding shape, all four PROD-02 JobOrderStatus production-stage cases"
provides:
  - "GET /track (public.tracking.show): unauthenticated job-order lookup by number, throttle:60,1"
  - "TrackingController::publicStage(): the server-side status-to-label mapping table (D-02/06-UI-SPEC §5) — the only place the raw JobOrderStatus enum is read for public display"
  - "resources/js/components/TrackingQrCode.vue: reusable SVG QR wrapper (render-as=svg, level=M) around qrcode.vue, for any future surface needing a printable deep-link QR"
  - "Cashier Receipt now exposes jobOrder.number and trackingUrl, so any future receipt-adjacent surface can read the same shape"
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "PII-boundary Inertia assertion via AssertableJson's built-in exhaustive interacted() check: scoping onto a prop with has('result', fn ($r) => $r->where(...)) and omitting ->etc() makes the assertion fail if the prop carries any extra key — used instead of a nonexistent hasOnly() helper"
    - "Public status-label mapping stays entirely server-side (never sends the raw enum) — same allowlist discipline as QueueDisplayController, one level stricter per D-02"

key-files:
  created:
    - app/Http/Controllers/Public/TrackingController.php
    - app/Http/Requests/Public/TrackJobOrderRequest.php
    - resources/js/pages/public/Tracking.vue
    - resources/js/components/TrackingQrCode.vue
    - tests/Feature/Public/TrackingTest.php
  modified:
    - routes/web.php
    - app/Http/Controllers/Cashier/ReceiptController.php
    - resources/js/pages/cashier/Receipt.vue
    - tests/Feature/Cashier/ReceiptTest.php

key-decisions:
  - "No new dependency added for the QR code (D-03's approval gate already cleared in 06-UI-SPEC.md): qrcode.vue@3.10.0 was installed and approved in Phase 5 for PaymentQrCode.vue; TrackingQrCode.vue is a second, independent thin wrapper around the same library rendering render-as=\"svg\" (crisp at any print DPI) instead of PaymentQrCode.vue's untouched 240px canvas"
  - "PII-boundary 'only these fields' test assertions use AssertableJson's native exhaustive-interaction check (has('result', fn ($r) => $r->where(...)) with no ->etc()) rather than a hasOnly() helper, which does not exist on this Inertia testing version (v3.3.1) — confirmed via source inspection of vendor/laravel/framework's Fluent\\Concerns\\Interaction trait"
  - "TrackJobOrderRequest's regex suffix is \\d{4,} (minimum 4 digits), never \\d{4} (exactly 4) — matches JobOrder::nextNumberForYear()'s sprintf('JO-%d-%04d', ...) zero-padding, so a year that reaches a 5-digit sequence is accepted, not rejected"

requirements-completed: [TRACK-01, TRACK-02]

# Metrics
duration: ~25min (includes one-time worktree environment bootstrap: composer install, npm ci, npm run build)
completed: 2026-09-07
---

# Phase 6 Plan 3: Public Job-Order Tracking & Receipt QR Summary

**Unauthenticated `/track` page showing only a job order's mapped public stage (never the raw status or any PII/pricing), plus a print-ready SVG QR on the cashier receipt that deep-links straight to that result.**

## Performance

- **Duration:** ~25 min (majority was one-time worktree bootstrap: `composer install`, `npm ci`, `npm run build` — this worktree had no `vendor/`, `node_modules/`, `.env`, or built assets, matching 06-01's documented pattern)
- **Tasks:** 2 completed
- **Files modified:** 9 (4 created, 5 modified)

## Accomplishments
- A customer can type a job order number on a fully public, unauthenticated `/track` page and see exactly its current stage — nothing else
- The server-side `publicStage()` mapping collapses all eight pre-production statuses into "In Progress" and never serializes the raw `JobOrderStatus` enum value to the client
- The lookup form's validation regex accepts every number `JobOrder::nextNumberForYear()` can actually produce, including a 5+-digit yearly sequence, proven by a dedicated test
- Polling (`usePoll(5000)`) runs only in the result state — the bare lookup form never polls, matching D-14
- The cashier receipt now shows a "Job Order No." row and a scannable, print-visible SVG QR that deep-links to that exact order's tracking result, with a typed fallback URL for a torn/smudged receipt

## Task Commits

Each task was committed atomically (Task 1 used TDD: test → feat):

1. **Task 1: Public tracking controller, route, and page** - `1b17f6f` (test, RED) → `668710a` (feat, GREEN)
2. **Task 2: Receipt QR deep link** - `2f1967d` (feat)

## Files Created/Modified
- `app/Http/Controllers/Public/TrackingController.php` - `show()` renders `public/Tracking` with an explicit column allowlist (`['number', 'status', 'released_at']`) and response shape (`['found', 'number', 'stage']`); `publicStage()` implements the D-02 mapping table
- `app/Http/Requests/Public/TrackJobOrderRequest.php` - `number` validation, nullable + `\d{4,}`-suffixed regex, custom copywriting-contract error message
- `resources/js/pages/public/Tracking.vue` - lookup / result / not-found states on the forced-dark public shell, `usePoll(5000, { only: ['result'] }, { autoStart: false })` started/stopped via a `watch` on `result`
- `resources/js/components/TrackingQrCode.vue` - `QrcodeVue` wrapper, `render-as="svg"`, `size=160`, `level="M"`
- `routes/web.php` - `GET /track` → `TrackingController::show`, `throttle:60,1`, named `public.tracking.show`
- `app/Http/Controllers/Cashier/ReceiptController.php` - added `number` to the `jobOrder` allowlist and a top-level `trackingUrl` prop via `route('public.tracking.show', ['number' => ...])`
- `resources/js/pages/cashier/Receipt.vue` - "Job Order No." row, print-visible QR block (caption + typed fallback), `<Head>` title now uses the job order number
- `tests/Feature/Public/TrackingTest.php` - 16 tests: lookup state, found/not-found, all 8 pre-production statuses collapsing to "In Progress", `released_at` → "Completed", 5-digit sequence acceptance, malformed-input rejection, throttle middleware, PII-boundary assertion
- `tests/Feature/Cashier/ReceiptTest.php` - new test asserting `jobOrder.number` and a `trackingUrl` containing both `/track` and the order's number

## Decisions Made
- No new dependency: `qrcode.vue@3.10.0` (Phase 5, already approved and installed) is reused via a second independent wrapper component, not by modifying `PaymentQrCode.vue`.
- PII-boundary "only these fields" test assertions use `AssertableJson`'s native exhaustive-interaction check (omitting `->etc()` inside a `has('result', fn ($r) => ...)` scope) since `hasOnly()` does not exist on this Inertia testing version — verified by reading `vendor/laravel/framework/src/Illuminate/Testing/Fluent/Concerns/Interaction.php` directly rather than guessing at an API.
- Validation regex suffix is `\d{4,}` (minimum, not exact), matching `JobOrder::nextNumberForYear()`'s zero-padding — a dedicated test (`JO-2026-10000`) proves the 5-digit case is accepted.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Fixed the plan's `hasOnly()` test helper call, which does not exist in this Inertia testing version**
- **Found during:** Task 1 verification — GREEN run failed 2 of 16 tests with `Method Inertia\Testing\AssertableInertia::hasOnly does not exist.`
- **Issue:** The plan's acceptance criteria described the PII-boundary assertion as `->hasOnly([...])`, but `inertiajs/inertia-laravel` v3.3.1's `AssertableInertia` (extending Laravel's `Illuminate\Testing\Fluent\AssertableJson`) has no `hasOnly()` method.
- **Fix:** Rewrote both affected assertions to rely on `AssertableJson`'s built-in exhaustive-interaction check: scoping onto the prop with `has('result', fn ($result) => $result->where(...))` and deliberately omitting `->etc()` — the scope's own `interacted()` call (confirmed by reading the framework source) fails the test if `result` carries any key beyond the ones explicitly checked. This is functionally equivalent to `hasOnly()` using only existing public API.
- **Files modified:** `tests/Feature/Public/TrackingTest.php`
- **Verification:** All 16 tests pass; re-verified the assertion actually catches leaks by temporarily adding an extra key to the controller response and confirming the test failed, then reverting.
- **Committed in:** `668710a` (Task 1 GREEN commit)

**2. [Rule 3 - Blocking] Bootstrapped the fresh worktree's build/runtime environment**
- **Found during:** Start of Task 1 verification — `vendor/bin/pint`/`vendor/bin/pest`/`php artisan migrate`/`npm run types:check` all required `vendor/`, `.env`, a SQLite database file, and `node_modules/`, none of which exist in a fresh worktree checkout (all gitignored)
- **Fix:** `composer install`, `cp .env.example .env`, `php artisan key:generate`, created `database/database.sqlite`, ran all 21 existing migrations, `npm ci`, `npm run build`
- **Files modified:** none tracked (`.env`, `database/database.sqlite`, `vendor/`, `node_modules/`, `public/build/` are all gitignored)
- **Committed in:** N/A (no trackable file changes)

---

**Total deviations:** 2 auto-fixed (1 blocking/test-API, 1 blocking/environment)
**Impact on plan:** Both fixes were necessary to deliver working, verifiable code exactly matching the plan's intent. No scope creep — no plan files were touched beyond what Task 1/2 specified.

## Issues Encountered

- **Pre-existing Larastan failures unrelated to this plan** (already logged in `deferred-items.md` by 06-01, reconfirmed unchanged here): the non-exhaustive `match ($jobOrder->status)` in `app/Http/Controllers/FrontlineStaff/QueueEntryController.php:181`, and `property.notFound`/`method.notFound` errors in three Phase 5 Cashier/Owner Form Requests. `composer types:check` still reports the same 6 errors, none in any file this plan created or modified.

## User Setup Required

None - no external service configuration required. No new dependency was added (D-03's approval gate was already cleared before this plan by reusing Phase 5's `qrcode.vue@3.10.0`).

## Next Phase Readiness
- The public tracking route (`public.tracking.show`) and its `publicStage()` mapping are the pattern any later phase can extend if the mapping table ever needs a new stage.
- `TrackingQrCode.vue` is a reusable component if a future surface (e.g., an SMS/email pickup notification, if ever built) needs the same deep-link QR.
- No blockers for the remaining Phase 6 plans (production board, Frontline alert) — this plan's controller/tests operate on job order status values directly and do not depend on the production board existing.

---
*Phase: 06-production-monitoring-public-tracking*
*Completed: 2026-09-07*

## Self-Check: PASSED

- FOUND: `app/Http/Controllers/Public/TrackingController.php`
- FOUND: `app/Http/Requests/Public/TrackJobOrderRequest.php`
- FOUND: `resources/js/pages/public/Tracking.vue`
- FOUND: `resources/js/components/TrackingQrCode.vue`
- FOUND: `tests/Feature/Public/TrackingTest.php`
- FOUND commit `1b17f6f` (test, RED)
- FOUND commit `668710a` (feat, GREEN)
- FOUND commit `2f1967d` (feat, Task 2)
