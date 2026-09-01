---
phase: 02
slug: customer-queue-management
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-09-01
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
| TBD | TBD | TBD | QUEUE-01 | — | Search returns partial/LIKE matches on name or contact | feature | `php artisan test --filter=CustomerSearchTest` | ❌ Wave 0 | ⬜ pending |
| TBD | TBD | TBD | QUEUE-02 | V5 | Register new customer; duplicate contact_number rejected at DB level | feature | `php artisan test --filter=CustomerRegistrationTest` | ❌ Wave 0 | ⬜ pending |
| TBD | TBD | TBD | QUEUE-03 | Tampering/Repudiation | Queue numbers increment sequentially per Asia/Manila business day (D-16), reset the next day, protected by lockForUpdate (D-17) | feature | `php artisan test --filter=QueueNumberGenerationTest` | ❌ Wave 0 | ⬜ pending |
| TBD | TBD | TBD | QUEUE-04 | — | Combined save creates 1 queue entry + N job orders atomically | feature | `php artisan test --filter=QueueEntryIntakeTest` | ❌ Wave 0 | ⬜ pending |
| TBD | TBD | TBD | QUEUE-04 (D-18) | — | Job order can be appended to an existing queue visit, including after it's marked Done | feature | `php artisan test --filter=AddJobOrderToVisitTest` | ❌ Wave 0 | ⬜ pending |
| TBD | TBD | TBD | QUEUE-05 | V12 File Handling | Type A requires file field validated per-row; Type B does not | feature | `php artisan test --filter=JobOrderTypeValidationTest` | ❌ Wave 0 | ⬜ pending |
| TBD | TBD | TBD | QUEUE-06 | Information Disclosure | Public route returns only `queue_number`/`status`, no customer data, no auth required | feature | `php artisan test --filter=QueueDisplayTest` | ❌ Wave 0 | ⬜ pending |
| TBD | TBD | TBD | RBAC-02 (regression) | V4 Access Control | Non-frontline-staff roles blocked (403) from all new routes | feature | `php artisan test --filter=RoleBoundaryTest` | ❌ Wave 0 (extension) | ⬜ pending |
| TBD | TBD | TBD | AUDIT-01 (regression) | — | Customer/QueueEntry/JobOrder mutations write audit rows | feature | `php artisan test --filter=AuditCoverageTest` | ❌ Wave 0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky. Task IDs and wave assignments are TBD until the planner creates this phase's PLAN.md files.*

---

## Wave 0 Requirements

- [ ] `tests/Feature/FrontlineStaff/CustomerSearchTest.php` — covers QUEUE-01
- [ ] `tests/Feature/FrontlineStaff/CustomerRegistrationTest.php` — covers QUEUE-02
- [ ] `tests/Feature/FrontlineStaff/QueueNumberGenerationTest.php` — covers QUEUE-03 (sequential correctness only; true concurrent-write safety cannot be exercised by the SQLite-based Pest suite — documented test-coverage gap, see RESEARCH.md Pitfall 2)
- [ ] `tests/Feature/FrontlineStaff/QueueEntryIntakeTest.php` — covers QUEUE-04
- [ ] `tests/Feature/FrontlineStaff/AddJobOrderToVisitTest.php` — covers D-18 (add job order to existing visit, including after Done)
- [ ] `tests/Feature/FrontlineStaff/JobOrderTypeValidationTest.php` — covers QUEUE-05
- [ ] `tests/Feature/Public/QueueDisplayTest.php` — covers QUEUE-06, including an explicit assertion that the response payload contains no customer name/contact field
- [ ] `database/factories/CustomerFactory.php`, `QueueEntryFactory.php`, `JobOrderFactory.php` — shared fixtures, none exist yet
- [ ] No framework install needed — Pest is already configured project-wide

---

## Manual-Only Verifications

*None — all phase behaviors have automated verification per the Phase Requirements → Test Map above. (Note: true concurrent-write safety for QUEUE-03's queue counter cannot be proven by the automated suite against SQLite — see Wave 0 note above — but this is a documented coverage gap, not a manual verification substitute.)*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
