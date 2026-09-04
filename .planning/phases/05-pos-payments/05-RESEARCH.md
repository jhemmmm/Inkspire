# Phase 5: POS & Payments - Research

**Researched:** 2026-09-04
**Domain:** Laravel/Inertia POS + payment-gateway (PayMongo) integration, PHP 8.4 / Laravel 13
**Confidence:** MEDIUM-HIGH (schema/architecture HIGH — grounded in this codebase's own established patterns; PayMongo API specifics MEDIUM — WebFetch-summarized from official docs which repeatedly served truncated/restructured pages, but cross-verified against the actual installed candidate package's source code, which is the strongest evidence available)

## Summary

Phase 5 adds three greenfield tables (`transactions`, `pricing_database`, `accounts_receivable`) and one additive column (`job_orders.payment_status`) to a codebase that already has strong, repeatable conventions from Phases 1-4: PHP-attribute Eloquent models (`#[Fillable]`/`#[ObservedBy(AuditObserver::class)]`), Form Request + Validation Concern trait pairs, invokable `app/Actions/*` classes wrapped in `DB::transaction()` + `lockForUpdate()` for race-condition-sensitive writes, and dual-caller "same outcome, independently reached" controllers for in-person vs. remote/external triggers. All of this phase's hard problems — payment idempotency, webhook double-delivery, reconciliation racing a webhook — have a *direct, already-proven precedent* in this codebase: `app/Actions/JobOrder/AssignArtistToJobOrder.php`. The recommended architecture reuses that exact pattern for a new `app/Actions/POS/ConfirmPaymentIntent.php` (or similarly named) Action, invoked by both the webhook controller and the manual reconciliation controller, so double-crediting a payment is structurally prevented rather than merely tested for.

No PayMongo package is installed. The official `paymongo/paymongo-php` SDK is stale (last tagged release November 2022, `php: >=5.6.0`, largely unmaintained) — do not use it. `luigel/laravel-paymongo` v2.6.0 (released April 2026) explicitly declares `laravel: ^10.0|^11.0|^12.0|^13.0` and `php: ^8.2`, matching this project's Laravel 13.29.0/PHP 8.4.3 exactly, and was installed and inspected directly in an isolated sandbox during this research (source code reviewed below, not just README claims). It ships a `PaymongoValidateSignature` middleware, a `Signer` class computing `hash_hmac('sha256', "$timestamp.$rawBody", $secret)`, and thin fluent wrappers (`Paymongo::paymentIntent()->create()`/`->attach()`) over Guzzle — this is the recommended dependency, gated behind a `checkpoint:human-verify` per this project's dependency-approval constraint.

**Primary recommendation:** Use `luigel/laravel-paymongo` for the PayMongo API client and webhook signature middleware; use PayMongo's **Payment Intent workflow** (not the legacy Source workflow, which never supported Maya) with `payment_method_allowed: ['gcash', 'paymaya']`; build a custom webhook receiver + reconciliation controller that both delegate to one shared, lock-guarded `app/Actions/POS/ConfirmPaymentIntent.php` Action mirroring `AssignArtistToJobOrder`'s established transaction pattern; snapshot computed pricing onto `job_orders` at POS time rather than trusting a live `pricing_database` FK join; generate the digital receipt as a plain Inertia/Vue page styled for `window.print()`, adding zero new PHP dependencies.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Price computation (catalog + adjustments + rush + discount) | API/Backend | Browser/Client (display only) | Money math must be server-authoritative; Vue only renders the computed total, never computes the source of truth |
| Payment recording (Cash/Bank Transfer) | API/Backend | — | Direct DB write, no external service; Form Request validates and controller persists synchronously |
| PayMongo Payment Intent creation & attach | API/Backend | — | Secret key (`sk_*`) must never reach the browser; all PayMongo API calls originate server-side via `luigel/laravel-paymongo` |
| QR/checkout display for GCash/Maya | Browser/Client | API/Backend (supplies the redirect URL) | Backend returns `next_action.redirect.url` as an Inertia prop; Vue renders it as a QR code (client-side `qrcode.vue`) — no PHP QR image generation needed |
| Webhook receipt & signature verification | API/Backend | — | Must live outside `auth`/`role:*` middleware, but still inside the app; raw-body HMAC verification is server-only by definition |
| Payment confirmation (webhook path + reconciliation path) | API/Backend | — | Single shared Action class (both callers), never duplicated — this is the idempotency boundary |
| Digital receipt rendering | Frontend Server (SSR-optional Inertia page) | Browser/Client (`window.print()`) | No PDF generation needed; Inertia renders a print-styled Vue page, browser handles print/save-as-PDF |
| On-Credit approval gate | API/Backend | — | Owner-only mutation of `accounts_receivable.status`; no client-side trust |
| Release/hand-over gate (POS-09) | API/Backend | — | `payment_status` check must be server-enforced; a client-only disabled button is not sufficient given RBAC-02's "not just hidden navigation" precedent set in Phase 1 |

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `luigel/laravel-paymongo` | ^2.6 (verified: v2.6.0, released 2026-04-04) | PayMongo API client (Payment Intent, Payment Method, Webhook signature middleware) | Only actively-maintained Laravel-native PayMongo wrapper; composer.json declares `laravel: ^10.0\|^11.0\|^12.0\|^13.0`, `php: ^8.2` — matches this project's stack exactly. Ships `PaymongoValidateSignature` middleware implementing the documented HMAC-SHA256 scheme, avoiding a hand-rolled signature check. [VERIFIED: packagist + installed & inspected source] |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `qrcode.vue` | ^3.10 (verified: 3.10.0) | Render a scannable QR code client-side from PayMongo's `next_action.redirect.url` | Needed for D-10's "register displays a QR code" requirement. Vue 3 component, `peerDependencies: { vue: "^3.0.0" }` — compatible with this project's Vue 3.5.13. Zero backend footprint (no PHP QR/Imagick dependency), consistent with "no PDF library currently installed, don't add one unless genuinely required." [VERIFIED: npm registry, peer-dep confirmed] |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `luigel/laravel-paymongo` | `paymongo/paymongo-php` (official) | Official name, but last release Nov 2022, `php: >=5.6.0`, 4 open issues, no evidence of active maintenance — a real staleness/security risk for a payment integration. [VERIFIED: packagist metadata] Not recommended. |
| `luigel/laravel-paymongo` | Raw `Illuminate\Support\Facades\Http` client against PayMongo's REST API | Zero new dependency, full control over headers (enables `Idempotency-Key` support the package's `create()` method does not expose — see Pitfall 5). Reasonable fallback if the package audit below is rejected at planning time; would require hand-rolling the HMAC signature check that the package already provides tested. |
| `qrcode.vue` for the counter QR | `simplesoftwareio/simple-qrcode` (server-side PHP, needs Imagick or GD) | Adds a PHP image-generation dependency plus a controller round-trip just to render an image; client-side JS QR generation from the already-fetched redirect URL is strictly simpler for an SPA and avoids an extra request per QR refresh. |
| One `transactions` row per money movement | A single mutable `job_orders.amount_paid` running total, no ledger | Rejected — POS-06 (receipts), POS-07 (cancellation fee netting against a prior down payment, D-05), and RPT-02 (Cashier Daily Sales report, Phase 8) all need an immutable per-payment-event record. A ledger table is the standard pattern for anything touching AR/reporting; a single mutable counter cannot be un-done or reconciled. |

**Installation (pending planner/user approval per PROJECT.md's dependency-approval constraint):**
```bash
composer require luigel/laravel-paymongo
npm install qrcode.vue
```

**Version verification:** Confirmed live against the registries during this research session (not from training-data memory):
```bash
# via https://repo.packagist.org/p2/luigel/laravel-paymongo.json — latest v2.6.0, 2026-04-04, require: php ^8.2, illuminate/support ^10|^11|^12|^13
# via `npm view qrcode.vue version` — 3.10.0, peerDependencies.vue ^3.0.0
```
`luigel/laravel-paymongo` was also `composer require`'d and its full `vendor/luigel/laravel-paymongo/src/**` tree read directly in an isolated sandbox during this research — the code samples in this document (Signer, PaymongoValidateSignature, Paymongo.php facade methods) are copied from the actual installed source, not from README claims.

## Package Legitimacy Audit

| Package | Registry | Age | Downloads | Source Repo | slopcheck | Disposition |
|---------|----------|-----|-----------|--------------|-----------|-------------|
| `luigel/laravel-paymongo` | packagist | ~7 yrs (v1.0.0 predates; v2.x line since ~2022) | 68,929 total / 2,255 monthly | github.com/luigel/laravel-paymongo (78 stars) | OK | Approved |
| `qrcode.vue` | npm | ~9 yrs (first published 2017) | 297,184/week | github.com/scopewu/qrcode.vue | OK | Approved |
| `paymongo/paymongo-php` | packagist | ~9 yrs, last release 2022 | 12,970 total (low, not actively growing) | github.com/paymongo/paymongo-php | OK (not-slop, but stale) | **REMOVED — not recommended** (unmaintained, `php: >=5.6.0`, superseded by community package for Laravel use) |

**Packages removed due to slopcheck [SLOP] verdict:** none.
**Packages flagged as suspicious [SUS]:** none — but `paymongo/paymongo-php` is explicitly excluded from the Standard Stack above on maintenance-staleness grounds even though slopcheck itself returned `[OK]` (it is a real, registered, non-hallucinated package; it is simply the wrong choice for this project).

Both approved packages were verified via `slopcheck install` run inside an isolated scratch directory (not this project's `composer.json`/`package.json` — confirmed via `git status` before and after that this project's dependency files were untouched by the audit process itself).

## Architecture Patterns

### System Architecture Diagram

```
                    ┌─────────────────────────────────────────────────┐
                    │            Cashier / Accounting Portal (Vue)      │
                    └───────────────┬───────────────────┬─────────────┘
                                     │                   │
                     POST pricing/payment          GET reconcile (per-txn button)
                                     │                   │
                                     ▼                   ▼
┌────────────────────────────────────────────────────────────────────────────┐
│                              API / Backend (Laravel)                        │
│                                                                              │
│  PricingController          PaymentController          ReconcileController  │
│  (POS-01 compute)            (POS-02/05/07 record)      (POS-04 manual poll)│
│         │                          │                            │           │
│         ▼                          ▼                            ▼           │
│   pricing_database          Cash/Bank Transfer:         Paymongo::           │
│   (catalog lookup)          Transaction::create()        paymentIntent()    │
│                              status=completed              ->find($id)      │
│                                                                   │          │
│                              GCash/Maya:                         │          │
│                              Paymongo::paymentIntent()            │          │
│                                ->create()->attach()                │          │
│                              Transaction::create()                 │          │
│                                status=pending_confirmation          │          │
│                              job_orders.payment_status =             │          │
│                                pending_confirmation                   │          │
│                                     │                                  │          │
│                                     ▼                                  ▼          │
│                        (customer scans QR, pays in own app)   app/Actions/POS/    │
│                                     │                          ConfirmPaymentIntent│
│                                     ▼                          (shared, lock-guarded)│
│                     PayMongo servers ──POST webhook──►  WebhookController         │
│                     (Paymongo-Signature header)          (signed HMAC verified,    │
│                                                            outside auth/role:*)     │
│                                                                   │                 │
│                                                                   ▼                 │
│                                                     ConfirmPaymentIntent::__invoke  │
│                                                     - lockForUpdate() the JobOrder   │
│                                                     - guard: still pending_confirmation?│
│                                                     - Transaction::confirmed_at = now()│
│                                                     - job_orders.payment_status = paid/│
│                                                       partially_paid                  │
└────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
                         accounts_receivable (On-Credit path, D-06 through D-09)
                         Owner approval → status=active → posts balance
                                     │
                                     ▼
                    Release/Hand-over action (D-16) — checks payment_status
                    before allowing pickup; blocks + redirects to Cashier if unpaid
```

### Recommended Project Structure
```
app/
├── Actions/
│   └── POS/
│       ├── ComputeJobOrderPrice.php       # POS-01: catalog + rush + discount → total_amount
│       └── ConfirmPaymentIntent.php       # shared by webhook + reconciliation (idempotency boundary)
├── Enums/
│   ├── PaymentStatus.php                  # job_orders.payment_status values
│   ├── PaymentMethod.php                  # cash, bank_transfer, gcash, maya
│   ├── TransactionType.php                # down_payment, balance_payment, full_payment, cancellation_fee
│   └── TransactionStatus.php              # completed, pending_confirmation, failed
├── Http/
│   ├── Controllers/
│   │   ├── Cashier/
│   │   │   ├── PricingController.php      # POS-01
│   │   │   ├── PaymentController.php      # POS-02/03/05/07
│   │   │   ├── ReconciliationController.php # POS-04 (also mounted for Accounting)
│   │   │   ├── ReceiptController.php      # POS-06
│   │   │   └── CreditRequestController.php # POS-08 (Cashier's half: request)
│   │   ├── Owner/
│   │   │   └── CreditApprovalController.php # POS-08 (Owner's half: approve/reject)
│   │   └── Webhooks/
│   │       └── PaymongoWebhookController.php # POS-03, outside auth/role:*
│   └── Requests/
│       └── Cashier/*ValidationRules pairs, matching the existing Concerns-trait pattern
├── Models/
│   ├── PricingEntry.php                    # pricing_database table
│   ├── Transaction.php
│   └── AccountsReceivable.php
routes/
├── portals.php   # add cashier.* and accounting-staff.* POS routes to existing groups
├── owner.php     # add owner.credit-requests.* routes
└── web.php       # add the unauthenticated webhooks/paymongo route, CSRF-excluded
```

### Pattern 1: Shared idempotent confirmation Action (webhook + reconciliation)

**What:** One invokable Action class both the webhook controller and the manual reconciliation controller call. It re-fetches the JobOrder under `lockForUpdate()` inside `DB::transaction()`, checks a guard condition (still `pending_confirmation`?) before writing, exactly mirroring the codebase's existing `AssignArtistToJobOrder` pattern.

**When to use:** Any place two independent triggers (an external async webhook, and a synchronous user-initiated action) can both attempt to apply the same state transition.

**Example (adapted directly from this codebase's own `app/Actions/JobOrder/AssignArtistToJobOrder.php`, read in full during this research):**
```php
// Source: app/Actions/JobOrder/AssignArtistToJobOrder.php (existing precedent, this codebase)
namespace App\Actions\POS;

use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\JobOrder;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class ConfirmPaymentIntent
{
    /**
     * Confirm a pending GCash/Maya payment, called by either the signed
     * webhook receiver or the manual reconciliation button (POS-03/POS-04).
     * Idempotent by construction: a locked re-read + guard means a webhook
     * arriving while reconciliation is mid-flight (or vice versa, or the
     * same webhook delivered twice) can never double-credit the job order.
     */
    public function __invoke(Transaction $transaction, string $paymongoStatus): Transaction
    {
        return DB::transaction(function () use ($transaction, $paymongoStatus): Transaction {
            $locked = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->first();

            if ($locked->status !== TransactionStatus::PendingConfirmation) {
                return $locked; // already resolved by the other caller — no-op
            }

            $locked->forceFill([
                'status' => $paymongoStatus === 'succeeded'
                    ? TransactionStatus::Completed
                    : TransactionStatus::Failed,
                'confirmed_at' => now(),
            ])->save();

            if ($locked->status === TransactionStatus::Completed) {
                $jobOrder = JobOrder::query()->whereKey($locked->job_order_id)->lockForUpdate()->first();
                $jobOrder->forceFill([
                    'payment_status' => $this->resolvePaymentStatus($jobOrder),
                ])->save();
            }

            return $locked;
        });
    }
}
```

### Pattern 2: Webhook route outside `role:*`, CSRF-excluded

**What:** The PayMongo webhook endpoint must be reachable by PayMongo's servers with no session, no CSRF token, no auth. This codebase already has one precedent for "public, unauthenticated" (the `signed`-middleware design-review route in `routes/web.php`) but that route is still inside the default `web` middleware group, which includes `VerifyCsrfToken`/`ValidateCsrfToken`. A signed URL protects against forgery there; a webhook has no signed-URL equivalent — it must be CSRF-*excluded* instead, and verified by the PayMongo HMAC signature.

**When to use:** Exactly one route in this phase.

```php
// routes/web.php — new addition, following the existing "public, unauthenticated" comment convention
use App\Http\Controllers\Webhooks\PaymongoWebhookController;

// Public, unauthenticated, HMAC-signature-verified (POS-03) — PayMongo's
// servers call this with no session/CSRF token available. Deliberately
// outside every auth/role:* group, matching the design-review precedent,
// but CSRF-excluded (see bootstrap/app.php) instead of signed-URL-protected,
// since PayMongo signs the *body*, not a temporarySignedRoute() URL.
Route::post('webhooks/paymongo', PaymongoWebhookController::class)
    ->middleware('paymongo.signature:payment_paid') // luigel/laravel-paymongo's HMAC check
    ->name('public.webhooks.paymongo');
```

```php
// bootstrap/app.php — REQUIRED addition, currently absent from this codebase.
// Without this, PayMongo's webhook POST gets a 419 CSRF failure before it
// ever reaches the signature middleware, because ->withRouting(web: ...)
// puts every route in web.php under the default 'web' middleware group,
// which includes ValidateCsrfToken.
->withMiddleware(function (Middleware $middleware): void {
    // ...existing lines unchanged...
    $middleware->validateCsrfTokens(except: [
        'webhooks/paymongo',
    ]);
})
```
[VERIFIED: Laravel 12.x official docs, `validateCsrfTokens(except:)` — Laravel 13.x doc page redirects to the same content, syntax unchanged]

### Pattern 3: Price snapshot, not live FK dereference

**What:** Store the *computed* price fields (`base_price_snapshot`, `rush_fee_amount`, `discount_amount`, `total_amount`) on `job_orders` at the moment the Cashier computes/confirms pricing (POS-01), rather than only storing `pricing_entry_id` and recalculating from the live `pricing_database` row on every read.

**When to use:** Always, for any money-bearing FK relationship where the referenced catalog row can change after the fact (Owner/Admin could edit a `pricing_database.base_price` later via CONFIG-01-adjacent tooling).

**Why:** If `pricing_database.base_price` changes after a job order is priced, a live-join calculation would silently reprice historical, already-paid job orders — corrupting receipts, AR balances, and Phase 8's sales reports. This is standard invoicing practice (an invoice snapshots the unit price at time of sale) and is not explicitly called out in CONTEXT.md, making it the single highest-value architectural finding of this research.

### Anti-Patterns to Avoid

- **Recomputing `total_amount` from `pricing_entry_id` on every page load:** breaks historical accuracy the moment a catalog price changes. Snapshot at write time (Pattern 3).
- **Duplicating the payment-confirmation logic separately in the webhook controller and the reconciliation controller:** this codebase's own `DesignReviewController`/`DesignEditorController` pair *does* duplicate logic across two independently-reached callers (by explicit design comment, because design-approval idempotency is cheap to re-derive from `status`/`outcome` state). Payment confirmation is higher-stakes (double-crediting real money) — extract a shared Action instead (Pattern 1). Flagging this as a deliberate deviation from the JOB-06/07 precedent, not an oversight.
- **Trusting a client-disabled "Release" button as the payment gate (POS-09):** RBAC-02 in this codebase already established "enforced server-side on every request — not just hidden navigation" as a hard rule; the same principle applies to the Release/Hand-over action (D-16) — it must re-check `payment_status` server-side even if the UI never shows the button to an unpaid job order.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| HMAC-SHA256 webhook signature verification | A custom `hash_hmac()` + `hash_equals()` check in a new middleware | `luigel/laravel-paymongo`'s `PaymongoValidateSignature` middleware + `DefaultSigner` | Already implements the exact documented scheme (`hash_hmac('sha256', "$timestamp.$rawBody", $secret)`) and has existing test coverage in the package's own test suite; a hand-rolled version has to get the `te=`/`li=` test-vs-live key selection and raw-body handling exactly right with no test coverage of its own. |
| PayMongo API request/response marshaling (Payment Intent create/attach/cancel, Guzzle auth) | A raw `Http::withBasicAuth(...)` wrapper per endpoint | `luigel/laravel-paymongo`'s `Paymongo::paymentIntent()->create()/->attach()/->cancel()` fluent methods | Handles Basic Auth with the secret key, amount-to-cents conversion, and PayMongo's `{data: {attributes: {...}}}` envelope consistently across every resource type. |
| QR code image rendering for the counter checkout URL | Server-side PHP QR generation (Imagick/GD) | `qrcode.vue` client-side component | Zero new PHP dependency; the redirect URL is already an Inertia prop reaching the browser — encode it into a QR image entirely client-side. |
| Digital receipt as PDF | `barryvdh/laravel-dompdf` or similar, right now | A print-styled Inertia/Vue page + `window.print()` | POS-06 only requires "generate a digital receipt," not specifically a PDF file. Adding a PDF dependency now pre-empts Phase 8's RPT-05 (PDF/Excel export for reports) decision, which is that phase's call to make with its own research. If a literal PDF file (not print-to-PDF) becomes a hard requirement during planning/discuss, flag it as a new dependency-approval item then — don't default to it here. |
| Duplicate-payment / idempotency guarding | Ad-hoc `if` checks scattered across the webhook controller and the reconciliation controller | The single shared `ConfirmPaymentIntent` Action (Pattern 1), following `AssignArtistToJobOrder`'s `DB::transaction()` + `lockForUpdate()` precedent | Two independently-written guards drift out of sync over time; one shared, tested Action is the only way to *guarantee* — not just hope — that a webhook and a manual reconciliation click can never both apply the same payment. |

**Key insight:** Every hard problem this phase's `STATE.md` entry warns about (signature verification, idempotency, reconciliation racing a webhook) already has a proven, working precedent *somewhere in this exact codebase* — `AssignArtistToJobOrder`'s locked-transaction pattern for idempotency, and the existing "public, unauthenticated route outside role:*" precedent for the webhook's routing shape. The actual net-new risk is narrower than it looks: get the CSRF exclusion right (Pattern 2, currently completely absent from `bootstrap/app.php`) and reuse the lock pattern (Pattern 1) instead of re-deriving it.

## Common Pitfalls

### Pitfall 1: CSRF blocks the webhook silently (419, not a clear error)
**What goes wrong:** PayMongo's webhook POST hits `routes/web.php`, which is registered under the default `web` middleware group (`->withRouting(web: __DIR__.'/../routes/web.php', ...)` in `bootstrap/app.php`). That group includes `ValidateCsrfToken` by default. PayMongo has no CSRF token to send, so every webhook delivery fails with HTTP 419 before your controller code or the signature middleware ever runs.
**Why it happens:** This project currently has zero CSRF exceptions configured (`grep -rn "validateCsrfTokens" bootstrap/app.php` returns nothing as of this research) — Phase 1-4 never needed one because the only other public route (`design-review`) is signed-URL-protected but still session/CSRF-token-free from the *browser's* perspective (a GET request, or a POST from a page that was itself rendered without a session-bound form... actually check: `design-review` POST routes ARE inside the default web group too, and work today only because the client's browser has a session-independent Inertia CSRF cookie from having *loaded* that GET page first — a server-to-server webhook has no such round-trip).
**How to avoid:** Add `$middleware->validateCsrfTokens(except: ['webhooks/paymongo'])` in `bootstrap/app.php` (Pattern 2). Test this explicitly with a Pest test that POSTs to the webhook route with no CSRF token/session and asserts it does NOT return 419.
**Warning signs:** Webhook deliveries show as failing/retrying in the PayMongo dashboard with no application-log entry at all (because Laravel's CSRF middleware rejects before the route's controller executes).

### Pitfall 2: Signature verification must run against the *raw* body, but Laravel's Request object is not the danger people think
**What goes wrong:** A common webhook-integration folk claim is "Laravel's JSON parsing mutates the body before you can verify it." In practice, `$request->getContent()` returns the raw, unparsed body every time (Symfony's `Request` caches the raw stream in memory) — calling `$request->input()`/`$request->json()` elsewhere in the same request lifecycle does **not** corrupt what `getContent()` returns. The real risk is a *different* piece of middleware (e.g., a global body-trimming middleware, or a proxy/load balancer that re-encodes JSON) altering bytes before your handler sees them.
**Why it happens:** Confusing "PHP frameworks generally have this footgun" (true of some other ecosystems' body-parser middleware) with Laravel's specific behavior (not applicable here by default).
**How to avoid:** Verify the signature as early as possible in the middleware stack (the package's `PaymongoValidateSignature` middleware does this correctly using `$request->getContent()`), and confirm this project has no custom global middleware in `bootstrap/app.php`'s `$middleware->web(append: [...])` list that could touch the body before it reaches the webhook route (checked during this research — `HandleAppearance`, `HandleInertiaRequests`, `AddLinkHeadersForPreloadedAssets`, `VerifySingleSession`, `EnforceIdleSessionTimeout` are all header/session-only, none touch the request body).
**Warning signs:** Signature verification fails in production but passes in local testing with an identical payload — investigate any reverse proxy (Laravel Cloud's ingress) re-serializing JSON in transit before assuming your code is wrong.

### Pitfall 3: Source workflow does not support Maya
**What goes wrong:** Planning around a single "PayMongo Source" object (the older, simpler API) for both GCash and Maya, because early PayMongo integrations/tutorials online are Source-workflow-based and GCash-only.
**Why it happens:** PayMongo's Source API historically covered GCash and GrabPay only; Maya was added exclusively through the newer Payment Intent workflow (`payment_method_allowed: ['gcash', 'paymaya']`). [MEDIUM confidence — WebFetch-summarized from official docs across several partially-truncated pages; the `paymaya`/`gcash` type-value pairing was independently confirmed via a second, differently-worded WebFetch of the e-wallets doc page]
**How to avoid:** Use the Payment Intent workflow uniformly for both GCash and Maya (POS-03's "Payment Intent/Source" wording in REQUIREMENTS.md already anticipates this ambiguity — resolve it as Payment Intent, not Source).
**Warning signs:** An attempt to create a `paymaya` Source object returns an API error about an unsupported type.

### Pitfall 4: `luigel/laravel-paymongo`'s magic-getter attribute access can produce untyped `mixed` values that fail Larastan level 7
**What goes wrong:** `Luigel\Paymongo\Models\BaseModel` uses `__get`/`__call` magic methods (`$intent->getStatus()`, `$intent->status`) to expose API response attributes dynamically. This project's `phpstan.neon` runs Larastan at level 7 against `app/`. Vendor code itself is not scanned, but any of *this project's own* code that chains these magic accessors will produce `mixed`-typed values Larastan cannot narrow, and the package's own README lists "Fix the magic method when accessing a nested data with underscore" as an open TODO item — nested-attribute access (e.g., a deeply-nested `next_action.redirect.url`) is a documented rough edge.
**Why it happens:** The package prioritizes a terse fluent API over static analysis friendliness (it predates widespread Larastan adoption).
**How to avoid:** Prefer `$intent->getData()` (returns a plain `array`) over chained magic getters in this project's own controllers/Actions, and add explicit `@var array{...}` PHPDoc shapes where the array is destructured, consistent with this codebase's existing "array shape type definitions in PHPDoc blocks" convention (CLAUDE.md PHP rules).
**Warning signs:** `composer types:check` reports `mixed` leaking into a typed method signature anywhere pricing/payment code touches a `Luigel\Paymongo\Models\*` return value.

### Pitfall 5: The package's `create()` does not support PayMongo's `Idempotency-Key` header
**What goes wrong:** PayMongo's API supports an `Idempotency-Key` request header so a retried/double-submitted POST (e.g., a Cashier double-clicking "Create Payment," or a network retry) returns the original cached response instead of creating a second Payment Intent. [MEDIUM confidence — WebSearch-derived, not independently confirmed against PayMongo's own docs pages, which did not surface this detail during this research session's WebFetch attempts] `luigel/laravel-paymongo`'s `create()` method (in `src/Traits/Request.php`, read directly during this research) hardcodes its Guzzle headers to `Accept`/`Content-type` only — there is no parameter to inject a custom `Idempotency-Key` header without extending the trait.
**Why it happens:** The package wraps the API generically and wasn't built around this specific PayMongo feature.
**How to avoid:** Don't rely on PayMongo-side idempotency for Payment Intent creation. Instead, guard at the application layer: before creating a new Payment Intent for a job order, check for an existing `transactions` row with `status = pending_confirmation` for that `job_order_id` and reuse/surface it instead of creating a duplicate (same `lockForUpdate()` pattern as Pattern 1). Disable the "Create Payment" button client-side after first click as a UX-level backstop, not the sole guard.
**Warning signs:** Two `pending_confirmation` transactions exist for the same job order after a Cashier reports "the QR code changed when I refreshed."

### Pitfall 6: `payment_method_allowed` amount is in centavos, not pesos
**What goes wrong:** The Payment Intent `amount` field is a positive integer in the smallest currency unit (centavos for PHP) — ₱1,500.00 must be sent as `150000`, not `1500` or `1500.00`.
**Why it happens:** Standard payment-gateway convention (matches Stripe's cents-based `amount`), easy to miss if the project's own `decimal(10,2)` columns store pesos-with-cents directly.
**How to avoid:** `luigel/laravel-paymongo`'s `Request::convertPayloadAmountsToInteger()` already does this conversion automatically (confirmed by reading `src/Traits/Request.php` — `config('paymongo.amount_type')` defaults to `AMOUNT_TYPE_FLOAT`, which multiplies by 100 before sending), *as long as* you pass the payload's `amount` key as a plain float/decimal (e.g., `1500.00`), not pre-converted centavos. Don't double-convert.
**Warning signs:** PayMongo dashboard shows a Payment Intent for ₱15.00 when ₱1,500.00 was intended (off by 100x), or a `400 Bad Request` for an amount below the ₱1.00 (100 centavos) minimum.

## Code Examples

### Creating a Payment Intent for GCash/Maya (POS-02/03)
```php
// Source: luigel/laravel-paymongo src/Paymongo.php + src/Traits/Request.php,
// read directly from the installed package during this research.
use Luigel\Paymongo\Facades\Paymongo;

$intent = Paymongo::paymentIntent()->create([
    'amount' => $transaction->amount, // pesos as float, e.g. 1500.00 — auto-converted to centavos
    'currency' => 'PHP',
    'payment_method_allowed' => ['gcash', 'paymaya'],
    'capture_type' => 'automatic',
    'description' => "Job Order #{$jobOrder->id}",
]);

$paymentMethod = Paymongo::paymentMethod()->create([
    'type' => 'gcash', // or 'paymaya' — exact string values [VERIFIED via WebFetch of docs.paymongo.com/docs/payment-acceptance-e-wallets]
]);

$attached = $intent->attach($paymentMethod->id, route('cashier.payments.return', $jobOrder));
// $attached->getData()['attributes']['next_action']['redirect']['url'] — encode this into qrcode.vue on the register
```

### Webhook signature verification (already provided by the package — do not reimplement)
```php
// Source: vendor/luigel/laravel-paymongo/src/Signer/DefaultSigner.php (installed package, read in full)
public function calculateSignature(string|int $timestamp, string $contentBody, string $secret): string
{
    return hash_hmac('sha256', $timestamp.'.'.$contentBody, $secret);
}
// Header format: Paymongo-Signature: t=<unix_ts>,te=<test_sig>,li=<live_sig>
// Compare against `te` in test mode, `li` in live mode (config('paymongo.livemode')).
```

### Idempotent confirmation guard (project-specific — write this, following the existing precedent)
```php
// Pattern to follow, adapted from app/Actions/JobOrder/AssignArtistToJobOrder.php
// (this codebase's own existing file — read in full during this research)
DB::transaction(function () use ($transactionId) {
    $txn = Transaction::query()->whereKey($transactionId)->lockForUpdate()->first();
    if ($txn->status !== TransactionStatus::PendingConfirmation) {
        return $txn; // already resolved — webhook and reconciliation can never double-apply
    }
    // ...apply the confirmed state...
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|---------------|--------|
| PayMongo Source API (GCash/GrabPay only) | Payment Intent workflow (GCash, Maya, GrabPay, ShopeePay, cards, BNPL, all unified) | PayMongo's stated current direction per its own docs restructure (exact date not surfaced in this research — docs site shows a 2025-07-11 header on some pages) | Source workflow cannot serve this phase's requirement to support both GCash *and* Maya through one code path; Payment Intent can. |

**Deprecated/outdated:**
- `Luigel\Paymongo\Traits\Request::token()` is marked `@deprecated 1.2.0` in the package's own source — do not use for anything in this phase.
- The package's bundled `php artisan paymongo:webhook` command only registers a `source.chargeable` event subscription by default (read directly from `WebhookAddCommand.php`) — insufficient for this phase's `payment.paid`/`payment.failed` needs. Register the production webhook endpoint's event list via the PayMongo Dashboard directly, or call `Paymongo::webhook()->create(['url' => ..., 'events' => ['payment.paid', 'payment.failed']])` manually rather than relying on the interactive command as-is.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | PayMongo webhook events fire `payment.paid` and `payment.failed` (as opposed to `payment_intent.succeeded` being the primary event to subscribe to for this flow) | Code Examples, State of the Art | If PayMongo's actual recommended event for the Payment Intent flow is `payment_intent.succeeded` rather than `payment.paid`, the webhook route/middleware event-key argument (`paymongo.signature:payment_paid`) and the `webhook_signatures` config array need the corresponding key renamed — low-risk, single-string fix, easy to catch in PayMongo's sandbox test webhook delivery during Wave 0. |
| A2 | PayMongo supports an `Idempotency-Key` header on Payment Intent creation | Pitfall 5 | If unsupported, the recommended app-layer dedup guard (check for an existing `pending_confirmation` transaction before creating a new intent) is still necessary and sufficient on its own — this assumption only affects whether a *second*, PayMongo-side safety net exists; the primary mitigation doesn't depend on it. |
| A3 | GCash/Maya redirect-URL completion windows are ~4 hours (GCash) / ~30 minutes (Maya) | (informs D-13's "discard and switch method" UX, not directly stated in a Common Pitfall but relevant to planning the reconciliation button's expected wait time) | If these windows are wrong, the only impact is UX copy/expectations ("come back and reconcile in X minutes") — no structural risk. |
| A4 | `paymongo/paymongo-php`'s last tagged release is v0.0.0 from November 2022 | Standard Stack / Alternatives Considered | If a newer, unlisted release exists that WebFetch's summarization missed, the "don't use it" recommendation would need revisiting — but the packagist `p2` API JSON confirms this via a structured registry query, not a page-summary, so confidence here is HIGH despite the overall MEDIUM section rating. |

**Note:** Package names (`luigel/laravel-paymongo`, `qrcode.vue`) are tagged `[VERIFIED]` per the provenance rule because they were confirmed via direct registry inspection (packagist `p2` JSON API, `npm view`) **and** the composer package was actually installed and its source code read directly — this is the strongest verification tier available short of Context7 (which was not available in this session's toolset). Business/API-behavior claims about PayMongo itself (event names, header format specifics not independently re-derived from installed code) remain MEDIUM confidence per the honest-reporting discipline, since WebFetch repeatedly returned truncated/restructured doc pages during this research session.

## Open Questions

1. **Is On-Credit approval (POS-08) Owner-only, or Owner+Admin?**
   - What we know: PROJECT.md's Key Decisions table states "7 roles with Owner and Admin split... Manuscript specifies Owner-exclusive financial/approval powers distinct from Admin's user-management/config powers." CONTEXT.md's D-06/D-07/D-08 consistently say "Owner reviews and approves," never mentioning Admin.
   - What's unclear: The existing `routes/owner.php` route group middleware is `role:owner,admin` for *everything* currently in that file (user management, system config, audit trail, design overrides) — there is no existing precedent in this codebase for an Owner-*exclusive* (excluding Admin) route group.
   - Recommendation: Based on PROJECT.md's explicit "Owner-exclusive financial powers" language, recommend a **new**, narrower `role:owner` (not `role:owner,admin`) middleware group specifically for the credit-approval route, diverging from the existing `owner.php` file's blanket `owner,admin` pattern. Flag this explicitly for planner/discuss-phase confirmation since it's a deviation from established routing precedent, not an extension of it.

2. **Exact webhook event name(s) to subscribe to for the Payment Intent flow**
   - What we know: PayMongo's event catalog includes `payment.paid`, `payment.failed`, `payment_intent.succeeded`, `payment_intent.awaiting_payment_method`.
   - What's unclear: Whether `payment.paid`/`payment.failed` (payment-level events) or `payment_intent.succeeded` (intent-level) is the canonical event to key the webhook handler off for a Payment-Intent-created e-wallet charge — official docs pages describing this specific relationship returned truncated content during this research.
   - Recommendation: Register the webhook endpoint for **both** `payment.paid` and `payment.failed` at minimum (these are the two REQUIREMENTS.md POS-03 explicitly needs — "Pending Confirmation" until paid, discard-and-switch on failure per D-13); verify the exact payload shape against PayMongo's sandbox test-mode webhook delivery during Wave 0 before writing the parsing logic, rather than trusting this document's payload-shape assumptions blindly.

3. **Does the `pricing_database` "product" concept need a `category`/`unit` breakdown, or is a flat name+price catalog sufficient for POS-01?**
   - What we know: D-01 says "catalog pick + manual adjust" — Cashier picks a product/service row and can adjust the line amount.
   - What's unclear: CONTEXT.md defers exact schema to Claude's Discretion; no manuscript excerpt or demo detail was available in this research session specifying catalog categories.
   - Recommendation: Start with a flat `pricing_database` table (`name`, `base_price`, `is_active`) per the Standard Stack proposal below — add `category` only if planning/discuss surfaces a concrete need (e.g., a filterable catalog UI). Simplest schema that satisfies POS-01 as literally stated.

## Recommended Schema (Claude's Discretion, per CONTEXT.md)

Per CONTEXT.md's explicit delegation ("Exact schema/columns for `pricing_database`, `transactions`, and `accounts_receivable`... follow the Eloquent model + `#[Fillable]`/`#[ObservedBy(AuditObserver::class)]` conventions"), this is a recommendation, not a locked decision — the planner should treat column names/types below as a strong starting point.

### `pricing_database`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | — |
| `name` | string | Catalog display name |
| `base_price` | decimal(10,2) | Snapshot-independent — this is the *current* catalog price; job orders snapshot it separately |
| `unit` | string, nullable | e.g. "per piece", "per sq ft" — discretionary |
| `is_active` | boolean, default true | Soft-disable pattern — generalizes `users.is_active`'s "never hard-delete" spirit to any catalog row referenced by a historical job order's FK |
| `created_at`/`updated_at` | timestamps | — |

### `job_orders` additive columns (this phase)
| Column | Type | Notes |
|--------|------|-------|
| `payment_status` | string (enum-backed) | New `PaymentStatus` enum — `Unpaid`, `PartiallyPaid`, `PendingConfirmation`, `Paid`, `CreditPendingApproval`, `OnCredit`, `CreditRejected` |
| `pricing_entry_id` | foreignId, nullable | FK → `pricing_database.id` — the catalog pick (D-01); nullable because job orders exist pre-pricing (created in Phase 2/3 intake) |
| `base_price_snapshot` | decimal(10,2), nullable | Snapshotted at POS time — see Pattern 3 |
| `rush_fee_applied` | boolean, default false | D-03's Cashier toggle |
| `rush_fee_amount` | decimal(10,2), nullable | Snapshotted computed amount, not just the flag |
| `discount_type` | string, nullable | `percentage` \| `flat` — D-02 |
| `discount_value` | decimal(10,2), nullable | Raw entered value (e.g., `10` for 10% or `₱100`) |
| `discount_amount` | decimal(10,2), nullable | Snapshotted computed discount |
| `total_amount` | decimal(10,2), nullable | Final computed price — the number balance/receipt calculations key off |
| `cancelled_at` | timestamp, nullable | POS-07 — **not** a new `JobOrderStatus` enum value (CONTEXT.md's code_context explicitly says this phase does not add new `JobOrderStatus` values); follows the existing orthogonal-nullable-timestamp pattern already used for `queue_deprioritized_at` and `design_files.locked_at` |

### `transactions`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | — |
| `job_order_id` | foreignId, **NOT NULL** | Per PROJECT.md's non-negotiable constraint — no standalone sales |
| `type` | string (enum-backed) | `down_payment`, `balance_payment`, `full_payment`, `cancellation_fee` |
| `payment_method` | string (enum-backed) | `cash`, `bank_transfer`, `gcash`, `maya` |
| `amount` | decimal(10,2) | — |
| `status` | string (enum-backed) | `completed` (Cash/Bank Transfer are always immediately this), `pending_confirmation` (GCash/Maya pre-webhook), `failed` |
| `reference_number` | string, nullable | Bank transfer ref / Cashier note |
| `paymongo_payment_intent_id` | string, nullable, unique when set | Dedup key |
| `recorded_by` | foreignId → users.id | Attribution for RPT-02 (Phase 8) |
| `confirmed_at` | timestamp, nullable | Set by `ConfirmPaymentIntent` (Pattern 1) |
| `created_at`/`updated_at` | timestamps | — |

### `accounts_receivable`
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | — |
| `job_order_id` | foreignId | — |
| `balance` | decimal(10,2) | D-09 — balance only, no due date/term (Phase 7's job) |
| `status` | string (enum-backed) | `pending_approval`, `active`, `rejected` — D-06/D-07 |
| `requested_by` | foreignId → users.id | Cashier who requested credit |
| `approved_by` | foreignId → users.id, nullable | Owner who approved/rejected |
| `approved_at` | timestamp, nullable | — |
| `created_at`/`updated_at` | timestamps | — |

All three tables get `#[Fillable([...])]` + `#[ObservedBy(AuditObserver::class)]`, matching every existing domain model (`JobOrder`, `SystemConfiguration`, `RevisionLog`, `DesignFile`). Money-moving models make audit coverage especially load-bearing here, per CONTEXT.md's own callout.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Composer / packagist registry access | Installing `luigel/laravel-paymongo` | ✓ | — | — |
| npm registry access | Installing `qrcode.vue` | ✓ | — | — |
| PayMongo sandbox/test-mode account + API keys | POS-02/03/04 (all payment flows) | **Unverified this session** — no PayMongo dashboard credentials available in this environment | — | Development/testing must proceed against PayMongo's documented test-mode behavior (no real GCash/Maya account needed; `next_action.redirect.url` opens a PayMongo test page with Authorize/Fail buttons) until real sandbox keys are provisioned — flag for the user before Wave 0 execution begins. |
| `config('services.paymongo')` block in `config/services.php` | All PayMongo calls | Not yet present (confirmed by reading the file) | — | Add during Wave 0, mirroring the existing `resend` key pattern |

**Missing dependencies with no fallback:**
- PayMongo sandbox API keys (`PAYMONGO_SECRET_KEY`, `PAYMONGO_PUBLIC_KEY`, `PAYMONGO_WEBHOOK_SIG`) — must be obtained by the user from the PayMongo dashboard before any live integration testing can occur; this is a human-verify checkpoint, not something research or planning can resolve.

**Missing dependencies with fallback:**
- None beyond the above — the two new packages themselves are installable without any additional environment setup.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 5.1.3 (`pestphp/pest`) + `pestphp/pest-plugin-laravel` 5.0.1 |
| Config file | `tests/Pest.php` — `RefreshDatabase` bound globally to `Feature/` |
| Quick run command | `php artisan test --compact --filter=Pos` (or the specific new test file path) |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| POS-01 | Cashier computes price from catalog + rush + discount | feature | `php artisan test --compact --filter=PricingComputation` | ❌ Wave 0 |
| POS-02 | Cash/Bank Transfer payment links to exactly one job order | feature | `php artisan test --compact --filter=RecordPayment` | ❌ Wave 0 |
| POS-03 | GCash/Maya → Pending Confirmation → webhook confirms | feature | `php artisan test --compact --filter=PaymongoWebhook` | ❌ Wave 0 |
| POS-04 | Manual reconciliation check, Cashier or Accounting | feature | `php artisan test --compact --filter=Reconciliation` | ❌ Wave 0 |
| POS-05 | Down payment + balance tracking | feature | `php artisan test --compact --filter=DownPayment` | ❌ Wave 0 |
| POS-06 | Digital receipt generation | feature | `php artisan test --compact --filter=Receipt` | ❌ Wave 0 |
| POS-07 | Cancellation fee, netted against down payment (D-05) | feature | `php artisan test --compact --filter=CancellationFee` | ❌ Wave 0 |
| POS-08 | On-Credit request → Owner approve/reject → posts to AR | feature | `php artisan test --compact --filter=OnCredit` | ❌ Wave 0 |
| POS-09 | Release/hand-over blocked until paid/active-credit | feature | `php artisan test --compact --filter=ReleaseGate` | ❌ Wave 0 |
| Idempotency (Pattern 1) | Webhook + reconciliation racing never double-credits | unit/feature | `php artisan test --compact --filter=ConfirmPaymentIntent` | ❌ Wave 0 |
| Webhook signature | Invalid/missing signature is rejected; CSRF exclusion works | feature | `php artisan test --compact --filter=WebhookSignature` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** targeted `--filter` run for the touched test file
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Cashier/PricingComputationTest.php` — POS-01
- [ ] `tests/Feature/Cashier/RecordPaymentTest.php` — POS-02, POS-05
- [ ] `tests/Feature/Webhooks/PaymongoWebhookTest.php` — POS-03, signature verification, CSRF-exclusion check
- [ ] `tests/Feature/Cashier/ReconciliationTest.php` — POS-04
- [ ] `tests/Unit/Actions/ConfirmPaymentIntentTest.php` — idempotency guard (Pattern 1), the highest-value test in this phase given the "highest pitfall density" flag in STATE.md
- [ ] `tests/Feature/Cashier/ReceiptTest.php` — POS-06
- [ ] `tests/Feature/Cashier/CancellationFeeTest.php` — POS-07, D-05 netting math
- [ ] `tests/Feature/Owner/CreditApprovalTest.php` — POS-08
- [ ] `tests/Feature/Cashier/ReleaseGateTest.php` — POS-09
- [ ] `database/factories/PricingEntryFactory.php`, `TransactionFactory.php`, `AccountsReceivableFactory.php` — new model factories, following `JobOrderFactory`'s existing pattern (`HasFactory` + factory states, per CLAUDE.md's testing rules)
- [ ] PayMongo test-mode fixture/mock strategy for feature tests that don't want to hit the real sandbox API on every run — recommend `Http::fake()` around the Guzzle client the package uses internally, or a small test double implementing the same interface, decided during planning

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | No (webhook is unauthenticated by design; all other POS routes reuse existing Fortify session auth) | Existing Fortify session guard |
| V3 Session Management | No new surface | Existing `VerifySingleSession`/`EnforceIdleSessionTimeout` middleware, unchanged |
| V4 Access Control | Yes | `role:cashier`, `role:accounting_staff`, `role:owner` (see Open Question 1) middleware groups per route, following `EnsureUserHasRole`'s existing `abort_if(..., 403)` pattern |
| V5 Input Validation | Yes | Form Request + Validation Concern trait pairs (existing pattern) for all pricing/payment input; server-side amount/discount-cap validation against `system_configurations`, never trusting a client-submitted total |
| V6 Cryptography | Yes | Webhook HMAC-SHA256 signature verification via `luigel/laravel-paymongo`'s `Signer` — never hand-roll (see Don't Hand-Roll) |
| V9 Communication | Yes | All PayMongo API calls over HTTPS (package hardcodes `https://api.paymongo.com/v1/`); secret key (`sk_*`) stored only in `.env`/`config/services.php`, never exposed to the frontend (only the `client_key` returned per-Payment-Intent is safe for any client-side use, and this phase's design doesn't need even that since the QR is rendered from a server-supplied redirect URL) |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Forged webhook payload claiming a job order is paid | Spoofing | HMAC-SHA256 signature verification (Pattern 1/2) — reject any request without a valid `Paymongo-Signature` before touching the database |
| Replayed/duplicated webhook delivery double-crediting a payment | Repudiation / Tampering | Idempotent `ConfirmPaymentIntent` Action with `lockForUpdate()` guard (Pattern 1) |
| Cashier-submitted discount/rush-fee bypassing the configured cap | Tampering | Server-side validation against `system_configurations` cap values in the Form Request — never trust a client-computed `total_amount` |
| Direct URL access to the Release/Hand-over action bypassing the UI's disabled state | Elevation of Privilege | Server-side `payment_status`/AR-active check inside the controller action itself (Pattern in Anti-Patterns), not just a disabled button — matches RBAC-02's existing "not just hidden navigation" precedent |
| PayMongo secret key committed to git or exposed in a frontend bundle | Information Disclosure | `.env`-only storage via `config/services.php`, following the existing `resend`/`RESEND_API_KEY` pattern exactly; secret key is only ever used server-side inside `luigel/laravel-paymongo`'s Guzzle Basic Auth call |

## Sources

### Primary (HIGH confidence)
- This codebase, read directly: `app/Models/JobOrder.php`, `app/Actions/JobOrder/AssignArtistToJobOrder.php`, `app/Http/Controllers/Public/DesignReviewController.php`, `bootstrap/app.php`, `routes/web.php`, `routes/portals.php`, `routes/owner.php`, `app/Observers/AuditObserver.php`, `app/Support/AuditLogger.php`, `database/seeders/SystemConfigurationSeeder.php`, `config/services.php`, `.env.example`, all existing migrations
- `vendor/luigel/laravel-paymongo/src/**` — installed and read in full during this research (Signer, PaymongoValidateSignature middleware, Paymongo.php facade, Traits/Request.php, config/config.php, Commands/WebhookAddCommand.php)
- Laravel 12.x official docs, CSRF Protection page (`https://laravel.com/docs/12.x/csrf`) — `validateCsrfTokens(except:)` syntax, confirmed applicable to 13.x (doc redirect target)
- Packagist `p2` registry API (`repo.packagist.org/p2/luigel/laravel-paymongo.json`, `repo.packagist.org/p2/paymongo/paymongo-php.json` equivalent lookups) and `npm view qrcode.vue` — structured registry data, not page-summarization

### Secondary (MEDIUM confidence)
- `docs.paymongo.com` / `developers.paymongo.com` pages (webhook signature format, event names, e-wallet payment_method type values, Payment Intent object schema) — fetched via WebFetch, which repeatedly returned truncated/restructured content across ~15 attempts; cross-verified where possible against the actual installed package source (the HMAC formula matched exactly between the docs-summary and the package's `DefaultSigner.php`, raising confidence on that specific claim to effectively HIGH)
- WebSearch results on PayMongo idempotency-key support, GCash/Maya completion time windows

### Tertiary (LOW confidence)
- None retained as unflagged claims — all LOW-confidence findings were either dropped or explicitly logged in the Assumptions Log above.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — package versions/compatibility verified via direct registry query and actual `composer require`/`npm install` in an isolated sandbox, not training-data memory
- Architecture: HIGH — every recommended pattern is a direct extension of an existing, working pattern already in this codebase (not a novel proposal)
- PayMongo API specifics (webhook events, exact payload shapes): MEDIUM — official docs site repeatedly served truncated content during this research session; the single most safety-critical claim (HMAC signature formula) was independently corroborated against the installed package's actual source code
- Pitfalls: HIGH for CSRF/routing/idempotency pitfalls (grounded in this codebase's actual current state, verified by direct `grep`); MEDIUM for PayMongo-specific pitfalls (Source-vs-Maya, Idempotency-Key)

**Research date:** 2026-09-04
**Valid until:** 2026-10-04 (30 days — PayMongo API is a third-party service that can change without this project's control; re-verify webhook event names and payload shapes against PayMongo's sandbox before Wave 0 execution if this research is more than a few days old by the time planning begins)
