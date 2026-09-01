# Phase 2: Customer & Queue Management - Context

**Gathered:** 2026-09-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Frontline Staff can search for or register a customer, generate a queue number for their visit, and turn that visit into one or more job orders (each marked Type A print-ready or Type B needs-consultation). This phase also includes a public, unauthenticated shared queue display (added mid-discussion — see Scope Change below). Covers QUEUE-01 through QUEUE-06. File upload storage for Type A happens here (unvalidated); DPI/format/size validation and Type B auto-assignment are Phase 3's job, not this phase's.

**Scope change during discussion:** QUEUE-06 (public shared queue display) was added to Phase 2 at the user's explicit request, reversing PROJECT.md's prior "Out of Scope" call on a lobby/TV display. `ROADMAP.md`, `REQUIREMENTS.md`, and `PROJECT.md` were updated in place during this discussion to reflect it — downstream agents should read the current versions of those files, not treat QUEUE-06 as absent.

</domain>

<decisions>
## Implementation Decisions

### Customer Record & Search
- **D-01:** Registration captures a full profile: name, contact number, email, and address.
- **D-02:** Contact number is enforced unique at the database level — one contact number maps to one customer record.
- **D-03:** Search matches partially (LIKE-style) on name or contact number, not exact-match-only — forgiving of typos/partial recall at the counter.
- **D-04:** Frontline Staff must search first (and get zero results) before "Register New" unlocks — reduces accidental duplicate customer records.

### Queue Number Mechanics
- **D-05:** A queue entry is stateful, not just an identifier: it moves through **Waiting → Serving → Done**. This is an internal Frontline Staff work-tracking concept, distinct from the `job_orders.status` production-stage lifecycle (Phase 3/6).
- **D-06:** Queue numbers are daily-reset sequential (e.g. `001`, `002`, ... resetting each day) — no letter prefix.
- **D-07:** The queue number is generated first (visit enters Waiting); job orders are added to that visit afterward as a separate step within the same overall intake flow (see D-11).
- **D-08:** State transitions (Waiting → Serving → Done) are manual staff actions (e.g. "Call Next" / mark Serving / mark Done) — not auto-triggered by job order creation.

### Queue Number Mechanics — Scope Change: Shared Queue Display (QUEUE-06)
- **D-09:** A public, unauthenticated route displays each queue entry's number and current status (Waiting/Serving/Done) — **status only, no customer name or other PII**, mirroring the no-PII rule already locked for the public job-order tracking page (TRACK-02).
- **D-10:** The display refreshes via client-side polling, consistent with the project-wide constraint against Laravel Echo/Reverb/websockets (`PROJECT.md` §Constraints, §Out of Scope).

### Job Order Intake Scope
- **D-11:** For Type A, Frontline Staff attaches the file at intake and it is stored — but **not validated** against DPI/format/size thresholds; that check is Phase 3's `JOB-01` responsibility. Phase 2 only needs a file input + storage column.
- **D-12:** Beyond the Type A/B flag, a job order records a free-text product/service description (e.g. "Tarpaulin, 3x5ft") — no `pricing_database` link yet (that arrives in Phase 5).
- **D-13:** A Phase 2 job order starts in a new placeholder status (e.g. "Intake"/"Pending"), distinct from the production-stage statuses (`For Production → Printing → Quality Check → Ready for Pickup`) that Phase 3/6 introduce and drive.

### Multi-Job-Order Visit Flow
- **D-14:** Adding job orders to a visit is one combined form: the queue number is generated and one or more job order rows (repeatable, "Add another") are submitted together in a single save — not a generate-then-separately-add-each-one flow.
- **D-15:** There is no hard limit on job orders per visit, and job orders can still be added to a visit even after it's marked Done — "Done" does not lock the visit closed.

### Claude's Discretion
- Exact wording/enum values for the queue-entry status column (`waiting`/`serving`/`done` vs similar) and the job order placeholder status (`intake`/`pending` vs similar) — pick during planning, following the project's existing enum conventions (see `app/Enums/UserRole.php` for the TitleCase-case/string-value pattern).
- Exact shape of the "Add another job order" repeatable-row UI (inline table vs stacked cards) — a UI/UX call, not a business-rule call.
- Audit trail coverage for the new `Customer`, `QueueEntry`, and `JobOrder` models — apply the existing `AuditObserver` registration pattern from Phase 1 (see Code Context below); no new discussion needed, it's an established pattern.

### Resolved During Planning (Research Follow-ups)
- **D-16 (timezone):** The shop's business day for the daily queue-reset boundary is `Asia/Manila`, scoped narrowly to the queue-number generation logic only (e.g. `now()->timezone('Asia/Manila')->toDateString()`). `config('app.timezone')` stays `UTC` project-wide — do not change it globally.
- **D-17 (queue counter schema):** No new table. Generate the queue number via `DB::transaction()` + `lockForUpdate()` over an indexed `queue_date` column on `queue_entries` itself (Research Pattern 1), staying within the approved 12-table ERD.
- **D-18 (D-15 UI scope):** Phase 2 builds a dedicated "add job order to this visit" action for an *existing* queue entry (beyond the initial combined intake form), so D-15's "can still add job orders after Done" guarantee is actually usable, not just a data-model statement.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements & Roadmap
- `.planning/REQUIREMENTS.md` §Customer & Queue — QUEUE-01 through QUEUE-06 full requirement text (QUEUE-06 added during this discussion)
- `.planning/ROADMAP.md` §Phase 2 — goal, success criteria (4 criteria, including the QUEUE-06 shared-display criterion added during this discussion)

### Project-Level Context
- `.planning/PROJECT.md` §Constraints — RBAC/portal pattern, no-websockets polling constraint, tech stack
- `.planning/PROJECT.md` §Key Decisions — the shared-queue-display scope reversal entry (added during this discussion), and the pre-existing `job_orders` status/payment_status split and `system_configurations` deviation entries for background
- `.planning/PROJECT.md` §Context — the approved 12-table ERD (`customers`, `queue_entries`, `job_orders` are three of the twelve); manuscript-derived spec is authoritative for business rules over the client UI demo

### Prior Phase Context
- `.planning/phases/01-foundation-rbac-auth-hardening-audit-trail/01-CONTEXT.md` — audit trail observer pattern (D-01 there: models opt into a shared observer, no per-controller boilerplate), Form Request + Validation Concern trait pairing, `Inertia::flash('toast', ...)` mutation-feedback convention — all apply directly to the new `Customer`/`QueueEntry`/`JobOrder` models and their controllers

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Observers/AuditObserver.php` + `app/Support/AuditLogger.php` — existing audit-logging mechanism from Phase 1; new models (`Customer`, `QueueEntry`, `JobOrder`) should register this observer the same way `User` does, for automatic audit coverage
- `app/Enums/UserRole.php` — existing enum pattern (string-backed, TitleCase keys) to follow for any new status enums (queue state, job order intake status)
- `routes/portals.php` — `frontline-staff` role group already exists (`Route::middleware(['auth', 'role:frontline_staff'])->prefix('frontline-staff')->name('frontline-staff.')`) with an empty `dashboard` route; new Phase 2 routes (customer search, registration, queue, job order intake) extend this group
- `resources/js/pages/frontline-staff/Dashboard.vue` — current empty placeholder page ("nothing here yet") that Phase 2 replaces with real functionality
- `app/Http/Requests/Owner/FilterAuditTrailRequest.php` and sibling FormRequest classes — establishes the Form Request + Validation Concern trait pattern to follow for customer/queue/job-order validation

### Established Patterns
- Form Request + Validation Concern trait pair (`app/Http/Requests/**` + `app/Concerns/*ValidationRules.php`)
- `Inertia::flash('toast', [...])` for mutation feedback
- Wayfinder-generated route/action helpers — all new routes must go through this, not hardcoded URLs
- Model observer registration (likely in `AppServiceProvider` or a dedicated provider — check where `User::observe(AuditObserver::class)` is currently registered) for new domain models

### Integration Points
- No `Customer`, `QueueEntry`, or `JobOrder` models, migrations, or controllers exist yet — this phase creates all three from scratch (genuinely greenfield within an otherwise-scaffolded app)
- The public shared queue display (QUEUE-06) needs a new unauthenticated route, likely alongside where the Phase 6 public tracking portal will eventually live — check `routes/web.php` for the pattern used by other public/guest routes
- `demo/queue-display.html` and `demo/index.html` exist in the repo as the client's original UI reference for both the queue display and customer registration/search flow — useful for UI/visual cues only, **not** a source for business rules or data model (per `PROJECT.md` §Context, this was already explicitly decided for the whole project)

</code_context>

<specifics>
## Specific Ideas

- The demo's "Queue ID" ticket format (`demo/index.html`) shows a boxed queue number + sub-label after registration — a reasonable visual reference for the confirmation state of the combined intake form (D-14), though exact styling is a UI/UX call, not locked here.
- The demo's `queue-display.html` is a direct visual precedent for QUEUE-06's shared display — worth opening during UI design, remembering it's a style reference only (see Code Context note on the demo).

</specifics>

<deferred>
## Deferred Ideas

None — the one scope-expansion idea raised (shared queue display) was folded into this phase as QUEUE-06 rather than deferred, per explicit user decision. No other out-of-domain ideas came up during discussion.

</deferred>

---

*Phase: 2-customer-queue-management*
*Context gathered: 2026-09-01*
</code_context>
