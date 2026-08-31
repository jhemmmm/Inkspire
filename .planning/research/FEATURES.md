# Feature Research

**Domain:** Print-shop / small manufacturing job-order management system (POS + production tracking + AR, single-location walk-in shop)
**Researched:** 2026-08-31
**Confidence:** MEDIUM — Context7 not applicable (this is a domain/market question, not a library question); findings are WebSearch-derived from print-MIS vendor sites (ShopVOX, Printavo, EFI PrintSmith Vision) and preflight/queue-management vendor content, cross-checked across 3+ independent sources per claim. No official "print shop MIS standard" spec exists to verify against, so treat as MEDIUM confidence industry-pattern research rather than HIGH-confidence documented fact.

## Feature Landscape

### Table Stakes (Users Expect These)

Features users assume exist in any print-shop job-management product. Missing these makes the system feel like a downgrade from the paper process it replaces.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Job/order tracking with clear production stages | Every print-MIS (Printavo, ShopVOX, PrintSmith Vision) centers on a job board showing where each order stands; staff and customers both need this | MEDIUM | Locked scope has this (Production Monitoring board, sequential status). Industry convention is 7-10 named stages with clear stage ownership — locked scope's 4-stage sequential flow (For Production → Printing → QC → Ready for Pickup) is leaner but consistent with the pattern. |
| Order intake / job creation tied to a customer record | CRM-lite: every job needs a customer, contact info, order history | LOW-MEDIUM | Covered by Customer registration + Job Order intake. |
| File/artwork attachment on the job | Customers need to upload art; staff need to see what they're printing | MEDIUM | Covered by Type A/B intake + design_files table. |
| Proofing / design approval workflow with revision history | "Online Proofing & Approvals" is called out as a core module by every major vendor (ShopVOX, Printavo) — email-based approval chaos is the #1 complaint preflight/proofing tools solve | MEDIUM-HIGH | Covered: design editor, revision logging, design lock w/ Owner override. This is a differentiator-grade implementation (in-house editor vs. just approve/reject) — see Differentiators below. |
| File preflight / print-readiness validation on upload | Industry-standard practice; low-resolution/wrong-format uploads are called out as the #1 cause of reprints and wasted material across preflight-tool vendor content | MEDIUM | Covered: DPI/format/size auto-validation (Type A). Full commercial preflight also checks bleed, CMYK color mode, and font embedding — not in scope, and reasonably so for a small digital/signage shop rather than an offset commercial printer. |
| Point of sale / invoicing tied to the order | "Invoicing & Payments" — creating invoices, recording payments — is core to every vendor's feature set; a print job without a payment record is an incomplete system of record | MEDIUM-HIGH | Covered: POS module linked to job order, multiple payment methods, down payment/balance tracking, digital receipts. |
| Multiple payment method support (cash + digital/card) | Modern shops need cash plus at least one digital rail; in the Philippines context GCash/Maya are the dominant e-wallets, equivalent to Stripe/Square in US vendor feature lists | MEDIUM-HIGH | Covered via PayMongo (GCash, Maya) + Cash + Bank Transfer. |
| Basic reporting (sales, production throughput) | "Business Intelligence & Reporting" is universal — every vendor ships sales trend and job throughput reports | MEDIUM | Covered: role-scoped Daily/Monthly Sales, Production Status, Summary reports w/ PDF/Excel export. |
| Role-based access / permissions | Any multi-staff shop tool needs to prevent a Cashier from approving credit or an Artist from editing prices | MEDIUM | Covered: 7-role RBAC. Locked scope's choice of fully separate portals per role (not shared UI with permission-gated nav) is heavier than most vendors do — flagged as a differentiator/complexity note below. |
| Order status visibility for the customer | Customers expect to check "is my order ready" without calling; walk-in queue systems (Qwaiting, ScanQueue) universally offer a ticket/status lookup | LOW-MEDIUM | Covered: public QR job-order tracking portal (pull-based, status only). Most competing systems (queue vendors specifically) push SMS notifications proactively rather than requiring the customer to check — noted as a possible future differentiator, not a gap, since it wasn't confirmed as wanted. |
| Accounts receivable / running-tab tracking | Any shop that allows "pay later" or partial payment needs a ledger, not just a payment log | MEDIUM-HIGH | Covered, and covered unusually thoroughly (see Differentiators — aging brackets + automated reminders is above what most small-shop tools do). |
| Audit trail of key actions | Expected in any system handling money and approvals, though rarely marketed as a headline feature — it shows up as a request once a business has been burned by a dispute | LOW-MEDIUM | Covered: append-only audit trail, structurally undeletable. |

### Differentiators (Competitive Advantage)

Features that go beyond what typical small print-shop tools offer, or that address this shop's specific paper-process pain points better than a generic off-the-shelf tool would.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Built-in web design editor (not just approve/reject proofing) | Most competitors (Printavo, ShopVOX) treat "proofing" as upload-a-PDF-and-click-approve; a full in-browser editor (TOAST UI Image Editor) lets the Artist iterate without leaving the system — closer to a design tool than an MIS | HIGH | Real complexity driver. TOAST UI Image Editor is unmaintained upstream (community-forked); vet current maintenance state before committing further (flag for phase-specific research, not a blocker for this document). |
| Automated escalating AR reminder pipeline with aging brackets | Generic print-MIS "AR" is usually just an invoice-aging report a human reads; automated, escalating reminders tied to aging brackets (Current/15/30/60/90+) is closer to dedicated AR-automation software (the kind marketed separately from print MIS, e.g. invoicing-focused AR tools) than to what Printavo/ShopVOX ship out of the box | HIGH | This is a genuine differentiator vs. the domain norm — most print shops handle overdue accounts manually or with a generic "send invoice reminder" button, not bracket-driven escalation. Worth extra design/testing attention as a roadmap phase. |
| Formal On-Credit approval gate (Owner sign-off before a credit sale posts to AR) | Prevents staff from unilaterally extending credit — a real failure mode in small shops with informal "keep a tab" practices | MEDIUM | Not a standard SaaS print-MIS feature (those assume pay-on-invoice or card-on-file); this is bespoke to this shop's risk-control needs. Correctly scoped as a differentiator, not table stakes. |
| Public QR-based order tracking (no login) | Reduces "is my order ready" phone calls / walk-in interruptions without requiring a customer account or app | LOW-MEDIUM | Table-stakes-adjacent (see above) but the no-login QR approach specifically is a nice lightweight implementation choice vs. requiring account creation like some vendor customer portals do. |
| Auto-assignment of jobs to Artists (Type A and Type B) | Removes manual triage — a supervisor doesn't have to hand-assign every job | MEDIUM | Differentiator vs. manual/paper process; most small-shop tools leave assignment manual. Needs clear, testable assignment rules (load-balance? round robin? skill-based?) — flag for requirements definition, not resolved by this research. |
| Configurable business-rules panel (rush fee %, DPI thresholds, SLA, session timeout, etc.) | Lets Owner/Admin tune the system without a developer — most competing SaaS tools either hardcode these or bury them in unexposed settings | MEDIUM | Good practice; keeps the system adaptable without code changes. |
| Role-dedicated portals (not shared UI with role-filtered nav) | Confirmed against the client's own UI demo as wanted; gives each role a purpose-built experience rather than a generic dashboard with hidden menu items | HIGH | Flagging as a complexity note, not a critique: this is more front-end surface area (7 distinct portal layouts) than most competing products build, since most competitors ship one adaptive UI. Worth respecting in roadmap phase sizing — this multiplies UI work across every feature that touches more than one role. |

### Anti-Features (Commonly Requested, Often Problematic)

Features common in the broader print-MIS market that would be scope creep or actively harmful for this project, given its single-location, paper-replacement mandate.

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|------------------|-------------|
| Materials/inventory management (paper, ink, garment stock with auto-decrement and reorder alerts) | Standard in mid/large print-MIS platforms (ShopVOX, PrintXpand, Printlogic all ship this as a core module) | Full inventory tracking is its own subsystem (stock levels, purchase orders, vendor catalogs, waste tracking) — large scope addition not in the approved 12-table ERD; this shop's manuscript never specified it | Leave out of this milestone. If material shortages become a real operational pain point post-launch, revisit as a scoped future module — do not bolt it onto `pricing_database` or `job_orders`. |
| Quote-to-order conversion workflow (formal quoting stage before an order exists) | Standard for B2B commercial printers (PrintSmith Vision, ShopVOX) doing custom bids | This is a walk-in retail shop with a pricing database driving direct order entry, not a bid-based commercial printer; adding a quote stage duplicates the Job Order intake flow for no benefit at this shop's scale | Keep pricing-database-driven direct job order creation as-is; if large custom jobs need estimates later, add a lightweight "estimate" flag on an existing job order rather than a parallel quoting entity. |
| Real-time (websocket) live updates everywhere | Feels "modern," commonly requested by clients who've seen Slack/chat apps | Requires running/maintaining a websocket server (Reverb) for a single small shop's concurrency needs; adds infra and hosting complexity for imperceptible UX gain at this scale | Client-side polling via Inertia partial reloads (already the locked decision) — correct call, matches the domain's actual freshness needs. |
| Multi-branch / multi-tenant support | "Might expand to a second location someday" is a common ask | Massive scope multiplier (location-scoped data, cross-branch reporting, branch-level RBAC) for a single-location shop with no confirmed expansion plan | Single-location design now; if a second branch happens, that's a new milestone with its own research, not a speculative feature today. |
| Loyalty/rewards programs, marketing email campaigns, SMS blast marketing | Common upsell feature in retail/POS-adjacent SaaS (and in general queue-management vendors) | Distracts from the core value (job flows correctly end-to-end); this is a job-order and money-tracking replacement project, not a marketing platform | If customer retention becomes a priority later, it's a separate, explicitly-scoped feature — not something to fold into this build. |
| Full offset-print preflight (bleed, CMYK conversion, font embedding, auto-fix) | Standard in commercial-print preflight tools (Filecheck, printQ) | This shop's Type A validation (DPI/format/size) already covers the failure modes relevant to a small digital/signage print shop; full offset preflight targets a different production process (commercial press runs) this shop doesn't appear to run | Keep the current lighter-weight DPI/format/size validation; do not expand into full prepress preflight tooling. |
| Customer-facing self-service online ordering / storefront (customer builds and submits their own job without staff involvement) | Common in ShopVOX/Printavo positioning ("customer portal enables self-service... order placement") and inevitably gets requested as "why can't customers just order online" | This shop's core value is explicitly the in-person queue → job order → design consultation flow; a self-serve storefront bypasses Frontline/Artist intake entirely and conflicts with the Type B consultation model | The QR portal stays read-only (status tracking only), as already locked. If online ordering is wanted later, it's a distinct milestone with its own workflow design, not an extension of the tracking portal. |

## Feature Dependencies

```
Customer Queueing
    └──requires──> Customer registration (customer record must exist to queue)

Job Order Intake (Type A / Type B)
    └──requires──> Customer Queueing (a queued customer becomes a job order)
    └──requires──> Pricing Database (job pricing needs a rate source)

Artist Workflow (design editor, revision logging, design lock)
    └──requires──> Job Order Intake (there must be a job to design against)
    └──enhances──> Production Monitoring (design lock gates entry into production stages)

POS Module (payment processing, down payments)
    └──requires──> Job Order Intake (transactions.job_order_id NOT NULL — no standalone sales)
    └──requires──> Pricing Database

PayMongo Webhook Flow
    └──requires──> POS Module (GCash/Maya is one payment method within POS)

On-Credit Approval Flow
    └──requires──> POS Module (credit is selected at payment time)
    └──requires──> Owner role/portal (approval gate)
    └──feeds──> Accounts Receivable

Accounts Receivable (aging + reminders)
    └──requires──> On-Credit Approval Flow (AR entries originate from approved credit sales)

Production Monitoring Board
    └──requires──> Job Order Intake
    └──requires──> Artist Workflow (design lock) for jobs that need design work

Public QR Tracking Portal
    └──requires──> Job Order Intake (there must be a JO number to look up)
    └──enhances──> Production Monitoring (surfaces the same status externally)

Role-Scoped Reporting
    └──requires──> POS Module, Production Monitoring, Accounts Receivable, Expense Tracking (reports read from all of these)

Audit Trail
    └──enhances──> every mutating feature (cross-cutting, not a dependency chain — must be wired into each feature as it's built, not bolted on after)

System Configuration Panel
    └──enhances──> Job Order Intake (DPI thresholds, file formats), POS Module (rush fee %), Artist Workflow (max break duration), Login Hardening (session timeout)
```

### Dependency Notes

- **Job Order Intake requires Pricing Database:** POS pricing computation reads from `pricing_database`; this table effectively needs to be seeded/built before job intake pricing can work end-to-end — sequence this early in the roadmap.
- **Design Lock enhances Production Monitoring:** a job can't meaningfully enter "For Production" until its design is locked (for Type B jobs); this is a soft gate worth making explicit in the state machine, not just a UI convention.
- **On-Credit Approval Flow requires Owner role:** this is the one workflow that structurally cannot be built or tested without the Owner portal existing — Owner portal access should land before or alongside On-Credit work.
- **Accounts Receivable requires On-Credit Approval Flow:** don't build AR aging/reminders before the On-Credit posting path exists, since AR entries are seeded by approved credit sales — no other entry point into AR is in scope.
- **Audit Trail is cross-cutting, not sequential:** every phase that adds a mutating action needs to also add its audit log entry in the same phase — deferring "add audit trail later" risks silently missing coverage on early features (queueing, intake) that won't get revisited.
- **Real-time-ish notifications conflict with nothing** but depend on whatever they're notifying about existing first (e.g., "Ready for Pickup" notification depends on Production Monitoring's status transitions being implemented).

## MVP Definition

Given this is a single "replace the paper process end-to-end" milestone (not an iterative SaaS product), a traditional v1/v1.x/v2+ split is less applicable — the Core Value in PROJECT.md already defines the walking skeleton. Reframed as core flow vs. deferrable polish:

### Launch With (v1 — the trustworthy end-to-end flow)

- [ ] Customer queueing + registration — entry point to everything downstream
- [ ] Job Order intake (Type A + Type B) with pricing — the record of what's being sold
- [ ] Artist workflow with design lock — Type B jobs cannot proceed without this
- [ ] POS with Cash + at least one digital method — payment must be provable, not just assumed
- [ ] Production Monitoring board with sequential statuses — the operational core the shop actually runs on daily
- [ ] Basic RBAC (even if portals start minimal and get richer per role) — without this, everyone can do everything, which defeats the "right role, right step" value prop
- [ ] Audit trail from day one (cross-cutting — see dependency note above)

### Add After Core Flow Validated (v1.x)

- [ ] PayMongo webhook flow (GCash/Maya) — Cash/Bank Transfer alone proves the flow; digital payment gateway integration is a distinct, riskier subsystem (webhook reliability, signature verification, reconciliation) that shouldn't block validating the core job flow
- [ ] On-Credit approval + Accounts Receivable aging/reminders — a real feature, but only needed once the shop is actually extending credit through the system rather than paper
- [ ] Full reporting suite (PDF/Excel exports across all report types) — reports read from data the core flow produces; they're naturally sequenced after that data exists
- [ ] Public QR tracking portal — valuable but non-blocking; the shop can operate without it while it's being built
- [ ] Expense tracking — operationally independent of the job-order flow, can land anytime

### Future Consideration (beyond this milestone)

- [ ] Materials/inventory tracking — explicitly out of the approved scope; revisit only if it becomes a proven pain point
- [ ] Customer-facing self-service ordering — conflicts with the current Frontline/consultation-driven intake model
- [ ] SMS/push notifications to customers (vs. pull-based QR lookup) — not confirmed as wanted; the queue-management market treats this as standard, but it wasn't part of the locked scope

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|----------------------|----------|
| Job Order intake (Type A/B) | HIGH | MEDIUM | P1 |
| Production Monitoring board | HIGH | MEDIUM | P1 |
| POS core (Cash/Bank Transfer, job-linked) | HIGH | MEDIUM | P1 |
| RBAC + role portals | HIGH | HIGH | P1 |
| Artist design workflow + lock | HIGH | HIGH | P1 |
| Audit trail | MEDIUM (invisible until needed) | LOW-MEDIUM | P1 (cross-cutting, cheap to add early, expensive to retrofit) |
| PayMongo webhook payments | HIGH | HIGH | P2 |
| On-Credit + AR aging/reminders | MEDIUM-HIGH | HIGH | P2 |
| Public QR tracking portal | MEDIUM | LOW-MEDIUM | P2 |
| Role-scoped reporting | MEDIUM | MEDIUM | P2 |
| Expense tracking | LOW-MEDIUM | LOW | P2 |
| System configuration panel | MEDIUM (enables the above) | LOW-MEDIUM | P2 (build alongside whichever feature first needs a configurable value, not all at once) |
| Login hardening | MEDIUM (risk mitigation, not visible value) | LOW-MEDIUM | P1-P2 (lockout/session basics early; full config surface can follow) |

**Priority key:**
- P1: Must have for the core flow to be trustworthy
- P2: Should have, sequenced after the core flow proves out

## Competitor Feature Analysis

| Feature | Printavo / ShopVOX (SaaS print-MIS) | Generic Queue-Mgmt SaaS (Qwaiting, ScanQueue) | Inkspire's Approach |
|---------|--------------------------------------|-----------------------------------------------|----------------------|
| Job tracking | Kanban job board, 7-10 custom statuses, owner-based handoffs | N/A (not job-focused) | Leaner 4-stage sequential board (For Production → Printing → QC → Ready for Pickup) with color-coded urgency — simpler than SaaS norm, matched to a small shop's actual stage count |
| Proofing/design | Upload PDF, approve/reject, version tracking | N/A | Full in-browser design editor (TOAST UI) + revision log + lock — heavier than typical proofing tools, closer to a design app |
| Customer queue/status | Not a core feature (B2B quoting-focused) | Core feature: SMS push notification when it's your turn | Pull-based QR lookup, no push notification — lighter-weight than dedicated queue vendors, consistent with "polling not push" architecture decision |
| Payments | Stripe/Square/Authorize.net, integrates to QuickBooks/Xero | N/A | PayMongo (GCash/Maya) + Cash + Bank Transfer — correctly localized to Philippine payment rails instead of copying US-centric gateway choices |
| AR / credit | Basic invoice aging report | N/A | Aging brackets + automated escalating reminders + Owner-gated On-Credit approval — more sophisticated than the SaaS-print-MIS norm |
| Inventory | Core module (stock, PO, reorder alerts) | N/A | Out of scope — reasonable divergence for this shop's size/process, flagged above as intentional gap |
| Multi-role UI | Single adaptive UI with permission-gated nav (standard SaaS pattern) | N/A | 7 fully separate portal layouts — heavier engineering investment than the market norm; intentional per client demo confirmation, but the roadmap should size phases accordingly |

## Sources

- [Best Print Shop Management Software 2026 — softwareconnect.com](https://softwareconnect.com/roundups/best-print-shop-management-software/)
- [ShopVOX Print Shop Software feature overview](https://shopvox.com/print-shop-software/)
- [Printavo: Print Shop Job Tracking Software — Build Better Statuses](https://www.printavo.com/blog/print-shop-job-tracking-software/)
- [ShopVOX: A Comprehensive Look at Print Order Management Software](https://shopvox.com/print-shop-software/a-comprehensive-look-at-print-order-management-software-for-modern-print-shops/)
- [ShopVOX: Best Print Management Software — Features & Buyer's Guide](https://shopvox.com/print-shop-software/what-is-the-best-print-management-software-top-features-buyers-checklist/)
- [Waitwhile: 9 best queue management systems for retail](https://waitwhile.com/blog/best-queue-management-systems-for-retail/)
- [ScanQueue: Best Queue Management Software for Small Business](https://scanqueue.com/blog/best-queue-management-software-small-business)
- [Filecheck: Free PDF Preflight Checker](https://filecheck.io/tools/pdf-preflight)
- [Printcart: Preparing Print-Ready Files — DPI, Bleed, Color, and Fonts](https://www.printcart.com/tutorial/preparing-print-ready-files-dpi-bleed-color-and-fonts)
- [printQ: Ensuring Print Quality — Dynamic Preflight](https://www.web-to-printq.com/post/dynamic-preflight-check-printq)
- [Capterra: shopVOX vs ePS PrintSmith Vision comparison](https://www.capterra.com/compare/155218-210917/shopVOX-vs-ePs-PrintSmith-Vision)
- Project context: `/home/user/inkspire/.planning/PROJECT.md`

---
*Feature research for: print-shop / small manufacturing job-order management system*
*Researched: 2026-08-31*
