# Phase 3: Job Order Intake & Auto-Assignment - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-02
**Phase:** 3-job-order-intake-auto-assignment
**Areas discussed:** Type A file validation, Artist availability data, Round-robin & no-artist fallback, Post-validation/assignment status

---

## Type A File Validation

| Option | Description | Selected |
|--------|-------------|----------|
| Raster-only DPI check | jpg/png get real DPI validation via getimagesize()/exif; pdf/ai/eps skip DPI, validated on format+size only | ✓ |
| Full DPI check via Imagick for all formats | Rasterize pdf/ai/eps through Ghostscript+Imagick for DPI too | |
| Vector formats always fail Type A validation | pdf/ai/eps always route to manual/Type-B-style handling regardless of DPI | |

**User's choice:** Raster-only DPI check (recommended)
**Notes:** Sidesteps the unverified Ghostscript/Imagick-on-Laravel-Cloud risk flagged in STATE.md; DPI isn't a meaningful concept for vector content until rasterized.

| Option | Description | Selected |
|--------|-------------|----------|
| Flag for re-upload | Job order gets 'Validation Failed' status; Frontline must replace the file; stays Type A | ✓ |
| Auto-convert to Type B | A failed Type A file becomes Type B and enters the artist queue | |
| Something else | — | |

**User's choice:** Flag for re-upload (recommended)

| Option | Description | Selected |
|--------|-------------|----------|
| Synchronous | Runs during the intake request itself, immediate feedback | ✓ |
| Asynchronous (queued job) | Dispatches to the database queue, job order shows 'Validating...' | |

**User's choice:** Synchronous (recommended)
**Notes:** Justified by the raster-only decision — no slow Ghostscript step remains in the critical path.

---

## Artist Availability Data

| Option | Description | Selected |
|--------|-------------|----------|
| Substrate now, UI in Phase 4 | Add an availability column to users now, defaulting true; Phase 4 builds the real toggle | ✓ |
| Ignore availability entirely for now | Treat every active Artist as available, no new column | |
| Something else | — | |

**User's choice:** Substrate now, UI in Phase 4 (recommended)
**Notes:** Same forward-reference pattern as Phase 2 storing an unvalidated file for Phase 3 to check.

| Option | Description | Selected |
|--------|-------------|----------|
| Single boolean: is_available | One column, defaults true for Artist-role users | ✓ |
| Enum: availability_status | Available/OnBreak/OffShift string-backed enum | |

**User's choice:** Single boolean: is_available

---

## Round-Robin & No-Artist Fallback

| Option | Description | Selected |
|--------|-------------|----------|
| Last-assigned timestamp | last_assigned_at column on users; oldest/null picked next | ✓ |
| Rotating pointer | Single 'whose turn is next' pointer cycling through artists | |

**User's choice:** Last-assigned timestamp (recommended)

| Option | Description | Selected |
|--------|-------------|----------|
| Stays unassigned, picked up later | Next artist to become available claims the oldest unassigned Type B job | ✓ |
| Assign anyway, ignore availability | Falls back to any Artist-role user when nobody is available | |
| Something else | — | |

**User's choice:** Stays unassigned, picked up later (recommended)

---

## Post-Validation/Assignment Status

| Option | Description | Selected |
|--------|-------------|----------|
| Ready for Production / Assigned | Type A pass → 'Ready for Production'; Type B assigned → 'Assigned' | ✓ |
| Single generic 'Processed' status | Both paths collapse into one status value | |
| Something else | — | |

**User's choice:** Ready for Production / Assigned (recommended)

| Option | Description | Selected |
|--------|-------------|----------|
| Dedicated status value | JobOrderStatus::ValidationFailed as its own enum case | ✓ |
| Flag on top of Intake | validation_failed boolean/reason column, status stays Intake | |

**User's choice:** Dedicated status value (recommended)

---

## Claude's Discretion

- Exact enum case naming/string values (`ValidationFailed`, `ReadyForProduction`, `Assigned`, `is_available`) — follow existing TitleCase-key/string-value convention
- Assigned-artist tracking: direct FK on `job_orders` vs. separate table — direct FK favored, given the approved 12-table ERD has no assignment table
- Where the "claim oldest unassigned job" check lives — inline in availability-flip action vs. dedicated action/service class
- Exact Form Request rules for the raster DPI check and vector format/size check, reading thresholds from `system_configurations`

## Deferred Ideas

None — discussion stayed within phase scope.
