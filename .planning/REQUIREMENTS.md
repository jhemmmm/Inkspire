# Requirements: Inkspire

**Defined:** 2026-08-31
**Core Value:** A job order flows correctly end-to-end — a customer queues in, gets a job order created, pays, and the order moves through production to pickup with the right role seeing and doing the right thing at each step.

## v1 Requirements

### RBAC & Authentication

- [x] **RBAC-01**: User can log in with a role-scoped account (one of 7 roles: Owner, Admin, Frontline Staff, Artist, Cashier, Production Staff, Accounting Staff) and lands on that role's own dedicated portal
- [x] **RBAC-02**: User is blocked (403) from accessing any route outside their assigned role, enforced server-side on every request — not just hidden navigation
- [x] **RBAC-03**: User account locks out after 5 consecutive failed login attempts for a configurable duration
- [x] **RBAC-04**: User can only have one active session at a time; a new login invalidates the prior session
- [x] **RBAC-05**: User is logged out automatically after a configurable idle session timeout
- [x] **RBAC-06**: User's password must meet complexity rules (minimum length, mixed case, numbers, symbols)
- [x] **RBAC-07**: Owner/Admin can deactivate a user account; deactivated accounts cannot log in; accounts are never hard-deleted
- [x] **RBAC-08**: Every authentication event (login, logout, failed attempt, lockout) is written to the audit trail

### Customer & Queue

- [x] **QUEUE-01**: Frontline Staff can search for a returning customer by name or contact info
- [x] **QUEUE-02**: Frontline Staff can register a new customer
- [x] **QUEUE-03**: Frontline Staff can generate a queue number for a customer visit
- [x] **QUEUE-04**: A single queue visit can produce more than one job order (e.g. two different products in one visit)
- [x] **QUEUE-05**: Frontline Staff marks each job order as Type A (print-ready file) or Type B (needs consultation) at intake
- [x] **QUEUE-06**: A public, unauthenticated shared display shows each queue entry's number and status (Waiting/Serving/Done), refreshed via client-side polling — number and status only, no customer name or other PII

### Job Order & Design

- [x] **JOB-01**: Frontline Staff can upload a print-ready file for a Type A job order, auto-validated against configured DPI/format/max-size thresholds before being queued for production
- [x] **JOB-02**: A Type B job order auto-assigns to an available Artist via round-robin among artists who are clocked in and not on break
- [x] **JOB-03**: Artist can record consultation notes and generate a job order for a Type B customer
- [x] **JOB-04**: Artist can create and edit a design using the built-in TOAST UI-based image editor
- [x] **JOB-05**: Artist can log a design revision and submit it for review ("Send for Review")
- [x] **JOB-06**: A design file becomes read-only (locked) once its job order reaches final approval
- [x] **JOB-07**: Owner can authorize an override to unlock a locked design file; the override is written to the audit trail
- [x] **JOB-08**: Artist can set session status (On Break, End Shift), which affects eligibility for auto-assignment
- [x] **JOB-09**: Artist can view their own assigned job orders and use Next/Forward/Not-Appear queue controls
- [x] **JOB-10**: Artist can view their own performance metrics report

### POS & Payments

- [x] **POS-01**: Cashier can compute a job order's price from the pricing database (base price, rush fee, discounts)
- [x] **POS-02**: Cashier can record a payment against a job order via Cash, Bank Transfer, GCash, or Maya — every payment links to exactly one job order, no standalone sales
- [x] **POS-03**: For GCash/Maya, the system creates a PayMongo Payment Intent/Source; the job order shows "Pending Confirmation" until a signature-verified webhook confirms payment
- [x] **POS-04**: Cashier or Accounting can manually trigger a reconciliation check against PayMongo when a webhook hasn't arrived
- [x] **POS-05**: Cashier can record a down payment and track the remaining balance on a job order
- [x] **POS-06**: Cashier can generate a digital receipt for a completed payment
- [x] **POS-07**: Cashier can collect a cancellation fee when a job order is cancelled
- [x] **POS-08**: A job order can be placed On Credit, requiring Owner approval before the credit activates and posts to accounts receivable
- [x] **POS-09**: A job order cannot be released to the customer until fully paid (or on active credit); otherwise the customer is redirected to Cashier

### Production Monitoring

- [x] **PROD-01**: Production Staff can view a Production Monitoring board color-coded by urgency (Green = Normal, Amber = Rush)
- [x] **PROD-02**: Production Staff can advance a job order sequentially through For Production → Printing → Quality Check → Ready for Pickup, without skipping stages
- [x] **PROD-03**: Frontline Staff receives a "Ready for Pickup" alert when a job order reaches that stage

### Accounts Receivable

- [x] **AR-01**: Accounting Staff can view outstanding balances grouped into aging brackets (Current, 15/30/60/90+ days)
- [x] **AR-02**: The system automatically sends escalating reminder notifications as an AR entry crosses each aging bracket (15-day → Accounting+Owner, 30-day → urgent, 60-day → escalation, 90+ → final escalation with write-off option)
- [x] **AR-03**: Accounting Staff can update an AR entry's collection status and generate a printable collection letter
- [x] **AR-04**: Owner can approve a write-off of an AR balance

### Expenses

- [x] **EXP-01**: Accounting Staff can record an expense with a category, amount, and date

### Reporting

- [ ] **RPT-01**: Owner can view financial/profit reports
- [ ] **RPT-02**: Cashier can view Daily Sales & Cancellation reports
- [ ] **RPT-03**: Production Staff can view a Production Status report
- [ ] **RPT-04**: Accounting Staff can view Daily/Monthly Sales, Daily/Monthly Expenses, and Summary of Sales & Expenses reports
- [ ] **RPT-05**: Any role-scoped report can be exported to PDF or Excel

### Audit Trail

- [x] **AUDIT-01**: Owner/Admin can view a read-only audit trail of every mutating action and auth event, filterable by user/action/date
- [x] **AUDIT-02**: No user, including Owner, can edit or delete an audit trail entry — enforced structurally (no update/delete code path exists), not just by permission check

### Public Tracking

- [x] **TRACK-01**: A customer can enter a job order number on a public, unauthenticated page and see the order's current status
- [x] **TRACK-02**: The tracking page shows status only — no pricing, payment, customer PII, or design files

### System Configuration

- [x] **CONFIG-01**: Owner/Admin can configure rush fee %, DPI thresholds, accepted file formats/max size, per-product SLA, max artist break duration, session timeout, lockout duration, file retention days, and expense categories

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Notifications

- **NOTF-01**: Customer receives SMS/email notification when order is ready for pickup (currently: pull-based QR tracking only, no push)

### Reporting

- **RPT-06**: Admin can view non-financial reports beyond user/config/audit (Admin's report access stops at what's explicitly listed for RBAC-01's role boundary; anything beyond that is deferred)

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Blade-primary + Vue-islands architecture | Superseded — repo is already scaffolded on Inertia; that's the real architecture |
| Multi-role-per-user | Approved ERD has a single `role` column on `users`; one role per account |
| Laravel Echo / Reverb / websocket real-time updates | Client-side polling is sufficient at this shop's scale and concurrency |
| Multi-tenant / multi-branch support | Single-location system for SquareFoot Graphics & Ads |
| Materials/inventory management (paper, ink, stock) | Standard in larger print-MIS platforms but absent from the approved 12-table ERD; correct scope decision for a small single-location shop |
| Quote-to-order workflow, customer self-service ordering | Conflicts with the consultation-driven Type B model; not in the approved scope |
| Full offset-print preflight (bleed, CMYK, font embedding) | DPI/format/size validation is sufficient for this shop's process; full preflight is a superset not needed here |
| Loyalty/marketing features | Not part of the approved scope |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| RBAC-01 through RBAC-08 | Phase 1 - Foundation | Pending |
| AUDIT-01, AUDIT-02 | Phase 1 - Foundation | Pending |
| CONFIG-01 | Phase 1 - Foundation | Complete |
| QUEUE-01 through QUEUE-06 | Phase 2 - Customer & Queue Management | Pending |
| JOB-01, JOB-02 | Phase 3 - Job Order Intake & Auto-Assignment | Pending |
| JOB-03 through JOB-10 | Phase 4 - Artist Workflow & Design Editor | Pending |
| POS-01 through POS-09 | Phase 5 - POS & Payments | Pending |
| PROD-01 through PROD-03 | Phase 6 - Production Monitoring & Public Tracking | Pending |
| TRACK-01, TRACK-02 | Phase 6 - Production Monitoring & Public Tracking | Pending |
| AR-01 through AR-04 | Phase 7 - Accounts Receivable | Pending |
| EXP-01 | Phase 8 - Expenses & Reporting | Complete |
| RPT-01 through RPT-05 | Phase 8 - Expenses & Reporting | Pending |

**Coverage:**
- v1 requirements: 51 total (RBAC 8, QUEUE 6, JOB 10, POS 9, PROD 3, AR 4, EXP 1, RPT 5, AUDIT 2, TRACK 2, CONFIG 1)
- Mapped to phases: 51/51 ✓
- Unmapped: 0 ✓

---
*Requirements defined: 2026-08-31*
*Last updated: 2026-09-01 during Phase 2 discussion — added QUEUE-06 (shared queue display), user-requested expansion of Phase 2 scope, reversing the prior Out of Scope call on a lobby/TV board*
