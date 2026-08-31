# Phase 1: Foundation — RBAC, Auth Hardening & Audit Trail - Context

**Gathered:** 2026-08-31
**Status:** Ready for planning

<domain>
## Phase Boundary

Every user logs in through a role-scoped, secured account (one of 7 roles, single `role` column, own dedicated portal) and every mutating action plus every auth event is permanently, structurally recorded in an append-only audit trail. Covers RBAC-01 through RBAC-08, AUDIT-01, AUDIT-02, CONFIG-01. This is the non-negotiable trust foundation every later phase (Phases 2-8) builds on — no new product capabilities beyond what's listed here.

</domain>

<decisions>
## Implementation Decisions

### Audit Trail Logging Mechanism
- **D-01:** Mutating actions (Eloquent create/update/delete) are captured via a global model observer/listener pattern — a base trait or interface models opt into, registered once (e.g. in a service provider), rather than explicit `Audit::log(...)` calls scattered through controllers. New domain models added in Phases 2-8 (job_orders, transactions, etc.) get audit coverage automatically by adopting the trait, with no per-controller boilerplate required.
- **D-02:** Auth events (login, logout, failed attempt, lockout) are NOT Eloquent mutations and are not covered by the model observer. They're captured separately via Laravel's built-in auth events (`Illuminate\Auth\Events\Login`, `Logout`, `Failed`, `Lockout`) — Fortify already fires these natively, so no changes to Fortify's own login/logout flow are needed, just event listeners that write audit entries.
- **D-03:** Each audit entry for a mutating action stores structured before/after value diffs (e.g. `old_values`/`new_values` JSON columns) — old values on update, full attribute snapshot on create/delete — not just a human-readable description string. This lets Owner/Admin see exactly what changed, matching the audit trail's filterable, detailed-review intent (AUDIT-01).
- **D-04:** Append-only enforcement (AUDIT-02) targets the code layer only for Phase 1: the AuditLog model exposes no update/delete code path anywhere (no `::update()`/`::destroy()` calls, no route/controller ever exposes mutation), enforced by never writing that code rather than by DB-level restriction. DB-level enforcement (restricted-privilege grants or a MySQL trigger) is explicitly deferred — Laravel Cloud's managed-MySQL support for a restricted-privilege DB user is unverified per STATE.md, and Phase 1 should not block on verifying that. Revisit as defense-in-depth once Laravel Cloud's privilege model is confirmed.

### Claude's Discretion
- Session enforcement (single active session, idle timeout), account lockout mechanism (attempts counter + configurable duration, separate from Fortify's existing IP-based rate limiter), system_configurations table shape (key-value vs fixed-column), and the role-portal routing/middleware pattern were not selected for discussion — these were the other candidate gray areas presented but the user chose to discuss only the audit trail logging mechanism. Claude/downstream agents have discretion on these, informed by: existing Fortify rate-limiter stays as IP-level throttle (unchanged), account-level lockout is a separate, additional mechanism (RBAC-03 is account lockout, distinct from the existing per-IP+email rate limit); system_configurations needs to support diverse value types (percentages, file format lists, durations) across many future phases (CONFIG-01 now, more in Phase 3/4/8) — lean toward whichever shape best supports that growth, decide during planning/research.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements & Roadmap
- `.planning/REQUIREMENTS.md` §RBAC & Authentication, §Audit Trail, §System Configuration — RBAC-01 through RBAC-08, AUDIT-01, AUDIT-02, CONFIG-01 full requirement text
- `.planning/ROADMAP.md` §Phase 1 — goal, success criteria, requirement mapping

### UI Design Contract
- `.planning/phases/01-foundation-rbac-auth-hardening-audit-trail/01-UI-SPEC.md` — visual/interaction contract: dedicated-portal pattern (locked architectural decision), 403 boundary page treatment, audit trail table columns/filters, lockout/session-state copy and Alert treatment, destructive-confirmation copy for account deactivation. Verified but not yet checker-signed-off (Approval: pending).

### Project-Level Context
- `.planning/PROJECT.md` §Constraints — RBAC (7 roles, single `role` column, dedicated portals), data integrity (`audit_trail` structurally append-only, `users` never hard-deleted/soft-deleted), tech stack (Laravel 13/PHP 8.4, Inertia v3/Vue 3.5, MySQL in prod)
- `.planning/PROJECT.md` §Context — the 13th table `system_configurations` is a deliberate, already-approved deviation from the 12-table ERD

### Codebase State
- `.planning/codebase/CONCERNS.md` §Known Bugs — `RefreshDatabase` disabled in `tests/Pest.php:18` (17/27 feature tests currently fail); fix this before building Phase 1 feature tests on top of it
- `.planning/codebase/ARCHITECTURE.md` — existing Fortify wiring (`app/Providers/FortifyServiceProvider.php`), existing login rate limiter (5/min per email+IP), single `User` model, no domain models yet

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Providers/FortifyServiceProvider.php` — existing login rate limiter (`RateLimiter::for('login', ...)`) and custom Fortify view wiring (Login, ResetPassword, ForgotPassword, ConfirmPassword) to extend, not replace
- `app/Actions/Fortify/ResetUserPassword.php` — existing pattern for a custom Fortify action override, if any auth-event hook needs similar treatment
- `app/Models/User.php` — sole existing model, uses PHP 8 attributes (`#[Fillable]`, `#[Hidden]`) instead of legacy properties; will need a `role` column, `is_active` flag, and lockout-tracking columns added
- `resources/js/layouts/AppSidebarLayout.vue` / `AppSidebar.vue` — existing shell to reuse per role-specific portal, per UI-SPEC's locked dedicated-portal pattern

### Established Patterns
- Form Request + Validation Concern trait pair (`app/Http/Requests/**` + `app/Concerns/*ValidationRules.php`) — reuse for new RBAC/config validation
- `Inertia::flash('toast', [...])` for mutation feedback — reuse for config save, account deactivation
- Wayfinder-generated route/action helpers — new routes for role portals, config, audit trail must go through this, not hardcoded URLs

### Integration Points
- `app/Providers/AppServiceProvider.php` — already configures global password policy defaults; session/lockout config additions likely land here or in a new dedicated provider
- `bootstrap/app.php` — middleware registration point for new role-boundary (403) middleware and any session-enforcement middleware
- `tests/Pest.php:18` — must uncomment `RefreshDatabase` before any Phase 1 feature tests are written against it

</code_context>

<specifics>
## Specific Ideas

No specific UI/behavior examples beyond what's already locked in `01-UI-SPEC.md` came up during this discussion — it stayed focused on the audit trail's backend logging mechanism.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within Phase 1 scope. No scope-creep suggestions were raised.

</deferred>

---

*Phase: 1-foundation-rbac-auth-hardening-audit-trail*
*Context gathered: 2026-08-31*
