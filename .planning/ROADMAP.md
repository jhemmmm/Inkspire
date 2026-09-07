# Roadmap: Inkspire

## Overview

Inkspire replaces SquareFoot Graphics & Ads' paper-based print-shop workflow with a Laravel + Inertia + Vue platform built around one central flow: a customer queues in, gets a job order created, pays, and the order moves through production to pickup — with each of 7 roles seeing and doing only what belongs to them. Because RBAC, the audit trail, and the `job_orders` entity are hard technical prerequisites (every downstream feature is gated by role, and every downstream table hangs off `job_orders`), the roadmap is necessarily foundation-heavy in its first phases before true vertical-slice phases become possible. From Phase 3 onward, each phase delivers one complete, observable end-to-end capability: a job order gets validated and assigned, an artist takes it through design, a cashier takes it through payment, staff and customers track it through production and pickup, accounting chases unpaid balances, and finally every module's data rolls up into reports.

## Phases

**Phase Numbering:**

- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Foundation — RBAC, Auth Hardening & Audit Trail** - Every user logs in through a role-scoped, secured account and every mutating action is permanently recorded (completed 2026-09-01)
- [x] **Phase 2: Customer & Queue Management** - Frontline Staff can register/find customers and generate queue numbers that produce job orders (completed 2026-09-01)
- [x] **Phase 3: Job Order Intake & Auto-Assignment** - A job order becomes a validated, production-ready record, routed automatically when it needs an artist (completed 2026-09-01)
- [x] **Phase 4: Artist Workflow & Design Editor** - An Artist takes a Type B job from consultation through a locked, approved design (completed 2026-09-02)
- [x] **Phase 5: POS & Payments** - A job order gets priced, paid (cash, bank transfer, GCash/Maya, or on-credit), and receipted (completed 2026-09-05)
- [x] **Phase 6: Production Monitoring & Public Tracking** - Staff and customers can see a job order's physical progress through to pickup (completed 2026-09-06)
- [ ] **Phase 7: Accounts Receivable** - An on-credit balance is tracked from creation through aging, reminders, collections, and write-off
- [ ] **Phase 8: Expenses & Reporting** - Expenses get logged and every module's data rolls up into exportable, role-scoped reports

## Phase Details

### Phase 1: Foundation — RBAC, Auth Hardening & Audit Trail

**Goal**: Every user logs in through a role-scoped, secured account and every mutating action is permanently, structurally recorded — the non-negotiable trust foundation every later phase builds on.
**Mode:** mvp
**Depends on**: Nothing (first phase)
**Requirements**: RBAC-01, RBAC-02, RBAC-03, RBAC-04, RBAC-05, RBAC-06, RBAC-07, RBAC-08, AUDIT-01, AUDIT-02, CONFIG-01
**Success Criteria** (what must be TRUE):

  1. User logs in with a role-scoped account (one of 7 roles) and lands on that role's own dedicated portal
  2. User is blocked with a 403 when attempting to access a route outside their assigned role, enforced server-side even via direct URL — not just hidden navigation
  3. Account locks out after 5 consecutive failed login attempts for a configurable duration; only one session is active per user at a time; idle sessions time out after a configurable duration; passwords must meet complexity rules
  4. Owner/Admin can deactivate a user account (never hard-deleted) and configure system-wide business rules (rush fee %, DPI thresholds, file formats/size, SLA, artist break duration, session timeout, lockout duration, file retention, expense categories)
  5. Owner/Admin can view a read-only, filterable (by user/action/date) audit trail of every mutating action and auth event (login/logout/failed attempt/lockout); no update or delete code path exists for any audit entry, for any role including Owner

**Plans**: 12 plans in 6 waves
Plans:
**Wave 1**

- [x] 01-01-PLAN.md — Skeleton: Data Foundation & Audit Substrate (RefreshDatabase fix, UserRole enum, RBAC/lockout columns, audit_trail table, AuditObserver)
- [x] 01-02-PLAN.md — Password Complexity Enforcement Fix (RBAC-06)

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 01-03-PLAN.md — System Configuration Substrate (CONFIG-01 data layer)
- [x] 01-04-PLAN.md — Skeleton: Role Login & Owner Portal (RBAC-01, RBAC-02)
- [x] 01-05-PLAN.md — Login Success & Logout Auth Audit + Session Capture (RBAC-04, RBAC-08)

**Wave 3** *(blocked on Wave 2 completion)*

- [x] 01-06-PLAN.md — Skeleton: Audited User Deactivation (RBAC-07, closes the walking skeleton)
- [x] 01-07-PLAN.md — Account Lockout Enforcement (RBAC-03, RBAC-07, RBAC-08)
- [x] 01-08-PLAN.md — Session Boundary Enforcement: Single Session & Idle Timeout (RBAC-04, RBAC-05)
- [x] 01-09-PLAN.md — Remaining Role Portals (RBAC-01, RBAC-02 completion)

**Wave 4** *(blocked on Wave 3 completion)*

- [x] 01-10-PLAN.md — Audit Trail Viewer (AUDIT-01, AUDIT-02 UI compliance)

**Wave 5** *(blocked on Wave 4 completion)*

- [x] 01-11-PLAN.md — User Management Refinement: Authorization Policy & Reactivate (RBAC-07 completion)

**Wave 6** *(blocked on Wave 5 completion)*

- [x] 01-12-PLAN.md — System Configuration UI (CONFIG-01 completion)

**UI hint**: yes

### Phase 2: Customer & Queue Management

**Goal**: Frontline Staff can register or find customers and turn a visit into one or more queued job orders, exercising the full RBAC + audit stack on real business data for the first time.
**Mode:** mvp
**Depends on**: Phase 1
**Requirements**: QUEUE-01, QUEUE-02, QUEUE-03, QUEUE-04, QUEUE-05, QUEUE-06
**Success Criteria** (what must be TRUE):

  1. Frontline Staff can search for a returning customer by name or contact info, and register a new customer when none is found
  2. Frontline Staff can generate a queue number for a customer visit
  3. A single queue visit can produce more than one job order (e.g. two different products), with each job order marked Type A (print-ready) or Type B (needs consultation) at intake
  4. A public, unauthenticated shared display shows each queue entry's number and status (Waiting/Serving/Done), refreshed via polling, with no customer PII

**Plans**: 5 plans in 3 waves
Plans:
**Wave 1**

- [x] 02-01-PLAN.md — Customer Search & Registration (QUEUE-01, QUEUE-02)

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 02-02-PLAN.md — Queue Number Generation & Job Order Intake — Backend (QUEUE-03, QUEUE-04, QUEUE-05)

**Wave 3** *(blocked on Wave 2 completion)*

- [x] 02-03-PLAN.md — Job Order Intake — Frontend Wiring (QUEUE-03, QUEUE-04, QUEUE-05 completion)
- [x] 02-04-PLAN.md — Internal Queue List: Status Transitions & Add Job Order (QUEUE-03, QUEUE-04 continuation)
- [x] 02-05-PLAN.md — Public Queue Display (QUEUE-06)

**UI hint**: yes

### Phase 3: Job Order Intake & Auto-Assignment

**Goal**: A queued job order becomes a real, validated work item — Type A files pass automated quality checks and Type B jobs route themselves to an available artist — turning Phase 2's placeholders into production-ready records that every downstream module can act on.
**Mode:** mvp
**Depends on**: Phase 2
**Requirements**: JOB-01, JOB-02
**Success Criteria** (what must be TRUE):

  1. Frontline Staff can upload a print-ready file for a Type A job order, and the system auto-validates it against configured DPI/format/max-size thresholds before it's queued for production
  2. A Type B job order auto-assigns to an available Artist via round-robin among artists who are clocked in and not on break, with no manual assignment step required

**Plans**: 2 plans in 2 waves
Plans:
**Wave 1**

- [x] 03-01-PLAN.md — Job Order Validation & Assignment — Backend (JOB-01, JOB-02)

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 03-02-PLAN.md — Job Order Validation & Assignment — Frontend Wiring (JOB-01, JOB-02 completion)

**UI hint**: yes

### Phase 4: Artist Workflow & Design Editor

**Goal**: An Artist can take a Type B job from consultation through a locked, approved design — the complete design lifecycle a non-print-ready order goes through before it can print.
**Mode:** mvp
**Depends on**: Phase 3
**Requirements**: JOB-03, JOB-04, JOB-05, JOB-06, JOB-07, JOB-08, JOB-09, JOB-10
**Success Criteria** (what must be TRUE):

  1. Artist can record consultation notes and generate a job order for a Type B customer
  2. Artist can create and edit a design using the built-in TOAST UI-based image editor, log a revision, and submit it for review ("Send for Review")
  3. A design file becomes read-only once its job order reaches final approval, and only Owner can authorize an audited override to unlock it
  4. Artist can set session status (On Break, End Shift) which affects auto-assignment eligibility, view their own assigned job orders with Next/Forward/Not-Appear queue controls, and view their own performance metrics report

**Plans**: 13 plans in 8 waves
Plans:
**Wave 1**

- [x] 04-01-PLAN.md — Consultation Notes & Artist Queue Controls — Backend (JOB-03, JOB-09)

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 04-02-PLAN.md — Consultation Notes & Artist Queue Controls — Frontend Wiring (JOB-03, JOB-09 completion)
- [x] 04-03-PLAN.md — Design Editor & Send for Review — Backend (JOB-04, JOB-05)

**Wave 3** *(blocked on Wave 2 completion)*

- [x] 04-04-PLAN.md — Design Editor & Send for Review — Frontend Wiring (JOB-04, JOB-05 completion)
- [x] 04-05-PLAN.md — Design Review, Lock & Owner Override — Backend (JOB-06, JOB-07)

**Wave 4** *(blocked on Wave 3 completion)*

- [x] 04-06-PLAN.md — Design Review, Lock & Owner Override — Frontend Wiring (JOB-06, JOB-07 completion)
- [x] 04-07-PLAN.md — Artist Session Status — Backend (JOB-08)

**Wave 5** *(blocked on Wave 4 completion)*

- [x] 04-08-PLAN.md — Artist Session Status — Frontend Wiring (JOB-08 completion)
- [x] 04-09-PLAN.md — Artist Performance Report — Backend (JOB-10)

**Wave 6** *(blocked on Wave 5 completion)*

- [x] 04-10-PLAN.md — Artist Performance Report — Frontend Wiring (JOB-10 completion)

**Wave 7** *(2026-09-03 scope expansion — post-UAT, user-forced into Phase 4 rather than a new phase; see 04-CONTEXT.md D-17 through D-23)*

- [x] 04-11-PLAN.md — Client Remote Design Review (JOB-06 extension: D-17 through D-21)
- [x] 04-12-PLAN.md — PSD Import (JOB-04 extension: D-22, D-23)

**Wave 8** *(gap closure — 2026-09-04, closes 04-VERIFICATION.md's sole BLOCKER)*

- [x] 04-13-PLAN.md — Mail transport failure isolation on Send for Review (JOB-05)

**UI hint**: yes

### Phase 5: POS & Payments

**Goal**: A job order can be priced, paid for — by cash, bank transfer, or PayMongo-routed GCash/Maya — and receipted, with credit sales gated by Owner approval, completing the money side of the core flow.
**Mode:** mvp
**Depends on**: Phase 3
**Requirements**: POS-01, POS-02, POS-03, POS-04, POS-05, POS-06, POS-07, POS-08, POS-09
**Success Criteria** (what must be TRUE):

  1. Cashier can compute a job order's price from the pricing database (base price, rush fee, discounts) and record a payment via Cash, Bank Transfer, GCash, or Maya — every payment links to exactly one job order, no standalone sales
  2. For GCash/Maya, the job order shows "Pending Confirmation" until a signature-verified PayMongo webhook confirms payment; Cashier or Accounting can manually trigger a reconciliation check when a webhook hasn't arrived
  3. Cashier can record a down payment and track the remaining balance, generate a digital receipt for a completed payment, and collect a cancellation fee when a job order is cancelled
  4. A job order can be placed On Credit, requiring Owner approval before the credit activates and posts to accounts receivable; a job order cannot be released to the customer until fully paid or on active credit, otherwise the customer is redirected to Cashier

**Plans**: 7 plans in 7 waves
Plans:
**Wave 1**

- [x] 05-01-PLAN.md — Pricing & Cash/Bank Transfer Payment (POS-01, POS-02, POS-05)

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 05-02-PLAN.md — Digital Receipt (POS-06)

**Wave 3** *(blocked on Wave 2 completion)*

- [x] 05-03-PLAN.md — GCash/Maya via PayMongo, Signed Webhook Confirmation (POS-03)

**Wave 4** *(blocked on Wave 3 completion)*

- [x] 05-04-PLAN.md — Manual Reconciliation (Cashier + Accounting Staff) (POS-04)

**Wave 5** *(blocked on Wave 4 completion)*

- [x] 05-05-PLAN.md — Cancellation Fee & Down-Payment Netting (POS-07)

**Wave 6** *(blocked on Wave 5 completion)*

- [x] 05-06-PLAN.md — On-Credit Request & Owner Approval (POS-08)

**Wave 7** *(blocked on Wave 6 completion)*

- [x] 05-07-PLAN.md — Release/Hand-over Payment Gate (POS-09)

**UI hint**: yes

### Phase 6: Production Monitoring & Public Tracking

**Goal**: A job order's physical progress is visible to Production Staff on an urgency-coded board and to the customer through a public QR-based status check, closing the loop from payment through to pickup.
**Mode:** mvp
**Depends on**: Phase 3
**Requirements**: PROD-01, PROD-02, PROD-03, TRACK-01, TRACK-02
**Success Criteria** (what must be TRUE):

  1. Production Staff can view a Production Monitoring board color-coded by urgency (Green = Normal, Amber = Rush)
  2. Production Staff can advance a job order sequentially through For Production → Printing → Quality Check → Ready for Pickup, without skipping stages
  3. Frontline Staff receives a "Ready for Pickup" alert when a job order reaches that stage
  4. A customer can enter a job order number on a public, unauthenticated page and see only its current status — no pricing, payment, customer PII, or design files

**Plans**: 7 plans in 5 waves
Plans:
**Wave 1**

- [x] 06-01-PLAN.md — Foundation: Schema, Enums, Models & Job Order Number Generator (substrate for PROD-01, PROD-02, TRACK-01)

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 06-02-PLAN.md — Job Order Number Generation & Display (TRACK-01)
- [x] 06-03-PLAN.md — Public Tracking & Receipt QR (TRACK-01, TRACK-02)

**Wave 3** *(blocked on Wave 2 completion)*

- [x] 06-04-PLAN.md — Automatic Production Entry & Payment Compatibility (PROD-01, PROD-02)

**Wave 4** *(blocked on Wave 3 completion)*

- [x] 06-05-PLAN.md — Frontline Ready-for-Pickup Alert (PROD-03)

**Wave 5** *(blocked on Wave 4 completion)*

- [x] 06-06-PLAN.md — Production Board: View & Stage Advancement (PROD-01, PROD-02 completion)
- [x] 06-07-PLAN.md — Fix: Production-Status Consumer Compatibility (POS-07, JOB-10 regression closure)

**UI hint**: yes

### Phase 7: Accounts Receivable

**Goal**: An On-Credit balance is tracked from creation through aging, escalating reminders, collections, and Owner-approved write-off — the accounting follow-through on Phase 5's credit path.
**Mode:** mvp
**Depends on**: Phase 5
**Requirements**: AR-01, AR-02, AR-03, AR-04
**Success Criteria** (what must be TRUE):

  1. Accounting Staff can view outstanding balances grouped into aging brackets (Current, 15/30/60/90+ days)
  2. The system automatically sends escalating reminder notifications as an AR entry crosses each aging bracket (15-day → Accounting+Owner, 30-day → urgent, 60-day → escalation, 90+ → final escalation with write-off option)
  3. Accounting Staff can update an AR entry's collection status and generate a printable collection letter
  4. Owner can approve a write-off of an AR balance

**Plans**: 5 plans in 4 waves
Plans:
**Wave 1**

- [x] 07-01-PLAN.md — Foundation: Aging & Collection Schema Substrate (substrate for AR-01, AR-02, AR-03, AR-04; closes the due_at stamping gap in CreditApprovalController)

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 07-02-PLAN.md — Aging List & Entry Detail (AR-01)
- [x] 07-03-PLAN.md — Escalating Reminder Command (AR-02)

**Wave 3** *(blocked on Wave 2 completion)*

- [x] 07-04-PLAN.md — Collection Status & Printable Collection Letter (AR-03)

**Wave 4** *(blocked on Wave 3 completion)*

- [ ] 07-05-PLAN.md — Write-Off Request & Owner Approval (AR-04)

**UI hint**: yes

### Phase 8: Expenses & Reporting

**Goal**: Every role can see the numbers that matter to them — expenses get logged and every module's data rolls up into exportable, role-scoped reports — completing the operational picture now that every upstream module produces real data.
**Mode:** mvp
**Depends on**: Phase 1, Phase 5, Phase 6, Phase 7 (aggregates data from RBAC/audit, POS, production, and AR)
**Requirements**: EXP-01, RPT-01, RPT-02, RPT-03, RPT-04, RPT-05
**Success Criteria** (what must be TRUE):

  1. Accounting Staff can record an expense with a category, amount, and date
  2. Owner can view financial/profit reports; Cashier can view Daily Sales & Cancellation reports; Production Staff can view a Production Status report; Accounting Staff can view Daily/Monthly Sales, Daily/Monthly Expenses, and Summary of Sales & Expenses reports
  3. Any role-scoped report can be exported to PDF or Excel

**Plans**: TBD
**UI hint**: yes

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Foundation — RBAC, Auth Hardening & Audit Trail | 12/12 | Complete    | 2026-09-01 |
| 2. Customer & Queue Management | 5/5 | Complete   | 2026-09-01 |
| 3. Job Order Intake & Auto-Assignment | 2/2 | Complete    | 2026-09-02 |
| 4. Artist Workflow & Design Editor | 13/13 | Complete    | 2026-09-03 |
| 5. POS & Payments | 7/7 | Complete    | 2026-09-05 |
| 6. Production Monitoring & Public Tracking | 7/7 | Complete   | 2026-09-06 |
| 7. Accounts Receivable | 4/5 | In Progress|  |
| 8. Expenses & Reporting | 0/TBD | Not started | - |
