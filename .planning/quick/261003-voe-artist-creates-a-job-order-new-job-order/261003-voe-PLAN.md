---
phase: quick-261003-voe
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - routes/portals.php
  - app/Http/Controllers/Artist/JobOrderIntakeController.php
  - app/Http/Requests/Artist/StoreJobOrderRequest.php
  - app/Mail/JobOrdersReceived.php
  - resources/views/mail/job-orders-received.blade.php
  - tests/Feature/Artist/JobOrderIntakeTest.php
  - resources/js/pages/artist/NewJobOrder.vue
  - resources/js/config/nav/artist.ts
autonomous: true
requirements: [QUICK-261003-voe]
must_haves:
  truths:
    - "An artist opens New Job Order, picks or registers a customer, adds job order rows and saves."
    - "An on-shift artist's Type B order lands in their own queue (assigned, accepted_at set), in the O lane."
    - "An off-shift artist's order is still created and stays in Available Jobs, and the toast says so."
    - "A Type A with a passing file goes to production, unassigned."
    - "The customer receives JobOrdersReceived with one tracking link per job order; a mail failure never fails the save."
    - "The customer mode is switchable both ways without a reload and the server never receives both customer_id and customer."
    - "The tracking token is never put in an Inertia prop or a toast."
  artifacts:
    - path: "app/Http/Controllers/Artist/JobOrderIntakeController.php"
      provides: "create + store"
    - path: "app/Http/Requests/Artist/StoreJobOrderRequest.php"
      provides: "validation incl. inline new customer"
    - path: "app/Mail/JobOrdersReceived.php"
      provides: "customer receipt mail with tracking links"
    - path: "resources/js/pages/artist/NewJobOrder.vue"
      provides: "the page"
    - path: "tests/Feature/Artist/JobOrderIntakeTest.php"
      provides: "feature coverage"
  key_links:
    - from: "JobOrderIntakeController::store"
      to: "OpenVisit then ClaimJobOrderForArtist"
      via: "one DB::transaction"
      pattern: "OpenVisit|ClaimJobOrderForArtist"
    - from: "NewJobOrder.vue"
      to: "JobOrderRowFields"
      via: "audience=staff rows, same hosting as NewVisit.vue"
      pattern: "JobOrderRowFields"
---

<objective>
Part 2 of 4 (user-approved plan at /home/user/.claude/plans/let-s-create-a-plan-wondrous-hennessy.md, "Part 2" and "Defaults I chose" only). Add a "New Job Order" page to the Artist portal for clients who send requests by email. The order goes to the creating artist's own queue and the customer is emailed tracking links.

Purpose: artists can book email requests without a Frontline Staff handoff.
Output: routes, controller, FormRequest, mailable + markdown view, Vue page, nav entry, Pest feature tests.

Scope guard: no public website order form, no online_orders table, no payment work (Parts 3-4).
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@CLAUDE.md
@.planning/STATE.md
@app/Actions/JobOrder/OpenVisit.php
@app/Actions/JobOrder/ClaimJobOrderForArtist.php
@app/Actions/JobOrder/RecordDesignRevision.php
@app/Http/Controllers/FrontlineStaff/CustomerController.php
@app/Http/Requests/FrontlineStaff/StoreQueueEntryRequest.php
@app/Concerns/JobOrderValidationRules.php
@app/Concerns/CustomerValidationRules.php
@app/Mail/DesignReviewRequested.php
@resources/views/mail/design-review-requested.blade.php
@resources/js/components/JobOrderRowFields.vue
@resources/js/components/SearchableSelect.vue
@resources/js/pages/frontline-staff/NewVisit.vue
@resources/js/pages/artist/Dashboard.vue
@resources/js/config/nav/artist.ts
@tests/Feature/Artist/AcceptJobOrderTest.php

Rules to honour: read .claude/skills laravel-best-practices, testing-best-practices, inertia-vue-development, wayfinder-development, tailwindcss-development SKILL.md first. Read .ai/rules/index.md if present and follow matching rules. Use `php artisan make:*` with `--no-interaction`. Main tree only; no worktrees; no composer install/require/dump-autoload; no dependency changes. Laravel Boost MCP is down: use artisan and file reads. `.env` has a real SMTP mailer: never send real mail; do not run tinker or any script that could send. Do not claim the UI was driven in a browser (the orchestrator does that).
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Backend (routes, controller, request, mail) with feature tests</name>
  <files>routes/portals.php, app/Http/Controllers/Artist/JobOrderIntakeController.php, app/Http/Requests/Artist/StoreJobOrderRequest.php, app/Mail/JobOrdersReceived.php, resources/views/mail/job-orders-received.blade.php, tests/Feature/Artist/JobOrderIntakeTest.php</files>
  <behavior>
    - Test 1: artist GET artist.job-orders.create renders `artist/NewJobOrder` with `customers`, `specificationOptions`, `printSizeDimensions`, `rushFeePercentage`, `acceptedFileFormats`, `pricingEntries`.
    - Test 2: existing customer + Type B, available artist: job order status `assigned`, `assigned_artist_id` = artist, `accepted_at` set; visit queue_prefix `O`; redirect to artist.dashboard; toast says "added to your queue".
    - Test 3: new customer inline: Customer created with the given fields and used by the visit.
    - Test 4: Type A with `UploadedFile::fake()->create('design.pdf', 500)` (Storage::fake('local')): `for_production`, `assigned_artist_id` null.
    - Test 5: artist `off_shift`: order created, status `intake`, no artist, toast mentions Available Jobs.
    - Test 6: non-artist role (e.g. cashier, frontline staff) gets 403 on GET and POST.
    - Test 7: Mail::fake(); `JobOrdersReceived` sent to the customer's email; assert it renders the tracking URL for each job order (`->assertSeeInHtml` / `render()`), and no assertion on artist/email wording.
    - Test 8: neither customer_id nor customer: session errors, nothing created; duplicate contact_number for a new customer: error on `customer.contact_number`, no Customer/QueueEntry/JobOrder created.
  </behavior>
  <action>
Read the five skills and the files in context first. Mirror existing test file conventions (Pest, factories, `User::factory()->artist()->create(['artist_status' => ArtistStatus::Available->value])`, `Customer::factory()`). Use unique global helper names (prefix e.g. `intakeTest...`), NOT `createPreCheckedJobOrder`. Name the file JobOrderIntakeTest (not CreateJobOrderTest). Create it with `php artisan make:test --pest Artist/JobOrderIntakeTest`... pass name without the suite dir per project rules, then move/verify it lands in tests/Feature/Artist. Write tests first, run to see them fail, then implement.

Routes (routes/portals.php, `role:artist` group): add `Route::get('job-orders/create', [JobOrderIntakeController::class, 'create'])->name('job-orders.create')` and `Route::post('job-orders', [JobOrderIntakeController::class, 'store'])->name('job-orders.store')` BEFORE the existing `job-orders/{jobOrder}` route, otherwise `create` is captured as an id. Add the controller import.

Controller `App\Http\Controllers\Artist\JobOrderIntakeController` (make:controller):
- `create(): Response` renders `artist/NewJobOrder` with `customers` = `Customer::orderBy('name')->get(['id','name','organization','contact_number'])` preceded by the comment `// ponytail: the whole list ships to the page; move to server-side search when it outgrows that.`, plus the same catalog props FrontlineStaff CustomerController::index() passes: `specificationOptions` (SpecificationOption::activeLabelsByCategory()), `printSizeDimensions` (printSizeDimensionsByLabel()), `rushFeePercentage` (SystemConfiguration::getFloat('rush_fee_percentage', 0.0)), `acceptedFileFormats` (ValidateJobOrderFile::acceptedFormats()), `pricingEntries` (active, orderBy name, get id,name,base_price,unit).
- `store(StoreJobOrderRequest $request, OpenVisit $openVisit, ClaimJobOrderForArtist $claim): RedirectResponse`. Inside ONE `DB::transaction`: resolve customer (`Customer::find(validated customer_id)` or `Customer::create($request->validated('customer'))`); `$entry = $openVisit($customer->id, $request->validated('job_orders'), QueueEntry::ONLINE_PREFIX)` (validated rows already carry each row's UploadedFile under `file`; pass straight through); then for each `$entry->jobOrders` still at `JobOrderStatus::Intake` call `$claim($jobOrder, $request->user())` and record whether it returned true (false when the artist is not Available writes nothing, which is the approved behaviour: the order stays in the pool). ClaimJobOrderForArtist already runs SyncQueueEntryStatus on success. A Type A passing its check goes to production and is not assigned; leave that CreateJobOrder behaviour alone.
- After the transaction commits: `try { Mail::to($customer->email)->send(new JobOrdersReceived($entry)); } catch (\Throwable $e) { report($e); }` exactly like RecordDesignRevision. A mail failure must never fail the save.
- Flash `Inertia::flash('toast', ['type' => 'success', 'message' => __(...)])` naming the JO number(s) (`$jobOrder->number`). Two variants: claimed ("... added to your queue") and not claimed ("... is in Available Jobs. Start your shift to accept it."); handle singular/plural and a mixed visit sensibly (name the claimed numbers and the pooled numbers separately). Never include `tracking_token` in the toast. Redirect `to_route('artist.dashboard')`.
- Short PHPDoc on each method per project convention; thin controller.

Request `App\Http\Requests\Artist\StoreJobOrderRequest` (`php artisan make:request Artist/StoreJobOrderRequest`): `use JobOrderValidationRules, CustomerValidationRules`. Rules: `customer_id` => `['nullable','required_without:customer','integer','exists:customers,id']`; `customer` => `['nullable','required_without:customer_id','array']`; `customer.name|organization|contact_number|email|address` built from the trait's individual methods (`customerNameRules()`, `organizationRules()`, `contactNumberRules()` which keeps the unique rule, `customerEmailRules()`, `addressRules()`) and added ONLY when `customer_id` is absent (`! $this->filled('customer_id')`); merge `...$this->jobOrdersRules()`. `after()` identical to StoreQueueEntryRequest::after() (calls `rejectUnusableTypeAFiles` with `job_orders.{index}.` prefixes). Staff price override `quoted_amount` stays allowed: the artist is trusted staff doing intake. `authorize()` follows sibling requests (role middleware already guards the route; return true if siblings do).

Mail `App\Mail\JobOrdersReceived` (`php artisan make:mail JobOrdersReceived --markdown=mail.job-orders-received --no-interaction`): constructor `public QueueEntry $queueEntry`; follow DesignReviewRequested structure. Subject like `__('We received your order — :number', ['number' => first job order number])`. Content `with:` a list of job orders (number, description, `url` = `route('public.tracking.token', ['token' => $jobOrder->tracking_token])`); load `jobOrders` on the entry. Markdown view `resources/views/mail/job-orders-received.blade.php` in the same `<x-mail::message>` style: plain short customer copy saying what we received, one tracking link per job order (button or list item), and that the same link is where they approve a design and pay once the shop confirms the price. It will also be reused by a later task (website orders), so it must NOT mention the artist or email as the channel. `resources/views/mail/*` is excluded from the JS formatter.

`tracking_token` is a bearer credential: never add it to any Inertia prop or toast. Do not add it to JobOrder fillable.

Run `php artisan wayfinder:generate` after routes/controller exist so the frontend imports resolve in Task 2 (generated files are gitignored; never hand-edit or commit them).
  </action>
  <verify>
    <automated>vendor/bin/pint --dirty --format agent && php artisan test --compact tests/Feature/Artist/JobOrderIntakeTest.php</automated>
    Then run: `php artisan test --compact tests/Feature/Artist tests/Feature/FrontlineStaff tests/Feature/RoleBoundaryTest.php` (all pass) and `composer types:check` (baseline is 19 pre-existing Larastan errors in unrelated files; the bar is no new errors). Confirm with `php artisan route:list --path=artist/job-orders` that `create` is listed before `{jobOrder}`.
  </verify>
  <done>All 8 test scenarios pass; routes registered ahead of the `{jobOrder}` route; mail failure path swallowed with report(); no new Larastan errors; Pint clean.</done>
</task>

<task type="auto">
  <name>Task 2: NewJobOrder page and nav entry</name>
  <files>resources/js/pages/artist/NewJobOrder.vue, resources/js/config/nav/artist.ts</files>
  <action>
Read the inertia-vue-development, wayfinder-development and tailwindcss-development skills, NewVisit.vue (host pattern), JobOrderRowFields.vue and SearchableSelect.vue first. Reuse shared components; do not hand-roll shells. Tailwind utilities only, semantic tokens only (`bg-card`, `text-muted-foreground`, `border-border`), no `style=`, no `<style>`, no app.css additions. PageContainer is the `@container`: use `@2xl:` container-query classes.

Nav: in `resources/js/config/nav/artist.ts` add `{ title: 'New Job Order', href: create(), icon: FilePlus }` between Dashboard and Performance Report; import `FilePlus` from `@lucide/vue` and `create` from `@/routes/artist/job-orders`.

Page `resources/js/pages/artist/NewJobOrder.vue`: `<script setup lang="ts">`, single root element (`PageContainer`), `<Head title="New Job Order" />`, `PageHeader` title "New Job Order" with description "For a client who sent their request by email. The job order goes straight to your queue and the client is emailed a tracking link." Layout via `defineOptions({ layout: { navItems: artistNavItems, breadcrumbs: [Dashboard (dashboard() from @/routes/artist), New Job Order (create())] } })` like artist/Dashboard.vue. Props: `customers` ({id,name,organization,contact_number}[]), `specificationOptions`, `printSizeDimensions`, `rushFeePercentage`, `acceptedFileFormats`, `pricingEntries` (same types NewVisit uses).

Customer card (`Card`, `SectionHeading` or CardTitle): two modes, `existing` (default) and `new`. Existing mode: `SearchableSelect` (data-test `customer-select`) over `customers` mapped to `{ value: String(id), label: name, hint: [organization, contact_number].filter(Boolean).join(' · ') }`, plus a button data-test `new-customer-toggle` ("New customer"). New mode: Label+Input fields for name, organization (labelled optional), contact number, email, address, each with `InputError` bound to `form.errors['customer.name']` etc., plus a button data-test `existing-customer-toggle` ("Pick an existing customer"). Switching either direction clears the other mode's value so the server never receives both `customer_id` and `customer`. Because the form must omit the unused key entirely, build the payload with `form.transform((data) => ...)` that returns `{ job_orders }` plus either `customer_id` or `customer` according to mode (do not send empty strings or both). Show `InputError` for `form.errors.customer_id` and `form.errors.customer` in existing mode. Empty customers list: the select's emptyText should point at "New customer".

Job orders: `useForm({ customer_id: '', customer: { name:'', organization:'', contact_number:'', email:'', address:'' }, job_orders: [emptyJobOrderRow()] as JobOrderRow[] })` (or equivalent), `addRow()`/`removeRow(index)`, `jobOrderRowErrors(index)` slicing prefix `job_orders.${index}.` exactly as NewVisit does. Render `JobOrderRowFields v-for` with `:key="row._key"`, `audience="staff"`, `:removable="form.job_orders.length > 1"` and the catalog props. Follow NewVisit exactly on whether `_key` / `quoted_amount_overridden` are stripped before posting (check how its form/transform handles them and copy it; do not invent).

Sticky footer (copy NewVisit's sticky footer classes): "Add Another Job Order" button (`variant="secondary"`, data-test `add-job-order-row-button`) and primary "Create Job Order" button (data-test `create-job-order-button`, `:disabled="form.processing"`, label switches to "Creating…"). Optional estimated-total line using `rowLineAmount`/`money` with `tabular-nums` only if NewVisit exports/uses the same helpers (copy its import); otherwise a job-order count with `tabular-nums`.

Submit: `form.post(JobOrderIntakeController.store().url, { forceFormData: true, preserveScroll: true, onError: () => toast.error('Nothing was saved. Fix the highlighted fields and try again.') })` with `toast` from `vue-sonner` and `JobOrderIntakeController` from `@/actions/App/Http/Controllers/Artist/JobOrderIntakeController`. Success toast comes from the server flash and the redirect to the dashboard.

Keyboard reachability: all controls are real buttons/inputs. Check 375px: customer fields stack, two-column only at `@2xl:`. Run `php artisan wayfinder:generate` first if `@/routes/artist/job-orders` has no `create` export.
  </action>
  <verify>
    <automated>npx vp check --fix resources/js/pages/artist/NewJobOrder.vue resources/js/config/nav/artist.ts && npm run types:check</automated>
    Never run bare `npm run check:fix`. Re-run `php artisan test --compact tests/Feature/Artist tests/Feature/FrontlineStaff tests/Feature/RoleBoundaryTest.php`. State plainly in the summary that the UI was type-checked but NOT driven in a browser; the orchestrator does that.
  </verify>
  <done>Page and nav entry exist, lint and `npm run types:check` clean, Artist/FrontlineStaff/RoleBoundary tests pass, grep confirms no `tracking_token` in the page or controller props, and no `style=`/`<style>` in the page.</done>
</task>

</tasks>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| browser to POST artist/job-orders | untrusted customer fields, rows, uploaded files cross here |
| app to SMTP | customer email and bearer tracking links leave the system |

## STRIDE Threat Register

| Threat ID | Category | Component | Disposition | Mitigation Plan |
|-----------|----------|-----------|-------------|-----------------|
| T-voe-01 | E | both routes | mitigate | `role:artist` middleware group; test 6 asserts 403 for other roles |
| T-voe-02 | T | StoreJobOrderRequest | mitigate | FormRequest rules incl. exists:customers, unique contact_number, jobOrdersRules, rejectUnusableTypeAFiles; whole save in one DB::transaction |
| T-voe-03 | I | tracking_token | mitigate | never in an Inertia prop or toast; only in the mail to the customer's own address |
| T-voe-04 | D | mail transport | mitigate | send after commit inside try/catch with report(); save never fails on mail |
| T-voe-05 | I | customers prop | accept | staff-only portal; whole list shipped (ponytail comment notes server-side search later) |
| T-voe-SC | T | package installs | accept | no new dependencies in this plan |
</threat_model>

<verification>
- `vendor/bin/pint --dirty --format agent` clean.
- `npx vp check --fix <the touched files>` (never bare `npm run check:fix`).
- `npm run types:check` clean; `composer types:check` shows no new errors beyond the 19 pre-existing.
- `php artisan test --compact tests/Feature/Artist tests/Feature/FrontlineStaff tests/Feature/RoleBoundaryTest.php` passes.
- `git status` shows no generated `resources/js/routes/**` or `resources/js/actions/**` files staged.
</verification>

<success_criteria>
An artist can create job orders for an existing or new customer from the Artist portal; on-shift artists get Type B orders in their own queue, off-shift orders wait in Available Jobs with a clear toast; the customer is emailed tracking links without ever blocking the save; all 8 test scenarios pass; scope stays inside Part 2.
</success_criteria>

<output>
Create `.planning/quick/261003-voe-artist-creates-a-job-order-new-job-order/261003-voe-SUMMARY.md` when done
</output>
