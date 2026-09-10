---
phase: quick-260910-mbb
plan: 01
type: execute
wave: 1
depends_on: []
autonomous: true
requirements: [RUSH-01, RUSH-02, QR-01, QR-02, HIST-01, PAID-01]
files_modified:
  - database/migrations/2026_09_10_120000_add_is_rush_to_job_orders_table.php
  - database/migrations/2026_09_10_120100_add_tracking_token_to_job_orders_table.php
  - app/Models/JobOrder.php
  - database/factories/JobOrderFactory.php
  - app/Concerns/JobOrderValidationRules.php
  - app/Http/Controllers/FrontlineStaff/QueueEntryController.php
  - app/Http/Controllers/FrontlineStaff/CustomerController.php
  - app/Http/Controllers/Artist/JobOrderQueueController.php
  - app/Http/Controllers/Artist/JobOrderWorkspaceController.php
  - app/Http/Controllers/Cashier/DashboardController.php
  - app/Http/Controllers/ProductionStaff/ProductionBoardController.php
  - app/Http/Controllers/Public/TrackingController.php
  - routes/web.php
  - resources/js/pages/frontline-staff/NewVisit.vue
  - resources/js/pages/frontline-staff/QueueList.vue
  - resources/js/pages/artist/Dashboard.vue
  - resources/js/pages/artist/JobOrderWorkspace.vue
  - resources/js/pages/cashier/Dashboard.vue
  - resources/js/pages/cashier/JobOrderPayment.vue
  - resources/js/pages/public/TrackingToken.vue
  - tests/Feature/FrontlineStaff/RushJobOrderTest.php
  - tests/Feature/FrontlineStaff/CustomerJobOrderHistoryTest.php
  - tests/Feature/Cashier/CashierDashboardPaidFilterTest.php
  - tests/Feature/Public/TrackingTokenTest.php
  - tests/Feature/ProductionStaff/ProductionBoardTest.php

must_haves:
  truths:
    - "Frontline staff can mark a job order Rush at intake, through both the New Visit form and the Add Job Order dialog, and the flag survives the save."
    - "An Artist sees a Rush badge on their queue rows, on the Available Jobs pool rows, and in the Job Order Workspace."
    - "A Cashier sees a Rush badge on the dashboard row, and opening a rush job order's payment page finds the existing Apply Rush Fee toggle already checked but still overridable."
    - "The Production Board still flags a job order due today as Rush, and now also flags one that was marked Rush at intake regardless of its due date."
    - "A customer scanning the printed QR slip lands on a public page showing only their job order number and public production stage — no name, contact number, address, price, payment or internal status value."
    - "When that job order is awaiting a design verdict, the public page offers an Approve / Request Changes link that goes through a freshly signed public.design-review.show URL."
    - "After a visit is confirmed, frontline staff can print a slip per job order containing its QR code, job order number, customer name, and one instruction line — and only that slip prints."
    - "Selecting a returning customer on New Visit shows their recent job orders first, with a New Job Order button that reveals the intake form; a first-time customer drops straight into the form as today."
    - "A fully paid job order disappears from the Cashier dashboard while partially paid, on-credit, and not-yet-priced job orders remain."
  artifacts:
    - path: "database/migrations/2026_09_10_120000_add_is_rush_to_job_orders_table.php"
      provides: "job_orders.is_rush boolean NOT NULL default false"
      contains: "is_rush"
    - path: "database/migrations/2026_09_10_120100_add_tracking_token_to_job_orders_table.php"
      provides: "job_orders.tracking_token, unique index, backfill of existing rows"
      contains: "tracking_token"
    - path: "resources/js/pages/public/TrackingToken.vue"
      provides: "Customer-facing QR landing page"
      min_lines: 60
    - path: "tests/Feature/Public/TrackingTokenTest.php"
      provides: "Token route behaviour and PII-boundary assertions"
      min_lines: 60
    - path: "tests/Feature/FrontlineStaff/RushJobOrderTest.php"
      provides: "Rush persistence through both intake paths"
    - path: "tests/Feature/Cashier/CashierDashboardPaidFilterTest.php"
      provides: "Fully-paid exclusion, partial/unpriced inclusion"
    - path: "tests/Feature/FrontlineStaff/CustomerJobOrderHistoryTest.php"
      provides: "Customer history prop shape and ordering"
  key_links:
    - from: "resources/js/pages/frontline-staff/NewVisit.vue"
      to: "job_orders.*.is_rush"
      via: "intakeForm job order row field posted as FormData"
      pattern: "is_rush"
    - from: "routes/web.php"
      to: "App\\Http\\Controllers\\Public\\TrackingController::showByToken"
      via: "GET track/{token} outside every auth/role group"
      pattern: "track/\\{token\\}"
    - from: "app/Http/Controllers/Public/TrackingController.php"
      to: "public.design-review.show"
      via: "URL::temporarySignedRoute"
      pattern: "temporarySignedRoute"
    - from: "resources/js/pages/frontline-staff/NewVisit.vue"
      to: "public tracking URL"
      via: "TrackingQrCode fed trackingBaseUrl + tracking_token"
      pattern: "TrackingQrCode"
    - from: "app/Http/Controllers/Cashier/DashboardController.php"
      to: "amount_paid withSum aggregate"
      via: "in-PHP reject of fully-paid rows"
      pattern: "amount_paid"
---

<objective>
Four independent frontline/cashier improvements, delivered as four separately
committable tasks in one plan:

1. A real, persisted `is_rush` flag on `job_orders`, set at intake by frontline
   staff and surfaced to Artist, Cashier and Production Staff — resolving the
   collision with the non-persisted `is_rush` that `ProductionBoardController`
   currently synthesises.
2. An unguessable `tracking_token` per job order plus a public, unauthenticated
   `GET track/{token}` route that holds the exact same PII boundary the existing
   `GET track` route holds, and hands the customer a freshly signed link into the
   already-existing design-review flow when a verdict is pending.
3. A printable QR handoff slip on the New Visit confirmation block, and a
   returning-customer job-order history gate before the intake form.
4. A Cashier dashboard that stops listing fully-paid job orders.

Purpose: rush work is currently invisible to everyone downstream of the counter;
customers have to type a job order number to track; frontline staff cannot see
what a returning customer ordered last time; and the Cashier's worklist never
shrinks.

Output: two migrations, one new public Inertia page, one new public controller
action, four new Pest feature test files, and edits across nine PHP files and six
Vue files.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@CLAUDE.md

@app/Models/JobOrder.php
@app/Concerns/JobOrderValidationRules.php
@app/Http/Controllers/FrontlineStaff/QueueEntryController.php
@app/Http/Controllers/FrontlineStaff/CustomerController.php
@app/Http/Controllers/Public/TrackingController.php
@app/Http/Controllers/Public/DesignReviewController.php
@app/Http/Controllers/Cashier/DashboardController.php
@app/Http/Controllers/ProductionStaff/ProductionBoardController.php
@resources/js/components/TrackingQrCode.vue
@resources/js/pages/cashier/Receipt.vue
</context>

<interfaces>
Contracts an executor would otherwise have to go looking for. Treat these as
given — do not re-derive them from the codebase.

**Inertia FormData boolean serialisation** (`@inertiajs/core`, verified in
`node_modules/@inertiajs/core/dist/index.js`): a JS `boolean` in a `useForm`
payload posted with `forceFormData: true` is appended as the string `"1"` or
`"0"`. Laravel's `boolean` validation rule accepts both. `(bool) "0"` is `false`
in PHP, so a plain cast is safe; prefer `filter_var($v, FILTER_VALIDATE_BOOLEAN)`
for explicitness.

**reka-ui `SwitchRoot` hidden input** (verified in
`node_modules/reka-ui/src/Switch/SwitchRoot.vue`): when a `name` prop is set,
`SwitchRoot` renders `<input type="checkbox" :name :value :checked>` through
`VisuallyHiddenInput`. The `value` prop **defaults to the string `'on'`**, which
fails Laravel's `boolean` rule. Any `<Switch>` bound into an uncontrolled Inertia
`<Form>` MUST pass `value="1"` explicitly. An unchecked checkbox is omitted from
the submission entirely, so the server-side rule must be `nullable` and the read
must default to false.

**Shared component `class` forwarding**: `PageContainer`, `PageHeader`,
`SectionHeading`, `DataTableCard`, `EmptyState`, `Card`, `CardHeader`,
`CardContent` all accept a `class` prop merged via `cn()`. Passing
`class="print:hidden"` to any of them works.

**`DesignReviewController` signed-link contract**: `show`, `approve` and
`requestChanges` all sit behind `['signed', 'throttle:60,1']`. The controller
itself computes `$expiresAt = $revisionLog->submitted_at->addDays(7)` for the
signed action URLs it emits. A link generated elsewhere must use the same expiry
basis, and `isCurrentRevision()` means only the job order's **latest**
`revisionLog` by `submitted_at` is ever actionable; `isActionable()` additionally
requires `status === PendingReview` and `outcome === null`.

**`TrackingController` PII boundary** (its own class docblock): the narrow
`first([...])` column list and the narrow response array are the entire
boundary. `publicStage()` maps status to a customer-facing label so the raw enum
value never reaches the client.

**`JobOrder` `#[Fillable]`** does not include `assigned_artist_id`,
`validation_failure_reason`, `status` overrides used by factory states, etc.
Factory states that need a non-fillable column use `afterCreating()` +
`forceFill()`. Follow that precedent.
</interfaces>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Persist a rush flag and capture it on both frontline intake paths</name>
  <files>
    database/migrations/2026_09_10_120000_add_is_rush_to_job_orders_table.php (new),
    app/Models/JobOrder.php,
    database/factories/JobOrderFactory.php,
    app/Concerns/JobOrderValidationRules.php,
    app/Http/Controllers/FrontlineStaff/QueueEntryController.php,
    resources/js/pages/frontline-staff/NewVisit.vue,
    resources/js/pages/frontline-staff/QueueList.vue,
    tests/Feature/FrontlineStaff/RushJobOrderTest.php (new)
  </files>
  <behavior>
    - Posting the New Visit intake form with `job_orders.0.is_rush` truthy creates a job order whose `is_rush` is true; omitting the field creates one whose `is_rush` is false.
    - Posting the Add Job Order dialog to `QueueEntryController::addJobOrder` with `is_rush=1` creates a rush job order on an existing visit; omitting it creates a non-rush one.
    - `is_rush` is never null — the column is NOT NULL with a false default.
    - `JobOrder::factory()->rush()` produces a job order with `is_rush` true.
    - A rush flag submitted alongside a Type A file upload still persists (the FormData path, not just the JSON path).
  </behavior>
  <action>
Create the migration with `php artisan make:migration add_is_rush_to_job_orders_table --no-interaction`, then rename the generated file so its timestamp sorts after `2026_09_10_112000_add_dimensions_to_specification_options_table.php` (target name `2026_09_10_120000_add_is_rush_to_job_orders_table.php`). In `up()` add `$table->boolean('is_rush')->default(false)->after('deadline')` — NOT nullable, so downstream code never has to handle a third state. In `down()` drop the column. Add a docblock explaining that this column is the staff-declared urgency flag captured at the counter, deliberately distinct from the Production Board's due-date heuristic (Task 2 reconciles the two).

In `app/Models/JobOrder.php`: add `is_rush` to the `#[Fillable]` attribute list (place it next to `deadline`), add `'is_rush' => 'boolean'` to `casts()`, and REPLACE the existing `@property bool|null $is_rush Not a persisted column ...` PHPDoc block with `@property bool $is_rush` plus a note that it is now a real column, and that `ProductionBoardController::index()` deliberately widens the in-memory value with its due-date heuristic for display only and never saves that widened value. This PHPDoc correction is the visible half of resolving the collision the task description flags — do not leave the stale "Not a persisted column" wording in place.

In `database/factories/JobOrderFactory.php`: add `'is_rush' => false` to `definition()` so the default is explicit, and add a `rush(): static` state that returns `$this->state(fn (array $attributes) => ['is_rush' => true])`. A plain `state()` is correct here (unlike `assigned()`/`validationFailed()`) because `is_rush` IS fillable.

In `app/Concerns/JobOrderValidationRules.php`: add `is_rush` to BOTH rule sets by adding `$prefix.'is_rush' => ['nullable', 'boolean']` inside `printSpecificationRules()` — that single addition covers `jobOrdersRules()` (as `job_orders.*.is_rush`) and `jobOrderRules()` (as `is_rush`) with no duplication, which is exactly what that helper exists for. Add a line to its docblock explaining `nullable` is load-bearing: an unchecked reka-ui `Switch` omits its hidden checkbox from the submission entirely, so `required` would reject every non-rush job order.

In `app/Http/Controllers/FrontlineStaff/QueueEntryController.php`:
- In `addJobOrder()`, add `'is_rush' => $request->boolean('is_rush')` to the `create([...])` array.
- In `store()`, add `'is_rush' => filter_var($row['is_rush'] ?? false, FILTER_VALIDATE_BOOLEAN)` to the per-row `create([...])` array. Use `filter_var`, not a bare cast, because the FormData path delivers the string `"1"`/`"0"` while a JSON test payload delivers a real boolean.

In `resources/js/pages/frontline-staff/NewVisit.vue`:
- Add `is_rush: boolean;` to the `JobOrderRow` interface and `is_rush: false` to `emptyJobOrderRow()`.
- Import `Switch` from `@/components/ui/switch` and `Zap` from `@lucide/vue`.
- Inside each job order Card's Print Specifications `<section>`, add a full-width row (`class="grid gap-2 md:col-span-2"`) after the Deadline field containing: a `<Switch :id="`job-order-rush-${index}`" v-model="row.is_rush" />` next to a `<Label :for="...">` reading "Rush Order" with a `Zap` icon, plus a `text-muted-foreground text-sm` help line reading "Prioritised in production. The Cashier decides whether the rush fee is charged." Wrap the switch + label in `class="flex items-center gap-3"`. Use semantic tokens only.
- Do NOT pass a `name` prop here: this form is submitted programmatically through `useForm`/`intakeForm`, not by native form serialisation, so `v-model` alone is correct.

In `resources/js/pages/frontline-staff/QueueList.vue`:
- Import `Switch` from `@/components/ui/switch`.
- Inside the Add Job Order `<Dialog>`'s Inertia `<Form>` (the one bound to `QueueEntryController.addJobOrder.form(...)`), add a Rush row after the file input, before `<DialogFooter>`, using `<Switch name="is_rush" value="1" />` with a matching `<Label>` and the same help copy. The explicit `value="1"` is mandatory — see the interfaces block; the reka-ui default of `'on'` fails Laravel's `boolean` rule. This form is uncontrolled, so the `name`/`value` pair IS the wiring; there is no `v-model` here.

Write `tests/Feature/FrontlineStaff/RushJobOrderTest.php` with `php artisan make:test --pest RushJobOrderTest` and move it into `tests/Feature/FrontlineStaff/`. Cover the five behaviours above. Post to `route('frontline-staff.queue-entries.store')` and `route('frontline-staff.queue-entries.add-job-order', $queueEntry)` — confirm the real route names with `php artisan route:list --name=frontline-staff` rather than guessing. Model the request payloads on the existing `tests/Feature/FrontlineStaff/QueueEntryIntakeTest.php` and `AddJobOrderToVisitTest.php`; for the FormData case reuse whatever `UploadedFile` helper those files already use. Assert with `$this->assertTrue($jobOrder->fresh()->is_rush)` style, not by inspecting raw DB strings.

Run `vendor/bin/pint --dirty --format agent`.
  </action>
  <verify>
    <automated>php artisan migrate --no-interaction && vendor/bin/pest tests/Feature/FrontlineStaff/RushJobOrderTest.php --compact && vendor/bin/pest tests/Feature/FrontlineStaff --compact && npm run types:check</automated>
    <human-check>
      Open New Visit in a browser (both light and dark theme, and at a 375px viewport).
      1. Pick a customer, toggle Rush on job order 1, add a second job order, leave its Rush off, submit. Confirm in the DB (or via the Cashier dashboard after Task 2) that exactly one row has is_rush true.
      2. Toggle Rush on, then OFF again, then submit — the "change your mind" path is the one that breaks. Confirm the row saves as not-rush.
      3. On the Queue List page, open the Add Job Order dialog, toggle Rush, submit, and confirm the new job order persists as rush.
      4. Keyboard-reach both switches with Tab and toggle them with Space.
    </human-check>
  </verify>
  <done>The `is_rush` column exists and is NOT NULL; both intake paths write it; both UI toggles are observed working in a browser including the toggle-off path; `RushJobOrderTest` passes; `npm run types:check` is clean.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Surface rush downstream, reconcile the Production Board heuristic, and hide fully-paid orders from the Cashier</name>
  <files>
    app/Http/Controllers/Artist/JobOrderQueueController.php,
    app/Http/Controllers/Artist/JobOrderWorkspaceController.php,
    app/Http/Controllers/Cashier/DashboardController.php,
    app/Http/Controllers/ProductionStaff/ProductionBoardController.php,
    resources/js/pages/artist/Dashboard.vue,
    resources/js/pages/artist/JobOrderWorkspace.vue,
    resources/js/pages/cashier/Dashboard.vue,
    resources/js/pages/cashier/JobOrderPayment.vue,
    tests/Feature/ProductionStaff/ProductionBoardTest.php,
    tests/Feature/Cashier/CashierDashboardPaidFilterTest.php (new)
  </files>
  <behavior>
    - Production Board: a job order due today or overdue is still flagged rush (all six existing assertions in `ProductionBoardTest` keep passing unchanged).
    - Production Board: a job order with persisted `is_rush` true and a `due_at` a week away is ALSO flagged rush.
    - Cashier dashboard: a job order whose completed-transaction sum equals or exceeds `total_amount` is absent from `jobOrders`.
    - Cashier dashboard: a partially paid job order is present, an on-credit job order with an outstanding balance is present, and a job order with `total_amount` null is present.
    - Cashier dashboard: the surviving rows are a JSON array (sequential keys), not a keyed object.
  </behavior>
  <action>
**Production Board collision — the decision.** The persisted column wins as a
source of truth, and the board's displayed `is_rush` is the OR of the persisted
flag and the existing due-date heuristic. Rationale: the heuristic answers "is
this urgent by the clock" and the column answers "did the customer pay for
urgency"; production staff need both, and OR-ing preserves every existing test
and the amber row styling with no frontend change. In
`app/Http/Controllers/ProductionStaff/ProductionBoardController.php`, add
`'is_rush'` to the `->get([...])` column list and change the `->each(...)`
closure to assign `$jobOrder->is_rush || ($jobOrder->due_at !== null &&
$jobOrder->due_at->lessThanOrEqualTo($endOfBusinessDay))`. Update the method
docblock: state that `is_rush` is now a real column, that this assignment
deliberately widens the in-memory value for display, and that the widened value
is never saved (nothing on this request path calls `save()`).

Add one test to `tests/Feature/ProductionStaff/ProductionBoardTest.php`: a job
order created via `JobOrder::factory()->rush()` on a production status with
`due_at` set a week out asserts `jobOrders.0.is_rush` is true. Leave the six
existing assertions untouched — they must still pass as written.

**Artist queue.** In `app/Http/Controllers/Artist/JobOrderQueueController.php`,
add `'is_rush'` to BOTH `->get([...])` column lists (the artist's own
`jobOrders` and the `availableJobOrders` pool). In
`resources/js/pages/artist/Dashboard.vue`, add `is_rush: boolean;` to both the
`ArtistJobOrder` and `PoolJobOrder` interfaces, import `Zap` from `@lucide/vue`,
and render a Rush `<Badge variant="outline">` with a `Zap` icon in the
description cell of both tables when `jobOrder.is_rush` is true. Match the
Production Board's existing amber treatment
(`class="border-amber-600/40 text-amber-600 dark:text-amber-400"`) so Rush reads
identically across portals.

**Artist workspace.** In
`app/Http/Controllers/Artist/JobOrderWorkspaceController.php`, add
`'is_rush' => $jobOrder->is_rush,` to the `'jobOrder' => [...]` payload array. In
`resources/js/pages/artist/JobOrderWorkspace.vue`, add `is_rush: boolean;` to the
job order prop interface and render the same amber Rush badge next to the job
order description heading.

**Cashier dashboard — rush badge.** In
`app/Http/Controllers/Cashier/DashboardController.php`, add `'is_rush'` to the
`->get([...])` column list. In `resources/js/pages/cashier/Dashboard.vue`, add
`is_rush: boolean;` to `CashierJobOrder` and render the same amber Rush badge
inside the first `<TableCell>`, under the description, when true.

**Cashier dashboard — paid-order filter.** Still in `DashboardController::index()`,
after the existing `->each(...)` that casts `amount_paid` to float, chain
`->reject(...)` then `->values()`. The reject predicate excludes a job order when
`total_amount` is not null AND `amount_paid` is not null AND
`(float) $amount_paid >= (float) $total_amount - 0.005`. Three points to honour:
reuse the `amount_paid` the existing `withSum` over Completed transactions
already produced — do not add a second query or a second definition of "paid";
the 0.005 epsilon absorbs decimal-cast float dust so a peso-exact payment is not
left on the list by a rounding hair; and `->values()` is mandatory, because
`reject()` preserves keys and a gapped-key Collection serialises to Inertia as a
JSON object, which would break `v-for` and the `CashierJobOrder[]` prop type.
Filtering in PHP rather than SQL is deliberate — this query is unpaginated and
already fully materialised, and a `havingRaw` on a `withSum` alias has no
`GROUP BY` to hang off. Document that reasoning in the method docblock alongside
the existing Plan 05-05/05-07 notes.

**Cashier payment page — rush fee default.** In
`resources/js/pages/cashier/JobOrderPayment.vue`, add `is_rush: boolean;` to the
`jobOrder` prop interface and change the `rushFeeApplied` initialiser (currently
`ref<boolean>(Boolean(props.jobOrder.rush_fee_amount))`) so that when
`rush_fee_amount` is null — meaning this job order has never been priced — it
falls back to `props.jobOrder.is_rush`, and otherwise keeps deriving from
`rush_fee_amount` exactly as today. Add a short comment: a saved
`rush_fee_amount` of 0 means the Cashier already looked at this and declined, so
re-checking the toggle on a revisit would silently overturn their decision. Do
NOT change `ComputeJobOrderPrice`, the `rush_fee_percentage` lookup, or the
submitted `rush_fee_applied` field — the Cashier keeps final say, and nothing
auto-charges. `PaymentController::edit()` passes the whole `$jobOrder` model to
Inertia and `JobOrder` declares no `#[Hidden]`, so `is_rush` reaches the page
with no controller change; confirm that with the human check rather than adding
a redundant explicit key.

Write `tests/Feature/Cashier/CashierDashboardPaidFilterTest.php` covering the
five behaviours above. Build transactions with the existing `Transaction`
factory — read `tests/Feature/Cashier/RecordPaymentTest.php` first for the
established way to create a Completed transaction of a given amount, and reuse
it. Assert presence/absence with `AssertableInertia`'s `->has('jobOrders', N)`
and `->where('jobOrders.0.id', ...)`.

Run `vendor/bin/pint --dirty --format agent`.
  </action>
  <verify>
    <automated>vendor/bin/pest tests/Feature/Cashier/CashierDashboardPaidFilterTest.php tests/Feature/ProductionStaff/ProductionBoardTest.php --compact && vendor/bin/pest tests/Feature/Cashier tests/Feature/Artist --compact && npm run types:check</automated>
    <human-check>
      In a browser, both themes, desktop and 375px:
      1. As an Artist, confirm the Rush badge renders on a rush row in My Queue and in Available Jobs, and that a non-rush row shows nothing extra (no empty gap).
      2. Open the Job Order Workspace for a rush job order and confirm the badge.
      3. As a Cashier, confirm the Rush badge on the dashboard row, then open that job order's payment page and confirm the Apply Rush Fee switch is already on, that you can turn it OFF, and that saving with it off records no rush fee.
      4. Open a NON-rush job order's payment page and confirm the switch is off.
      5. Record a payment that fully settles a job order and confirm the row leaves the Cashier dashboard on the next load, while a partially paid one stays.
    </human-check>
  </verify>
  <done>Production Board flags both clock-urgent and staff-marked rush; Rush badges render in all three portals; the rush fee toggle pre-checks without auto-charging; fully-paid job orders are gone from the Cashier list while partial/credit/unpriced remain; all Cashier, Artist and Production tests pass.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Unguessable tracking token and a public token route holding the existing PII boundary</name>
  <files>
    database/migrations/2026_09_10_120100_add_tracking_token_to_job_orders_table.php (new),
    app/Models/JobOrder.php,
    routes/web.php,
    app/Http/Controllers/Public/TrackingController.php,
    resources/js/pages/public/TrackingToken.vue (new),
    tests/Feature/Public/TrackingTokenTest.php (new)
  </files>
  <behavior>
    - Every newly created job order — from a controller, a factory, or a seeder — has a 32-character `tracking_token`, and no two share one.
    - `GET track/{token}` for a valid token returns 200 and renders `public/TrackingToken` with the job order number and its mapped public stage.
    - `GET track/{token}` for an unknown token returns 200 with `result.found` false — not an exception page.
    - The response body contains no customer name, contact number, email, address, price, payment status, transaction data, raw status enum value, or the token itself.
    - A job order at `pending_review` whose latest revision log has no outcome yields a non-null `reviewUrl` that resolves to the existing `public.design-review.show` route with a valid signature.
    - A job order not at `pending_review`, or whose latest revision log is already resolved, yields a null `reviewUrl`.
    - Following the emitted `reviewUrl` returns 200; the same URL with its signature stripped returns 403.
  </behavior>
  <action>
Create the migration with `php artisan make:migration add_tracking_token_to_job_orders_table --no-interaction` and rename it to `2026_09_10_120100_add_tracking_token_to_job_orders_table.php`. In `up()`, do three things in order: add `$table->string('tracking_token', 32)->nullable()->after('number')`; backfill every existing row by selecting ids with `DB::table('job_orders')->whereNull('tracking_token')->pluck('id')` and updating each with a fresh `Str::random(32)` (per-row, because the value must be unique); then in a second `Schema::table` call add `$table->unique('tracking_token')`. In `down()`, drop the unique index then the column.

Document the deliberate decision in the migration docblock: the column stays nullable at the DB level rather than being tightened with `->change()`, because a `change()` on SQLite rebuilds the table and can silently drop indexes, and this project runs SQLite in dev against MySQL in production. The uniqueness guarantee is the index; the presence guarantee is the model's `creating` hook below. Also state the security intent: this token is a bearer credential printed on a customer's slip, so it must never be selected on a public surface — the only thing it may ever do is look a job order up.

In `app/Models/JobOrder.php`: add `@property string $tracking_token` to the PHPDoc, import `Illuminate\Support\Str`, and add a `protected static function booted(): void` that registers `static::creating(function (JobOrder $jobOrder): void { $jobOrder->tracking_token ??= Str::random(32); })`. Deliberately do NOT add `tracking_token` to `#[Fillable]` — nothing request-driven may ever set it; the hook assigns the attribute directly, which is not mass assignment. Add a docblock on `booted()` saying so, and noting this hook is why factories and seeders get tokens for free.

In `routes/web.php`, immediately after the existing `Route::get('track', ...)` block and still outside every `auth`/`role:*` group, register `Route::get('track/{token}', [TrackingController::class, 'showByToken'])->middleware('throttle:120,1')->name('public.tracking.token')`. Mirror the neighbour's throttle exactly, and extend the existing comment block to explain that this is the QR-scan entry point to the same boundary.

In `app/Http/Controllers/Public/TrackingController.php`:
- Extend the class docblock so the boundary statement covers both actions.
- Add `public function showByToken(string $token): Response`.
- Query `JobOrder::query()->where('tracking_token', $token)->first(['id', 'number', 'status', 'released_at', 'cancelled_at'])`. `id` is selected only to look the revision log up and is deliberately absent from the response. Never widen this list, never eager-load a relation, and never select `tracking_token` back out.
- On no match, return `Inertia::render('public/TrackingToken', ['result' => ['found' => false]])`. Chosen over `abort(404)` so a smudged or partially scanned slip gets a readable customer-facing message instead of a raw error page.
- On a match, build the response as `['found' => true, 'number' => ..., 'stage' => $this->publicStage($jobOrder), 'reviewUrl' => ...]`. That is the whole shape. Do not add description or print size: the task allows them only with justification, and a description is free text a staff member may have typed a customer's name into, so the narrower shape is the defensible one. Say that in a comment.
- Compute `reviewUrl` in a small private helper. Return null unless the job order's `status` is `JobOrderStatus::PendingReview`. Otherwise load the LATEST revision log by `submitted_at` via `$jobOrder->revisionLogs()->latest('submitted_at')->first(['id', 'submitted_at', 'outcome'])`, and return null when it is null or its `outcome` is not null. This mirrors `DesignReviewController::isCurrentRevision()` plus `isActionable()` exactly — a superseded or already-resolved revision must not get a link. Compute `$expiresAt = $revisionLog->submitted_at->addDays(7)`, the same basis `DesignReviewController::render()` uses; if `$expiresAt` is already in the past return null rather than minting a URL that 403s on arrival. Otherwise return `URL::temporarySignedRoute('public.design-review.show', $expiresAt, ['revisionLog' => $revisionLog->id])`.
- Do not touch `DesignReviewController`, its routes, or the `signed` middleware in any way. This action only mints a link into the existing boundary.
- Leave `publicStage()` private and call it from both actions — no extraction is needed, both callers are in this class.

Run `php artisan wayfinder:generate --with-form`. The `--with-form` flag is mandatory; omitting it strips `.form()` from every generated action across the whole app.

Create `resources/js/pages/public/TrackingToken.vue`. It renders with no layout (`app.ts` already returns `null` for any page name starting with `public/`). Model it on `resources/js/pages/public/Tracking.vue`: a centred `Card`, a `<Head>` title, the job order number in `tabular-nums`, the stage as a `Badge`, and — satisfying CLAUDE.md rule 7 — a one-line description telling the customer what this screen is for. Add a not-found state for `result.found === false` with copy pointing them at the shop. When `reviewUrl` is non-null, render a prominent `<a :href="reviewUrl">` styled with `buttonVariants()` reading "Review your design" plus a line of context; use a plain anchor, not Inertia `<Link>`, since the target is a signed full-page URL. Reuse `Tracking.vue`'s `usePoll(5000, { only: ['result'] }, { autoStart: false })` pattern and its `TERMINAL_STAGES` stop condition verbatim so a Completed or Cancelled order stops burning the rate-limit bucket. Tailwind utilities and semantic tokens only.

Write `tests/Feature/Public/TrackingTokenTest.php` covering all seven behaviours. For the PII assertions, create the job order under a `Customer` with distinctive values (name `Zenaida Villanueva`, contact `09171234567`, address `12 Mabini St`) and a priced job order, then assert with `$response->assertDontSee('Zenaida Villanueva', false)` and equivalents for the contact number, the address, the `total_amount` digits, the raw status enum value, and the token string itself. Use `false` as the second argument so the check runs against the unescaped body — the Inertia data prop is JSON-encoded into the page, and an escaped-only assertion would miss a leak. Also assert the Inertia prop shape directly with `AssertableInertia`: `->has('result', 4)` on the found case pins the response to exactly four keys, so any future widening of the payload fails this test loudly. For the signature test, hit the emitted `reviewUrl` directly, then hit the same URL with the query string removed and assert 403.

Run `vendor/bin/pint --dirty --format agent`.
  </action>
  <verify>
    <automated>php artisan migrate --no-interaction && vendor/bin/pest tests/Feature/Public --compact && vendor/bin/pest tests/Feature/JobOrder --compact && npm run types:check && npm run build</automated>
    <human-check>
      1. Visit `/track/{token}` for a real job order in a private window (no session) and confirm the page renders with only the number and stage, in both themes and at 375px.
      2. Visit `/track/garbage` and confirm the not-found state, not an error page.
      3. Put a job order into `pending_review` with an open revision log, reload the token page, click "Review your design", and confirm the existing design-review page loads and an Approve verdict still works end to end.
      4. View source on the token page and search for the customer's name, phone number and the token — none may appear.
    </human-check>
  </verify>
  <done>Every job order has a unique token; `GET track/{token}` is public, throttled, and renders exactly four result keys; the PII assertions pass; the design-review link is freshly signed, expires on the same basis as the existing flow, and the unsigned URL still 403s.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 4: Printable QR handoff slip and returning-customer job-order history on New Visit</name>
  <files>
    app/Http/Controllers/FrontlineStaff/CustomerController.php,
    resources/js/pages/frontline-staff/NewVisit.vue,
    tests/Feature/FrontlineStaff/CustomerJobOrderHistoryTest.php (new)
  </files>
  <behavior>
    - Requesting New Visit with a `customer` query parameter returns a `customerJobOrders` prop holding that customer's job orders, newest first, capped at 20, each carrying number, description, type, status and created_at.
    - A customer with no prior job orders returns an empty `customerJobOrders` array.
    - Job orders belonging to a different customer never appear in `customerJobOrders`.
    - The history is loaded in a single query — no N+1.
    - After a visit is created, `confirmedQueueEntry.jobOrders` carries `tracking_token`, and a `trackingBaseUrl` prop holds the absolute base URL for the public token route.
  </behavior>
  <action>
**Server.** In `app/Http/Controllers/FrontlineStaff/CustomerController::index()`:
- Hoist the currently-inline selected customer lookup into a local `$selectedCustomer` variable so it can be reused (it is currently evaluated inside the render array).
- Add a `customerJobOrders` prop. When `$selectedCustomer` is null it is an empty collection. Otherwise it is `JobOrder::query()->whereRelation('queueEntry', 'customer_id', $selectedCustomer->id)->latest('id')->limit(20)->get(['id', 'number', 'description', 'type', 'status', 'created_at'])`. One query, no eager-loaded visit tree, no N+1 — state in a docblock that this expresses the customer → queueEntries → jobOrders path as a single constrained query precisely because eager-loading the visits and then flattening would fan out. Order by `id` descending rather than `created_at` so several job orders created in the same second within one visit still sort deterministically.
- This is the frontline staff's own authenticated screen, so the customer's own PII is fine here. Add a one-line comment saying so explicitly, so nobody later mistakes this for the public boundary Task 3 defends.
- Add `'tracking_token'` to the `jobOrders:` column list inside the `confirmedQueueEntry` `with([...])` eager load.
- Add a `trackingBaseUrl` prop set to `url('track')` — the absolute base the client appends a token to. Add a comment that it is built server-side so the QR encodes the deployment's real scheme and host rather than whatever the browser happens to be on.

**Customer selection must round-trip.** `selectCustomer()` currently sets
`selected.value` purely client-side, so no server request happens and the new
`customerJobOrders` prop would always be stale/empty. Change `selectCustomer()`
to issue `router.get(newVisit.url(), { q: searchTerm.value, customer: customer.id }, { preserveState: true, preserveScroll: true, replace: true })` — the same shape `search()` already uses — and let the existing `watch(() => props.selectedCustomer)` set `selected`. Do not also set `selected.value` optimistically: that would flash the intake form for one frame before the history arrives and replaces it.

Correspondingly, `clearCustomer()` must drop the `customer` parameter from the
URL as well as nulling `selected`, otherwise re-picking the same customer
produces an identical `props.selectedCustomer` value, the watch never fires, and
the page is stuck on an empty state. Have it set `selected.value = null` and
issue the same `router.get` without the `customer` key. Add a comment recording
that trap.

**History gate.** Add a `customerJobOrders` prop type (`id`, `number`,
`description`, `type`, `status`, `created_at`) and a `newJobOrderRequested`
ref. Derive `showJobOrderForm` as `newJobOrderRequested.value || props.customerJobOrders.length === 0`, and reset `newJobOrderRequested` to false in the existing `watch` on `props.selectedCustomer` so switching customers re-gates. Change the intake form block's condition from `selected && !confirmedQueueEntry` to `selected && !confirmedQueueEntry && showJobOrderForm`.

Add a new history block rendered when `selected && !confirmedQueueEntry && !showJobOrderForm`: a `SectionHeading` ("Previous Job Orders" plus a description saying these are this customer's most recent orders and that a new one can be started at any time), then a `DataTableCard` wrapping a `Table` with columns Job Order / Description / Type / Status / Date. Reuse the page's existing `jobOrderTypeLabel()` and `jobOrderStatusLabel()` helpers for the Type and Status cells — do not write a second copy. Render the number and date with `tabular-nums`. Above or beside the heading, render a prominent `<Button size="lg">` reading "New Job Order" (with the already-imported `Plus` icon) that sets `newJobOrderRequested = true`. Since the history table can run long, also render the same button below the table so the primary action is never a screen away. Rows are informational only — do not make them clickable, so no keyboard-affordance obligation is created.

**Printable QR slip.** Extend the existing `confirmedQueueEntry` confirmation
Card. Add `tracking_token: string;` to the `ConfirmedJobOrder` interface and a
`trackingBaseUrl: string` prop. Import `TrackingQrCode from '@/components/TrackingQrCode.vue'` and reuse it — do not instantiate `qrcode.vue` directly.

Inside the Queue Number card, below the existing job order `<ul>`, add a slips
section: one bordered block per job order in `confirmedQueueEntry.job_orders`,
each containing the `TrackingQrCode` fed `` `${trackingBaseUrl}/${jobOrder.tracking_token}` ``, the job order number in `tabular-nums`, the customer name from `selected.name`, and exactly one instruction line — "Scan this code to follow your order." Give each slip a per-slip `<Button variant="outline">` with the already-imported `Printer` icon reading "Print Slip".

**Print, with Tailwind `print:` variants only.** No `style=` attribute, no
`<style>` block, no CSS module, and no `@media print` addition to `app.css`. If
you conclude the approach below cannot work, STOP and report it to the developer
as a deviation rather than adding CSS silently.

The approach: when `confirmedQueueEntry` is present, the intake form block does
not render at all and the customer search Card does not render (it is the `v-if`
arm of the customer bar's `v-else`), so only four things are on screen. Add
`class="print:hidden"` to the `PageHeader`, to the steps `<ol>`, and to the
customer bar `<div>`. Inside the Queue Number `Card`, add `print:hidden` to the
`CardHeader`, to the queue-number flex row, to the job order `<ul>`, and to the
"Start New Visit" `Link`; add `print:border-0 print:shadow-none` to the `Card`
itself. What survives a print is exactly the slips section.

To print ONE slip rather than all of them, hold a `printingJobOrderId` ref and
bind each slip's class to `printingJobOrderId !== null && printingJobOrderId !== jobOrder.id ? 'print:hidden' : ''`. Both class strings must appear as literals in the source so Tailwind's scanner generates them. Also give each slip `print:break-inside-avoid` so a slip never splits across pages when all of them print.

Reset the ref on the browser's `afterprint` event, registered with `{ once: true }` immediately before calling `window.print()` — not after the `window.print()` call, because `window.print()` returns immediately in some browsers and blocks until the preview closes in others, and a post-call reset races the render in the first case. Await `nextTick()` between setting the ref and calling `print()` so the class binding has actually been applied to the DOM.

Write `tests/Feature/FrontlineStaff/CustomerJobOrderHistoryTest.php` covering the
five behaviours. Assert `customerJobOrders` with `AssertableInertia`'s
`->has('customerJobOrders', N)` and `->where('customerJobOrders.0.number', ...)`
for ordering. For the cap, create 25 job orders across two visits for one
customer and assert exactly 20 come back with the newest first. For the N+1
check, wrap the request in `DB::listen` (or `\Illuminate\Support\Facades\DB::enableQueryLog()`) and assert the query count does not scale with the number of visits — read `tests/Feature/FrontlineStaff/ReadyForPickupAlertTest.php` first to see whether this suite already has a query-counting idiom to reuse before inventing one. Also assert `confirmedQueueEntry.job_orders.0.tracking_token` is present and that `trackingBaseUrl` ends with `/track`.

Run `vendor/bin/pint --dirty --format agent`.
  </action>
  <verify>
    <automated>vendor/bin/pest tests/Feature/FrontlineStaff --compact && npm run types:check && npm run build && ! grep -rn 'style=\|<style' resources/js/pages/frontline-staff/NewVisit.vue</automated>
    <human-check>
      In a real browser, both themes, desktop and 375px:
      1. Search for and select a customer who HAS past job orders. Confirm the history table appears instead of the intake form, that the numbers align in a column, and that "New Job Order" reveals the form.
      2. Press "Change" to go back, select a DIFFERENT customer, and confirm the gate re-arms (history shows again, not the form left open from the previous customer). Then re-select the FIRST customer and confirm it still works — the re-select path is where the URL-parameter trap bites.
      3. Select a brand new customer with no history and confirm the intake form appears immediately, with no extra click.
      4. Complete a visit with two job orders. In the confirmation block, confirm two QR slips render. Scan one with a phone and confirm it lands on the public tracking page for the right job order.
      5. Press "Print Slip" on the SECOND job order and confirm the print preview shows only that slip — no sidebar, no page header, no step bar, no queue number card chrome, no first slip. Cancel the preview, then press "Print Slip" on the first and confirm it now shows only the first (proving the afterprint reset ran).
      6. Keyboard-reach both "New Job Order" and "Print Slip" buttons with Tab and activate with Enter.
    </human-check>
  </verify>
  <done>History gates the intake form for returning customers only; customer selection round-trips through the server; QR slips render and scan to the correct public page; a single slip prints alone twice in a row; no inline styles, style blocks, or app.css additions were introduced; all frontline tests pass and the build is clean.</done>
</task>

</tasks>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| unauthenticated internet → `GET track/{token}` | A bearer token printed on a paper slip is the only credential. Anyone holding or guessing it reads whatever this action returns. |
| unauthenticated internet → `public.design-review.show` | Already defended by `signed` middleware. This plan mints links into it; it must not widen it. |
| frontline staff browser → `job_orders.is_rush` | Staff-supplied boolean crossing into a column the Cashier's fee default reads. |
| frontline staff browser → `customer` query parameter | Arbitrary customer id selecting whose PII and order history is rendered. |

## STRIDE Threat Register

| Threat ID | Category | Component | Disposition | Mitigation Plan |
|-----------|----------|-----------|-------------|-----------------|
| T-mbb-01 | Information Disclosure | `TrackingController::showByToken` | mitigate | Narrow `first(['id','number','status','released_at','cancelled_at'])` and a four-key response array; `publicStage()` maps the enum so no raw status value ships. `TrackingTokenTest` asserts absence of name, contact, address, price and raw status against the unescaped body, and pins the prop count at 4 so any widening fails the suite. |
| T-mbb-02 | Information Disclosure | `tracking_token` echoed back on a public surface | mitigate | Token is excluded from the `showByToken` select entirely and never appears in the response; a dedicated test asserts the token string is absent from the body. |
| T-mbb-03 | Spoofing | Token guessing / enumeration | mitigate | `Str::random(32)` (~190 bits of alphanumeric entropy) generated in the model's `creating` hook, unique-indexed. Route inherits the neighbouring `throttle:120,1` per-IP limiter. |
| T-mbb-04 | Elevation of Privilege | Design-review reachable without a signature | mitigate | This plan adds no route to the `design-review` prefix and does not touch its `signed` middleware. It only calls `URL::temporarySignedRoute`. A test asserts the same URL with its query string stripped returns 403. |
| T-mbb-05 | Elevation of Privilege | Stale or superseded revision reachable via a minted link | mitigate | `reviewUrl` is emitted only when the job order is `pending_review` AND the latest revision log by `submitted_at` has a null `outcome`, mirroring `isCurrentRevision()` + `isActionable()`. Expiry uses the same `submitted_at->addDays(7)` basis, and an already-past expiry yields null instead of a dead link. |
| T-mbb-06 | Tampering | Forged `is_rush` on intake | accept | The flag only pre-checks a toggle the Cashier must still confirm before any money moves; `ComputeJobOrderPrice` and `rush_fee_applied` are untouched, so the worst case is a mis-prioritised job the Cashier can see and correct. |
| T-mbb-07 | Information Disclosure | `?customer=` enumerating customer history | accept | The New Visit route already sits behind `auth` + `role:frontline-staff` and already renders any customer by id; this plan adds order history to a surface that already exposes the same customer's name, contact number, email and address. No new privilege boundary is crossed. |
| T-mbb-08 | Tampering | npm/composer installs | mitigate | No new dependencies. `qrcode.vue` and `reka-ui` are already installed; no `composer require` or `npm install` is permitted by this plan. |
</threat_model>

<verification>
After all four tasks:

- `php artisan migrate:fresh --seed --no-interaction` succeeds (proves both migrations and the seeder path get tokens from the `creating` hook).
- `php artisan test --compact` — full suite green. Ask the developer to run this last.
- `npm run types:check` and `npm run build` clean.
- `vendor/bin/pint --dirty --format agent` reports nothing to fix.
- `php artisan route:list --path=track` shows both `public.tracking.show` and `public.tracking.token`, neither inside an auth or role group.
- `grep -rn 'style=\|<style' resources/js/pages/frontline-staff/NewVisit.vue resources/js/pages/public/TrackingToken.vue` returns nothing, and `git diff resources/css/app.css` is empty.

Do NOT run `composer types:check` — Larastan is broken in this environment with
`Undefined constant Larastan\Larastan\LARAVEL_VERSION`, a pre-existing failure
unrelated to this work. Do not attempt to fix it.
</verification>

<success_criteria>
- `is_rush` is a real NOT NULL column, written by both frontline intake paths, and visible as a Rush badge to Artist, Cashier and Production Staff.
- The Production Board `is_rush` collision is resolved explicitly (persisted OR due-date heuristic) with the model PHPDoc corrected and the six existing `ProductionBoardTest` assertions still passing unchanged.
- The Cashier's Apply Rush Fee toggle pre-checks for rush orders without auto-charging, without bypassing the Cashier, and without touching `ComputeJobOrderPrice`.
- `GET track/{token}` is public, unauthenticated, throttled at 120/min, and returns exactly `found`, `number`, `stage`, `reviewUrl` — proven by a prop-count assertion and by unescaped-body PII assertions.
- The design-review boundary is unchanged: no new unsigned route, no duplicated verdict logic, and an unsigned URL still 403s.
- Frontline staff can print a single QR slip per job order using only Tailwind `print:` variants.
- Returning customers show history first with a New Job Order button; first-time customers reach the form with no extra click.
- Fully paid job orders leave the Cashier dashboard; partial, on-credit and unpriced job orders stay.
- No new npm or composer dependencies. No inline styles, `<style>` blocks, CSS modules, or `app.css` additions.
- Every UI change was observed working in a real browser per CLAUDE.md rule 10, including each "change your mind" path called out in the human checks.
</success_criteria>

<output>
Create `.planning/quick/260910-mbb-frontline-rush-flag-printable-customer-q/260910-mbb-SUMMARY.md` when done.
</output>
