# Phase 4: Artist Workflow & Design Editor - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-02
**Phase:** 4-artist-workflow-design-editor
**Areas discussed:** Consultation & own-queue flow, Design review & approval, Design editor scope & files, Artist session status

---

## Consultation & own-queue flow

| Question | Options | Selected |
|---|---|---|
| Consultation notes — where do they live? | On the existing job order / Separate consultation record | **On the existing job order** |
| JOB-03's "generate a job order" meaning | Imprecise wording — Artist finalizes the existing JO / Artist can create additional new JOs mid-consultation | **Imprecise wording — Artist finalizes the existing JO** |
| Next/Forward/Not-Appear mechanics | New status values on job_orders.status / Separate consultation_queue table | **New status values on job_orders.status** |
| Not-Appear semantics | Stays with the same Artist, just deprioritized / Unassigned and returned to the round-robin pool | **Stays with the same Artist, just deprioritized** |

**Notes:** All four answers matched the recommended option. Confirmed via demo's `main.js` that the per-artist call-next pattern (Next/Forward/Not-Appear) is distinct from Frontline's own queue.

---

## Design review & approval

| Question | Options | Selected |
|---|---|---|
| Who approves a design? | Artist records the client's in-person verdict / Routes to another role for approval | **Artist records the client's in-person verdict** |
| Design review statuses | Extend JobOrderStatus enum (InDesign → PendingReview → DesignApproved) / Separate review-state field | **Extend JobOrderStatus enum** |
| Does every Send for Review log a revision, including the first? | Yes — every Send for Review logs a revision / Only actual change-driven resubmissions | **Yes — every Send for Review logs a revision** |
| design_files versioning | Single current row, overwritten each revision / New row per version | **Single current row, overwritten each revision** |

**Notes:** Confirmed there is no customer portal or separate reviewer role anywhere in the project — the demo shows the Artist clicking through the client's verbal approval directly.

---

## Design editor scope & files

| Question | Options | Selected |
|---|---|---|
| TOAST UI npm package approval | Approve @toast-ui/vue-image-editor + tui-image-editor / Use a different library | **Approve @toast-ui/vue-image-editor + tui-image-editor** |
| What does Send for Review save? | Flattened export overwrites design_files each time / Persist full editable project state | **Flattened export overwrites design_files each time** |
| Blank canvas or importable source asset? | Blank canvas by default, optional image upload to import / Blank canvas only | **Blank canvas by default, optional image upload to import** |
| Export file storage | PNG export via the same local-disk pattern as job_orders.file_path / Multiple export formats | **PNG export via the same local-disk pattern** |

**Notes:** No image-editor dependency exists in the project yet — this is a genuinely new Composer/npm addition, explicitly approved here per CLAUDE.md's dependency-change rule.

---

## Artist session status

| Question | Options | Selected |
|---|---|---|
| Does JOB-08 need more than is_available? | Add artist_status enum (Available/OnBreak/OffShift) + break_started_at / Keep just is_available | **Add artist_status enum + break_started_at** |
| Break exceeds max_artist_break_minutes | Passive over-limit flag, no auto state change / Auto-return via scheduled job | **Passive over-limit flag** |
| End Shift with in-progress job orders | Allowed anytime, JOs wait for next shift / Blocked while actively in-progress | **Allowed anytime** |
| JOB-10 performance report scope | Jobs completed, avg revisions/job, SLA adherence over date range / Just a completed-jobs count | **Jobs completed, avg revisions/job, SLA adherence** |

**Notes:** `max_artist_break_minutes` and `default_sla_days` system config keys already exist from Phase 1 — this phase is the first to actually consume them.

---

## Claude's Discretion

- Exact enum case naming/string values for all new statuses.
- Exact schema/columns for the new `design_files` and `revision_logs` tables.
- Whether "not appeared" is a boolean flag or its own status value.
- UI layout of the Artist's own queue view, consultation-notes form, and performance report.
- Where `artist_status` transition logic lives (model method vs. small action/service class).

## Deferred Ideas

None — discussion stayed within Phase 4 scope. No new-capability suggestions came up.
