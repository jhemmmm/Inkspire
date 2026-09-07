# Phase 7: Accounts Receivable - Context

**Gathered:** 2026-09-07
**Status:** Ready for planning

<domain>
## Phase Boundary

An `accounts_receivable` row at status `Active` — an Owner-approved On-Credit balance created by Phase 5 — becomes a tracked receivable: it acquires a due date, falls into an aging bracket, triggers escalating internal reminder emails as it ages, carries a collection status Accounting works by hand, produces a printable collection letter, and closes either by payment through the existing POS flow or by an Owner-approved write-off. Covers AR-01 through AR-04.

The table already exists (`job_order_id` unique, `balance`, `status`, `requested_by`, `approved_by`, `approved_at`) — Phase 5 D-09 deliberately wrote it minimal and parked due-date/aging-term logic here. This phase adds columns to it; it does not create it.

**Explicitly not this phase:** creating AR entries (Phase 5's On-Credit request + Owner approval flow already does that, and stays untouched), recording payments (Phase 5's `PaymentController` is the single money-entry path — this phase reads transactions, never writes them), PDF/Excel export (Phase 8 RPT-05), and any customer-facing notification channel (Phase 6 D-13 already ruled that a new capability).

</domain>

<decisions>
## Implementation Decisions

### AR Entry Scope

- **D-01:** Only `accounts_receivable` rows at `AccountsReceivableStatus::Active` appear in the aging report — Owner-approved On-Credit balances and nothing else. Matches ROADMAP.md's Phase 7 goal verbatim ("An On-Credit balance is tracked from creation through aging…") and Phase 5 D-09. A partially-paid walk-in with an outstanding balance is tracked on the job order (`payment_status`), **not** in AR — they were never granted credit, they simply have not finished paying. Deliberately rejected: the demo's model, where every partial payment becomes an AR row (`entryType: 'Partial Payment'` on all six of its fixtures) — that would create AR entries with no Owner approval, breaking the `requested_by`/`approved_by` shape and the unique index on `job_order_id`.

### Aging Clock & Brackets

- **D-02:** A **due date is stamped on the AR row at approval** — a `due_at`-style column written when Owner approves, computed as `approved_at` plus a new configurable `credit_term_days` system configuration key (`business_rules` group, default 30). Aging counts **days past due**, not days since approval, so "Current" genuinely means "not yet due" and the 15/30/60/90 brackets read as days overdue. This follows Phase 6 D-06's pattern exactly: stamp the derived date at the triggering event so a later config change never retroactively rewrites history and the list can sort/filter in SQL. Rejected: aging straight from `approved_at` (no grace period, brackets stop meaning "overdue"), and a Cashier-entered per-request due date (Phase 5 D-08 deliberately made credit eligibility open with no per-customer terms; this would retrofit the Phase 5 credit-request form).
- **D-03:** Five brackets: **Current** (`due_at` in the future), then **1–15**, **16–30**, **31–60**, **61–90**, and **90+** days past due. Maps 1:1 onto AR-02's four reminder triggers — each reminder fires as an entry enters the next band. The boundary numbers live in code, not in `system_configurations`: the bracket list is written into AR-01's own requirement text, so there is nothing asking to tune it. This is the same call Phase 6 D-07 made for the Green/Amber line, and the same escape hatch applies — if a shop later wants different bands, they become `business_rules` keys following the established seeding pattern.

### Audience & Surfaces

- **D-04:** **Accounting Staff get the full working aging list** — filter by bracket, drill into an entry, update collection status, print letters, request write-offs. **Owner gets a write-off approval queue only**, structurally identical to the existing `resources/js/pages/owner/CreditRequests.vue` that already serves Phase 5's credit approvals. Owner's broader financial view arrives in Phase 8 (RPT-01), so no second aging table to build and keep in sync now. Note that Owner still receives every reminder email (D-06), and the approval queue is where they act on the 90+ ones.

### Reminder Delivery (AR-02)

- **D-05:** Reminders are **emails sent via the installed `resend/resend-php` transport**, following `app/Mail/DesignReviewRequested.php`. AR-02 says "sends notifications", and an escalation only escalates if it pushes — an in-app list nobody opens is not an escalation, and the Owner in particular does not sit in the portal. **The send MUST be wrapped in Phase 4's mail-failure isolation** (plan 04-13, `app/Actions/JobOrder/RecordDesignRevision.php`) so a Resend outage can never break the bracket transition or the scheduled run. Rejected: Phase 6 D-13's derived-polled-list pattern alone — correct there because a pickup alert is a live queue a Frontline person is already staring at, wrong here because AR escalation is precisely about reaching someone who is *not* looking.
- **D-06:** All four levels go to **Accounting Staff and Owner, at every bracket** — only the subject line and tone escalate (15 = notice, 30 = urgent, 60 = escalation, 90+ = final, flagged for write-off). AR-02 names exactly these two roles. **The customer is never auto-emailed.** The customer-facing artifact is AR-03's collection letter — deliberately human-triggered and printed, so the shop controls what a customer receives. Rejected: escalating the *audience* (15 to Accounting, Owner joining at 30) because AR-02 explicitly says "15-day → Accounting+Owner"; and auto-dunning the customer, which is a new capability with no existing channel (Phase 6 D-13 already deferred customer notifications).

### Reminder Trigger & Idempotency

- **D-07:** A **daily scheduled Artisan command** finds every Active entry whose current bracket is later than the bracket recorded on the row, sends the matching reminder, and stamps the new bracket back. State lives in **one new column on `accounts_receivable`** (e.g. `last_reminder_bracket`) — **no new table**, so the approved 12-table ERD stands (`accounts_receivable` is one of the twelve; only `system_configurations` was ever justified beyond it). The command is idempotent: running it twice in a day sends nothing the second time. This is the **first scheduled work in the project** — `routes/console.php` is bare and `app/Console/Commands` is empty. Laravel Cloud provides the managed scheduler (PROJECT.md §Constraints names it as the hosting target). Rejected: a dedicated `ar_reminders` log table (a 14th table needing the same explicit justification `system_configurations` required, when `audit_trail` already records the row update), and firing on page load (outbound mail as a side effect of a GET, nothing fires over a quiet weekend, and it is exactly the coupling 04-13 existed to break).
- **D-08:** Reminders stop **only when the entry closes** — balance reaches zero, or a write-off is approved. Collection-status changes do **not** suppress them: Accounting marking "Follow-up" records what a human did, it is not an instruction to stop chasing. A submitted-but-unapproved write-off request also keeps reminding, which is the correct pressure on the Owner to act. Rejected: a manual snooze/hold (new capability with its own expiry rules, not asked for by AR-01–04), and going quiet after the 90+ final notice (a 90+ entry nobody wrote off is the one that most needs to stay visible).

### Collection Status (AR-03)

- **D-09:** Collection status lives in its **own column, separate from `AccountsReceivableStatus`**. The existing enum (`PendingApproval` / `Active` / `Rejected`) is the *credit lifecycle* — "did Owner approve this?". Collection status is *how chasing is going*. Two orthogonal axes, two columns — the identical reasoning PROJECT.md §Context already applied when splitting `job_orders.status` from `payment_status`. Merging them would recreate the flat multi-value list that split was rejecting, and every Phase 5 query filtering `status = Active` would need revisiting.
- **D-10:** Six values, matching the demo's set (which the client already recognises): **Pending, Follow-up, Warning Sent, Collections, Paid, Written Off**. Accounting sets any of the first four **freely, in any direction** — it is a record of human activity, not a machine state, and a customer who promises to pay legitimately moves back from Collections to Follow-up. **Paid and Written Off are system-set**, never hand-picked: Paid when the derived balance clears, Written Off when Owner approves (D-13). Every change is audited automatically through the model's existing `#[ObservedBy(AuditObserver::class)]`. Rejected: a strict forward-only ladder (collections is not a production line; even Phase 6's stage sequence needed D-11's backward escape hatch).

### Collection Letter (AR-03)

- **D-11:** The letter is a **dedicated Inertia page styled for print**, opened and sent to the browser's print dialog — the pattern `app/Http/Controllers/Cashier/ReceiptController.php` + `resources/js/pages/cashier/Receipt.vue` already established for POS-06. **No new dependency.** AR-03 asks for "printable", not "PDF"; Phase 8's RPT-05 is where PDF/Excel export gets solved once for every report, and picking a PDF library here would mean picking one twice.
- **D-12:** **One layout, body text selected by the entry's current aging bracket** at print time — polite reminder at 1–15, firmer at 16–30, formal demand at 31–60, final notice at 61–90 and 90+. Mirrors AR-02's escalation on the customer-facing side and switches on data the reminder emails already compute. **No stored letter state** — derive the copy from the bracket, do not persist a letter body. Rejected: one neutral template (a 90-day debtor receiving the same letter as a 10-day one undercuts the point of printing it), and an editable textarea before printing (document authoring is more capability than AR-03 asks for).

### Write-Off (AR-04)

- **D-13:** **Accounting requests a write-off with a mandatory reason; Owner approves or rejects** from their queue. The demo's flow, and structurally identical to Phase 5 D-06's async credit request — requester and approver are already distinct roles on this exact table (`requested_by` / `approved_by` / `approved_at`). AR-04's "Owner can approve" implies something was submitted for approval. Rejected: Owner writing off directly (they would have to find the entry themselves, and the reason lives with whoever did the chasing), and supporting both paths (two entry points into one terminal state means two sets of preconditions and tests).
- **D-14:** On approval, the AR entry's `collection_status` becomes **Written Off** (reminders stop per D-08) **and the job order gets a matching terminal `payment_status`**, so it stops reading as "owes money" on every other surface — the Phase 5 release gate, the Cashier dashboard, and Phase 8's reports. Adding one case to `app/Enums/PaymentStatus.php` follows the existing string-backed TitleCase-key convention. **The job order's `total_amount` and transactions are NOT altered** — a write-off is a loss to report, not a sale that shrank, and Phase 8's profit reporting needs the original figure intact.

### Settlement & Balance

- **D-15:** An AR balance is paid down **through the existing Cashier POS flow** — the customer pays at the counter, Phase 5's `PaymentController` records a transaction against the job order, and the AR entry follows automatically. `transactions` stays the single source of truth for money entering the system, so Phase 8's sales reports can never disagree with AR. Rejected: an Accounting-side payment recorder (a second write path, when `transactions.job_order_id` NOT NULL and PROJECT.md's "no standalone POS sales" constraint exist precisely to keep that surface single).
- **D-16:** **Outstanding balance is derived, never stored** — job order total minus completed transactions, computed the way `ReceiptController::show` already does it (`$completedTransactions = $jobOrder->transactions->where('status', TransactionStatus::Completed)`). It cannot drift, needs no sync step, and a Cashier payment shrinks the AR entry with zero AR-side code. **The existing `accounts_receivable.balance` column is retained as the original approved credit amount** — what was extended at approval, which the collection letter wants anyway. Do not repurpose it as a running balance. Rejected: decrementing the stored column on payment (one missed path — a reconciliation confirming a webhook, a cancellation refund — and AR silently disagrees with the receipt), and refreshing it in the daily command (an entry can be wrong for up to a day, and "paid this morning, still chased this afternoon" is the failure a customer notices).

### Claude's Discretion

- **Exact column names and enum casing** — `due_at` / `credit_due_at`, `last_reminder_bracket` / `last_reminder_sent_bracket`, `collection_status`, the new `PaymentStatus` write-off case, and the aging-bracket enum itself. Follow the string-backed TitleCase-key convention in `app/Enums/JobOrderStatus.php` and `app/Enums/PaymentStatus.php`.
- **Whether the aging bracket is a computed accessor, a query scope, or a small value object.** D-02's stored `due_at` makes any of these cheap; the business rule (D-03's five bands, counted from `due_at`) is what is locked, not the mechanism.
- **The scheduled command's name and run time.** `routes/console.php` currently only has the stock `inspire` stub — either `Schedule::command(...)` in `routes/console.php` or `bootstrap/app.php`'s `withSchedule` is acceptable, whichever matches Laravel 13's current idiom. Verify the choice against installed-version docs rather than assuming.
- **Backfilling `due_at` for AR entries Phase 5 already approved.** Phase 5 created rows with no due date. Backfill (`approved_at` + `credit_term_days`) is strongly preferred so no existing entry is unaged, but the mechanism is an implementation call — note that Phase 6's 06-05 backfill was later hardened by WR-10 into an atomic, re-runnable form; reuse that shape rather than rediscovering it.
- **Mail class structure** — one `Mailable` with a bracket-driven subject/view versus four classes. Either is fine; `app/Mail/DesignReviewRequested.php` is the only existing precedent and it is a single class.
- **Whether the AR entry detail page shows an activity log.** The demo carries a per-entry `log` array, and the data already exists in `audit_trail` (every status change is observed). Rendering a filtered audit view is a UI convenience, not a locked requirement — and it must read `audit_trail`, never write a parallel log.
- **How the Accounting portal's aging list is laid out** — bracket-column summary cards over a filterable table, versus a plain filtered table. This phase has `UI hint: yes` in ROADMAP.md, so `/gsd-ui-phase 7` can settle it against `demo/main.js`.
- **What a rejected write-off request does to the entry.** Following Phase 5 D-07's precedent (a rejected credit request stays flagged, no automatic fallback), the entry should return to Active and keep aging — but the exact flagging is an implementation call.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements & Roadmap

- `.planning/REQUIREMENTS.md` §Accounts Receivable — AR-01 through AR-04 full requirement text. AR-02's exact wording ("15-day → Accounting+Owner, 30-day → urgent, 60-day → escalation, 90+ → final escalation with write-off option") is what D-03/D-06 implement literally.
- `.planning/REQUIREMENTS.md` §Out of Scope — Laravel Echo/Reverb websockets locked out; "Loyalty/marketing features" locked out (relevant to any customer-communication instinct).
- `.planning/ROADMAP.md` §Phase 7 — goal ("An On-Credit balance is tracked…"), the four success criteria, `Depends on: Phase 5`, `UI hint: yes`. The goal's wording is what grounds D-01's scope.
- `.planning/ROADMAP.md` §Phase 8 — depends on Phase 7; RPT-01 (Owner financial reports) grounds D-04's decision not to build a second Owner list, and RPT-05 (PDF/Excel export) grounds D-11's no-PDF-yet call.

### Project-Level Context

- `.planning/PROJECT.md` §Context — the approved 12-table ERD (`accounts_receivable` is one of the twelve; D-07 stays inside it by adding a column, not a table) and the `system_configurations` justification bar any 13th+ table must clear.
- `.planning/PROJECT.md` §Constraints — `transactions.job_order_id` NOT NULL / no standalone POS sales (grounds D-15); `audit_trail` structurally append-only (grounds D-10's audit note); dependency changes require approval (grounds D-11's no-new-PDF-library call); Laravel Cloud as the hosting target with a managed scheduler (grounds D-07).
- `.planning/PROJECT.md` §Key Decisions — "Keep the manuscript's On-Credit approval gate and automated AR reminder pipeline, even though the client's UI demo shows simpler behavior (no on-credit concept, manual-only AR follow-up)" is the project-level decision this entire phase implements. The demo is a UI reference, never a business-rules source.

### Prior Phase Context

- `.planning/phases/05-pos-payments/05-CONTEXT.md` — **D-09** (AR posted with balance only, no due date/term: "Due-date/aging-term logic is entirely Phase 7's responsibility") is the direct handoff D-02 picks up. Also **D-06/D-07** (async Owner approval, rejection stays flagged with no auto-fallback — the pattern D-13 reuses), **D-08** (open credit eligibility, no per-customer terms — why D-02 rejects a Cashier-set due date), **D-15/D-16** (release gate allows handover on active credit; a written-off order must not break it — see D-14).
- `.planning/phases/06-production-monitoring-public-tracking/06-CONTEXT.md` — **D-06** (stamp the derived date at the triggering event, the pattern D-02 copies), **D-07** (hardcode a threshold when nothing asks to tune it, the pattern D-03 copies), **D-13** (derived polled alerts, no notifications table, email rejected for status transitions — deliberately NOT followed for AR reminders; see D-05's rationale for the divergence), and the deferred "customer-facing pickup notification" item that grounds D-06's no-customer-email call.
- `.planning/phases/04-artist-workflow-design-editor/04-CONTEXT.md` and plan `04-13` — mail-transport failure isolation. D-05 requires this pattern; `app/Actions/JobOrder/RecordDesignRevision.php:47` is the live example.
- `.planning/phases/01-foundation-rbac-auth-hardening-audit-trail/01-CONTEXT.md` — audit observer registration, Form Request + Validation Concern trait pairing, `Inertia::flash('toast', …)` mutation-feedback convention.

### Implementation Rules

- `.ai/rules/index.md` — maps file globs to rule files. Read every rule file whose globs cover `app/Models/**`, `app/Http/Controllers/**`, `app/Console/Commands/**`, `app/Mail/**`, `database/migrations/**`, `resources/js/pages/**`, and `tests/**` before writing code, and `grep -rin` the directory for `receivable`, `schedule`, `mail` to catch what a path match alone misses.
- `CLAUDE.md` — PHP conventions (curly braces always, constructor property promotion, explicit return types, PHPDoc array shapes, TitleCase enum keys), Pint after every PHP change, Larastan level 7, test-every-change enforcement.

### UI Reference (non-authoritative)

- `demo/main.js` lines ~83–255 (`acArData`) — the client's AR entry shape: `agingBracket`, `collectionStatus`, `invoiceDate` + `dueDate` as a pair (the precedent behind D-02), `daysOut`, and a per-entry `log` array. Its `entryType: 'Partial Payment'` on every row is the model D-01 explicitly rejects.
- `demo/main.js` lines ~256–300 (`acAgingBadge`, `acStatusBadge`) — the bracket and collection-status badge palettes.
- `demo/main.js` lines ~432–510 (`acUpdateCollectionStatus`, `acRequestWriteOff`) — the request-with-reason → "Pending Owner/Admin approval" write-off flow D-13 follows, and the free-set collection-status dropdown D-10 follows.
- `demo/main.js` lines ~512–570 (`acConfirmAdjustment`) — the demo's Credit Memo / Write-down / Overpayment Correction adjustment modal. **Not built** — see Deferred Ideas.
- Per `.planning/PROJECT.md` §Context, this demo is a UI/interaction reference only and never a business-rules source.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets

- `app/Models/AccountsReceivable.php` — **already exists**, with `jobOrder()`, `requestedBy()`, `approvedBy()` relations, `#[ObservedBy(AuditObserver::class)]`, a `decimal:2` cast on `balance`, and an explicit `$table = 'accounts_receivable'` override (Eloquent would otherwise pluralize to `accounts_receivables` — do not remove it). This phase adds columns and relations; it does not create the model.
- `app/Enums/AccountsReceivableStatus.php` — `PendingApproval` / `Active` / `Rejected`. D-09 leaves this untouched and adds a separate collection-status enum alongside it.
- `app/Policies/AccountsReceivablePolicy.php` — already has Owner-exclusive `approve()` / `reject()` for credit requests, with a docblock explaining the deliberate Owner-not-Admin narrowing below the route group's `role:owner,admin`. The write-off approval ability (D-13) belongs here, in the same shape.
- `app/Http/Controllers/Cashier/ReceiptController.php` + `resources/js/pages/cashier/Receipt.vue` — the printable-document pattern D-11 copies, and the completed-transactions balance computation D-16 copies.
- `resources/js/pages/owner/CreditRequests.vue` — the Owner approval-queue pattern D-04 and D-13 both reuse.
- `app/Mail/DesignReviewRequested.php` + `app/Actions/JobOrder/RecordDesignRevision.php:47` — the only existing Mailable and the only existing send site, wrapped in 04-13's failure isolation. D-05 follows both.
- `database/seeders/SystemConfigurationSeeder.php` — 15 keys already seeded via `updateOrCreate` + `SystemConfiguration::invalidate()`; `cancellation_fee_amount` and `discount_cap_percentage` (group `business_rules`) are the closest analogs for D-02's new `credit_term_days` key.
- `app/Observers/AuditObserver.php` — `AccountsReceivable` is already observed, so every collection-status change and write-off approval is audited with no new code.

### Established Patterns

- Form Request + Validation Concern trait pair (`app/Http/Requests/**` + `app/Concerns/*ValidationRules.php`) — the collection-status update and write-off request both need one.
- `Inertia::flash('toast', [...])` for mutation feedback.
- System config read pattern: controllers/Form Requests read `system_configurations`, never hardcode thresholds (Phase 3 DPI, Phase 4 break minutes, Phase 5 discount cap). D-02's `credit_term_days` follows it; D-03's bracket boundaries deliberately do not.
- String-backed, TitleCase-key enums.
- Wayfinder-generated route/action helpers for every new route — no hardcoded URLs. Regenerate with `--with-form`, not the bare command (01-04's documented trap).
- Additive migrations against existing tables — Phases 3, 4, 5 and 6 all added columns this way without conflict.

### Integration Points

- **`accounting_staff` portal is nearly empty** — `routes/portals.php` gives it a single `dashboard` route pointing at `ReconciliationController::index` plus the shared reconcile action, and `resources/js/pages/accounting/` **does not exist**. This phase builds the real Accounting portal, the same way Phase 6 replaced the placeholder `production-staff` dashboard.
- **No `app/Console/Commands` and a bare `routes/console.php`** — D-07's daily command is the project's first scheduled work. Phases 3–6 all deliberately chose synchronous processing over queued jobs; the scheduled command is a schedule concern, not a queue concern, and nothing here needs the queue.
- **No `app/Notifications` directory** — D-05 uses a `Mailable` via `Mail::to(...)`, matching the one existing precedent, not Laravel's notification system.
- **`accounts_receivable` has a unique index on `job_order_id`** (migration `2026_09_04_120000`, added by WR-04) — at most one receivable per job order. Any query or factory in this phase must respect that; do not seed two rows for one order.
- **`app/Enums/PaymentStatus.php`** gains one terminal write-off case (D-14). Check every existing consumer of `payment_status` — the Phase 5 release gate (`app/Http/Controllers/FrontlineStaff/JobOrderReleaseController.php`), the Cashier dashboard, and Phase 6's board filters — since 06-07 was a dedicated fix plan for exactly this class of enum-consumer regression.
- **`config/mail.php` / Resend** — the transport is installed and Phase 4 uses it, but confirm the from-address and that a failed send degrades gracefully rather than throwing out of the scheduled run.

</code_context>

<specifics>
## Specific Ideas

- The demo's aging bracket labels are `Current`, `30–60 Days`, `60–90 Days`, `90+ Days` — only four, and it skips the 15-day band entirely. D-03's five bands follow AR-01/AR-02's requirement text instead, which names 15 explicitly. The badge palette (`acAgingBadge`, ~line 256) is still a usable visual reference.
- The demo's write-off confirmation copy — "Pending Owner/Admin approval. Amount: ₱X. Once approved, JO will be marked Written Off and logged in the audit trail" — is a good model for D-13's pending-state banner. Note it says "Owner/Admin"; this project narrows that to Owner only, matching `AccountsReceivablePolicy`'s existing deliberate split.
- The demo shows `total` / `paid` / `balance` as three columns on each AR row. Under D-16 only `total` and the derived balance are real; `paid` is the sum of completed transactions. The three-column display is fine, the three stored fields are not.
- The demo's per-entry activity log renders newest-first with an actor (`by: 'AC001'` / `by: 'System'`). If the detail page carries one, it should be a filtered read of `audit_trail`, never a parallel table.

</specifics>

<deferred>
## Deferred Ideas

- **Payment adjustments (Credit Memo / Write-down / Overpayment Correction).** The demo has a full adjustment modal (`acConfirmAdjustment`, ~line 526) that mutates `total` and `paid` directly. Not built: AR-01–04 never mention adjustments, D-16 makes the balance derived so there is nothing to adjust in place, and writing down a total outside the POS flow contradicts D-15's single-money-path rule. If the shop genuinely needs credit memos, they belong in a phase of their own alongside refunds.
- **Manual reminder snooze / payment-plan hold.** Considered and rejected in D-08. If a customer negotiates a payment plan, the system currently keeps chasing. Adding a hold means new state with its own expiry rules.
- **Configurable aging bracket boundaries.** D-03 hardcodes 15/30/60/90. If a shop wants different bands, they become `business_rules` config keys following the existing seeding pattern — the same escape hatch Phase 6 D-07 left for the Green/Amber line.
- **Emailing or archiving the collection letter.** D-11's print page cannot be attached to anything. When Phase 8 (RPT-05) picks a PDF library for report export, the letter is the obvious second consumer.
- **Customer-facing automated dunning (SMS/email to the debtor).** Rejected in D-06. This is the same new-capability boundary Phase 6 D-13 drew for pickup notifications, and NOTF-01 is already deferred to v2 in `.planning/STATE.md`.
- **AR aging on the Owner dashboard.** D-04 keeps Owner to an approval queue. Phase 8's RPT-01 (Owner financial/profit reports) is where the aging summary should surface for the Owner.

</deferred>

---

*Phase: 7-accounts-receivable*
*Context gathered: 2026-09-07*
