# Phase 6: Production Monitoring & Public Tracking - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-05
**Phase:** 6-production-monitoring-public-tracking
**Areas discussed:** Tracking identifier & QR, Urgency source (Green/Amber), Board entry & stage rework, Ready-for-Pickup alert

---

## Tracking identifier & QR

### Q1 — What identifier does a customer type on the public tracking page?

| Option                        | Description                                                                                                                                                                        | Selected |
| ----------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| Generated JO number           | Human-readable `job_order_number` column (JO-2026-0001) generated at intake; matches demo and manuscript wording; reuses Phase 2's lock-based sequential pattern; still enumerable | ✓        |
| JO number + last 4 of contact | Adds a second field before showing status; kills drive-by enumeration; costs an extra field and complicates the QR link                                                            |          |
| Opaque random token           | Random per-order slug in QR/receipt only; unguessable; but a customer who lost the receipt has nothing to type                                                                     |          |

**User's choice:** Generated JO number
**Notes:** Enumeration risk accepted, mitigated by the minimal payload (Q2) plus the existing `throttle:60,1` public-route convention. → D-01

### Q2 — TRACK-02 says status only. How much status does the public page actually show?

| Option                      | Description                                                                                                                                      | Selected |
| --------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ | -------- |
| Current stage only          | One line, current production stage, nothing else; strictest read of TRACK-02; mirrors the QueueDisplayController column-allowlist pattern        | ✓        |
| Stage + progress timeline   | Adds timestamped passed stages from `production_logs` as a stepper; more reassuring, slightly more exposed                                       |          |
| Stage + product description | Adds the free-text description so a customer with several orders can disambiguate; but that text is customer-authored and could contain anything |          |

**User's choice:** Current stage only
**Notes:** The description option was flagged during the question as a silent weakening of the no-PII guarantee. → D-02

### Q3 — Where does the QR code get produced, and what does scanning it do?

| Option                                | Description                                                                                                                                             | Selected |
| ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| On the receipt, deep-links to status  | QR encodes the tracking URL with the JO number in it; rendered onto the Phase 5 digital receipt; needs a QR library (new dependency, approval required) | ✓        |
| On the receipt, links to blank lookup | Opens the tracking page, customer still types; leaks nothing if a receipt photo circulates, but discards the convenience                                |          |
| No QR this phase                      | Typed lookup only, no new dependency; but PROJECT.md names QR tracking as a headline capability                                                         |          |

**User's choice:** On the receipt, deep-links to status
**Notes:** Carries an open dependency-approval gate for the QR library, same gate Phase 4's `resend/resend-php` and `tui-image-editor` went through. → D-03

### Q4 — How should the JO number be shaped and sequenced?

| Option                              | Description                                                                                                                   | Selected |
| ----------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- | -------- |
| `JO-{year}-{seq}`, resets yearly    | Matches the demo's JO-2026-1008; reuses Phase 2's `DB::transaction()` + `lockForUpdate()` pattern keyed on year; no new table | ✓        |
| `JO-{seq}`, never resets            | Simplest generator, no year-boundary edge cases; diverges from the demo and gets long over time                               |          |
| `JO-{yyyymmdd}-{seq}`, resets daily | Mirrors the queue number's daily reset; longest to speak and easiest to mistype into someone else's order                     |          |

**User's choice:** `JO-{year}-{seq}`, resets yearly → D-04

---

## Urgency source (Green/Amber)

### Q1 — What makes a job order show as Amber (Rush)?

| Option                       | Description                                                                                                                        | Selected |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- | -------- |
| Deadline vs. SLA             | Compute a due date from the seeded `default_sla_days`; works regardless of payment state; matches the demo's deadline+urgency pair | ✓        |
| `rush_fee_applied` flag      | Reuses the Phase 5 boolean, zero new state; but orders reaching production unpaid (allowed by D-15) would wrongly show Green       |          |
| Both — either turns it Amber | Catches paid-for-rush and quietly-slipping alike; costs a due-date column plus config read, and blurs what "Rush" means            |          |

**User's choice:** Deadline vs. SLA → D-05

### Q2 — When does a job order get its due date, and is it stored?

| Option                         | Description                                                                                                                                           | Selected |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| Stamped on entry to production | `due_at` column written on entering For Production as now + `default_sla_days`; sortable in SQL, survives config changes, gives Phase 8 a real column | ✓        |
| Computed on the fly            | Derive per render from `production_logs` + current config; nothing to migrate, but no SQL sorting and history shifts under config changes             |          |
| Stamped at intake              | Closest to what the customer was promised, but retrofits Phase 2/3 and burns SLA during consultation and design                                       |          |

**User's choice:** Stamped on entry to production → D-06

### Q3 — Where's the line between Green and Amber?

| Option                 | Description                                                                                                                 | Selected |
| ---------------------- | --------------------------------------------------------------------------------------------------------------------------- | -------- |
| Configurable threshold | New `business_rules` config key ("Amber when due within N hours"), tunable without a deploy, which is what CONFIG-01 is for |          |
| Due today or overdue   | Hardcoded; dead simple, nothing to seed or explain, reads naturally to staff; not tunable without a code change             | ✓        |
| Overdue only           | Amber always means a real problem; purely reactive, no advance warning                                                      |          |

**User's choice:** Due today or overdue
**Notes:** A conscious departure from the phase's config-driven neighbours — `default_sla_days` itself stays configurable. Adding a warning-window key later follows the existing seeding pattern. → D-07

### Q4 — How is the board organised?

| Option                      | Description                                                                                                                        | Selected |
| --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- | -------- |
| Kanban columns by stage     | Four columns, advancing is a visible rightward move making the sequential rule self-evident; maps to the demo's four stage colours |          |
| Single list, urgency-sorted | Flat table, Amber first, denser and easier on small screens; reuses existing table patterns                                        |          |
| You decide                  | Leave to the UI pass — `UI hint: yes` in ROADMAP.md; business rules hold under either layout                                       | ✓        |

**User's choice:** You decide → Claude's Discretion

---

## Board entry & stage rework

### Q1 — How does a job order get onto the production board?

| Option                                   | Description                                                                                                                                                       | Selected |
| ---------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| Automatic on ready status                | Appears the moment Type A hits `ReadyForProduction` / Type B hits `DesignApproved`; nothing forgotten at the handoff; keeps the phase dependent only on Phase 3/4 | ✓        |
| Manual accept into production            | Explicit ownership moment with a real actor on the first log row; costs a click and lets work sit unnoticed                                                       |          |
| Automatic, with the log written on entry | Auto-appears but system-writes the status and first log row                                                                                                       |          |

**User's choice:** Automatic on ready status
**Notes:** The third option's concern (a real first log row) was raised again in Q2 and resolved there. → D-08

### Q2 — Is "For Production" its own status, or just how ready orders are displayed?

| Option                            | Description                                                                                                                                                                                   | Selected |
| --------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| Own status, written automatically | Adds `ForProduction` to `JobOrderStatus`, transitioned automatically (actor = system, per Phase 3's auto-assignment); uniform sequence validation; guarantees the first `production_logs` row | ✓        |
| Display-only label                | No new status; first column shows `ReadyForProduction`/`DesignApproved`; fewer statuses but two underlying values, a special case in sequence validation, and no log row for the stage        |          |

**User's choice:** Own status, written automatically → D-09

### Q3 — Can an order ever move backward?

| Option                          | Description                                                                                                                                         | Selected |
| ------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| Back one stage, reason required | Quality Check fails → back to Printing, mandatory reason on the log row; matches real print-shop practice and enables a future reprint/waste report | ✓        |
| Strictly forward only           | Most literal reading of PROD-02; a failed check would be handled outside the system or by cancel-and-recreate, losing history                       |          |
| Back any number of stages       | Most flexible, covers a re-do of the file itself; weakest guarantee, allows large unexplained jumps                                                 |          |

**User's choice:** Back one stage, reason required → D-10, D-11

### Q4 — What takes an order off the board once it's Ready for Pickup?

| Option                       | Description                                                                                                                                            | Selected |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ | -------- |
| Phase 5's Release action     | Stays visible until `released_at` is stamped by the existing Release/Hand-over action; reuses what's built; production keeps sight of the pickup shelf | ✓        |
| Leaves at Ready for Pickup   | Board stays strictly about work in progress; nobody in production can see the pickup shelf                                                             |          |
| Stays until released, dimmed | Same rule as the first, de-emphasised visually — a UI refinement rather than a different rule                                                          |          |

**User's choice:** Phase 5's Release action → D-12

---

## Ready-for-Pickup alert

### Q1 — How does the alert reach Frontline Staff?

| Option                   | Description                                                                                                                                                   | Selected |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| Derived query, polled    | Poll for `ReadyForPickup` + null `released_at`; nothing stored, no new table, no read-state to drift, self-corrects on a D-11 rework                          | ✓        |
| Stored notification rows | Per-user read state and announcement history; costs a table beyond the approved 12-table ERD, needing the same justification `system_configurations` required |          |
| Email to Frontline       | Reaches staff away from the screen via the Phase 4 Resend transport; push-only, and Phase 4 already needed mail-failure isolation                             |          |

**User's choice:** Derived query, polled → D-13

### Q2 — Is the alert list also where Frontline triggers the Release/Hand-over action?

| Option                 | Description                                                                                                 | Selected |
| ---------------------- | ----------------------------------------------------------------------------------------------------------- | -------- |
| Yes — one working list | Each row carries a Release button running the existing Phase 5 gate; one screen, closes the loop            |          |
| No — alert only        | Strictly PROD-03's wording, touches no Phase 5 code; costs a navigation step with a customer at the counter |          |
| You decide             | Placement, not a business rule; the gate's behaviour is locked by Phase 5 D-16 either way                   | ✓        |

**User's choice:** You decide
**Notes:** Recorded with a hard constraint — if planning wires it there, it MUST call the existing gate, never re-implement the payment check. → Claude's Discretion

### Q3 — How fresh does the polled data need to be?

| Option                            | Description                                                                                            | Selected |
| --------------------------------- | ------------------------------------------------------------------------------------------------------ | -------- |
| Match the queue display (5s)      | Reuse the proven 5000ms `usePoll` interval everywhere; one consistent number                           | ✓        |
| Slower for staff screens (15–30s) | Stage changes happen minutes apart; fewer requests from screens open all shift; two intervals to track |          |
| You decide                        | Leave the number to planning                                                                           |          |

**User's choice:** Match the queue display (5s) → D-14

---

## Claude's Discretion

- Board layout — kanban columns vs. urgency-sorted list; left to `/gsd-ui-phase` (`UI hint: yes`)
- Whether the Frontline ready-for-pickup list carries the Release/Hand-over button (must call the existing Phase 5 gate if it does)
- Backfilling job order numbers onto the existing Phase 2–5 `job_orders` rows — backfill strongly preferred, mechanism is a planning call
- Exact column names and new `JobOrderStatus` enum casing
- `production_logs` schema, following established model conventions
- Which QR library — subject to the D-03 dependency-approval gate

## Deferred Ideas

- **Per-product SLA override** — newly buildable now that `pricing_database` exists, but it's a configuration capability, not PROD-01/02/03
- **Configurable Green/Amber warning window** — D-07 chose a hardcoded rule; a `business_rules` key can be added later
- **Reprint / waste reporting** — D-11's rework reason is the data; the report belongs with Phase 8
- **Customer-facing pickup notification (SMS/email)** — PROD-03 alerts Frontline; a customer push channel is a new capability needing its own phase
