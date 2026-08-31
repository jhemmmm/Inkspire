# Project Research Summary

**Project:** Inkspire (print-shop management system for SquareFoot Graphics & Ads)
**Domain:** Multi-role internal business management system — job-order/production tracking, POS + payment gateway, accounts receivable, file-upload design workflow
**Researched:** 2026-08-31
**Confidence:** MEDIUM-HIGH

## Executive Summary

Inkspire is a single-location print shop's paper-process replacement, not a multi-tenant SaaS product — every research thread (stack, features, architecture, pitfalls) converges on the same conclusion: build a straightforward Laravel + Inertia + Vue monolith with 7 role-dedicated portals, hand-rolled RBAC via native Policies/Gates (no permissions package needed), guarded Action classes for state transitions (no state-machine package needed), and Inertia's polling/partial-reload mechanics for "real-time-ish" cross-role updates (no websockets needed). The existing scaffold (Laravel 13, Inertia v3, Vue 3.5, Fortify) is correctly chosen and needs only domain-specific additions: `barryvdh/laravel-dompdf` and `maatwebsite/excel:^3.1` for reporting, `endroid/qr-code` for job-order QR generation, `ext-imagick` for DPI validation, and a hand-wrapped `tui-image-editor` (not the archived Vue wrapper) for the design editor. PayMongo requires a custom `Http`-facade service — no maintained official or community PHP SDK exists.

The feature research confirms the locked scope matches or exceeds industry table-stakes (job tracking, proofing, POS, basic reporting, RBAC) while deliberately — and correctly — excluding inventory management, quoting workflows, multi-branch support, and self-service ordering as out-of-scope scope-creep for this shop's size and process. Two features exceed the domain norm and deserve roadmap attention as genuine differentiators: the in-browser TOAST UI design editor (heavier than typical "approve/reject" proofing) and the automated escalating AR-aging-bracket reminder pipeline (closer to dedicated AR-automation software than typical print-MIS AR reporting). The 7-fully-separate-portal decision (vs. one adaptive UI with role-filtered nav) is a deliberate, client-confirmed complexity multiplier that should be sized into every phase touching more than one role, not treated as a one-time cost.

The primary risks are concentrated in three areas, all flagged as MUST-avoid in PITFALLS.md: (1) PayMongo webhook handling — raw-body signature verification, DB-constraint-backed idempotency (PayMongo retries up to 12 times), and a manual reconciliation path that actually calls PayMongo's API rather than a free-form "mark as paid" button; (2) the audit trail being "append-only" only at the UI layer rather than structurally (bulk Eloquent updates bypass Observers; DB grants may still permit UPDATE/DELETE) — this needs DB-level or trigger-level enforcement, verified against Laravel Cloud's actual managed-MySQL capabilities; and (3) RBAC enforced only by hiding nav items in each portal rather than server-side Policy checks on every route — a near-certain failure mode when building 7 separate portal codebases in parallel. A fourth, more print-domain-specific risk is DPI validation trusting the embedded metadata tag instead of computing effective DPI from pixel dimensions vs. intended print size — a subtle but very-likely-to-surface bug given real-world uploads from phones/screenshots. All four should be resolved architecturally in the foundational phase (RBAC + audit) and the payments/file-upload phases respectively, not patched in later.

## Key Findings

### Recommended Stack

The core scaffold (Laravel 13.29.0, Inertia 3.3.1, Vue 3.5.13, MySQL, Fortify 1.39.0) is already correctly locked in and requires no changes. Domain gaps are filled by mature, narrowly-scoped packages rather than heavier alternatives — notably pinning `maatwebsite/excel` to the `^3.1` line (not the just-released, weeks-old `4.0.x`) and building PayMongo integration as a custom service rather than depending on an effectively-abandoned SDK. The design editor (TOAST UI Image Editor) is confirmed functional but upstream-unmaintained since 2022; the recommendation is to keep it (no actively-maintained equivalent-feature alternative exists) but import the core library directly rather than the archived official Vue wrapper.

**Core technologies:**
- Laravel 13 + Inertia v3 + Vue 3.5 (already scaffolded) — server-driven SPA bridge whose v3 polling/deferred-props/partial-reload features directly serve this project's "real-time-ish without websockets" and progressive-loading (design previews, production board) needs
- MySQL (Laravel Cloud managed) — matches the 12-table ERD's relational/transactional needs (AR aging, audit trail, FK integrity) far better than SQLite
- `barryvdh/laravel-dompdf:^3.1` + `maatwebsite/excel:^3.1` — PDF/Excel report and collection-letter export, both verified Laravel 13-compatible
- `endroid/qr-code:^6.1` — job-order QR generation, framework-agnostic and actively maintained
- `ext-imagick` (+ optionally system Ghostscript for PDF/AI/EPS) — DPI/dimension reads for Type A auto-validation; Ghostscript availability on Laravel Cloud is unverified and should be confirmed early, as it gates a Phase 1/2 scope decision
- Custom `PayMongoService` over Laravel's `Http` facade — no maintained official/community SDK exists; also gives full control over per-call audit logging

### Expected Features

Feature research (MEDIUM confidence — industry-pattern research from print-MIS vendor content, no formal domain spec) confirms the locked scope matches or exceeds table stakes for print-shop management software, while correctly excluding common but inappropriate scope-creep for a single-location shop.

**Must have (table stakes):**
- Job/order tracking through clear production stages, tied to a customer record
- File/artwork attachment with preflight-style validation (DPI/format/size) on upload
- Proofing/design approval workflow with revision history
- POS/invoicing tied to the order, multiple payment methods (cash + local digital rails — GCash/Maya via PayMongo, correctly localized vs. US-centric Stripe/Square)
- Basic role-scoped reporting, RBAC, customer-facing order status visibility, AR/running-tab tracking, audit trail

**Should have (competitive differentiators — worth extra roadmap attention):**
- Built-in web design editor (TOAST UI) rather than upload-PDF-and-approve — real complexity driver, closer to a design tool than a typical MIS
- Automated escalating AR reminder pipeline with aging brackets — more sophisticated than the domain norm (most shops handle overdue accounts manually)
- Formal On-Credit Owner-approval gate before a credit sale posts to AR
- Auto-assignment of jobs to Artists — needs clearly defined, testable assignment rules (flag for requirements definition)
- 7 fully separate role portals (not one adaptive UI) — client-confirmed, but multiplies front-end surface area across every cross-role feature; size phases accordingly

**Defer (explicitly out of scope for this milestone):**
- Materials/inventory management, quote-to-order workflow, multi-branch support, loyalty/marketing features, full offset-print preflight (bleed/CMYK/fonts), customer-facing self-service ordering — all confirmed anti-features for this shop's size and paper-replacement mandate

### Architecture Approach

A monolithic Laravel + Inertia app with 7 role-scoped route-group/controller/layout/page trees (plus a structurally separate guest tracking surface and webhook endpoint), thin controllers delegating to single-purpose `Actions/` classes for business operations, native Policies/Gates for authorization (no Spatie permissions package needed for a single fixed `role` column), and Observer-driven audit logging so append-only trail writes are a structural consequence of mutation rather than an opt-in call site.

**Major components:**
1. **Role portals (7) + public tracking surface** — dedicated `resources/js/layouts/{role}/` and `pages/{role}/` trees, gated by `EnsureRole` middleware per route group; tracking and webhook routes live structurally outside any `EnsureRole` group to prevent accidental auth/data leakage
2. **Actions layer** (`app/Actions/{Domain}/`) — single-purpose, independently testable classes for state transitions (job order status, payment capture, AR posting, design lock, queue assignment), replacing both fat controllers and a monolithic god-service
3. **Domain models + Observers** — `job_orders` as the central hub referenced by 5+ other tables; Observers write append-only `AuditTrail` rows on every mutating Eloquent event, kept structurally separate from feature code
4. **Async/cross-cutting infra** — queued jobs for PayMongo webhook processing, AR aging sweeps, and report exports; Laravel Cloud's managed scheduler for nightly aging recompute; DB-backed notifications surfaced via Inertia polling, not broadcast/websockets

Suggested build order (dependency-driven, per ARCHITECTURE.md): foundational RBAC/audit/config infra → portal shells → Customers/Queue → Job Orders (central entity) → Artist workflow ∥ POS/Payments ∥ Public Tracking (parallel-safe once job orders exist) → Production Monitoring → Accounts Receivable → Expenses → Reporting (deliberately last, since it aggregates every other module's data) → login-hardening polish.

### Critical Pitfalls

1. **PayMongo webhook signature verification breaks on re-parsed/re-encoded bodies** — compute HMAC-SHA256 over the raw request body (`$request->getContent()`), never over `$request->all()` or re-`json_encode`-ed data; use `hash_equals()`; exclude the route from CSRF and auth/session middleware.
2. **Webhook handler not idempotent → duplicate payment application** — PayMongo retries up to 12 times and can redeliver even on success; enforce idempotency via a unique DB constraint on the event/resource ID inside a DB transaction, not an in-memory/cache check; guard status transitions against already-applied states.
3. **No real fallback when a webhook never arrives** — build manual reconciliation to actually call PayMongo's retrieve-status API and apply the confirmed result; never allow a free-form "mark as paid" flip for gateway-routed payments.
4. **Audit trail append-only in the UI but not at the data layer** — bulk Eloquent `->update()`/`->delete()` calls bypass Observers entirely, and DB grants may still permit mutation; enforce via a restricted DB user/connection or a MySQL trigger (verify Laravel Cloud's managed-MySQL multi-user support before committing to this as the mechanism), and route all audited mutations through Eloquent instance saves.
5. **RBAC enforced only by hiding UI, not server-side Policy checks** — building 7 separate portals creates a false sense that access is already segmented; every route needs independent server-side role/Policy verification plus `is_active` mid-session enforcement, since Inertia responses are still normal HTTP endpoints reachable by URL.
6. **DPI validation trusting the embedded metadata tag** — DPI is meaningless without knowing intended print size; compute effective DPI from pixel dimensions ÷ target output size, treat the embedded tag only as a fallback signal, and give vector/PDF/PSD uploads a separate validation path.

## Implications for Roadmap

Based on combined research, suggested phase structure (aligned with ARCHITECTURE.md's dependency-driven build order and FEATURES.md's P1/P2 prioritization):

### Phase 1: Foundation — RBAC, Audit Trail, System Configuration
**Rationale:** Every other phase depends on role-gated routing existing, and audit-trail infrastructure must exist before the first mutating feature is built — retrofitting audit hooks later is explicitly flagged as an anti-pattern. This is also where the login-hardening/lockout security work belongs, since it's cheap to do early and risky to defer.
**Delivers:** `role` column + `EnsureRole` middleware + Policy scaffolding, `system_configurations` table + Owner settings UI, `audit_trail` table with Observer infrastructure and (pending Laravel Cloud verification) DB-level append-only enforcement, Fortify lockout hardening with IP-aware rate limiting.
**Addresses:** RBAC (table stakes), audit trail (table stakes), login hardening (P1-P2 in FEATURES.md).
**Avoids:** Pitfalls 4, 6, 7 (audit trail not structurally append-only; RBAC UI-only; lockout DoS against employees).

### Phase 2: Portal Shells + Customers/Queue Management
**Rationale:** Proves the 7-portal routing/layout convention with minimal domain risk before real features build on it; queue management is the lowest-complexity vertical slice that still exercises RBAC + audit end-to-end.
**Delivers:** Empty dashboards for all 7 roles + tracking layout wired to correct middleware; customer registration and search; queue-number generation with concurrency-safe locking.
**Addresses:** Customer registration, queueing (table stakes).
**Avoids:** Pitfall 8 (queue-number races) — build the locking pattern here, it's cheap at this scale.

### Phase 3: Job Orders Core (Central Entity)
**Rationale:** `job_orders` is referenced by 5+ downstream tables (Artist, POS, Production, Tracking) — nothing else can be built until it exists. Pricing database must be seeded here too, since POS pricing reads from it.
**Delivers:** Type A/B intake, `pricing_database`, DPI/format/size auto-validation reading `system_configurations` thresholds.
**Addresses:** Job Order intake (table stakes, P1).
**Avoids:** Pitfall 5 (DPI trusting metadata alone) — implement effective-DPI computation from the start, not as a later fix.
**Research flag:** Confirm `ext-imagick`/Ghostscript availability on Laravel Cloud before locking Type A scope for vector formats (PDF/AI/EPS).

### Phase 4: Artist Workflow, POS/Payments, Public Tracking (parallelizable)
**Rationale:** All three depend only on Job Orders existing (Phase 3) and are otherwise independent of each other — architecture research confirms these can be built in parallel or interleaved once the central entity is in place.
**Delivers:** TOAST UI design editor integration, revision logging, design lock/Owner override; Cash/Bank Transfer synchronous POS path first, then PayMongo webhook-driven GCash/Maya path with signature verification + idempotency + manual reconciliation; read-only public QR tracking portal structurally isolated from staff routes.
**Uses:** `tui-image-editor` (hand-wrapped), custom `PayMongoService`, `endroid/qr-code`.
**Implements:** Webhook-first payment confirmation pattern (Architecture Pattern 3), isolated tracking-route anti-pattern guard (Anti-Pattern 5).
**Avoids:** Pitfalls 1, 2, 3 (webhook signature, idempotency, missing reconciliation fallback) — this is the highest-risk phase in the entire project per PITFALLS.md; needs explicit tests re-delivering identical webhook payloads and simulating missed webhooks.
**Research flag:** Needs deeper research during planning — PayMongo Payment Intent specifics, webhook retry/reconciliation edge cases.

### Phase 5: Production Monitoring + On-Credit/Accounts Receivable
**Rationale:** Production status transitions depend on Job Orders (Phase 3) and benefit from POS existing (Phase 4) if any payment-gating rules apply. On-Credit/AR strictly depends on the POS On-Credit path from Phase 4 producing rows to operate on — do not build AR aging before the posting path exists.
**Delivers:** Sequential production status board with guarded transitions (no skipped stages), color-coded urgency; On-Credit Owner-approval gate; AR aging brackets with nightly scheduled recompute and escalating reminder notifications; collection letters, write-offs.
**Addresses:** Production Monitoring (table stakes), On-Credit + AR (P2, genuine differentiator per FEATURES.md).
**Avoids:** Pitfall 8's status-transition half (skipped stages under concurrent staff action) — enforce the state machine server-side, never trust client-submitted "next" actions.

### Phase 6: Expenses + Reporting
**Rationale:** Expense tracking is operationally independent and can slot in anywhere after Phase 1; full reporting is deliberately last because it aggregates data from every other module — building it earlier means building against incomplete/fake data.
**Delivers:** Expense tracking module; role-scoped Daily/Monthly Sales, Production Status, Financial, Artist Performance reports with PDF (`laravel-dompdf`) and Excel (`maatwebsite/excel`) export.
**Uses:** `barryvdh/laravel-dompdf`, `maatwebsite/excel:^3.1`.

### Phase Ordering Rationale

- RBAC and audit infrastructure come first because every subsequent phase's controllers, Actions, and Observers depend on them existing — this matches ARCHITECTURE.md's explicit "critical path" and PITFALLS.md's warning that retrofitting audit/RBAC is far costlier than building it in from the start.
- Job Orders is the forced sequencing point: nothing touching Artist workflow, POS, Production, or Tracking can be meaningfully built or tested before it exists, since all reference `job_orders` as a foreign key.
- Payments (Phase 4) groups the highest pitfall density of the whole project (webhook signature/idempotency/reconciliation) — isolating it as its own phase, sequenced after the simpler Cash/Bank path is proven, contains that risk rather than spreading it across the timeline.
- AR is sequenced strictly after POS's On-Credit path because AR entries have no other entry point into the system — this is a hard dependency, not a preference.
- Reporting is last by design: it's the one component whose correctness depends on every other module already producing real data.

### Research Flags

Phases likely needing deeper research during planning (`/gsd-plan-phase --research-phase <N>`):
- **Phase 4 (Artist/POS/Payments/Tracking):** PayMongo Payment Intent API specifics, webhook retry/idempotency edge cases, and TOAST UI's post-edit DPI-metadata-stripping behavior are all flagged MEDIUM/LOW confidence in the underlying research and warrant direct verification before implementation.
- **Phase 3 (Job Orders Core):** Ghostscript/Imagick availability on Laravel Cloud's actual PHP 8.4 runtime is unverified (LOW confidence) and gates the Type A vector-format validation scope decision.
- **Phase 1 (Foundation):** Laravel Cloud's managed-MySQL support for a restricted-privilege DB user/connection (for audit-trail DB-grant enforcement) is unverified (LOW confidence) — needs direct verification against Laravel Cloud docs/dashboard before committing to that enforcement mechanism vs. a trigger-based fallback.

Phases with standard, well-documented patterns (skip research-phase):
- **Phase 2 (Portal Shells/Queue):** Standard Laravel route-group/middleware/Policy patterns, HIGH confidence throughout.
- **Phase 5 (Production/AR):** Guarded Action-class state transitions and scheduled-job patterns are standard Laravel mechanics, HIGH confidence.
- **Phase 6 (Reporting/Expenses):** Both PDF/Excel packages are mature, well-documented, HIGH confidence on compatibility.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH for framework-adjacent packages (verified directly against Packagist/GitHub/npm metadata); LOW for Laravel Cloud's Imagick/Ghostscript support (WebSearch-derived, unverified against Laravel Cloud's own docs) |
| Features | MEDIUM — no formal print-shop-MIS spec exists to verify against; WebSearch-derived from print-MIS vendor sites, cross-checked across 3+ independent sources per claim |
| Architecture | HIGH for Laravel/Inertia framework mechanics and RBAC/Actions patterns; MEDIUM for PayMongo webhook specifics and third-party package choices |
| Pitfalls | MEDIUM-HIGH — PayMongo specifics and Laravel mechanics verified against official docs and multiple sources; DPI/print-production conventions and some Laravel Cloud specifics are MEDIUM/LOW, flagged explicitly |

**Overall confidence:** MEDIUM-HIGH

### Gaps to Address

- **Laravel Cloud Ghostscript/system-binary support:** Unconfirmed whether Laravel Cloud provides or allows installing Ghostscript for PDF/AI/EPS resolution reads — resolve before scoping Phase 3's Type A validation to cover vector formats; fallback is routing those formats to Type B (manual consultation) regardless of technical readiness.
- **Laravel Cloud managed-MySQL multi-user/grant support:** Unconfirmed whether a second, restricted-privilege DB connection is available for enforcing audit-trail append-only guarantees at the DB layer — resolve during Phase 1; have a MySQL-trigger fallback ready if a restricted user isn't provisionable.
- **Artist auto-assignment rules:** FEATURES.md flags that assignment logic (load-balance? round robin? skill-based?) is not yet defined — needs resolution during requirements definition, not deferred into Phase 4 implementation.
- **TOAST UI Image Editor's long-term maintenance risk:** Core library and Vue wrapper are both stale/archived upstream (last real activity 2022-2023); functional today but carries unmitigated risk of breaking on a future browser/Vue update with no upstream fix — watch as a signal, not a blocker, per STACK.md's explicit fallback guidance (Fabric.js rebuild only if it actually breaks).
- **PayMongo SDK absence:** Confirmed no maintained official or community PHP SDK exists — the custom `Http`-facade service approach is validated across STACK.md, ARCHITECTURE.md, and PITFALLS.md, but this is inherently more implementation surface (and more testing burden) than a typical "install package, call methods" integration, and should be sized accordingly in Phase 4.

## Sources

### Primary (HIGH confidence)
- Packagist API (`packagist.org/packages/*.json`) — direct version/require-block verification for PDF, Excel, QR, image packages
- GitHub REST API (`api.github.com/repos/*`) — staleness/archive verification for `tui-image-editor` and `paymongo/paymongo-php`
- npm registry API — `tui-image-editor` last-publish-date verification
- PayMongo official docs (`docs.paymongo.com/reference/webhook-resource`, `docs.paymongo.com/docs/developer-tools-webhook-setup-management`) — signature verification mechanics, retry policy (12 retries, auto-disable after 3 consecutive exhausted events, 200-209 ack requirement)
- MySQL official docs — partial/privilege revocation mechanism for DB-level audit-trail enforcement
- `.planning/codebase/STACK.md`, `.planning/codebase/ARCHITECTURE.md`, `.planning/codebase/STRUCTURE.md`, `.planning/PROJECT.md` — existing scaffold and locked-scope ground truth

### Secondary (MEDIUM confidence)
- Print-MIS vendor feature content (ShopVOX, Printavo, PrintSmith Vision comparisons) — table-stakes/differentiator feature landscape
- Queue-management vendor content (Qwaiting, ScanQueue, Waitwhile) — customer-facing status/queue pattern comparison
- Print-production/DPI convention sources (Catdi, Templated, ImResizer, Printcart) — effective-DPI-vs-metadata-tag industry convention, converging across multiple independent vendors
- Laravel ecosystem articles on Gates/Policies vs. Spatie permissions, Actions vs. Services patterns — architecture recommendation support
- Webhook idempotency/deduplication guides (Hookdeck, Hooklistener) — cross-provider idempotency pattern corroboration

### Tertiary (LOW confidence, needs validation)
- WebSearch on Laravel Cloud PHP extension support — Imagick confirmed present, Ghostscript/system-binary support unconfirmed
- WebSearch on Laravel Cloud managed-MySQL multi-user/grant provisioning — unconfirmed, needs direct verification against Laravel Cloud docs before Phase 1 audit-trail enforcement design
- Single-author blog posts on Laravel audit-trail Observer patterns and pessimistic locking — cross-checked against generic/official guidance but not independently corroborated

---
*Research completed: 2026-08-31*
*Ready for roadmap: yes*
