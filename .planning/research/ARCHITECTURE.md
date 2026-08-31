# Architecture Research

**Domain:** Multi-role internal business management system (print-shop/small-business ops: queueing, job orders, POS/payments, production tracking, AR, reporting) on Laravel + Inertia + Vue
**Researched:** 2026-08-31
**Confidence:** HIGH (framework mechanics, Laravel/Inertia conventions) / MEDIUM (PayMongo webhook specifics, third-party package choices)

## Standard Architecture

### System Overview

```
┌───────────────────────────────────────────────────────────────────────────┐
│                         Browser (Vue 3 SPA, per role)                      │
│  7 role portals, each own layout+nav:                                      │
│  Owner | Admin | Frontline | Artist | Cashier | Production | Accounting    │
│  + 1 public unauthenticated surface: QR Tracking Portal                    │
│  resources/js/pages/{role}/**  resources/js/layouts/{role}/**              │
└──────────────────┬───────────────────────────────┬─────────────────────────┘
                    │ Inertia visits (auth'd)        │ Inertia visits (guest)
                    ▼                                 ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  Middleware: auth, EnsureRole (per-portal route group), HandleInertia      │
│  Route groups: routes/{role}.php  +  routes/tracking.php (guest, no auth)  │
└──────────────────┬───────────────────────────────────────────────────────┘
                    ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  Controllers (thin, per feature/role) → Form Requests → Actions            │
│  app/Http/Controllers/{Role}/**Controller.php                              │
│  app/Actions/{Domain}/**Action.php  (state transitions, payment capture,   │
│    AR posting, design lock, queue assignment — single-purpose, testable)   │
│  app/Policies/**Policy.php  (RBAC authorization per model)                 │
└──────────────────┬───────────────────────────────────────────────────────┘
                    ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  Domain Models (Eloquent) — job_orders is the hub                          │
│  User ─┐  Customer → QueueEntry → JobOrder ─┬→ Transaction → AccountsRecv. │
│         └(role)                              ├→ DesignFile → RevisionLog   │
│  PricingDatabase ↗                           ├→ ProductionLog              │
│  Expense (independent)                       └→ (status, payment_status)  │
│  SystemConfiguration (Owner-editable rules, read by many Actions)          │
│  AuditTrail (append-only — written via Observers/Listeners, never touched  │
│    by feature code directly)                                               │
└──────────────────┬───────────────────────────────────────────────────────┘
                    ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  Cross-cutting infrastructure                                              │
│  - Model Observers → AuditTrail rows (append-only, structural, not permission-gated) │
│  - Queued Jobs: PayMongo webhook processing, AR aging recalculation,       │
│    escalating reminder dispatch, report generation (large exports)         │
│  - Scheduler (Laravel Cloud managed): nightly AR aging bracket sweep,      │
│    SLA/urgency recompute for production board coloring                     │
│  - Notifications: DB-backed notification model, surfaced via polling       │
│    (Inertia `usePoll` / partial reloads), not broadcast/websockets         │
└──────────────────┬───────────────────────────────────────────────────────┘
                    ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  External: PayMongo (Payment Intents/Sources API + signature-verified      │
│  webhook endpoint, public, CSRF-exempt, raw-body signature check)          │
└───────────────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Typical Implementation |
|-----------|----------------|------------------------|
| Role portals (7) | Own layout/nav/dashboard per role; scope what each role can see/do | `resources/js/layouts/{role}/*Layout.vue`, `resources/js/pages/{role}/**`, route-group prefix per role in `app.ts` layout resolver |
| Public tracking portal | Unauthenticated JO-number lookup, status-only display | Dedicated guest route (`routes/tracking.php`), no `auth` middleware, minimal props (never leak customer PII, pricing, or internal notes) |
| `EnsureRole` middleware | Gate entire route groups by the single `role` column | Custom middleware, one per role or parameterized (`EnsureRole:owner,admin`) registered in `bootstrap/app.php` |
| Policies | Per-model authorization (can this role approve this write-off, edit this job order, etc.) | `app/Policies/JobOrderPolicy.php`, `TransactionPolicy.php`, etc. — checked in controllers/Form Requests via `$this->authorize()` |
| Actions | Single-purpose, testable business operations that mutate state (status transitions, payment capture, AR posting, artist auto-assignment, design lock) | `app/Actions/JobOrders/AdvanceStatusAction.php`, `app/Actions/Payments/RecordPaymentAction.php` — invoked from controllers and from queued jobs (e.g. webhook handler) |
| Form Requests | Authorize + validate input per feature, delegate shared rules to Concerns traits (existing convention) | `app/Http/Requests/{Feature}/*Request.php` |
| Eloquent Models | Persistence + relationships + domain invariants (e.g. `JobOrder::isDesignLocked()`) | `app/Models/*.php`, one per ERD table + `SystemConfiguration` |
| Observers/Listeners | Write append-only `AuditTrail` rows on every mutating model event, without feature code opting in per-call | `app/Observers/*Observer.php` registered per audited model, or a single generic `Auditable` trait + boot hook |
| Queued Jobs | Offload PayMongo webhook processing, AR aging sweeps, reminder escalation, heavy report exports | `app/Jobs/*.php`, dispatched from webhook controller/scheduler, run on Laravel Cloud's managed queue |
| Scheduler | Nightly/periodic recompute of AR aging brackets, SLA/urgency flags for the production board | `routes/console.php` `Schedule::` calls, run by Laravel Cloud's managed scheduler |
| Notifications (polling) | Cross-role alerts (e.g. "Ready for Pickup" to Frontline) surfaced via periodic client refetch, not push | DB notifications table + Inertia `usePoll`/`router.reload({ only: [...] })` on affected pages |
| PayMongo integration | Payment Intent/Source creation (Cashier-initiated), signature-verified webhook (source of truth), manual reconciliation fallback | `app/Http/Controllers/Webhooks/PayMongoWebhookController.php` (CSRF-exempt, raw body), `app/Services/PayMongoClient.php` thin HTTP wrapper |
| Wayfinder actions/routes | Typed frontend calls into role-scoped controllers | Generated, unchanged pattern from existing scaffold |

## Recommended Project Structure

```
app/
├── Actions/                      # Business operations, one class = one operation
│   ├── JobOrders/                 # CreateJobOrderAction, AdvanceStatusAction, AssignArtistAction, LockDesignAction
│   ├── Payments/                  # RecordCashPaymentAction, CreatePayMongoIntentAction, ConfirmPayMongoPaymentAction, ReconcilePaymentAction
│   ├── AccountsReceivable/        # ApproveOnCreditAction, RecalculateAgingAction, WriteOffAction, EscalateReminderAction
│   └── Queue/                     # RegisterCustomerAction, GenerateQueueNumberAction
├── Http/
│   ├── Controllers/
│   │   ├── Owner/                 # role-scoped controllers, one dir per portal
│   │   ├── Admin/
│   │   ├── Frontline/
│   │   ├── Artist/
│   │   ├── Cashier/
│   │   ├── Production/
│   │   ├── Accounting/
│   │   ├── Tracking/              # public/guest — TrackingController@show
│   │   └── Webhooks/              # PayMongoWebhookController (no auth, no role)
│   ├── Middleware/
│   │   └── EnsureRole.php
│   └── Requests/                  # mirrors Controllers/, one subfolder per feature
├── Models/                        # one per ERD table + SystemConfiguration
├── Observers/                     # audit trail writers, registered per audited model
├── Policies/                      # one per authorizable model
├── Jobs/                          # queued: webhook processing, aging sweep, reminders, exports
├── Notifications/                 # DB-channel notifications (ready-for-pickup, AR reminders)
└── Services/                      # thin external API wrappers (PayMongoClient), pricing calculator

resources/js/
├── layouts/
│   ├── owner/  admin/  frontline/  artist/  cashier/  production/  accounting/
│   └── tracking/                  # minimal guest layout, no sidebar/nav
├── pages/
│   ├── owner/  admin/  frontline/  artist/  cashier/  production/  accounting/
│   └── tracking/                  # public status-lookup page
└── composables/
    └── usePolling.ts              # thin wrapper around Inertia's poll helper for notification refresh

routes/
├── owner.php  admin.php  frontline.php  artist.php  cashier.php  production.php  accounting.php
├── tracking.php                   # guest, no auth middleware
└── webhooks.php                   # PayMongo, CSRF-exempt
```

### Structure Rationale

- **One controller/page/layout directory per role**: matches the already-decided "each role gets its own dedicated portal" requirement — avoids one shared `AppLayout` with role-filtered nav, which was explicitly rejected. Route groups double as the natural place to attach `EnsureRole`.
- **`Actions/` over fat services or fat controllers**: state transitions here (job order status, payment recording, AR approval) have real business rules (guarded transitions, side effects like audit rows and notifications) that deserve a single, independently testable class — not scattered across controller methods or buried in a monolithic `JobOrderService` god-class. Matches existing scaffold's preference for thin controllers (`app/Actions/Fortify/` already establishes this pattern).
- **`Webhooks/` and `Tracking/` controllers live outside role-scoped groups**: both are intentionally unauthenticated (PayMongo can't log in; a walk-in customer scanning a QR code isn't a staff account), so keeping them structurally separate from `EnsureRole`-protected groups prevents accidental exposure through a shared base controller.
- **`Observers/` for audit trail**: keeps "log every mutating action" out of every controller/action by hooking Eloquent's `created`/`updated`/`deleted` (deleted should never fire for domain data given the no-hard-delete rule, but the observer can assert that). This is the only way to satisfy "structurally append-only... not just permission checks" without threading logging calls through every Action by hand.

## Architectural Patterns

### Pattern 1: Role-Scoped Route Groups + `EnsureRole` Middleware + Policies (defense in depth)

**What:** Two authorization layers — coarse (route middleware blocks a role from even reaching a portal's routes) and fine (Policies check per-model rules like "only Owner can override design lock" or "only Accounting can write off AR").
**When to use:** Coarse gate at the route/middleware level for "can this role see this portal at all"; fine-grained Policy checks for actions that need model context or cross-role exceptions (e.g. Owner override).
**Trade-offs:** More boilerplate than a single global gate, but matches the 7-dedicated-portal decision and gives a clean audit-trail hook point (Policy denial is itself loggable). Spatie permission packages are unnecessary here — a single `role` enum column with 7 fixed values doesn't need a many-to-many permissions system; native Gates/Policies are simpler, have no extra dependency, and directly express "one role per user."

```php
// app/Http/Middleware/EnsureRole.php
public function handle(Request $request, Closure $next, string ...$roles): Response
{
    abort_unless(in_array($request->user()->role->value, $roles, true), 403);
    return $next($request);
}

// routes/cashier.php
Route::middleware(['auth', 'ensure.role:cashier,owner,admin'])
    ->prefix('cashier')
    ->group(function () {
        Route::get('pos', [PosController::class, 'index'])->name('cashier.pos');
    });
```

### Pattern 2: Orthogonal Status Columns Advanced via Guarded Actions, Not a Full State-Machine Package

**What:** `job_orders.status` (production stage) and `payment_status` (money state) each have a small, fixed set of valid transitions. Rather than pulling in a generic workflow/state-machine package, each transition is its own `Action` class that (a) validates the current state allows the transition, (b) performs the mutation, (c) triggers side effects (audit row via Observer, notification, AR posting).
**When to use:** When the state set is small, fixed, and known upfront (this project: ~5 production stages, ~4 payment states) rather than open-ended/user-configurable workflows.
**Trade-offs:** A dedicated package (Laravel Workflow, generic state-machine libs) adds power (visual graphs, async orchestration) this project doesn't need and adds a dependency + learning curve for a solo developer. Hand-rolled guarded Actions are simpler to test with Pest and easier to reason about for two orthogonal columns (a generic single-column state machine package assumes one state field, not two independent ones).

```php
// app/Actions/JobOrders/AdvanceStatusAction.php
final class AdvanceStatusAction
{
    private const TRANSITIONS = [
        'for_production' => ['printing'],
        'printing' => ['quality_check'],
        'quality_check' => ['ready_for_pickup', 'printing'], // failed QC loops back
        'ready_for_pickup' => ['completed'],
    ];

    public function execute(JobOrder $jobOrder, JobOrderStatus $to): JobOrder
    {
        abort_unless(
            in_array($to->value, self::TRANSITIONS[$jobOrder->status->value] ?? [], true),
            422,
            "Cannot move from {$jobOrder->status->value} to {$to->value}"
        );
        $jobOrder->update(['status' => $to]);
        return $jobOrder;
    }
}
```

### Pattern 3: Webhook-First Payment Confirmation with Manual Reconciliation Fallback

**What:** The Cashier's POS action creates a PayMongo Payment Intent/Source and shows a pending state; the *actual* payment confirmation comes from PayMongo's signature-verified webhook hitting a public, CSRF-exempt endpoint, which dispatches a queued job to confirm the transaction. A separate manual "Reconcile" action lets Cashier/Owner mark a payment confirmed by checking PayMongo's dashboard if the webhook is delayed/lost.
**When to use:** Any async payment gateway where the client-side "success" redirect is not trustworthy as the source of truth (GCash/Maya via PayMongo specifically warn against trusting client redirects).
**Trade-offs:** Adds a queue dependency and a small window where a transaction sits in "awaiting confirmation" — acceptable for a print shop's timescales; must NOT block production/pickup on webhook latency for Cash/Bank Transfer payments, which confirm synchronously and don't touch this path at all.

```php
// routes/webhooks.php — must be excluded from CSRF (bootstrap/app.php withMiddleware exclusion)
Route::post('/webhooks/paymongo', PayMongoWebhookController::class);

// PayMongoWebhookController: verify raw-body signature FIRST, before any JSON parsing,
// then dispatch ConfirmPayMongoPaymentJob::dispatch($payload) and return 200 immediately.
```

## Data Flow

### Request Flow (typical authenticated portal action)

```
Vue page (role portal) → Inertia form submit (Wayfinder action)
    ↓
Route (role-scoped, EnsureRole middleware) → Controller
    ↓
Form Request (authorize via Policy + validate)
    ↓
Action class (mutate JobOrder/Transaction/etc., read SystemConfiguration for business-rule thresholds)
    ↓
Eloquent model save → Observer fires → AuditTrail row appended
    ↓
Controller returns Inertia::render()/redirect + flash toast
    ↓
Other role's portal picks up the change on next poll (usePoll / partial reload)
```

### Payment Confirmation Flow (async, webhook-driven)

```
Cashier POS → CreatePayMongoIntentAction → PayMongo API
    ↓ (customer pays on PayMongo-hosted page/app)
PayMongo → POST /webhooks/paymongo (signature header)
    ↓
PayMongoWebhookController: verify signature on raw body → 200 OK immediately
    ↓
ConfirmPayMongoPaymentJob (queued) → Action: mark Transaction paid, update JobOrder.payment_status,
    post to AccountsReceivable if applicable, AuditTrail row, Notification to Cashier/Frontline
```

### Job Order Lifecycle (the central data flow)

```
Customer walk-in → QueueEntry (Frontline)
    ↓
JobOrder created (Type A: auto-validated against SystemConfiguration DPI/format/size thresholds
                   Type B: routed to Artist for consultation)
    ↓ (Type B only)
Artist: consultation notes → design in TOAST UI editor → DesignFile + RevisionLog rows
    ↓
Owner-only override available; otherwise design locks on final approval
    ↓
Cashier: POS pricing (PricingDatabase) → Transaction (job_order_id NOT NULL)
    → Cash/Bank: synchronous confirm | GCash/Maya: PayMongo async | On-Credit: Owner approval → AccountsReceivable
    ↓
Production: status advances For Production → Printing → Quality Check → Ready for Pickup
    (ProductionLog rows per stage; polling notifies Frontline at Ready for Pickup)
    ↓
Frontline: pickup/completion
    ↓ (parallel, always available once JobOrder exists)
Public Tracking Portal: JO number → status only (no auth, no PII)
```

### AR / Collections Flow (branches off On-Credit)

```
On-Credit Transaction → Owner approval gate → AccountsReceivable row created
    ↓
Scheduled job (nightly): recompute aging bracket (Current/15/30/60/90+) per open AR row
    ↓
Escalating reminder Notification dispatched per bracket threshold crossed
    ↓
Accounting: collection status updates, printable collection letters (PDF), Owner-approved write-offs
```

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| Single shop, low concurrency (this project's actual scale) | Monolith + polling is correct; no queue/websocket infra beyond what Laravel Cloud provides by default |
| If order volume grows heavily (unlikely for this client) | Move report generation and AR aging sweep fully to queued jobs (already recommended above) before they become slow synchronous requests |
| If multi-branch is ever requested (explicitly out of scope now) | Would require a `location_id` on most tables and a rethink of the single-tenant assumption baked into RBAC/reporting — flag as a major re-architecture, not incremental |

### Scaling Priorities

1. **First likely friction point:** Production board and AR list views doing N+1 queries across `job_orders` → `customers`/`transactions`/`production_logs` as data grows — mitigate with eager loading (`with()`) from the start, not as a later fix.
2. **Second:** Polling frequency vs. server load if many portals poll simultaneously — keep polling intervals conservative (e.g. 10-30s) and scope `only: [...]` to the minimal props needed per page, per Inertia v3 partial reload conventions.

## Anti-Patterns

### Anti-Pattern 1: Single Shared `AppLayout` with Role-Filtered Navigation

**What people do:** Build one layout/sidebar and conditionally show/hide nav items based on `auth.user.role`, reusing the existing starter-kit `AppLayout.vue`/`AppSidebar.vue` as-is.
**Why it's wrong:** Already explicitly rejected for this project — each role needs its own dedicated portal, not a filtered subset of a shared one. It also tends to leak role-irrelevant props/routes into every page's Wayfinder bundle and makes RBAC easy to get wrong (hidden-but-not-blocked nav items).
**Do this instead:** Separate layout/page/route directories per role (as structured above), each pulling only the props and Wayfinder actions relevant to that role.

### Anti-Pattern 2: Trusting Client-Side Payment Redirect as Confirmation

**What people do:** Mark a `Transaction` paid as soon as the browser returns from PayMongo's checkout redirect.
**Why it's wrong:** The redirect can be spoofed, abandoned mid-flow, or the browser closed before it fires — the webhook is PayMongo's only authoritative signal.
**Do this instead:** Redirect shows a "payment pending confirmation" state; only the verified webhook (or manual reconciliation by staff) flips `payment_status`.

### Anti-Pattern 3: Logging Audit Events Ad-Hoc Inside Each Controller/Action

**What people do:** Sprinkle `AuditTrail::create([...])` calls manually inside every controller method that mutates something, relying on developer discipline to remember every call site.
**Why it's wrong:** Guarantees gaps — the requirement is "every mutating action," and manual call sites will always miss something (a new Action added later, a bulk update, an Owner override). It also isn't "structurally" append-only in spirit if the calls are optional/forgettable.
**Do this instead:** Hook Eloquent model events via Observers (or a shared `Auditable` trait applied to every audited model) so audit rows are a structural consequence of the mutation itself, not an opt-in step. Combine with genuinely removing update/delete code paths on the `AuditTrail` model (no `$fillable` update methods, no `SoftDeletes`, model-level guard against `update()`/`delete()` calls).

### Anti-Pattern 4: One God `JobOrderService` Class

**What people do:** Put pricing, status transitions, artist assignment, design locking, and notification-triggering all into one `JobOrderService` with a dozen public methods.
**Why it's wrong:** `job_orders` is already the central entity referenced by five other tables — a single service class covering all of its behavior becomes a dumping ground and a merge-conflict/test-fragility magnet as more roles touch it.
**Do this instead:** Split by operation into focused `Actions/` classes (per Pattern 2 above); each is independently unit-testable with Pest and has one reason to change.

### Anti-Pattern 5: Building the Public QR Tracking Route Inside an Authenticated Route Group "Temporarily"

**What people do:** Add the tracking page under the same route file/middleware stack as staff portals during early development, planning to "extract it later."
**Why it's wrong:** Easy to accidentally leave `auth` middleware attached, or to accidentally expose staff-only props (customer contact info, pricing, internal notes) through a shared controller/page component.
**Do this instead:** Keep `routes/tracking.php` and its controller/page structurally separate from day one (as structured above) — it should never share a base controller, layout, or prop set with any staff portal.

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| PayMongo (GCash/Maya) | Server-side Payment Intent/Source creation via HTTP client wrapper (`app/Services/PayMongoClient.php`); signature-verified webhook as source of truth; queued job processes webhook payload | Verify `Paymongo-Signature` header against raw request body (not parsed JSON) before any middleware re-encodes it; exclude the webhook route from CSRF; acknowledge with 200 fast, do slow work in a queued job. (MEDIUM confidence — verified against PayMongo's own webhook docs; no official Laravel SDK confirmed current, so a thin custom `Service` wrapper is safer than depending on an unmaintained community package.) |
| TOAST UI Image Editor | Frontend-only JS library mounted inside the Artist portal's design page; saved output persisted as a `DesignFile` via a normal Inertia form/file upload | Not a backend integration — treat as a component, not a service; check bundle size impact on the Artist portal's page chunk |
| PDF/Excel export | Server-side generation for reports and AR collection letters | Standard Laravel ecosystem choices: `barryvdh/laravel-dompdf` (or `spatie/laravel-pdf`) for PDF, `maatwebsite/excel` for Excel — confirm current versions against PHP 8.4/Laravel 13 compatibility before installing (LOW confidence on exact package pick — verify at implementation time, not now) |
| Laravel Cloud (queue + scheduler) | Managed queue worker for webhook processing/reminders/exports; managed scheduler for nightly AR aging sweep | No custom infra needed; confirm queue connection config (`config/queue.php`) targets Laravel Cloud's managed driver, not `sync`, before payment/AR jobs go live |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| Role portals ↔ shared domain models | Direct Eloquent access via role-scoped controllers/Actions, gated by Policies | No inter-portal HTTP calls — it's one monolith; "boundary" is authorization, not network separation |
| Staff portals ↔ public tracking portal | One-way read: tracking portal reads `job_orders.status` (and maybe a customer-facing label) only, never writes | Enforce via a dedicated read path (e.g. a `TrackingStatus` view/resource that whitelists fields) rather than exposing the full `JobOrder` model to the guest route |
| POS/Payments ↔ Accounts Receivable | One-way: On-Credit approval in POS creates an `AccountsReceivable` row; AR module never writes back to `transactions` except through its own write-off Action | Keeps AR's aging/collection logic from needing to understand POS internals beyond the initial row it received |
| Production board ↔ Notifications | Status-transition Actions dispatch a Notification; Frontline/other portals discover it via polling, not the Production portal calling Frontline directly | Keeps portals decoupled — no controller in one role's namespace ever calls into another role's controller |
| Audit Trail ↔ everything | Write-only, one-directional, via Observers | No feature code ever reads `AuditTrail` for business logic (it's a record, not a data source) — only Owner/Admin reporting reads it |

## Suggested Build Order (dependency-driven)

This is components-and-dependencies reasoning for roadmap phase sequencing, not a phase list itself:

1. **Foundation infra** — fix broken test baseline (`RefreshDatabase`), switch to MySQL, add the `role` column + `EnsureRole` middleware + Policy scaffolding, `system_configurations` table + Owner settings UI, `audit_trail` table + Observer infrastructure. Everything downstream depends on RBAC (which portal can a controller even live in) and on audit logging existing before feature code is written (retrofitting audit hooks after the fact is exactly the anti-pattern above).
2. **Portal shells (all 7 + tracking)** — empty dashboards per role wired to the right layout/middleware, proves the routing/layout convention before any real feature uses it.
3. **Customers + Queue management (Frontline)** — lowest-complexity vertical slice; exercises RBAC + audit end-to-end with minimal domain risk. No dependency on job orders yet.
4. **Job Orders core (central entity)** — Type A/B intake, `pricing_database`, DPI/format/size auto-validation reading `system_configurations`. Everything from here on references `job_orders`, so it must exist before Artist, POS, Production, or Tracking work.
5. **Artist workflow** — `design_files`, `revision_logs`, TOAST UI editor, design lock/Owner override. Depends only on step 4 (job order existing, Type A/B distinction).
6. **POS + Payments** — `transactions` (NOT NULL `job_order_id`), pricing computation, Cash/Bank synchronous path first (simplest, no external dependency), then PayMongo webhook path, then On-Credit → `accounts_receivable` row creation. Depends on step 4; PayMongo sub-piece can be built/tested somewhat independently (webhook endpoint has no portal dependency at all).
7. **Public QR Tracking Portal** — depends on step 4 only (job order + status existing and meaningful); can be built in parallel with steps 5-6 since it's read-only and structurally isolated.
8. **Production Monitoring** — `production_logs`, sequential status transitions, color-coded urgency, polling-based notifications to Frontline. Depends on step 4 (and benefits from step 6 existing so payment-gating business rules, if any, are already modeled).
9. **Accounts Receivable module proper** — aging brackets, scheduled recompute job, escalating reminders, collection letters, write-offs. Depends on step 6's On-Credit path having produced `accounts_receivable` rows to operate on.
10. **Expenses (Accounting)** — largely independent; can slot in anywhere after step 1, low priority relative to the job-order critical path.
11. **Reporting (PDF/Excel, all report types)** — deliberately last; it aggregates data from every other module (sales, production, artist performance, financial, AR), so building it before those modules exist means building against fake/incomplete data.
12. **Login hardening polish** (lockout thresholds, single active session, configurable timeout, password complexity) — the underlying `system_configurations` fields and Fortify plumbing should land in step 1; the last mile of wiring (session-kill-on-second-login, lockout UI) can be finished whenever, but don't leave it to the very end since it's security-relevant and cheap to do early.

**Critical path:** RBAC/audit infra → job_orders → (Artist ∥ POS ∥ Tracking, all parallel-safe once job_orders exists) → Production → AR → Reporting. Expenses and login-hardening polish are off the critical path and can fill gaps.

## Sources

- [Menerapkan RBAC di Laravel dengan Policy & Gate](https://medium.com/@m.farras.majid/menerapkan-role-based-access-control-rbac-di-laravel-dengan-policy-gate-71269a4d89e7) — MEDIUM confidence, corroborates native Gate/Policy approach for simple role systems
- [Spatie Permissions vs Laravel Policies and Gates](https://dev.to/cyber_aurora_/spatie-permissions-vs-laravel-policies-and-gates-handling-role-based-access-1bdn) — MEDIUM confidence, supports the "single fixed role column doesn't need a permissions package" conclusion
- [Laravel Service Pattern issues](https://nabilhassen.com/laravel-service-pattern-issues) and [Laravel Actions vs Services](https://ilyaskazi.medium.com/laravel-actions-vs-services-deep-dive-best-practices-for-scalable-architecture-part-2-05bc861c0af3) — MEDIUM confidence, informs the Actions-over-god-service recommendation
- [PayMongo Webhooks Resource (official docs)](https://docs.paymongo.com/reference/webhook-resource) and [Webhook Setup & Management](https://docs.paymongo.com/docs/developer-tools-webhook-setup-management) — HIGH confidence, official source for signature verification mechanics
- [PayMongo developer forum: Laravel/PHP webhook signature help](https://developers.paymongo.com/discuss/6655332c39aa280058b1d545) — LOW confidence, community discussion corroborating raw-body-before-parsing requirement
- Existing repo state: `.planning/codebase/ARCHITECTURE.md`, `.planning/codebase/STRUCTURE.md`, `.planning/PROJECT.md` — HIGH confidence, ground truth for current scaffold and already-decided constraints

---
*Architecture research for: multi-role internal business management system (Laravel + Inertia + Vue)*
*Researched: 2026-08-31*
