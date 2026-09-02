# Phase 4: Artist Workflow & Design Editor - Context

**Gathered:** 2026-09-02
**Status:** Ready for planning

<domain>
## Phase Boundary

An Artist takes a Type B job order (already created and auto-assigned to them by Phase 3's round-robin) from consultation through a locked, approved design. Covers JOB-03 through JOB-10: consultation notes, a TOAST UI-based design editor, revision logging with a "Send for Review" cycle, design lock on final approval (Owner-only audited override), Artist session status (On Break/End Shift) affecting auto-assignment eligibility, the Artist's own consultation queue (Next/Forward/Not-Appear), and an Artist performance report. Everything upstream (queue intake, Type A validation, Type B auto-assignment) is Phase 3's completed work — this phase never re-touches assignment logic. Everything downstream (pricing, payment, production stages) is Phase 5/6's job — this phase produces an approved, locked design and stops there.

</domain>

<decisions>
## Implementation Decisions

### Consultation & Job Order Relationship
- **D-01:** Consultation notes live directly on the existing `job_orders` record (new field(s)) — no separate consultation entity. The approved 12-table ERD has no dedicated consultation table.
- **D-02:** JOB-03's "generate a job order for a Type B customer" is imprecise wording carried from the manuscript. Phase 3 already creates and assigns the job order at intake; this phase's Artist finalizes/updates that same job order with consultation details. No new job-order-creation flow happens during consultation.

### Artist's Own Consultation Queue
- **D-03:** Next/Forward/Not-Appear (JOB-09) are implemented as new `JobOrderStatus` values, not a separate queue table — extends the same enum + `AuditObserver` pattern Phase 3 established (`Assigned` → `InConsultation`, etc.). No new table.
- **D-04:** "Next" picks the oldest `Assigned` job order for that Artist and moves it to `InConsultation`. "Forward" and "Not-Appear" both keep the job order assigned to the same Artist — no re-triggering of Phase 3's round-robin, no reassignment to another artist. Not-Appear deprioritizes the job order (flagged/statused out of the active "up next" pool) but leaves it visible for the Artist to resume manually later.

### Design Review & Approval
- **D-05:** There is no separate reviewer role or customer portal. The Artist directly records the client's in-person verdict ("Client Approved" / "Client Requested Changes") — the client is physically at the shop looking at the screen. This matches the demo's flow and the project's existing out-of-scope call on customer self-service/accounts.
- **D-06:** The design review cycle is modeled as `JobOrderStatus` values: `InDesign` → `PendingReview` → `DesignApproved`. A change request bounces status back to `InDesign`; `revision_logs` carries the history of each cycle, not the status enum.
- **D-07:** Every "Send for Review" click — including the very first submission — creates a `revision_logs` entry. One rule, no special-casing the first submission. This matches JOB-05's wording that logging the revision IS the send-for-review action.
- **D-08:** `design_files` holds a single current row per job order, overwritten on each revision. `revision_logs` is the version-history trail (what changed, when, review outcome) — there is no independently-restorable old version of the file itself. This also matches JOB-07's framing of unlocking "the design file" (singular).

### Design Editor Scope
- **D-09:** New Composer/npm dependency approved: `@toast-ui/vue-image-editor` + its `tui-image-editor` core, per JOB-04's named requirement. No image-editor library exists in the project yet.
- **D-10:** The editor's save model is a flattened export, not a persisted layered/re-editable project state (TOAST UI Image Editor doesn't natively support the latter). On open, the editor loads the current `design_files` image as its working base; "Send for Review" exports and overwrites that file. Continued editing after a change request re-opens the same flattened image as the new base.
- **D-11:** The Artist can start a Type B design from a blank canvas OR import a client-supplied reference image/logo as the starting point — both supported. TOAST UI's core use case is loading a base image, and the demo shows client-supplied assets are sometimes relevant even for Type B jobs.
- **D-12:** Exported design files are stored as PNG, using the same local-disk storage pattern already established for Type A's `job_orders.file_path` — no new storage configuration.

### Artist Session Status
- **D-13:** Phase 3's single `is_available` boolean is insufficient for JOB-08. This phase adds an `artist_status` enum (`Available` / `OnBreak` / `OffShift`) plus a `break_started_at` timestamp on `users`. `is_available` becomes derived from `artist_status` (true only when `Available`) so Phase 3's round-robin query keeps working unchanged. The enum + timestamp are what actually make On Break vs. End Shift distinguishable and make `max_artist_break_minutes` enforceable.
- **D-14:** When a break exceeds `max_artist_break_minutes`, there is no automatic state change or scheduled job — the Artist stays `OnBreak` until they manually return. Owner/Admin-facing views show a passive "exceeded break time" indicator instead. Avoids scheduler/queue-worker machinery for a self-service action the Artist already controls.
- **D-15:** Ending shift is allowed at any time, including with job orders mid-consultation or mid-design. Those job orders simply wait for the Artist's next shift — no reassignment, no handoff mechanism (consistent with D-04's Not-Appear behavior; a handoff-to-another-artist concept doesn't exist anywhere else in the requirements).
- **D-16:** JOB-10's performance report shows: jobs completed, average revisions per job, and SLA adherence, over a selectable date range. All derivable from data this phase already produces (`revision_logs` count, `job_orders` timestamps) plus Phase 1's existing `default_sla_days` system config.

### Claude's Discretion
- Exact enum case naming/string values (`InConsultation`, `InDesign`, `PendingReview`, `DesignApproved`, `Available`/`OnBreak`/`OffShift`, etc.) — follow the existing TitleCase-key/string-value convention in `app/Enums/JobOrderStatus.php` and `app/Enums/UserRole.php`.
- Exact schema/columns for `design_files` and `revision_logs` (both new tables from the approved 12-table ERD, not yet created) — follow the Eloquent model + `#[Fillable]`/`#[ObservedBy(AuditObserver::class)]` conventions Phase 2/3 established.
- Whether the "not appeared" deprioritization is a boolean flag or its own status value — data-modeling detail, not a business-rule call.
- UI layout of the Artist's own queue view, consultation-notes form, and performance report — a UI/UX call, not a business-rule call.
- Where `artist_status` transition logic lives (model method vs. small action/service class) — implementation detail, follow whatever pattern Phase 3's assignment logic used.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements & Roadmap
- `.planning/REQUIREMENTS.md` §Job Order & Design — JOB-03 through JOB-10 full requirement text
- `.planning/ROADMAP.md` §Phase 4 — goal, success criteria, depends on Phase 3

### Project-Level Context
- `.planning/PROJECT.md` §Constraints — tech stack, RBAC/portal pattern, no-websockets polling constraint
- `.planning/PROJECT.md` §Context — approved 12-table ERD (`design_files`, `revision_logs` are two of the twelve, both created fresh in this phase); manuscript-derived spec is authoritative for business rules over the client UI demo
- `.planning/PROJECT.md` §Constraints — dependency changes require approval; D-09 is that approval for this phase's TOAST UI packages specifically, not a blanket exception

### Prior Phase Context
- `.planning/phases/03-job-order-intake-auto-assignment/03-CONTEXT.md` — D-04/D-05 (the `is_available`/`last_assigned_at` forward-reference substrate this phase extends via D-13), D-06/D-07 (round-robin fairness and no-artist-available fallback, both untouched by this phase), D-08 (existing `JobOrderStatus` values `Intake`/`ValidationFailed`/`ReadyForProduction`/`Assigned` that this phase's new statuses extend)
- `.planning/phases/02-customer-queue-management/02-CONTEXT.md` — Waiting/Serving/Done queue-state precedent (referenced during discussion; this phase deliberately did NOT copy that pattern — see D-03)
- `.planning/phases/01-foundation-rbac-auth-hardening-audit-trail/01-CONTEXT.md` — audit observer registration pattern, Form Request + Validation Concern trait pairing, `Inertia::flash('toast', ...)` mutation-feedback convention, and the existing `max_artist_break_minutes`/`default_sla_days` system config keys this phase reads

### UI Reference (non-authoritative)
- `/home/user/inkspire/demo/main.js`, `/home/user/inkspire/demo/index.html` — client's original UI demo. Confirmed during this discussion as the source for the in-person/no-portal design-approval pattern (D-05) and the Next/Forward/Not-Appear queue-control naming (D-03/D-04). Per `PROJECT.md` §Context, this is a UI/interaction reference only — never a source for business rules or data model.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Enums/JobOrderStatus.php` — currently `Intake`, `ValidationFailed`, `ReadyForProduction`, `Assigned`. This phase adds `InConsultation`, `InDesign`, `PendingReview`, `DesignApproved` (exact naming at Claude's discretion).
- `app/Models/JobOrder.php` — has `assignedArtist()` relation already; this phase adds `consultation_notes` field(s), a `designFile()` relation to the new `design_files` table, and a `revisionLogs()` relation to the new `revision_logs` table.
- `database/migrations/2026_09_01_224410_add_availability_columns_to_users_table.php` — added `is_available` (boolean) + `last_assigned_at` in Phase 3; this phase adds `artist_status` (enum/string) + `break_started_at` alongside them, with `is_available` becoming derived from `artist_status`.
- `database/seeders/SystemConfigurationSeeder.php` — already seeds `max_artist_break_minutes` and `default_sla_days`; this phase reads both, adds nothing new to system config.
- `app/Observers/AuditObserver.php` — new `design_files`/`revision_logs` models get automatic audit coverage via `#[ObservedBy(AuditObserver::class)]`, same as every other domain model.
- `resources/js/pages/artist/Dashboard.vue` — current empty placeholder ("nothing here yet") this phase replaces with the real Artist portal (own queue, consultation form, design editor, session status controls, performance report).
- `routes/portals.php` — `artist` role group already exists (`role:artist` middleware, empty `dashboard` route) — this phase's new routes extend this group.

### Established Patterns
- Form Request + Validation Concern trait pair (`app/Http/Requests/**` + `app/Concerns/*ValidationRules.php`)
- `Inertia::flash('toast', [...])` for mutation feedback
- Model observer registration via `#[ObservedBy(AuditObserver::class)]` directly on the model class
- String-backed, TitleCase-key enum convention (`app/Enums/JobOrderStatus.php`, `app/Enums/UserRole.php`)
- Wayfinder-generated route/action helpers for all new routes

### Integration Points
- No `design_files` or `revision_logs` models/migrations/controllers exist yet — this phase creates both from scratch, same greenfield situation Phase 2 was in for `customers`/`queue_entries`/`job_orders`.
- No image-editor package is installed (`composer show --direct` / `package.json` confirmed clean) — D-09's `@toast-ui/vue-image-editor` + `tui-image-editor` addition is genuinely new.
- `users` table needs two additive columns (`artist_status`, `break_started_at`) — no conflicts with Phase 1's RBAC/lockout columns or Phase 3's `is_available`/`last_assigned_at`.
- `job_orders` table needs additive columns/relations for consultation notes and the new status values — no conflicts with Phase 3's `assigned_artist_id`/`validation_failure_reason`.

</code_context>

<specifics>
## Specific Ideas

- The demo's per-artist "Forward" action shows an optional reason dropdown (`ar-forward-reason-modal` in `demo/main.js`) — a reasonable UI cue for capturing why a job order was forwarded, though whether to log a reason at all is left to planning (not locked here).
- The demo's design-approval flow shows the Artist clicking a confirm dialog ("Confirm: Client Approved") rather than the client operating any UI themselves — direct visual precedent for D-05.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within Phase 4 scope (JOB-03 through JOB-10). No new-capability suggestions came up; every question was about how to implement what's already scoped.

</deferred>

---

*Phase: 4-artist-workflow-design-editor*
*Context gathered: 2026-09-02*
