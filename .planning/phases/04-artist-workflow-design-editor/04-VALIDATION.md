---
phase: 04
slug: artist-workflow-design-editor
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-09-02
---

# Phase 04 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 5.1.3 + pestphp/pest-plugin-laravel 5.0.1 |
| **Config file** | phpunit.xml (suites: Unit, Feature) |
| **Quick run command** | `php artisan test --compact --filter=<TestName>` (or `vendor/bin/pest --filter=<name>`) |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15-30 seconds (full suite, SQLite in-memory) |

Feature tests are globally bound to `Tests\TestCase` + `RefreshDatabase` via `tests/Pest.php`'s `pest()->extend(...)->in('Feature')` call — new Phase 4 feature tests need no per-file `uses()` boilerplate as long as they live under `tests/Feature/`.

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=<relevant test>`
- **After every plan wave:** Run `php artisan test --compact` (full suite)
- **Before `/gsd-verify-work`:** Full suite green, plus `vendor/bin/pint --dirty --format agent` and `npm run types:check` for every touched PHP/TS file
- **Max feedback latency:** ~30 seconds

---

## Per-Task Verification Map

| Task ID | Requirement | Behavior | Test Type | Automated Command | File Exists | Status |
|---------|-------------|----------|-----------|---------------------|-------------|--------|
| 04-xx | JOB-03 | Artist saves consultation notes on an assigned job order | feature | `pest --filter="consultation notes"` | ❌ W0 | ⬜ pending |
| 04-xx | JOB-09 | "Next" claims oldest `Assigned` job order for that artist; Forward/Not-Appear don't reassign | feature | `pest --filter="next.*forward.*not.appear"` | ❌ W0 | ⬜ pending |
| 04-xx | JOB-04 | Design editor page renders with a signed image URL prop (or null for blank canvas) | feature | `pest --filter="design editor"` | ❌ W0 | ⬜ pending |
| 04-xx | JOB-05 | Send-for-review creates a `revision_logs` row and overwrites `design_files` every time, including the first submission | feature | `pest --filter="send for review"` | ❌ W0 | ⬜ pending |
| 04-xx | JOB-06 | A `DesignApproved` job order's design file rejects further edits/overwrites server-side | feature | `pest --filter="locked design"` | ❌ W0 | ⬜ pending |
| 04-xx | JOB-07 | Non-Owner is forbidden from unlock; Owner unlock writes an `audit_trail` row | feature | `pest --filter="unlock override"` | ❌ W0 | ⬜ pending |
| 04-xx | JOB-08 | Setting `OnBreak`/`OffShift` flips `is_available` false; returning to `Available` claims oldest unassigned job order | feature | `pest --filter="session status"` | ❌ W0 | ⬜ pending |
| 04-xx | JOB-10 | Performance report aggregates jobs completed / avg revisions / SLA adherence over a date range | feature | `pest --filter="performance report"` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky. Task IDs filled in by the planner once plan/task numbering exists.*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Artist/ConsultationNotesTest.php` — covers JOB-03
- [ ] `tests/Feature/Artist/QueueControlsTest.php` — covers JOB-09
- [ ] `tests/Feature/Artist/DesignEditorTest.php` — covers JOB-04 (server-side prop-shape assertions only; no Dusk/Playwright in this project, so canvas rendering itself is manual QA)
- [ ] `tests/Feature/Artist/SendForReviewTest.php` — covers JOB-05, JOB-07 (audit assertion), JOB-08 (revision counting downstream)
- [ ] `tests/Feature/Artist/DesignLockTest.php` — covers JOB-06
- [ ] `tests/Feature/Owner/UnlockDesignFileTest.php` — covers JOB-07
- [ ] `tests/Feature/Artist/SessionStatusTest.php` — covers JOB-08
- [ ] `tests/Feature/Artist/PerformanceReportTest.php` — covers JOB-10
- [ ] `database/factories/DesignFileFactory.php`, `database/factories/RevisionLogFactory.php` — follow `JobOrderFactory`'s `afterCreating()` pattern
- [ ] Wave-0 spike: mount `tui-image-editor` core in a Vue 3 component, load a blank canvas, call `toDataURL()`, confirm `npm run types:check` / `vp check` pass with a hand-written `.d.ts` shim — de-risks Pitfall 1/2/7 from 04-RESEARCH.md before the full "Send for Review" slice is built on top of it

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|--------------------|
| TOAST UI editor renders correctly, tools function (crop/flip/rotate/draw/shape/icon/text/filter), color picker is styled | JOB-04 | No Dusk/Playwright in this project — canvas-level rendering and interaction is outside Pest's reach | Open the Artist design editor page in a browser; verify menu bar renders, blank canvas and existing-image loading both work, each tool is usable, color picker is styled (not a bare unstyled `<div>`), no NHN telemetry request fires in the network tab |
| Exported PNG visually matches what was edited in the canvas | JOB-05 | Client-side `toDataURL()` → multipart upload round-trip; asserting pixel content isn't practical in Pest | After editing and clicking "Send for Review", download/view the stored `design_files` PNG and confirm it matches the canvas |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 30s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-09-02
