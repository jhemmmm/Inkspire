---
phase: 02-customer-queue-management
reviewed: 2026-09-02T00:00:00Z
depth: standard
files_reviewed: 43
files_reviewed_list:
  - app/Concerns/CustomerValidationRules.php
  - app/Concerns/JobOrderValidationRules.php
  - app/Enums/JobOrderStatus.php
  - app/Enums/JobOrderType.php
  - app/Enums/QueueStatus.php
  - app/Http/Controllers/FrontlineStaff/CustomerController.php
  - app/Http/Controllers/FrontlineStaff/QueueEntryController.php
  - app/Http/Controllers/Public/QueueDisplayController.php
  - app/Http/Requests/FrontlineStaff/AddJobOrderRequest.php
  - app/Http/Requests/FrontlineStaff/SearchCustomersRequest.php
  - app/Http/Requests/FrontlineStaff/StoreCustomerRequest.php
  - app/Http/Requests/FrontlineStaff/StoreQueueEntryRequest.php
  - app/Http/Requests/FrontlineStaff/UpdateQueueEntryStatusRequest.php
  - app/Models/Customer.php
  - app/Models/JobOrder.php
  - app/Models/QueueEntry.php
  - database/factories/CustomerFactory.php
  - database/factories/JobOrderFactory.php
  - database/factories/QueueEntryFactory.php
  - database/migrations/2026_09_01_133940_create_customers_table.php
  - database/migrations/2026_09_01_154402_create_queue_entries_table.php
  - database/migrations/2026_09_01_154403_create_job_orders_table.php
  - resources/js/app.ts
  - resources/js/components/ui/radio-group/RadioGroup.vue
  - resources/js/components/ui/radio-group/RadioGroupItem.vue
  - resources/js/components/ui/radio-group/index.ts
  - resources/js/components/ui/textarea/Textarea.vue
  - resources/js/components/ui/textarea/index.ts
  - resources/js/config/nav/frontline-staff.ts
  - resources/js/pages/frontline-staff/Dashboard.vue
  - resources/js/pages/frontline-staff/NewVisit.vue
  - resources/js/pages/frontline-staff/QueueList.vue
  - resources/js/pages/public/QueueDisplay.vue
  - routes/portals.php
  - routes/web.php
  - tests/Feature/FrontlineStaff/AddJobOrderToVisitTest.php
  - tests/Feature/FrontlineStaff/CustomerRegistrationTest.php
  - tests/Feature/FrontlineStaff/CustomerSearchTest.php
  - tests/Feature/FrontlineStaff/JobOrderTypeValidationTest.php
  - tests/Feature/FrontlineStaff/QueueEntryIntakeTest.php
  - tests/Feature/FrontlineStaff/QueueNumberGenerationTest.php
  - tests/Feature/FrontlineStaff/QueueStatusTransitionTest.php
  - tests/Feature/Public/QueueDisplayTest.php
findings:
  critical: 2
  warning: 3
  info: 2
  total: 7
status: issues_found
---

# Phase 02: Code Review Report

**Reviewed:** 2026-09-02T00:00:00Z
**Depth:** standard
**Files Reviewed:** 43
**Status:** issues_found

## Summary

Reviewed the customer/queue intake flow: customer search & registration, queue number generation, job order intake (single-save and add-to-existing-visit), queue status transitions, and the public queue display, across backend (controllers, form requests, models, migrations) and frontend (Inertia/Vue pages, nav config, UI primitives).

The domain logic is generally careful — the `nextForBusinessDay()` locking pattern, the wildcard `required_if` validation for per-row file requirements, the PII-safe column allowlist on the public display, and the append-only audit trail integration are all correctly implemented and covered by passing tests (`php artisan test tests/Feature/FrontlineStaff tests/Feature/Public` → 32/32 passing).

However, two BLOCKER-level defects were found that are **not covered by the existing test suite** and break core flows:

1. The authenticated frontline-staff Queue List page (`GET /frontline-staff/queue`) always returns zero results, because it repeats a date-comparison mistake that the codebase's own model documentation explicitly warns against and that a sibling controller (`QueueDisplayController`) already avoids. This was verified live against the running (SQLite) database.
2. The "Start New Visit" flow in `NewVisit.vue` leaves the page stuck showing the just-served customer instead of resetting to the search screen, because a `watch()` callback only handles the case where a customer becomes selected, never the case where selection is cleared.

Both defects were found by tracing logic/data flow rather than by pattern-matching, and both are invisible to the current test suite (no feature test exists for the Queue List route; the stale-`ref` bug is a client-only Vue/Inertia navigation defect that HTTP-level Pest tests cannot observe).

## Critical Issues

### CR-01: Frontline staff Queue List page always returns zero entries

**File:** `app/Http/Controllers/FrontlineStaff/QueueEntryController.php:30`

**Issue:** `index()` filters today's queue with a plain `where('queue_date', QueueEntry::currentBusinessDate())`, comparing the bare `Y-m-d` business-date string against the stored `queue_date` value. Per `QueueEntry`'s own documented cast behavior (see the docblock on `QueueEntry::nextForBusinessDay()`), writing a `date`-cast attribute serializes with a full `Y-m-d H:i:s` timestamp, and on SQLite (the driver configured for both local dev in `.env`/`.env.example` and the entire test suite in `phpunit.xml`) that string is never truncated back to a bare date, so an equality `where()` never matches. `QueueDisplayController::index()` (the public counterpart to this exact page) and `QueueEntry::nextForBusinessDay()` both already use `whereDate()` specifically to avoid this; this call site was missed.

Verified live against the project's running database:
```
Raw DB value: 2026-09-02 00:00:00
Count via where():      0
Count via whereDate():  1
```
for a row freshly created with `queue_date` set to today's business date. There is no feature test covering `GET /frontline-staff/queue` (`frontline-staff.queue-entries.index`), which is why this shipped undetected — every other test in this phase happens to exercise `QueueEntry::nextForBusinessDay()` or `QueueDisplayController`, both of which already use the correct pattern.

Impact: D-05 ("today's queue with each entry's number, customer, and status") is completely non-functional — frontline staff cannot see the queue they're supposed to manage from this page, on the currently configured database driver.

**Fix:**
```php
public function index(Request $request): Response
{
    return Inertia::render('frontline-staff/QueueList', [
        'queueEntries' => QueueEntry::query()
            ->with('customer:id,name')
            ->whereDate('queue_date', QueueEntry::currentBusinessDate())
            ->orderBy('queue_number')
            ->get(['id', 'customer_id', 'queue_number', 'status']),
    ]);
}
```
Also add a feature test asserting the route returns today's entries (mirroring `tests/Feature/Public/QueueDisplayTest.php`'s date-scoping test), so this class of regression is caught going forward.

### CR-02: "Start New Visit" leaves the page stuck on the previous customer

**File:** `resources/js/pages/frontline-staff/NewVisit.vue:82-89`

**Issue:**
```ts
watch(
    () => props.selectedCustomer,
    (value) => {
        if (value) {
            selected.value = value;
        }
    },
);
```
This watcher only ever sets `selected.value` — it never clears it. Clicking "Start New Visit" (`:href="newVisit()"`, line 345-352) navigates to the bare `new-visit` URL with no `customer`/`queueEntry` query params, so the server correctly returns `selectedCustomer: null` and `confirmedQueueEntry: null`. But because Inertia reuses the existing `frontline-staff/NewVisit` component instance across this navigation (the file's own preceding comment acknowledges this exact non-remounting behavior for the registration-redirect case: "Inertia can preserve this component instance across the post-registration redirect instead of remounting it"), the `if (value)` guard means `selected.value` is never reset to `null` on this transition. No `:key` is set anywhere (checked `app.ts`/`app.blade.php`) to force a remount.

Result: after finishing one visit, clicking "Start New Visit" re-renders the same "Customer" card and job-order intake form for the *previous* customer instead of the search screen — while `intakeForm.customer_id` has already been reset to `0` by the prior `onSuccess: () => intakeForm.reset()` (line 148). If staff proceed to submit in this state, `customer_id: 0` fails `exists:customers,id` and the flow silently breaks, requiring a manual page reload to recover. This directly breaks the one-visit-after-another workflow that is the core daily use of this page.

**Fix:** mirror the prop unconditionally so the local ref can't drift from server state on any navigation, not just the "customer selected" direction:
```ts
watch(
    () => props.selectedCustomer,
    (value) => {
        selected.value = value;
    },
);
```
(`selectCustomer()` still works for the pre-submit client-only selection, since that call happens without any intervening Inertia visit that would re-fire this watcher with a stale value.)

## Warnings

### WR-01: Job order file upload has no size or type constraints

**File:** `app/Concerns/JobOrderValidationRules.php:28,43`

**Issue:** Both `jobOrdersRules()` and `jobOrderRules()` validate the file field as `['nullable', 'file', 'required_if:...']` only — no `max:` (size) or `mimes:`/`mimetypes:` constraint. Any authenticated frontline-staff request can upload an arbitrarily large file of any type (bounded only by server-level `php.ini` `upload_max_filesize`/`post_max_size`, not by application policy), which lands in `storage/app/job-orders` unrestricted.

**Fix:**
```php
'job_orders.*.file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,ai,psd,eps', 'required_if:job_orders.*.type,'.JobOrderType::TypeA->value],
```
(adjust size/mime list to the actual accepted print-ready file types) and apply the same change to `jobOrderRules()`.

### WR-02: Customer search does not escape SQL LIKE wildcard characters

**File:** `app/Http/Controllers/FrontlineStaff/CustomerController.php:24-29`

**Issue:**
```php
$customers = Customer::query()
    ->where(fn ($query) => $query
        ->where('name', 'like', "%{$request->string('q')}%")
        ->orWhere('contact_number', 'like', "%{$request->string('q')}%"))
    ->orderBy('name')
    ->get();
```
This is safe from SQL injection (Eloquent binds the value), but `%` and `_` typed by staff into the search box are interpreted as LIKE wildcards rather than literal characters, producing incorrect match sets for search terms that happen to contain them (e.g. a contact number search containing no literal risk today, but a name search for `"D_la Cruz"` or any input with `%`/`_` behaves unexpectedly).

**Fix:**
```php
$term = addcslashes((string) $request->string('q'), '%_\\');

$customers = Customer::query()
    ->where(fn ($query) => $query
        ->where('name', 'like', "%{$term}%")
        ->orWhere('contact_number', 'like', "%{$term}%"))
    ->orderBy('name')
    ->get();
```

### WR-03: Stale file silently resubmitted after toggling job order type

**File:** `resources/js/pages/frontline-staff/NewVisit.vue:140-142,391-434`

**Issue:** `onFileChange(row, event)` sets `row.file` when a file is chosen for a Type A row. If the staff member then switches that row's type to Type B (hiding the file input via `v-if="row.type === 'type_a'"`) and back to Type A, the file `<input>` re-renders empty (native inputs cannot be pre-populated), but `row.file` still holds the previously selected `File` object — nothing clears it. The form will silently submit the old file even though the visible input looks empty, which can attach the wrong file to a job order without the user's awareness.

**Fix:** clear `row.file` whenever the row's type changes away from `type_a`:
```ts
function selectJobOrderType(row: JobOrderRow, value: unknown): void {
    row.type = value === 'type_b' ? 'type_b' : 'type_a';
    if (row.type !== 'type_a') {
        row.file = null;
    }
}
```
and bind `@update:model-value="(value) => selectJobOrderType(row, value)"` on the row's `<RadioGroup>` instead of `v-model="row.type"`.

## Info

### IN-01: Job order rows keyed by array index

**File:** `resources/js/pages/frontline-staff/NewVisit.vue:357`

**Issue:** `<Card v-for="(row, index) in intakeForm.job_orders" :key="index">` keys removable rows by their position. `removeRow(index)` can splice out a non-last row, causing Vue to reuse/reindex DOM nodes for subsequent rows by position rather than identity — this can bleed transient input/focus state between rows after a mid-list removal (the underlying `job_orders` data itself stays correct since `v-model`/`row` bind to the actual array items).

**Fix:** give each row a stable synthetic id and key on that instead of `index`:
```ts
function emptyJobOrderRow(): JobOrderRow {
    return { description: '', type: 'type_a', file: null, _key: crypto.randomUUID() };
}
```
```html
<Card v-for="(row, index) in intakeForm.job_orders" :key="row._key">
```

### IN-02: "Zero-result search" gate for new customer registration is UI-only

**File:** `app/Http/Controllers/FrontlineStaff/CustomerController.php:44-57`

**Issue:** The docblock states registration is "gated behind a zero-result search (D-04)", but `store()` performs no such check server-side — the only enforcement is `NewVisit.vue`'s conditional rendering (`v-if="hasSearched && customers.length === 0"`). Any authenticated frontline-staff `POST` to `frontline-staff.customers.store` succeeds regardless of whether a search was ever run, so the gate can be bypassed by any direct/replayed request.

**Fix:** if D-04 is meant to be an enforced business rule rather than a UI nudge, consider requiring/validating a preceding search context server-side, or update the docblock to make explicit that this is a client-side-only workflow hint, not an authoritative constraint.

---

_Reviewed: 2026-09-02T00:00:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
