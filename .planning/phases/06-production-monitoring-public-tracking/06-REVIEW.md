---
phase: 06-production-monitoring-public-tracking
reviewed: 2026-09-06T20:18:01Z
depth: standard
files_reviewed: 57
files_reviewed_list:
  - app/Actions/JobOrder/EnterProduction.php
  - app/Concerns/ProductionLogValidationRules.php
  - app/Enums/JobOrderStatus.php
  - app/Http/Controllers/Artist/DesignEditorController.php
  - app/Http/Controllers/Artist/JobOrderQueueController.php
  - app/Http/Controllers/Artist/PerformanceReportController.php
  - app/Http/Controllers/Cashier/CancellationController.php
  - app/Http/Controllers/Cashier/CreditRequestController.php
  - app/Http/Controllers/Cashier/DashboardController.php
  - app/Http/Controllers/Cashier/PaymentController.php
  - app/Http/Controllers/Cashier/ReceiptController.php
  - app/Http/Controllers/FrontlineStaff/DashboardController.php
  - app/Http/Controllers/FrontlineStaff/JobOrderController.php
  - app/Http/Controllers/FrontlineStaff/QueueEntryController.php
  - app/Http/Controllers/Owner/CreditApprovalController.php
  - app/Http/Controllers/ProductionStaff/ProductionBoardController.php
  - app/Http/Controllers/ProductionStaff/ProductionStageController.php
  - app/Http/Controllers/Public/DesignReviewController.php
  - app/Http/Controllers/Public/TrackingController.php
  - app/Http/Requests/ProductionStaff/AdvanceProductionStageRequest.php
  - app/Http/Requests/ProductionStaff/SendBackProductionStageRequest.php
  - app/Http/Requests/Public/TrackJobOrderRequest.php
  - app/Models/JobOrder.php
  - app/Models/ProductionLog.php
  - database/factories/JobOrderFactory.php
  - database/factories/ProductionLogFactory.php
  - database/migrations/2026_09_05_120000_add_number_and_due_at_to_job_orders_table.php
  - database/migrations/2026_09_05_120100_create_production_logs_table.php
  - resources/js/components/TrackingQrCode.vue
  - resources/js/config/nav/production-staff.ts
  - resources/js/pages/artist/Dashboard.vue
  - resources/js/pages/cashier/Dashboard.vue
  - resources/js/pages/cashier/Receipt.vue
  - resources/js/pages/frontline-staff/Dashboard.vue
  - resources/js/pages/frontline-staff/NewVisit.vue
  - resources/js/pages/frontline-staff/QueueList.vue
  - resources/js/pages/owner/CreditRequests.vue
  - resources/js/pages/production-staff/Dashboard.vue
  - resources/js/pages/public/Tracking.vue
  - routes/portals.php
  - routes/web.php
  - tests/Feature/Artist/DesignReviewTest.php
  - tests/Feature/Artist/PerformanceReportTest.php
  - tests/Feature/Artist/QueueControlsTest.php
  - tests/Feature/Cashier/CancellationFeeTest.php
  - tests/Feature/Cashier/ProductionCompatibilityTest.php
  - tests/Feature/Cashier/ReceiptTest.php
  - tests/Feature/FrontlineStaff/JobOrderNumberAssignmentTest.php
  - tests/Feature/FrontlineStaff/JobOrderProcessingTest.php
  - tests/Feature/FrontlineStaff/ReadyForPickupAlertTest.php
  - tests/Feature/JobOrder/EnterProductionTest.php
  - tests/Feature/JobOrder/JobOrderNumberGeneratorTest.php
  - tests/Feature/JobOrder/ProductionLogModelTest.php
  - tests/Feature/ProductionStaff/ProductionBoardTest.php
  - tests/Feature/ProductionStaff/StageAdvancementTest.php
  - tests/Feature/Public/DesignReviewTest.php
  - tests/Feature/Public/TrackingTest.php
findings:
  critical: 6
  warning: 12
  info: 7
  total: 25
status: issues_found
---

# Phase 6: Code Review Report

**Reviewed:** 2026-09-06T20:18:01Z
**Depth:** standard
**Files Reviewed:** 57
**Status:** issues_found

## Summary

Phase 6 adds a public unauthenticated `/track` endpoint, a `JO-{year}-{seq}` number
generator, automatic entry into production, a Production Staff board with stage
transitions, and a ready-for-pickup alert. The PII boundary on `/track` is genuinely
tight (column-scoped query, three-field response, a test that asserts the raw HTML
contains no `total_amount`/`payment_status`/`file_path`), the role gating on the new
production routes is correct, and the polled endpoints all eager-load their relations
(no N+1 found in `ProductionBoardController`, `FrontlineStaff\DashboardController`, or
`QueueEntryController::index`).

The defects are concentrated in three places:

1. **The number generator is wrong.** It reads a fixed 4-character suffix and relies on
   a string `MAX()`, both of which break at the 10,000th job order of a year — and the
   failure is unrecoverable (permanent unique-constraint violation on every subsequent
   create). The code's own docblock and `TrackJobOrderRequest`'s `\d{4,}` regex claim
   this case is supported; it is not, and no test covers it.
2. **Status guards were widened everywhere except where orders can be dragged
   *backwards*.** `replaceFile`, `sendBack`, and `release` all lack the guards that
   Phase 6's own state machine now requires, producing job orders that are invisible on
   every board, or reported "Completed" to a customer while still on the press.
3. **`cancelled_at` is not consulted by the public stage mapping**, so a cancelled order
   tells the customer it is "Printing" and polls forever.

The stage-advance/send-back locking pattern is sound in shape but checks `cancelled_at`
outside the lock, and `nextNumberForYear` releases its lock before the caller inserts —
safe in `store()` only by accident of an enclosing transaction, and unsafe in
`addJobOrder()`.

No structural findings block was supplied for this review.

## Critical Issues

### CR-01: Job-order number generator produces duplicates past 9999 and permanently breaks job-order creation

**File:** `app/Models/JobOrder.php:186-198`

**Issue:** Two independent bugs compound:

- `->max('number')` on a `varchar` column is a **string-lexicographic** max.
  `strcmp('JO-2026-9999', 'JO-2026-10000') > 0` is `true` (verified), so once
  `JO-2026-10000` exists the max is still `JO-2026-9999`.
- `(int) substr($maxNumber, -4)` reads only the **last four characters**. For
  `JO-2026-10000` that is `"0000"` → `0`, so the "next" number is `JO-2026-0001`.

Traced end-to-end (verified with a standalone PHP run):

| current max | computed next | result |
|---|---|---|
| `JO-2026-9999` | `JO-2026-10000` | inserts OK |
| `JO-2026-10000` (max still reads `JO-2026-9999`) | `JO-2026-10000` | **UNIQUE violation** |

Because `MAX()` never advances past `JO-2026-9999`, **every** job order created for the
rest of that year collides on `job_orders_number_unique` → uncaught `QueryException` →
500 on the Frontline Staff intake flow. In `QueueEntryController::addJobOrder()` the
uploaded file has already been written to disk before the insert fails, so the failure
also orphans a file.

The docblock at lines 180-185 explicitly asserts the fixed-width assumption ("every
number sharing a year prefix has the same fixed width"), and
`TrackJobOrderRequest::rules()` (lines 19-36) explicitly widens its regex to `\d{4,}`
"since a year that reaches a 5-digit sequence ... is a legitimate number". The two
files contradict each other; the generator is the one that is wrong.

**Fix:**
```php
public static function nextNumberForYear(int $year): string
{
    return DB::transaction(function () use ($year): string {
        $sequence = (int) static::query()
            ->where('number', 'like', "JO-{$year}-%")
            ->lockForUpdate()
            // Numeric max on the suffix, not a lexicographic max on the whole string.
            ->max(DB::raw("CAST(SUBSTRING(number, ".(strlen("JO-{$year}-") + 1).") AS UNSIGNED)"));

        return sprintf('JO-%d-%04d', $year, $sequence + 1);
    });
}
```
If the SQLite/MySQL portability concern that motivated the PHP-side parse still stands,
keep the PHP parse but split on the delimiter instead of a fixed width, and order the
candidate rows by length then value:
```php
$maxNumber = static::query()
    ->where('number', 'like', "JO-{$year}-%")
    ->lockForUpdate()
    ->orderByRaw('LENGTH(number) DESC, number DESC')
    ->value('number');

$sequence = $maxNumber === null
    ? 1
    : ((int) substr($maxNumber, strrpos($maxNumber, '-') + 1)) + 1;
```
Add a regression test that seeds `JO-2026-9999`, generates, seeds the result, generates
again, and asserts `JO-2026-10001`.

---

### CR-02: `addJobOrder()` calls the number generator outside a transaction, so the row lock is released before the insert

**File:** `app/Http/Controllers/FrontlineStaff/QueueEntryController.php:105-113`

**Issue:** `JobOrder::nextNumberForYear()` opens and **commits** its own
`DB::transaction`. The `lockForUpdate()` range lock therefore dies when that closure
returns — before the caller has inserted anything.

`store()` (lines 129-155) is safe only incidentally: the whole method body is wrapped in
an outer `DB::transaction`, so the inner call becomes a savepoint and the lock survives
until the outer commit. `addJobOrder()` has **no** enclosing transaction:

```php
$jobOrder = $queueEntry->jobOrders()->create([
    'number' => JobOrder::nextNumberForYear(JobOrder::currentNumberingYear()),  // lock taken AND released here
    ...
]);                                                                             // insert happens here, unprotected
```

Two Frontline Staff adding a job order to different visits at the same moment both
compute the same number and the second insert dies on the unique index — the exact race
the lock was written to prevent. This is production-only (SQLite makes `lockForUpdate()`
a no-op, so tests will never catch it).

**Fix:**
```php
public function addJobOrder(AddJobOrderRequest $request, QueueEntry $queueEntry): RedirectResponse
{
    $jobOrder = DB::transaction(fn (): JobOrder => $queueEntry->jobOrders()->create([
        'number' => JobOrder::nextNumberForYear(JobOrder::currentNumberingYear()),
        'description' => $request->validated('description'),
        'type' => $request->validated('type'),
        'status' => JobOrderStatus::Intake,
        'file_path' => $request->file('file')?->store('job-orders', 'local'),
    ]));

    $this->applyIntakeOutcome($jobOrder, $request->file('file'));
    ...
}
```
Better still, make the invariant impossible to get wrong by asserting inside
`nextNumberForYear()` that a transaction is already open, or by having it perform the
insert itself.

---

### CR-03: `replaceFile()` has no status guard — it drags in-production and released job orders back to `for_production` and writes a falsified production log

**File:** `app/Http/Controllers/FrontlineStaff/JobOrderController.php:26-51`

**Issue:** The only server-side guard is `type === TypeA` (line 28). Phase 6 wired
`EnterProduction` into this path (lines 39-41), so the consequences are now much larger
than before:

- A Type A order at `printing` / `quality_check` / `ready_for_pickup`, or one already
  released (`released_at` set), can be POSTed to `frontline-staff.job-orders.replace-file`.
- On a passing file: `status` → `ready_for_production` → `EnterProduction` → `for_production`,
  **`due_at` silently reset to `now()+SLA`**, and a `ProductionLog` row is written with
  `from_status: null, to_status: for_production, recorded_by: null` — a system-authored
  "first entry into production" row that misrepresents a `ready_for_pickup → for_production`
  regression by a named human.
- On a failing file: `status` → `validation_failed`, which is not on any board. The order
  disappears from the Production Board (`ProductionBoardController:32-37`) and from the
  ready-for-pickup list (`FrontlineStaff\DashboardController:24`) **with no production log
  row at all** — silent data loss of the order's position in the workflow.
- `cancelled_at` is not checked either, so a cancelled order can be reanimated into
  production.

`ReplaceJobOrderFileDialog` is only rendered for `validation_failed`
(`QueueList.vue:237-241`), but the project's own stated principle — quoted verbatim in
`JobOrderReleaseController`'s docblock ("RBAC-02 precedent: never trust a client-side-only
decision") — requires the server to re-check.

**Fix:**
```php
public function replaceFile(ReplaceJobOrderFileRequest $request, JobOrder $jobOrder): RedirectResponse
{
    abort_unless($jobOrder->type === JobOrderType::TypeA, 422, 'Only a Type A job order accepts a file replacement.');
    abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
    abort_if($jobOrder->released_at !== null, 422, __('This job order has already been released.'));
    abort_unless(
        in_array($jobOrder->status, [
            JobOrderStatus::Intake,
            JobOrderStatus::ValidationFailed,
            JobOrderStatus::ReadyForProduction,
        ], true),
        422,
        __('This job order has already entered production and its file can no longer be replaced.'),
    );
    ...
}
```

---

### CR-04: `sendBack()` / `advance()` have no `released_at` guard — a released job order can be pushed back into production and becomes invisible everywhere

**File:** `app/Http/Controllers/ProductionStaff/ProductionStageController.php:60, 101-135`

**Issue:** Both actions guard `cancelled_at` but neither guards `released_at`. A job
order that has been released (`released_at` set, `status = ready_for_pickup`) can be sent
back to `quality_check` while `released_at` stays populated. The resulting row is a
zombie:

| surface | filter | result |
|---|---|---|
| Production Board | `whereNull('released_at')` (`ProductionBoardController:38`) | hidden |
| Frontline Dashboard | `whereNull('released_at')` (`DashboardController:26`) | hidden |
| Frontline QueueList summary | `whereNull('released_at')` (`QueueEntryController:45`) | hidden |
| Public `/track` | `released_at !== null` wins (`TrackingController:60`) | reports **"Completed"** |

The order is physically back on the press, but no staff surface shows it and the customer
is told it is done. `StageAdvancementTest` covers the cancelled case for both actions but
has no released-order case for either.

**Fix:** Add the guard to both actions, and re-check it inside the locked transaction (see
WR-10):
```php
abort_if($jobOrder->released_at !== null, 422, __('This job order has already been released.'));
```
Add matching tests:
`test('a released job order rejects advance and send back')`.

---

### CR-05: Public tracking reports a cancelled job order as still in production

**File:** `app/Http/Controllers/Public/TrackingController.php:58-71`

**Issue:** `publicStage()` consults `released_at` and then `status`. It never consults
`cancelled_at`. `CancellationController::store()` deliberately leaves `status` untouched —
its own comment (lines 76-78) states "`cancelled_at` is the authoritative 'no longer
actionable' signal" — and it explicitly permits cancelling from all four production
statuses (lines 44-47, added in this phase).

So an order cancelled while at `printing` keeps `status = printing`, and the public page
tells the customer **"Printing"**. `Tracking.vue:31-47` then polls that lie every 5
seconds forever, since `result.found` stays `true`. The customer has no way to learn the
order was cancelled.

`TrackingTest.php` covers released, not-found, every pre-production status, and the
5-digit case — but has no cancelled-order case.

**Fix:**
```php
private function publicStage(JobOrder $jobOrder): string
{
    if ($jobOrder->cancelled_at !== null) {
        return 'Cancelled';
    }

    if ($jobOrder->released_at !== null) {
        return 'Completed';
    }
    ...
}
```
Widen the query at line 35 to `['number', 'status', 'released_at', 'cancelled_at']` and add
`test('a cancelled job order reports Cancelled, not its production stage')`.

---

### CR-06: Release has no production-stage gate — a paid order still on the press can be released, and `/track` then reports it "Completed"

**File:** `app/Http/Controllers/FrontlineStaff/JobOrderReleaseController.php:22-36`; UI at `resources/js/pages/frontline-staff/QueueList.vue:132-138, 256`

**Issue:** `JobOrderReleaseController::store()` gates on `cancelled_at`, `released_at`, and
`payment_status` only — never on `status`. `QueueList.vue`'s `isReleaseEligible()` mirrors
exactly those three checks:

```ts
function isReleaseEligible(jobOrder: JobOrderRecord): boolean {
    return (
        (jobOrder.payment_status === 'paid' ||
            jobOrder.payment_status === 'on_credit') &&
        jobOrder.released_at === null
    );
}
```

So a fully-paid job order sitting at `for_production` or `printing` renders a live
**"Release to Customer"** button on the Frontline queue page. This is a UI-reachable path,
not a crafted request. Pressing it:

- stamps `released_at` on an order that has not been printed,
- removes it from the Production Board (`whereNull('released_at')`),
- and makes `/track` report **"Completed"** (`TrackingController:60-62`) to the customer.

The new Frontline **Dashboard** does this correctly — it only lists
`status = ready_for_pickup` — but the QueueList surface (also modified in this phase) does
not. Phase 6 is where the `ready_for_pickup` gate belongs, since it is the phase that
introduced both the stage sequence and the `released_at → "Completed"` public mapping.

**Fix:** server-side, in `JobOrderReleaseController::store()`:
```php
abort_unless(
    $jobOrder->status === JobOrderStatus::ReadyForPickup,
    422,
    __("This job order isn't ready for pickup yet. Production hasn't marked it complete."),
);
```
and mirror it in `QueueList.vue`:
```ts
return (
    jobOrder.status === 'ready_for_pickup' &&
    (jobOrder.payment_status === 'paid' || jobOrder.payment_status === 'on_credit') &&
    jobOrder.released_at === null
);
```
(`status` is already in the QueueList payload — `QueueEntryController:52`.)

---

## Warnings

### WR-01: `is_rush` uses UTC end-of-day while the rest of the app scopes business dates to Asia/Manila

**File:** `app/Http/Controllers/ProductionStaff/ProductionBoardController.php:43`

**Issue:** `now()->endOfDay()` evaluates in `config('app.timezone')`, which the codebase
keeps at UTC by design. UTC `23:59:59` is Manila **07:59:59 the next morning**, so "due
today" is off by up to 8 hours: a job due tomorrow at 07:00 Manila is badged **Rush** and
row-highlighted amber.

This directly contradicts two sibling helpers that were written specifically to avoid this:
`JobOrder::currentNumberingYear()` (`JobOrder.php:171-174`) and
`QueueEntry::currentBusinessDate()` — both explicitly `->timezone('Asia/Manila')`.

It also produces a self-contradictory board: the client-side `dueLabel()`
(`production-staff/Dashboard.vue:143-170`) uses browser-local `toDateString()`, so a
Manila browser will render "**Tomorrow**, 7:00 AM" next to a "**Rush**" badge on the same
row.

**Fix:**
```php
->each(fn (JobOrder $jobOrder) => $jobOrder->is_rush = $jobOrder->due_at !== null
    && $jobOrder->due_at->lessThanOrEqualTo(now()->timezone('Asia/Manila')->endOfDay()))
```

---

### WR-02: `EnterProduction` is not idempotent and never inspects the job order's current state

**File:** `app/Actions/JobOrder/EnterProduction.php:20-38`

**Issue:** The action unconditionally `forceFill`s `status = ForProduction` and a fresh
`due_at`, then inserts a `null → for_production` log row. It never checks whether the
order is already in production, already released, or cancelled.

Four call sites invoke it (`QueueEntryController:187`, `JobOrderController:40`,
`DesignEditorController:92`, `DesignReviewController:52`), and the only thing preventing a
duplicate "first entry into production" record is each caller's own guard — which CR-03
shows one caller entirely lacks. A retried/double-submitted design approval likewise
produces two `null → for_production` rows and a silently extended SLA clock.

**Fix:** Make the action defend itself:
```php
public function __invoke(JobOrder $jobOrder): void
{
    if ($jobOrder->cancelled_at !== null || $jobOrder->released_at !== null) {
        return;
    }

    if (in_array($jobOrder->status, [
        JobOrderStatus::ForProduction,
        JobOrderStatus::Printing,
        JobOrderStatus::QualityCheck,
        JobOrderStatus::ReadyForPickup,
    ], true)) {
        return;
    }

    DB::transaction(...);
}
```
Add `test('invoking EnterProduction twice writes only one production_logs row and does not reset due_at')`.

---

### WR-03: `cancelled_at` is checked against the stale, pre-lock model and never re-checked inside the transaction

**File:** `app/Http/Controllers/ProductionStaff/ProductionStageController.php:60 & 103` vs. `62-84` & `105-127`

**Issue:** The docblocks (lines 53-56, 98-99) present the locked re-read as "the
idempotency boundary", but the `cancelled_at` guard runs *before* the transaction, on the
route-model-bound instance. The locked re-read at lines 63/106 re-reads `status` but not
`cancelled_at`.

A cancellation committing between the guard and the lock lets the stage transition through
on a cancelled order, writing a `ProductionLog` row for an order that is no longer
actionable — defeating the very idempotency the lock exists to provide.

**Fix:** Move both `abort_if` calls (and CR-04's new `released_at` guard) inside the
transaction, after `lockForUpdate()->firstOrFail()`.

---

### WR-04: "Ready Since" and ready-for-pickup ordering use `updated_at`, which any unrelated write resets

**Files:** `app/Http/Controllers/FrontlineStaff/DashboardController.php:28-29`;
`app/Http/Controllers/FrontlineStaff/QueueEntryController.php:60`;
`resources/js/pages/frontline-staff/Dashboard.vue:87-127, 192`

**Issue:** `updated_at` changes on *every* write to the row — recording a payment,
requesting credit, an owner approving credit, a reconciliation. Taking payment at the
counter for an order that has been on the shelf for two hours resets its "Ready Since" to
"Just now" and jumps it to the bottom of the `oldest('updated_at')` ordering, so the
oldest-waiting order silently stops being surfaced in the QueueList banner (which shows
only the two oldest).

Phase 6 already persists the exact transition moment: the `production_logs` row with
`to_status = ready_for_pickup`.

**Fix:** Derive "ready since" from the production log rather than the mutable row
timestamp, e.g.:
```php
->withMax(['productionLogs as ready_at' => fn ($q) => $q->where('to_status', JobOrderStatus::ReadyForPickup->value)], 'created_at')
->orderBy('ready_at')
```
and send `ready_at` to the page instead of `updated_at`.

---

### WR-05: `throttle:60,1` is under-sized for a page that polls every 5s and is keyed per-IP

**Files:** `routes/web.php:22-24`; `resources/js/pages/public/Tracking.vue:31-47`

**Issue:** Laravel's `throttle` limiter keys guests by IP. `Tracking.vue` starts a 5-second
`usePoll` as soon as a result is found — 12 requests/minute per open tab. Five customers
tracking orders from behind one NAT (shop Wi-Fi, mall, mobile carrier CGNAT) exhaust the
60/min bucket and start receiving 429s, which the page does not handle at all.

Compounding it: the poll never stops. `watch` (lines 37-47) only calls `stop()` when
`result.found` is falsy, so an order whose stage is already **"Completed"** keeps burning
quota indefinitely on every device that ever looked it up.

**Fix:** Raise the limit for this route (it returns three scalar fields), and stop polling
on a terminal stage:
```ts
watch(() => props.result, (value) => {
    if (value?.found && value.stage !== 'Completed' && value.stage !== 'Cancelled') {
        start();
    } else {
        stop();
    }
}, { immediate: true });
```

---

### WR-06: `/track` is a clean enumeration oracle over sequential, guessable job-order numbers

**Files:** `app/Http/Controllers/Public/TrackingController.php:33-49`; `app/Models/JobOrder.php:186-198`

**Issue:** Job order numbers are strictly sequential and fully predictable
(`JO-{year}-0001`, `0002`, …). `/track` is unauthenticated, requires no CAPTCHA or proof
of possession, and returns a clean binary existence signal plus the live production stage.

At the configured 60 req/min a single IP walks an entire year's numbering space in under
an hour. That discloses the shop's total order volume, the current highest order number
(and hence daily throughput by sampling over time), and per-order production progress — a
competitor-useful business-intelligence leak. It is *not* a PII leak: the response shape is
tightly scoped and `TrackingTest.php:108-133` proves description/pricing/payment/file data
never reach the client.

**Fix:** Make the tracking key unguessable rather than relying on rate limiting alone —
e.g. add a random `tracking_token` column, encode `/track?t={token}` into the receipt QR
(`ReceiptController:46`), and keep the human-typed number path behind a much tighter
per-IP limit on *not-found* responses specifically.

---

### WR-07: The new "In Production" fallback badge mislabels every design-stage job order

**Files:** `resources/js/pages/frontline-staff/QueueList.vue:234-236`;
`resources/js/pages/frontline-staff/NewVisit.vue:401-403`

**Issue:** The added `v-else` terminates a chain that only handles `intake`,
`ready_for_production`, `assigned`, and `validation_failed`. Everything else falls through
to "In Production" — including `in_consultation`, `in_design`, `pending_review`, and
`design_approved`, none of which are in production. It also labels already-released orders
(`status` stays `ready_for_pickup`) as "In Production".

Frontline Staff reading the queue will believe a design still being consulted with the
customer is on the press.

**Fix:** Enumerate the real production statuses and give the design stages their own
labels:
```vue
<Badge v-else-if="['for_production','printing','quality_check','ready_for_pickup'].includes(jobOrder.status)" variant="secondary">
    In Production
</Badge>
<Badge v-else variant="secondary">In Design</Badge>
```

---

### WR-08: Receipt QR encodes a dead URL when `number` is null

**Files:** `app/Http/Controllers/Cashier/ReceiptController.php:46`;
`resources/js/pages/cashier/Receipt.vue:159-168`

**Issue:** `number` is `nullable` in the schema (`..._add_number_and_due_at_...:17`) and
typed `string | null` in every TS interface. `route('public.tracking.show', ['number' => null])`
drops the query string entirely — verified, it returns `http://localhost:8000/track`.

So for a job order with a null number the QR code encodes a bare lookup form, and the
printed fallback line renders "Or visit http://host/track and enter " with nothing after
it. The `?? '—'` guard used on the "Job Order No." row two blocks up (line 94) is missing
here.

**Fix:**
```vue
<div v-if="jobOrder.number" class="flex flex-col items-center gap-2 border-t pt-4">
```

---

### WR-09: Cancelled job orders still count as "completed" in the Artist Performance Report

**File:** `app/Http/Controllers/Artist/PerformanceReportController.php:25-36`

**Issue:** The query has no `whereNull('cancelled_at')`. Phase 6 widened the status list
from one status to five *and* `CancellationController` (lines 44-47, also this phase) now
explicitly permits cancelling from all four production statuses. The window in which a
job order can be cancelled while still inflating `jobsCompleted` and `slaAdherence` has
therefore grown substantially.

**Fix:** `->whereNull('cancelled_at')` on the query at line 26.

---

### WR-10: Migration backfill runs after a DDL statement and outside any transaction, leaving an unrepeatable migration on failure

**File:** `database/migrations/2026_09_05_120000_add_number_and_due_at_to_job_orders_table.php:14-22, 47-62`

**Issue:** On MySQL the `ALTER TABLE` at lines 16-19 implicitly commits, so Laravel's
per-migration transaction cannot roll it back. `backfillNumbers()` then issues one bare
`UPDATE` per row with no transaction of its own. If it throws partway — a pre-existing
number colliding on the new unique index, a connection drop on a large table — the
migration is left half-applied: the column exists, some rows are numbered, the batch is not
recorded, and re-running `php artisan migrate` fails with "duplicate column name".

Two smaller correctness issues in the same method:
- `created_at` is nullable (`$table->timestamps()`), and `Carbon::parse(null)` silently
  returns *now* — so an unstamped legacy row is filed under the current year rather than
  its real one.
- `orderBy('created_at')` has no tiebreaker, so rows created in the same second get an
  arbitrary sequence.

**Fix:**
```php
private function backfillNumbers(): void
{
    DB::transaction(function (): void {
        $rows = DB::table('job_orders')
            ->whereNull('number')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'created_at']);
        ...
    });
}
```
The added `whereNull('number')` also makes the backfill safely re-runnable.

---

### WR-11: `readyForPickup.count` and `.items` are two unsynchronised queries on a 5-second poll

**File:** `app/Http/Controllers/FrontlineStaff/QueueEntryController.php:58-61`

**Issue:** `->clone()->count()` and `->clone()->oldest(...)->limit(2)->get()` execute as two
separate statements with no snapshot between them, and the pair runs every 5 seconds per
staff member. A release landing between them yields `count: 1, items: []`, which
`readyForPickupBannerBody()` (`QueueList.vue:92-108`) renders as an empty subject:

> "1 job order ready for pickup /  is waiting on the shelf."

**Fix:** Fetch once and derive both:
```php
$readyForPickup = $readyForPickupQuery->orderBy('updated_at')->get(['id', 'number']);

'readyForPickup' => [
    'count' => $readyForPickup->count(),
    'items' => $readyForPickup->take(2)->map->only('number')->values(),
],
```

---

### WR-12: Stage-transition side effects are unbounded — `sendBack` can loop a job order indefinitely with no rate or count limit

**File:** `app/Http/Controllers/ProductionStaff/ProductionStageController.php:58-135`

**Issue:** Neither action limits how many times an order may bounce between stages. A
double-clicked or scripted send-back/advance pair writes two `ProductionLog` rows and two
`audit_trail` rows per cycle, unbounded. The `lockForUpdate()` re-read prevents two
*concurrent* requests from producing two logs for one logical move, but does nothing about
sequential repeats — which is the actual failure mode for a double-click, since the second
request simply sees the new status and performs a second legitimate-looking move.

The board's own Send Back button offers no confirmation-throttle either
(`production-staff/Dashboard.vue:453-539`).

**Fix:** Low-effort mitigation: disable the buttons for the duration of the request
(already partly done via `:disabled="processing"`) and reject a transition whose
`from_status`/`to_status` pair matches the most recent `ProductionLog` row within a short
window. At minimum, document that repeated bouncing is expected and acceptable so the
production log is not read as a defect.

---

## Info

### IN-01: `JobOrderFactory` generates random, non-sequential numbers using the UTC year

**File:** `database/factories/JobOrderFactory.php:25`

**Issue:** `'JO-'.now()->year.'-'.fake()->unique()->numerify('####')` uses `now()->year`
(UTC) while the application's numbering year is Asia/Manila
(`JobOrder::currentNumberingYear()`). Between 16:00 and 24:00 UTC on 31 December the two
disagree, so factory-made and generator-made numbers land in different years within the
same test run.

`fake()->unique()->numerify('####')` also draws from a 10,000-value pool with retry-on-collision;
any test creating a large number of job orders will slow down and eventually exhaust it.

**Fix:** Use a deterministic sequence anchored to the same year source:
```php
'number' => sprintf('JO-%d-%04d', JobOrder::currentNumberingYear(), fake()->unique()->numberBetween(1, 9999)),
```

---

### IN-02: The 5-digit rollover the code claims to support has no test, and the existing test asserts the opposite

**Files:** `tests/Feature/JobOrder/JobOrderNumberGeneratorTest.php:8`;
`app/Http/Requests/Public/TrackJobOrderRequest.php:19-36`

**Issue:** `expect($number)->toMatch('/^JO-\d{4}-\d{4}$/')` asserts *exactly* four digits,
directly contradicting `TrackJobOrderRequest`'s documented `\d{4,}`. `TrackingTest` covers a
5-digit number *reaching* `/track`, but nothing covers the generator producing or
continuing from one — which is precisely where CR-01 lives.

**Fix:** Add the seeded-9999 → 10000 → 10001 regression test described in CR-01 and relax
the regex here to `/^JO-\d{4}-\d{4,}$/`.

---

### IN-03: The status→label map is duplicated in five places with five different fallbacks

**Files:** `ProductionStageController.php:39-48`; `TrackingController.php:58-71`;
`production-staff/Dashboard.vue:220-225, 386-412`; `cashier/Dashboard.vue:134-142`;
`artist/Dashboard.vue:94-110`; `frontline-staff/QueueList.vue:202-236`

**Issue:** Each copy carries its own `default`/`v-else` behaviour — `$status->value`,
`'In Progress'`, `''`, the raw status string, and `'In Production'` respectively. WR-07 is a
direct consequence: adding four enum cases required editing six unrelated files, and one
was got wrong.

**Fix:** A single `JobOrderStatus::label()` method plus one shared TS map
(`resources/js/lib/jobOrderStatus.ts`) consumed by every page. The controller docblock at
`ProductionStageController.php:36-38` justifies the duplication as "the codebase's
established convention" — that convention is now the source of a bug and is worth revisiting.

---

### IN-04: `v-if`/`v-else` combined with `v-for` on the same element

**Files:** `resources/js/pages/production-staff/Dashboard.vue:351-354`;
`resources/js/pages/frontline-staff/Dashboard.vue:177-180`

**Issue:** `<TableRow v-for="..." v-else :key="...">` works (the compiler nests the list
render inside the else branch) but is explicitly listed as an anti-pattern in the Vue style
guide, and the precedence is non-obvious to the next reader.

**Fix:** Move the `v-else` to a wrapping `<template v-else>` and keep `v-for` on the row.

---

### IN-05: `queue_entry.customer.name` dereferenced without optional chaining, inconsistently with its sibling page

**File:** `resources/js/pages/frontline-staff/Dashboard.vue:188`

**Issue:** Uses `jobOrder.queue_entry.customer.name` while the equivalent line on the
Production Board (`production-staff/Dashboard.vue:383`) uses
`jobOrder.queue_entry?.customer?.name`. Both are polled every 5s and both eager-load the
same relation; the inconsistency means a missing relation renders a blank cell in one page
and throws a render error in the other.

**Fix:** Use `?.` on both, or neither.

---

### IN-06: Magic status string alongside enum comparisons in the same expression

**File:** `app/Http/Controllers/Artist/JobOrderQueueController.php:75, 95`

**Issue:** `$jobOrder->status === JobOrderStatus::InConsultation || $jobOrder->status->value === 'in_design'`
mixes a type-safe enum comparison with a string literal, even though `JobOrderStatus::InDesign`
exists and is imported at line 5. Pre-existing, but this file was modified in this phase and
the pattern will be copied.

**Fix:** `in_array($jobOrder->status, [JobOrderStatus::InConsultation, JobOrderStatus::InDesign], true)`.

---

### IN-07: `number` is mass-assignable

**File:** `app/Models/JobOrder.php:52`

**Issue:** `number` was added to `#[Fillable([...])]`. No current route passes user input
into it — `QueueEntryController::store()` and `::addJobOrder()` both build the array
server-side from `nextNumberForYear()` — so this is not currently exploitable. But the
unique business identifier printed on receipts and used as the public tracking key does not
need to be fillable, and Eloquent factories bypass `$fillable` via `Model::unguarded()`
anyway, so nothing depends on it.

**Fix:** Drop `number` from `#[Fillable]` and set it via `forceFill` at the two write sites.

---

_Reviewed: 2026-09-06T20:18:01Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
