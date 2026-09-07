# Phase 7: Accounts Receivable - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-07
**Phase:** 7-accounts-receivable
**Areas discussed:** AR scope & aging clock, Reminder delivery & trigger, Collection status & letter, Write-off & settlement

---

## AR Scope & Aging Clock

### What lands in the AR aging report?

| Option | Description | Selected |
|--------|-------------|----------|
| Active On-Credit only | Only `accounts_receivable` rows at status Active. Matches ROADMAP's Phase 7 goal verbatim and Phase 5 D-09. A partially-paid walk-in shows on the job order, not in AR. | ✓ |
| Any outstanding balance | Every job order with money owed enters AR, partial payments included — what the demo does. Would need rework of the unique index and requested_by/approved_by shape. | |
| On-Credit + released unpaid | Active credit plus any order handed over carrying a balance. Phase 5 D-16's release gate makes the second set empty in practice. | |

**User's choice:** Active On-Credit only
**Notes:** → D-01.

### What day is "day 0" for aging?

| Option | Description | Selected |
|--------|-------------|----------|
| Due date from a credit term | Store `due_at` at approval = `approved_at` + configurable `credit_term_days` (default 30). Age counts days past due. Matches the demo's invoiceDate/dueDate pair and Phase 6 D-06's stamp-the-date pattern. | ✓ |
| Age from approval date | No due date, no term — days counted straight from `approved_at`. No grace period; brackets stop meaning "overdue". | |
| Due date, Cashier sets it per request | Cashier picks a due date per credit request. Contradicts Phase 5 D-08's open, term-less eligibility and would retrofit the Phase 5 form. | |

**User's choice:** Due date from a credit term
**Notes:** → D-02.

### How do the five brackets map to days past due?

| Option | Description | Selected |
|--------|-------------|----------|
| Current + 4 overdue bands | Current = not yet due; then 1–15, 16–30, 31–60, 61–90, 90+ days past due. Maps 1:1 onto AR-02's four reminder triggers. | ✓ |
| Age from approval, no grace | 0–14, 15–29, 30–59, 60–89, 90+ from `approved_at`. Contradicts the due-date decision above. | |
| Hardcode the boundaries | Same bands, numbers in code rather than `system_configurations` — Phase 6 D-07's call for the Green/Amber line. | |

**User's choice:** Current + 4 overdue bands
**Notes:** → D-03. The hardcode-vs-config aspect was folded in: boundaries live in code, since the bracket list is written into AR-01's own requirement text.

### Who sees the AR list, and how much of it?

| Option | Description | Selected |
|--------|-------------|----------|
| Accounting full list, Owner approval queue | Accounting gets the working aging list; Owner gets a write-off approval queue matching the existing `owner/CreditRequests.vue`. Owner's reporting view arrives in Phase 8 RPT-01. | ✓ |
| Both get the full list | Owner sees the same aging table read-only. A second surface for the same data; AR-01 names Accounting Staff. | |
| Accounting only | No Owner list at all. But the 15-day reminder already goes to Owner — they'd be told about an entry with nowhere to look. | |

**User's choice:** Accounting full list, Owner approval queue
**Notes:** → D-04.

---

## Reminder Delivery & Trigger

### How does an escalating reminder reach Accounting and Owner?

| Option | Description | Selected |
|--------|-------------|----------|
| Email via resend | `resend/resend-php` is installed; `app/Mail/DesignReviewRequested.php` proves the pattern. Must wrap in Phase 4's (04-13) mail-failure isolation. | ✓ |
| In-app polled list only | Phase 6 D-13's derived-query pattern. Zero new infrastructure, but nothing is "sent" and the Owner hears nothing. | |
| Both — email plus in-app | Fullest reading of AR-02, with Phase 6's self-correcting display as backup. More surface to build and test. | |

**User's choice:** Email via resend
**Notes:** → D-05. Deliberate divergence from Phase 6 D-13, which rejected email for the pickup alert — correct there (a live queue someone is watching), wrong here (escalation must reach someone who is not looking).

### What fires the bracket crossing, and how do we avoid daily re-sends?

| Option | Description | Selected |
|--------|-------------|----------|
| Daily scheduled command + last-sent column | Command finds entries past their recorded bracket, sends, stamps the new bracket back. One new column, no new table, idempotent. First scheduled work in the project. | ✓ |
| Scheduled command + a reminder log table | Same command, plus an `ar_reminders` send-history table. A 14th table needing the same justification `system_configurations` got, when `audit_trail` already records the update. | |
| Fire on read | Recompute and send on page load. No cron dependency, but outbound mail as a GET side effect — the coupling 04-13 existed to break. | |

**User's choice:** Daily scheduled command + last-sent column
**Notes:** → D-07.

### Who receives each escalation level, and does the customer get one?

| Option | Description | Selected |
|--------|-------------|----------|
| Internal only, Accounting + Owner at every bracket | All four go to both roles; only tone and subject escalate. Customer contact is AR-03's human-triggered letter. | ✓ |
| Escalate the audience | 15-day to Accounting only, Owner joins at 30. Contradicts AR-02's explicit "15-day → Accounting+Owner". | |
| Internal + auto-email the customer | Strongest collection pressure, but no customer email channel exists and Phase 6 D-13 deferred customer notifications as a new capability. | |

**User's choice:** Internal only, Accounting + Owner at every bracket
**Notes:** → D-06.

### When do reminders stop for an entry?

| Option | Description | Selected |
|--------|-------------|----------|
| Stop only when the entry closes | Zero balance or approved write-off. Collection-status changes do not suppress; a pending write-off request keeps reminding. | ✓ |
| Also stop at a manual snooze/hold | Realistic for real collections work, but new state with its own expiry rules and nothing in AR-01–04 asks for it. | |
| Stop at 90+ after the final notice | Less inbox noise, but a 90+ entry nobody wrote off silently drops out of view. | |

**User's choice:** Stop only when the entry closes
**Notes:** → D-08.

---

## Collection Status & Letter

### Where does collection status live?

| Option | Description | Selected |
|--------|-------------|----------|
| Separate collection_status column | `AccountsReceivableStatus` is the credit lifecycle; collection status is how chasing is going. Two orthogonal axes — the same reasoning as the `job_orders.status`/`payment_status` split. | ✓ |
| Extend the existing enum | One column, one enum. But "is this approved?" and "has a letter been sent?" share a slot, and every Phase 5 `status = Active` query needs revisiting. | |

**User's choice:** Separate collection_status column
**Notes:** → D-09.

### What statuses exist, and can Accounting move freely?

| Option | Description | Selected |
|--------|-------------|----------|
| Demo's six, freely settable | Pending, Follow-up, Warning Sent, Collections, Paid, Written Off. First four settable in any direction; Paid and Written Off system-set. Audited via the existing observer. | ✓ |
| Four working statuses only | Drop Paid and Written Off as derivable. Avoids fields that can disagree, but the demo's list view badges them anyway. | |
| Strict forward-only ladder | Mirrors Phase 6 D-10's no-skip rule. But collections isn't a production line, and even Phase 6 needed D-11's backward escape hatch. | |

**User's choice:** Demo's six, freely settable
**Notes:** → D-10.

### How is the printable collection letter produced?

| Option | Description | Selected |
|--------|-------------|----------|
| Inertia print page, like the receipt | The `cashier/Receipt.vue` pattern from POS-06. No new dependency; AR-03 says "printable", not "PDF". Phase 8 RPT-05 solves export once for every report. | ✓ |
| Server-generated PDF | A real attachable file, but a new dependency behind the approval gate, and the same choice made a phase early. | |
| Print page now, PDF in Phase 8 | Same immediate result, but records the follow-up explicitly. | |

**User's choice:** Inertia print page, like the receipt
**Notes:** → D-11. The Phase 8 follow-up was recorded as a deferred idea regardless.

### One letter template, or bracket-driven tone?

| Option | Description | Selected |
|--------|-------------|----------|
| Tone follows the bracket | One layout, body text picked by current bracket — polite at 1–15 through final notice at 90+. Mirrors AR-02's escalation customer-side, no extra state. | ✓ |
| One neutral template | Simplest, but a 90-day debtor gets the same letter as a 10-day one. | |
| Editable before printing | Most flexible, but free-text-then-print is document authoring — more than AR-03 asks for. | |

**User's choice:** Tone follows the bracket
**Notes:** → D-12.

---

## Write-Off & Settlement

### Does Accounting request, or does Owner write off directly?

| Option | Description | Selected |
|--------|-------------|----------|
| Accounting requests, Owner approves | Mandatory reason, entry pends until Owner acts. The demo's flow, and structurally identical to Phase 5 D-06's async credit request on this same table. | ✓ |
| Owner writes off directly | Literally what AR-04 says, one less state. But Owner must find the entry, and the reason lives with whoever chased. | |
| Request, plus Owner can initiate | Covers every situation, but two entry points into one terminal state means two sets of preconditions and tests. | |

**User's choice:** Accounting requests, Owner approves
**Notes:** → D-13.

### How does an AR balance get paid down?

| Option | Description | Selected |
|--------|-------------|----------|
| Through the existing Cashier POS flow | Phase 5's `PaymentController` records the transaction; AR follows. One money-entry path, so Phase 8's reports can't disagree with AR. | ✓ |
| Accounting records the payment | Natural for collections, but a second payment write path — exactly what `transactions.job_order_id` NOT NULL and "no standalone sales" exist to prevent. | |
| Both | Most realistic for a small shop, but two write paths into one balance. | |

**User's choice:** Through the existing Cashier POS flow
**Notes:** → D-15.

### What is `accounts_receivable.balance` once payments land?

| Option | Description | Selected |
|--------|-------------|----------|
| Derived, never stored | Outstanding = job order total minus completed transactions, the way `ReceiptController` already computes it. Cannot drift. Keep the existing column as the original approved credit amount. | ✓ |
| Stored and synced on payment | Fast to query, but a denormalised copy — one missed path (reconciliation, cancellation refund) and AR disagrees with the receipt. | |
| Stored, recomputed by the daily command | Self-healing without touching the payment path, but wrong for up to a day: "paid this morning, still chased this afternoon". | |

**User's choice:** Derived, never stored
**Notes:** → D-16.

### What happens to the job order when a write-off is approved?

| Option | Description | Selected |
|--------|-------------|----------|
| AR closes; job order payment_status becomes a written-off state | Stops reading as "owes money" on the release gate, Cashier dashboard, and Phase 8 reports. One new `PaymentStatus` case. | ✓ |
| Close AR only, leave the job order alone | Smallest diff, but every other surface still shows an outstanding balance and the write-off is invisible outside AR. | |
| Also zero the job order's totals | Books balance automatically, but destroys the record of what was owed — a write-off is a loss to report, not a shrunken sale. | |

**User's choice:** AR closes; job order payment_status becomes a written-off state
**Notes:** → D-14. Flagged for planning: 06-07 was a dedicated fix plan for exactly this class of enum-consumer regression, so every `payment_status` consumer must be checked.

---

## Claude's Discretion

No question was answered with "you decide" — all sixteen decisions were made explicitly. The discretion items recorded in CONTEXT.md are ones Claude identified as implementation-level rather than business-rule calls:

- Exact column names and enum casing (`due_at`, `last_reminder_bracket`, `collection_status`, the new `PaymentStatus` case)
- Whether the aging bracket is a computed accessor, query scope, or value object
- The scheduled command's name, run time, and registration site (`routes/console.php` vs `bootstrap/app.php`'s `withSchedule`)
- Backfilling `due_at` for the AR entries Phase 5 already approved (reuse WR-10's atomic, re-runnable backfill shape)
- One `Mailable` with bracket-driven copy vs four classes
- Whether the AR entry detail page renders an activity log (must read `audit_trail`, never a parallel table)
- Accounting portal aging-list layout — `/gsd-ui-phase 7` can settle it
- What a rejected write-off request does to the entry (Phase 5 D-07 precedent: stays flagged, no auto-fallback)

## Deferred Ideas

- **Payment adjustments** (Credit Memo / Write-down / Overpayment Correction) — the demo has a full modal for this; contradicts D-15's single-money-path rule and D-16's derived balance. Belongs with refunds in its own phase.
- **Manual reminder snooze / payment-plan hold** — rejected in D-08.
- **Configurable aging bracket boundaries** — D-03 hardcodes them; the `business_rules` seeding pattern is the escape hatch.
- **Emailing or archiving the collection letter** — needs the PDF library Phase 8 RPT-05 will pick.
- **Customer-facing automated dunning** — rejected in D-06; same boundary Phase 6 D-13 drew, and NOTF-01 is already deferred to v2.
- **AR aging on the Owner dashboard** — Phase 8's RPT-01 is where this surfaces for the Owner.
