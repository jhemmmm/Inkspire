# Phase 4: Artist Workflow & Design Editor - Research

**Researched:** 2026-09-02
**Domain:** Laravel/Inertia/Vue 3 status-machine workflow + third-party canvas image editor integration
**Confidence:** MEDIUM (HIGH on established codebase patterns; MEDIUM on TOAST UI integration — verified against source/npm/GitHub but zero prior in-repo precedent)

## Summary

This phase extends the existing `JobOrderStatus` enum and audit-observer conventions Phase 1-3 already established — that part is low-risk, high-confidence mechanical work. The genuine unknown is D-09's TOAST UI Image Editor dependency: **`@toast-ui/vue-image-editor` is a Vue 2-only wrapper (`peerDependencies: {"vue": "^2.6.14"}`), last published 2022-04-07, with an open, unresolved "Vue 3 support" GitHub issue (#788, filed June 2022, still open, no timeline).** Installing it in this Vue 3.5 project will either fail peer-dependency resolution or silently do nothing useful. The correct integration path — confirmed against the framework-agnostic `tui-image-editor` core package's source (v3.15.3, unpacked and inspected directly) — is to **skip the Vue wrapper entirely** and drive the vanilla `ImageEditor` class from a Vue 3 component using `ref` + `onMounted`/`onBeforeUnmount`, the same pattern used for any canvas/DOM-heavy vanilla-JS library wrapped in Composition API. The core library itself is framework-agnostic (built on `fabric@^4.x`), ships as CJS-only (no ESM `module` field, no TypeScript types), and needs two separate CSS imports (`tui-image-editor/dist/tui-image-editor.css` + `tui-color-picker/dist/tui-color-picker.css`) — both self-contained (icons are base64-embedded, no external asset-path configuration needed for Vite).

Everything else in this phase — the new `JobOrderStatus` values, the `artist_status` enum, `design_files`/`revision_logs` tables with `AuditObserver` coverage, and the Owner-only unlock override — has a direct precedent already in the codebase (`app/Enums/JobOrderStatus.php`, `app/Policies/UserPolicy.php` + `DeactivateUserRequest`, `app/Actions/JobOrder/AssignArtistToJobOrder.php`). Follow those patterns verbatim; do not invent new architectural shapes for them.

**Primary recommendation:** Treat the TOAST UI integration as its own isolated vertical slice with a Wave-0 spike task (mount the editor, load a blank canvas, export a `toDataURL()`, confirm `npm run types:check`/`vp check` pass with a hand-written `.d.ts` shim) before building the full "Send for Review" flow on top of it — the risk here is integration friction (types, CJS interop, CSS imports), not business logic.

## Architectural Responsibility Map

| Capability                                            | Primary Tier     | Secondary Tier   | Rationale                                                                                                                                               |
| ----------------------------------------------------- | ---------------- | ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Consultation notes capture                            | API / Backend    | Browser / Client | Form Request + Eloquent update on `job_orders`; Vue form is presentation only                                                                           |
| Artist queue controls (Next/Forward/Not-Appear)       | API / Backend    | Browser / Client | Status-transition business rules (ordering, eligibility) must live server-side; client only triggers actions and renders the list                       |
| TOAST UI design editor (canvas editing)               | Browser / Client | —                | Canvas manipulation, filters, shapes are inherently client-side (fabric.js/canvas API); server never touches pixel data mid-edit                        |
| Design export → file persistence                      | API / Backend    | Browser / Client | Client produces the PNG via `toDataURL()`; server validates, stores to disk, and is the sole writer of `design_files`                                   |
| Revision logging / Send for Review                    | API / Backend    | —                | `revision_logs` row creation is a server-side side effect of the export request, not a client concern                                                   |
| Client verdict recording (Approved/Requested Changes) | API / Backend    | Browser / Client | Same job-order status-machine transition as the rest of JobOrderStatus; Artist's UI is a thin trigger                                                   |
| Design lock enforcement                               | API / Backend    | Browser / Client | Server is the authority on `DesignApproved` → read-only; client should also disable editor controls, but that's UX polish, not the enforcement boundary |
| Owner-only unlock override                            | API / Backend    | —                | Policy-gated Eloquent mutation + automatic `AuditObserver` coverage; no client-side logic beyond a button visible only to Owner                         |
| Artist session status (On Break/End Shift)            | API / Backend    | Browser / Client | `artist_status`/`break_started_at` mutation and its effect on round-robin eligibility (`is_available` derivation) is server logic                       |
| Performance report                                    | API / Backend    | Browser / Client | Aggregation query (jobs completed, avg revisions, SLA adherence) computed server-side; Vue renders the numbers                                          |

<user_constraints>

## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01:** Consultation notes live directly on the existing `job_orders` record (new field(s)) — no separate consultation entity. The approved 12-table ERD has no dedicated consultation table.
- **D-02:** JOB-03's "generate a job order for a Type B customer" is imprecise wording carried from the manuscript. Phase 3 already creates and assigns the job order at intake; this phase's Artist finalizes/updates that same job order with consultation details. No new job-order-creation flow happens during consultation.
- **D-03:** Next/Forward/Not-Appear (JOB-09) are implemented as new `JobOrderStatus` values, not a separate queue table — extends the same enum + `AuditObserver` pattern Phase 3 established (`Assigned` → `InConsultation`, etc.). No new table.
- **D-04:** "Next" picks the oldest `Assigned` job order for that Artist and moves it to `InConsultation`. "Forward" and "Not-Appear" both keep the job order assigned to the same Artist — no re-triggering of Phase 3's round-robin, no reassignment to another artist. Not-Appear deprioritizes the job order (flagged/statused out of the active "up next" pool) but leaves it visible for the Artist to resume manually later.
- **D-05:** There is no separate reviewer role or customer portal. The Artist directly records the client's in-person verdict ("Client Approved" / "Client Requested Changes") — the client is physically at the shop looking at the screen.
- **D-06:** The design review cycle is modeled as `JobOrderStatus` values: `InDesign` → `PendingReview` → `DesignApproved`. A change request bounces status back to `InDesign`; `revision_logs` carries the history of each cycle, not the status enum.
- **D-07:** Every "Send for Review" click — including the very first submission — creates a `revision_logs` entry. One rule, no special-casing the first submission.
- **D-08:** `design_files` holds a single current row per job order, overwritten on each revision. `revision_logs` is the version-history trail — there is no independently-restorable old version of the file itself.
- **D-09:** New Composer/npm dependency approved: `@toast-ui/vue-image-editor` + its `tui-image-editor` core, per JOB-04's named requirement. No image-editor library exists in the project yet.
- **D-10:** The editor's save model is a flattened export, not a persisted layered/re-editable project state. On open, the editor loads the current `design_files` image as its working base; "Send for Review" exports and overwrites that file. Continued editing after a change request re-opens the same flattened image as the new base.
- **D-11:** The Artist can start a Type B design from a blank canvas OR import a client-supplied reference image/logo as the starting point — both supported.
- **D-12:** Exported design files are stored as PNG, using the same local-disk storage pattern already established for Type A's `job_orders.file_path` — no new storage configuration.
- **D-13:** Phase 3's single `is_available` boolean is insufficient for JOB-08. This phase adds an `artist_status` enum (`Available`/`OnBreak`/`OffShift`) plus a `break_started_at` timestamp on `users`. `is_available` becomes derived from `artist_status` (true only when `Available`).
- **D-14:** When a break exceeds `max_artist_break_minutes`, there is no automatic state change or scheduled job — Artist stays `OnBreak` until manual return. Owner/Admin-facing views show a passive "exceeded break time" indicator instead.
- **D-15:** Ending shift is allowed at any time, including with job orders mid-consultation or mid-design. Those job orders simply wait for the Artist's next shift — no reassignment, no handoff mechanism.
- **D-16:** JOB-10's performance report shows: jobs completed, average revisions per job, and SLA adherence, over a selectable date range. Derivable from `revision_logs` count, `job_orders` timestamps, plus `default_sla_days` system config.

### Claude's Discretion

- Exact enum case naming/string values (`InConsultation`, `InDesign`, `PendingReview`, `DesignApproved`, `Available`/`OnBreak`/`OffShift`, etc.) — follow the existing TitleCase-key/string-value convention in `app/Enums/JobOrderStatus.php` and `app/Enums/UserRole.php`.
- Exact schema/columns for `design_files` and `revision_logs` (both new tables from the approved 12-table ERD) — follow the Eloquent model + `#[Fillable]`/`#[ObservedBy(AuditObserver::class)]` conventions Phase 2/3 established.
- Whether the "not appeared" deprioritization is a boolean flag or its own status value — data-modeling detail, not a business-rule call.
- UI layout of the Artist's own queue view, consultation-notes form, and performance report.
- Where `artist_status` transition logic lives (model method vs. small action/service class) — follow whatever pattern Phase 3's assignment logic used (`app/Actions/JobOrder/AssignArtistToJobOrder.php`).

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within Phase 4 scope (JOB-03 through JOB-10). No new-capability suggestions came up.
</user_constraints>

<phase_requirements>

## Phase Requirements

| ID                    | Description                                                                                                | Research Support                                                                                                                                                                                                             |
| --------------------- | ---------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| JOB-03                | Artist can record consultation notes and generate a job order for a Type B customer                        | D-01/D-02 clarify: update existing `job_orders` row, no new creation flow. Follows `ReplaceJobOrderFileRequest`/Form Request pattern. See Code Examples §1.                                                                  |
| JOB-04                | Artist can create and edit a design using the built-in TOAST UI-based image editor                         | Core finding of this research: `@toast-ui/vue-image-editor` is Vue-2-only and unmaintained; integrate `tui-image-editor` core directly via Composition API wrapper. See Common Pitfalls §1-4 and Code Examples §2-4.         |
| JOB-05                | Artist can log a design revision and submit it for review ("Send for Review")                              | `toDataURL()` export → `useForm()` with a `File` field → multipart POST → `design_files` overwrite + `revision_logs` insert in one transaction. See Code Examples §5.                                                        |
| JOB-06                | A design file becomes read-only (locked) once its job order reaches final approval                         | `JobOrderStatus::DesignApproved` gate — both a server-side write-guard and client-side editor-disable. See Architecture Patterns §2.                                                                                         |
| JOB-07                | Owner can authorize an override to unlock a locked design file; the override is written to the audit trail | Direct precedent: `UserPolicy::deactivate()` + `DeactivateUserRequest::authorize()`. `AuditObserver` already covers the "written to audit trail" requirement automatically on the Eloquent `update()`. See Code Examples §6. |
| JOB-08                | Artist can set session status (On Break, End Shift), which affects eligibility for auto-assignment         | New `artist_status` enum + `break_started_at`; `is_available` becomes a derived/synced column so `AssignArtistToJobOrder`'s existing query needs zero changes. See Architecture Patterns §3.                                 |
| JOB-09                | Artist can view their own assigned job orders and use Next/Forward/Not-Appear queue controls               | Extends `JobOrderStatus` per D-03/D-04; see Open Questions §1 for the Forward/Not-Appear ordering mechanism (left to planner).                                                                                               |
| JOB-10                | Artist can view their own performance metrics report                                                       | Aggregation query over `job_orders`/`revision_logs`/`default_sla_days`; no new library needed — plain Eloquent aggregates.                                                                                                   |
| </phase_requirements> |

## Standard Stack

### Core

| Library            | Version                         | Purpose                                                                                                  | Why Standard                                                                                                               |
| ------------------ | ------------------------------- | -------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| `tui-image-editor` | 3.15.3 [VERIFIED: npm registry] | Canvas-based image editor core (crop/flip/rotate/draw/shape/icon/text/filter) — D-09's named requirement | Only maintained, framework-agnostic implementation of "TOAST UI Image Editor"; the requirement names this specific product |
| `tui-color-picker` | 2.2.8 [VERIFIED: npm registry]  | Color picker widget used by `tui-image-editor`'s UI (draw/shape/text color controls)                     | Direct dependency of `tui-image-editor`'s `includeUI` mode; its CSS must be imported separately (see Common Pitfalls §3)   |

**Do NOT install `@toast-ui/vue-image-editor`.** It resolves `peerDependencies: {"vue": "^2.6.14"}` [VERIFIED: npm registry] and will conflict with this project's Vue 3.5.13. Its GitHub issue tracker confirms Vue 3 support was requested June 2022 and remains open/unresolved as of this research [CITED: github.com/nhn/tui.image-editor/issues/788]. D-09 named this package because it's what JOB-04's manuscript wording pointed to, but the _product_ being integrated is TOAST UI Image Editor — that's satisfied by the core package alone. Flag this substitution for the user/planner to confirm (see Assumptions Log A1).

### Supporting

| Library            | Version                                            | Purpose                                                  | When to Use                                                                                                                               |
| ------------------ | -------------------------------------------------- | -------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| `fabric`           | `^4.2.0` → resolves 4.6.0 [VERIFIED: npm registry] | Canvas manipulation engine underlying `tui-image-editor` | Transitive dependency only — do not import or interact with `fabric` directly; `tui-image-editor`'s public API is the integration surface |
| `tui-code-snippet` | `^2.3.3` (transitive) [VERIFIED: npm registry]     | Internal utility library `tui-image-editor` depends on   | Transitive only, no direct interaction needed                                                                                             |

### Alternatives Considered

| Instead of                                     | Could Use                                                                                        | Tradeoff                                                                                                                                                                                                                           |
| ---------------------------------------------- | ------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `tui-image-editor` core + custom Vue 3 wrapper | Fork/patch `@toast-ui/vue-image-editor` for Vue 3                                                | More surface area to maintain; the wrapper is a thin `<script>`/`<template>` shim over the same core class — patching it yourself gains nothing over calling the core class directly from a `<script setup>` component             |
| `tui-image-editor`                             | Fabric.js directly (no TOAST UI layer)                                                           | Loses the pre-built UI (menu bar, submenus, icon picker) that D-09/JOB-04 explicitly asked for ("built-in TOAST UI-based image editor") — would require building a UI from scratch, defeating the purpose of choosing this library |
| `tui-image-editor`                             | A modern maintained alternative (e.g. `react-image-editor`-style libs, `filerobot-image-editor`) | Out of scope — D-09 already locked TOAST UI Image Editor as the named requirement; not Claude's discretion to substitute a different product                                                                                       |

**Installation:**

```bash
npm install tui-image-editor@^3.15.3 tui-color-picker@^2.2.8
```

Do **not** add `@toast-ui/vue-image-editor` to `package.json`.

**Version verification:** Confirmed via `npm view tui-image-editor version` (3.15.3, published 2022-05-22) and `npm view tui-color-picker version` (2.2.8). Both are the latest available versions — this ecosystem has had no releases since 2022, which is expected for a stable, feature-complete canvas editor rather than a sign of abandonment risk to the _core_ package (only the Vue wrapper is the dead end).

## Package Legitimacy Audit

| Package                      | Registry | Age                        | Downloads (last week) | Source Repo                                                                                       | slopcheck | Disposition                              |
| ---------------------------- | -------- | -------------------------- | --------------------- | ------------------------------------------------------------------------------------------------- | --------- | ---------------------------------------- |
| `tui-image-editor`           | npm      | 8 yrs (created 2017-08-29) | 36,617/wk             | github.com/nhn/tui.image-editor                                                                   | OK        | Approved                                 |
| `tui-color-picker`           | npm      | 8 yrs                      | 79,216/wk             | github.com/nhn/tui.image-editor (monorepo)                                                        | OK        | Approved                                 |
| `@toast-ui/vue-image-editor` | npm      | 8 yrs (created 2018-10-26) | 971/wk                | github.com/nhn/toast-ui.vue-image-editor (deprecated, archived per NHN's mono-repo consolidation) | OK        | **REMOVED — Vue 2-only, do not install** |

**Packages removed due to slopcheck [SLOP] verdict:** none (all three came back `OK` — the Vue wrapper's removal here is a _compatibility_ disposition, not a legitimacy/security one).
**Packages flagged as suspicious [SUS]:** none.

Both approved packages are official NHN Corp (creator of TOAST UI) releases, cross-verified via npm registry metadata, the official GitHub organization, and slopcheck. `slopcheck` v0.6.1 was installed and run successfully in this research session (`pip install slopcheck`), so no `[ASSUMED]` fallback gating is needed for these two.

## Architecture Patterns

### System Architecture Diagram

```
Artist Portal (Vue 3 / Inertia)
  │
  ├─ Artist Queue View ──────────────► GET  /artist/job-orders (own assigned list)
  │     │ Next / Forward / Not-Appear    PATCH /artist/job-orders/{id}/next
  │     └───────────────────────────────► PATCH /artist/job-orders/{id}/forward
  │                                       PATCH /artist/job-orders/{id}/not-appear
  │                                              │
  │                                              ▼
  │                                    JobOrderStatus transition
  │                                    (Intake/Assigned → InConsultation → InDesign …)
  │                                    + AuditObserver (automatic)
  │
  ├─ Consultation Notes Form ────────► PATCH /artist/job-orders/{id}/consultation
  │                                              │
  │                                              ▼
  │                                    job_orders.consultation_notes update
  │
  ├─ Design Editor (TOAST UI core,   ── on mount: GET signed temporaryUrl()
  │  wrapped in a Vue 3 component)       for design_files.file_path (if exists)
  │     │                                or blank canvas (D-11)
  │     │ "Send for Review" click
  │     ▼
  │  toDataURL({format:'png'})
  │     │ (client-side canvas → base64 PNG)
  │     ▼
  │  fetch(dataUrl) → Blob → File
  │     │
  │     ▼ useForm({file}).post(...)         POST /artist/job-orders/{id}/design/send-for-review
  │                                              │
  │                                              ▼
  │                                    DB transaction:
  │                                      1. design_files UPSERT (overwrite, D-08)
  │                                      2. revision_logs INSERT (D-07, every click)
  │                                      3. job_orders.status → PendingReview
  │                                    + AuditObserver on both new models
  │
  ├─ Client Verdict Buttons ─────────► PATCH /artist/job-orders/{id}/design/approve
  │  ("Client Approved" /                     → status: PendingReview → DesignApproved
  │   "Client Requested Changes")             → design_files locked (read-only)
  │                                    PATCH /artist/job-orders/{id}/design/request-changes
  │                                              → status: PendingReview → InDesign
  │
  ├─ Session Status Controls ────────► PATCH /artist/session-status
  │  (On Break / End Shift / Return)            │
  │                                              ▼
  │                                    users.artist_status + break_started_at
  │                                    → users.is_available derived/synced
  │                                    (Phase 3's AssignArtistToJobOrder query
  │                                     keeps working unchanged)
  │
  └─ Performance Report ─────────────► GET /artist/performance-report?from=&to=
                                                │
                                                ▼
                                      Aggregate query: job_orders + revision_logs
                                      + SystemConfiguration::getInt('default_sla_days')

Owner Portal (separate, existing role group)
  └─ Unlock Override ────────────────► PATCH /owner/design-files/{id}/unlock
       (DesignFilePolicy::unlock,          │
        Owner-only, D-07)                  ▼
                                     design_files.locked_at → null
                                     + AuditObserver (automatic "written to audit trail")
```

### Recommended Project Structure

```
app/
├── Enums/
│   ├── JobOrderStatus.php          # add InConsultation, InDesign, PendingReview,
│   │                                 DesignApproved (+ NotAppeared if status-based, see Open Q1)
│   └── ArtistStatus.php            # new: Available, OnBreak, OffShift (D-13)
├── Models/
│   ├── DesignFile.php              # new — #[Fillable], #[ObservedBy(AuditObserver::class)]
│   └── RevisionLog.php             # new — same pattern
├── Policies/
│   └── DesignFilePolicy.php        # new — unlock() Owner-only, mirrors UserPolicy::deactivate()
├── Actions/
│   ├── JobOrder/
│   │   ├── AssignArtistToJobOrder.php   # existing — claimOldestUnassigned() may be
│   │   │                                  invoked when artist_status → Available (D-13/D-07 xref)
│   │   ├── RecordDesignRevision.php     # new — the export→store→revision_logs transaction
│   │   └── SetArtistSessionStatus.php   # new — artist_status transition + is_available sync
├── Http/
│   ├── Controllers/
│   │   ├── Artist/
│   │   │   ├── JobOrderQueueController.php     # Next/Forward/Not-Appear, consultation notes
│   │   │   ├── DesignEditorController.php      # send-for-review, approve, request-changes
│   │   │   ├── SessionStatusController.php     # On Break/End Shift
│   │   │   └── PerformanceReportController.php
│   │   └── Owner/
│   │       └── DesignFileController.php        # unlock() only
│   └── Requests/
│       └── Artist/*.php            # one per mutating action, authorize() + rules()
resources/js/
├── pages/artist/
│   ├── Dashboard.vue               # replace placeholder — queue list + session controls
│   ├── DesignEditor.vue            # hosts the TOAST UI wrapper component
│   └── PerformanceReport.vue
└── components/
    └── ToastImageEditor.vue        # new — thin Composition API wrapper over tui-image-editor
```

### Pattern 1: Status-Machine Enum Extension (established, HIGH confidence)

**What:** String-backed PHP enum, TitleCase case names, snake_case string values, extended additively — never renumbered/removed.
**When to use:** Every new stage in the job-order or artist-session lifecycle.
**Example:**

```php
// Source: app/Enums/JobOrderStatus.php (existing, verified in this repo)
enum JobOrderStatus: string
{
    case Intake = 'intake';
    case ValidationFailed = 'validation_failed';
    case ReadyForProduction = 'ready_for_production';
    case Assigned = 'assigned';
    // Phase 4 additions — same casing/value convention:
    case InConsultation = 'in_consultation';
    case InDesign = 'in_design';
    case PendingReview = 'pending_review';
    case DesignApproved = 'design_approved';
}
```

New sibling enum for D-13, following `UserRole`'s exact convention (`ProductionStaff => 'production_staff'`):

```php
enum ArtistStatus: string
{
    case Available = 'available';
    case OnBreak = 'on_break';
    case OffShift = 'off_shift';
}
```

### Pattern 2: Owner-Only Audited Override (established, HIGH confidence)

**What:** FormRequest `authorize()` delegates to `$this->user()->can('ability', $model)`, which resolves to an auto-discovered `App\Policies\{Model}Policy` (Laravel's convention-based policy discovery — no explicit registration found anywhere in `app/Providers/`, confirming auto-discovery is already relied on for `UserPolicy`).
**When to use:** JOB-07's unlock override.
**Example:**

```php
// Source: app/Policies/UserPolicy.php + app/Http/Requests/Owner/DeactivateUserRequest.php (existing, verified)
class DesignFilePolicy
{
    public function unlock(User $actor, DesignFile $designFile): bool
    {
        return $actor->role === UserRole::Owner; // Owner only — not Admin, per JOB-07's exact wording
    }
}

class UnlockDesignFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('unlock', $this->route('designFile'));
    }

    public function rules(): array
    {
        return [];
    }
}
```

The controller then does a plain `$designFile->forceFill(['locked_at' => null])->save()` (or `->update()`) — **no manual audit-log call needed.** `AuditObserver::updated()` fires automatically on any Eloquent `save()`/`update()` against a model carrying `#[ObservedBy(AuditObserver::class)]`, recording `old_values`/`new_values` including `auth()->id()` as the actor. This is the same mechanism that already produces the `'action' => 'updated'` audit row asserted in `tests/Feature/FrontlineStaff/JobOrderProcessingTest.php`'s audit test. JOB-07's "override is written to the audit trail" requirement is satisfied for free as long as the unlock goes through a normal Eloquent mutation on `design_files` — do not hand-roll a bespoke `AuditLogger::recordMutation()` call for this action (see Don't Hand-Roll).

### Pattern 3: `is_available` as a Derived Column (new, MEDIUM confidence — Claude's discretion per CONTEXT.md, informed by AssignArtistToJobOrder)

**What:** D-13 requires `artist_status` (3-state enum) while D-13 also requires `is_available` (existing boolean) to keep working unchanged for `AssignArtistToJobOrder`'s query (`->where('is_available', true)`).
**Recommendation:** Sync `is_available` in the same write that changes `artist_status`, rather than making it a computed accessor — Eloquent boolean casts + a raw DB column keep the existing `where('is_available', true)` query untouched (an accessor/mutator-computed value is not queryable with `->where()` without an accessor-aware scope, which would be a bigger change to `AssignArtistToJobOrder` than D-13 intends).
**Example:**

```php
// Recommended shape — app/Actions/JobOrder/SetArtistSessionStatus.php (new)
class SetArtistSessionStatus
{
    public function __invoke(User $artist, ArtistStatus $status): void
    {
        $artist->forceFill([
            'artist_status' => $status,
            'is_available' => $status === ArtistStatus::Available,
            'break_started_at' => $status === ArtistStatus::OnBreak ? now() : null,
        ])->save();

        // D-07 xref: when an artist returns to Available, claim their oldest
        // unassigned Type B job order — AssignArtistToJobOrder::claimOldestUnassigned()
        // already exists for exactly this caller (see its own docblock: "No caller
        // exists yet this phase — Phase 4's On Break/End Shift toggle will invoke this").
        if ($status === ArtistStatus::Available) {
            app(AssignArtistToJobOrder::class)->claimOldestUnassigned($artist);
        }
    }
}
```

### Pattern 4: Vue 3 Composition API Wrapper for `tui-image-editor` (new, MEDIUM confidence — verified against library source, no in-repo precedent)

**What:** Mount the vanilla `ImageEditor` class inside a `<script setup>` component's `onMounted` hook, expose an imperative API (export method) via `defineExpose`, tear down in `onBeforeUnmount`.
**When to use:** JOB-04's editor component.
**Example:**

```vue
<!-- resources/js/components/ToastImageEditor.vue -->
<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
// @ts-expect-error — tui-image-editor ships no type declarations (verified: no
// `types`/`typings` field in package.json, no @types/tui-image-editor on npm)
import ImageEditor from 'tui-image-editor';
import 'tui-color-picker/dist/tui-color-picker.css';
import 'tui-image-editor/dist/tui-image-editor.css';

const props = defineProps<{
    /** Signed temporaryUrl() for the existing design file, or null for a blank canvas (D-11). */
    initialImageUrl: string | null;
    initialImageName?: string;
}>();

const editorContainer = ref<HTMLElement | null>(null);
let editor: InstanceType<typeof ImageEditor> | null = null;

onMounted(() => {
    editor = new ImageEditor(editorContainer.value!, {
        includeUI: {
            // Omitting/blanking loadImage.path starts the editor with an empty
            // canvas — verified in tui-image-editor source (src/js/ui.js):
            // `_getLoadImage()` only calls initLoadImage() `if (loadImageInfo.path)`.
            loadImage: props.initialImageUrl
                ? {
                      path: props.initialImageUrl,
                      name: props.initialImageName ?? 'design',
                  }
                : undefined,
            theme: {},
            menu: [
                'crop',
                'flip',
                'rotate',
                'draw',
                'shape',
                'icon',
                'text',
                'filter',
            ],
            menuBarPosition: 'bottom',
        },
        cssMaxWidth: 900,
        cssMaxHeight: 600,
        usageStatistics: false, // opt out of NHN hostname telemetry — see Security Domain
    });
});

onBeforeUnmount(() => {
    editor?.destroy();
    editor = null;
});

/** Flattened PNG export (D-10) — returns a base64 data URI. */
function exportPng(): string {
    return editor!.toDataURL({ format: 'png' });
}

defineExpose({ exportPng });
</script>

<template>
    <div ref="editorContainer" class="tui-image-editor-wrapper" />
</template>
```

### Pattern 5: Loading an Existing Private-Disk Image Into the Editor (new, HIGH confidence — verified against Laravel 13.x docs)

**What:** `design_files.file_path` lives on the `local` disk (`storage/app/private`, per `config/filesystems.php`), same as Type A's `job_orders.file_path`. That disk is not web-accessible by a bare URL. `tui-image-editor`'s `loadImage.path` is a browser-fetched URL (it runs `loadImageFromURL()` internally), so the server must hand the client a URL it can actually load.
**Recommendation:** Use Laravel's `Storage::disk('local')->temporaryUrl($path, now()->addMinutes(10))` — this is enabled by the `'serve' => true` already present on the `local` disk in `config/filesystems.php` (confirmed: this project's config already has `serve: true`, so no config change is needed). This generates a short-lived, HMAC-signed URL served by Laravel's built-in signed-route file controller — no new storage/serve route needs to be hand-built, and it avoids ever making `design_files` world-readable.
**Example:**

```php
// In the controller/Inertia prop preparation for the design editor page
'initialImageUrl' => $designFile?->file_path
    ? Storage::disk('local')->temporaryUrl($designFile->file_path, now()->addMinutes(10))
    : null,
```

## Don't Hand-Roll

| Problem                                                               | Don't Build                                                                                          | Use Instead                                                                                                 | Why                                                                                                                                                                                                                                                          |
| --------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Recording "override written to audit trail" for JOB-07                | A bespoke `AuditLogger::recordMutation()` call inside the unlock controller/action                   | A plain Eloquent `save()`/`update()` on the `#[ObservedBy(AuditObserver::class)]`-tagged `DesignFile` model | `AuditObserver::updated()` already fires automatically on every Eloquent mutation of an observed model — this is the exact mechanism that produces every other audit row in the codebase; adding a second, manual audit call would double-log the same event |
| Serving a private-disk image to the browser for the editor            | A custom `Route::get('design-files/{id}/raw', ...)` controller that streams the file                 | `Storage::disk('local')->temporaryUrl()` (already enabled by `serve: true`)                                 | Laravel 13's local-disk `serve` feature is purpose-built for exactly this — a signed, time-limited URL without exposing the disk publicly or writing a custom download endpoint                                                                              |
| Canvas image editing UI (crop/rotate/draw/shape/text/filter controls) | A hand-rolled `<canvas>` + toolbar Vue component                                                     | `tui-image-editor`'s `includeUI` mode                                                                       | This is the entire reason D-09 named this library — building an equivalent editor UI from raw canvas APIs is a multi-week effort the requirement explicitly avoids by naming TOAST UI                                                                        |
| Base64 data-URI → uploadable file conversion                          | Manual byte-array/ArrayBuffer parsing of the data URI                                                | `fetch(dataUrl).then(r => r.blob())` then wrap in `new File([blob], name, {type})`                          | Standard, well-tested browser API path; manual base64 decoding is error-prone and unnecessary                                                                                                                                                                |
| `is_available` recomputation on every read                            | A computed Eloquent accessor (`getIsAvailableAttribute()`) derived from `artist_status` at read time | Writing `is_available` directly alongside `artist_status` in the same mutation (Pattern 3 above)            | An accessor-only approach breaks `AssignArtistToJobOrder`'s existing `->where('is_available', true)` query (accessors aren't queryable without an extra scope/cast layer) — D-13 explicitly asks for the round-robin query to keep working unchanged         |

**Key insight:** Every "don't hand-roll" item in this phase already has a working, tested mechanism sitting in the codebase from Phases 1-3 (`AuditObserver`, Laravel's own file-serving feature, `AssignArtistToJobOrder`'s query shape). The only genuinely new _tool_ introduced by this phase is the TOAST UI editor itself — everything wrapped around it should reuse what's already there.

## Common Pitfalls

### Pitfall 1: Installing `@toast-ui/vue-image-editor` breaks the Vue 3 build

**What goes wrong:** `npm install @toast-ui/vue-image-editor` either fails npm's peer-dependency resolution (Vue 3.5.13 vs. required `^2.6.14`) or installs "successfully" (npm doesn't hard-block peer mismatches by default) but the component throws at runtime because it's written against Vue 2's Options API internals (`Vue.extend`, `this.$refs` patterns incompatible with Vue 3's reactivity system).
**Why it happens:** D-09 named this exact package before this research confirmed its Vue-2-only status; the requirement's intent (TOAST UI Image Editor) is satisfiable without it.
**How to avoid:** Install only `tui-image-editor` + `tui-color-picker` (core, framework-agnostic). Build a thin Vue 3 wrapper component (Pattern 4).
**Warning signs:** `npm install` warnings mentioning `vue@^2.6.14`; `vue-tsc`/browser console errors referencing `Vue.extend` or `this.$mount`.

### Pitfall 2: `tui-image-editor` ships with zero TypeScript types

**What goes wrong:** `vue-tsc --noEmit` (this project's `npm run types:check`, strict mode, part of the CI-equivalent `vp check`) fails on `import ImageEditor from 'tui-image-editor'` with an implicit-`any`/missing-module error.
**Why it happens:** Verified directly — the package's `package.json` has no `types`/`typings` field, and `@types/tui-image-editor` does not exist on npm (confirmed via `npm view`, 404).
**How to avoid:** Either (a) add a minimal ambient module declaration (`resources/js/types/tui-image-editor.d.ts` with `declare module 'tui-image-editor'`), or (b) use a scoped `// @ts-expect-error` comment at the import site (shown in Code Examples §4). Confirm which approach this project prefers — no existing precedent for an untyped third-party JS lib exists in the codebase yet (`fabric` isn't imported directly, so its own types are irrelevant here).
**Warning signs:** `npm run types:check` failing specifically on the editor component file; CI-equivalent `vp check` red.

### Pitfall 3: Missing `tui-color-picker.css` produces a broken/unstyled color picker

**What goes wrong:** The editor's draw/shape/text color-picker widget renders unstyled or non-functional if only `tui-image-editor.css` is imported.
**Why it happens:** Verified by inspecting the unpacked package — `tui-image-editor.css` does not bundle `tui-color-picker`'s own stylesheet; they ship as two separate CSS files that must both be imported by the consuming app. (The editor's own toolbar/menu icons _are_ self-contained as base64 data-URIs inside `tui-image-editor.css`, so no separate asset-path configuration is needed for those.)
**How to avoid:** Import both CSS files, in this order: `tui-color-picker/dist/tui-color-picker.css` then `tui-image-editor/dist/tui-image-editor.css` (shown in Code Examples §4).
**Warning signs:** Color swatches render as plain unstyled `<div>`s in manual QA.

### Pitfall 4: `usageStatistics` defaults to `true` and pings NHN's telemetry with the hostname

**What goes wrong:** By default, `tui-image-editor` sends the page's hostname to NHN on initialization (`options.usageStatistics` defaults to `true` per the library's own JSDoc: _"Let us know the hostname. If you don't want to send the hostname, please set to false"_).
**Why it happens:** Opt-out, not opt-in, telemetry baked into the library.
**How to avoid:** Always pass `usageStatistics: false` in the `ImageEditor` constructor options (shown in Code Examples §4). This is a genuine third-party outbound network call from a printing shop's internal tool — worth an explicit decision, not an accidental default. Flag for ASVS/security review (see Security Domain).
**Warning signs:** Outbound requests to an NHN-owned domain visible in browser network tab during manual QA; a strict CSP would also surface this.

### Pitfall 5: `toDataURL()` export can't go through Inertia's uncontrolled `<Form>` component

**What goes wrong:** The existing Type A file-upload pattern in this codebase (`ReplaceJobOrderFileDialog.vue`) uses Inertia's `<Form>` component with a native `<input type="file">` — this only works for user-selected files. A canvas export produces an in-memory `Blob`/`File`, and browsers do not allow programmatically assigning `.files` on a native file input (no `DataTransfer` shortcut reliably supported across browsers for this).
**Why it happens:** The export is generated client-side, not selected by the user through a file picker.
**How to avoid:** Use Inertia's `useForm()` composable instead (already documented in `.claude/skills/inertia-vue-development/SKILL.md` as the "more programmatic control" option): assign the `File` object to a reactive form field, then `form.post(url, { forceFormData: true })`. Inertia auto-detects `File`/`Blob` values in form data and switches to multipart encoding regardless, but `forceFormData: true` makes the intent explicit and avoids edge cases with mixed field types.
**Warning signs:** Server-side `$request->file('file')` returns `null` despite the client clearly producing an image.

### Pitfall 6: `NotAppeared`/"Forward" ordering has no obvious column to sort on

**What goes wrong:** If Not-Appear/Forward are modeled as new `JobOrderStatus` values (per D-03's general framing) without a supporting timestamp/order column, there's no way to express "deprioritized, but still resumable, and specifically ordered _after_ the artist's other still-active job orders."
**Why it happens:** `JobOrderStatus` alone captures _stage_, not _queue position within a stage_ — "Next" needs an ordering key (D-04 says "oldest `Assigned`"), and Forward/Not-Appear need to affect that ordering without changing `created_at`.
**How to avoid:** This is explicitly Claude's/planner's discretion per CONTEXT.md — flagging the mechanism, not the decision. A workable shape: add a nullable `queue_deprioritized_at` timestamp (set on Forward and Not-Appear, cleared by "Next"/manual resume), and order the "Next" query by `COALESCE(queue_deprioritized_at, created_at) ASC` scoped to the artist and to non-terminal statuses. Combine with a `not_appeared` boolean if Not-Appear needs to be excluded from the "up next" pool entirely rather than just reordered (see Open Questions §1).
**Warning signs:** "Next" repeatedly resurfaces the same forwarded/not-appeared job order instead of moving to the next one.

### Pitfall 7: Fortifying against a stale `fabric@4.x` in a Vite 8 project

**What goes wrong:** `fabric@4.x` (2020-2021 era) predates Vite's more aggressive ESM-first dependency pre-bundling; some CJS/UMD interop edge cases (default export shape, `window`/`document` global assumptions at module-eval time) have historically tripped up bundlers other than Webpack, which is what `fabric@4.x` and `tui-image-editor` were built/tested against.
**Why it happens:** Neither package has been updated since ~2021-2022; Vite's esbuild pre-bundler is generally good at CJS interop but isn't infallible for older UMD bundles with non-standard export patterns.
**How to avoid:** This is exactly why the Wave-0 spike (mount editor, load blank canvas, export) is recommended before building the full slice — confirm the import/mount/export cycle works cleanly under this project's actual Vite 8 + `vite-plus` config before committing to the full editor UI.
**Warning signs:** Blank white editor area with no console errors (a classic CJS-default-export mismatch symptom — `import ImageEditor from 'tui-image-editor'` resolving to the module namespace object instead of the class).

## Code Examples

Verified patterns from official sources and this codebase:

### 1. Consultation Notes Update (follows existing Form Request + Eloquent update pattern)

```php
// Source: pattern matches app/Http/Controllers/FrontlineStaff/JobOrderController.php (existing)
public function updateConsultation(UpdateConsultationNotesRequest $request, JobOrder $jobOrder): RedirectResponse
{
    $jobOrder->forceFill([
        'consultation_notes' => $request->validated('consultation_notes'),
    ])->save();

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Consultation notes saved.')]);

    return back();
}
```

### 2. `ImageEditor` Constructor Options (verified against `tui-image-editor` v3.15.3 source)

```js
// Source: node_modules/tui-image-editor/src/js/imageEditor.js JSDoc (unpacked and inspected directly)
new ImageEditor(wrapper /* string | HTMLElement */, {
    includeUI: {
        loadImage: { path: '...', name: '...' }, // omit or {path:''} for blank canvas
        theme: {},
        menu: [
            'crop',
            'flip',
            'rotate',
            'draw',
            'shape',
            'icon',
            'text',
            'filter',
        ],
        initMenu: '',
        uiSize: { width: '100%', height: '600px' },
        menuBarPosition: 'bottom',
    },
    cssMaxWidth: 700,
    cssMaxHeight: 500,
    usageStatistics: false, // default true — see Pitfall 4
});
```

### 3. Export API (verified against source)

```js
// Source: node_modules/tui-image-editor/src/js/imageEditor.js — loadImageFromFile/toDataURL JSDoc
const dataUri = editor.toDataURL({ format: 'png' }); // synchronous, returns base64 data URI
```

### 4. Full editor component — see Architecture Patterns §4 above (Pattern 4) for the complete `ToastImageEditor.vue`.

### 5. Send-for-Review submission (Vue side)

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ToastImageEditor from '@/components/ToastImageEditor.vue';

const editorRef = ref<InstanceType<typeof ToastImageEditor> | null>(null);
const form = useForm<{ file: File | null }>({ file: null });

async function sendForReview(jobOrderId: number) {
    const dataUrl = editorRef.value!.exportPng();
    const blob = await (await fetch(dataUrl)).blob();
    form.file = new File([blob], 'design.png', { type: 'image/png' });

    form.post(`/artist/job-orders/${jobOrderId}/design/send-for-review`, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>
```

### 6. Send-for-Review submission (server side — D-07/D-08 transaction)

```php
// Pattern follows app/Actions/JobOrder/AssignArtistToJobOrder.php's DB::transaction shape
public function __invoke(JobOrder $jobOrder, UploadedFile $file): void
{
    DB::transaction(function () use ($jobOrder, $file) {
        $path = $file->store('design-files', 'local');

        DesignFile::updateOrCreate(
            ['job_order_id' => $jobOrder->id], // D-08: single current row per job order
            ['file_path' => $path],
        );

        RevisionLog::create([
            'job_order_id' => $jobOrder->id,
            'submitted_at' => now(),
            // outcome/notes columns per planner's schema design
        ]);

        $jobOrder->forceFill(['status' => JobOrderStatus::PendingReview])->save();
    });
}
```

## State of the Art

| Old Approach                                            | Current Approach                                                                         | When Changed                                                                                                                                                                                                  | Impact                                                                                                                                                                                         |
| ------------------------------------------------------- | ---------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `@toast-ui/vue-image-editor` official Vue wrapper       | Direct integration of `tui-image-editor` core via a hand-written Composition API wrapper | NHN deprecated/archived the standalone framework wrapper repos (React confirmed archived 2021-07-28; Vue wrapper repo deprecated as part of the same mono-repo consolidation) and never shipped Vue 3 support | Any Vue 3 project integrating TOAST UI Image Editor must write its own thin wrapper — this is now the de facto standard approach across the ecosystem, not a workaround unique to this project |
| Custom download/serve controller for private-disk files | `Storage::disk('local')->temporaryUrl()` with `serve: true`                              | Laravel 11 introduced local-disk temporary URL support (this app is on Laravel 13.29.0, well past that)                                                                                                       | This project's `config/filesystems.php` already has `serve: true` set — no framework upgrade or config change needed, just use the feature                                                     |

**Deprecated/outdated:**

- `@toast-ui/vue-image-editor`: superseded by no official replacement; the ecosystem's answer is "wrap the core class yourself." Do not attempt to install or patch it.
- `nhn/toast-ui.react-image-editor` (React wrapper): confirmed archived on GitHub 2021-07-28, read-only — not relevant to this Vue project but corroborates the pattern of NHN sunsetting framework-specific wrappers in favor of the core package.

## Assumptions Log

| #   | Claim                                                                                                                                                    | Section                                | Risk if Wrong                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| --- | -------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| A1  | Substituting `tui-image-editor` core (no Vue wrapper) satisfies D-09's intent even though D-09's text literally names `@toast-ui/vue-image-editor`       | Standard Stack, Pattern 4              | If the user specifically wanted the _wrapper package_ installed (even if non-functional) for some external reason (e.g. contractual/manuscript literal-compliance reasons), this substitution would need explicit sign-off before planning locks it in. The technical case for the substitution is HIGH confidence (verified peer-dependency conflict + open unresolved GitHub issue), but the _decision_ to substitute is a scope call, not purely technical — recommend the planner surface this explicitly rather than silently substituting. |
| A2  | A nullable `queue_deprioritized_at` timestamp + optional `not_appeared` boolean is a reasonable schema shape for Forward/Not-Appear ordering             | Common Pitfalls §6, Architecture       | Low risk — explicitly flagged as Claude's discretion in CONTEXT.md; the planner is free to choose a different shape (e.g. a dedicated status value) as long as it satisfies D-04's "oldest Assigned first, Forward/Not-Appear don't reassign" behavior.                                                                                                                                                                                                                                                                                          |
| A3  | `design_files`/`revision_logs` should use `#[Fillable]`/`#[ObservedBy(AuditObserver::class)]` attributes identically to `JobOrder`/`SystemConfiguration` | Architecture Patterns, Don't Hand-Roll | Very low risk — this is a mechanical, 100%-consistent convention across every existing domain model in the codebase (`JobOrder`, `User`, `SystemConfiguration`, `Customer`, `QueueEntry` all follow it identically).                                                                                                                                                                                                                                                                                                                             |

**If this table is empty:** N/A — see A1 above for the one assumption that genuinely needs user/planner confirmation before locking into a plan.

## Open Questions (RESOLVED)

1. **Exact mechanism for Forward/Not-Appear ordering (ties to Assumption A2)**
    - What we know: D-04 locks the _behavior_ (no reassignment, "Next" = oldest `Assigned`, Not-Appear deprioritizes but stays resumable). CONTEXT.md explicitly leaves the _data shape_ to Claude's discretion.
    - What's unclear: Whether to model this as a new `JobOrderStatus::NotAppeared` case (D-03's literal "extends the enum" framing) or as a boolean/timestamp flag alongside the existing status (simpler, avoids treating "not appeared" as a lifecycle stage it isn't).
    - Recommendation: Lean toward the flag/timestamp approach (Pitfall 6) — a `JobOrderStatus::NotAppeared` case would need its own transition rules back into `InConsultation`/`Assigned`, effectively duplicating state that the flag approach handles with one extra `WHERE` clause. Either way, this should be an explicit planner decision, not silently picked during implementation.
    - **(RESOLVED)** Planner adopted the flag/timestamp approach: 04-01-PLAN.md Task 1 adds `job_orders.queue_deprioritized_at` (nullable timestamp) and `job_orders.not_appeared` (boolean, default false); Task 3's `oldestEligibleId()`/`next()`/`forward()`/`notAppear()` implement the ordering and resumability behavior.

2. **`design_files`/`revision_logs` exact column list**
    - What we know: `design_files` needs at minimum `job_order_id`, `file_path`, and (per JOB-06/07) a lock indicator (`locked_at` timestamp, or infer lock purely from `job_orders.status === DesignApproved` with no dedicated column). `revision_logs` needs at minimum `job_order_id`, a submission timestamp, and (per D-05/D-06) an outcome field (approved / changes-requested / pending) plus whatever "what changed" notes JOB-05's wording implies.
    - What's unclear: Whether `locked_at` should live on `design_files` (allowing a design to be locked independent of job-order status, e.g. for the Owner override to be reversible without also reverting the job order's status) or be purely derived from `job_orders.status`.
    - Recommendation: A dedicated `design_files.locked_at` (nullable timestamp) is safer — it lets JOB-07's unlock override clear _just_ the lock without forcing a job-order status rollback, which better matches "Owner can authorize an override to unlock a locked design file" (the requirement talks about unlocking the _file_, not reopening the job order's review cycle). This is Claude's discretion per CONTEXT.md; flagging the reasoning for the planner to confirm or override.
    - **(RESOLVED)** Planner adopted the dedicated-column recommendation: 04-03-PLAN.md Task 1 adds `design_files.locked_at` (nullable timestamp, set only via `forceFill()`), and Task 2's `sendForReview`/Plan 04-05's `unlock` guards key exclusively off it, never off `job_orders.status`.

3. **CSS import order/scope for the TOAST UI wrapper**
    - What we know: Both `tui-color-picker.css` and `tui-image-editor.css` must be imported somewhere for the editor to render correctly (Pitfall 3).
    - What's unclear: Whether to import them globally (`resources/css/app.css`) or scoped to the `ToastImageEditor.vue` component's `<script setup>` (shown in Code Examples). Given this is the _only_ page using the editor, component-scoped `import` statements (as shown) avoid shipping ~100KB of unused CSS to every other page — but this should be confirmed against the project's actual CSS-splitting behavior under Vite 8 during the Wave-0 spike.
    - Recommendation: Component-scoped imports (as shown in Pattern 4), verified during the spike task.
    - **(RESOLVED)** Planner adopted component-scoped imports: 04-04-PLAN.md Task 1 imports `tui-color-picker/dist/tui-color-picker.css` then `tui-image-editor/dist/tui-image-editor.css` directly inside `ToastImageEditor.vue`'s `<script setup>`, verified by the Wave-0 spike's `npm run types:check` pass.

## Environment Availability

| Dependency                | Required By                                                                                                                                             | Available                                                                                | Version | Fallback                                                                                                                                                                                  |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- | ------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| npm registry reachability | Installing `tui-image-editor`/`tui-color-picker`                                                                                                        | Confirmed reachable in this research session (`npm view`, `npm pack` succeeded)          | —       | —                                                                                                                                                                                         |
| `slopcheck` (Python/pip)  | Package Legitimacy Gate                                                                                                                                 | Installed successfully in this session (`pip install slopcheck --break-system-packages`) | 0.6.1   | N/A — already used                                                                                                                                                                        |
| GD extension (PHP)        | Laravel's image manipulation helpers (not required by this phase — PNG export lands as raw bytes via `store()`, no server-side image processing needed) | Not checked — not required                                                               | —       | Not needed; this phase does zero server-side image manipulation, unlike Phase 3's `ValidateJobOrderFile` which uses `getimagesize()`/`exif_read_data()` (both are core PHP, no extension) |

No blocking missing dependencies. This phase's only genuinely new external dependency is the two npm packages above, both confirmed installable.

## Validation Architecture

### Test Framework

| Property           | Value                                                                                 |
| ------------------ | ------------------------------------------------------------------------------------- |
| Framework          | Pest 5.1.3 + `pestphp/pest-plugin-laravel` 5.0.1                                      |
| Config file        | `phpunit.xml` (suites: Unit, Feature)                                                 |
| Quick run command  | `php artisan test --compact --filter=<TestName>` or `vendor/bin/pest --filter=<name>` |
| Full suite command | `php artisan test --compact`                                                          |

Feature tests are globally bound to `Tests\TestCase` + `RefreshDatabase` via `tests/Pest.php`'s `pest()->extend(...)->in('Feature')` call — new Phase 4 feature tests need no per-file `uses()` boilerplate as long as they live under `tests/Feature/`.

### Phase Requirements → Test Map

| Req ID | Behavior                                                                                                                       | Test Type | Automated Command                           | File Exists?                                                                                                                                                                                                          |
| ------ | ------------------------------------------------------------------------------------------------------------------------------ | --------- | ------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| JOB-03 | Artist saves consultation notes on an assigned job order                                                                       | feature   | `pest --filter="consultation notes"`        | ❌ Wave 0 — new `tests/Feature/Artist/ConsultationNotesTest.php`                                                                                                                                                      |
| JOB-09 | "Next" claims oldest `Assigned` job order for that artist; Forward/Not-Appear don't reassign                                   | feature   | `pest --filter="next.*forward.*not.appear"` | ❌ Wave 0 — new `tests/Feature/Artist/QueueControlsTest.php`                                                                                                                                                          |
| JOB-04 | Design editor page renders with a signed image URL prop (or null for blank canvas)                                             | feature   | `pest --filter="design editor"`             | ❌ Wave 0 — server-side prop assertion only; actual canvas rendering is out of Pest's reach (PHP-side test), browser-level editor behavior is manual/visual QA per this project's stack (no Dusk/Playwright detected) |
| JOB-05 | Send-for-review request creates a `revision_logs` row and overwrites `design_files` every time, including the first submission | feature   | `pest --filter="send for review"`           | ❌ Wave 0 — new `tests/Feature/Artist/SendForReviewTest.php`, follows `Storage::fake('local')` + `Storage::disk('local')->assertExists(...)` pattern from `JobOrderProcessingTest.php`                                |
| JOB-06 | A `DesignApproved` job order's design file rejects further edits/overwrites server-side                                        | feature   | `pest --filter="locked design"`             | ❌ Wave 0                                                                                                                                                                                                             |
| JOB-07 | Non-Owner is forbidden from unlock; Owner unlock writes an `audit_trail` row                                                   | feature   | `pest --filter="unlock override"`           | ❌ Wave 0 — mirrors the existing audit-assertion pattern (`DB::table('audit_trail')->where('auditable_type', DesignFile::class)->where('action','updated')->exists()`)                                                |
| JOB-08 | Setting `OnBreak`/`OffShift` flips `is_available` to false; returning to `Available` claims the oldest unassigned job order    | feature   | `pest --filter="session status"`            | ❌ Wave 0 — extends `tests/Feature/JobOrder/AssignArtistToJobOrderTest.php`'s existing pattern for `claimOldestUnassigned()`                                                                                          |
| JOB-10 | Performance report aggregates jobs completed / avg revisions / SLA adherence over a date range                                 | feature   | `pest --filter="performance report"`        | ❌ Wave 0                                                                                                                                                                                                             |

### Sampling Rate

- **Per task commit:** `php artisan test --compact --filter=<relevant test>`
- **Per wave merge:** `php artisan test --compact` (full suite)
- **Phase gate:** Full suite green before `/gsd-verify-work`, plus `vendor/bin/pint --dirty --format agent` and `npm run types:check`/`vp check` for every touched PHP/TS file (per CLAUDE.md's mandatory formatting/type-check rules).

### Wave 0 Gaps

- [ ] `tests/Feature/Artist/ConsultationNotesTest.php` — covers JOB-03
- [ ] `tests/Feature/Artist/QueueControlsTest.php` — covers JOB-09
- [ ] `tests/Feature/Artist/DesignEditorTest.php` — covers JOB-04 (prop-shape assertions; a JS-level "canvas actually renders" check is out of scope for Pest — flag to the user that this project has no browser-automation test tool (no Dusk/Playwright detected in `composer.json`/`package.json`), so the _visual_ correctness of the TOAST UI editor is manual QA only)
- [ ] `tests/Feature/Artist/SendForReviewTest.php` — covers JOB-05, JOB-07 (audit assertion), JOB-08 (revision counting downstream)
- [ ] `tests/Feature/Artist/DesignLockTest.php` — covers JOB-06
- [ ] `tests/Feature/Owner/UnlockDesignFileTest.php` — covers JOB-07
- [ ] `tests/Feature/Artist/SessionStatusTest.php` — covers JOB-08
- [ ] `tests/Feature/Artist/PerformanceReportTest.php` — covers JOB-10
- [ ] Factory additions: `DesignFileFactory`, `RevisionLogFactory` — follow `JobOrderFactory`'s `afterCreating()` pattern for any non-`#[Fillable]` columns (e.g. `locked_at`)

## Security Domain

### Applicable ASVS Categories

| ASVS Category           | Applies       | Standard Control                                                                                                                                                                                                                                                                                                                                                                                            |
| ----------------------- | ------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| V4 Access Control       | yes           | `role:artist` route middleware (existing `EnsureUserHasRole`) for all Artist actions; `DesignFilePolicy::unlock()` restricted to `UserRole::Owner` specifically (not Admin) for the JOB-07 override — mirrors `UserPolicy`'s Owner/Admin differentiation pattern                                                                                                                                            |
| V5 Input Validation     | yes           | FormRequest `rules()` for consultation notes (string/length limits) and the send-for-review file upload (`['required', 'file', 'image', 'mimes:png']` — tighter than `ReplaceJobOrderFileRequest`'s deliberately-loose Type A rules, since this file is server-generated by _this app's own_ canvas export, not an arbitrary user upload, so validating it's actually a PNG is reasonable defense-in-depth) |
| V8 Data Protection      | yes           | `design_files` served to the browser only via short-lived signed `temporaryUrl()`, never a permanent public URL — consistent with the project's existing private-`local`-disk posture for `job_orders.file_path`                                                                                                                                                                                            |
| V9 Communications       | yes (opt-out) | `usageStatistics: false` on the `ImageEditor` constructor (Pitfall 4) — without this, the editor makes an outbound call to NHN with the page's hostname on every load, which is an undisclosed third-party data flow from an internal business tool                                                                                                                                                         |
| V13 API and Web Service | n/a           | No API layer in this app (Inertia-only, per project architecture) — not applicable                                                                                                                                                                                                                                                                                                                          |

### Known Threat Patterns for this stack

| Pattern                                                                                                                                                 | STRIDE                             | Standard Mitigation                                                                                                                                                                                                                                                                              |
| ------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Artist bypassing server-side lock by re-submitting to the send-for-review endpoint after `DesignApproved`                                               | Tampering                          | Server-side status guard in the controller/action (`abort_unless($jobOrder->status !== JobOrderStatus::DesignApproved, 422, ...)`) — mirrors `JobOrderController::replaceFile`'s existing `abort_unless($jobOrder->type === JobOrderType::TypeA, ...)` pattern for a similar "wrong state" guard |
| Non-Owner (e.g. Admin) attempting the unlock override via direct route/PATCH                                                                            | Elevation of Privilege             | `DesignFilePolicy::unlock()` checked via FormRequest `authorize()`, returning 403 — same mechanism already proven for `UserPolicy::deactivate()`'s Owner/Admin split                                                                                                                             |
| Forged/replayed signed `temporaryUrl()` after expiry                                                                                                    | Information Disclosure             | Laravel's signed-route middleware validates both the HMAC signature and expiration timestamp automatically — use the default 10-minute-or-shorter expiry, don't extend it unnecessarily                                                                                                          |
| Arbitrary file upload disguised as the design export (a malicious client bypassing the Vue editor and POSTing directly to the send-for-review endpoint) | Tampering / Elevation of Privilege | `mimes:png` + `image` validation rules on the FormRequest (tighter than Type A's intentionally-loose rules, since this endpoint's _only_ legitimate producer is the app's own canvas export)                                                                                                     |

## Sources

### Primary (HIGH confidence)

- `tui-image-editor@3.15.3` npm package — unpacked and inspected directly (`npm pack`, `tar -xzf`) for constructor signature, `includeUI.loadImage` blank-canvas behavior (`src/js/ui.js`), CSS asset structure (`dist/tui-image-editor.css` base64-embedded icons), and dependency graph (`fabric@^4.2.0`, `tui-code-snippet@^2.3.3`, `tui-color-picker@^2.2.6`)
- `npm view` (registry) — confirmed exact versions, `peerDependencies`, `time.created`/`time.modified` for `tui-image-editor`, `tui-color-picker`, `@toast-ui/vue-image-editor`
- `npm view @types/tui-image-editor` — confirmed 404 (no type definitions exist anywhere)
- Laravel 13.x official docs (laravel.com/docs/13.x/filesystem) — `serve` option, `Storage::temporaryUrl()`, local disk behavior
- This codebase, read directly: `app/Enums/JobOrderStatus.php`, `app/Enums/UserRole.php`, `app/Observers/AuditObserver.php`, `app/Support/AuditLogger.php`, `app/Models/JobOrder.php`, `app/Models/User.php`, `app/Policies/UserPolicy.php`, `app/Http/Requests/Owner/DeactivateUserRequest.php`, `app/Http/Controllers/Owner/UserManagementController.php`, `app/Actions/JobOrder/AssignArtistToJobOrder.php`, `app/Actions/JobOrder/ValidateJobOrderFile.php`, `app/Http/Controllers/FrontlineStaff/JobOrderController.php`, `app/Http/Requests/FrontlineStaff/ReplaceJobOrderFileRequest.php`, `resources/js/components/ReplaceJobOrderFileDialog.vue`, `routes/portals.php`, `routes/owner.php`, `database/factories/*.php`, `tests/Feature/FrontlineStaff/JobOrderProcessingTest.php`, `config/filesystems.php`, `vite.config.ts`, `package.json`, `composer.json`, `phpunit.xml`, `tests/Pest.php`

### Secondary (MEDIUM confidence)

- GitHub `nhn/tui.image-editor` issue #788 ("Vue 3 Support") — WebFetch-verified: open, filed June 2022, unresolved, no timeline
- WebSearch cross-verification: `nhn/toast-ui.react-image-editor` confirmed archived 2021-07-28 (read-only), corroborating the framework-wrapper-deprecation pattern for the Vue wrapper too

### Tertiary (LOW confidence)

- General WebSearch summaries about `fabric@4.x`-era CJS/bundler interop quirks (Pitfall 7) — not verified against this project's actual Vite 8 build; flagged explicitly for Wave-0 spike verification rather than stated as fact

## Metadata

**Confidence breakdown:**

- Standard stack: MEDIUM — package identities/versions/legitimacy are HIGH confidence (verified via npm registry, official GitHub, slopcheck); the _integration approach_ (core-only, custom wrapper) is well-supported by source inspection but has zero prior in-repo precedent to validate against, hence MEDIUM overall
- Architecture: HIGH — every pattern except the TOAST UI wrapper itself (Patterns 1, 2, 3, 5) is a direct, already-proven precedent in this codebase
- Pitfalls: MEDIUM-HIGH — Pitfalls 1-5 are verified against library source/official docs; Pitfall 6 is an open design question (correctly flagged, not asserted as fact); Pitfall 7 is genuinely speculative and labeled as such

**Research date:** 2026-09-02
**Valid until:** 30 days (stable ecosystem — `tui-image-editor` hasn't shipped a release since 2022, and this project's own Laravel/Inertia stack is pinned; re-verify only if `package.json`/`composer.json` versions change before planning executes)
