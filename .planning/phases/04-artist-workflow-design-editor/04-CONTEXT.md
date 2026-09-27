# Phase 4: Artist Workflow & Design Editor - Context

**Gathered:** 2026-09-02
**Updated:** 2026-09-03 — post-UAT scope expansion (client remote review, PSD import)
**Status:** Ready for planning

<domain>
## Phase Boundary

An Artist takes a Type B job order (already created and auto-assigned to them by Phase 3's round-robin) from consultation through a locked, approved design. Covers JOB-03 through JOB-10: consultation notes, a TOAST UI-based design editor, revision logging with a "Send for Review" cycle, design lock on final approval (Owner-only audited override), Artist session status (On Break/End Shift) affecting auto-assignment eligibility, the Artist's own consultation queue (Next/Forward/Not-Appear), and an Artist performance report. Everything upstream (queue intake, Type A validation, Type B auto-assignment) is Phase 3's completed work — this phase never re-touches assignment logic. Everything downstream (pricing, payment, production stages) is Phase 5/6's job — this phase produces an approved, locked design and stops there.

**2026-09-03 expansion (explicit scope override):** After Phase 4 shipped and went through human UAT, the user requested two additions that fall outside the phase's original ROADMAP.md goal (which is strictly artist-side, in-person). This is acknowledged scope creep — the standard move would be a separate phase — but the user explicitly chose to force both into Phase 4 rather than split them out. See D-17 through D-23.

1. A client can remotely Approve / Request Changes on a pending design via an emailed link, alongside the existing in-person path (supersedes D-05's "no customer portal, ever" framing, not its in-person mechanism).
2. An Artist can import a `.psd` file as a design's starting point (extends D-11's "blank canvas or reference image" starting points).

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

- **D-09:** New npm dependency approved: `tui-image-editor` + `tui-color-picker` core packages (TOAST UI Image Editor), per JOB-04's named requirement. No image-editor library exists in the project yet. **Amended 2026-09-02 during research:** `@toast-ui/vue-image-editor` (the official Vue wrapper) is Vue 2-only (`peerDependencies: vue ^2.6.14`), unmaintained since 2022, with an open/unresolved Vue 3 support request (GitHub issue #788). Confirmed with user: do NOT install the wrapper — use the framework-agnostic `tui-image-editor` core class directly, wrapped in a hand-written Vue 3 Composition API component. See `04-RESEARCH.md` Assumptions Log A1 and Pattern 4.
- **D-10:** The editor's save model is a flattened export, not a persisted layered/re-editable project state (TOAST UI Image Editor doesn't natively support the latter). On open, the editor loads the current `design_files` image as its working base; "Send for Review" exports and overwrites that file. Continued editing after a change request re-opens the same flattened image as the new base.
- **D-11:** The Artist can start a Type B design from a blank canvas OR import a client-supplied reference image/logo as the starting point — both supported. TOAST UI's core use case is loading a base image, and the demo shows client-supplied assets are sometimes relevant even for Type B jobs.
- **D-12:** Exported design files are stored as PNG, using the same local-disk storage pattern already established for Type A's `job_orders.file_path` — no new storage configuration.

### Artist Session Status

- **D-13:** Phase 3's single `is_available` boolean is insufficient for JOB-08. This phase adds an `artist_status` enum (`Available` / `OnBreak` / `OffShift`) plus a `break_started_at` timestamp on `users`. `is_available` becomes derived from `artist_status` (true only when `Available`) so Phase 3's round-robin query keeps working unchanged. The enum + timestamp are what actually make On Break vs. End Shift distinguishable and make `max_artist_break_minutes` enforceable.
- **D-14:** When a break exceeds `max_artist_break_minutes`, there is no automatic state change or scheduled job — the Artist stays `OnBreak` until they manually return. Owner/Admin-facing views show a passive "exceeded break time" indicator instead. Avoids scheduler/queue-worker machinery for a self-service action the Artist already controls.
- **D-15:** Ending shift is allowed at any time, including with job orders mid-consultation or mid-design. Those job orders simply wait for the Artist's next shift — no reassignment, no handoff mechanism (consistent with D-04's Not-Appear behavior; a handoff-to-another-artist concept doesn't exist anywhere else in the requirements).
- **D-16:** JOB-10's performance report shows: jobs completed, average revisions per job, and SLA adherence, over a selectable date range. All derivable from data this phase already produces (`revision_logs` count, `job_orders` timestamps) plus Phase 1's existing `default_sla_days` system config.

### Client Remote Design Review (2026-09-03 expansion)

- **D-17:** D-05's "no separate reviewer role or customer portal" is deliberately superseded — the client can now also review remotely. This does NOT change the in-person mechanism: `DesignEditorController::approve()`/`requestChanges()` and their `assigned_artist_id`/`PendingReview` guards stay exactly as they are (D-05/D-06 unchanged for the Artist-side path). This adds a second, unauthenticated caller reaching the same two outcomes — not a rewrite of the review model.
- **D-18:** Delivery channel is **email only** (not SMS) via **Resend**. `config/services.php` already stubs a `resend` key; this decision is the approval to add the `resend/resend-php` Composer package and set `MAIL_MAILER=resend` / `RESEND_API_KEY` for this feature specifically — not a blanket dependency exception. This is the first outbound email the app sends (no existing `app/Mail` classes to follow as precedent — greenfield).
- **D-19:** The review link is a Laravel `URL::temporarySignedRoute()`, not a stored token column — no new schema for the link itself. Valid 7 days, minted per `revision_logs` row (the specific revision being reviewed). Sending a new revision (the D-06 bounce-back after "Client Requested Changes") naturally makes the old link stale — visiting it should read "This design has changed — check your latest email," not act on outdated content.
- **D-20:** Race handling: first verdict wins. The existing `abort_unless($jobOrder->status === PendingReview, 422, ...)` guard already rejects a second attempt (whether in-person or remote comes second) — the remote page renders "Already reviewed" instead of surfacing a 422, mirroring this session's bug-2 fix (graceful 422 handling) applied to the new public page.
- **D-21:** The client's remote page shows the flattened design image, the job order description, and two buttons — Approve / Request Changes. No free-text comment field (considered and explicitly declined — see Deferred Ideas). Writes the same `revision_logs.outcome` values (`approved` / `changes_requested`) the in-person path already writes; only the caller/controller differs.

### PSD Import (2026-09-03 expansion)

- **D-22:** `.psd` files are parsed and flattened entirely **client-side** via the `ag-psd` npm package (new dependency, approved here) inside `ToastImageEditor.vue`'s existing import flow. Explicitly chosen over a server-side Imagick path, even though Imagick's PSD delegate was confirmed working on this box (`php -r '(new Imagick())->queryFormats("PSD")'` → `[PSD]`) — user's call, not a technical constraint.
- **D-23:** Extends the existing "Import Reference Image" file input (D-11) to also accept `.psd` — no separate button. If `ag-psd` fails to parse a file (corrupt, unsupported feature, oversized), fail loud with a specific message ("Couldn't read this PSD — try exporting a flattened PNG/JPG from Photoshop") rather than silently falling back to a blank/broken canvas — same failure-mode class as this session's bug 1/3, deliberately avoided this time.

### Claude's Discretion

- Exact enum case naming/string values (`InConsultation`, `InDesign`, `PendingReview`, `DesignApproved`, `Available`/`OnBreak`/`OffShift`, etc.) — follow the existing TitleCase-key/string-value convention in `app/Enums/JobOrderStatus.php` and `app/Enums/UserRole.php`.
- Exact schema/columns for `design_files` and `revision_logs` (both new tables from the approved 12-table ERD, not yet created) — follow the Eloquent model + `#[Fillable]`/`#[ObservedBy(AuditObserver::class)]` conventions Phase 2/3 established.
- Whether the "not appeared" deprioritization is a boolean flag or its own status value — data-modeling detail, not a business-rule call.
- UI layout of the Artist's own queue view, consultation-notes form, and performance report — a UI/UX call, not a business-rule call.
- Where `artist_status` transition logic lives (model method vs. small action/service class) — implementation detail, follow whatever pattern Phase 3's assignment logic used.
- Where the new public review route lives (a new `routes/public.php`-style group vs. inline in `routes/portals.php` outside any `role:*` group) — routing organization detail, not a business-rule call.
- Exact Mailable class name/structure/subject line for the review-link email — follows whatever's idiomatic for Laravel 13, no existing precedent in this app to match.
- Exact wording of "link expired," "already reviewed," and "PSD parse failed" user-facing messages.

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

### Remote Review / PSD Import (2026-09-03 expansion)

- `.planning/REQUIREMENTS.md` line 98 — NOTF-01 ("Customer receives SMS/email notification... currently pull-based QR tracking only, no push") — the remote-review email overlaps this logged gap but is not the same requirement (NOTF-01 is pickup-ready notification; this is design-review notification).
- `.planning/REQUIREMENTS.md` §Out of Scope, line 115 — "Quote-to-order workflow, customer self-service ordering" is locked Out of Scope; the client remote-review page is adjacent to but distinct from this (reviewing an existing design, not self-service ordering) — noted for downstream awareness, not a conflict requiring resolution.
- `config/services.php` — already stubs a `resend` key (`env('RESEND_API_KEY')`); D-18 activates it.
- `app/Models/Customer.php` — has `email` (fillable) — the Mailable's recipient.

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
- No `app/Mail` classes exist yet anywhere in the app — the review-link email is the first outbound mail this app sends.
- All existing routes in `routes/portals.php` sit behind a `role:*` middleware group; the new public review route needs to live outside any of them (unauthenticated, signed-URL-protected instead).
- `Imagick` (`ext-imagick`) is loaded and its PSD delegate works (confirmed live on this box) — available if a future phase wants server-side PSD handling, though D-22 chose client-side (`ag-psd`) for this pass.
- No SMS gateway package/config exists anywhere in the project — SMS remains genuinely unbuilt, not just unconfigured.

</code_context>

<specifics>
## Specific Ideas

- The demo's per-artist "Forward" action shows an optional reason dropdown (`ar-forward-reason-modal` in `demo/main.js`) — a reasonable UI cue for capturing why a job order was forwarded, though whether to log a reason at all is left to planning (not locked here).
- The demo's design-approval flow shows the Artist clicking a confirm dialog ("Confirm: Client Approved") rather than the client operating any UI themselves — direct visual precedent for D-05.

</specifics>

<deferred>
## Deferred Ideas

None from the original 2026-09-02 discussion — it stayed within Phase 4 scope (JOB-03 through JOB-10).

**From the 2026-09-03 expansion discussion:**

- **SMS delivery** for the remote review link — no SMS gateway exists in this project; email-only (D-18) ships first. Add SMS as a later enhancement if email-only proves insufficient — would need a provider decision (e.g. Semaphore for PH numbers) and a new integration, same weight as this session's Resend addition.
- **Free-text comment field** on the client's "Request Changes" remote-review action — considered during discussion (D-21), explicitly declined for this pass to match the in-person path's existing behavior (which also carries no reason today). Worth revisiting for both paths together if artists report not knowing what to change.

</deferred>

---

_Phase: 4-artist-workflow-design-editor_
_Context gathered: 2026-09-02, updated 2026-09-03_
