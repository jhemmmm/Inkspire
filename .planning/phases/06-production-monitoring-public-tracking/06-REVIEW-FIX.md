---
phase: 06-production-monitoring-public-tracking
fixed_at: 2026-09-07T00:00:00Z
review_path: .planning/phases/06-production-monitoring-public-tracking/06-REVIEW.md
iteration: 1
findings_in_scope: 18
fixed: 16
skipped: 2
status: partial
---

# Phase 6: Code Review Fix Report

**Fixed at:** 2026-09-07
**Source review:** `.planning/phases/06-production-monitoring-public-tracking/06-REVIEW.md`
**Iteration:** 1
**Scope:** `critical_warning` (CR-01..CR-06, WR-01..WR-12; the 7 Info findings were out of scope)

**Summary:**

- Findings in scope: 18
- Fixed: 16
- Skipped: 2 (WR-06, WR-12 — reasoning below)

**Verification:**

- `php artisan test --compact` — **385 tests, 382 passed, 3 skipped, 0 failures** (baseline was 352/349/3; +33 tests)
- `vendor/bin/pint --dirty --format agent` — passed
- `npm run types:check` (`vue-tsc --noEmit`) — clean
- `composer types:check` (Larastan level 7) — exactly the 5 known pre-existing Phase 5 errors in
  `CreateCreditRequestRequest`, `SavePricingAndPaymentRequest`, `UpdateSystemConfigurationRequest`
  documented in `deferred-items.md`. **No new errors.**
  (Note: Larastan initially aborted with `Undefined constant Larastan\Larastan\LARAVEL_VERSION` —
  a stale result cache in `/tmp/phpstan`. `vendor/bin/phpstan clear-result-cache` fixes it; not a
  code problem.)
- `npx vp check resources/js` — the only remaining format issues are in
  `resources/js/pages/errors/Forbidden.vue` and `resources/js/pages/owner/AuditTrail.vue`,
  both untouched by this run (last modified in Phase 1, commit `c9284d0`). The repo-wide
  `npm run check` also fails on the untracked `demo/` directory, which is pre-existing.

## Fixed Issues

### CR-01: Job-order number generator produces duplicates past 9999

**Files modified:** `app/Models/JobOrder.php`, `tests/Feature/JobOrder/JobOrderNumberGeneratorTest.php`
**Commit:** `b828120`
**Status:** fixed: requires human verification (MySQL semantics)

Replaced `max('number')` + `substr($maxNumber, -4)` with
`orderByRaw('LENGTH(number) DESC, number DESC')->value('number')` plus a prefix-length-based PHP
parse. Ordering by length first and the string second restores numeric order for a variable-width
zero-padded suffix without a `SUBSTR`/`CAST` SQL expression, keeping the SQLite/MySQL portability
the original docblock was reaching for. `sprintf('JO-%d-%04d', ...)` already pads to a _minimum_ of
four digits, so the generator now produces exactly what `TrackJobOrderRequest`'s `\d{4,}` regex
already accepted.

Regression test added: seed `JO-2026-9999` → generate `JO-2026-10000` → seed → generate
`JO-2026-10001`.

**Why flagged for human verification:** the ordering expression and the `lockForUpdate()` range/gap
lock it takes are exercised only on SQLite here. On MySQL the `ORDER BY LENGTH(...)` forces a
filesort over the matched range, which locks that range under `FOR UPDATE` — correct, but heavier
than the previous plain `MAX()`. Worth a look on the real engine before go-live.

---

### CR-02: `addJobOrder()` calls the number generator outside a transaction

**Files modified:** `app/Http/Controllers/FrontlineStaff/QueueEntryController.php`,
`tests/Feature/FrontlineStaff/JobOrderNumberAssignmentTest.php`
**Commit:** `b63a45b`
**Status:** fixed: requires human verification (MySQL semantics)

Wrapped the create + `applyIntakeOutcome()` in one `DB::transaction`, matching `store()`. Also
documented the invariant on `nextNumberForYear()` itself so the next caller does not repeat the
mistake.

Test added asserts `DB::transactionLevel()` at `JobOrder::creating` time is `baseline + 1` — the
only assertable evidence available, since SQLite makes `lockForUpdate()` a no-op. Verified the
assertion discriminates (it reads `baseline + 0` without the fix).

**Why flagged for human verification:** as the orchestrator noted, a green SQLite suite does not
prove the lock survives. The reasoning is: `nextNumberForYear()` opens and _commits_ its own
transaction, so on MySQL its gap lock is released at that commit unless an outer transaction turns
it into a savepoint. The outer transaction is now present at both call sites.

---

### CR-03: `replaceFile()` has no status guard

**Files modified:** `app/Http/Controllers/FrontlineStaff/JobOrderController.php`,
`tests/Feature/FrontlineStaff/JobOrderProcessingTest.php`
**Commit:** `1b35f7e`

Added `cancelled_at`, `released_at`, and a `REPLACEABLE_STATUSES` whitelist
(`Intake`, `ValidationFailed`, `ReadyForProduction`) alongside the existing Type A check. Seven new
test cases cover all four production statuses plus the released and cancelled cases, asserting a
422 and that no `ProductionLog` row is written.

---

### CR-04: `sendBack()` / `advance()` have no `released_at` guard

**Files modified:** `app/Http/Controllers/ProductionStaff/ProductionStageController.php`,
`tests/Feature/ProductionStaff/StageAdvancementTest.php`
**Commit:** `d5fd0b3`

Added `abort_if($jobOrder->released_at !== null, 422, ...)` to both actions, with a test asserting
a released order rejects both and produces no log row.

---

### CR-05: Public tracking reports a cancelled job order as still in production

**Files modified:** `app/Http/Controllers/Public/TrackingController.php`,
`tests/Feature/Public/TrackingTest.php`
**Commit:** `321690f`

Widened the column list to `['number', 'status', 'released_at', 'cancelled_at']` and made
`publicStage()` return `'Cancelled'` before consulting `released_at`. Two tests added: cancelled
while at `printing`, and cancelled _and_ released (Cancelled wins).

The existing PII-boundary test still passes — `cancelled_at` is selected on the model but never
enters the three-field response shape.

---

### CR-06: Release has no production-stage gate

**Files modified:** `app/Http/Controllers/FrontlineStaff/JobOrderReleaseController.php`,
`resources/js/pages/frontline-staff/QueueList.vue`,
`tests/Feature/FrontlineStaff/ReleaseGateTest.php`
**Commit:** `c7c7e14`

Added `abort_unless($jobOrder->status === JobOrderStatus::ReadyForPickup, 422, ...)` server-side and
mirrored `jobOrder.status === 'ready_for_pickup'` in `isReleaseEligible()`.

**Note for the reviewer:** every existing test in `ReleaseGateTest.php` set the job order up with
`JobOrder::factory()->readyForProduction()` — i.e. status `ready_for_production`, which the new gate
correctly rejects. All six were updated to create at `ready_for_pickup`. No test was deleted; the
assertions are unchanged. A new parameterised test covers the actual defect (a fully-paid order at
`ready_for_production` / `for_production` / `printing` / `quality_check` cannot be released).

`frontline-staff/Dashboard.vue`'s `isReleaseEligible()` deliberately still omits the status check —
its backing query already filters to `status = ready_for_pickup`, and its docblock says so.

---

### WR-01: `is_rush` uses UTC end-of-day

**Files modified:** `app/Http/Controllers/ProductionStaff/ProductionBoardController.php`,
`tests/Feature/ProductionStaff/ProductionBoardTest.php`
**Commit:** `4dfa6a0`

Hoisted `$endOfBusinessDay = now()->timezone('Asia/Manila')->endOfDay()` out of the `each()` closure
(also avoids recomputing it per row). Two frozen-clock tests added; verified the "due 07:00 tomorrow
Manila" case fails against the old `now()->endOfDay()`.

One subtlety worth recording: the tests must call `->utc()` on the `Carbon::parse(..., 'Asia/Manila')`
value before `forceFill`, because Eloquent's `datetime` cast stores the _wall clock_ of whatever
timezone the Carbon instance carries, without converting.

---

### WR-02: `EnterProduction` is not idempotent

**Files modified:** `app/Actions/JobOrder/EnterProduction.php`,
`tests/Feature/JobOrder/EnterProductionTest.php`
**Commit:** `406a134`

The action now returns early when the job order is cancelled, released, or already on one of the
four production stages. Tests: invoking twice writes one log row and does not move `due_at`;
cancelled and released orders are never re-entered.

---

### WR-03: `cancelled_at` checked against the stale, pre-lock model

**Files modified:** `app/Http/Controllers/ProductionStaff/ProductionStageController.php`,
`tests/Feature/ProductionStaff/StageAdvancementTest.php`
**Commit:** `5754948`

Both guards (including CR-04's new `released_at` one) moved inside the transaction, after
`lockForUpdate()->firstOrFail()`.

The test simulates a cancellation committing between route-model binding and the locked re-read by
listening for `TransactionBeginning` and updating the row via the query builder. **Verified
discriminating:** stashing the controller change makes both data sets fail with 302 instead of 422.

---

### WR-04: "Ready Since" and ordering use `updated_at`

**Files modified:** `app/Models/JobOrder.php`,
`app/Http/Controllers/FrontlineStaff/DashboardController.php`,
`app/Http/Controllers/FrontlineStaff/QueueEntryController.php`,
`resources/js/pages/frontline-staff/Dashboard.vue`,
`tests/Feature/FrontlineStaff/ReadyForPickupAlertTest.php`
**Commit:** `3b4b238`

Both surfaces now carry `withMax(['productionLogs as ready_at' => ...], 'created_at')` scoped to
`to_status = ready_for_pickup`, sort on `ready_at ?? updated_at`, and the Vue reads
`jobOrder.ready_at ?? jobOrder.updated_at`.

Three implementation notes:

- Sorting happens in PHP (`->sortBy()->values()`) rather than `orderBy` on the aggregate alias —
  the collections here are small and this avoids relying on alias-in-`ORDER BY` resolution
  differing between SQLite and MySQL.
- `'ready_at' => 'datetime'` was added to `JobOrder::casts()` so the aggregate serialises as
  ISO-8601 rather than a raw driver string. `datetime` is a primitive cast type, so a missing
  `ready_at` still returns `null` cleanly.
- **`->select([...])` must run before `->withMax(...)`.** `withAggregate()` falls back to selecting
  `job_orders.*` when no columns are set yet, which would have silently widened the Frontline
  Dashboard payload to every column including `total_amount`. A dedicated test now asserts the
  payload contains neither `total_amount` nor `base_price_snapshot`.

Ordering tests invert the `updated_at` order relative to the real one so they fail against the old
implementation.

---

### WR-05: `throttle:60,1` under-sized for a 5s poll; poll never stops

**Files modified:** `routes/web.php`, `resources/js/pages/public/Tracking.vue`,
`tests/Feature/Public/TrackingTest.php`
**Commit:** `fd68b48` (plus formatting in `b9428c2`)

`Tracking.vue` now stops polling once the stage is terminal (`Completed` or `Cancelled` — the
latter only exists because of CR-05). The route limit went `60,1` → `120,1`; the existing route
assertion was updated to match.

**Deliberate restraint on the limit:** raising it does not meaningfully change WR-06's enumeration
posture (a year's numbering space is walkable at either rate), so 120/min was chosen as enough
headroom for ~10 concurrent tabs behind one NAT rather than something larger. The poll-stop change
is what actually removes most of the sustained load.

---

### WR-07: "In Production" fallback badge mislabels design-stage job orders

**Files modified:** `resources/js/pages/frontline-staff/QueueList.vue`,
`resources/js/pages/frontline-staff/NewVisit.vue`
**Commit:** `6dba592`

Both pages now enumerate the four real production statuses via an `isInProduction()` helper and give
the design stages an explicit "In Design" fallback. `QueueList.vue` additionally badges released
orders as "Released" (it already has `released_at` in its payload), which was the other half of the
finding.

`NewVisit.vue` has no `released_at` in its `ConfirmedJobOrder` shape, so it only got the
production/design split.

---

### WR-08: Receipt QR encodes a dead URL when `number` is null

**Files modified:** `resources/js/pages/cashier/Receipt.vue`, `tests/Feature/Cashier/ReceiptTest.php`
**Commit:** `cd74d2d`

The QR block is now `v-if="jobOrder.number"`. A backend test asserts the underlying condition —
that a null-numbered job order yields a `trackingUrl` with no `number=` query parameter — since
Pest cannot assert on rendered Vue here.

---

### WR-09: Cancelled job orders count as "completed" in the Artist Performance Report

**Files modified:** `app/Http/Controllers/Artist/PerformanceReportController.php`,
`tests/Feature/Artist/PerformanceReportTest.php`
**Commit:** `08771a8`

Added `->whereNull('cancelled_at')`. Parameterised test covers a cancelled `design_approved` and a
cancelled `printing` job order, asserting both `jobsCompleted` and `slaAdherence` go to zero.

---

### WR-10: Migration backfill runs outside any transaction and is unrepeatable

**Files modified:**
`database/migrations/2026_09_05_120000_add_number_and_due_at_to_job_orders_table.php`,
`tests/Feature/JobOrder/JobOrderNumberBackfillTest.php` (new file)
**Commit:** `5f90de2`
**Status:** fixed: requires human verification (MySQL semantics)

`backfillNumbers()` now takes its own `DB::transaction`, filters `whereNull('number')`, adds
`orderBy('id')` as a same-second tiebreaker, and handles a null `created_at` explicitly instead of
relying on `Carbon::parse(null)` returning _now_.

`whereNull('number')` alone would **not** have made the backfill safely re-runnable — a partial run
followed by a re-run would restart each year at `0001` and collide on the new unique index. A new
`highestSequencePerYear()` helper primes the counters from numbers already present, which is what
actually delivers the re-runnability the review asked for.

Four tests cover: continuing from an existing number, idempotent re-run, null `created_at`, and the
same-second `id` tiebreaker. They reach the private helper via `ReflectionMethod` (the migration has
already run under `RefreshDatabase`, so `up()` is not re-invocable) — the migration's public surface
stays `up()`/`down()` as Laravel expects. `php artisan migrate:fresh` verified clean.

**Why flagged for human verification:** the transaction cannot undo the `ALTER TABLE` on MySQL
(implicit commit) — that part of the finding is unfixable in-place. What the change buys is that a
failed backfill is now atomic _within itself_ and safe to re-run. Confirm on MySQL before relying
on it against a table with pre-existing rows.

---

### WR-11: `readyForPickup.count` and `.items` are two unsynchronised queries

**Files modified:** `app/Http/Controllers/FrontlineStaff/QueueEntryController.php`,
`tests/Feature/FrontlineStaff/ReadyForPickupAlertTest.php`
**Commit:** `cdc5e71`

One fetch, both values derived from it. The test counts statements binding the
`ready_for_pickup` value during the request and asserts exactly one (it read two before the fix).

---

## Skipped Issues

### WR-06: `/track` is an enumeration oracle over sequential job-order numbers

**Files:** `app/Http/Controllers/Public/TrackingController.php:33-49`; `app/Models/JobOrder.php`
**Reason:** skipped — requires an architectural change beyond a targeted repair.

The review's own fix is "make the tracking key unguessable rather than relying on rate limiting
alone": a new random `tracking_token` column, a migration + backfill for it, a change to the QR
payload in `ReceiptController`, a second lookup path in `TrackingController`/`TrackJobOrderRequest`,
and a separate tighter per-IP limiter keyed on _not-found_ responses specifically. That is a schema
change plus a new public URL contract, not a repair — and it interacts with the printed-receipt
format, which is a product decision (a token in the QR but not on the printed line, or both?).

The finding itself is **valid and I am not disputing it**. What Phase 6 ships is a real, if
low-severity, business-intelligence leak: order volume, current highest order number, and per-order
production progress are all walkable. The review is also right that it is _not_ a PII leak — the
three-field response shape is tightly scoped and tested.

Two things were done that partially reduce the exposure without pre-empting the design decision:
WR-05's terminal-stage poll stop removes the sustained per-order traffic, and the throttle was
raised only modestly (120/min, not something large) precisely so enumeration does not get cheaper.

**Recommend logging this to `deferred-items.md` and scheduling it as its own small plan.**

---

### WR-12: Stage-transition side effects are unbounded

**File:** `app/Http/Controllers/ProductionStaff/ProductionStageController.php:58-135`
**Reason:** skipped — I do not believe the suggested fix is correct, and the underlying behaviour is
by design rather than a defect.

The review's own proposed remedy is "reject a transition whose `from_status`/`to_status` pair matches
the most recent `ProductionLog` row within a short window", with the fallback "at minimum, document
that repeated bouncing is expected and acceptable". I think the fallback is the right answer and the
primary suggestion is actively harmful:

1. **A repeated send-back is a legitimate move, not a duplicate.** If Quality Check rejects a
   reprint twice in five minutes, that is two real events the production log exists to record.
   A time-window rejection would silently swallow the second one and leave the physical state and
   the log disagreeing — a worse failure than the one it prevents.
2. **The stated failure mode is already covered.** The review frames it as "the actual failure mode
   for a double-click". A double-clicked _advance_ is genuinely idempotent-by-lock: the second
   request sees the new status, and `advance()` from `ready_for_pickup` already 422s, as does
   `sendBack()` from `for_production`. Mid-sequence, the second click performs a different, real
   transition — which is what the operator asked for by clicking twice. Both buttons already carry
   `:disabled="processing"`.
3. **There is no defined policy to enforce.** "How many times may an order bounce between Printing
   and Quality Check before that is an error?" has no answer in the phase's requirements or UI spec.
   Inventing a threshold here would be me making a product decision, not repairing a defect.

WR-03 (moving the guards inside the lock) closes the only concurrency hole this finding actually
touches. If unbounded bouncing turns out to matter operationally, the right response is a report
surfacing high-bounce orders — not a server-side rejection.

---

_Fixed: 2026-09-07_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
