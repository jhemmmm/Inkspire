---
phase: 06-production-monitoring-public-tracking
verified: 2026-09-07T00:00:00Z
status: human_needed
score: 16/16 must-haves verified in code (3 carry a MySQL-concurrency caveat)
overrides_applied: 0
human_verification:
  - test: "Concurrent job-order-number generation on MySQL (CR-01/CR-02 closure)"
    expected: "Two simultaneous requests (store()/addJobOrder()) never generate the same JO-{year}-{seq} number, and the fix's lockForUpdate() + orderByRaw(LENGTH(number) DESC, number DESC) range lock actually serializes writers under MySQL's InnoDB locking, not just SQLite (where lockForUpdate() is a no-op)."
    why_human: "The dev/test environment runs SQLite; lockForUpdate() is a no-op there, so a green test suite cannot prove the lock survives on MySQL. The fixer's own report (06-REVIEW-FIX.md) explicitly flags this as 'requires human verification (MySQL semantics)'. Requires a real MySQL instance and a concurrency harness (e.g. two parallel requests or a manual interleaved-transaction test)."
  - test: "Migration backfill atomicity/re-runnability on MySQL (WR-10 closure)"
    expected: "A partially-failed backfill of database/migrations/2026_09_05_120000_add_number_and_due_at_to_job_orders_table.php is safe to re-run without producing duplicate or colliding numbers, given MySQL's implicit commit on ALTER TABLE (which the migration's own DB::transaction() cannot roll back)."
    why_human: "Same SQLite-vs-MySQL gap as above. The fix makes the backfill's UPDATE loop atomic-within-itself and re-runnable in principle (highestSequencePerYear() primes counters from existing numbers), but this can only be truly proven against MySQL's real DDL/transaction semantics, ideally against a table with pre-existing legacy rows."
  - test: "Physical QR code scan from a printed receipt"
    expected: "Scanning the printed receipt's QR code with a phone camera opens /track?number=JO-... directly and shows the correct current stage, with the code still resolvable at typical print DPI (the component renders SVG specifically for this)."
    why_human: "Automated tests verify the trackingUrl prop is correctly built server-side and the SVG QR component receives it, but actual camera-scannability of a printed physical page cannot be verified by grep or an HTTP test client."
gaps: []
deferred: []
---

# Phase 6: Production Monitoring & Public Tracking Verification Report

**Phase Goal:** A job order's physical progress is visible to Production Staff on an urgency-coded board and to the customer through a public QR-based status check, closing the loop from payment through to pickup.

**Verified:** 2026-09-07
**Status:** human_needed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | PROD-01: Production Staff can view a Production Monitoring board color-coded by urgency (Green=Normal, Amber=Rush) | VERIFIED | `app/Http/Controllers/ProductionStaff/ProductionBoardController.php` computes `is_rush` server-side from `due_at` vs. an Asia/Manila-scoped end-of-business-day (`now()->timezone('Asia/Manila')->endOfDay()` — WR-01 fixed), never from `rush_fee_applied` (`grep -c "rush_fee_applied"` = 0). `resources/js/pages/production-staff/Dashboard.vue` renders an outline `Badge` with literal text "Rush"/"Normal" plus amber/green Tailwind classes — colour is never the sole signal. `ProductionBoardTest.php` passes (part of the 59/59 run below). |
| 2 | PROD-02: Production Staff can advance a job order sequentially For Production → Printing → Quality Check → Ready for Pickup, without skipping any stage, enforced server-side | VERIFIED | `app/Http/Controllers/ProductionStaff/ProductionStageController.php` defines a fixed `private const SEQUENCE` and derives the next/previous status purely via `array_search($jobOrder->status, self::SEQUENCE, true) ± 1` against a `lockForUpdate()`-re-read row — `AdvanceProductionStageRequest`/`SendBackProductionStageRequest` are body-less/reason-only, so a client cannot request an arbitrary target stage. Boundary abort at both ends ("already moved on..."). `StageAdvancementTest.php` passes. |
| 3 | PROD-03: Frontline Staff receives a "Ready for Pickup" alert when a job order reaches that stage | VERIFIED | `FrontlineStaff\DashboardController::index()` and `QueueEntryController::index()` both derive a `readyForPickup` list/summary from `status=ReadyForPickup AND released_at IS NULL AND cancelled_at IS NULL` — nothing stored, self-correcting. `frontline-staff/Dashboard.vue` and `QueueList.vue` both `usePoll(5000, …)` and render a live table / banner (`PackageCheck` icon, count + named orders). `ReadyForPickupAlertTest.php` passes. |
| 4 | TRACK-01/02: A customer can enter a job order number on a public, unauthenticated page and see only its current status — no pricing, payment, PII, or design files | VERIFIED | `app/Http/Controllers/Public/TrackingController.php` queries only `['number','status','released_at','cancelled_at']` and returns only `['found','number','stage']` via a `match` expression (`publicStage()`), with `Cancelled` now checked first (CR-05 fix) ahead of `Completed`/production labels. `TrackingTest.php`'s PII test uses a strict `assertInertia` scope (no `->etc()`, so extra keys fail) AND asserts the raw response body never contains `total_amount`/`payment_status`/`file_path`/the seeded description string. Route confirmed unauthenticated (`php artisan route:list --name=public.tracking.show`), throttled `120,1`. |
| 5 | Every job order gets a real `JO-{year}-{seq}` number at creation, generator is collision-free past 9999 | VERIFIED (code); MySQL lock behavior UNCERTAIN | `JobOrder::nextNumberForYear()` fixed post-review (CR-01): `orderByRaw('LENGTH(number) DESC, number DESC')` + PHP suffix parse from known prefix length, replacing the broken string-`MAX()`+fixed-`substr(-4)` combination. Regression test seeds `JO-2026-9999` → generates `JO-2026-10000` → seeds → generates `JO-2026-10001`. `addJobOrder()` now wraps create+outcome in one `DB::transaction()` (CR-02) so the generator's own inner transaction becomes a savepoint, not a released lock. **Caveat:** SQLite makes `lockForUpdate()` a no-op; the fixer's own report explicitly flags this as needing MySQL confirmation — see Human Verification. |
| 6 | Automatic, no-manual-step entry into `ForProduction` for both a validated Type A file and an approved Type B design (both `approve()` code paths) | VERIFIED | `app/Actions/JobOrder/EnterProduction.php` wired into all four call sites (`QueueEntryController::applyIntakeOutcome()`, `JobOrderController::replaceFile()`, `Artist\DesignEditorController::approve()`, `Public\DesignReviewController::approve()` — confirmed via grep). Action is idempotent (WR-02 fix): returns early if already cancelled/released/in-production. `EnterProductionTest.php`, `DesignReviewTest.php` (both Artist and Public) pass. |
| 7 | Cashier can still price/pay/credit-request a job order after it auto-advances past `ReadyForProduction`/`DesignApproved` into production | VERIFIED | `Cashier\DashboardController`, `PaymentController` (both `edit()`/`store()`), `CreditRequestController::store()` all extended to a six-value `in_array`/`whereIn` including the four new production statuses. `ProductionCompatibilityTest.php` (06-04) and the full Cashier regression sweep pass. |
| 8 | Board excludes released and cancelled job orders; excludes pre-production statuses | VERIFIED | `ProductionBoardController::index()`: `whereIn([4 statuses])->whereNull('released_at')->whereNull('cancelled_at')`. `ProductionBoardTest.php` covers ReadyForProduction/DesignApproved absence, released absence, cancelled absence. |
| 9 | Send Back requires a mandatory reason, recorded on the `production_logs` row with the acting user | VERIFIED | `SendBackProductionStageRequest::rules()` → `ProductionLogValidationRules::sendBackReasonRules()` = `['reason' => ['required','string','max:1000']]`. `ProductionStageController::sendBack()` persists `'reason' => $request->validated('reason')` and `'recorded_by' => $request->user()->id`. |
| 10 | A cancelled or already-released job order cannot be advanced, sent back, have its file replaced, or be released again | VERIFIED | All four guards independently confirmed in code: `ProductionStageController::advance()/sendBack()` (`cancelled_at`, `released_at` — CR-04, re-checked inside the lock per WR-03); `JobOrderController::replaceFile()` (`cancelled_at`, `released_at`, `REPLACEABLE_STATUSES` whitelist — CR-03); `JobOrderReleaseController::store()` (`cancelled_at`, `released_at`, AND now `status === ReadyForPickup` — CR-06, also mirrored client-side in `QueueList.vue`'s `isReleaseEligible()`). |
| 11 | A cancelled job order's public tracking status reports "Cancelled", not a stale production stage | VERIFIED | `TrackingController::publicStage()` checks `cancelled_at !== null` first, before `released_at`/status match (CR-05). Query widened to select `cancelled_at`. Tests cover cancelled-while-printing and cancelled-and-released (Cancelled wins). |
| 12 | Downstream consumers of `DesignApproved`/`ReadyForProduction` (cancellation fee, Artist performance report, Artist queue, staff status badges) keep working once 06-04's auto-advance makes those statuses transient | VERIFIED | `CancellationController` (`$designStarted` extended to 7 values, proven via the real `approve()` route, not a factory shortcut), `PerformanceReportController` (`whereIn` extended + `whereNull('cancelled_at')` per WR-09), `Artist\JobOrderQueueController` (`whereNotIn` extended to exclude 4 production statuses), and four Vue pages (`cashier/Dashboard.vue`, `artist/Dashboard.vue`, `frontline-staff/QueueList.vue`/`NewVisit.vue`) all render real labels instead of blank/stale ones. `CancellationFeeTest.php`, `PerformanceReportTest.php`, `QueueControlsTest.php` all pass driving the real HTTP approve route. |
| 13 | Receipt QR deep-links to the tracking result; degrades safely when `number` is null | VERIFIED | `ReceiptController::show()` adds `trackingUrl` via `route('public.tracking.show', ['number' => $jobOrder->number])`; `TrackingQrCode.vue` renders `QrcodeVue` as SVG (`render-as="svg"`, no `print:hidden`). `Receipt.vue`'s QR block is now `v-if="jobOrder.number"` (WR-08 fix) instead of unconditionally rendering a dead link. |
| 14 | Tracking page polls only in the result state, and stops at a terminal stage | VERIFIED | `Tracking.vue`: `TERMINAL_STAGES = ['Completed', 'Cancelled']`; `watch()` calls `start()` only when `result.found && !TERMINAL_STAGES.includes(result.stage)`, else `stop()` (WR-05 fix). Throttle raised to `120,1` to give this poll headroom. |
| 15 | `production_logs` is append-only/audited, with a nullable `recorded_by` for system-authored rows | VERIFIED | `ProductionLog` carries `#[ObservedBy(AuditObserver::class)]`; migration defines `recorded_by` nullable FK. `ProductionLogModelTest.php` asserts an `audit_trail` row is written and a null `recorded_by`/`from_status` round-trips. |
| 16 | Migration backfill is atomic and safely re-runnable | VERIFIED (code); MySQL DDL-commit interaction UNCERTAIN | `backfillNumbers()` now wraps in `DB::transaction()`, filters `whereNull('number')`, adds `orderBy('id')` tiebreaker, primes per-year counters from existing numbers (`highestSequencePerYear()`). **Caveat:** MySQL's implicit commit on `ALTER TABLE` cannot be rolled back by the wrapping transaction — the fixer's own report flags this as unfixable in-place and recommends confirming on MySQL against a table with pre-existing rows. |

**Score:** 16/16 truths verified in the codebase; 3 of them (#5, #16, and the physical QR scan) carry a caveat that can only be closed by a human testing against a real MySQL instance / physical device, per the escalation gate below.

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Http/Controllers/ProductionStaff/ProductionBoardController.php` | Board listing with server-computed `is_rush` | VERIFIED | Confirmed exists, substantive, wired into `production-staff.dashboard` route, data flows from real `job_orders` table |
| `app/Http/Controllers/ProductionStaff/ProductionStageController.php` | `advance()`/`sendBack()` enforcing fixed SEQUENCE | VERIFIED | Confirmed exists, substantive, wired into two PATCH routes, locked re-read confirmed |
| `app/Http/Controllers/Public/TrackingController.php` | Public unauthenticated status lookup, PII-scoped | VERIFIED | Confirmed exists, substantive, wired into `/track` route, PII boundary independently re-verified against source |
| `app/Actions/JobOrder/EnterProduction.php` | Idempotent auto-advance action | VERIFIED | Confirmed exists, substantive (idempotency guard present), wired into 4 call sites |
| `app/Models/ProductionLog.php` / migration | Append-only stage-transition log | VERIFIED | Confirmed exists, `ObservedBy(AuditObserver::class)` present, nullable `recorded_by` |
| `app/Http/Controllers/FrontlineStaff/DashboardController.php` | Ready-for-pickup derived list | VERIFIED | Confirmed exists, substantive, wired, `ready_at` derived from log not mutable `updated_at` (WR-04 fixed) |
| `resources/js/pages/production-staff/Dashboard.vue` | Real board UI (urgency, stages, actions) | VERIFIED | Confirmed real implementation (stat cards, tabs, urgency badges, Advance/Send Back dialogs) — not a placeholder |
| `resources/js/pages/public/Tracking.vue` | Lookup/result/not-found states, polling | VERIFIED | Confirmed real implementation with terminal-stage poll stop |
| `resources/js/pages/frontline-staff/Dashboard.vue` | Real Ready-for-Pickup table (replaces placeholder) | VERIFIED | Confirmed real implementation with Release action wired to existing `JobOrderReleaseController` |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `production-staff/Dashboard.vue` | `ProductionStageController` | `.advance.form()`/`.sendBack.form()` | WIRED | Confirmed via grep — both Wayfinder-generated forms present and bound to Dialog/Button actions |
| `ProductionStageController` | `ProductionLog` | `ProductionLog::create()` inside `DB::transaction()` | WIRED | Confirmed in both `advance()` and `sendBack()` |
| `QueueEntryController`/`JobOrderController`/`DesignEditorController`/`DesignReviewController` | `EnterProduction` | direct invocation after status-setting `forceFill()->save()` | WIRED | Confirmed at all 4 call sites |
| `Tracking.vue` | `TrackingController` | `router.get(TrackingController.show.url(), …)` | WIRED | Confirmed; route resolves, controller returns scoped payload |
| `TrackingQrCode.vue` | `public.tracking.show` route | server-built `trackingUrl` prop, `QrcodeVue` SVG render | WIRED | Confirmed; null-safe (`v-if="jobOrder.number"`) |
| `frontline-staff/Dashboard.vue` | `JobOrderReleaseController` | `.store.form(jobOrder.id)` | WIRED | Confirmed; same server action Phase 5 built, second entry point |
| `JobOrder::nextNumberForYear()` | `job_orders.number` | `lockForUpdate()` range lock nested in caller's outer transaction | WIRED (SQLite-verified only) | Both `store()` and `addJobOrder()` now wrap the create in `DB::transaction()` — confirmed in source; lock survival under MySQL not independently provable here |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
|----------|---------------|--------|---------------------|--------|
| `production-staff/Dashboard.vue` | `jobOrders` (incl. `is_rush`) | `ProductionBoardController::index()` — real `job_orders` query, `is_rush` computed per-row from `due_at` | Yes | FLOWING |
| `public/Tracking.vue` | `result` | `TrackingController::show()` — real `job_orders` lookup by `number` | Yes | FLOWING |
| `frontline-staff/Dashboard.vue` | `readyForPickup` | `FrontlineStaff\DashboardController::index()` — real query with `withMax` over `production_logs` | Yes | FLOWING |
| `cashier/Receipt.vue` | `trackingUrl` | `ReceiptController::show()` — `route()` helper with real `$jobOrder->number` | Yes | FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| `/track` route is unauthenticated and resolves | `php artisan route:list --name=public.tracking.show` | Route present, no auth middleware, `throttle:120,1` | PASS |
| Production Staff routes exist and are role-gated | `php artisan route:list --path=production-staff` | 3 routes present (`dashboard`, `advance`, `send-back`), `role:production_staff` group confirmed via grep | PASS |
| Full phase-relevant test suite passes | `vendor/bin/pest tests/Feature/Public/TrackingTest.php tests/Feature/ProductionStaff/ProductionBoardTest.php tests/Feature/ProductionStaff/StageAdvancementTest.php tests/Feature/FrontlineStaff/ReadyForPickupAlertTest.php --compact` | 59/59 passed | PASS |
| Full application suite passes (regression) | `php artisan test --compact` | 385 tests, 382 passed, 3 skipped, 0 failures — matches SUMMARY/REVIEW-FIX claims exactly | PASS |
| Larastan clean except known pre-existing Phase 5 errors | `composer types:check` | Exactly the 5 documented pre-existing errors in `CreateCreditRequestRequest`/`SavePricingAndPaymentRequest`/`UpdateSystemConfigurationRequest` — no new errors from Phase 6 files | PASS |
| Pint clean on Phase 6 files | `vendor/bin/pint --test --format agent` | Only unrelated pre-existing files flagged (`tests/Feature/Auth/*`, `LoginResponse.php`) — none touched by Phase 6 | PASS |

### Probe Execution

Step 7c: SKIPPED — no `scripts/*/tests/probe-*.sh` files exist in the repository and no plan/summary for this phase declares a probe-based verification contract.

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|--------------|--------|----------|
| PROD-01 | 06-01, 06-04, 06-06, 06-07 | Production Staff can view a Production Monitoring board color-coded by urgency | SATISFIED | `ProductionBoardController`, `production-staff/Dashboard.vue` |
| PROD-02 | 06-01, 06-04, 06-06, 06-07 | Production Staff can advance a job order sequentially without skipping stages | SATISFIED | `ProductionStageController::advance()`/`sendBack()` |
| PROD-03 | 06-05 | Frontline Staff receives a "Ready for Pickup" alert | SATISFIED | `FrontlineStaff\DashboardController`, `Dashboard.vue`, `QueueList.vue` banner |
| TRACK-01 | 06-01, 06-02, 06-03 | Customer can enter a job order number on a public page and see current status | SATISFIED | `TrackingController`, `Tracking.vue`, `JobOrder::nextNumberForYear()` |
| TRACK-02 | 06-03 | Tracking page shows status only — no pricing/payment/PII/design files | SATISFIED | `TrackingController::publicStage()` allowlist + strict PII test |

No orphaned requirements found — all 5 phase requirement IDs (PROD-01, PROD-02, PROD-03, TRACK-01, TRACK-02) are declared across the 7 plans' frontmatter and each maps to verified implementation.

### Anti-Patterns Found

No debt markers (TBD/FIXME/XXX/TODO/HACK/PLACEHOLDER) found in any of the 34 Phase 6-touched application/frontend files (one incidental match is an HTML `placeholder="09XXXXXXXXX"` attribute on a phone input field — not a code stub).

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| N/A | — | — | — | No blocker or warning-level anti-patterns found in the current code (post-review-fix state) |

**Documented, reasoned deviations from the code review (informational, not gaps):**

- **WR-06** (`/track` enumeration oracle over sequential job-order numbers) — deliberately skipped. Reasoned as an accepted, low-severity business-intelligence leak (order volume/throughput), explicitly not a PII leak (verified independently by the strict PII test in this report). Partial mitigation already shipped (WR-05's terminal-stage poll stop, a modest throttle raise). Recommended by the fixer as its own follow-up plan — matches the phase's own D-01/T-06-03-02 threat-model disposition of "accept."
- **WR-12** (unbounded stage-transition bouncing) — deliberately skipped with a reasoned rebuttal: repeated send-back is legitimate business behavior (a second real rejection), not a duplicate-request artifact; the actual double-click/concurrency hole is closed by WR-03 (guards moved inside the lock). No product-defined threshold exists to enforce. This reasoning was independently verified against the code — `ProductionStageController` guards are correctly inside the `lockForUpdate()` transaction (WR-03 confirmed fixed).

Both are Warning-level (not Critical) findings, do not affect any of the four roadmap Success Criteria, and are explicitly documented with sound engineering rationale — not silently dropped work.

### Human Verification Required

### 1. Concurrent job-order-number generation on MySQL

**Test:** Under a real MySQL database (not SQLite), fire two near-simultaneous requests that each create a job order for the same numbering year (e.g. two `POST frontline-staff.queue-entries.job-orders.store` requests against different visits) and confirm both succeed with distinct, sequential numbers — no unique-constraint violation, no duplicate.
**Expected:** Each request receives a distinct `JO-{year}-{seq}` number; the `lockForUpdate()` range lock correctly serializes the two writers.
**Why human:** SQLite (the test/dev database) makes `lockForUpdate()` a no-op, so a green automated test suite cannot prove this. The code fix (CR-01/CR-02) is sound in shape and reasoning, but production is MySQL and this specific guarantee has not been exercised against real InnoDB locking. The executor's own fix report flags this explicitly as "requires human verification (MySQL semantics)."

### 2. Migration backfill atomicity on MySQL

**Test:** On a MySQL staging database with pre-existing `job_orders` rows (some without a `number`), run the `2026_09_05_120000_add_number_and_due_at_to_job_orders_table` migration, deliberately interrupt it partway if possible (or review its behavior under MySQL's implicit DDL commit), and re-run `php artisan migrate`.
**Expected:** The migration completes cleanly, or a partial failure is safely resumable without producing duplicate or colliding numbers.
**Why human:** Same SQLite-vs-MySQL gap. MySQL's implicit commit on `ALTER TABLE` cannot be rolled back by the migration's own wrapping transaction — the fixer's report is explicit that this is "unfixable in-place" and recommends confirming behavior on the real target engine before relying on it, ideally against a table with legacy rows.

### 3. Physical QR code scan from a printed receipt

**Test:** Print a customer receipt (via the existing "Print Receipt" flow) and scan its QR code with a phone camera.
**Expected:** The camera resolves the code and opens `/track?number=JO-...` directly, landing on the correct current stage, at normal receipt-printer resolution.
**Why human:** This is the literal "QR-based status check" the phase goal names. Automated tests confirm the URL is built correctly server-side and the component renders as SVG (chosen specifically for print crispness), but actual scannability from a physical printed page cannot be verified by an HTTP test client.

### Gaps Summary

No gaps found. Every roadmap Success Criterion and every plan-level must-have was independently traced to real, substantive, wired code — not SUMMARY.md claims — and cross-checked against the code review (25 findings) and its fix pass (16 fixed, 2 reasoned skips). All 6 Critical findings and 10 of 12 Warning findings were fixed and independently re-verified against current source in this pass (CR-01 through CR-06, WR-01 through WR-05, WR-07 through WR-11). The 2 skipped Warnings (WR-06, WR-12) are Warning-level, explicitly documented with sound reasoning, and do not touch any of the four roadmap Success Criteria.

The phase is held at `human_needed` rather than `passed` solely because three items require confirmation this verifier cannot obtain from a SQLite-backed automated run: two MySQL-specific concurrency/DDL guarantees (CR-01/CR-02's locking fix, WR-10's backfill atomicity) that the fix authors themselves flagged as needing human confirmation before go-live, and one physical device test (QR scan) inherent to the "closing the loop through pickup" goal. None of these represent missing or stubbed functionality — the code, tests, and reasoning for all three are present and sound; they need a human with access to a real MySQL instance and a printer/camera to close out.

---

_Verified: 2026-09-07_
_Verifier: Claude (gsd-verifier)_
