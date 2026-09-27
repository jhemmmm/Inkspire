---
quick_id: 260927-ts2
status: complete
completed: 2026-09-27
committed: false
---

# Quick 260927-ts2: Price at intake, drop Material, prices in every job order table

Frontline now computes a live price at intake from the chosen Product/Service × W×H (ft) × qty, adjustable per row via "Adjust"/"Use computed". Material is gone everywhere, including the column — the catalog entry Frontline picks already is the material. Every job order table (Cashier Dashboard, Frontline Dashboard, Queue List, and a new read-only Admin Job Orders page) now shows a Total/Balance. The Cashier's payment page prefills from the intake quote instead of opening at ₱0.

Nothing was committed, as instructed. All changes are left in the working tree alongside the user's own 229 pre-existing uncommitted changes.

## Changes

### Task 1 — Backend: schema, pricing, validation, store logic, display total, Cashier prefill

- Two new migrations:
    - `database/migrations/2026_09_27_134446_add_pricing_fields_to_job_orders_table.php`: adds `width_ft`, `height_ft` (after `quantity`), `quoted_amount` (after `pricing_entry_id`), drops `material`. Reversible `down()`.
    - `database/migrations/2026_09_27_134447_delete_material_specification_options.php`: deletes `specification_options` rows where `category = 'material'`. No down path (documented).
    - Both ran cleanly against the dev SQLite database via `php artisan migrate`.
- Material removed everywhere:
    - `app/Enums/SpecificationCategory.php`: `Material` case deleted; the catalog is now `PrintSize` only.
    - `database/seeders/SpecificationOptionSeeder.php`: all Material rows, `supersededMaterials()`, and its call site removed.
    - `database/factories/SpecificationOptionFactory.php`: `material()` state removed.
    - `app/Models/JobOrder.php`: `material` dropped from `#[Fillable]` and the docblock.
    - `app/Concerns/JobOrderValidationRules.php`: `material` rule replaced with `width_ft`/`height_ft`/`quoted_amount`.
    - `app/Http/Controllers/FrontlineStaff/QueueEntryController.php`, `FrontlineStaff/JobOrderController.php`, `Artist/JobOrderWorkspaceController.php`: `material` swapped for `width_ft`/`height_ft`.
    - Stale "Material"/"materials" doc comments cleaned up in `Admin/SpecificationOptionController.php`, `StoreSpecificationOptionRequest.php`, `UpdateSpecificationOptionRequest.php`, `DatabaseSeeder.php`. The Cashier `DashboardController.php` comment never referenced `material` (confirmed, no change needed).
- New `app/Actions/POS/QuoteJobOrderLineAmount.php` (invokable): `base_price × w × h × qty` for units prefixed `sq ft` (null if W/H missing), else `base_price × qty`.
- `app/Models/JobOrder.php`: casts for `width_ft`/`height_ft`/`quoted_amount` (`decimal:2`), added to `#[Fillable]`; new `display_total` accessor (`#[Appends(['display_total'])]`) — `total_amount` if set, else `ComputeJobOrderPrice` over `quoted_amount`/`is_rush`, else null.
- `app/Http/Controllers/FrontlineStaff/QueueEntryController.php`: `QuoteJobOrderLineAmount` injected; both `store()` and `addJobOrder()` compute `quoted_amount` via a shared private `quoteAmountForRow()` (null with no service, explicit override wins, else computed from width/height/qty defaulting qty to 1 for the calc only).
- `resources/js/pages/cashier/JobOrderPayment.vue`: `quoted_amount` added to the interface; `lineAmount` now initializes from `base_price_snapshot ?? quoted_amount ?? 0`. `PaymentController::edit()` needed no backend change (already passes the full model).

### Task 2 — Intake UI: shared price fields component, NewVisit, QueueList add-job dialog, detail views

- `resources/js/lib/jobOrders.ts`: new `quoteLineAmount()`, mirroring the PHP action exactly (client-side preview only; server recomputes on submit).
- `app/Models/SpecificationOption.php`: new `printSizeDimensionsByLabel()` for auto-filling W/H from a print-size preset.
- `app/Http/Controllers/FrontlineStaff/CustomerController.php` and `QueueEntryController::index()`: added `printSizeDimensions`, `rushFeePercentage` (and, for the queue page, `pricingEntries`/`specificationOptions`, since that dialog never had a service field before).
- New `resources/js/components/JobOrderPriceFields.vue` — the one new shared component (service select, print size, conditional W/H, quantity, live price strip with Adjust/Use computed, rush hint). Supports a `nativeNames` mode (hidden inputs for `pricing_entry_id`/`print_size`/`description`) for QueueList's uncontrolled `<Form>`.
- `resources/js/pages/frontline-staff/NewVisit.vue`: Material field/computed removed; `JobOrderRow` gained `width_ft`/`height_ft`/`quoted_amount`/`quoted_amount_overridden`; Product/Service + Print Size/Quantity/Material replaced with `<JobOrderPriceFields>`; sticky footer now shows "N job orders · Estimated total ₱X".
- `resources/js/pages/frontline-staff/QueueList.vue`: Add Job Order dialog's free-text description replaced with `<JobOrderPriceFields native-names>`, backed by a per-entry `jobOrderPriceState` reactive map (`priceRow()` getter); `is_rush` Switch gained a `v-model` alongside its native `name`; state resets on successful submit.
- `resources/js/pages/frontline-staff/JobOrderDetail.vue` and `resources/js/pages/artist/JobOrderWorkspace.vue`: Material row replaced with a Size row (`W ft × H ft` or `—`).

### Task 3 — Price columns in every job order table + new Admin Job Orders page

- `app/Http/Controllers/Cashier/DashboardController.php`: `quoted_amount` added to the `get()` column list (needed for `display_total` to resolve). `resources/js/pages/cashier/Dashboard.vue`: Total/Balance columns added, "Est." badge when `total_amount` is null.
- `app/Http/Controllers/FrontlineStaff/DashboardController.php`: both `index()`'s `readyForPickup` query and `search()` now select `total_amount`/`quoted_amount`/`is_rush` and `withSum` completed transactions as `amount_paid`. `resources/js/pages/frontline-staff/Dashboard.vue`: Total/Balance columns on both tables, shared `balanceLabel()` helper.
- `app/Http/Controllers/FrontlineStaff/QueueEntryController.php::index()`: the `jobOrders` eager-load converted from column-shorthand to a closure with `select()` + `withSum` + `with('assignedArtist')`. `resources/js/pages/frontline-staff/QueueList.vue`: inline price next to each job order's description, plus a visit-level Total column (`visitTotal()` sums `display_total`).
- New Admin Job Orders page (read-only):
    - `app/Http/Requests/Admin/FilterJobOrdersRequest.php` (new): `q`/`status` rules.
    - `app/Http/Controllers/Admin/JobOrderController.php` (new): search by number/description/customer name, status filter, `withSum` for `amount_paid`, paginated 25, `display_total` resolves automatically.
    - `routes/admin.php`: `GET admin/job-orders` → `admin.job-orders.index`, inside the existing `role:admin` group.
    - `resources/js/pages/admin/JobOrders.vue` (new): `PageContainer`/`PageHeader`/`DataTableCard`/`EmptyState`, debounced search, `SearchableSelect` status filter (12 statuses, per the UI checklist's >10-options rule), columns `#`/Customer/Product/Service/Size × Qty/Total/Paid/Balance/Status/Payment, `AuditTrail.vue`-style pagination.
    - `resources/js/config/nav/admin.ts`: new "Job Orders" nav item (`ClipboardList` icon, verified exported by `@lucide/vue`).
    - Wayfinder regenerated with `--with-form`; `resources/js/routes/admin/job-orders/index.ts` and `resources/js/actions/App/Http/Controllers/Admin/JobOrderController.ts` confirmed present with `.form()`.

### Tests

- `tests/Feature/FrontlineStaff/JobOrderPrintSpecificationsTest.php`: material cases dropped; added width/height persistence, sq-ft/piece `quoted_amount` computation, override-wins, no-service-leaves-null, and omitting-the-override-recomputes cases, across both `store()` and `addJobOrder()`.
- `tests/Feature/Artist/JobOrderBriefTest.php`: `material` input/assertion replaced with `width_ft`/`height_ft` (confirmed decimal-cast serializes as `"3.00"`/`"6.00"`).
- `tests/Feature/Admin/SpecificationCatalogTest.php`: every `material()`/`SpecificationCategory::Material` reference removed; kept the same test shapes using `printSize()` only (a second `printSize()` fixture where a test needed two entries).
- `tests/Feature/Cashier/CashierPagesTest.php`: new test asserting the payment page's `jobOrder` prop carries `quoted_amount` and that `total_amount` stays null server-side after intake (protects the PayMongo/credit sentinel).
- New `tests/Feature/Admin/JobOrderIndexTest.php` (10 tests): list with totals, search by number/customer, status filter, cancelled-order exclusion, pagination at 25, and 403 for every non-admin role.
- `tests/Feature/FrontlineStaff/ReadyForPickupAlertTest.php`: the "does not widen the payload" test's premise changed — `total_amount`/`quoted_amount`/`is_rush` are now intentionally selected/exposed, so the canary column was switched to `base_price_snapshot` (never selected here), which is a deliberate deviation (Rule 1 — the old assertion tested behavior this plan intentionally changed). Added a `display_total`/`amount_paid` presence check to the main test.
- `tests/Feature/FrontlineStaff/JobOrderSearchTest.php`: added `display_total`/`amount_paid` presence checks to the number-search test.

## Deviations from Plan

**1. [Rule 1] `ReadyForPickupAlertTest`'s payload-widening canary switched from `total_amount` to `base_price_snapshot`.** The test asserted `total_amount` never appears in the Frontline dashboard payload — true before this plan, false now that Task 3b deliberately selects and exposes it for the Total column. Kept the test's real intent (catch `withAggregate()`'s `job_orders.*` fallback) by asserting on `base_price_snapshot`, a column still never selected there.

**2. [Rule 3] `QueueEntryController::index()`'s `jobOrders` eager-load closure left untyped (`fn ($query) => ...`).** A `HasMany $query` type hint caused a real 500 (Larastan's contravariance concern manifested as a genuine `TypeError` at runtime: Laravel's `with()` closure signature is `Closure(Relation<*,*,*>): mixed`, and a narrower `HasMany` param isn't compatible). Matched the untyped-closure convention already used by `Cashier/DashboardController`'s own `accountsReceivable` eager-load closure.

**3. [Rule 3] `Admin/JobOrderController::whereLike()` duplicates `FrontlineStaff/DashboardController::whereLike()` verbatim rather than extracting a shared trait.** The plan left this as "your call" — duplicating matches the existing precedent (this exact 4-line method already exists once, unextracted) and keeps the new controller self-contained.

No architectural deviations. No stubs — every new prop is wired to a real data source.

## Verification

- `php artisan test --compact` on the touched test files: `tests/Feature/FrontlineStaff/JobOrderPrintSpecificationsTest.php tests/Feature/Artist/JobOrderBriefTest.php tests/Feature/Admin/SpecificationCatalogTest.php tests/Feature/Cashier tests/Feature/Admin/JobOrderIndexTest.php tests/Feature/FrontlineStaff` — **all pass** (399 tests, 2140 assertions, across FrontlineStaff+Artist+Admin+Cashier).
- Full suite (`php artisan test --compact`): **708 tests, 704 passed, 3 skipped, 1 failed.** The one failure is `Tests\Feature\Reports\ReportExportTest` — "xlsx export of a non-financial-summary report is not capped at 100 rows" — a pre-existing failure unrelated to this task (`app/Http/Controllers/Reports/ReportController.php` was already uncommitted-dirty before this session started; this file was never touched by this plan; the same failure was already recorded in the prior quick task's SUMMARY, `260927-svw`).
- `vendor/bin/pint --dirty --format agent`: passed, every round.
- `composer types:check` (phpstan, level 7): **28 errors project-wide**, none newly introduced beyond two duplicate-pattern hits in `Admin/JobOrderController::whereLike()` that mirror the exact, pre-existing, unfixed style in `FrontlineStaff/DashboardController::whereLike()` (see Deviation 3). All other 26 errors are in files this task never touched (Admin `DashboardController`, `FrontlineStaff/JobOrderController`, the Reports controllers/`ReportBuilder`, the Cashier requests, `SpecificationOptionFactory`, the seeders) — identical count/location to the pre-existing baseline recorded in `260927-svw-SUMMARY.md`.
- `npm run types:check` (vue-tsc): passed, clean, every round — including after all NewVisit/QueueList/Admin JobOrders/JobOrderPriceFields edits.
- Wayfinder regenerated with `--with-form`; confirmed `resources/js/routes/admin/job-orders/index.ts` (with `.form()`) and `resources/js/actions/App/Http/Controllers/Admin/JobOrderController.ts` exist.
- Migrations ran cleanly against the dev SQLite database (`php artisan migrate`, no `migrate:fresh` used).

## Not Verified

- **Browser check.** No browser/devtools tooling was available in this environment, so none of the plan's end-of-plan browser steps were run: New Visit's sq-ft service + print-size autofill + Adjust/Use-computed round trip + sticky footer total + service-change reset; the price appearing on Queue List/Frontline Dashboard/Cashier Dashboard after a real submit; the Cashier payment page opening prefilled; QueueList's Add Job Order dialog end-to-end; Admin → Job Orders search/filter/pagination/403-as-non-admin at the browser level; desktop vs 375px and light vs dark theme for any of the above.
- The full suite above was run by this agent as a sanity check, but per the plan's own closing instruction, the user should still run `php artisan test --compact` themselves as the final step.

## Known Stubs

None.

## Self-Check: PASSED

- All new files confirmed present on disk: both migrations, `QuoteJobOrderLineAmount.php`, `JobOrderPriceFields.vue`, `Admin/JobOrderController.php`, `Admin/FilterJobOrdersRequest.php`, `admin/JobOrders.vue`, `Admin/JobOrderIndexTest.php`, and both Wayfinder-generated files for the new route.
- No commits were made (constraint honored) — nothing to verify by hash; verified instead by the file-existence checks above and by the passing test/type-check runs recorded.
