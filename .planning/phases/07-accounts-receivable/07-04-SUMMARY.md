---
phase: 07-accounts-receivable
plan: 04
subsystem: ui

tags: [laravel, inertia, vue, accounts-receivable, collection-status, printable-letter]

# Dependency graph
requires:
  - phase: 07-accounts-receivable
    plan: 02
    provides: "AccountsReceivableController's Show.vue entry detail page with the two 07-04/07-05 insertion-point HTML comments, accounts-receivable.show route"
  - phase: 07-accounts-receivable
    plan: 01
    provides: "AccountsReceivableAgingBracket/CollectionStatus enums, AccountsReceivable::agingBracket()/daysPastDue()"
provides:
  - "AccountsReceivableValidationRules::collectionStatusRules() -- allowlists only the four human-settable collection_status values (D-09/D-10)"
  - "CollectionStatusController::update() -- PATCH endpoint, server-side re-check of Active status and non-terminal collection_status independent of client UI"
  - "AccountsReceivableAgingBracket::letterBody() -- bracket-driven collection letter copy (D-12), never persisted"
  - "CollectionLetterController::show() + accounting-staff/CollectionLetter.vue -- printable, read-only collection letter quoting the derived outstanding balance as Amount Due (D-11/D-12)"
  - "Show.vue's Collection Status panel and Print Collection Letter action"
affects: [07-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "letterBody() returns a bracket-selected template string with literal {due date}/{n} placeholder tokens; the Vue page interpolates them at render time via computed .replaceAll() -- keeps the enum method free of due-date/days-past-due parameters while still matching the UI-SPEC Copywriting Contract's exact literal text"

key-files:
  created:
    - app/Concerns/AccountsReceivableValidationRules.php
    - app/Http/Requests/AccountingStaff/UpdateCollectionStatusRequest.php
    - app/Http/Controllers/AccountingStaff/CollectionStatusController.php
    - app/Http/Controllers/AccountingStaff/CollectionLetterController.php
    - resources/js/pages/accounting-staff/CollectionLetter.vue
    - tests/Feature/AccountingStaff/CollectionStatusTest.php
    - tests/Feature/AccountingStaff/CollectionLetterTest.php
  modified:
    - app/Enums/AccountsReceivableAgingBracket.php
    - routes/portals.php
    - resources/js/pages/accounting-staff/AccountsReceivable/Show.vue

key-decisions:
  - "letterBody() keeps {due date}/{n} as literal placeholder tokens (matching 07-UI-SPEC.md's Copywriting Contract text verbatim) rather than accepting parameters -- the Vue page does the interpolation at render time using its own dueDate/daysPastDue props, keeping the enum method a pure, argument-free bracket-to-copy lookup"
  - "CollectionLetterController passes an additional jobOrderDescription prop beyond the plan's literal prop list -- required by 07-UI-SPEC.md's Reference line ('Re: Job Order {JO number} — {job order description}'), which the plan's controller instructions omitted"
  - "Task 1's RED/GREEN split was not applied -- test and implementation were written and committed together in a single feat commit, following the same precedent 07-03 already established for tdd=\"true\" tasks whose <action> block specifies test content and implementation together"

requirements-completed: [AR-03]

# Metrics
duration: ~45min
completed: 2026-09-08
---

# Phase 7 Plan 04: Collection Status & Collection Letter Summary

**Accounting Staff can freely move an Active entry's collection status between Pending/Follow-up/Warning Sent/Collections (never Paid/Written Off) and print a bracket-appropriate collection letter that quotes the true, derived outstanding balance as Amount Due.**

## Performance

- **Duration:** ~45 min (including fresh-worktree bootstrap: composer/npm install, .env, sqlite db, migrate:fresh, asset build -- this worktree started with no `vendor/`, `node_modules/`, `.env`, or built assets, matching every prior Phase 7 plan's documented gap)
- **Started:** 2026-09-08 (worktree base, `b56e200`)
- **Completed:** 2026-09-08
- **Tasks:** 2 completed
- **Files modified:** 10 (7 created, 3 modified)

## Accomplishments
- `CollectionStatusController::update()` accepts only the four human-settable `collection_status` values -- `paid`/`written_off` are excluded from the request's own validation allowlist, never reachable from a crafted request (D-09/D-10)
- The same endpoint independently re-verifies the entry is `Active` and not already `paid`/`written_off` server-side, regardless of what the client's UI happens to render (T-07-04-02)
- `AccountsReceivableAgingBracket::letterBody()` returns the correct one of four bracket-selected letter bodies (61-90 and 90+ share the final-notice text, per D-12), throwing for the unreachable `Current` case
- `CollectionLetterController::show()` 200s with `pastDue: false` for a not-yet-due entry (never a 404) and quotes the live derived outstanding balance -- job order total minus completed transactions -- as `amountDue`, never the stored `accounts_receivable.balance` column
- `Show.vue` gained a Collection Status panel (Select limited to the four values, inside an Inertia `<Form>`) and a Print Collection Letter action, both replaced by the UI-SPEC's exact muted explanatory copy for a terminal or not-yet-due entry
- `CollectionLetter.vue` matches `cashier/Receipt.vue`'s read-only-render + `window.print()` shape exactly, with a not-yet-due guard screen and zero editable fields

## Task Commits

Each task was committed atomically:

1. **Task 1: Collection status update + printable collection letter backend** - `7000166` (feat)
2. **Task 2: Collection Status panel on Show.vue + new CollectionLetter.vue print page** - `2b0645f` (feat)

_Task 1 is `tdd="true"`; test and implementation were committed together in one commit rather than split into RED/GREEN, following 07-03's precedent for tasks whose `<action>`/`<behavior>` blocks specify test content and implementation together._

## Files Created/Modified
- `app/Concerns/AccountsReceivableValidationRules.php` - `collectionStatusRules()`, Rule::in allowlist of the four human-settable values only
- `app/Http/Requests/AccountingStaff/UpdateCollectionStatusRequest.php` - route-group-gated FormRequest, no Policy narrowing (D-10 gives every Accounting Staff user this authority)
- `app/Http/Controllers/AccountingStaff/CollectionStatusController.php` - `update()`, double server-side guard (Active status + non-terminal collection_status)
- `app/Http/Controllers/AccountingStaff/CollectionLetterController.php` - `show()`, derived-balance computation matching `ReceiptController::show()`
- `app/Enums/AccountsReceivableAgingBracket.php` - `letterBody()` added, four literal UI-SPEC bodies + `Current` throw
- `routes/portals.php` - two new routes in the `accounting-staff` group
- `resources/js/pages/accounting-staff/AccountsReceivable/Show.vue` - Collection Status Card + Actions Card (Print Collection Letter), replacing the 07-02 insertion-point comment
- `resources/js/pages/accounting-staff/CollectionLetter.vue` - new printable letter page
- `tests/Feature/AccountingStaff/CollectionStatusTest.php` - 6 tests covering the full `<behavior>` block
- `tests/Feature/AccountingStaff/CollectionLetterTest.php` - 5 tests covering not-yet-due 200, derived balance, 404, and `letterBody()`'s bracket matrix + `Current` throw

## Decisions Made
- `letterBody()` returns its bracket's template with literal `{due date}`/`{n}` tokens rather than taking parameters; `CollectionLetter.vue` interpolates them via a computed `.replaceAll()` using its own `dueDate`/`daysPastDue` props. This keeps the enum method a pure bracket→copy lookup (matching the plan's `letterBody(): string` no-argument signature) while still reproducing the UI-SPEC's exact literal text and rendering real dates/day-counts to the customer, never the literal placeholder strings.
- Added a `jobOrderDescription` prop to `CollectionLetterController::show()`'s Inertia payload, beyond the plan's literal prop list -- required by `07-UI-SPEC.md`'s Reference line copy ("Re: Job Order {JO number} — {job order description}"), which the plan's own controller instructions omitted. Documented under Deviations below.
- Task 1's TDD flag was satisfied with a single combined commit (test + implementation) rather than a RED-then-GREEN split, following the exact precedent `07-03-SUMMARY.md` already established and justified for this phase's `tdd="true"` tasks.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing critical functionality] Added `jobOrderDescription` to `CollectionLetterController::show()`'s Inertia props**
- **Found during:** Task 1 (writing the controller against `07-UI-SPEC.md`'s binding Copywriting Contract)
- **Issue:** The plan's controller `<action>` text lists `jobOrderNumber`, `customerName`, `creditExtended`, `amountPaid`, `amountDue`, `dueDate`, `daysPastDue`, `pastDue`, `letterBody` -- but omits the job order description, which `07-UI-SPEC.md`'s approved "Reference line" contract requires ("Re: Job Order {JO number} — {job order description}"). Without it, Task 2's letter page could not render this required line.
- **Fix:** Added `jobOrderDescription` to the eager-load column list and the Inertia render payload
- **Files modified:** `app/Http/Controllers/AccountingStaff/CollectionLetterController.php`
- **Verification:** `CollectionLetter.vue`'s reference line renders correctly; `npm run types:check` passes
- **Committed in:** `7000166` (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 Rule 2 missing-critical-functionality addition)
**Impact on plan:** Necessary for correctness against the approved, binding UI-SPEC Copywriting Contract. No scope creep -- the only file touched beyond the plan's own instructions is the one controller already in this plan's `files_modified` list.

## Issues Encountered
- This worktree had no `vendor/`, `node_modules/`, `.env`, SQLite database, or built frontend assets, and its git branch was unexpectedly based on an unrelated single "init" commit rather than the phase's `b56e200` tracking commit -- corrected via `git reset --hard b56e200` (clean working tree, no uncommitted work lost) before any file was read, per the worktree branch check protocol. Then ran `composer install`, `cp .env.example .env`, `php artisan key:generate`, `touch database/database.sqlite`, `php artisan migrate:fresh`, `npm install`, `npm run build`, mirroring every prior Phase 7 plan's identical documented bootstrap gap.
- `npm install` again renamed `package-lock.json`'s `name` field to the worktree directory name; reverted with `git checkout -- package-lock.json` before any commit, per 07-01/07-02/07-03's documented precedent.
- `composer types:check` (Larastan level 7) fails on the same 3 pre-existing, unrelated files first flagged in 07-01 (`CreateCreditRequestRequest.php`, `SavePricingAndPaymentRequest.php`, `UpdateSystemConfigurationRequest.php` -- a route-model-binding type-inference gap from Phase 5). None of these files are in this plan's `files_modified`; not fixed here, per the scope boundary. Every other verification command (`route:list`, `pint --dirty`, the full 427-test Pest suite, `npm run types:check`) passes clean.

## Known Stubs

None. Both new surfaces render real, derived data end to end -- no hardcoded empty values or placeholder text reaches the UI.

## Threat Flags

None -- the plan's own `<threat_model>` fully covers this plan's two new routes (`Rule::in()` allowlist against T-07-04-01, independent server-side Active/terminal re-check against T-07-04-02, read-only letter render accepted as intended disclosure per T-07-04-03, existing `AuditObserver` coverage against T-07-04-04). The one addition beyond the plan's literal instructions (`jobOrderDescription` prop) is a read-only data addition to an already-covered read-only route, not a new trust boundary.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- The Collection Status panel and Print Collection Letter action are live and tested on `Show.vue`; 07-05 (write-off request/approval) can extend the same page at its own marked insertion point (`<!-- Write-off actions: added by 07-05 -->`) without restructuring the panels this plan added.
- No blockers. The one pre-existing Larastan gap (see Issues Encountered) remains orthogonal to Phase 7 and is already tracked in `deferred-items.md` from prior plans.

---
*Phase: 07-accounts-receivable*
*Completed: 2026-09-08*
