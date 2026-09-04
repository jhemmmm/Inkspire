---
phase: 05-pos-payments
plan: 03
subsystem: payments
tags: [laravel, inertia, vue3, pest, pos, payments, paymongo, gcash, maya, qrcode, webhook, hmac]

# Dependency graph
requires:
  - phase: 05-pos-payments (plan 05-01)
    provides: pricing_database/transactions schema, Transaction/JobOrder payment models, PaymentController/JobOrderPayment.vue for Cash/Bank Transfer
  - phase: 05-pos-payments (plan 05-02)
    provides: Receipt page/controller pattern (not directly extended this plan, but the redirect-to-receipt-on-full-payment convention it established)
provides:
  - luigel/laravel-paymongo (^2.6) + qrcode.vue (^3.10) installed and wired
  - ConfirmPaymentIntent — shared, lock-guarded idempotency boundary for GCash/Maya payment confirmation
  - PaymongoWebhookController — public, unauthenticated, HMAC-signature-verified webhook receiver at POST webhooks/paymongo
  - PaymentController::store() branches GCash/Maya into a Payment Intent + QR flow (Cash/Bank Transfer path unchanged)
  - PaymentQrCode.vue + JobOrderPayment.vue's Scan to Pay sub-view
affects: [05-04, 05-05, 05-06, 05-07, 07-accounts-receivable, 08-reporting]

# Tech tracking
tech-stack:
  added: [luigel/laravel-paymongo ^2.6, qrcode.vue ^3.10]
  patterns:
    - "Paymongo facade calls only (Paymongo::paymentIntent()->create()/attach()), never the model's own attach()/cancel() convenience methods — those instantiate `new Paymongo` directly, bypassing the facade/container and making them unmockable in tests"
    - "PayMongo API responses read via ->getData()['key'] (flat array), never magic property/getter chains — BaseModel::setAttributes() flattens the response envelope's 'attributes' wrapper directly onto the model, so getData() has no nested 'attributes' key at all (verified against the installed package's actual source, not assumed from the plan's literal code sketch)"
    - "Tests mock the Paymongo facade (Mockery shouldReceive/andReturnSelf), not Http::fake() — the package's Request trait instantiates `new GuzzleHttp\\Client()` directly, which Http::fake() cannot intercept"
    - "GCash/Maya QR sub-view's displayed amount/method are sourced from the persisted pending Transaction row via dedicated Inertia props (pendingPaymongoAmount/pendingPaymongoMethod), not local Vue refs — a full Inertia redirect after form submission resets all local component state to its defaults"

key-files:
  created:
    - app/Actions/POS/ConfirmPaymentIntent.php
    - app/Http/Controllers/Webhooks/PaymongoWebhookController.php
    - config/paymongo.php
    - resources/js/components/PaymentQrCode.vue
    - tests/Feature/Webhooks/PaymongoWebhookTest.php
    - tests/Unit/Actions/ConfirmPaymentIntentTest.php
  modified:
    - app/Http/Controllers/Cashier/PaymentController.php
    - app/Concerns/PaymentValidationRules.php
    - bootstrap/app.php
    - routes/web.php
    - .env.example
    - resources/js/pages/cashier/JobOrderPayment.vue
    - tests/Feature/Cashier/RecordPaymentTest.php

key-decisions:
  - "Checkpoint (Task 1) was pre-approved by the orchestrator before this agent started — user replied 'Approved' to the Package Legitimacy Audit findings for luigel/laravel-paymongo and qrcode.vue; proceeded directly to Task 2/3 without re-prompting"
  - "config/paymongo.php (published by the package) is authoritative for PAYMONGO_* env vars — no duplicate 'paymongo' entry added to config/services.php, per the plan's explicit skip-if-package-has-own-config instruction"
  - "route('home') used as the PayMongo attach() return_url — this is a Cashier-counter QR flow, not a customer self-checkout; the webhook (not this redirect) is the actual source of truth, so a placeholder, always-reachable destination is sufficient"
  - "'Check Payment Status' button intentionally NOT rendered this plan — left as a <!-- Plan 05-04 wires... --> comment anchor instead of a disabled/dead button, per the plan's explicit preference"

patterns-established:
  - "Pattern: any future PayMongo API interaction in this codebase must go through the Paymongo facade's fluent methods directly (never a model's attach()/cancel() convenience wrapper) to stay mockable"
  - "Pattern: derive PayMongo response payload data via ->getData()['key'], with explicit (string)/(float) casts at the point of use, never via the package's magic __get/__call chain"

requirements-completed: [POS-03]

# Metrics
duration: ~75min
completed: 2026-09-05
---

# Phase 5 Plan 3: PayMongo GCash/Maya payments Summary

**`luigel/laravel-paymongo` + `qrcode.vue` wired end-to-end: `ConfirmPaymentIntent` is the sole idempotent confirmation path, a signature-verified/CSRF-excluded webhook confirms real Payment Intents, and the Cashier sees a counter QR code with a Switch Payment Method escape hatch.**

## Performance

- **Duration:** ~75 min (includes worktree environment setup — vendor/node_modules copy, .env, sqlite db, build)
- **Started:** 2026-09-04T22:03:08+08:00 (base commit, prior wave)
- **Completed:** 2026-09-05T00:28:43+08:00
- **Tasks:** 3/3 (Task 1 checkpoint pre-approved by orchestrator; Task 2 and Task 3 fully implemented)
- **Files modified:** 17 (6 created, 11 modified)

## Accomplishments
- `luigel/laravel-paymongo` v2.6.0 and `qrcode.vue` v3.10.0 installed; `config/paymongo.php` published and its actual env var names (`PAYMONGO_PUBLIC_KEY`/`PAYMONGO_SECRET_KEY`/`PAYMONGO_WEBHOOK_SIG`) confirmed by reading the published file directly, then added as blank placeholders to `.env.example`
- `bootstrap/app.php` CSRF-excludes `webhooks/paymongo`; `routes/web.php` registers the route outside every `role:*` group, signature-verified via the package's own `paymongo.signature:payment_paid` middleware alias
- `ConfirmPaymentIntent` — the single lock-guarded (`lockForUpdate()` + `status !== PendingConfirmation` guard) idempotency boundary, matching `AssignArtistToJobOrder`'s established pattern — confirmed both by the webhook and, in Plan 05-04, manual reconciliation
- `PaymongoWebhookController` parses `payment.paid`/`payment.failed` events, looks up the matching `Transaction` by `paymongo_payment_intent_id`, and acknowledges unrecognized intents with a `204` no-op (never a `404`/`500` that would cause PayMongo to retry forever)
- `PaymentController::store()` branches GCash/Maya into a real `Paymongo::paymentIntent()->create()` → `paymentMethod()->create()` → `paymentIntent()->attach()` sequence, creating a `pending_confirmation` Transaction and flashing the redirect URL back to the page
- `PaymentQrCode.vue` + `JobOrderPayment.vue`'s "Scan to Pay" sub-view: GCash/Maya radio options, QR render, instructions text sourced from the actual persisted pending transaction (not local form state), and a fully working "Switch Payment Method" button (D-13)
- Full test suite (218 tests, 215 passed / 3 pre-existing skips) green; `npm run types:check` and `composer types:check` (PayMongo-code-related errors) both clean

## Task Commits

Each task was committed atomically:

1. **Task 1: Dependency legitimacy checkpoint** — no commit (checkpoint only, no files modified; pre-approved by the orchestrator before this agent started, per this plan's explicit pre-resolution)
2. **Task 2: Install PayMongo dependencies and build the idempotent confirmation + webhook backend** - `cd2a4bd` (feat)
3. **Task 3: Frontend — QR display and Scan to Pay sub-view** - `0e9ae56` (feat)

_No plan-metadata commit yet — SUMMARY.md commit follows this file._

## Files Created/Modified

**Task 2 (backend):**
- `app/Actions/POS/ConfirmPaymentIntent.php` - shared idempotent confirmation Action
- `app/Http/Controllers/Webhooks/PaymongoWebhookController.php` - signature-verified, CSRF-excluded webhook receiver
- `config/paymongo.php` - published package config (env var names confirmed against actual file, not assumed)
- `app/Http/Controllers/Cashier/PaymentController.php` - GCash/Maya branch (`storePaymongoIntent`), pricing-snapshot-if-first-payment reused from the Cash/Bank Transfer path
- `app/Concerns/PaymentValidationRules.php` - `payment_method` widened to include `gcash`/`maya`
- `bootstrap/app.php` - `validateCsrfTokens(except: ['webhooks/paymongo'])`
- `routes/web.php` - `POST webhooks/paymongo` route, outside every `role:*` group
- `.env.example` - new `# PayMongo` section (`PAYMONGO_PUBLIC_KEY`, `PAYMONGO_SECRET_KEY`, `PAYMONGO_WEBHOOK_SIG`, blank)
- `tests/Feature/Webhooks/PaymongoWebhookTest.php` - 6 cases: missing signature rejected + no mutation, no-CSRF-no-419, valid signature confirms, valid signature fails, unrecognized intent no-ops, wrong-secret signature rejected
- `tests/Unit/Actions/ConfirmPaymentIntentTest.php` - 4 cases: success→Paid, partial success→PartiallyPaid, failure leaves job order PendingConfirmation, duplicate call is a structural no-op
- `tests/Feature/Cashier/RecordPaymentTest.php` - extended (not duplicated) with GCash success case (facade-mocked) and Maya API-failure case

**Task 3 (frontend):**
- `resources/js/components/PaymentQrCode.vue` - thin `qrcode.vue` wrapper (`QrcodeVue` default export, confirmed via installed `dist/index.d.ts`)
- `resources/js/pages/cashier/JobOrderPayment.vue` - GCash (`Wallet`)/Maya (`Smartphone`) radio rows after Bank Transfer; `subView` ref flips to the Scan to Pay card; "Switch Payment Method" resets state; "Check Payment Status" left as a TODO comment anchor for Plan 05-04

## Decisions Made

- Task 1's checkpoint was already resolved by the orchestrator before this agent was spawned (user approved both packages) — recorded here per the plan's instruction, no re-prompt issued.
- `config/paymongo.php` is authoritative for all `PAYMONGO_*` values; no `config/services.php` duplication added, confirmed by reading the published config file directly rather than assuming RESEARCH.md's guess.
- `route('home')` used as the PayMongo `attach()` return_url — a placeholder destination since this is a Cashier-counter flow where the webhook, not the browser redirect, is the source of truth.
- "Check Payment Status" rendered as a comment anchor, not a disabled button, per the plan's explicit "rather than shipping a dead button" preference.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Composer dependency resolution required downgrading guzzlehttp/guzzle (8.1.0 → 7.15.5) and upgrading laravel/framework (v13.29.0 → v13.30.1)**
- **Found during:** Task 2, `composer require luigel/laravel-paymongo`
- **Issue:** `luigel/laravel-paymongo` requires `guzzlehttp/guzzle: ^7.0`, but this project had guzzle locked to 8.1.0 (via `laravel/framework`'s `^7.8.2|^8.0` range). A plain `composer require` failed to resolve; `--with-all-dependencies` was needed, which also pulled `laravel/framework` to v13.30.1 (still within `laravel/framework: ^13.0`, a patch/minor bump only).
- **Fix:** Ran `composer require luigel/laravel-paymongo --with-all-dependencies`. Verified `guzzlehttp/guzzle: ^7.8.2|^8.0` is satisfied by every other dependency (`laravel/boost`, `resend/resend-php`, `league/flysystem`) at 7.15.5, so no other package broke.
- **Files modified:** `composer.json`, `composer.lock`
- **Verification:** Full test suite green (218/218, 3 pre-existing skips) after the downgrade/upgrade.
- **Committed in:** `cd2a4bd` (Task 2 commit)

**2. [Rule 1 - Bug] `getData()` does not nest under an `attributes` key — the plan's literal `->getData()['attributes']['next_action']...` access path is wrong against the installed package**
- **Found during:** Task 2, before writing the controller code
- **Issue:** `RESEARCH.md`/the plan's code sketch assumed `$attached->getData()['attributes']['next_action']['redirect']['url']`. Reading `Luigel\Paymongo\Models\BaseModel::setAttributes()` directly (and confirming via a tinker spike against the real installed package) shows the response envelope's `attributes` array is flattened directly onto the model — `getData()` returns `{id, type, ...every individual attribute key...}` with no nested `attributes` wrapper at all.
- **Fix:** Used `$attached->getData()['next_action']['redirect']['url'] ?? null` (no `['attributes']` level).
- **Files modified:** `app/Http/Controllers/Cashier/PaymentController.php`
- **Verification:** Confirmed via `php artisan tinker` spike before writing the controller, then via the GCash `RecordPaymentTest` case, which asserts the exact redirect URL flows through to the flashed session value.
- **Committed in:** `cd2a4bd` (Task 2 commit)

**3. [Rule 1 - Bug] The model's own `attach()`/`cancel()` convenience methods bypass the facade, making the flow unmockable and forcing a real network call in tests**
- **Found during:** Task 2, before writing the controller's PayMongo calls
- **Issue:** `Luigel\Paymongo\Models\PaymentIntent::attach()` internally does `(new Paymongo)->paymentIntent()->attach(...)` — a fresh, directly-instantiated `Paymongo` object, not the container-bound facade singleton. Mocking `Paymongo::shouldReceive(...)` has zero effect on this call; using the plan's literal `$intent->attach($paymentMethod->id, ...)` would have made a real Guzzle HTTP request during every test run (no PayMongo credentials exist in this environment, so this would either hang or error).
- **Fix:** Called `Paymongo::paymentIntent()->attach($intent, $paymongoPaymentMethodId, route('home'))` — the trait method via the facade — for all three PayMongo calls (create intent, create payment method, attach), keeping every PayMongo interaction on the mockable seam.
- **Files modified:** `app/Http/Controllers/Cashier/PaymentController.php`
- **Verification:** `RecordPaymentTest`'s GCash case fully mocks all three facade calls (Mockery `shouldReceive`); no real network call occurs (test passes without network access).
- **Committed in:** `cd2a4bd` (Task 2 commit)

**4. [Rule 1 - Bug] Copywriting Contract capitalization — `ucfirst($paymentMethod)` produces "Gcash", not "GCash"**
- **Found during:** Task 2, writing the PayMongo-intent-creation-failed error message
- **Issue:** The plan's literal code (`ucfirst($request->input('payment_method'))`) would render "Couldn't start the Gcash payment...", not matching `05-UI-SPEC.md`'s Copywriting Contract, which uses "GCash" (capital C).
- **Fix:** Explicit `$methodLabel = $paymentMethod === PaymentMethod::Gcash->value ? 'GCash' : 'Maya';` used in both the error toast and the frontend instructions text.
- **Files modified:** `app/Http/Controllers/Cashier/PaymentController.php`
- **Verification:** Maya failure-path test in `RecordPaymentTest` exercises this code path (doesn't assert exact copy text, but confirms the branch executes without error).
- **Committed in:** `cd2a4bd` (Task 2 commit)

**5. [Rule 1 - Bug] QR sub-view's displayed amount/method would be wrong for a Down Payment, or after any redirect, since local Vue refs reset to their defaults**
- **Found during:** Task 3, designing the sub-view state
- **Issue:** The plan's sketch implied deriving the "Ask the customer to scan..." instructions text from the page's live pricing-preview `targetAmount`/local `paymentMethod` ref. Since the QR sub-view only appears after a full Inertia redirect (`back()`) remounts the component with fresh props, every local ref (including `paymentType`/`downPaymentAmount`/`paymentMethod`) resets to its default — the text would show the full remaining balance and always say "Maya" (default fallback), regardless of what was actually submitted.
- **Fix:** `PaymentController::edit()` now also passes `pendingPaymongoAmount`/`pendingPaymongoMethod`, sourced from the actual persisted `pending_confirmation` Transaction row (the one just created), and the Vue page reads those instead of local state for the QR sub-view's text.
- **Files modified:** `app/Http/Controllers/Cashier/PaymentController.php`, `resources/js/pages/cashier/JobOrderPayment.vue`
- **Verification:** `RecordPaymentTest`'s GCash case follows the redirect with a fresh GET and asserts `paymongoRedirectUrl` on the resulting Inertia page props.
- **Committed in:** `cd2a4bd` (backend half), `0e9ae56` (frontend half)

**6. [Rule 1 - Bug] PHPStan flagged 3 new errors from magic-getter/type-mismatch access on PayMongo response models**
- **Found during:** Task 2, `composer types:check`
- **Issue:** `$intent->id`/`$paymongoPaymentMethod->id` access (undefined property on `BaseModel`, per Pitfall 4) and passing the trait's generically-typed `create()` return value (`BaseModel`) into `attach()`'s `PaymentIntent`-typed parameter.
- **Fix:** Read IDs via `->getData()['id']` with explicit `(string)` casts (per Pitfall 4's own recommendation); added a genuine `if (! $intent instanceof PaymentIntent) { throw new RuntimeException(...); }` runtime guard (defensive code, not a suppression) before calling `attach()`.
- **Files modified:** `app/Http/Controllers/Cashier/PaymentController.php`
- **Verification:** `composer types:check` — the 3 `PaymentController.php` errors are gone; remaining 5 errors are pre-existing, in files this plan never touched (`QueueEntryController.php`, `SavePricingAndPaymentRequest.php`, `UpdateSystemConfigurationRequest.php`), matching Plans 05-01/05-02's documented baseline.
- **Committed in:** `cd2a4bd` (Task 2 commit)

---

**Total deviations:** 6 auto-fixed (1 blocking/dependency-resolution, 5 bugs — 3 caught by directly reading the installed vendor source rather than trusting the plan's literal code sketch, 1 copywriting mismatch, 1 static-analysis)
**Impact on plan:** All auto-fixes were necessary corrections for correctness (payment amount/method display, testability, static analysis) or dependency resolution. No architectural changes, no scope creep — every fix stayed inside this plan's stated files.

## Issues Encountered

- **Same worktree cold-start situation as Plans 05-01/05-02** (no `vendor/`, `node_modules/`, `.env`, or build output). Resolved identically: full copy of `vendor`/`node_modules` from the main checkout, `composer dump-autoload`, `.env` from `.env.example` + `key:generate`, worktree-local SQLite DB, `migrate:fresh --seed`, `npm run build`.
- **Worktree base drift at spawn time:** this worktree's initial HEAD (`351fb0d "init"`, a single orphan commit) did not descend from the expected base commit (`a05b222`). Corrected via the sanctioned `git reset --hard` inside the mandatory `<worktree_branch_check>` setup step (working tree was clean at that point, nothing lost).
- **`php artisan make:controller Webhooks/PaymongoWebhookController --invokable`** failed with `UnexpectedValueException: Invalid route action` — referencing the not-yet-created controller class in `routes/web.php` before creating the file causes Laravel's route-loading bootstrap (which every Artisan command triggers) to fail eagerly. Resolved by writing the controller file directly instead of via the artisan scaffolding command.
- **`npm run check` (vite-plus lint/format, no `--fix`) reports 150 pre-existing formatting issues repo-wide**, none of which are in this plan's two touched Vue files (`PaymentQrCode.vue`, `JobOrderPayment.vue`) — confirmed by name-filtering the output. Left untouched per SCOPE BOUNDARY and Plan 05-02's documented precedent (`npm run check:fix` reformats unrelated files repo-wide and should not be run casually).
- **`composer types:check` (Larastan) intermittently affected by a shared `/tmp/phpstan` cache** — cleared before each run in this session, matching Plans 05-01/05-02's documented workaround.

## User Setup Required

**External services require manual configuration.** This plan's frontmatter declares a `user_setup` block (not a separate `05-03-USER-SETUP.md` file):
- Add to `.env` (not tracked, already blank-placeholdered in `.env.example`): `PAYMONGO_PUBLIC_KEY`, `PAYMONGO_SECRET_KEY` (from PayMongo Dashboard → Developers → API keys, test-mode `pk_test_*`/`sk_test_*`), `PAYMONGO_WEBHOOK_SIG` (from PayMongo Dashboard → Developers → Webhooks — create an endpoint pointing at `{APP_URL}/webhooks/paymongo`, subscribed to `payment.paid` and `payment.failed`, copy the generated signing secret).
- **Not verifiable in this session:** the plan's Task 3 "Manual verification" bullet (selecting GCash, submitting, seeing a QR code sourced from a real PayMongo redirect URL) requires the user's own PayMongo test-mode sandbox keys, which are not present in this environment. All automated coverage (`Http`-free, fully facade-mocked tests) passes; the live end-to-end path against PayMongo's actual sandbox is deferred to the user's own verification once real keys are added.

## Next Phase Readiness

- `ConfirmPaymentIntent` is ready for Plan 05-04's manual reconciliation controller to call directly — no changes needed to this Action.
- `PaymongoWebhookController`'s payload-parsing shape (`data.attributes.type` / `data.attributes.data.attributes.payment_intent_id`) is flagged MEDIUM confidence per `RESEARCH.md` — re-verify against a real PayMongo sandbox test-delivery once the user provisions keys, and adjust the two `$request->json(...)` paths in `PaymongoWebhookController` if the actual shape differs.
- The "Check Payment Status" TODO anchor in `JobOrderPayment.vue`'s QR sub-view is exactly where Plan 05-04 should wire its reconciliation click handler.
- No blockers.

---
*Phase: 05-pos-payments*
*Completed: 2026-09-05*
