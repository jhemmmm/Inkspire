# Phase 4: Artist Workflow & Design Editor - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-02
**Phase:** 4-artist-workflow-design-editor
**Areas discussed:** Consultation & own-queue flow, Design review & approval, Design editor scope & files, Artist session status

---

## Consultation & own-queue flow

| Question                                 | Options                                                                                                      | Selected                                                 |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------- |
| Consultation notes — where do they live? | On the existing job order / Separate consultation record                                                     | **On the existing job order**                            |
| JOB-03's "generate a job order" meaning  | Imprecise wording — Artist finalizes the existing JO / Artist can create additional new JOs mid-consultation | **Imprecise wording — Artist finalizes the existing JO** |
| Next/Forward/Not-Appear mechanics        | New status values on job_orders.status / Separate consultation_queue table                                   | **New status values on job_orders.status**               |
| Not-Appear semantics                     | Stays with the same Artist, just deprioritized / Unassigned and returned to the round-robin pool             | **Stays with the same Artist, just deprioritized**       |

**Notes:** All four answers matched the recommended option. Confirmed via demo's `main.js` that the per-artist call-next pattern (Next/Forward/Not-Appear) is distinct from Frontline's own queue.

---

## Design review & approval

| Question                                                        | Options                                                                                              | Selected                                          |
| --------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- | ------------------------------------------------- |
| Who approves a design?                                          | Artist records the client's in-person verdict / Routes to another role for approval                  | **Artist records the client's in-person verdict** |
| Design review statuses                                          | Extend JobOrderStatus enum (InDesign → PendingReview → DesignApproved) / Separate review-state field | **Extend JobOrderStatus enum**                    |
| Does every Send for Review log a revision, including the first? | Yes — every Send for Review logs a revision / Only actual change-driven resubmissions                | **Yes — every Send for Review logs a revision**   |
| design_files versioning                                         | Single current row, overwritten each revision / New row per version                                  | **Single current row, overwritten each revision** |

**Notes:** Confirmed there is no customer portal or separate reviewer role anywhere in the project — the demo shows the Artist clicking through the client's verbal approval directly.

---

## Design editor scope & files

| Question                                 | Options                                                                                      | Selected                                                     |
| ---------------------------------------- | -------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| TOAST UI npm package approval            | Approve @toast-ui/vue-image-editor + tui-image-editor / Use a different library              | **Approve @toast-ui/vue-image-editor + tui-image-editor**    |
| What does Send for Review save?          | Flattened export overwrites design_files each time / Persist full editable project state     | **Flattened export overwrites design_files each time**       |
| Blank canvas or importable source asset? | Blank canvas by default, optional image upload to import / Blank canvas only                 | **Blank canvas by default, optional image upload to import** |
| Export file storage                      | PNG export via the same local-disk pattern as job_orders.file_path / Multiple export formats | **PNG export via the same local-disk pattern**               |

**Notes:** No image-editor dependency exists in the project yet — this is a genuinely new Composer/npm addition, explicitly approved here per CLAUDE.md's dependency-change rule.

---

## Artist session status

| Question                                 | Options                                                                                         | Selected                                             |
| ---------------------------------------- | ----------------------------------------------------------------------------------------------- | ---------------------------------------------------- |
| Does JOB-08 need more than is_available? | Add artist_status enum (Available/OnBreak/OffShift) + break_started_at / Keep just is_available | **Add artist_status enum + break_started_at**        |
| Break exceeds max_artist_break_minutes   | Passive over-limit flag, no auto state change / Auto-return via scheduled job                   | **Passive over-limit flag**                          |
| End Shift with in-progress job orders    | Allowed anytime, JOs wait for next shift / Blocked while actively in-progress                   | **Allowed anytime**                                  |
| JOB-10 performance report scope          | Jobs completed, avg revisions/job, SLA adherence over date range / Just a completed-jobs count  | **Jobs completed, avg revisions/job, SLA adherence** |

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

---

## 2026-09-03 Expansion Session

> Post-UAT: user requested client remote design review and Photoshop file import. Both fall outside Phase 4's original ROADMAP.md goal (strictly artist-side, in-person) — flagged as scope creep per the discuss-phase scope guardrail; user explicitly chose "Force into Phase 4 anyway" over inserting a new phase or logging to backlog.

**Date:** 2026-09-03
**Phase:** 4-artist-workflow-design-editor
**Areas discussed:** Notification channel & provider, Remote link security & lifecycle, What the client can do on that page, PSD import path

---

## Scope path (pre-discussion gate)

| Option                    | Description                                                          | Selected |
| ------------------------- | -------------------------------------------------------------------- | -------- |
| Insert as new phase now   | Own phase via gsd-phase --insert, keeps Phase 4 traceable            |          |
| Force into Phase 4 anyway | Fold directly into 04-CONTEXT.md, breaks phase-boundary traceability | ✓        |
| Log to backlog only       | Note in REQUIREMENTS.md, build later                                 |          |

**User's choice:** Force into Phase 4 anyway.

---

## Notification channel & provider

| Question         | Options                                          | Selected       |
| ---------------- | ------------------------------------------------ | -------------- |
| Delivery channel | Email only / SMS only / Both                     | **Email only** |
| Email provider   | Resend / Postmark / AWS SES / log driver for now | **Resend**     |

**Notes:** No SMS gateway exists anywhere in the project (checked composer.json/package.json). `config/services.php` already stubs `resend`/`postmark`/`ses` keys but none are populated — Resend requires adding `resend/resend-php` and setting `RESEND_API_KEY`.

---

## Remote link security & lifecycle

| Question              | Options                                                                                                 | Selected                                |
| --------------------- | ------------------------------------------------------------------------------------------------------- | --------------------------------------- |
| Link mechanism        | Laravel signed URL (temporarySignedRoute) / Stored random token column                                  | **Signed URL**                          |
| Expiry & staleness    | 7 days, new revision invalidates old link / No expiry, always shows latest                              | **7 days, invalidated by new revision** |
| Artist-vs-client race | First verdict wins, existing 422 guard rejects the second / New "review in progress" coordination state | **First verdict wins**                  |

---

## What the client can do on that page

| Question   | Options                                                                                   | Selected                                 |
| ---------- | ----------------------------------------------------------------------------------------- | ---------------------------------------- |
| Page scope | Design image + Approve/Request Changes only / Same + free-text comment on Request Changes | **Image + Approve/Request Changes only** |

**Notes:** Comment field explicitly declined to match the in-person path, which also carries no reason today — logged as a deferred idea for both paths together.

---

## PSD import path

| Question                 | Options                                                                                             | Selected                    |
| ------------------------ | --------------------------------------------------------------------------------------------------- | --------------------------- |
| Where flattening happens | Server-side via Imagick (confirmed working: PSD delegate present) / Client-side via a JS PSD parser | **Client-side (JS parser)** |
| Import entry point       | Extend existing "Import Reference Image" button / Separate "Import Photoshop File" button           | **Extend existing button**  |
| Library                  | ag-psd (browser-native, no fs/Buffer dependency) / psd.js (Node-oriented, needs polyfilling)        | **ag-psd**                  |
| Parse-failure behavior   | Fail loud with a specific error message / Silently fall back to blank canvas                        | **Fail loud**               |

**Notes:** User chose client-side parsing over the server-side Imagick recommendation, despite Imagick's PSD delegate being confirmed working live on this box (`(new Imagick())->queryFormats("PSD")` → `[PSD]`) — explicit preference, not a technical constraint.

---

## Claude's Discretion (2026-09-03)

- Where the new public review route lives (new route group vs. inline in `routes/portals.php` outside `role:*` groups).
- Exact Mailable class name/structure/subject line — no existing precedent in this app.
- Exact wording of "link expired," "already reviewed," and "PSD parse failed" messages.

## Deferred Ideas (2026-09-03)

- **SMS delivery** for the remote review link — build later if email-only proves insufficient; needs its own provider decision (e.g. Semaphore for PH numbers).
- **Free-text comment field** on "Request Changes" (both in-person and remote paths) — revisit if artists report not knowing what to change.
