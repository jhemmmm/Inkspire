---
phase: quick-261003-vxm
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
    - app/Models/OnlineOrder.php
    - database/factories/OnlineOrderFactory.php
    - database/migrations/*_create_online_orders_table.php
    - app/Http/Requests/Public/StoreOnlineOrderRequest.php
    - app/Concerns/JobOrderValidationRules.php
    - app/Http/Controllers/Public/OnlineOrderController.php
    - app/Mail/ConfirmOnlineOrder.php
    - resources/views/mail/confirm-online-order.blade.php
    - routes/web.php
    - routes/console.php
    - bootstrap/app.php
    - tests/Feature/Public/OnlineOrderTest.php
    - resources/js/pages/public/Order.vue
    - resources/js/pages/public/OrderConfirm.vue
    - resources/js/components/JobOrderRowFields.vue
    - resources/js/components/JobOrderPriceFields.vue
    - resources/js/pages/Welcome.vue
    - tests/Feature/Public/WelcomePageTest.php
autonomous: true
requirements: [QUICK-261003-vxm]
must_haves:
    truths:
        - "A visitor can submit an order on /order without logging in and sees a 'Check your email' state"
        - 'Nothing is written to customers, queue_entries or job_orders until the emailed signed link is confirmed'
        - 'Confirming creates/reuses the customer, opens an O-lane visit and sends JobOrdersReceived; a second confirm creates nothing'
        - 'A client-posted quoted_amount never reaches CreateJobOrder; prices are never sent to the public page'
        - 'Expired or invalid links render a friendly expired state, not a 404/blank'
        - 'Landing page links to the order page and still shows no staff links'
    artifacts:
        - path: 'app/Models/OnlineOrder.php'
          provides: 'Prunable pending-order model'
        - path: 'app/Http/Controllers/Public/OnlineOrderController.php'
          provides: 'create/store/show/confirm'
        - path: 'resources/js/pages/public/Order.vue'
          provides: 'Public order form'
        - path: 'resources/js/pages/public/OrderConfirm.vue'
          provides: 'pending/confirmed/expired states'
        - path: 'tests/Feature/Public/OnlineOrderTest.php'
          provides: '12 specified feature tests'
    key_links:
        - from: 'OnlineOrderController::confirm'
          to: 'OpenVisit'
          via: "$openVisit($customer->id, $payload['job_orders'], QueueEntry::ONLINE_PREFIX)"
        - from: 'bootstrap/app.php'
          to: 'public/OrderConfirm'
          via: "routeIs('public.orders.*') branch of InvalidSignatureException"
---

<objective>
Part 3 of 4: public website order form with email confirmation. A client submits an order, receives a signed 48-hour link, and the order enters the shop workflow (customer + O-lane visit + job orders) only on confirmation. Strictly excludes online payment (Part 4), PayMongo and cashier changes.

Output: OnlineOrder model/migration/factory, request, controller, mail, routes, prune schedule, expired-link branch, two Vue pages, customer-audience copy, Welcome call to action, tests.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@CLAUDE.md
@/home/user/.claude/plans/let-s-create-a-plan-wondrous-hennessy.md
@app/Http/Controllers/Artist/JobOrderIntakeController.php
@app/Http/Requests/Artist/StoreJobOrderRequest.php
@app/Concerns/JobOrderValidationRules.php
@app/Http/Controllers/Public/DesignReviewController.php
@resources/js/pages/public/DesignReview.vue
@resources/js/pages/artist/NewJobOrder.vue
@resources/js/pages/Welcome.vue
@tests/Feature/Public/TrackingTokenTest.php

Rules: read .claude/skills laravel-best-practices, testing-best-practices, inertia-vue-development, wayfinder-development, tailwindcss-development SKILL.md first. Main tree only, no worktrees, no composer install/require/dump-autoload, no dependency changes. `.env` has a REAL SMTP mailer and MySQL dev DB: never send mail or write records outside the test suite, and DO NOT run `php artisan migrate` against dev (tests migrate SQLite themselves). Do not claim the UI was driven in a browser (orchestrator does that). Prices are never shown publicly. Tracking URLs (bearer credentials) appear ONLY on the confirmed state of the signed page and in the email, never on public/Order.
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Backend - model, request, controller, mail, routes, prune, expired branch, tests</name>
  <files>app/Models/OnlineOrder.php, database/factories/OnlineOrderFactory.php, database/migrations/*_create_online_orders_table.php, app/Http/Requests/Public/StoreOnlineOrderRequest.php, app/Concerns/JobOrderValidationRules.php, app/Http/Controllers/Public/OnlineOrderController.php, app/Mail/ConfirmOnlineOrder.php, resources/views/mail/confirm-online-order.blade.php, routes/web.php, routes/console.php, bootstrap/app.php, tests/Feature/Public/OnlineOrderTest.php</files>
  <behavior>
    The 12 tests, exactly: (1) GET /order renders public/Order, pricingEntries carry only id,name,unit (no base_price); (2) valid submit stores one pending OnlineOrder, sends ConfirmOnlineOrder to the email, creates no customer/queue entry/job order; (3) signed confirm POST creates customer, an O-lane visit, job orders, sets confirmed_at and sends JobOrdersReceived to the submitted email; (4) second confirm POST idempotent (one visit, mail once); (5) unsigned confirm URL refused, nothing created; (6) posted quoted_amount ignored, stored quote equals catalog computation; (7) Type A `.xyz` rejected at submit with the customer-facing reason (no "Ask the customer"), nothing stored; (8) filled honeypot `website` rejected; (9) existing customer with same contact_number reused and not overwritten; (10) pre-checked passing Type A ends for_production after confirm, Type B stays intake unassigned; (11) `model:prune` removes a 4-day-old unconfirmed order and its file, keeps a confirmed order's file; (12) more than 5 items rejected.
    Use Mail::fake(), Storage::fake('local'), factories, unique global helper function names, `php artisan make:test --pest OnlineOrderTest`.
  </behavior>
  <action>
    Scaffold: `php artisan make:model OnlineOrder -mf --no-interaction` and `php artisan make:mail ConfirmOnlineOrder --markdown=mail.confirm-online-order --no-interaction`, `make:request Public/StoreOnlineOrderRequest`, `make:controller Public/OnlineOrderController`. Match sibling conventions (check app/Models/User.php and an existing domain model for attribute style).

    Migration: id, string email, json payload, nullable timestamp confirmed_at, nullable foreignId queue_entry_id constrained('queue_entries')->nullOnDelete(), timestamps. Model: `#[Fillable([...])]`, `casts()` (payload array, confirmed_at datetime), PHPDoc @property block, HasFactory, `use Prunable`; `prunable()` = created_at <= now()->subDays(3); `pruning()` deletes each payload job_orders[*].file_path from the `local` disk ONLY when confirmed_at is null. Factory default: a valid one-row Type B payload with no file. Schedule in routes/console.php: `Schedule::command('model:prune', ['--model' => [OnlineOrder::class]])->daily();`.

    Trait: add `bool $customerFacing = false` to `rejectUnusableTypeAFiles()` in JobOrderValidationRules; when true, the reason becomes `Str::before($reason, ' Ask the customer')` with a `// ponytail:` comment naming the coupling to ValidateJobOrderFile's wording. Existing callers/tests unchanged.

    Request (App\Http\Requests\Public\StoreOnlineOrderRequest, per locked spec): flat `name` (customerNameRules), `organization` (organizationRules), `contact_number` => ['required','string','max:20'] with NO unique rule (returning customers must be able to order), `email` (customerEmailRules), `address` (addressRules); `...Arr::except($this->jobOrdersRules(), ['job_orders.*.quoted_amount'])` then `'job_orders' => ['required','array','min:1','max:5']`; `'website' => ['prohibited']` honeypot. TRUST BOUNDARY: removing the quoted_amount rule makes validated() drop it so a client price never reaches CreateJobOrder. messages(): `$this->jobOrderMessages('job_orders.*.')` plus `job_orders.max` => "You can order up to 5 items at a time."; attributes(): `$this->jobOrderAttributes('job_orders.*.')` plus contact_number => "mobile number". `after()` mirrors StoreJobOrderRequest but passes `customerFacing: true`. authorize true.

    Controller (App\Http\Controllers\Public\OnlineOrderController):
    - create(): Inertia::render('public/Order') with pricingEntries = active PricingEntry ordered by name, ONLY id,name,unit; specificationOptions (SpecificationOption::activeLabelsByCategory()), printSizeDimensions, acceptedFileFormats (ValidateJobOrderFile::acceptedFormats()), sentTo = session('orderSentTo'). No rushFeePercentage, no base_price.
    - store(): for each validated row with a `file`, `$file->store('job-orders','local')`; for type_a rows run ValidateJobOrderFile and record `file_check` = ['outcome' => outcome->value, 'reason' => reason]; build payload per locked shape (customer + job_orders with file_path/file_check, no UploadedFile, no price); OnlineOrder::create; Mail::to($email)->send(new ConfirmOnlineOrder($order)). On mail throw: report($e), delete stored files and the row, redirect back with error on `email` "We couldn't send the confirmation email. Check the address and try again." (withInput not needed for files; use back()->withErrors). Success: `to_route('public.orders.create')->with('orderSentTo', $email)`.
    - show(Request, int $onlineOrder): OnlineOrder::find; null -> `public/OrderConfirm` state expired; unconfirmed -> state pending with email, items (descriptions + count), confirmUrl = URL::temporarySignedRoute('public.orders.confirm', now()->addHours(48), ['onlineOrder' => $id]); confirmed -> state confirmed with jobOrders (number, description, trackingUrl = route('public.tracking.token', ['token' => $jobOrder->tracking_token])) loaded via the queue entry.
    - confirm(Request, int $onlineOrder, OpenVisit $openVisit): ONE DB::transaction with `lockForUpdate()` re-read; missing -> expired state; already confirmed -> no-op (track `$confirmedNow = false`); else `Customer::firstOrCreate(['contact_number' => ...], [name, organization, email, address])` (never overwrite an existing customer), `$entry = $openVisit($customer->id, $payload['job_orders'], QueueEntry::ONLINE_PREFIX)`, set confirmed_at and queue_entry_id. After commit and only if confirmedNow: Mail::to($onlineOrder->email)->send(new JobOrdersReceived($entry->load('jobOrders'))) in try/catch + report(). Render the confirmed state (reuse a private method shared with show()).
    - `{onlineOrder}` is a plain int param, NOT route-model-bound, so a pruned row yields the friendly expired state.

    Mail ConfirmOnlineOrder: constructor `public OnlineOrder $onlineOrder`; markdown with one button to URL::temporarySignedRoute('public.orders.confirm.show', $onlineOrder->created_at->addHours(48), ['onlineOrder' => $onlineOrder->id]); copy: list of ordered descriptions, "Your order is not placed until you confirm it", "This link is valid for 48 hours", "If you did not place this order, ignore this email." Post-confirm email reuses JobOrdersReceived (do not create another).

    Routes in routes/web.php, outside every auth/role group, with a comment in the file's existing style explaining why public: `GET /order` create `public.orders.create` throttle:60,1; `POST /order` store `public.orders.store` throttle:5,10; `Route::middleware(['signed','throttle:60,1'])->prefix('order/confirm')`: GET `{onlineOrder}` show `public.orders.confirm.show`, POST `{onlineOrder}` confirm `public.orders.confirm`. Constrain `{onlineOrder}` with `->whereNumber('onlineOrder')`.

    bootstrap/app.php: in the InvalidSignatureException branch, when `$request->routeIs('public.orders.*')` render `public/OrderConfirm` with state 'expired', status 403; leave design-review behaviour as is.

    Finally run `php artisan wayfinder:generate --with-form` (flag REQUIRED; generated files are gitignored, never hand-edit or commit).

  </action>
  <verify>
    <automated>php artisan test --compact tests/Feature/Public/OnlineOrderTest.php && vendor/bin/pint --dirty --format agent && php artisan test --compact tests/Feature/Artist tests/Feature/FrontlineStaff tests/Feature/JobOrder</automated>
  </verify>
  <done>All 12 tests pass; existing Artist/FrontlineStaff/JobOrder tests still green (staff-sentence test unchanged); `composer types:check` shows the 19-error baseline with none added or in a touched file; routes visible in `php artisan route:list --path=order`.</done>
</task>

<task type="auto">
  <name>Task 2: Order and OrderConfirm pages plus customer-audience copy</name>
  <files>resources/js/pages/public/Order.vue, resources/js/pages/public/OrderConfirm.vue, resources/js/components/JobOrderRowFields.vue, resources/js/components/JobOrderPriceFields.vue</files>
  <action>
    Read resources/js/pages/artist/NewJobOrder.vue, public/DesignReview.vue and Welcome.vue first; reuse PageHeader/EmptyState etc. only where they fit a no-layout public shell. `<script setup lang="ts">`, single root element, semantic tokens only, Tailwind utilities only (no `<style>`, no inline style=), no random ids/Date.now() in rendered attributes (SSR). Import order per project convention.

    Order.vue: shell consistent with Welcome.vue (`bg-muted text-foreground`, logo header with logo linking to `home` route, "Track an order" link). `<h1>` "Order online" + description "Tell us what you need printed. We email you a link to confirm, then your order goes to the shop." Props: pricingEntries, specificationOptions, printSizeDimensions, acceptedFileFormats, sentTo. When `sentTo` is set, replace the form with a "Check your email" state: show the address, say the order is not placed until the link is opened, offer "Place another order" (a plain Link/visit to `create` that reloads the empty form). Otherwise: "Your details" card (name, organization optional, mobile number, email, address; each with `<Label for>`, `InputError`, autocomplete, type/inputmode e.g. tel/email). Wrap the rows in an element with class `@container`. Render `JobOrderRowFields` with `audience="customer"` and rushFeePercentage 0. Sticky footer: "Add another item" (hidden at 5 rows) and primary "Send my order" (`:disabled="form.processing"`). Honeypot input name `website`: visually hidden (sr-only/absolute utilities), `aria-hidden="true"`, `tabindex="-1"`, `autocomplete="off"`. Submit via `useForm(...).post(OnlineOrderController.store().url, { forceFormData: true, preserveScroll: true, onError: scroll/focus first error })` (import from `@/actions/...` Wayfinder output). Check how other public pages show feedback; no vue-sonner unless a Toaster is mounted, otherwise a visible error summary with `role="alert"` above the footer. Never render prices or tracking URLs.

    OrderConfirm.vue: props `state: 'pending'|'confirmed'|'expired'`, optional email, items, confirmUrl, jobOrders. Same shell as Order.vue. Pending: summary (count, descriptions, email) + "Confirm my order" via `<Form :action="confirmUrl" method="post">` with processing disabled (DesignReview precedent). Confirmed: each JO number (`tabular-nums`) + description + "Track this order" as a plain `<a :href>` (not Inertia Link), plus a line that the same link is where they approve a design and pay once the shop confirms the price. Expired: says the link expired or was already cleaned up, with a link to `create` from `@/routes/public/orders`.

    Customer copy in JobOrderRowFields.vue under `audience="customer"` ONLY (staff output must be byte-identical in behaviour): card title "Item {n}" instead of "Job Order {n}", "What do you need?" instead of "Job Order Type", "Print details" instead of "Print Specifications", remove-button sr-only "Remove item". JobOrderPriceFields.vue: product placeholder reads "Search products…" when `showPrices` is false, else unchanged.

  </action>
  <verify>
    <automated>php artisan wayfinder:generate --with-form && npx vp check --fix resources/js/pages/public/Order.vue resources/js/pages/public/OrderConfirm.vue resources/js/components/JobOrderRowFields.vue resources/js/components/JobOrderPriceFields.vue && npm run types:check</automated>
  </verify>
  <done>types:check clean; staff pages (artist/NewJobOrder, frontline intake) still pass their feature tests; no `base_price` or tracking token reference in Order.vue; customer-only strings gated on audience. No browser-driven claim made (orchestrator verifies).</done>
</task>

<task type="auto">
  <name>Task 3: Welcome call to action and its test</name>
  <files>resources/js/pages/Welcome.vue, tests/Feature/Public/WelcomePageTest.php</files>
  <action>
    In Welcome.vue add an "Order online" call to action using the Wayfinder helper `create` from `@/routes/public/orders` (alias per file convention): a header nav link, a primary button in the white hero panel under the paragraph, a footer link, and a new first `ORDER_RULES` entry titled "Order online": "Send your order from this site. We email you a link to confirm it, and your order goes straight to the shop." Soften the "Queue" rule so it reads as the walk-in alternative. Keep the docblock note that staff links are deliberately absent; do not add any of `Staff sign in`, `Live queue`, `/queue`, `/login` to the HTML, and show no prices. In WelcomePageTest add one assertion that the landing page HTML links to the order page (`route('public.orders.create', absolute: false)`/`/order`); the existing no-staff-links assertions must stay green.
  </action>
  <verify>
    <automated>npx vp check --fix resources/js/pages/Welcome.vue && vendor/bin/pint --dirty --format agent && npm run types:check && php artisan test --compact tests/Feature/Public tests/Feature/Artist tests/Feature/FrontlineStaff tests/Feature/JobOrder</automated>
  </verify>
  <done>Welcome shows three Order online links plus the rule entry; WelcomePageTest green including the new link assertion; full command above passes; user is asked to run the complete suite.</done>
</task>

</tasks>

<threat_model>

## Trust Boundaries

| Boundary                         | Description                                       |
| -------------------------------- | ------------------------------------------------- |
| anonymous visitor -> POST /order | Untrusted fields and uploaded files cross here    |
| email link -> confirm            | Signed URL is the only proof of mailbox ownership |
| public page -> tracking URL      | tracking_token is a bearer credential             |

## STRIDE Threat Register

| Threat ID       | Category               | Component                                     | Disposition | Mitigation Plan                                                                                  |
| --------------- | ---------------------- | --------------------------------------------- | ----------- | ------------------------------------------------------------------------------------------------ |
| T-vxm-01        | Tampering              | quoted_amount in payload                      | mitigate    | rule removed via Arr::except so validated() drops it; test 6                                     |
| T-vxm-02        | Spoofing               | confirm endpoint                              | mitigate    | `signed` middleware, 48h expiry, idempotent lockForUpdate; test 5 and 4                          |
| T-vxm-03        | Denial of service      | POST /order spam, mail bombing                | mitigate    | throttle:5,10, honeypot, max 5 items, 3-day prune                                                |
| T-vxm-04        | Information disclosure | prices / tracking tokens on public page       | mitigate    | pricingEntries limited to id,name,unit; tokens only on signed confirmed state and email; test 1  |
| T-vxm-05        | Tampering              | existing customer overwritten by public input | mitigate    | firstOrCreate never updates; test 9                                                              |
| T-vxm-06        | Tampering              | malicious upload                              | mitigate    | existing JobOrderValidationRules file rules + ValidateJobOrderFile; stored on private local disk |
| T-vxm-07        | Repudiation            | unconfirmed orphan files                      | mitigate    | Prunable pruning() deletes files of unconfirmed rows                                             |
| </threat_model> |

<verification>
- `php artisan test --compact tests/Feature/Public tests/Feature/Artist tests/Feature/FrontlineStaff tests/Feature/JobOrder`
- `npm run types:check` clean; `composer types:check` at 19-error baseline, none in touched files
- `vendor/bin/pint --dirty --format agent`; `npx vp check --fix <touched files only>` (never bare `npm run check:fix`)
</verification>

<success_criteria>
Public order flow works end to end under test with nothing created before confirmation, prices and tokens never leaked on public/Order, expired links friendly, Welcome links to /order, no dev DB migration or mail sent by the executor.
</success_criteria>

<output>
Create `.planning/quick/261003-vxm-website-order-form-with-email-confirmati/261003-vxm-SUMMARY.md` when done
</output>
