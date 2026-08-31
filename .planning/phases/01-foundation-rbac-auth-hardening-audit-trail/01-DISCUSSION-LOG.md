# Phase 1: Foundation — RBAC, Auth Hardening & Audit Trail - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-31
**Phase:** 1-foundation-rbac-auth-hardening-audit-trail
**Areas discussed:** Audit trail logging mechanism

---

## Gray Areas Presented (not all selected)

| Area | Selected for discussion? |
|------|---------------------------|
| Session & lockout enforcement | No |
| System config table shape | No |
| Audit trail logging mechanism | Yes |
| Role portal structure | No |

---

## Audit Trail Logging Mechanism

### Q1: How should mutating actions get captured into the audit trail?

| Option | Description | Selected |
|--------|-------------|----------|
| Global model observer | Base Eloquent event listener registered once, models opt in via a trait/interface. New models get audit coverage for free. | ✓ |
| Explicit manual calls | Controllers/Actions call an `Audit::log(...)` helper explicitly at the point of mutation. | |
| Hybrid | Global observer for generic CRUD + explicit calls for non-model actions (login/logout, design-lock override, write-off approval). | |

**User's choice:** Global model observer
**Notes:** None — recommended option chosen directly.

---

### Q2: How should auth events (login, logout, failed attempt, lockout) get into the audit trail, given they aren't Eloquent model mutations?

| Option | Description | Selected |
|--------|-------------|----------|
| Laravel auth event listeners | Listen for `Illuminate\Auth\Events\Login/Logout/Failed/Lockout` (fired natively by Fortify) and write an audit entry from each listener. | ✓ |
| Explicit calls in Fortify action overrides | Add audit-log calls directly inside custom Fortify action classes. | |

**User's choice:** Laravel auth event listeners
**Notes:** None — recommended option chosen directly.

---

### Q3: What should each audit entry capture for mutating actions, beyond actor/action/timestamp?

| Option | Description | Selected |
|--------|-------------|----------|
| Before/after value diff | Store old and new attribute values (JSON columns), full attributes on create/delete. | ✓ |
| Action description only | Store a human-readable summary string, no structured diff. | |

**User's choice:** Before/after value diff
**Notes:** None — recommended option chosen directly.

---

### Q4: What enforcement layer should Phase 1 target for AUDIT-02's structurally-undeletable requirement, given Laravel Cloud's restricted-privilege DB user support is unverified (per STATE.md)?

| Option | Description | Selected |
|--------|-------------|----------|
| Code-level only for now | No update()/delete() code path anywhere in the AuditLog model or its consumers. DB-level enforcement deferred until Laravel Cloud's MySQL privilege support is verified. | ✓ |
| Code-level + DB trigger now | Same code-level guarantees, plus a MySQL trigger as defense-in-depth from day one. | |

**User's choice:** Code-level only for now
**Notes:** None — recommended option chosen directly.

---

**Continue prompt:** User selected "I'm ready for context" — no further areas explored.

## Claude's Discretion

- Session enforcement mechanism (single active session, idle timeout) — not discussed, left to research/planning.
- Account lockout mechanism (attempts counter + configurable duration, distinct from Fortify's existing IP-based rate limiter) — not discussed, left to research/planning.
- `system_configurations` table shape (key-value vs fixed-column singleton) — not discussed, left to research/planning.
- Role portal routing/middleware pattern and the 5 not-yet-built portals' placeholder content — not discussed, left to research/planning (UI-SPEC.md already partially covers this).

## Deferred Ideas

None — discussion stayed within Phase 1 scope. No scope-creep suggestions were raised.
