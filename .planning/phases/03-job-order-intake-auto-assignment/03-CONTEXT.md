# Phase 3: Job Order Intake & Auto-Assignment - Context

**Gathered:** 2026-09-02
**Status:** Ready for planning

<domain>
## Phase Boundary

A queued job order (created unvalidated in Phase 2) becomes a real, production-ready record. Type A jobs get their attached file auto-validated against configured DPI/format/size thresholds. Type B jobs auto-assign to an available Artist via round-robin — no manual assignment step. Covers JOB-01 and JOB-02 only. Everything downstream of assignment/validation (consultation notes, the design editor, revision logging, design lock, Artist session controls, Artist performance reports — JOB-03 through JOB-10) is Phase 4's job, not this phase's.

</domain>

<decisions>
## Implementation Decisions

### Type A File Validation

- **D-01:** DPI validation is raster-only. `jpg`/`png` get real DPI validation via PHP's built-in `getimagesize()`/`exif_read_data()` — no new dependency. `pdf`/`ai`/`eps` skip the DPI check entirely and are validated on format + size only. Chosen specifically to sidestep the unverified Ghostscript/Imagick-on-Laravel-Cloud risk flagged in `STATE.md`, and because DPI isn't a meaningful concept for vector content until it's rasterized.
- **D-02:** When a Type A file fails validation (DPI too low, bad format, over size), the job order moves to a dedicated `ValidationFailed` status with the specific failure reason shown. Frontline Staff must replace the file before it can proceed. It stays Type A — a failed scan does not pull an artist into the loop.
- **D-03:** Validation runs synchronously, during the same request that (per Phase 2) already stores the file — no queued job, no worker dependency. Justified by D-01: raster DPI reads are near-instant, and vector formats skip DPI entirely, so there's no slow Ghostscript step in the critical path.

### Artist Availability Data

- **D-04:** JOB-02 requires routing only to artists who are "clocked in and not on break," but that toggle is Phase 4's JOB-08, not built yet. Phase 3 adds the availability substrate now — same forward-reference pattern Phase 2 used when it stored an unvalidated file for Phase 3 to check. Round-robin reads this substrate starting in this phase; Phase 4 builds the On Break/End Shift controls that flip it.
- **D-05:** The substrate is a single boolean (e.g. `is_available`) on `users`, defaulting `true` for Artist-role users. Whether Phase 4's On Break and End Shift both toggle this same boolean, or need finer-grained state, is Phase 4's decision to make later.

### Round-Robin & No-Artist Fallback

- **D-06:** Fairness is tracked via a `last_assigned_at` timestamp on `users`. Each auto-assignment picks the available Artist with the oldest (or null) `last_assigned_at`, then stamps it to now. Self-correcting if an artist is skipped for a stretch — no separate pointer/counter to keep in sync.
- **D-07:** When zero artists are available, the Type B job order stays unassigned (no fallback to "any artist regardless of availability"). The next artist who becomes available triggers a check for unassigned Type B jobs and claims the oldest one — piggybacks on the availability-change moment rather than a polling loop.

### Post-Validation/Assignment Status

- **D-08:** `JobOrderStatus` currently has only `Intake`. This phase adds two resting-state values reached after Phase 3 processing (not final states — Phase 5 payment and Phase 6's production board haven't been built yet): a Type A job that passes validation moves to `ReadyForProduction`; a Type B job that gets auto-assigned moves to `Assigned`.
- **D-09:** `ValidationFailed` (D-02) is its own `JobOrderStatus` enum value, not a boolean/reason flag layered on top of `Intake` — consistent with every other job-order state being modeled as a status, and directly filterable/reportable.

### Claude's Discretion

- Exact enum case naming/string values (`ValidationFailed`, `ReadyForProduction`, `Assigned`, `is_available` vs. similar) — follow the existing TitleCase-key/string-value convention in `app/Enums/JobOrderStatus.php` and `app/Enums/UserRole.php`.
- Whether the assigned artist is tracked via a foreign key directly on `job_orders` (e.g. `assigned_artist_id`) vs. a separate table — a direct FK is the natural fit given the approved 12-table ERD has no dedicated assignment table, but this is a data-modeling call, not a business-rule call.
- Where the "claim oldest unassigned job" check (D-07) lives — inline in the availability-flip action vs. a small dedicated action/service class — implementation detail.
- Exact Form Request rules for the raster DPI check and the vector format/size check (both read thresholds from `system_configurations`, not hardcoded) — follow the existing Form Request + Validation Concern trait pattern.

</decisions>

<canonical_refs>

## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements & Roadmap

- `.planning/REQUIREMENTS.md` §Job Order & Design — JOB-01, JOB-02 full requirement text (JOB-03 through JOB-10 belong to Phase 4, out of this phase's scope)
- `.planning/ROADMAP.md` §Phase 3 — goal, success criteria, depends on Phase 2

### Project-Level Context

- `.planning/PROJECT.md` §Constraints — tech stack, RBAC/portal pattern, no-websockets polling constraint
- `.planning/PROJECT.md` §Context — approved 12-table ERD; `job_orders` status/payment_status column split; `system_configurations` 13th-table deviation
- `.planning/PROJECT.md` §Key Decisions — the `job_orders` status/payment_status split entry (this phase is the first to populate real `status` values beyond the Phase 2 placeholder)

### Prior Phase Context

- `.planning/phases/02-customer-queue-management/02-CONTEXT.md` — D-11 (Type A file stored unvalidated at intake; this phase performs the actual validation), D-12 (job order fields, no pricing link until Phase 5), D-13 (the "Intake" placeholder status this phase's new statuses build on top of)
- `.planning/phases/01-foundation-rbac-auth-hardening-audit-trail/01-CONTEXT.md` — audit observer registration pattern, Form Request + Validation Concern trait pairing, `Inertia::flash('toast', ...)` mutation-feedback convention

### State & Blockers

- `.planning/STATE.md` §Blockers/Concerns — flagged Ghostscript/Imagick availability on Laravel Cloud as unverified for vector-format DPI reads. This discussion resolved it via D-01 (raster-only DPI scope) rather than requiring infra verification — the blocker no longer applies to this phase's locked scope.

</canonical_refs>

<code_context>

## Existing Code Insights

### Reusable Assets

- `app/Concerns/JobOrderValidationRules.php` — existing Form Request validation trait for job order fields (currently `'file' => ['nullable', 'file', 'required_if:type,type_a']`); this phase extends it with the DPI/format/size rules from D-01
- `app/Http/Controllers/FrontlineStaff/QueueEntryController.php` — stores Type A files unvalidated (`$request->file('file')?->store('job-orders', 'local')`, both single and repeatable-row paths); this phase's validation logic runs in this same intake flow per D-03
- `app/Models/SystemConfiguration.php` + `database/seeders/SystemConfigurationSeeder.php` — already seeds `dpi_threshold_minimum` (300), `accepted_file_formats` (`pdf`,`ai`,`eps`,`jpg`,`png`), `max_file_size_mb` (50) — this phase reads these, not hardcoded thresholds
- `app/Observers/AuditObserver.php` — `JobOrder` already carries `#[ObservedBy(AuditObserver::class)]`; new status transitions are automatically audit-covered with no extra wiring
- `app/Enums/JobOrderStatus.php`, `app/Enums/JobOrderType.php`, `app/Enums/UserRole.php` — string-backed, TitleCase-key enum convention to extend for the new status values

### Established Patterns

- Form Request + Validation Concern trait pair (`app/Http/Requests/**` + `app/Concerns/*ValidationRules.php`)
- `Inertia::flash('toast', [...])` for mutation feedback
- Model observer registration via the `#[ObservedBy(AuditObserver::class)]` attribute directly on the model class (not a central provider list)

### Integration Points

- `job_orders` table (`database/migrations/2026_09_01_154403_create_job_orders_table.php`) has no artist-assignment column yet — this phase's migration adds one (see Claude's Discretion)
- `users` table needs two new additive columns for the availability substrate (`is_available`) and round-robin fairness (`last_assigned_at`) — no conflicts with existing RBAC/lockout columns from Phase 1
- `routes/portals.php` — the `artist` role group scaffold exists from Phase 1 (empty dashboard, `role:artist` middleware) but has no real routes yet; this phase writes assignment data but does not need to build artist-facing UI (that's Phase 4/JOB-09)
- No image-processing Composer package is installed (`intervention/image` appears only as another package's optional suggestion, not a direct dependency in `composer.json`). Imagick/GD/exif PHP extensions are present in the local environment, but per D-01 this phase's raster-only approach uses PHP's built-in `getimagesize()`/`exif_read_data()`, requiring no new dependency and no approval-gated composer change

</code_context>

<specifics>
## Specific Ideas

No specific UI/UX references beyond what's captured in Decisions above — this phase is backend-heavy (validation logic, assignment logic) with no dedicated design discussion.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope. No scope-expansion ideas came up (all four discussed areas were implementation decisions for JOB-01/JOB-02, not new capabilities).

</deferred>

---

_Phase: 3-job-order-intake-auto-assignment_
_Context gathered: 2026-09-02_
