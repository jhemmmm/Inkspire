---
phase: 01
slug: foundation-rbac-auth-hardening-audit-trail
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-08-31
---

# Phase 01 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 5.1.3 + pest-plugin-laravel 5.0.1 |
| **Config file** | `phpunit.xml` (suite config) + `tests/Pest.php` (Pest binding — currently has `RefreshDatabase` commented out, must be fixed in Wave 0) |
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
| 01-01-01 | TBD | 0 | — | — | RefreshDatabase enabled | infra | `php artisan test --compact` | ❌ W0 | ⬜ pending |
| 01-0x-01 | TBD | TBD | RBAC-01 | — | Login redirects each role to its own portal | feature | `php artisan test --filter=redirects_owner_to_owner_dashboard` | ❌ W0 | ⬜ pending |
| 01-0x-02 | TBD | TBD | RBAC-02 | T-01-01 | Cross-role direct URL access returns 403 | feature | `php artisan test --filter=blocks_wrong_role_with_403` | ❌ W0 | ⬜ pending |
| 01-0x-03 | TBD | TBD | RBAC-03 | T-01-02 | 5 failed attempts locks account for configured duration | feature | `php artisan test --filter=locks_account_after_five_failed_attempts` | ❌ W0 | ⬜ pending |
| 01-0x-04 | TBD | TBD | RBAC-04 | T-01-04 | New login invalidates prior session | feature | `php artisan test --filter=new_login_invalidates_previous_session` | ❌ W0 | ⬜ pending |
| 01-0x-05 | TBD | TBD | RBAC-05 | — | Idle session times out with message | feature | `php artisan test --filter=idle_session_times_out` | ❌ W0 | ⬜ pending |
| 01-0x-06 | TBD | TBD | RBAC-06 | — | Weak password rejected in every environment | feature | `php artisan test --filter=password_complexity_enforced_outside_production` | ❌ W0 | ⬜ pending |
| 01-0x-07 | TBD | TBD | RBAC-07 | — | Deactivated user cannot log in, never hard-deleted | feature | `php artisan test --filter=deactivated_user_cannot_login` | ❌ W0 | ⬜ pending |
| 01-0x-08 | TBD | TBD | RBAC-08 | T-01-05 | Login/logout/failed/lockout write audit rows | feature | `php artisan test --filter=auth_events_write_audit_trail` | ❌ W0 | ⬜ pending |
| 01-0x-09 | TBD | TBD | AUDIT-01 | — | Owner/Admin can view + filter audit trail by user/action/date | feature | `php artisan test --filter=owner_can_filter_audit_trail` | ❌ W0 | ⬜ pending |
| 01-0x-10 | TBD | TBD | AUDIT-02 | T-01-05 | No update/delete code path for audit entries | arch | `php artisan test --filter=arch_audit_log_has_no_mutation_methods` | ❌ W0 | ⬜ pending |
| 01-0x-11 | TBD | TBD | CONFIG-01 | — | Owner/Admin can update a business rule and it takes effect | feature | `php artisan test --filter=owner_can_update_system_configuration` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky. Task IDs and wave assignments finalized once PLAN.md files exist — planner fills these in.*

---

## Wave 0 Requirements

- [ ] `tests/Pest.php` — uncomment `->use(RefreshDatabase::class)` (blocking, not optional — every other Wave 0 item depends on this)
- [ ] `tests/Feature/Auth/AccountLockoutTest.php` — covers RBAC-03, RBAC-08
- [ ] `tests/Feature/Auth/SingleSessionTest.php` — covers RBAC-04
- [ ] `tests/Feature/Auth/IdleTimeoutTest.php` — covers RBAC-05
- [ ] `tests/Feature/RoleBoundaryTest.php` — covers RBAC-01, RBAC-02 (parametrized across all 7 roles × a route outside their portal)
- [ ] `tests/Feature/AuditTrailTest.php` — covers AUDIT-01, D-03 (old/new value shape)
- [ ] `tests/Unit/Arch/AuditLogArchTest.php` — covers AUDIT-02 structurally
- [ ] `tests/Feature/SystemConfigurationTest.php` — covers CONFIG-01
- [ ] `database/factories/UserFactory.php` — needs a `role` state per enum case (e.g. `UserFactory::new()->owner()`, `->artist()`, etc.) for all the above tests to construct role-specific users

---

## Manual-Only Verifications

*None — all phase behaviors have automated verification per the Phase Requirements → Test Map above.*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
