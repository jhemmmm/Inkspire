---
phase: 02
slug: customer-queue-management
status: final
nyquist_compliant: true
wave_0_complete: true
created: 2026-09-01
updated: 2026-09-01
---

# Phase 02 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 5.1.3 + pest-plugin-laravel 5.0.1 |
| **Config file** | `phpunit.xml` (suites: Unit, Feature) + `tests/Pest.php` (binds `RefreshDatabase` to Feature) |
| **Quick run command** | `php artisan test --compact --filter={TestName}` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter={touched test}`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 02-01-02 | 02-01 | 1 | QUEUE-01 | — | Search returns partial/LIKE matches on name or contact | feature | `php artisan test --filter=CustomerSearchTest` | ❌ Wave 0 | ⬜ pending |
| 02-01-02 | 02-01 | 1 | QUEUE-02 | V5, T-02-01/T-02-03 | Register new customer; duplicate contact_number rejected at DB level | feature | `php artisan test --filter=CustomerRegistrationTest` | ❌ Wave 0 | ⬜ pending |
| 02-02-03 | 02-02 | 2 | QUEUE-03 | T-02-06 | Queue numbers increment sequentially per Asia/Manila business day (D-16), reset the next day, protected by lockForUpdate (D-17) | feature | `php artisan test --filter=QueueNumberGenerationTest` | ❌ Wave 0 | ⬜ pending |
| 02-02-03 | 02-02 | 2 | QUEUE-04 | T-02-05, T-02-07 | Combined save creates 1 queue entry + N job orders atomically | feature | `php artisan test --filter=QueueEntryIntakeTest` | ❌ Wave 0 | ⬜ pending |
| 02-04-01 | 02-04 | 3 | QUEUE-04 (D-18) | T-02-14 | Job order can be appended to an existing queue visit, including after it's marked Done | feature | `php artisan test --filter=AddJobOrderToVisitTest` | ❌ Wave 0 | ⬜ pending |
| 02-02-03 | 02-02 | 2 | QUEUE-05 | V12, T-02-08 | Type A requires file field validated per-row; Type B does not | feature | `php artisan test --filter=JobOrderTypeValidationTest` | ❌ Wave 0 | ⬜ pending |
| 02-05-01 | 02-05 | 3 | QUEUE-06 | T-02-15, T-02-16 | Public route returns only `queue_number`/`status`, no customer data, no auth required | feature | `php artisan test --filter=QueueDisplayTest` | ❌ Wave 0 | ⬜ pending |
| 02-01-02, 02-02-03, 02-04-01 | 02-01, 02-02, 02-04 | 1, 2, 3 | RBAC-02 (regression) | V4, T-02-02 | Non-frontline-staff roles blocked (403) from all new routes — no single extended `RoleBoundaryTest.php`; each plan's own Pest file carries a dedicated 403 case per RESEARCH.md Pitfall 5 | feature | `php artisan test --filter=CustomerSearchTest`; `--filter=CustomerRegistrationTest`; `--filter=QueueEntryIntakeTest`; `--filter=QueueStatusTransitionTest`; `--filter=AddJobOrderToVisitTest` | ❌ Wave 0 | ⬜ pending |
| 02-02-03 (QueueEntry/JobOrder); 02-01-01 (Customer) | 02-02; 02-01 | 2; 1 | AUDIT-01 (regression) | T-02-19 | `Customer`/`QueueEntry`/`JobOrder` mutations write `audit_trail` rows | feature (QueueEntry/JobOrder) + tinker smoke-check in acceptance_criteria (Customer only) | `php artisan test --filter=QueueEntryIntakeTest` (QueueEntry/JobOrder — persisted regression assertion); `php artisan tinker --execute '...'` from 02-01 Task 1's `acceptance_criteria` (Customer — one-off execution-time check, not a persisted regression test; flagged, not silently treated as equivalent coverage) | ❌ Wave 0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky. Task IDs and wave assignments now finalized against the 5 PLAN.md files created for this phase. Statuses flip to ✅/❌ during `/gsd-execute-phase`, not during planning. There is no standalone `AuditCoverageTest.php` file — AUDIT-01 regression coverage for `QueueEntry`/`JobOrder` lives inside `QueueEntryIntakeTest.php` (Plan 02-02, Task 3), added during the checker-driven revision that closed this phase's audit-coverage gap.*

---

## Wave 0 Requirements

- [ ] `tests/Feature/FrontlineStaff/CustomerSearchTest.php` — covers QUEUE-01 (Plan 02-01, Task 2)
- [ ] `tests/Feature/FrontlineStaff/CustomerRegistrationTest.php` — covers QUEUE-02 (Plan 02-01, Task 2)
- [ ] `tests/Feature/FrontlineStaff/QueueNumberGenerationTest.php` — covers QUEUE-03 (Plan 02-02, Task 3; sequential correctness only — true concurrent-write safety cannot be exercised by the SQLite-based Pest suite, documented test-coverage gap, see RESEARCH.md Pitfall 2)
- [ ] `tests/Feature/FrontlineStaff/QueueEntryIntakeTest.php` — covers QUEUE-04 and the QueueEntry/JobOrder half of AUDIT-01 (Plan 02-02, Task 3)
- [ ] `tests/Feature/FrontlineStaff/AddJobOrderToVisitTest.php` — covers D-18 / QUEUE-04 append case (Plan 02-04, Task 1)
- [ ] `tests/Feature/FrontlineStaff/JobOrderTypeValidationTest.php` — covers QUEUE-05 (Plan 02-02, Task 3)
- [ ] `tests/Feature/Public/QueueDisplayTest.php` — covers QUEUE-06, including an explicit assertion that the response payload contains no customer name/contact field (Plan 02-05, Task 1)
- [ ] `database/factories/CustomerFactory.php`, `QueueEntryFactory.php`, `JobOrderFactory.php` — shared fixtures (Plan 02-01 Task 1, Plan 02-02 Task 1)
- [ ] No framework install needed — Pest is already configured project-wide

---

## Manual-Only Verifications

*None — all phase behaviors have automated verification per the Phase Requirements → Test Map above. (Note: true concurrent-write safety for QUEUE-03's queue counter cannot be proven by the automated suite against SQLite — see Wave 0 note above — but this is a documented coverage gap, not a manual verification substitute.)*

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 30s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved — Per-Task Verification Map finalized against the 5 PLAN.md files (revision iteration 1, gsd-plan-checker). Checkbox items above (Wave 0 Requirements, Status column) remain unchecked pending actual `/gsd-execute-phase` execution — this sign-off certifies planning completeness, not execution completeness.
