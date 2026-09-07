---
phase: 07-accounts-receivable
plan: 03
subsystem: backend

tags: [laravel, mailable, scheduled-command, accounts-receivable, reminders]

# Dependency graph
requires:
  - phase: 07-accounts-receivable
    plan: 01
    provides: "accounts_receivable schema (due_at, last_reminder_bracket, collection_status), AccountsReceivableAgingBracket (rank()/reminderBearing()), AccountsReceivable::agingBracket()/daysPastDue()"
provides:
  - "AccountsReceivableReminder Mailable + Markdown view (bracket-driven subject/body, no deep link, no customer contact details)"
  - "ar:send-reminders scheduled command -- the project's first scheduled Artisan command, registered on Schedule::command()->daily()->withoutOverlapping()->onOneServer()"
  - "Auto-close-to-Paid mechanism (D-16) with zero Cashier-side code"
affects: [07-04, 07-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Per-entry try/catch around Mail::send(), report()-ed and never re-thrown, with the idempotency stamp write unconditionally after the try/catch block (04-13 mail-failure-isolation pattern)"
    - "Property-based $signature/$description on the console command (not the artisan-scaffolded #[Signature]/#[Description] attribute style), per the plan's explicit instruction since this is the project's first command with no prior sibling convention"

key-files:
  created:
    - app/Mail/AccountsReceivableReminder.php
    - resources/views/mail/accounts-receivable-reminder.blade.php
    - app/Console/Commands/SendAccountsReceivableReminders.php
    - tests/Unit/Mail/AccountsReceivableReminderMailableTest.php
    - tests/Feature/Console/SendAccountsReceivableRemindersTest.php
  modified:
    - routes/console.php

key-decisions:
  - "Single-line forceFill(['last_reminder_bracket' => ..., 'last_reminder_sent_at' => ...])->save() call (not multi-line) to satisfy the plan's literal grep-based acceptance criterion checking that exact substring"
  - "Property-based $signature/$description over PHP-attribute command metadata, per the plan's explicit locked instruction"

requirements-completed: [AR-02]

# Metrics
duration: 15min
completed: 2026-09-08
---

# Phase 7 Plan 03: AR Reminder Command & Mailable Summary

**A daily `ar:send-reminders` Artisan command fires exactly one escalating reminder email (Accounting Staff + Owner, never the customer) per AR entry per aging bracket crossing, auto-closes settled entries to Paid, and isolates Resend transport failures from the idempotency stamp.**

## Performance

- **Duration:** ~15 min (task work); environment setup — composer/npm install, migrate:fresh, npm run build — added on top since this worktree started with no `vendor/`, `node_modules/`, `.env`, or built assets
- **Started:** 2026-09-08T07:21:50+08:00 (worktree base)
- **Completed:** 2026-09-08T07:32:24+08:00
- **Tasks:** 2 completed
- **Files modified:** 6 (3 created + 1 test in Task 1, 2 created + 1 modified in Task 2)

## Accomplishments
- `AccountsReceivableReminder` Mailable with a bracket-driven, four-tier escalating subject line (AR notice → Urgent → Escalation → Final notice) and a Markdown body with no portal deep link and no customer contact details (UI-SPEC's binding rules, verified by grep)
- `ar:send-reminders` — the project's first scheduled Artisan command — correctly fires once per bracket crossing (D-07/D-17), skips the display-only 61-90 band, auto-closes a settled balance to `Paid` with zero Cashier-side code (D-16), and excludes already-closed entries from its query entirely (D-08)
- Mail transport failures are caught, reported, and never block the idempotency stamp — verified by a dedicated test using `Mail::shouldReceive('to')->andThrow(...)`
- Registered on `Schedule::command(...)->daily()->withoutOverlapping()->onOneServer()`, verified live via `php artisan schedule:list`

## Task Commits

Each task was committed atomically:

1. **Task 1: Mailable and Markdown view** - `7e11d31` (feat)
2. **Task 2: The reminder command, idempotency, mail-failure isolation, and scheduling** - `cadc035` (feat)

_Both tasks were TDD-flagged in the plan; each was implemented with its test written and passing in the same commit (not split into separate RED/GREEN commits) since the plan's `<action>`/`<behavior>` blocks specified the exact test content and implementation together._

## Files Created/Modified
- `app/Mail/AccountsReceivableReminder.php` - bracket-driven Mailable (subject/lead/closing all via `match($this->bracket)`), computes outstanding balance inline the same way `ReceiptController::show()` does
- `resources/views/mail/accounts-receivable-reminder.blade.php` - `<x-mail::message>` view, six detail-block captions, no button, no contact details
- `app/Console/Commands/SendAccountsReceivableReminders.php` - `ar:send-reminders`, `chunkById(50, ...)` query, `processOne()` auto-close + bracket-crossing logic, `reminderRecipients()` (Accounting Staff + Owner, `is_active = true`)
- `routes/console.php` - added `Schedule::command(SendAccountsReceivableReminders::class)->daily()->withoutOverlapping()->onOneServer();` below the existing `inspire` stub
- `tests/Unit/Mail/AccountsReceivableReminderMailableTest.php` - asserts the 90+ bracket subject starts with "Final notice"
- `tests/Feature/Console/SendAccountsReceivableRemindersTest.php` - 8 tests covering every `<behavior>` case (first-fire, same-day no-duplicate, bracket advance, 61-90 skip, auto-close-to-Paid, closed-entry exclusion, mail-failure isolation, schedule registration)

## Decisions Made
- Wrote the `forceFill(['last_reminder_bracket' => $bracket->value, 'last_reminder_sent_at' => now()])->save();` call as a single line (not Pint's usual multi-line array style) because the plan's acceptance criteria greps for that exact literal substring on one line; Pint's `--dirty --format agent` run left it untouched, confirming it doesn't violate the `laravel` preset.
- Used `protected $signature`/`protected $description` properties rather than the artisan-scaffolded `#[Signature]`/`#[Description]` attributes, per the plan's explicit locked instruction (`app/Console/Commands` was empty before this plan, so there was no existing sibling command to defer to instead).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking issue, environment setup] Worktree had no `vendor/`, `node_modules/`, `.env`, SQLite database, or built frontend assets**
- **Found during:** Start of Task 1 (`php artisan make:test` failed — no `vendor/autoload.php`)
- **Issue:** This worktree was provisioned with only the git-tracked source, matching the same gap 07-01 encountered
- **Fix:** Ran `composer install`, `cp .env.example .env`, `php artisan key:generate`, `touch database/database.sqlite`, `php artisan migrate:fresh`, `npm install`, `npm run build`
- **Files modified:** None tracked (environment-only); `npm install` again briefly renamed `package-lock.json`'s `name` field to the worktree directory name, reverted with `git checkout -- package-lock.json` before committing (same transient issue 07-01 documented)
- **Verification:** `vendor/bin/pest` and `php artisan` commands run correctly afterward
- **Committed in:** N/A (no tracked file changes from this setup)

---

**Total deviations:** 1 auto-fixed (environment setup, no code changes)
**Impact on plan:** None — purely local environment provisioning identical to 07-01's documented setup step. No scope creep; every file touched is inside this plan's declared `files_modified` list.

## Issues Encountered
- `composer types:check` (Larastan level 7) fails on the same 3 pre-existing files 07-01 already logged (`CreateCreditRequestRequest.php`, `SavePricingAndPaymentRequest.php`, `UpdateSystemConfigurationRequest.php` — a route-model-binding type-inference gap from Phase 5, unrelated to this plan's files). Not fixed here — out of scope per the plan's file boundary; already tracked in `.planning/phases/07-accounts-receivable/deferred-items.md`.
- Full suite (`vendor/bin/pest --compact`): 411 tests, 408 passed, 3 skipped, 0 failed — clean after the environment setup above.

## Known Stubs

None.

## Threat Flags

None — the plan's own `<threat_model>` fully covers this plan's surface (idempotency via `last_reminder_bracket` + `withoutOverlapping()`/`onOneServer()`, internal-only D-06 audience with no customer email, no deep link in the reminder body, per-entry mail-failure isolation). No new endpoints, auth paths, or trust boundaries were introduced — this command has no HTTP surface at all.

## User Setup Required

None — no external service configuration required. `resend/resend-php` was already installed and audited in Phase 4; this plan adds no new dependency.

## Next Phase Readiness
- `AccountsReceivableReminder` and `ar:send-reminders` are live and tested; 07-04 (collection status UI, write-off request) and 07-05 (Owner write-off queue, collection letter) can build on this without further changes here.
- No blockers.

---
*Phase: 07-accounts-receivable*
*Completed: 2026-09-08*

## Self-Check: PASSED

All 7 claimed files verified present on disk; all 3 claimed commit hashes (`7e11d31`, `cadc035`, `865c3a4`) verified present in `git log --oneline --all`.
