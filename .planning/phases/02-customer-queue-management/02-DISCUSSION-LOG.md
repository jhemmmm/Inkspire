# Phase 2: Customer & Queue Management - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-01
**Phase:** 2-customer-queue-management
**Areas discussed:** Customer record & search, Queue number mechanics, Job order intake scope, Multi-job-order visit flow

---

## Customer record & search

| Option                   | Description                                          | Selected |
| ------------------------ | ---------------------------------------------------- | -------- |
| Name + Contact only      | Minimal, matches the demo's required fields          |          |
| Name + Contact + Email   | Adds email for digital receipts / AR reminders later |          |
| Full profile (+ Address) | Name, contact, email, address — most complete record | ✓        |

**User's choice:** Full profile (+ Address)

| Option           | Description                                                | Selected |
| ---------------- | ---------------------------------------------------------- | -------- |
| Unique, enforced | One contact number = one customer record                   | ✓        |
| Not unique       | Contact numbers can repeat; dedup relies on staff judgment |          |

**User's choice:** Unique, enforced

| Option               | Description                                      | Selected |
| -------------------- | ------------------------------------------------ | -------- |
| Partial match (LIKE) | Typing part of a name/number surfaces candidates | ✓        |
| Exact match only     | Must type the full name or number exactly        |          |

**User's choice:** Partial match (LIKE)

| Option                    | Description                                           | Selected |
| ------------------------- | ----------------------------------------------------- | -------- |
| Search required first     | Forces a search attempt before "Register New" appears | ✓        |
| Register always available | "Register New" always one click away                  |          |

**User's choice:** Search required first
**Notes:** All four questions resolved without follow-up debate.

---

## Queue number mechanics

| Option                                | Description                                       | Selected |
| ------------------------------------- | ------------------------------------------------- | -------- |
| Lightweight identifier                | Just a visit record — number, timestamp, no state |          |
| Stateful queue (waiting/serving/done) | Adds a status lifecycle to queue_entries          | ✓        |

**User's choice:** Stateful queue (waiting/serving/done)

| Option                                    | Description          | Selected |
| ----------------------------------------- | -------------------- | -------- |
| Daily-reset sequential (e.g. 001, 002...) | Resets to 1 each day | ✓        |
| Prefixed daily-reset (e.g. A-001)         | Adds a letter/prefix |          |
| Globally incrementing                     | Never resets         |          |

**User's choice:** Daily-reset sequential

| Option                                  | Description                                            | Selected |
| --------------------------------------- | ------------------------------------------------------ | -------- |
| Number first, job orders added after    | Queue number generated right after search/registration | ✓        |
| Generated together with first job order | One combined action, no "empty" queue entries          |          |

**User's choice:** Number first, job orders added after

**Follow-up: what states, and for whom?**

| Option                                                | Description                                                            | Selected |
| ----------------------------------------------------- | ---------------------------------------------------------------------- | -------- |
| Waiting → Serving → Done (internal only)              | Internal Frontline Staff work-tracking aid, no customer-facing display |          |
| Waiting → Serving → Done + visible on a shared screen | Same states, plus rendered on a shared/TV view                         | ✓        |

**User's choice:** Waiting → Serving → Done + visible on a shared screen
**Notes:** This choice directly revived the "lobby/TV queue-display board" item PROJECT.md had marked Out of Scope pending explicit request. Flagged as a scope-boundary decision (see below) rather than folded in silently.

**SCOPE DECISION — shared/TV queue display**

| Option                                                   | Description                                                              | Selected |
| -------------------------------------------------------- | ------------------------------------------------------------------------ | -------- |
| Defer it — note for backlog, Phase 2 stays internal-only | Internal Waiting→Serving→Done view only; display becomes a deferred idea |          |
| Add it now — expand Phase 2 to include a shared display  | Treat the shared/TV display as in-scope for Phase 2 right now            | ✓        |

**User's choice:** Add it now — expand Phase 2 to include a shared display
**Notes:** User explicitly overrode the prior Out of Scope call. Added as **QUEUE-06** to `REQUIREMENTS.md`, added as a 4th success criterion to `ROADMAP.md` §Phase 2, removed the "Separate lobby/TV queue-display board" line from `PROJECT.md` §Out of Scope, and added a new row to `PROJECT.md` §Key Decisions documenting the reversal and its rationale. All three files were edited during this discussion session, not left as a follow-up task.

**Follow-up: what does the display show?**

| Option                             | Description                                                | Selected |
| ---------------------------------- | ---------------------------------------------------------- | -------- |
| Queue number + status only         | No customer name shown — matches TRACK-02's no-PII pattern | ✓        |
| Queue number + customer first name | More personal/readable but exposes partial PII             |          |

**User's choice:** Queue number + status only

**Follow-up: public or authenticated?**

| Option                  | Description                                                      | Selected |
| ----------------------- | ---------------------------------------------------------------- | -------- |
| Public, unauthenticated | Consistent with the public QR tracking portal pattern (TRACK-01) | ✓        |
| Requires login          | Only an authenticated role could view it                         |          |

**User's choice:** Public, unauthenticated
**Notes:** A third question about refresh mechanism (polling vs. alternatives) was dropped before asking — the project's locked no-websockets constraint (`PROJECT.md` §Constraints) already settles it; polling is not a genuine choice here, so it was applied directly instead of asked.

**Follow-up: state transition trigger**

| Option                               | Description                                                     | Selected |
| ------------------------------------ | --------------------------------------------------------------- | -------- |
| Manual staff action                  | Frontline Staff clicks "Call Next" / marks Serving / marks Done | ✓        |
| Auto-transition on job order actions | States change implicitly based on job order activity            |          |

**User's choice:** Manual staff action

---

## Job order intake scope

| Option                      | Description                                                                         | Selected |
| --------------------------- | ----------------------------------------------------------------------------------- | -------- |
| Attach now, unvalidated     | File uploaded at intake, stored but not DPI/format/size checked (Phase 3 validates) | ✓        |
| No file handling in Phase 2 | File upload UI/storage built entirely in Phase 3                                    |          |

**User's choice:** Attach now, unvalidated

| Option                                         | Description                                  | Selected |
| ---------------------------------------------- | -------------------------------------------- | -------- |
| Type + product/service description (free text) | e.g. "Type A — Tarpaulin, 3x5ft"             | ✓        |
| Type only, no product detail yet               | Just "visit X, Type A/B, status placeholder" |          |

**User's choice:** Type + product/service description (free text)

| Option                                      | Description                                         | Selected |
| ------------------------------------------- | --------------------------------------------------- | -------- |
| A new "Intake"/"Pending" placeholder status | Distinct from production-stage statuses (Phase 3/6) | ✓        |
| No status field yet                         | Leave `status` off the Phase 2 schema entirely      |          |

**User's choice:** A new "Intake"/"Pending" placeholder status
**Notes:** No follow-up questions requested; moved to next area.

---

## Multi-job-order visit flow

| Option                                                | Description                                                       | Selected |
| ----------------------------------------------------- | ----------------------------------------------------------------- | -------- |
| One combined form, repeatable rows                    | Single screen: queue number + N job order rows submitted together | ✓        |
| Queue number first, then add job orders one at a time | Separate "Add Job Order" action per job order                     |          |

**User's choice:** One combined form, repeatable rows

| Option                              | Description                                               | Selected |
| ----------------------------------- | --------------------------------------------------------- | -------- |
| No hard limit; locked once Done     | Visit closes once marked Done, no more job orders addable |          |
| No hard limit; open even after Done | Job orders addable regardless of visit status             | ✓        |

**User's choice:** No hard limit; open even after Done
**Notes:** This choice sits alongside D-07 ("number first, job orders added after") — reconciled as: the queue number and the first batch of job orders are submitted together as one form (D-14), but nothing prevents staff from reopening the same still-open visit later to add more.

---

## Claude's Discretion

- Exact enum string values for queue-entry status and job order placeholder status — follow `app/Enums/UserRole.php`'s existing string-backed, TitleCase-key convention.
- `queue_entries` / `job_orders` table structure follows the already-approved 12-table ERD; no deviation was discussed.
- Exact UI layout for the repeatable job-order-row form (inline table vs. stacked cards).
- Audit trail registration for the three new models — apply the existing `AuditObserver` pattern from Phase 1, no new discussion needed.

## Deferred Ideas

None — discussion stayed within Phase 2 scope aside from the one scope-expansion (shared queue display), which was folded into the phase as QUEUE-06 rather than deferred, per explicit user decision.
