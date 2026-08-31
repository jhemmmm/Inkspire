---
phase: 01
slug: foundation-rbac-auth-hardening-audit-trail
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-08-31
updated: 2026-08-31
---

# Phase 01 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 5.1.3 + pest-plugin-laravel 5.0.1 |
| **Config file** | `phpunit.xml` (suite config) + `tests/Pest.php` (Pest binding — fixed in Plan 01-01, Task 1) |
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
| 01-01-01 | 01-01 | 1 | — | — | RefreshDatabase enabled | infra | `php artisan test --compact` | ✅ Plan 01-01 | ⬜ pending |
| 01-04-02 | 01-04 | 2 | RBAC-01 | — | Login redirects owner/admin to the owner portal | feature | `php artisan test --filter=RoleBoundaryTest` | ✅ Plan 01-04 (`tests/Feature/RoleBoundaryTest.php`) | ⬜ pending |
| 01-09-02 | 01-09 | 3 | RBAC-01 | — | All 7 roles land on their own dedicated portal | feature | `php artisan test --filter=RoleBoundaryTest` | ✅ Plan 01-09 (extends `tests/Feature/RoleBoundaryTest.php`) | ⬜ pending |
| 01-04-02 | 01-04 | 2 | RBAC-02 | T-01-01 | Cross-role direct URL access returns 403 (owner case) | feature | `php artisan test --filter=RoleBoundaryTest` | ✅ Plan 01-04 | ⬜ pending |
| 01-09-02 | 01-09 | 3 | RBAC-02 | T-01-01 | Cross-role direct URL access returns 403 (full 7x7 matrix) | feature | `php artisan test --filter=RoleBoundaryTest` | ✅ Plan 01-09 | ⬜ pending |
| 01-07-02 | 01-07 | 3 | RBAC-03 | T-01-02 | 5 failed attempts locks account for configured duration | feature | `php artisan test --filter=AccountLockoutTest` | ✅ Plan 01-07 (`tests/Feature/Auth/AccountLockoutTest.php`) | ⬜ pending |
| 01-08-01 | 01-08 | 3 | RBAC-04 | T-01-04 | New login invalidates prior session | feature | `php artisan test --filter=SingleSessionTest` | ✅ Plan 01-08 (`tests/Feature/Auth/SingleSessionTest.php`) | ⬜ pending |
| 01-08-02 | 01-08 | 3 | RBAC-05 | T-01-03 | Idle session times out with message | feature | `php artisan test --filter=IdleTimeoutTest` | ✅ Plan 01-08 (`tests/Feature/Auth/IdleTimeoutTest.php`) | ⬜ pending |
| 01-02-01 | 01-02 | 1 | RBAC-06 | T-01-02 | Weak password rejected in every environment | feature | `php artisan test --filter=PasswordComplexityTest` | ✅ Plan 01-02 (`tests/Feature/Auth/PasswordComplexityTest.php`) | ⬜ pending |
| 01-06-01 | 01-06 | 3 | RBAC-07 | T-01-01 | Owner/Admin can deactivate a user; never hard-deleted | feature | `php artisan test --filter=UserManagementTest` | ✅ Plan 01-06 (`tests/Feature/Owner/UserManagementTest.php`) | ⬜ pending |
| 01-07-01 | 01-07 | 3 | RBAC-07 | T-01-01 | Deactivated user cannot log in | feature | `php artisan test --filter=AccountLockoutTest` | ✅ Plan 01-07 | ⬜ pending |
| 01-11-01 | 01-11 | 5 | RBAC-07 | T-01-01 | Narrow Owner-vs-Admin deactivate/reactivate authorization | feature | `php artisan test --filter=UserManagementTest` | ✅ Plan 01-11 | ⬜ pending |
| 01-05-01 | 01-05 | 2 | RBAC-08 | T-01-05 | Login writes audit_trail row | feature | `php artisan test --filter=AuthAuditTrailTest` | ✅ Plan 01-05 (`tests/Feature/Auth/AuthAuditTrailTest.php`) | ⬜ pending |
| 01-05-02 | 01-05 | 2 | RBAC-08 | T-01-05 | Logout writes audit_trail row | feature | `php artisan test --filter=AuthAuditTrailTest` | ✅ Plan 01-05 | ⬜ pending |
| 01-07-02 | 01-07 | 3 | RBAC-08 | T-01-05 | Failed attempts and lockout write audit_trail rows | feature | `php artisan test --filter=AccountLockoutTest` | ✅ Plan 01-07 | ⬜ pending |
| 01-10-02 | 01-10 | 4 | AUDIT-01 | — | Owner/Admin can view + filter audit trail by user/action/date | feature | `php artisan test --filter=AuditTrailTest` | ✅ Plan 01-10 (`tests/Feature/Owner/AuditTrailTest.php`) | ⬜ pending |
| 01-01-02 | 01-01 | 1 | AUDIT-02 | T-01-05 | No update/delete code path for audit entries | arch | `php artisan test --filter=AuditLogArchTest` | ✅ Plan 01-01 (`tests/Unit/Arch/AuditLogArchTest.php`) | ⬜ pending |
| 01-03-02 | 01-03 | 2 | CONFIG-01 | — | All 12 business-rule keys seeded, cached accessors work | unit | `php artisan test --filter=SystemConfigurationTest` | ✅ Plan 01-03 (`tests/Unit/SystemConfigurationTest.php`) | ⬜ pending |
| 01-12-02 | 01-12 | 6 | CONFIG-01 | T-01-06 | Owner/Admin can update a business rule and it takes effect immediately | feature | `php artisan test --filter=SystemConfigurationTest` | ✅ Plan 01-12 (`tests/Feature/Owner/SystemConfigurationTest.php`) | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky. Task IDs and wave assignments now finalized against the 12 PLAN.md files created for this phase. Statuses flip to ✅/❌ during `/gsd-execute-phase`, not during planning.*

**Note on file paths:** planning organized owner-portal-scoped feature tests under `tests/Feature/Owner/` (e.g. `UserManagementTest.php`, `AuditTrailTest.php`, `SystemConfigurationTest.php`) rather than flat under `tests/Feature/`, matching the `app/Http/Controllers/Owner/` namespace convention already established by this phase's `PATTERNS.md`. This is a organizational refinement of the original placeholder paths, not a scope change — the same behaviors are covered under the `--filter={TestName}` class-name filters listed above regardless of directory.

---

## Wave 0 Requirements

- [x] `tests/Pest.php` — uncomment `->use(RefreshDatabase::class)` — delivered in Plan 01-01, Task 1
- [x] `tests/Feature/Auth/AccountLockoutTest.php` — covers RBAC-03, RBAC-08 — delivered in Plan 01-07, Task 2
- [x] `tests/Feature/Auth/SingleSessionTest.php` — covers RBAC-04 — delivered in Plan 01-08, Task 1
- [x] `tests/Feature/Auth/IdleTimeoutTest.php` — covers RBAC-05 — delivered in Plan 01-08, Task 2
- [x] `tests/Feature/RoleBoundaryTest.php` — covers RBAC-01, RBAC-02 (parametrized across all 7 roles × a route outside their portal) — seeded in Plan 01-04 Task 2 (Owner case), completed in Plan 01-09 Task 2 (full 7-role matrix)
- [x] `tests/Feature/Owner/AuditTrailTest.php` — covers AUDIT-01, D-03 (old/new value shape) — delivered in Plan 01-10, Task 2
- [x] `tests/Unit/Arch/AuditLogArchTest.php` — covers AUDIT-02 structurally — delivered in Plan 01-01, Task 2
- [x] `tests/Feature/Owner/SystemConfigurationTest.php` — covers CONFIG-01 — delivered in Plan 01-12, Task 2 (data-layer half in `tests/Unit/SystemConfigurationTest.php`, Plan 01-03)
- [x] `database/factories/UserFactory.php` — role states (`owner`, `admin`, `frontlineStaff`, `artist`, `cashier`, `productionStaff`, `accountingStaff`) delivered in Plan 01-01 Task 1; `locked()`/`deactivated()` states delivered in Plan 01-07 Task 1

---

## Manual-Only Verifications

*None — all phase behaviors have automated verification per the Phase Requirements → Test Map above.*

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 30s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending (execution not yet run — this sign-off confirms the plan set satisfies Nyquist coverage, not that tests are green yet)
