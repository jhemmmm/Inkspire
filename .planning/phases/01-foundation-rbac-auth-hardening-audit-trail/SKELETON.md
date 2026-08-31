# Walking Skeleton — Inkspire

**Phase:** 1
**Generated:** 2026-08-31

## Capability Proven End-to-End

A user with the `owner` role logs in, is redirected server-side to their own dedicated portal (`/owner/dashboard`), and can deactivate another user's account from a real database-backed User Management screen — an action that is captured, structurally and automatically, as an append-only row in the `audit_trail` table with a before/after value diff.

This proves, in one thin vertical slice, that the four architectural pillars of Phase 1 work together: **role-scoped auth** (login → role column → per-role redirect), **server-side RBAC enforcement** (dedicated portal route group gated by role middleware), **structural audit capture** (Eloquent `#[ObservedBy]` observer firing on a real mutation with zero controller boilerplate), and **never-hard-delete data integrity** (`is_active` toggle, not `delete()`).

## Architectural Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Role storage | Single `role` column on `users`, backed by `App\Enums\UserRole` (string-backed PHP enum, TitleCase case names, snake_case values) | Locked by PROJECT.md: one role per user, no pivot table. `UserRole::portalRoute()` is the single source of truth mapping a role to its portal route name. |
| RBAC enforcement | `App\Http\Middleware\EnsureUserHasRole` route middleware (`role:owner,admin` etc.), applied per portal route group | Server-side, per-request, cannot be bypassed by hiding nav links (RBAC-02). |
| Login redirect | Custom `App\Http\Responses\LoginResponse` bound to Fortify's `Contracts\LoginResponse`, redirects via `UserRole::portalRoute()` | Fortify's documented response-customization extension point — no changes to Fortify's own login controller/pipeline ordering. |
| Audit capture | `App\Observers\AuditObserver` attached declaratively via `#[ObservedBy(AuditObserver::class)]` on any auditable model; auth events captured separately via `Illuminate\Auth\Events\*` listeners. Both write through `App\Support\AuditLogger` into one shared `audit_trail` table (D-01/D-02). | New Phases 2-8 domain models opt in with one attribute line — zero per-controller boilerplate. |
| Audit append-only enforcement | `App\Models\AuditLog` has no `update()`/`delete()` call anywhere in `app/` — enforced by never writing that code (D-04), verified by a Pest `arch()` test | DB-level restricted-privilege grants deferred (Laravel Cloud managed-MySQL support unverified per STATE.md) — code-layer-only for Phase 1. |
| Data integrity | `users.is_active` boolean toggle; `AuditLog` model has no delete path; no `SoftDeletes` trait anywhere | Matches PROJECT.md constraint: users are deactivated, never hard-deleted. |
| Portal architecture | Dedicated portal per role — separate `resources/js/pages/{role}/` namespace per role, NOT a shared layout with role-filtered nav | Locked by UI-SPEC.md. Sidebar navigation is parametrized (`AppSidebar` accepts an `items` prop) rather than duplicated per role, keeping the shared `AppShell`/`AppSidebarLayout` shell. |
| System configuration | Key-value `system_configurations` table (`key`, `group`, `value` JSON, `type`, `label`), cached via `Cache::rememberForever()`, invalidated on save | Supports adding new business-rule keys in later phases (DPI thresholds, SLA, etc.) without schema migrations. |
| Auth stack | Laravel Fortify 1.39 (already wired), extended via its documented `authenticateThrough()` pipeline and `Contracts\LoginResponse` binding — no new packages | RESEARCH.md's Alternatives Considered: every third-party package option (spatie/laravel-permission, owen-it/laravel-auditing, protonemedia/laravel-single-session, spatie/laravel-settings) was rejected as fighting this project's locked structural constraints. |
| Deployment target | Laravel Cloud (per PROJECT.md); local dev runs on SQLite via `composer run dev` | No deployment automation in Phase 1 — `composer run dev` / `php artisan serve` is the documented full-stack local run command. |

## Stack Touched in Phase 1

- [x] Project scaffold — already exists (Laravel 13 + Inertia v3 + Vue 3.5, pre-scaffolded); Phase 1 does not re-scaffold, it extends.
- [x] Routing — `routes/owner.php` (new, `role:owner,admin` group), required from `routes/web.php`.
- [x] Database — real read (User Management list) AND real write (`is_active` toggle via `forceFill()->save()`, captured by `AuditObserver::updated()`).
- [x] UI — `owner/UserManagement.vue`: a real `alert-dialog`-confirmed "Deactivate Account" button wired to `UserManagementController::deactivate`.
- [x] Deployment — no live deploy in Phase 1; documented local full-stack run command is `composer run dev` (already present in `composer.json`).

## Out of Scope (Deferred to Later Slices Within Phase 1)

The skeleton (Plans 01-01, 01-04, 01-06) deliberately does NOT include:

- Account lockout after failed attempts (RBAC-03) — deferred to Plan 01-07.
- Single active session / idle timeout enforcement (RBAC-04/05) — deferred to Plans 01-05/01-08.
- Password complexity enforcement outside production (RBAC-06) — deferred to Plan 01-02 (runs in parallel, not gated by the skeleton).
- The remaining 5 non-Owner role portals (Frontline Staff, Artist, Cashier, Production Staff, Accounting Staff) — deferred to Plan 01-09.
- Reactivating a deactivated account, and the narrow Owner-vs-Admin deactivation authorization rule — deferred to Plan 01-11.
- Viewing/filtering the audit trail (AUDIT-01) — deferred to Plan 01-10.
- System Configuration UI (CONFIG-01 full CRUD) — data substrate in Plan 01-03, UI in Plan 01-12.

## Subsequent Slice Plan

Within Phase 1 (after the skeleton, Plans 01-01/01-04/01-06):

- Plans 01-02, 01-03, 01-05 (Wave 1-2, parallel): password policy fix, system-configuration data substrate, login/logout auth-event audit.
- Plans 01-07, 01-08, 01-09 (Wave 3, parallel): account lockout, session/idle-timeout enforcement, remaining 6 role portals.
- Plan 01-10 (Wave 4): audit trail viewer.
- Plan 01-11 (Wave 5): user management authorization refinement + reactivate.
- Plan 01-12 (Wave 6): system configuration UI.

Beyond Phase 1, each later roadmap phase adds one vertical capability on top of this skeleton without renegotiating its architecture:

- Phase 2: Customer & Queue Management — first real domain models (`customers`, `queue_visits`, `job_orders` placeholders) adopt `#[ObservedBy(AuditObserver::class)]` for free audit coverage.
- Phase 3: Job Order Intake & Auto-Assignment — consumes `system_configurations` keys seeded in Plan 01-03 (DPI thresholds, file formats/size, SLA).
- Phase 4: Artist Workflow & Design Editor — Artist portal (scaffolded empty in Plan 01-09) gains real nav/pages; consumes `max_artist_break_minutes` config.
- Phase 5: POS & Payments — Cashier portal gains real nav/pages.
- Phase 6: Production Monitoring & Public Tracking — Production Staff portal gains real nav/pages; public tracking page is the first unauthenticated route added to the app.
- Phase 7: Accounts Receivable — Accounting Staff portal gains real nav/pages.
- Phase 8: Expenses & Reporting — every portal's nav gains a Reports entry; `expense_categories` config key (seeded in Plan 01-03) becomes consumable.
