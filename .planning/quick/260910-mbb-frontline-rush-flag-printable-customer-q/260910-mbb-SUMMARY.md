---
phase: quick-260910-mbb
plan: 01
subsystem: frontline-intake, public-tracking, cashier-pos
tags: [rush, qr-tracking, customer-history, print, pii-boundary, tdd]
requires:
    - job_orders table
    - public.design-review.* signed routes
    - TrackingQrCode.vue, SearchableSelect.vue, DataTableCard.vue, SectionHeading.vue
provides:
    - job_orders.is_rush (persisted, NOT NULL)
    - job_orders.tracking_token (unique, 32 chars)
    - GET track/{token} → public.tracking.token
    - customerJobOrders + trackingBaseUrl props on frontline-staff/NewVisit
affects:
    - Frontline Staff New Visit and Queue List
    - Artist Dashboard and Job Order Workspace
    - Cashier Dashboard and Job Order Payment
    - Production Board is_rush semantics
tech-stack:
    added: []
    patterns:
        - reka-ui Switch in an uncontrolled Inertia Form needs an explicit value="1"
          plus a preceding hidden "0" when the server rule is required|boolean
        - Tailwind print: variants only for print isolation (no @media print in app.css)
        - Eloquent creating hook for non-fillable generated credentials
key-files:
    created:
        - database/migrations/2026_09_10_120000_add_is_rush_to_job_orders_table.php
        - database/migrations/2026_09_10_120100_add_tracking_token_to_job_orders_table.php
        - resources/js/pages/public/TrackingToken.vue
        - tests/Feature/FrontlineStaff/RushJobOrderTest.php
        - tests/Feature/FrontlineStaff/CustomerJobOrderHistoryTest.php
        - tests/Feature/Cashier/CashierDashboardPaidFilterTest.php
        - tests/Feature/Public/TrackingTokenTest.php
    modified:
        - app/Models/JobOrder.php
        - app/Concerns/JobOrderValidationRules.php
        - app/Http/Controllers/FrontlineStaff/QueueEntryController.php
        - app/Http/Controllers/FrontlineStaff/CustomerController.php
        - app/Http/Controllers/Artist/JobOrderQueueController.php
        - app/Http/Controllers/Artist/JobOrderWorkspaceController.php
        - app/Http/Controllers/Cashier/DashboardController.php
        - app/Http/Controllers/ProductionStaff/ProductionBoardController.php
        - app/Http/Controllers/Public/TrackingController.php
        - routes/web.php
        - resources/js/pages/frontline-staff/NewVisit.vue
        - resources/js/pages/frontline-staff/QueueList.vue
        - resources/js/pages/artist/Dashboard.vue
        - resources/js/pages/artist/JobOrderWorkspace.vue
        - resources/js/pages/cashier/Dashboard.vue
        - resources/js/pages/cashier/JobOrderPayment.vue
decisions:
    - Production Board is_rush = persisted column OR due-date heuristic, display-only
    - Fully-paid rejection happens in PHP off the existing withSum, not a second query
    - track/{token} response pinned to exactly four keys by a ->has('result', 4) assertion
    - tracking_token stays DB-nullable; uniqueness is the index, presence is the model hook
metrics:
    tasks: 4
    commits: 8
    duration: ~2h
    completed: 2026-09-10
---

# Quick Task 260910-mbb: Frontline Rush Flag, Printable Customer QR, History Gate, Paid-Order Filter Summary

Rush is now a real column written at the counter and visible to every downstream
role; each job order carries an unguessable token behind a public four-key
tracking page and a printable QR slip; returning customers show their order
history before the intake form; and fully-paid job orders leave the Cashier's
worklist.

## What Was Built

**Task 1 — persisted rush flag.** `job_orders.is_rush` (boolean, NOT NULL,
default false). Written by both frontline intake paths: `QueueEntryController::store()`
uses `filter_var(..., FILTER_VALIDATE_BOOLEAN)` because the Inertia FormData path
delivers the string `"1"`/`"0"` while a JSON payload delivers a real boolean;
`addJobOrder()` uses `$request->boolean()`. A single `is_rush` rule added to
`printSpecificationRules()` covers both `job_orders.*.is_rush` and `is_rush`.
The stale `@property bool|null $is_rush Not a persisted column` PHPDoc was
replaced. UI: a `v-model` Switch on New Visit, and a `name="is_rush" value="1"`
Switch in the Add Job Order dialog.

**Task 2 — rush downstream, plus the paid filter.** The Production Board's
displayed `is_rush` is now the OR of the persisted column and its existing
due-date heuristic, widened in memory and never saved. Amber Rush badges on the
Artist queue, the Available Jobs pool, the Job Order Workspace and the Cashier
dashboard row. `Apply Rush Fee` pre-checks from `is_rush` only while
`rush_fee_amount` is still null, so a Cashier who already declined the fee is
never overruled on a revisit. The Cashier dashboard rejects fully-paid rows in
PHP off the `withSum` that already ran, with a 0.005 epsilon and `->values()`.

**Task 3 — public tracking token.** `job_orders.tracking_token`, backfilled
per-row then unique-indexed, assigned by a `JobOrder::booted()` `creating` hook
and deliberately outside `#[Fillable]`. `GET track/{token}` is public,
unauthenticated and throttled at 120/min, returning exactly
`found`/`number`/`stage`/`reviewUrl`. `reviewUrl` mints a freshly signed
`public.design-review.show` link only when the job order is `pending_review` AND
its latest revision by `submitted_at` is unresolved AND the
`submitted_at + 7 days` expiry has not already passed.

**Task 4 — QR slips and the history gate.** `customerJobOrders` (one constrained
query, newest-first by id, capped at 20) gates the intake form for returning
customers behind a New Job Order button, rendered twice so it is never a screen
away. Customer selection now round-trips through the server; `clearCustomer()`
drops the `customer` query parameter so re-picking the same customer still fires
the watch. One printable QR slip per job order carrying the QR, number,
customer name and one instruction line, printed individually with Tailwind
`print:` variants only.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] The Cashier could not record a payment on an unpriced job order**

- **Found during:** Task 2 browser verification
- **Issue:** `rush_fee_applied` is `required|boolean`, but the page bound it to a
  bare `<Switch v-model name="rush_fee_applied">`. reka-ui's `SwitchRoot` renders
  a real checkbox, so an unchecked switch was omitted from the submission
  entirely and the save returned `The rush fee applied field is required.` — and
  a checked one would have submitted reka-ui's default string `'on'`, which fails
  the `boolean` rule. Pre-existing (present in the committed file at `c0ee7db`),
  invisible to the suite because every existing test posts `rush_fee_applied`
  explicitly. It blocked this task's own acceptance criterion ("saving with it
  off records no rush fee"), so it was fixed rather than deferred.
- **Fix:** A hidden `<input type="hidden" name="rush_fee_applied" value="0">`
  placed _before_ the Switch, and `value="1"` on the Switch. Unchecked submits
  `"0"`; checked submits `"0"` then `"1"`, which PHP resolves to the last value.
  The server rule was deliberately left as `required|boolean` so a future broken
  form fails loudly rather than silently defaulting.
- **Files modified:** `resources/js/pages/cashier/JobOrderPayment.vue`,
  `tests/Feature/Cashier/RecordPaymentTest.php` (two regression tests: the
  `"0"`/`"1"` pair is accepted; omission is still rejected)
- **Commit:** 5cdc28c

**2. [Rule 3 - Blocking] `confirmedQueueEntry.job_orders` did not carry `number`**

- **Found during:** Task 4
- **Issue:** The slip must show the job order number, but the eager-load column
  list omitted it, so `vue-tsc` failed on `Property 'number' does not exist on
type 'ConfirmedJobOrder'`.
- **Fix:** Added `number` to the `jobOrders:` select and to the interface, and
  extended the existing history test to assert it.
- **Files modified:** `app/Http/Controllers/FrontlineStaff/CustomerController.php`,
  `resources/js/pages/frontline-staff/NewVisit.vue`,
  `tests/Feature/FrontlineStaff/CustomerJobOrderHistoryTest.php`
- **Commit:** 714cb4c

### Adjusted, with reasoning

**The token-absence assertion.** The plan asked for
`assertDontSee($token, false)`. That cannot hold: Inertia serialises the current
request URL into `page.url`, and the token _is_ the URL path. The assertion was
made stricter and more honest instead — the token must not appear anywhere in the
`result` prop, and must appear **exactly once** in the whole body, in
`page.url`. A genuine re-selection of the column would push that count to two and
fail. The echo discloses nothing the visitor does not already hold in their
address bar.

**`git diff resources/css/app.css` is not empty.** The plan lists this as a
verification step. The file carries pre-existing uncommitted changes from the
prior royal-blue reskin task; it is untouched by this plan (last committed at
`75e5cc6`, absent from all eight commits here) and contains no `@media print`
rule. No `app.css` addition was needed — the Tailwind `print:` approach worked.

### Noted, not changed

Staging by explicit path, as instructed, swept the working tree's pre-existing
uncommitted changes in the same files into these commits — most visibly
`NewVisit.vue`, which carried ~870 lines of earlier unrelated work. Nothing was
reverted or stashed.

## Threat Model Compliance

| Threat ID | Disposition | Evidence                                                                                                                                                                                                            |
| --------- | ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| T-mbb-01  | mitigated   | `showByToken` selects five columns, returns four keys; `->has('result', 4)` pins the shape; unescaped-body assertions cover name, contact, email, address, price digits, raw enum, `payment_status`, `total_amount` |
| T-mbb-02  | mitigated   | Token never selected into the response; asserted absent from `result` and present exactly once (in `page.url`)                                                                                                      |
| T-mbb-03  | mitigated   | `Str::random(32)` in the model `creating` hook, unique-indexed, route throttled 120/min                                                                                                                             |
| T-mbb-04  | mitigated   | No route added to the `design-review` prefix, `signed` middleware untouched; unsigned URL returns 403 in both the suite and the browser                                                                             |
| T-mbb-05  | mitigated   | `reviewUrl` requires PendingReview + latest-by-`submitted_at` + null outcome + unexpired; three tests cover the null cases                                                                                          |
| T-mbb-06  | accepted    | `is_rush` only pre-checks a toggle; `ComputeJobOrderPrice` and `rush_fee_applied` semantics untouched                                                                                                               |
| T-mbb-07  | accepted    | `?customer=` sits behind `auth` + `role:frontline-staff` on a page already rendering the same customer's PII                                                                                                        |
| T-mbb-08  | mitigated   | No `composer require`, no `npm install`; `qrcode.vue` reused via the existing `TrackingQrCode` component                                                                                                            |

## Browser Verification (CLAUDE.md rule 10)

Driven through CDP against a real Chrome, logged in with seeded accounts.
Observed, not inferred:

**Rush toggle.** New Visit switch: off → on → off → on by mouse, then off → on by
Space with keyboard focus. Submitted a two-job-order visit with rush on row 1
only → DB showed `is_rush=true` / `is_rush=false`. Repeated the change-your-mind
path (on, then off, then submit) → saved `false`. Add Job Order dialog: switch
toggled, hidden input read `value="1" checked=true`, job order persisted rush.

**Rush downstream.** Rush badge present on the Artist pool row, the My Queue row,
the Job Order Workspace header and the Cashier dashboard row; absent (with no
empty gap) on 28 non-rush rows.

**Rush fee.** Rush unpriced job order → switch pre-checked; clicked off, saved →
`rush_fee_applied=false`. Non-rush job order → switch off. Left on for another
rush job order → `rush_fee_applied=true`.

**Paid filter.** Recorded a full cash payment through the real form → that row
left the dashboard on the next load; a ₱300 down payment on another → that row
stayed.

**Public tracking.** `/track/{token}` logged out rendered number + stage only;
page source contained no customer name, contact, address, price or raw enum.
`/track/garbage` gave the friendly not-found card, not an error page. A
`pending_review` order surfaced a signed review link; clicking it loaded the
design-review page, "Client Approved" completed, the job order moved to
`for_production`, and the token page then showed "For Production" with the link
gone. The unsigned design-review URL returned 403.

**QR slips.** A two-job-order visit rendered two slips with distinct QR SVGs.
Under emulated print media the entire printable text was exactly
`JO-2026-9945 | Mr. Gino Willms MD | Scan this code to follow your order.` plus
the second slip — no sidebar, header, page title, step bar, queue-number card or
buttons. Pressing Print Slip on the second printed only the second; after
`afterprint` both returned, and pressing Print Slip on the first then printed
only the first. Both slip URLs were fetched and resolved to their own job order's
tracking page.

**History gate.** Returning customer → history table, form hidden, URL
round-tripped to `?customer=1&q=Gino`. New Job Order (mouse and Enter) revealed
the form. Change → different customer → gate re-armed. Change → **same** customer
again (after having opened the form) → gate re-armed, not stuck. First-time
customer → form immediately, no extra click.

**Themes and viewport.** Every new surface checked at 375px and desktop, light
and dark: no horizontal page overflow anywhere, the history table scrolls inside
its own card, and all colours resolve through semantic tokens.

## Verification Results

| Gate                                     | Result                                                                                                                                                                                        |
| ---------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `php artisan test --compact`             | **588 tests, 581 passed, 7 skipped, 0 failed** (baseline 551/544/7/0 → +37 tests)                                                                                                             |
| `npm run types:check`                    | clean                                                                                                                                                                                         |
| `npm run build`                          | clean                                                                                                                                                                                         |
| `npx vp check`                           | clean for every file under `app/`, `resources/`, `routes/`, `database/`, `tests/`; 235 pre-existing Markdown/JSON failures left alone (see `deferred-items.md`)                               |
| `vendor/bin/pint --dirty --format agent` | passed                                                                                                                                                                                        |
| `php artisan route:list --path=track`    | both routes present, `web` + `throttle:120,1` only, no `auth`, no `role:*`                                                                                                                    |
| `migrate:fresh --seed`                   | run against a throwaway SQLite DB (to avoid wiping the dev database): 33 job orders, 0 null tokens, 33 distinct tokens, 0 null `is_rush`; `migrate:rollback --step=2` and re-apply both clean |
| inline styles / `<style>` / `app.css`    | none added; `print:` variants only                                                                                                                                                            |
| new dependencies                         | none                                                                                                                                                                                          |

`composer types:check` was not run — Larastan is broken in this environment with
`Undefined constant Larastan\Larastan\LARAVEL_VERSION`, pre-existing and
unrelated, as the plan instructs.

## Commits

| Commit  | Message                                                                                |
| ------- | -------------------------------------------------------------------------------------- |
| 481fbba | test(quick-260910-mbb): add failing tests for persisted is_rush intake flag            |
| f9d6aae | feat(quick-260910-mbb): persist is_rush and capture it on both intake paths            |
| a792d36 | test(quick-260910-mbb): add failing tests for paid-order filter and rush downstream    |
| 5cdc28c | feat(quick-260910-mbb): surface rush downstream and drop fully-paid orders             |
| a749686 | test(quick-260910-mbb): add failing tests for the public tracking token route          |
| 4d8da08 | feat(quick-260910-mbb): add an unguessable tracking token and a public token route     |
| aa7abc7 | test(quick-260910-mbb): add failing tests for customer history and QR slip props       |
| 714cb4c | feat(quick-260910-mbb): printable QR slips and returning-customer history on New Visit |

Every task followed a RED → GREEN pair; each `test(...)` commit was verified
failing before its `feat(...)` counterpart was written.

## Known Stubs

None. Every prop added in this plan is wired to real data and was observed
rendering in a browser.

## Deferred Issues

See `deferred-items.md` in this directory. The notable one: `withSum()` sets
`select('table.*')`, which makes any subsequent `->get([...narrow list...])`
silently inert — the Cashier dashboard has been shipping the full job order model
despite its seven-column list. Pre-existing, not a privilege leak (the Cashier is
authorised for that row), and out of scope here.

## Self-Check: PASSED

All created files verified present on disk and all eight commit hashes verified
in `git log`.
