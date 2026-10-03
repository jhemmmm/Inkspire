---
phase: quick-261003-w8o
plan: 01
subsystem: payments
tags: [paymongo, gcash, maya, tracking, cashier, mail]
requires: []
provides:
  - PriceJobOrder and StartPaymongoPayment shared actions
  - Cashier payment-link flow (save price, email link)
  - Customer pay flow from the tracking-token page
affects: [cashier, public tracking, transactions.recorded_by]
key-files:
  created:
    - app/Actions/POS/PriceJobOrder.php
    - app/Actions/POS/StartPaymongoPayment.php
    - app/Http/Controllers/Cashier/PaymentLinkController.php
    - app/Http/Controllers/Public/OnlinePaymentController.php
    - app/Http/Requests/Cashier/SendPaymentLinkRequest.php
    - app/Http/Requests/Public/PayOnlineRequest.php
    - app/Mail/PaymentRequested.php
    - resources/views/mail/payment-requested.blade.php
    - database/migrations/2026_10_03_151651_make_transactions_recorded_by_nullable.php
    - tests/Feature/Cashier/PaymentLinkTest.php
    - tests/Feature/Public/OnlinePaymentTest.php
  modified:
    - app/Models/JobOrder.php
    - app/Models/Transaction.php
    - app/Http/Controllers/Cashier/PaymentController.php
    - app/Http/Controllers/Cashier/CreditRequestController.php
    - app/Http/Controllers/Public/TrackingController.php
    - resources/js/pages/cashier/JobOrderPayment.vue
    - resources/js/pages/cashier/Dashboard.vue
    - resources/js/pages/public/TrackingToken.vue
    - routes/portals.php
    - routes/web.php
    - tests/Feature/Public/TrackingTokenTest.php
decisions:
  - Online payment is always the full outstanding balance; only the counter may take a part payment
  - The unlocked route-bound job order is never mutated; pricing is applied only to the locked re-read inside StartPaymongoPayment
  - Payment-state rules live in JobOrder::onlinePaymentState(), shared by the tracking page and the pay endpoint
metrics:
  completed: 2026-10-03
---

# Quick 261003-w8o: Online GCash/Maya payment and Cashier payment link

One-liner: the Cashier can price a job order without taking payment and email a payment link; the customer pays the full balance by GCash or Maya from their tracking page, reusing the existing webhook and ConfirmPaymentIntent unchanged.

## Commits

- 8e31dd3 refactor: PriceJobOrder and StartPaymongoPayment extracted, `recorded_by` nullable migration (file only)
- 1cfdb47 feat: Cashier payment link (controller, request, mail, page button, dashboard badge, 6 tests)
- 2ae1733 feat: customer pay flow on the tracking-token page (controller, request, summary, page, 10 test cases)

## Verification

- `php artisan test --compact tests/Feature/Cashier tests/Feature/Public tests/Feature/Webhooks tests/Feature/Reports`: 250 passed. No file under tests/Feature/Cashier was edited (PaymentLinkTest.php is new).
- Pint clean. `npx vp check --fix` clean on the three touched Vue files. `npm run types:check` clean.
- Larastan: 19 errors, the pre-existing baseline, none in files created or touched.
- `grep -rn "base_price_snapshot' =>" app/Http` finds only a read in FrontlineStaff/JobOrderController, no write.

## Deviations from Plan

**1. [Orchestrator correction] PayMongo branch does not touch the unlocked model.** `PaymentController::storePaymongoIntent()` computes the total with `ComputeJobOrderPrice` directly and passes the validated pricing into `StartPaymongoPayment`, which applies `PriceJobOrder` only to its own `lockForUpdate()` re-read. Nothing is persisted if a PayMongo call throws.

**2. [Rule 3] Larastan array shape.** The `$pricing` docblock shape in the two new actions is `array<string, mixed>` rather than a strict shape, because `FormRequest::validated()` returns `array<string, mixed>` and the strict shape added 3 errors.

**3. [Rule 3] Return type.** `OnlinePaymentController::store()` returns `RedirectResponse|SymfonyResponse` because `Inertia::location()` is typed as the Symfony base response.

**4. TrackingTokenTest edits.** Beyond the key count (5 to 6), the `assertDontSee('8642')` assertion was replaced: the fixture order is priced, so its amount due legitimately appears now. It now asserts the figure appears inside `result.payment`. I also added `result.payment` to the first test's `where` chain. Every PII, raw-status, token, `payment_status` and `total_amount` assertion is kept and passes.

## Known limits

- The UI was type-checked and linted but NOT driven in a browser by me. The orchestrator verifies it.
- The migration was created but NOT run against the dev database. The tests migrate their own SQLite database, so the `change()` is exercised on SQLite only, not on MySQL.
- No real mail was sent and PayMongo was never called; every PayMongo call in tests goes through the mocked facade.
- PayMongo's status vocabulary is unverified against a sandbox, as ReconciliationController already notes.
- Double-submit: two near-simultaneous POSTs to the pay endpoint while the order is `due` could each open an intent. The buttons disable while a request is in flight and the route is throttled, but the locked re-read does not refuse a job order that is already PendingConfirmation (matching the Cashier path, which I left unchanged). Not tested.
- The "pending" button always posts GCash as the wallet, since the last-used method is not sent to the page. The pending-with-succeeded and awaiting_next_action paths ignore the wallet.
- Pending checkout in the token page: a pending intent whose status is `cancelled` or expired falls into the re-attach branch rather than failing the transaction (the Cashier-side reconcile handles that).

## Self-Check: PASSED

All created files exist and the three commit hashes resolve in `git log`.

## Orchestrator review and browser verification

Commit 6f5a4ab (review of the payment path, then a browser pass):
- Cashier GCash/Maya regression from the extraction: the locked re-check's refusals (paid, cancelled, written off, credit pending) were caught by the controller's `catch (Throwable)` and shown as "Couldn't start the payment". `HttpExceptionInterface` is now rethrown. New test in `RecordPaymentTest`.
- Public pay takes a cache lock per job order, so a double submit cannot open two PayMongo intents.
- Public resume handles each intent status by name: `succeeded` confirms; `cancelled` fails the transaction so the order returns to "due"; `awaiting_next_action` reuses the existing checkout URL; `awaiting_payment_method` re-attaches with the wallet stored on the transaction; anything else (`processing` above all) is left untouched. Previously everything that was not `succeeded`/`awaiting_next_action` had a new payment method attached, and the wallet was always the posted one (the pending page always posts GCash).
- A refusal from the locked re-check on the public path returns to the tracking page with no error.
- The tracking page scrolls the payment error into view.

Driven in headless Chrome against a seeded SQLite copy with the log mailer:
- Cashier dashboard shows the Online badge on online-lane orders only.
- Payment page: "Save price & email payment link" saved the price with no transaction and unchanged payment status, toast named the customer's email, and the logged email has the number, the amount and the Pay online link. Fits at 375px.
- Tracking page, logged out: amount due with both wallet buttons; keyboard activation works; at 375px no overflow.
- Pending state (set up directly in the scratch database) shows the waiting message and the single continue button; paid state after a cash payment at the counter shows "Paid. Thank you." with no buttons.
- The number-lookup page shows no amount and no pay button.
- No console errors.

NOT verified, and cannot be here: the real GCash/Maya hand-off. This environment has no PayMongo keys, so pressing Pay exercised only the failure path (readable error, nothing written, buttons usable again). The happy path, resume paths and webhook confirmation are covered by feature tests with the Paymongo facade mocked. PayMongo's intent status names remain unverified against the sandbox, as `ReconciliationController` already notes.

Also not done: `php artisan migrate` on the dev database (two new migrations: `online_orders`, nullable `transactions.recorded_by`; the latter's `->change()` has only run on SQLite).

Complete suite after the fixes: 886 tests, 883 passed, 3 skipped. `vue-tsc` clean. Larastan 19, all pre-existing, none in a touched file.
