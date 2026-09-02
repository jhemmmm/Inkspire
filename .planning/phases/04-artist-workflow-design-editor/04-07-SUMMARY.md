---
phase: 04-artist-workflow-design-editor
plan: 07
subsystem: api
tags: [laravel, eloquent, enums, artist-session-status, round-robin-assignment, owner-portal]

# Dependency graph
requires:
  - phase: 04-artist-workflow-design-editor (04-03/04-05)
    provides: JobOrderQueueController's index()/next()/forward()/not-appear() queue, routes/portals.php's artist. route group, JobOrderStatus's consultation/design-review cases
provides:
  - ArtistStatus enum (Available/OnBreak/OffShift) plus artist_status/break_started_at columns on users
  - SetArtistSessionStatus action — single mutation point keeping is_available in sync with artist_status, first caller of AssignArtistToJobOrder::claimOldestUnassigned()
  - Three artist-facing session-status routes (start-break/end-break/end-shift) with source-state guards
  - Owner/Admin UserManagement list now exposes artist_status + server-computed exceeded_break_time per artist
affects: [phase-04-remaining-plans, phase-05-pos-payments (round-robin assignment dependency), owner-portal-frontend]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Session-status transitions as a single-invoke Action class (SetArtistSessionStatus), mirroring AssignArtistToJobOrder's plain-invokable shape"
    - "Server-computed boolean indicator (exceeded_break_time) instead of shipping a raw timestamp to the client for elapsed-time math"

key-files:
  created:
    - app/Enums/ArtistStatus.php
    - app/Actions/JobOrder/SetArtistSessionStatus.php
    - app/Http/Controllers/Artist/SessionStatusController.php
    - app/Http/Requests/Artist/UpdateSessionStatusRequest.php
    - database/migrations/2026_09_02_123917_add_artist_status_columns_to_users_table.php
    - tests/Feature/Artist/SessionStatusTest.php
  modified:
    - app/Models/User.php
    - app/Http/Controllers/Artist/JobOrderQueueController.php
    - app/Http/Controllers/Owner/UserManagementController.php
    - routes/portals.php
    - tests/Feature/Owner/UserManagementTest.php

key-decisions:
  - "artist_status and break_started_at kept outside User's #[Fillable] list (system-computed, written only via forceFill()), matching the existing is_available/last_assigned_at precedent"
  - "now()->diffInMinutes($breakStartedAt, absolute: true) — Carbon 3's diffInMinutes() defaults to a signed difference, so absolute: true is required (matches the established EnforceIdleSessionTimeout convention)"

patterns-established:
  - "First real caller of AssignArtistToJobOrder::claimOldestUnassigned() (forward-declared since Phase 3) — proves D-13's is_available/artist_status sync contract end-to-end"

requirements-completed: [JOB-08]

# Metrics
duration: ~30min
completed: 2026-09-02
---

# Phase 4 Plan 07: Artist Session Status Summary

**3-state `artist_status` enum (Available/OnBreak/OffShift) kept in lockstep with `is_available`, with returning-to-Available auto-claiming the artist's oldest unassigned Type B job order via the previously caller-less `claimOldestUnassigned()`**

## Performance

- **Duration:** ~30 min (includes one-time worktree environment setup: `composer install`, `npm install`, `npm run build`, `.env`/sqlite/migrate bootstrap — none of which existed in this fresh worktree)
- **Completed:** 2026-09-02T12:46Z
- **Tasks:** 3/3
- **Files modified:** 11 (7 created, 4 modified)

## Accomplishments
- `ArtistStatus` enum + `artist_status`/`break_started_at` columns on `users`, both intentionally outside `#[Fillable]` (system-computed only)
- `SetArtistSessionStatus` action: single mutation point that keeps `is_available` derived from `artist_status`, stamps/clears `break_started_at`, and — on return to Available — invokes `AssignArtistToJobOrder::claimOldestUnassigned()` for the first time in the codebase
- `SessionStatusController` with three source-state-guarded transitions (start-break/end-break/end-shift), each flashing the exact Copywriting Contract toast wording
- Owner/Admin's `UserManagement` list now exposes each artist's `artist_status` plus a passive, server-computed `exceeded_break_time` boolean (never a raw timestamp)

## Task Commits

Each task was committed atomically:

1. **Task 1: Domain layer — ArtistStatus enum, users columns, model casts** - `3511263` (feat)
2. **Task 2: Session status transitions & Dashboard prop** - `90f691c` (feat)
3. **Task 3: Owner-facing artist status & exceeded-break indicator** - `25c2a71` (feat)

**Plan metadata:** committed separately by the worktree's final-commit step (SUMMARY.md only — STATE.md/ROADMAP.md are owned by the orchestrator in worktree mode)

## Files Created/Modified
- `app/Enums/ArtistStatus.php` - `Available`/`OnBreak`/`OffShift`, TitleCase-key/snake_case-value convention matching `UserRole`
- `database/migrations/2026_09_02_123917_add_artist_status_columns_to_users_table.php` - additive `artist_status` (default `'available'`) + `break_started_at` (nullable) on `users`
- `app/Models/User.php` - PHPDoc + `casts()` additions for both new columns, neither added to `#[Fillable]`
- `app/Actions/JobOrder/SetArtistSessionStatus.php` - `__invoke(User $artist, ArtistStatus $status)`, syncs `is_available`/`break_started_at`, claims oldest unassigned Type B job order on return to Available
- `app/Http/Controllers/Artist/SessionStatusController.php` - `startBreak()`/`endBreak()`/`endShift()`, each `abort_unless`-guarded on the required source state
- `app/Http/Requests/Artist/UpdateSessionStatusRequest.php` - body-less FormRequest, mirrors `UpdateQueueEntryStatusRequest`/`UpdateJobOrderQueuePositionRequest`
- `routes/portals.php` - three new `artist.session-status.*` routes; Wayfinder regenerated with `--with-form`
- `app/Http/Controllers/Artist/JobOrderQueueController.php` - `index()` now exposes `artistStatus` in the Inertia props
- `app/Http/Controllers/Owner/UserManagementController.php` - `index()` maps each user row, scoping `artist_status`/`exceeded_break_time` to Artist-role rows only
- `tests/Feature/Artist/SessionStatusTest.php` - 7 cases: start/end break, claim-on-return, end-shift from both active states, both guard-violation 422s, dashboard prop exposure
- `tests/Feature/Owner/UserManagementTest.php` - 2 new cases (role-scoping, exceeded-break threshold), 7 existing cases unchanged

## Decisions Made
- `artist_status`/`break_started_at` follow the exact non-`#[Fillable]`, `forceFill()`-only precedent Phase 3 already established for `is_available`/`last_assigned_at` — no new pattern introduced.
- `now()->diffInMinutes($user->break_started_at, absolute: true)` — matched the codebase's existing documented Carbon 3 signed-diff gotcha (`app/Http/Middleware/EnforceIdleSessionTimeout.php`) rather than rediscovering it.
- Adjusted the `SetArtistSessionStatus` docblock wording so the literal string `claimOldestUnassigned` appears exactly once in the file (matching the plan's acceptance criterion `grep -c` check), without losing the cross-reference documentation.

## Deviations from Plan

None — plan executed exactly as written. One acceptance-criterion-driven micro-adjustment (docblock wording in `SetArtistSessionStatus.php` to keep the `grep -c "claimOldestUnassigned"` check at exactly 1) is documented above under Decisions Made rather than as a deviation, since it changed no behavior.

## Issues Encountered
- Fresh worktree had no `vendor/`, no `node_modules/`, no `.env`, no `database/database.sqlite`, and no `public/build/` (Vite manifest) — none of which are part of version control. Bootstrapped all four (`composer install`, `npm install && npm run build`, `.env` from `.env.example` + `key:generate`, `touch database/database.sqlite` + `migrate`) before any task work could begin or be verified. This is one-time worktree setup, not a plan deviation.
- `npm install` rewrote `package-lock.json`'s top-level `name` field to the worktree's directory name (`agent-a15434970184806c0` instead of `inkspire`) — reverted via `git checkout -- package-lock.json` before committing; not a real dependency change.
- Full-repo `phpstan analyse` (not required by this plan's own `<verification>` block, run as an extra precaution) surfaced 2 pre-existing errors in files this plan does not touch (`app/Http/Controllers/FrontlineStaff/QueueEntryController.php`, `app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php`), both dating to Phase 2/3 commits. Logged to `deferred-items.md` per the Scope Boundary rule rather than fixed.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- JOB-08 fully satisfied: `is_available` stays queryable exactly as `AssignArtistToJobOrder`'s existing round-robin query expects (D-13), proven end-to-end by the "returning to available claims the oldest unassigned type b job order" test.
- Owner-facing `exceeded_break_time` indicator (D-14) is ready for Plan 04-08's frontend to consume as a plain boolean.
- `artistStatus` Inertia prop is available on `artist/Dashboard` for the frontend (Plan 04-08) to render session-status controls.
- No blockers for subsequent Phase 4 plans or Phase 5.

---
*Phase: 04-artist-workflow-design-editor*
*Completed: 2026-09-02*
