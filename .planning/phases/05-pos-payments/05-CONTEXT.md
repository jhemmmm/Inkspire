# Phase 5: POS & Payments - Context

**Gathered:** 2026-09-04
**Status:** Ready for planning

<domain>
## Phase Boundary

A job order (already `ReadyForProduction` or `DesignApproved` from Phase 3/4) gets priced from the pricing database, paid for via Cash, Bank Transfer, or PayMongo-routed GCash/Maya, and receipted. Covers POS-01 through POS-09: pricing computation, payment recording, PayMongo webhook confirmation with manual reconciliation fallback, down payment/balance tracking, digital receipts, cancellation fee collection, and the On-Credit path gated by Owner approval and posting to accounts receivable. This phase adds the `job_orders.payment_status` column (the deviation documented in PROJECT.md — orthogonal to `status`, not yet built). Production stage advancement (Phase 6) and AR aging/reminders/write-offs (Phase 7) are explicitly out of this phase's scope — Phase 5 only creates the AR entry that Phase 7 later ages.

</domain>

<decisions>
## Implementation Decisions

### Pricing & Catalog Linkage

- **D-01:** The Cashier connects a job order to its price via **catalog pick + manual adjust**: they select a product/service from `pricing_database` (which has a base price) and can adjust the line amount for size/quantity/specifics. The job order's existing free-text `description` (from Phase 2 intake) stays purely descriptive — the catalog pick is the actual pricing key. This requires linking the job order to a `pricing_database` row (new FK), not just trusting the free-text field.

### Discounts

- **D-02:** Discounts are **Cashier-discretionary with a cap** — any % or flat discount up to a configurable maximum. No fixed named categories (Senior/PWD, Bulk) to maintain. The cap is a new `system_configurations` key, following the existing `rush_fee_percentage` pattern (group `business_rules`). Note: "loyalty/marketing discounts" remain explicitly Out of Scope per `REQUIREMENTS.md` — this discretionary model is a general pricing tool, not a loyalty mechanism.

### Rush Fee

- **D-03:** The rush fee is **applied by Cashier toggle at POS time**, not set at Phase 2 intake. A job order has no inherent "rush" property from earlier phases — this phase adds the flag/toggle at the point of payment and applies the existing `rush_fee_percentage` system config when set. No retrofitting of the Phase 2 intake flow.

### Cancellation Fee

- **D-04:** The cancellation fee triggers **only if the job order has progressed past `InDesign`** (design work already started, matching the demo's Outcome D pattern) — cancelling before design starts is free. The fee amount is a **flat rate from a new `system_configurations` key** (group `business_rules`), not a percentage or Cashier's discretion.
- **D-05:** If a down payment was already recorded on a job order that later gets cancelled with a fee, the down payment is **applied toward the cancellation fee** first — only a shortfall (fee > down payment) needs additional collection, and any excess is refundable/owed back to the customer. This nets the two amounts rather than tracking them as independent, unrelated transactions.

### On-Credit Approval Flow

- **D-06:** Approval is **asynchronous, not real-time in-person**. Cashier marks the job order "On Credit — Pending Approval" and the transaction/flow moves on; Owner reviews and approves later from their own portal, whenever they next check. This differs from Phase 4's in-person client-approval pattern (D-05 there) — deliberately, since Owner isn't assumed to be at the counter for every credit request.
- **D-07:** If Owner **rejects** the request, the job order **stays flagged as rejected/denied** — there is no automatic fallback to another payment method. Someone (Cashier/Owner) follows up manually to collect payment a different way. Do not build an auto-bounce-to-Cashier flow.
- **D-08:** Eligibility is **open** — any Cashier can request On-Credit for any job order/customer, no pre-check (no credit limit, no required customer history). Owner's approval step is the only gate. Simplest model for a small single-location shop; revisit only if credit abuse becomes a real problem.
- **D-09:** Approval posts to `accounts_receivable` with **balance only, no due date/term**. Due-date/aging-term logic is entirely Phase 7's responsibility to add when it builds AR aging brackets — Phase 5's AR write is minimal (job order link + outstanding amount).

### GCash/Maya Payment Experience

- **D-10:** The customer pays via a **QR/checkout shown on-screen at the counter** — Cashier creates a PayMongo Payment Intent/Source for the amount, the register displays a QR code or checkout link, and the customer scans it with their own phone and pays in their own GCash/Maya app. Not a link sent remotely to their phone/email for later payment.
- **D-11:** While a job order sits at "Pending Confirmation," the **Cashier can move on to serve the next customer** — the register does not block on a single pending transaction. Pending job orders show up in an "awaiting payment" list the Cashier (or Accounting) returns to; the webhook usually resolves it automatically anyway.
- **D-12:** The manual reconciliation check (POS-04) is a **per-transaction button visible to both Cashier and Accounting** — same action, available from either role's view of that job order. Not a separate Accounting-only bulk dashboard (though nothing here prevents Accounting from building a filtered list view of pending job orders using standard filtering, if planning finds that natural).
- **D-13:** If reconciliation shows the PayMongo payment still unpaid/expired, the Cashier can **switch to a different payment method** (Cash/Bank Transfer) for the same job order — the failed/expired intent is simply discarded, not retried indefinitely on the same method.

### Down Payment, Balance & Release Gating

- **D-14:** There is **no minimum down payment** — Cashier accepts whatever amount the customer can pay now; the remainder becomes tracked balance. No `system_configurations` floor to enforce or maintain.
- **D-15:** An unpaid balance **only blocks the final pickup/handover to the customer, not production itself**. A job order can proceed through all of Phase 6's production stages (For Production → Printing → Quality Check → Ready for Pickup) regardless of `payment_status` — this matches `ROADMAP.md`'s Phase 6 depending on Phase 3, not Phase 5. Only the actual release/handover action checks payment state.
- **D-16:** "Redirected to Cashier" (POS-09) for an unpaid pickup is enforced via a **dedicated "Release/Hand over" action** — separate from Phase 6's `ReadyForPickup` production status — that checks `payment_status`/active-credit and refuses (with a clear message) if unpaid or credit isn't active. Exact role/UI shape (who triggers it, button placement) is left to planning.

### Claude's Discretion

- Exact schema/columns for `pricing_database`, `transactions`, and `accounts_receivable` (three of the approved 12 ERD tables, not yet created) — follow the Eloquent model + `#[Fillable]`/`#[ObservedBy(AuditObserver::class)]` conventions Phase 2/3/4 established.
- Exact `job_orders.payment_status` enum values (e.g. `Unpaid`, `PartiallyPaid`, `PendingConfirmation`, `Paid`, `OnCredit`, `CreditPendingApproval`) — follow the existing TitleCase-key/string-value convention in `app/Enums/JobOrderStatus.php`.
- Exact new `system_configurations` keys/naming for the discount cap and cancellation fee amount — follow the existing `rush_fee_percentage`/`max_artist_break_minutes` seeding pattern in `database/seeders/SystemConfigurationSeeder.php`, group `business_rules`.
- Where the rush-fee toggle and discount input live in the Cashier's POS UI — a UI/UX call, not a business-rule call (this phase has `UI hint: yes` in ROADMAP.md, so a `/gsd-ui-phase` pass is available if needed).
- Exact mechanism for D-16's release/hand-over action (dedicated route+button vs. a status transition gate) — implementation detail, not a business-rule call.
- PayMongo API specifics (Payment Intent vs. Source object choice, webhook payload shape, signature verification approach) — research territory, not locked here; flagged in `STATE.md` as the phase's highest pitfall density.
- Whether Accounting gets a dedicated filtered list view of pending GCash/Maya job orders beyond the per-transaction reconciliation button (D-12) — a UI convenience, not a locked requirement.

</decisions>

<canonical_refs>

## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements & Roadmap

- `.planning/REQUIREMENTS.md` §POS — POS-01 through POS-09 full requirement text
- `.planning/REQUIREMENTS.md` §Out of Scope — "Loyalty/marketing features" is locked Out of Scope (relevant to D-02's discount model); "Quote-to-order workflow, customer self-service ordering" (adjacent but distinct from POS)
- `.planning/ROADMAP.md` §Phase 5 — goal, success criteria, depends on Phase 3
- `.planning/ROADMAP.md` §Phase 6 — depends on Phase 3 (not Phase 5), which grounds D-15's "unpaid balance doesn't block production" decision
- `.planning/ROADMAP.md` §Phase 7 — depends on Phase 5, "the accounting follow-through on Phase 5's credit path" — grounds D-09's minimal AR write

### Project-Level Context

- `.planning/PROJECT.md` §Constraints — PayMongo webhook-confirmed requirement; `transactions.job_order_id` NOT NULL (no standalone sales); `audit_trail` append-only
- `.planning/PROJECT.md` §Context — approved 12-table ERD (`transactions`, `pricing_database`, `accounts_receivable` are three of the twelve, all still unbuilt); the `job_orders.status`/`payment_status` column-split deviation (this phase is what actually adds `payment_status`)
- `.planning/PROJECT.md` §Key Decisions — PayMongo webhook-driven confirmation decision; the On-Credit approval gate decision (kept despite the demo's simpler no-on-credit behavior)

### Prior Phase Context

- `.planning/phases/03-job-order-intake-auto-assignment/03-CONTEXT.md` — current `JobOrderStatus` values this phase's `payment_status` column sits alongside (orthogonal, not replacing); `system_configurations` read pattern (Form Request reads thresholds, not hardcoded)
- `.planning/phases/04-artist-workflow-design-editor/04-CONTEXT.md` — `InDesign`/`DesignApproved` status values D-04's cancellation-fee gate checks against; D-05's in-person approval pattern (deliberately NOT reused here — see D-06)
- `.planning/phases/01-foundation-rbac-auth-hardening-audit-trail/01-CONTEXT.md` — audit observer registration pattern, Form Request + Validation Concern trait pairing, `Inertia::flash('toast', ...)` mutation-feedback convention

### State & Blockers

- `.planning/STATE.md` §Blockers/Concerns — Phase 5 flagged as "Highest pitfall density in the project (PayMongo webhook signature verification, idempotency, reconciliation fallback)" — this discussion locked business-rule decisions (D-06 through D-13) but PayMongo API integration specifics remain research territory

### UI Reference (non-authoritative)

- `/home/user/inkspire/demo/main.js`, `/home/user/inkspire/demo/index.html` — client's original UI demo. Referenced during this discussion for the cancellation-fee "design started" trigger pattern (D-04, "Outcome D" in the demo), the GCash/Maya/Cash/Bank Transfer payment-method ordering, and receipt layout. Per `PROJECT.md` §Context, this is a UI/interaction reference only — confirmed during this discussion that the demo has no On-Credit concept and a simpler discount model (senior/loyalty/bulk categories), both of which were NOT copied — see D-02, D-06 through D-09.

</canonical_refs>

<code_context>

## Existing Code Insights

### Reusable Assets

- `app/Models/JobOrder.php` — has `queueEntry()`, `assignedArtist()`, `designFile()`, `revisionLogs()` relations; this phase adds a `pricingEntry()`/similar relation (D-01), a `payment_status` cast column, and likely `transactions()` HasMany.
- `app/Enums/JobOrderStatus.php` — currently `Intake`, `ValidationFailed`, `ReadyForProduction`, `Assigned`, `InConsultation`, `InDesign`, `PendingReview`, `DesignApproved`. This phase does NOT add new values here — it adds a separate `payment_status` enum/column per the documented status/payment_status split, per D-04's need to check "past InDesign."
- `database/seeders/SystemConfigurationSeeder.php` — already seeds `rush_fee_percentage` (0, decimal, group `business_rules`) and other business-rule keys via `updateOrCreate` + `SystemConfiguration::invalidate()`; this phase's two new keys (discount cap, cancellation fee amount) follow this exact pattern.
- `app/Observers/AuditObserver.php` — new `Transaction`, `PricingDatabase`/`Product`, `AccountsReceivable` models get automatic audit coverage via `#[ObservedBy(AuditObserver::class)]`, same as every other domain model. Money-moving actions make audit coverage especially important here.
- `config/services.php` — no `paymongo` key exists yet; this phase adds one (`PAYMONGO_SECRET_KEY`/`PAYMONGO_PUBLIC_KEY`/`PAYMONGO_WEBHOOK_SECRET` or similar), same pattern as the existing `resend` stub Phase 4 activated.

### Established Patterns

- Form Request + Validation Concern trait pair (`app/Http/Requests/**` + `app/Concerns/*ValidationRules.php`)
- `Inertia::flash('toast', [...])` for mutation feedback
- Model observer registration via `#[ObservedBy(AuditObserver::class)]` directly on the model class
- String-backed, TitleCase-key enum convention (`app/Enums/JobOrderStatus.php`, `app/Enums/UserRole.php`)
- System config read pattern: Form Requests/controllers read `system_configurations` values, never hardcode thresholds (established in Phase 3 for DPI thresholds, Phase 4 for `max_artist_break_minutes`)
- Wayfinder-generated route/action helpers for all new routes
- Public/unauthenticated routes (needed for the PayMongo webhook endpoint) live outside any `role:*` middleware group — precedent set by Phase 4's signed-URL public review route

### Integration Points

- No `transactions`, `pricing_database`, or `accounts_receivable` models/migrations/controllers exist yet — this phase creates all three from scratch, same greenfield situation Phase 2 was in for `customers`/`queue_entries`/`job_orders`.
- No PayMongo SDK/package installed (`composer show --direct` confirmed clean) — this phase's PayMongo integration is a new dependency, needs approval per PROJECT.md's "dependency changes require approval" constraint (mirrors Phase 4's `resend/resend-php` and `tui-image-editor` approvals).
- `job_orders` table needs an additive `payment_status` column plus likely `pricing_entry_id`/similar FK (D-01) and a rush-fee-applied flag (D-03) — no conflicts with Phase 3/4's existing additive columns (`assigned_artist_id`, `validation_failure_reason`, `consultation_notes`, etc.).
- `users` table already has `role` enum with `Owner`/`Admin`/`Cashier`/`Accounting Staff` (per PROJECT.md's 7-role list) — On-Credit approval routes need Owner-only gating, reconciliation routes need Cashier+Accounting gating, following the existing `role:*` middleware pattern in `routes/portals.php`.
- No `app/Http/Controllers/Webhooks` or similar namespace exists yet — the PayMongo webhook receiver is new, needs signature verification against the raw request body (a common webhook-handling trap: Laravel's default JSON parsing can interfere with signature checks over the raw payload).
- Queue infrastructure exists (`config/queue.php`, database driver) but no queued jobs are used anywhere yet — Phase 3/4 both explicitly chose synchronous processing over queued jobs; this phase should default to the same unless PayMongo webhook processing genuinely needs retry semantics that only a queue provides (research question, not decided here).

</code_context>

<specifics>
## Specific Ideas

- The demo's GCash/Maya method selection UI orders payment methods as Cash → GCash → Maya → Bank Transfer, with distinct method-specific accent colors (`demo/main.js` line ~2947-2990) — a visual reference only, not locked here.
- The demo's receipt preview shows job order number, total, tendered amount, and balance in a running footer (`demo/main.js` `cs-receipt-*` elements) — a layout reference for POS-06's digital receipt, not a locked spec.
- The demo's Bank Transfer flow shows "JO will stay inactive until you manually confirm receipt of transfer" — this project's constraint says Bank Transfer is "recorded directly, no gateway" (PROJECT.md §Constraints), meaning Cashier records it as complete immediately rather than requiring a separate confirmation step like the demo's. This is a deliberate contrast, not something to replicate from the demo.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within Phase 5 scope (POS-01 through POS-09). No new-capability ideas came up; all four discussed areas were implementation decisions for the phase's existing requirements.

</deferred>

---

_Phase: 5-pos-payments_
_Context gathered: 2026-09-04_
