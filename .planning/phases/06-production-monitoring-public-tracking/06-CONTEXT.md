# Phase 6: Production Monitoring & Public Tracking - Context

**Gathered:** 2026-09-05
**Status:** Ready for planning

<domain>
## Phase Boundary

A job order that has become production-ready (Type A at `ReadyForProduction`, Type B at `DesignApproved`) becomes physically trackable: Production Staff work it on an urgency-coded board through For Production → Printing → Quality Check → Ready for Pickup, Frontline Staff sees a live "ready for pickup" list, and the customer checks its stage on a public unauthenticated page reached by typing a job order number or scanning a QR from their receipt. Covers PROD-01, PROD-02, PROD-03, TRACK-01, TRACK-02. This phase creates `production_logs` (one of the approved 12 ERD tables, still unbuilt) and adds a human-readable job order number to `job_orders`.

**Explicitly not this phase:** the Release/Hand-over payment gate itself (built in Phase 5, D-16 — this phase consumes it, does not re-implement it), AR aging/reminders (Phase 7), and the Production Status report (Phase 8 — this phase only produces the `production_logs` data it will read).

</domain>

<decisions>
## Implementation Decisions

### Public Tracking Identifier & QR

- **D-01:** `job_orders` gains a **human-readable job order number** — the identifier a customer types on the public tracking page. Today the table has only an auto-increment `id`; TRACK-01's "enter a job order number" needs a real, speakable, printable value. Rejected alternatives: raw `id` (opaque to the customer and no better against enumeration), a second-factor check (last 4 of contact number), and an opaque unguessable token (a customer who lost their receipt would have nothing to type). Enumeration risk is accepted, mitigated by D-02's minimal payload plus the existing `throttle:60,1` public-route convention.
- **D-02:** The public tracking page shows the **current production stage and nothing else** — no timeline, no product description, no pricing, payment, PII, or design files. The strictest reading of TRACK-02. The product description was specifically rejected as a disambiguation aid because it is customer-authored free text captured at Phase 2 intake and could contain anything, which would silently weaken the no-PII guarantee. Implement the payload the same way `QueueDisplayController` does: an explicit column allowlist, never a full model.
- **D-03:** A **QR code on the Phase 5 digital receipt (POS-06) deep-links to that order's status** — it encodes the tracking URL with the job order number already in it, so scanning lands on the status directly with no typing. This requires a QR generation library, which is a **new dependency and needs explicit user approval** per PROJECT.md's dependency constraint (same gate Phase 4's `resend/resend-php` and `tui-image-editor` went through). Rejected: a QR pointing at a blank lookup page (throws away the convenience), and deferring QR entirely (PROJECT.md's "What This Is" names QR-based tracking as a headline capability).
- **D-04:** Number format is **`JO-{year}-{seq}`, sequence resetting yearly** (e.g. `JO-2026-0001`) — matching the demo's `JO-2026-1008` shape. Generate it with the same `DB::transaction()` + `lockForUpdate()` approach Phase 2 established for queue numbers (02-CONTEXT D-17), keyed on year rather than date, so no new counter table is needed and the 12-table ERD is respected.

### Production Board Urgency (Green / Amber)

- **D-05:** Urgency is **derived from an SLA deadline, not from the Phase 5 rush-fee flag**. Amber = Rush is computed against a due date built from the already-seeded `default_sla_days` system configuration. `rush_fee_applied` was rejected as the source because it is set by the Cashier at POS time, while Phase 6 depends on Phase 3 — per Phase 5 D-15 an unpaid order legitimately reaches the board with no rush flag set, and would wrongly show Green. This also matches the demo, which carries `deadline` and `urgency` as a pair.
- **D-06:** The due date is **stamped and stored on entry to production** — a `due_at`-style column written on `job_orders` at the moment the order enters For Production, computed as that timestamp plus `default_sla_days`. Stored rather than computed on the fly so the board can sort and filter in SQL, so a later config change does not retroactively rewrite history, and so Phase 8's Production Status report has a real column to report against. Rejected: stamping at Phase 2 intake, which would burn SLA during consultation and design and force a retrofit of the intake flow.
- **D-07:** The Green/Amber line is a **hardcoded "due today or overdue"** rule — no configurable threshold key. Deliberately chosen over a new `system_configurations` entry: the rule reads naturally to production staff and there is nothing yet asking to tune it. Note this is a conscious departure from the phase's config-driven neighbours (`default_sla_days` itself is configurable); if a warning window is later wanted, adding the key follows the established `business_rules` seeding pattern.

### Board Entry, Stage Sequence & Rework

- **D-08:** Orders enter the board **automatically the moment they become ready** — Type A at `ReadyForProduction`, Type B at `DesignApproved`. No manual "accept into production" step; nothing can be dropped at the handoff, and the phase stays dependent only on Phase 3/4 statuses exactly as ROADMAP.md has it.
- **D-09:** **`ForProduction` is a real `JobOrderStatus` value, written automatically** on that entry (actor = system, following Phase 3's auto-assignment precedent), not a display-only label over `ReadyForProduction`/`DesignApproved`. This keeps all four PROD-02 stages as genuine statuses, makes sequence validation one uniform rule with no special case at the front, and guarantees a first `production_logs` row exists for Phase 8's report.
- **D-10:** Forward movement is **strictly one stage at a time**, no skipping — PROD-02 as written.
- **D-11:** Backward movement is allowed **one stage only, with a mandatory reason** recorded on the `production_logs` row (Quality Check fails → back to Printing). Real print shops need this; without it a bad print would have to be handled outside the system or by cancelling and re-creating the job order, losing its history. The recorded reason is also what makes a future reprint/waste report possible. Rejected: strictly-forward-only (too rigid) and free backward jumps to any stage (allows large unexplained jumps in the log).
- **D-12:** An order **leaves the board when Phase 5's Release/Hand-over action stamps `released_at`**, not when it reaches Ready for Pickup. Production keeps visibility of what is sitting on the pickup shelf, and this reuses the existing gate rather than inventing a second exit. Board queries filter on `released_at` being null.

### Ready-for-Pickup Alert (PROD-03)

- **D-13:** The alert is a **derived polled query, with nothing stored** — the Frontline portal polls for job orders at `ReadyForPickup` with a null `released_at` and renders them as a live list/count; the "alert" is simply that list being non-empty. No notifications table, so the approved 12-table ERD is not extended, there is no read/unread state to drift out of sync with reality, and the alert self-corrects if an order is sent back a stage under D-11. Rejected: stored notification rows (a new table needing the same explicit justification `system_configurations` required) and email (push-only, and Phase 4 already had to add mail-failure isolation so a transport outage cannot break a status transition).
- **D-14:** Polling interval is **5000ms, matching `resources/js/pages/public/QueueDisplay.vue`**, for the production board, the Frontline alert, and the public tracking page alike — one consistent number, already proven in Phase 2. Use Inertia's `usePoll` with `only:` partial reloads. The no-websockets constraint is project-wide and non-negotiable.

### Claude's Discretion

- **Board layout** — kanban columns by stage vs. a single urgency-sorted list. Explicitly left to the UI pass; this phase has `UI hint: yes` in ROADMAP.md, so `/gsd-ui-phase` can settle it against `demo/main.js`. The business rules (four stages, two colours, sequential advance, back-one-with-reason) hold under either layout.
- **Whether the Frontline ready-for-pickup list also carries the Release/Hand-over button** — placement, not a business rule. If planning wires it there, it MUST call the existing Phase 5 gate (which checks payment/active-credit and refuses with a message), never re-implement the check.
- **Backfilling job order numbers onto existing rows.** Phases 2–5 created `job_orders` rows with no number. Planning must decide whether the migration backfills them (`JO-2026-xxxx` by `created_at` order) or whether only new orders get numbers — backfill is strongly preferred so no existing order is untrackable, but the mechanism is an implementation call.
- **Exact column names and enum casing** — `due_at`/`production_due_at`, `job_order_number`/`number`, and the new `JobOrderStatus` cases (`ForProduction`, `Printing`, `QualityCheck`, `ReadyForPickup`). Follow the existing string-backed TitleCase-key convention in `app/Enums/JobOrderStatus.php`.
- **`production_logs` schema** — follow the Eloquent model + `#[Fillable]` + `#[ObservedBy(AuditObserver::class)]` conventions Phases 2–5 established. It needs at minimum the job order, from/to stage, actor, timestamp, and D-11's reason column.
- **Which QR library** — subject to D-03's approval gate; a server-side PHP generator rendering into the receipt is the expected shape, but the specific package is research/planning territory.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements & Roadmap

- `.planning/REQUIREMENTS.md` §Production Monitoring — PROD-01, PROD-02, PROD-03 full requirement text
- `.planning/REQUIREMENTS.md` §Tracking — TRACK-01, TRACK-02 full requirement text; TRACK-02's exclusion list is what D-02 implements literally
- `.planning/ROADMAP.md` §Phase 6 — goal, four success criteria, `UI hint: yes`, and the "Depends on: Phase 3" line that grounds D-05 (orders reach the board unpaid)
- `.planning/ROADMAP.md` §Phase 8 — depends on Phase 6 for the Production Status report; grounds D-06's stored `due_at` and D-09's guaranteed first log row

### Project-Level Context

- `.planning/PROJECT.md` §Constraints — approved 12-table ERD (`production_logs` is one of the twelve, unbuilt); `audit_trail` append-only; dependency changes require approval (D-03's QR library)
- `.planning/PROJECT.md` §Out of Scope — Laravel Echo/Reverb/websockets are locked out; polling only (D-14)
- `.planning/PROJECT.md` §Active Requirements — "Real-time-ish cross-role notifications (e.g. 'Ready for Pickup' alerts to Frontline) via client-side polling, not websockets" is the project-level statement D-13/D-14 implement
- `.planning/PROJECT.md` §Context — the deliberate `job_orders.status` / `payment_status` column split; production stage lives in `status`, and this phase extends that enum only

### Prior Phase Context

- `.planning/phases/02-customer-queue-management/02-CONTEXT.md` — D-09/D-10 (public status-only display + polling, the direct precedent for the tracking page), D-17 (the `DB::transaction()` + `lockForUpdate()` sequential-number pattern D-04 reuses), D-16 (`Asia/Manila` scoped narrowly to number generation, not `config('app.timezone')` — relevant to D-04's yearly boundary)
- `.planning/phases/05-pos-payments/05-CONTEXT.md` — D-15 (an unpaid balance does NOT block production, only handover — the reason D-05 rejects `rush_fee_applied`), D-16 (Release/Hand-over is a separate action from `ReadyForPickup`; D-12 and the discretion note both depend on it)
- `.planning/phases/03-job-order-intake-auto-assignment/03-CONTEXT.md` — the `ReadyForProduction` status D-08 keys board entry off, and the system-config read pattern (read `system_configurations`, never hardcode thresholds) that D-06's `default_sla_days` lookup follows
- `.planning/phases/04-artist-workflow-design-editor/04-CONTEXT.md` — the `DesignApproved` status D-08 keys Type B board entry off; the public signed-URL route precedent; the mail-failure-isolation lesson behind D-13's rejection of email

### UI Reference (non-authoritative)

- `demo/main.js` — `pdJoData` (~line 4071) is the client's production board data shape: `deadline` + `deadlineDate` + `urgency` as a pair (the precedent behind D-05/D-06), the four stage colours (~line 4335), and the `JO-2026-1008` number format D-04 matches. Per PROJECT.md §Context this is a UI/interaction reference only, never a business-rules source.
- `demo/queue-display.html` — the visual precedent already used for Phase 2's public display; the public tracking page is its sibling.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets

- `app/Http/Controllers/Public/QueueDisplayController.php` — the exact pattern the public tracking controller should copy: an explicit `get([...])` column allowlist as the PII boundary, with a comment marking it as such. Its docblock warning ("never widen it to a full model, never eager-load the visit-owner relation") applies verbatim to D-02.
- `resources/js/pages/public/QueueDisplay.vue` — working `usePoll(5000, { only: [...] })` implementation; D-14 reuses the interval and the partial-reload pattern for all three polled surfaces.
- `app/Enums/JobOrderStatus.php` — currently ends at `DesignApproved`. This phase appends the four production stages (D-09); string-backed, TitleCase keys, snake_case values.
- `app/Models/JobOrder.php` — already has `queueEntry()`, `assignedArtist()`, `designFile()`, `revisionLogs()` relations plus Phase 5's payment columns; this phase adds `productionLogs()` HasMany, the number column, and the `due_at` column.
- `database/seeders/SystemConfigurationSeeder.php` — `default_sla_days` is already seeded (group `business_rules`, described as "Global default only. A per-product SLA override is deferred until the Phase 3/5 pricing tables exist to key it against."). D-06 reads it. Note the deferred per-product override now *could* be keyed against `pricing_database`, which Phase 5 built — but that is a scope expansion, not this phase's job.
- `app/Observers/AuditObserver.php` — the new `ProductionLog` model gets automatic audit coverage via `#[ObservedBy(AuditObserver::class)]`, same as every other domain model.
- `routes/web.php` — three existing public-route precedents (queue display, signed design review, PayMongo webhook), all deliberately outside every `auth`/`role:*` group, all carrying `throttle:60,1`. The tracking route joins them.

### Established Patterns

- Form Request + Validation Concern trait pair (`app/Http/Requests/**` + `app/Concerns/*ValidationRules.php`) — the stage-advance request validates D-10's sequence rule and D-11's mandatory reason
- `Inertia::flash('toast', [...])` for mutation feedback
- System config read pattern: controllers/Form Requests read `system_configurations`, never hardcode (Phase 3 DPI thresholds, Phase 4 `max_artist_break_minutes`)
- Wayfinder-generated route/action helpers for every new route — no hardcoded URLs
- Additive migrations against `job_orders` — Phases 3, 4 and 5 all added columns this way with no conflicts

### Integration Points

- **`production_logs` does not exist** — no model, migration, factory, or controller. Greenfield, same situation Phase 5 had for `transactions`.
- **`production-staff` role group** exists at `routes/portals.php:60` with a single `Route::inertia('dashboard', 'production-staff/Dashboard')` placeholder and an empty `resources/js/pages/production-staff/Dashboard.vue` — exactly the state `frontline-staff` was in before Phase 2. This phase replaces it with the real board.
- **`released_at`** already exists on `job_orders` (migration `2026_09_04_110000`), written by Phase 5's release action — D-12's board-exit filter and D-13's alert query both read it. Do not add a second "picked up" concept.
- **`rush_fee_applied`** exists on `job_orders` from Phase 5 but is deliberately NOT the urgency source (D-05). Leave it alone.
- **No QR library installed** — `composer show --direct` has no QR package. D-03 needs one; approval gate applies.
- **No Laravel Notifications used anywhere** (`app/Notifications` does not exist; only `app/Mail/DesignReviewRequested.php`). D-13 keeps it that way.
- **Queue infrastructure exists but no queued jobs are used** — Phases 3, 4 and 5 all chose synchronous processing. Nothing in this phase needs a queue; stay synchronous.

</code_context>

<specifics>
## Specific Ideas

- The demo's four stage colours (`demo/main.js` ~line 4335: For Production green, Printing amber, Quality Check violet, Ready for Pickup blue) are a *stage* palette, distinct from PROD-01's *urgency* palette (Green = Normal, Amber = Rush). Both cannot own green/amber on the same card — the UI pass needs to resolve which axis the colour carries. The success criterion locks urgency to two colours; the stage palette is decoration.
- The demo's production card shows `paid` / `paymentState` / `balance` alongside the job. Phase 5 D-15 says payment does not block production, so if any payment hint appears on the board it is informational only and must never gate the Advance action.
- `demo/main.js` line ~1146 ("Type A — File validated & queued directly to production") is the demo's version of D-08's automatic entry — the same instinct, arrived at independently.

</specifics>

<deferred>
## Deferred Ideas

- **Per-product SLA override.** `default_sla_days`' own seeder description defers this until pricing tables exist — they now do (Phase 5 built `pricing_database`), so it is newly buildable. Not folded in: it is a configuration capability, not part of PROD-01/02/03, and D-06's stored `due_at` means adding it later changes only the computation, not the schema.
- **Configurable Green/Amber warning window.** D-07 chose a hardcoded "due today or overdue" rule. If a shop wants a day's advance warning, it becomes a new `business_rules` config key following the existing seeding pattern.
- **Reprint / waste reporting.** D-11's mandatory rework reason is the data this would read. The report itself belongs with Phase 8's reporting work, not here.
- **Customer-facing pickup notification (SMS/email when an order is ready).** Out of scope: PROD-03 alerts Frontline Staff, and TRACK-01 is the customer's self-serve path. A push channel to customers is a new capability and would need its own phase.

</deferred>

---

*Phase: 6-production-monitoring-public-tracking*
*Context gathered: 2026-09-05*
