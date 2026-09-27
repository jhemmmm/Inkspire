---
phase: 7
slug: accounts-receivable
status: final
nyquist_compliant: true
wave_0_complete: true
created: 2026-09-08
updated: 2026-09-08
---

# Phase 7 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.
> Derived from `07-RESEARCH.md` → `## Validation Architecture`.

---

## Test Infrastructure

| Property               | Value                                                                                                                                                                |
| ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Framework**          | Pest 5.1.3 + pestphp/pest-plugin-laravel 5.0.1                                                                                                                       |
| **Config file**        | `phpunit.xml` (Pest bootstraps through PHPUnit's config) — testing env: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync` |
| **Quick run command**  | `php artisan test --compact --filter={TestName}`                                                                                                                     |
| **Full suite command** | `php artisan test --compact`                                                                                                                                         |
| **Estimated runtime**  | ~60 seconds (baseline before this phase: 385 tests, 382 passed, 3 skipped)                                                                                           |

No new test framework or config install is required — Pest plus the existing
`array` mail transport and `sync` queue already cover every capability this
phase's tests need.

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter={TestName}` scoped to the file/behavior just changed
- **After every plan wave:** Run `php artisan test --compact` (full suite)
- **Before `/gsd-verify-work`:** Full suite green **plus** `vendor/bin/pint --dirty --format agent` and `composer types:check` (Larastan level 7) — matching every prior phase's closing checklist
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

_Populated after planning — task IDs do not exist until PLAN.md files are written.
The requirement-level map below is the binding contract the planner must satisfy._

| Req ID | Behavior                                                                                                                                          | Test Type | Automated Command                                                                  | File Exists | Status     |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------- | --------- | ---------------------------------------------------------------------------------- | ----------- | ---------- |
| AR-01  | Aging list groups Active receivables into correct brackets; derived balance matches transactions                                                  | feature   | `php artisan test --filter=AccountsReceivableListTest`                             | ❌ W0       | ⬜ pending |
| AR-02  | Daily command sends the correct reminder on bracket-crossing, is idempotent same-day, skips non-reminder-bearing brackets, isolates mail failures | feature   | `php artisan test --filter=SendAccountsReceivableRemindersTest`                    | ❌ W0       | ⬜ pending |
| AR-03  | Collection status update persists + audits; collection letter renders correct bracket-driven body                                                 | feature   | `php artisan test --filter=CollectionStatusTest` / `--filter=CollectionLetterTest` | ❌ W0       | ⬜ pending |
| AR-04  | Write-off request/approve/reject flow, concurrency guard (locked re-read), `PaymentStatus::WrittenOff` propagation                                | feature   | `php artisan test --filter=WriteOffTest`                                           | ❌ W0       | ⬜ pending |

_Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky_

---

## Wave 0 Requirements

- [x] `tests/Feature/AccountingStaff/AccountsReceivableListTest.php` — AR-01 (bracket grouping, derived balance, no-N+1 column allowlist)
- [x] `tests/Feature/Console/SendAccountsReceivableRemindersTest.php` — AR-02, including the **first `Mail::fake()` / `Mail::assertSent()` test in this codebase**, plus same-day idempotency (D-17 resolved this to single-fire-per-bracket only -- no terminal-bracket-repeat case, per 07-03-PLAN.md and 07-CONTEXT.md D-17)
- [x] `tests/Feature/AccountingStaff/CollectionStatusTest.php` + `tests/Feature/AccountingStaff/CollectionLetterTest.php` — AR-03
- [x] `tests/Feature/Owner/WriteOffApprovalTest.php` + `tests/Feature/AccountingStaff/WriteOffRequestTest.php` — AR-04, including the locked-re-read concurrency test matching `CreditApprovalTest.php`'s CR-05 pattern
- [x] `database/factories/AccountsReceivableFactory.php` — extend `active()` with `due_at`/`collection_status`, and add a bracket state (e.g. `atBracket(AccountsReceivableAgingBracket $bracket)`) so bracket-specific tests stay concise
- [x] `tests/Unit/Mail/AccountsReceivableReminderMailableTest.php` — locked in 07-03-PLAN.md Task 1 (Warning 2 fix; no longer "executor's choice")
- [x] Pending-window race test (write-off requested -> entry settles to Paid -> approve must fail, `payment_status` unchanged) — locked in 07-05-PLAN.md Task 2 (Blocker 2 fix)

---

## Manual-Only Verifications

| Behavior                                                             | Requirement | Why Manual                                                                            | Test Instructions                                                                                                                                                                 |
| -------------------------------------------------------------------- | ----------- | ------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Laravel Cloud scheduler cron is enabled for the deployed environment | AR-02       | Dashboard toggle, not code — no automated assertion can reach it                      | In the Laravel Cloud dashboard, enable the scheduler for the environment; confirm `schedule:list` shows the AR reminder command and that it fires once on the next daily boundary |
| Collection letter prints correctly to physical paper                 | AR-03       | Browser `window.print()` output and page-break fidelity cannot be asserted headlessly | Open a collection letter for an entry in each bracket, print to PDF, confirm bracket-driven body copy, amount, and no clipped content                                             |
| Reminder email renders correctly in a real inbox                     | AR-02       | `MAIL_MAILER=array` asserts dispatch, not rendered appearance                         | Send one reminder through the Resend sandbox to a staff address; confirm subject escalation and body render in a real client                                                      |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies (11/11 tasks across all 5 plans)
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 60s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-09-08 (plan-checker revision pass)
