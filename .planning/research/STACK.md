# Stack Research

**Domain:** Print-shop / small-business management system (queueing, job orders, POS/payments, design-file handling, production tracking, AR, reporting) built on Laravel 13 + Inertia v3 + Vue 3.5
**Researched:** 2026-08-31
**Confidence:** HIGH for framework-adjacent packages (verified against Packagist/GitHub release metadata directly), MEDIUM for domain-pattern choices (verified via WebSearch + primary docs), LOW/flagged explicitly where noted (PDF/AI file resolution reading, Laravel Cloud system-binary support)

## Recommended Stack

### Core Technologies (already installed — confirmed compatible, not being revisited)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Laravel Framework | 13.29.0 | Backend framework | Already scaffolded; current LTS-track major, PHP 8.4 native |
| Inertia.js (`inertiajs/inertia-laravel` + `@inertiajs/vue3`) | 3.3.1 / ^3.0 | Server-driven SPA bridge | Already scaffolded; v3's polling, deferred props, and partial reloads directly serve this project's "real-time-ish" (client-poll, no websockets) and progressive-loading needs (design previews, production board) |
| Vue | 3.5.13 | Frontend components | Already scaffolded |
| MySQL | 8.0+ (Laravel Cloud managed) | Production database | Already decided; matches the 12-table ERD's relational/transactional needs (AR aging, audit trail, FK integrity) far better than SQLite (dev-only) |
| Laravel Fortify | 1.39.0 | Auth backend | Already scaffolded; covers login lockout (`RateLimiter`), password rules, and has 2FA (`pragmarx/google2fa` under the hood) already present but unwired — reuse it for the "login hardening" requirement rather than adding a new 2FA package |

No changes recommended here — these are correctly locked in.

### Supporting Libraries — Domain-Specific (new, to be added)

| Library | Version | Purpose | When to Use | Confidence |
|---------|---------|---------|-------------|------------|
| `barryvdh/laravel-dompdf` | `^3.1` (currently 3.1.2, released 2026-02-21) | PDF export | Reports (Daily/Monthly Sales, Financial, Artist Performance), printable AR collection letters, digital receipts | HIGH — verified `illuminate/support: ^9\|^10\|^11\|^12\|^13.0` on Packagist |
| `maatwebsite/excel` | `^3.1` (pin to `3.1.70`, released 2026-08-13) | Excel export | Summary of Sales & Expenses, exportable reporting tables | HIGH — verified Laravel 13 support. **Do not jump to `4.0.x` yet** (see Alternatives) |
| `endroid/qr-code` | `^6.1` (currently 6.1.3, released 2026-02-05) | QR code generation for job-order tracking | Generate the QR encoded with the JO number at job-order creation, printed on the receipt/ticket | HIGH — 79M+ total downloads, actively released, requires PHP `^8.4` (exact match), framework-agnostic (no Laravel-specific wrapper needed — see "What NOT to Use") |
| `ext-imagick` (PHP extension, not Composer package) | Whatever ships with the host's PHP 8.4 build | Reads embedded resolution (DPI) and pixel dimensions from uploaded design files | Type A job-order auto-validation (DPI/format/size thresholds) for raster formats (JPG, PNG, TIFF, PSD) | MEDIUM — Laravel Cloud environments document Imagick as a supported extension, but this wasn't verified against Inkspire's specific plan tier; confirm during setup |
| Ghostscript (system binary, not Composer/PHP) | Any recent 10.x | Enables Imagick to rasterize/inspect PDF and EPS/AI files for dimension/resolution checks | Only if Type A auto-validation must cover vector/print-industry formats (PDF, AI, EPS) directly | LOW — **could not verify Laravel Cloud provides a system Ghostscript binary or lets you install one.** See "What NOT to Use" for the safer fallback |
| `intervention/image` | `^3.11` | Image manipulation (thumbnails/previews of uploaded design files, format normalization) | Generating small preview thumbnails for the artist queue/production board UI, not for DPI/resolution reads (use raw `Imagick` class for that — Intervention's abstraction doesn't reliably expose resolution metadata) | MEDIUM |

### PayMongo Integration — No SDK, Custom Service (confirmed)

| Approach | Details | Confidence |
|----------|---------|------------|
| Custom `PayMongoService` over Laravel's `Http` facade | Call PayMongo's REST API (`https://api.paymongo.com/v1`) directly for Payment Intent / Source creation (GCash, Maya) | HIGH |
| Webhook verification | Read the `Paymongo-Signature` header, compute HMAC-SHA256 of the **raw** (unparsed) request body using the webhook's endpoint secret, compare with `hash_equals()` (timing-safe). Must exclude the webhook route from any middleware that mutates/parses the body before your handler runs | HIGH — confirmed against PayMongo's own webhook-implementation docs |

**Confirmed: no maintained official PHP SDK exists.** `paymongo/paymongo-php` on GitHub has exactly 2 commits total, its only tag is `v0.0.0`, and its last commit was 2023-08-18 — over 3 years stale with no unit tests ("Pending Todos" in its own README still lists "Unit Tests" and "Code Cleanup" as outstanding). Do not depend on it. Unofficial forks (`jenn0pal/paymongo-php`, `shinidev/paymongo-php`) exist but are single-maintainer community packages with no more assurance than a custom Http-facade wrapper — and a custom wrapper gives full control over the audit-trail logging this project requires on every payment mutation anyway.

### Design Editor — TOAST UI Image Editor (already decided — verify/refine)

| Item | Finding | Confidence |
|------|---------|------------|
| Core library (`tui-image-editor` npm package) | Latest npm publish is **3.15.3, dated 2022-04-25** — over 4 years stale. GitHub repo (`nhn/tui.image-editor`) last commit 2023-11-20, **not archived**, 289 open issues | HIGH (verified via npm registry + GitHub API directly) |
| Official Vue wrapper (`@toast-ui/vue-image-editor`) | Its own wrapper repo was archived by NHN on 2021-07-28 in favor of consolidating into the mono-repo — but the mono-repo itself has had no meaningful feature work since 2022 | HIGH |
| Recommendation | **Keep as decided**, but don't install `@toast-ui/vue-image-editor` (archived wrapper) — import `tui-image-editor` core directly and hand-wrap it as a thin Vue 3 component (a `ref`-mounted canvas host + lifecycle hooks), exactly as the milestone context already plans. This sidesteps the dead wrapper while keeping the (still functional, still the most feature-complete free option for crop/filter/text/shape/draw/undo-redo) core engine | MEDIUM — functional risk is real (unmaintained upstream, no PHP/security patches expected), but no actively-maintained drop-in replacement offers equivalent feature parity (see Alternatives) |
| Fallback signal to watch for | If `tui-image-editor`'s canvas rendering breaks on a future Chrome/Vue update with no upstream fix available, re-evaluate — do not invest in deep customization of TOAST UI internals that would be expensive to port off of | — |

## Installation

```bash
# PDF export
composer require barryvdh/laravel-dompdf:^3.1

# Excel export (pin to 3.1 line, not 4.0 — see rationale below)
composer require maatwebsite/excel:^3.1

# QR code generation (framework-agnostic, no Laravel wrapper package)
composer require endroid/qr-code:^6.1

# Image preview/thumbnail generation
composer require intervention/image:^3.11

# Design-file editor (JS side)
npm install tui-image-editor@^3.15
# Do NOT install @toast-ui/vue-image-editor (archived) — wrap tui-image-editor manually

# PayMongo: no package — build app/Services/PayMongo/PayMongoService.php over Http facade
```

**Server-side (not Composer):** confirm `ext-imagick` is present in the Laravel Cloud PHP 8.4 runtime before building the Type A auto-validation feature. If DPI/format checks must cover PDF/AI/EPS uploads (not just raster), separately confirm Ghostscript availability — do this early, it gates a Phase 1 requirement.

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| `maatwebsite/excel` `^3.1` (3.1.70) | `maatwebsite/excel` `4.0.x` | 4.0 is the actively-developed future line (Laravel 12/13-only, PHP ^8.3, `phpoffice/phpspreadsheet` ^5.8) but its first tagged release (4.0.0) shipped 2026-08-13 — **weeks old at research time**, effectively unbattle-tested in production. Revisit for 4.x once it has a few months of patch releases behind it; 3.1.x is still actively patched (latest patch 2026-08-13, same day) and has 7+ years of production hardening |
| `endroid/qr-code` | `simplesoftwareio/simple-qrcode` (the package named in already-decided notes) | Only if you specifically want a Laravel-facade-style API (`QrCode::size()->generate()`). Original package is functionally stalled (last tagged release 4.2.0, Feb 2021) — if a Laravel-native feel matters more than avoiding an extra abstraction layer, use the maintained fork `f9webltd/simple-qrcode` (last push 2026-03-19, declares Laravel 11/12/13 + PHP ^8.2 support) instead of the original |
| `tui-image-editor` (TOAST UI) | Custom Fabric.js-based editor | If TOAST UI's staleness becomes a blocker (browser compat break, security issue with no patch) — Fabric.js is the actively-maintained canvas primitive most bespoke web image editors are now built on. This is a full rebuild of the editor UI, not a drop-in swap — only justified if TOAST UI actually breaks, not preemptively |
| Custom PayMongo `Http`-facade service | `jenn0pal/paymongo-php` or `shinidev/paymongo-php` (community forks) | If you want typed request/response objects instead of raw arrays and are willing to accept single-maintainer risk equal to or greater than a custom wrapper. Given this project needs every PayMongo call individually logged to the audit trail anyway, a thin custom service costs little extra and avoids the dependency |
| `ext-imagick` + raw `Imagick` calls for DPI reads | `intervention/image` for resolution reads too | Intervention v3 is fine for resize/crop/thumbnail but its driver abstraction (GD or Imagick) doesn't cleanly expose `getImageResolution()` — reach for `Imagick` directly for the specific DPI-validation code path |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|--------------|
| `simplesoftwareio/simple-qrcode` (original package) | Functionally unmaintained: last real release Feb 2021 (v4.2.0), no engagement on new Laravel majors since | `endroid/qr-code` directly (framework-agnostic, actively maintained), or `f9webltd/simple-qrcode` fork if a Laravel-facade API is preferred |
| `paymongo/paymongo-php` | Effectively abandoned: 2 total commits, only tag is `v0.0.0`, last commit 2023-08-18, own README admits missing unit tests | Custom `Http`-facade service (see above) |
| `@toast-ui/vue-image-editor` | Archived by maintainer 2021-07-28, read-only, no fixes will land there even if the core engine gets patched | Import `tui-image-editor` core directly, wrap manually in a Vue 3 SFC |
| Generic audit-log packages (`spatie/laravel-activitylog`, `owen-it/laravel-auditing`) for the `audit_trail` table | This project's constraint is that `audit_trail` is **structurally** append-only — "no update/delete code paths at all, not just permission checks." Generic audit packages log via observers/events sitting *alongside* normal Eloquent update/delete capability on the log model itself — they don't remove the capability, they just don't happen to call it. That's a permission-boundary guarantee, not a structural one, and doesn't satisfy the stated constraint | A dedicated `AuditTrail` Eloquent model with `update()`/`delete()` overridden to throw (or simply never `use SoftDeletes` and never define fillable-for-update fields), inserted via a single `AuditLogger` service — small enough that a package adds indirection without buying the actual guarantee needed |
| `maatwebsite/excel` `4.0.x` today | First tagged release is only weeks old (2026-08-13) at time of research — high change-risk for a reporting feature that needs to be trustworthy from day one | `maatwebsite/excel` `^3.1` (mature, still receiving same-week patches) |
| Laravel Echo / Reverb / any websocket layer | Already explicitly out of scope per PROJECT.md — confirmed no reason to revisit; Inertia v3's polling + partial reloads fully covers the "real-time-ish" cross-role notification requirement at this shop's scale | Inertia `poll()` / `router.reload({ only: [...] })` against a lightweight polling endpoint, backed by Laravel's built-in `database` notification channel |

## Stack Patterns by Variant

**If Type A auto-validation must support PDF/AI/EPS uploads (not just raster JPG/PNG/TIFF):**
- Confirm Ghostscript is installed and reachable from the Laravel Cloud PHP runtime *before* committing to this scope in a phase plan
- If it's unavailable and can't be installed, the pragmatic fallback (and one worth proposing to the client) is: raster formats get full automatic DPI/dimension/format validation (Type A), while PDF/AI/EPS uploads are auto-routed to Type B (needs consultation) regardless of their actual technical readiness — an artist visually confirms print-readiness for those formats instead of the system doing it
- This directly affects a Phase 1/2 scope decision — flag for the roadmap

**If the shop's product mix is mostly raster (banners, tarps, photo prints from JPG/PNG/TIFF sources) rather than vector print jobs (AI/EPS from professional designers):**
- Skip Ghostscript entirely — `ext-imagick` alone on raster formats covers the validation requirement with far less infrastructure risk

## Version Compatibility

| Package A | Compatible With | Notes |
|-----------|-----------------|-------|
| `barryvdh/laravel-dompdf` 3.1.2 | Laravel 13, PHP ^8.1 | Verified via Packagist `require` block directly |
| `maatwebsite/excel` 3.1.70 | Laravel 5.8 through 13, PHP ^7.0\|\|^8.0 | Verified via Packagist; broadest compatibility of the two lines |
| `maatwebsite/excel` 4.0.x | Laravel 12–13 only, PHP ^8.3 | Narrower support window by design (clean break); not yet recommended (see above) |
| `endroid/qr-code` 6.1.3 | PHP ^8.4 | Exact match to this project's PHP 8.4 runtime |
| `tui-image-editor` 3.15.3 | Bundles its own Fabric.js-derived canvas engine; no framework version coupling | Test against Vue 3.5 + current Chrome/Edge manually — no official compatibility statement exists post-2022 |
| Fortify 1.39.0 2FA (`pragmarx/google2fa` transitive dep) | Already present, unwired | No new dependency needed to satisfy any 2FA-adjacent requirement |

## Sources

- Packagist API (`packagist.org/packages/*.json`) — direct version/require-block verification for `barryvdh/laravel-dompdf`, `maatwebsite/excel`, `endroid/qr-code`, `f9webltd/simple-qrcode`, `simplesoftwareio/simple-qrcode`, `intervention/image` — HIGH confidence, fetched directly 2026-08-31
- GitHub REST API (`api.github.com/repos/*`) — `pushed_at`/`archived`/commit-log verification for `nhn/tui.image-editor` and `paymongo/paymongo-php` — HIGH confidence, fetched directly 2026-08-31
- npm registry API (`registry.npmjs.org/tui-image-editor`) — last-publish-date verification — HIGH confidence
- PayMongo official docs (`docs.paymongo.com/docs/creating-a-webhook-endpoint`, `developers.paymongo.com/docs/webhook-implementation-best-practices`) — webhook HMAC-SHA256 signature verification pattern — MEDIUM confidence (WebSearch-surfaced summary of official docs, not a direct fetch of the raw page)
- WebSearch — Laravel Cloud PHP extension support (Imagick confirmed present in supported extension list; Ghostscript/system-binary support unconfirmed) — LOW confidence, flagged for direct verification against Laravel Cloud's own docs before scoping Phase work around it
- `.planning/codebase/STACK.md` — existing scaffold inventory (Laravel 13.29.0, Inertia 3.3.1, Vue 3.5.13, Fortify 1.39.0, Wayfinder 0.1.21, Tailwind 4.1.1) — HIGH confidence, read directly from repo

---
*Stack research for: print-shop management system (Inkspire)*
*Researched: 2026-08-31*
