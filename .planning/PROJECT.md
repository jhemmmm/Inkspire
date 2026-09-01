# Inkspire

## What This Is

Inkspire is a web-based printing management system for SquareFoot Graphics & Ads, replacing their manual/paper workflow (paper queueing, manual pricing, disconnected design tools, paper receipts, manual AR tracking) with one integrated platform. It covers customer queueing, job order management, POS/payments, a design workflow with a built-in web editor, QR-based order tracking for customers, accounts receivable with aging analysis, and reporting/audit trails. It's a modernization of an approved capstone manuscript (originally specified as vanilla PHP + MySQL + AJAX) onto Laravel + Inertia + Vue 3.

## Core Value

A job order flows correctly end-to-end — a customer queues in, gets a job order created (print-ready or needs-consultation), pays, and the order moves through production to pickup with the right role seeing and doing the right thing at each step. Everything else (reports, AR aging automation, QR polish) matters, but this flow working correctly is what makes the system trustworthy enough to replace the paper process.

## Requirements

### Validated

- ✓ Session-based authentication via Laravel Fortify (login, logout, password reset) — existing starter-kit scaffold
- ✓ User profile management (update profile, delete account) — existing starter-kit scaffold
- ✓ Security settings page (password change, two-factor auth scaffold present but not fully wired) — existing starter-kit scaffold
- ✓ Appearance/theme settings — existing starter-kit scaffold
- ✓ Inertia + Vue 3 request/response plumbing, Wayfinder-generated route helpers, Tailwind v4 + reka-ui component base — existing starter-kit scaffold
- ✓ 7-role RBAC (Owner, Admin, Frontline Staff, Artist, Cashier, Production Staff, Accounting Staff), single `role` column, each role with its own dedicated portal layout/sidebar — Validated in Phase 1: foundation-rbac-auth-hardening-audit-trail
- ✓ Read-only, append-only audit trail covering every mutating action and all auth events (login/logout/failed attempts/lockouts) — structurally undeletable/uneditable by anyone including Owner — Validated in Phase 1: foundation-rbac-auth-hardening-audit-trail
- ✓ Login hardening: lockout after 5 failed attempts, one active session per user, configurable session timeout, password complexity rules — Validated in Phase 1: foundation-rbac-auth-hardening-audit-trail
- ✓ System configuration panel (Owner/Admin): rush fee %, DPI thresholds, accepted file formats/max size, per-product SLA, max artist break duration, file retention settings, expense categories, session timeout — Validated in Phase 1: foundation-rbac-auth-hardening-audit-trail

### Active

- [ ] Public unauthenticated QR-based job order tracking portal (enter JO number, see status only)
- [ ] Customer registration and queue management (search returning customers, register new, generate queue numbers)
- [ ] Public, unauthenticated shared queue display (queue number + status only, no PII) for lobby/TV use, polling-refreshed
- [ ] Job Order intake: Type A (print-ready file, auto-validated by DPI/format/size against configurable thresholds) and Type B (needs consultation)
- [ ] Artist workflow: auto-assignment of job orders (both Type A and Type B), consultation notes, TOAST UI Image Editor-based design tool, revision logging, design lock on final approval with Owner-only override
- [ ] POS module: pricing computation from a pricing database, payment processing (Cash, GCash, Maya via PayMongo, Bank Transfer), down payments/balance tracking, digital receipts, cancellation fee collection — every POS transaction requires a linked job order, no standalone sales
- [ ] PayMongo webhook-confirmed payment flow for GCash/Maya (Payment Intent/Source creation, signature-verified webhook as source of truth, manual reconciliation fallback for delayed/lost webhooks)
- [ ] On-Credit payment path requiring Owner approval before activation, posting to accounts receivable
- [ ] Production Monitoring board (color-coded by urgency), sequential status updates (For Production → Printing → Quality Check → Ready for Pickup)
- [ ] Accounts Receivable: aging brackets (Current, 15/30/60/90+ days) with automated escalating reminder notifications, collection status tracking, printable collection letters, Owner-approved write-offs
- [ ] Expense encoding by category (Accounting role)
- [ ] Role-scoped reporting with PDF/Excel export (Daily/Monthly Sales, Cancellation, Production Status, Artist Performance, Financial/Profit reports, Summary of Sales & Expenses)
- [ ] Read-only, append-only audit trail covering every mutating action and all auth events (login/logout/failed attempts/lockouts) — structurally undeletable/uneditable by anyone including Owner
- [ ] Login hardening: lockout after 5 failed attempts, one active session per user, configurable session timeout, password complexity rules
- [ ] Real-time-ish cross-role notifications (e.g. "Ready for Pickup" alerts to Frontline) via client-side polling, not websockets
- [ ] System configuration panel (Owner/Admin): rush fee %, DPI thresholds, accepted file formats/max size, per-product SLA, max artist break duration, file retention settings, expense categories, session timeout

### Out of Scope

- Blade-primary + Vue-islands architecture — superseded; the repo is already scaffolded on Inertia and that's the real architecture now, not the originally-imagined split
- Multi-role-per-user (a staff account holding more than one role simultaneously) — the approved ERD has a single `role` column on `users`; keep it 1:1
- Laravel Echo / Reverb / websocket-based real-time updates — client-side polling is sufficient for this shop's scale and concurrency; revisit only if polling proves inadequate in production
- Multi-tenant / multi-branch support — this is a single-location system for SquareFoot Graphics & Ads

## Context

- **Manuscript-derived spec is authoritative for business rules.** This project modernizes an already-approved capstone manuscript. Its business rules (RBAC boundaries, status lifecycle, approval workflows, aging brackets, audit requirements) are the source of truth for what to build, even when they conflict with a client-provided HTML/JS UI demo found at `/home/user/inkspire/demo/`. That demo was checked once, for one question (whether each role should get its own dedicated portal — confirmed yes) — it is explicitly NOT a source for business rules or data model; several of its simplifications (a merged single Owner/Admin role, a 3-state payment field, no On-Credit concept, no automated AR reminders) were considered and rejected in favor of the manuscript's fuller rules.
- **Existing scaffold**: the repo at `/home/user/inkspire` is already initialized from Laravel's official `laravel/vue-starter-kit` (Laravel 13, PHP 8.4, Inertia v3, Vue 3.5, Fortify, Wayfinder, Tailwind v4, reka-ui). See `.planning/codebase/` for the full mapped state. Notably: the feature test suite is currently broken (`RefreshDatabase` disabled in `tests/Pest.php`, 17/27 tests failing) and should be fixed early rather than building on top of a broken baseline.
- **Database**: the approved ERD has 12 tables (`users, customers, queue_entries, job_orders, transactions, design_files, revision_logs, production_logs, accounts_receivable, pricing_database, expenses, audit_trail`). A 13th table, `system_configurations`, is a deliberate documented deviation to hold Owner-editable business rules that have nowhere else to live in the approved schema.
- **`job_orders` status modeling deviates from the manuscript's literal wording**: instead of one 13-value status list, it's split into two orthogonal columns — `status` (production stage) and `payment_status` (money state) — because a job can legitimately be, e.g., fully paid while still printing. This is a documented, deliberate deviation.
- Solo developer, no external deadline (not a capstone-defense-date-driven build).

## Constraints

- **Tech stack**: Laravel 13 / PHP 8.4 backend (already scaffolded), Inertia v3 + Vue 3.5 frontend (not Blade-primary), MySQL in production (currently SQLite in dev `.env` — switch before real use). Non-negotiable — this is the already-committed stack for the project, not an open choice.
- **RBAC**: exactly 7 roles, single `role` enum column on `users`, one role per user, each role with its own dedicated portal (not a shared layout with filtered nav).
- **Payments**: PayMongo for GCash/Maya, webhook-confirmed. Cash and Bank Transfer recorded directly, no gateway.
- **Data integrity**: `transactions.job_order_id` NOT NULL (no standalone POS sales); `audit_trail` structurally append-only (no update/delete code paths at all, not just permission checks); `users` deactivate via `is_active`, never hard-delete, never `SoftDeletes`.
- **Hosting**: Laravel Cloud is the intended target (managed MySQL/storage/queue/scheduler) over self-managed VPS — no dedicated DevOps for this project.

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Keep Inertia as the real frontend architecture (not Blade + Vue islands as originally imagined) | Repo is already scaffolded on Inertia via the official starter kit; rebuilding onto Blade would be pure rework with no benefit | — Pending |
| 7 roles with Owner and Admin split (not merged into one) | Manuscript specifies Owner-exclusive financial/approval powers distinct from Admin's user-management/config powers | — Pending |
| Each of the 7 roles gets its own dedicated portal layout, not a shared layout with role-filtered nav | Confirmed against the client's UI demo — this was the one thing intentionally checked against it | — Pending |
| `job_orders` splits `status` and `payment_status` into two columns instead of the manuscript's one 13-value status list | Production stage and payment state are orthogonal and can coexist (e.g. rush job fully paid while still printing) | — Pending |
| Add a 13th table, `system_configurations`, beyond the approved 12-table ERD | No existing table can hold Owner-editable business rules (rush fee %, DPI thresholds, SLA, etc.) without distorting its meaning | — Pending |
| Keep the manuscript's On-Credit approval gate and automated AR reminder pipeline, even though the client's UI demo shows simpler behavior (no on-credit concept, manual-only AR follow-up) | Explicit user decision: the demo is a UI reference, not a business-rules source; the manuscript's rules stand where they conflict | ✓ Good |
| Real-time-ish updates via client-side polling (Inertia partial reloads + a lightweight polling endpoint), not Laravel Echo/Reverb | Small shop, low concurrency, soft-real-time freshness (a few seconds' staleness) is acceptable; avoids running a websocket server | — Pending |
| PayMongo payment confirmation is webhook-driven, with a manual reconciliation action as fallback | Webhook signature verification is the reliable source of truth for GCash/Maya; a fallback covers delayed/lost webhooks without blocking the Cashier | — Pending |
| Reversed the "lobby/TV queue display Out of Scope" call from requirements definition — added QUEUE-06, a public status-only shared display, into Phase 2 | During Phase 2 discussion the user explicitly requested it, matching the manuscript-era demo's queue-display.html; status-only (no PII) keeps it consistent with the TRACK-01/02 public-tracking pattern, and polling-refresh keeps it consistent with the no-websockets constraint | — Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-09-01 after Phase 1 (foundation-rbac-auth-hardening-audit-trail) completion*
