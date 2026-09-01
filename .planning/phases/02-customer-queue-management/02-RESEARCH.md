# Phase 2: Customer & Queue Management - Research

**Researched:** 2026-09-01
**Domain:** Laravel 13 / Inertia v3 / Vue 3.5 — greenfield domain models (Customer, QueueEntry, JobOrder), concurrent daily-reset counters, unauthenticated polling display, file intake
**Confidence:** HIGH (backend mechanics, verified against installed vendor source + official docs) / MEDIUM (UI mechanics, verified against official Inertia v3 docs) / LOW-MEDIUM (timezone/business-day assumption, flagged explicitly)

## Summary

Phase 2 is genuinely greenfield: no `Customer`, `QueueEntry`, or `JobOrder` models exist yet, so every pattern below is inferred from Phase 1's established conventions (Form Request + Validation Concern trait pairs, PHP-attribute-based audit observer registration, `Inertia::flash('toast', ...)`, Wayfinder-only routing) rather than from existing Phase-2-adjacent code. The three technically novel problems this phase introduces — a concurrency-safe daily-reset queue counter, an unauthenticated polling display with zero PII leakage, and unvalidated file intake — all have safe, current, in-repo-verifiable solutions that require **no new Composer or npm packages**.

The single most consequential finding is that the daily-reset queue number can be generated safely under concurrent writes using either (a) a `lockForUpdate()` read of `MAX(queue_number)` scoped by an indexed `queue_date` column, relying on InnoDB's documented next-key/gap-locking behavior to "lock the nonexistence" of the next row for a brand-new day — no schema deviation from the approved ERD — or (b) a dedicated counter table using Laravel's `incrementOrCreate()` (confirmed present in the exact installed `laravel/framework` 13.29.0 source), which is simpler but adds a 13th/14th table beyond the approved 12-table ERD, mirroring the `system_configurations` precedent that required explicit call-out in Phase 1. This is presented as an open question, not resolved unilaterally, because CONTEXT.md explicitly reserves ERD-shape decisions ("follow the ERD; no deviation was discussed or approved here").

A second consequential finding: `config('app.timezone')` is `UTC`, not a Philippines-local timezone. If the daily reset uses `today()`/`now()` unmodified, the queue will roll over at 8:00 AM Manila time (UTC+8), mid-business-day — this is very likely wrong for a walk-in print shop and needs explicit confirmation before locking the implementation, since it is not addressed anywhere in CONTEXT.md.

A third finding worth flagging to the planner: Inertia v3 ships an official `usePoll()` composable (confirmed via official docs) that is a materially better fit for QUEUE-06 than the manual `setInterval` + `router.reload()` pattern the (still-unapproved, "Approval: pending") UI-SPEC currently describes — same no-websockets guarantee, less code, automatic cleanup, and built-in background-tab throttling.

**Primary recommendation:** Build `Customer`, `QueueEntry`, `JobOrder` as three plain Eloquent models following the `User` model's exact conventions (PHP-attribute `#[ObservedBy(AuditObserver::class)]`, `string` DB columns cast to PHP backed enums, Form Request + Validation Concern trait pairs, no Policy classes). Generate the queue number via a `lockForUpdate()`-scoped `MAX()+1` query on an indexed `queue_date` column on `queue_entries` itself (no new table), inside a `DB::transaction()`, using the *shop's local business-day* boundary rather than UTC — confirm the local timezone with the user before implementation. Store Type A files with Laravel's default `store()` hashed-filename behavior on the **private** `local` disk, never `public`, and never trust `getClientOriginalName()`. Build the public display with `usePoll()` and a controller action that hand-picks `id, queue_number, status` only.

## Project Constraints (from CLAUDE.md)

These directives from `./CLAUDE.md` apply to every task in this phase and were treated as authoritative throughout this research (equivalent to a locked decision):

- **Boost tool priority:** Prefer Laravel Boost MCP tools (`search-docs`, `database-schema`, `database-query`) over manual file reads/raw SQL during planning and execution. (This research agent does not have Boost MCP tools available and used direct vendor-source reads + official docs + WebSearch instead — the planner/executor agents should use Boost tools where available.)
- **`search-docs` before any Laravel-ecosystem-dependent change** — skip only for copy-only edits.
- **No new base folders without approval** — `app/Models`, `app/Http/Controllers`, `app/Http/Requests`, `app/Concerns`, `app/Enums`, `app/Policies`, `database/factories`, `database/migrations` are all pre-approved existing base folders; nothing in this phase requires a new one.
- **Do not change dependencies without approval** — confirmed no new Composer/npm packages are required for this phase (see Package Legitimacy Audit).
- **PHP conventions:** always brace control structures; constructor property promotion; explicit return types/param hints everywhere; TitleCase enum case names; PHPDoc array shapes over inline comments.
- **Pint:** run `vendor/bin/pint --dirty --format agent` after any PHP change (never `--test`).
- **Pest:** use `php artisan make:test --pest {Name}`, run narrowest test first (`--filter`), run full `php artisan test --compact` only after asking the user.
- **Wayfinder:** all frontend→backend route/action calls must go through generated `@/actions`/`@/routes` helpers, never hardcoded URL strings. Regenerate with `--with-form` (not the bare command) — `vite.config.ts` still has `formVariants: true` confirmed this session — or `.form()` silently disappears from every existing helper (a real regression hit in Phase 1, per STATE.md).
- **Vite/frontend changes:** if the user doesn't see a change reflected, ask whether they've run `npm run build`/`npm run dev`/`composer run dev`.
- **Do not create documentation files unless explicitly requested** (this RESEARCH.md is the one explicitly-scoped exception, per the GSD workflow that invoked this research).
- **GSD workflow enforcement:** file-changing work must go through `/gsd-execute-phase` (or `/gsd-quick`/`/gsd-debug` for small fixes) — not raw edits.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Customer Record & Search**
- D-01: Registration captures a full profile: name, contact number, email, and address.
- D-02: Contact number is enforced unique at the database level — one contact number maps to one customer record.
- D-03: Search matches partially (LIKE-style) on name or contact number, not exact-match-only.
- D-04: Frontline Staff must search first (and get zero results) before "Register New" unlocks.

**Queue Number Mechanics**
- D-05: A queue entry is stateful: Waiting → Serving → Done. Distinct from `job_orders.status`.
- D-06: Queue numbers are daily-reset sequential (e.g. `001`, `002`, ...) — no letter prefix.
- D-07: The queue number is generated first (visit enters Waiting); job orders are added afterward as a separate step within the same overall intake flow (see D-11/D-14).
- D-08: State transitions (Waiting → Serving → Done) are manual staff actions ("Call Next"/mark Serving/mark Done) — not auto-triggered by job order creation.

**Shared Queue Display (QUEUE-06, added mid-discussion)**
- D-09: A public, unauthenticated route displays each queue entry's number and current status — status only, no PII, mirroring TRACK-02's no-PII rule.
- D-10: The display refreshes via client-side polling — no Laravel Echo/Reverb/websockets (project-wide constraint).

**Job Order Intake Scope**
- D-11: For Type A, Frontline Staff attaches the file at intake and it is stored — but **not validated** against DPI/format/size thresholds; that's Phase 3's `JOB-01`. Phase 2 only needs a file input + storage column.
- D-12: A job order records a free-text product/service description — no `pricing_database` link yet (Phase 5).
- D-13: A Phase 2 job order starts in a new placeholder status, distinct from Phase 3/6's production-stage statuses.

**Multi-Job-Order Visit Flow**
- D-14: Adding job orders to a visit is one combined form: queue number generated + one-or-more repeatable job order rows submitted together in a single save.
- D-15: No hard limit on job orders per visit; job orders can still be added to a visit even after it's marked Done — "Done" does not lock the visit.

### Claude's Discretion
- Exact enum wording/values for queue-entry status and job order placeholder status — follow `app/Enums/UserRole.php`'s TitleCase-case/string-value pattern.
- Whether `queue_entries` and `job_orders` are separate tables with a foreign key (matches the approved 12-table ERD) or any denormalization — **follow the ERD; no deviation was discussed or approved here.**
- Exact shape of the "Add another job order" repeatable-row UI — a UI/UX call (now resolved by UI-SPEC: `Card`-per-row, `RadioGroup` for Type A/B).
- Audit trail coverage for `Customer`, `QueueEntry`, `JobOrder` — apply the existing `AuditObserver` pattern; no new discussion needed.

### Deferred Ideas (OUT OF SCOPE)
None — the one scope-expansion idea raised (shared queue display) was folded into this phase as QUEUE-06 rather than deferred. No other out-of-domain ideas came up during discussion.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| QUEUE-01 | Frontline Staff can search for a returning customer by name or contact info | LIKE-style search pattern documented in Architecture Patterns; reuses `AuditTrail.vue`'s `router.get(..., {preserveState:true})` filter pattern |
| QUEUE-02 | Frontline Staff can register a new customer | Form Request + Validation Concern trait pattern; DB-level unique constraint on `contact_number` documented in Code Examples |
| QUEUE-03 | Frontline Staff can generate a queue number for a customer visit | Concurrency-safe daily-reset counter fully researched (two viable approaches) in Architecture Patterns / Don't Hand-Roll |
| QUEUE-04 | A single queue visit can produce more than one job order | Repeatable-row Inertia `useForm` pattern + nested array validation documented in Code Examples |
| QUEUE-05 | Frontline Staff marks each job order Type A/B at intake | `JobOrderType` backed enum pattern (matches `UserRole` convention); file input conditional on Type A documented |
| QUEUE-06 | Public unauthenticated shared display, status/number only, polling-refreshed | `usePoll()` official API fully documented; public route registration convention (mirrors `Route::inertia('/', 'Welcome')`) documented; PII-exclusion enforced at controller prop-shaping layer |
</phase_requirements>

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Customer search (LIKE query) | API/Backend | Database/Storage | Controller builds the `LIKE` query; DB executes it. No client-side filtering — dataset could grow past what's safe to ship to the browser. |
| Customer registration + uniqueness | API/Backend | Database/Storage | Form Request validates shape; DB-level unique constraint on `contact_number` is the actual integrity guarantee (D-02 requires DB-level, not just app-level). |
| Queue number generation | API/Backend | Database/Storage | Concurrency safety is a DB-locking concern (`lockForUpdate`/atomic increment) — must not be computed in PHP without a DB-level guard. |
| Repeatable job-order-row form UI | Browser/Client | — | Adding/removing rows before submit is pure client-side reactive state (Vue array), no server round-trip per row. |
| Combined intake save (queue entry + job orders + file) | API/Backend | Database/Storage | Must be atomic (single DB transaction) so a failed job-order row never leaves an orphaned queue entry. |
| File storage (Type A) | API/Backend | Database/Storage (disk) | Controller calls `UploadedFile::store()`; the private `local` disk is the actual storage tier. |
| Queue state transitions (Waiting→Serving→Done) | API/Backend | Database/Storage | Manual staff action per D-08; simple authorized mutation. |
| Public queue display polling | Browser/Client | API/Backend | `usePoll()` drives the refresh cadence client-side; the backend's only job is to shape props to exclude PII — the leak-prevention boundary is backend, the refresh mechanism is frontend. |
| Audit trail coverage | API/Backend | Database/Storage | `#[ObservedBy(AuditObserver::class)]` attribute fires on Eloquent lifecycle events; `audit_trail` table is the sink. |

## Standard Stack

### Core (already installed — reused, not new)
| Library | Version (verified) | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `laravel/framework` | 13.29.0 [VERIFIED: `composer show --direct`] | Eloquent models, migrations, `Storage`, `DB::transaction`/`lockForUpdate` | Already the project's backend framework |
| `inertiajs/inertia-laravel` | 3.3.1 [VERIFIED: `composer show --direct`, released 2026-08-04] | Server-side Inertia responses, `Inertia::render`, `Inertia::flash` | Already the project's only response layer (no JSON API) |
| `@inertiajs/vue3` / `@inertiajs/vite` | ^3.0.0 [VERIFIED: `package.json`] | `useForm`, `usePoll`, `router` | Client-side Inertia adapter; `usePoll` is the v3-native polling primitive needed for QUEUE-06 |
| `reka-ui` | ^2.10.4 [VERIFIED: `package.json` + confirmed `RadioGroupRoot`/`RadioGroupItem` already ship in `node_modules/reka-ui/dist`] | Underlying primitives for shadcn-vue `radio-group` | Type A/B selector; no new npm dependency needed |
| `pestphp/pest` + `pest-plugin-laravel` | 5.1.3 / 5.0.1 [VERIFIED: `composer show --direct`] | Feature tests for new controllers | Already the project's only test framework |
| `laravel/wayfinder` | 0.1.21 [VERIFIED: `composer show --direct`] | Typed route/controller helpers for the new routes | Non-negotiable per CLAUDE.md — no hardcoded URLs |

### Supporting (new UI primitives, no new package.json entries)
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| shadcn-vue `radio-group` block | matches installed `reka-ui` ^2.10.4 | Type A / Type B mutually-exclusive selector | Copied via `npx shadcn add radio-group` — source files only, `reka-ui` dependency already satisfied |
| shadcn-vue `textarea` block | n/a (plain wrapper) | Customer address field | Copied via `npx shadcn add textarea` — no dependency at all |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `lockForUpdate()` + indexed `MAX()` on `queue_entries.queue_date` (no new table) | Dedicated `queue_counters` table + `Builder::incrementOrCreate()` | The counter-table approach is simpler code and has a stronger, well-understood atomicity guarantee (`col = col + n` UPDATE is race-safe regardless of isolation level or index presence), but it adds a table beyond the approved 12-table ERD — same category of decision that required explicit sign-off for `system_configurations` in Phase 1. Flagged as an Open Question, not decided here. |
| Manual `setInterval` + `router.reload({only:[...]})` (what the draft UI-SPEC currently describes) | Official `usePoll()` composable | `usePoll` is the documented v3-idiomatic API: same no-websockets guarantee, but adds automatic unmount cleanup, `mode: 'cancel'`/`'overlap'`/`'rest'` concurrency control, and 90%-throttling when the browser tab is backgrounded, none of which a raw `setInterval` gets for free. No functional downside identified. |
| `Cache::lock()` (Redis-style distributed lock) for the counter | Plain `DB::transaction()` + `lockForUpdate()` | `CACHE_STORE` is `database`, not Redis (per `.env.example`/tech stack) — a database-backed cache lock adds an extra table round-trip for no benefit over a direct row lock on the table that's already being written to. Not recommended at this shop's scale. |
| Storing Type A files on the `public` disk (simpler URL generation via `asset()`) | `local` (private) disk with `storage/app/private` root | `public` disk requires `php artisan storage:link` (not yet run — confirmed via `ls public/storage` returning nothing) and makes files reachable by anyone who guesses/finds the URL. Design files are exactly what TRACK-02 already establishes must never be publicly exposed; treat Type A originals the same way even though TRACK-02 itself is Phase 6. |

**Installation:** No install commands needed for Composer. For the two new shadcn-vue components:
```bash
npx shadcn add radio-group
npx shadcn add textarea
```
(Official shadcn-vue registry only, no third-party registry — matches UI-SPEC's Registry Safety table, "not required" for vetting gate.)

## Package Legitimacy Audit

**Not applicable — no new Composer or npm package.json dependencies are introduced by this phase.** The two shadcn-vue additions (`radio-group`, `textarea`) are copied component source files from the official shadcn-vue registry into `resources/js/components/ui/`, not new `package.json` entries; their only runtime dependency (`reka-ui`) is already installed at `^2.10.4` and already exports the primitives they wrap (`RadioGroupRoot`, `RadioGroupItem`, `RadioGroupIndicator` — confirmed present in `node_modules/reka-ui/dist/`). slopcheck/registry-verification was not run because there is nothing to verify.

## Architecture Patterns

### System Architecture Diagram

```
Frontline Staff (authenticated, role:frontline_staff)
        |
        v
[Search page]  --GET /frontline-staff/customers?q=...------> CustomerController@index
        |            (router.get, preserveState:true)              |
        |                                                            v
        |                                              LIKE query on name/contact_number
        |                                                            |
        v                                                            v
  zero results? --no--> click row --> proceed to intake        Table of matches (Inertia props)
        |
       yes
        |
        v
[Register form] --POST /frontline-staff/customers-----------> CustomerController@store
        |            (Wayfinder .form())                             |
        v                                                     StoreCustomerRequest
                                                          (unique:customers,contact_number)
        |                                                             |
        v                                                             v
[Combined intake form: queue + job order rows (repeatable)]   Customer::create() [ObservedBy fires]
        |
        | POST /frontline-staff/queue-entries  (multipart/form-data — file present)
        v
QueueEntryController@store
        |
        +--> DB::transaction():
        |       1. lock+read MAX(queue_number) WHERE queue_date = <business day> FOR UPDATE
        |       2. QueueEntry::create([...queue_number, status: Waiting]) [ObservedBy fires]
        |       3. foreach job_order row: JobOrder::create([...]) incl. file->store() [ObservedBy fires]
        |
        v
Inertia::flash('toast', ...) + redirect back with confirmation state
        |
        v
[Internal queue list] (Waiting/Serving/Done, "Call Next"/"Mark Done" actions)
        |
        | PATCH /frontline-staff/queue-entries/{id}/status
        v
QueueEntryController@updateStatus  --> authorize + mutate status enum

================================================================
Public, unauthenticated (no session, no CSRF needed for GET)
================================================================
[Public Queue Display page]  <--usePoll(5000, {only:['queueEntries']})--
        |
        v
GET /queue-display  ------------------------------> QueueDisplayController@index
                                                              |
                                                              v
                                          QueueEntry::whereDate('queue_date', <today>)
                                              ->select(['id','queue_number','status'])
                                              ->orderBy('queue_number')->get()
                                              (no customer_id, no join to customers — PII
                                               exclusion enforced here, not client-side)
```

### Recommended Project Structure
```
app/
├── Enums/
│   ├── QueueStatus.php          # Waiting|Serving|Done, string-backed
│   ├── JobOrderType.php         # TypeA|TypeB, string-backed
│   └── JobOrderStatus.php       # Intake (placeholder; Phase 3/6 add more cases)
├── Models/
│   ├── Customer.php             # #[ObservedBy(AuditObserver::class)]
│   ├── QueueEntry.php           # belongsTo Customer, hasMany JobOrder
│   └── JobOrder.php             # belongsTo QueueEntry
├── Http/
│   ├── Controllers/
│   │   ├── FrontlineStaff/
│   │   │   ├── CustomerController.php      # index (search), store (register)
│   │   │   └── QueueEntryController.php    # index (internal list), store (combined create), updateStatus
│   │   └── Public/
│   │       └── QueueDisplayController.php  # index only, unauthenticated
│   └── Requests/
│       └── FrontlineStaff/
│           ├── SearchCustomersRequest.php
│           ├── StoreCustomerRequest.php
│           ├── StoreQueueEntryRequest.php      # includes nested job_orders.* rules
│           └── UpdateQueueEntryStatusRequest.php
├── Concerns/
│   ├── CustomerValidationRules.php
│   └── JobOrderValidationRules.php
database/
├── migrations/
│   ├── 2026_09_01_xxxxxx_create_customers_table.php
│   ├── 2026_09_01_xxxxxx_create_queue_entries_table.php
│   └── 2026_09_01_xxxxxx_create_job_orders_table.php
└── factories/
    ├── CustomerFactory.php
    ├── QueueEntryFactory.php
    └── JobOrderFactory.php
resources/js/
├── pages/
│   ├── frontline-staff/
│   │   ├── Dashboard.vue        # replaced (currently an empty placeholder)
│   │   ├── NewVisit.vue         # search -> register -> combined intake, one continuous flow (per UI-SPEC)
│   │   └── QueueList.vue        # internal Waiting/Serving/Done list + actions
│   └── public/
│       └── QueueDisplay.vue     # new top-level namespace; needs a new app.ts layout-switch case
└── config/nav/
    └── frontline-staff.ts       # new; currently missing, follows owner.ts's exact pattern
```

### Pattern 1: Concurrency-safe daily-reset counter via indexed `MAX()` + `lockForUpdate()` (no new table)
**What:** A `queue_entries` table with an indexed `queue_date` column and a composite unique index on `(queue_date, queue_number)`. The next number is computed inside a transaction that takes a locking read over the current day's rows.
**When to use:** QUEUE-03 — this is the recommended default because it introduces no schema deviation from the approved 12-table ERD.
**Why it's safe for the "first entry of the day" case too:** InnoDB's next-key locking "enables you to 'lock' the nonexistence of something in your table" — a `SELECT ... FOR UPDATE` over an indexed range takes a gap lock even when zero rows currently match, blocking a concurrent transaction from inserting into that same gap until the first transaction commits. This requires MySQL's default `REPEATABLE READ` isolation level (confirmed as the unmodified default in `config/database.php` — no `isolation_level` override present) [CITED: dev.mysql.com/doc/refman/8.4/en/innodb-next-key-locking.html, Percona "InnoDB's Gap Locks"].
```php
// Source: pattern verified against MySQL InnoDB next-key locking docs +
// Laravel's documented DB::transaction()/lockForUpdate() API
public static function nextForBusinessDay(CarbonImmutable $businessDay): int
{
    return DB::transaction(function () use ($businessDay) {
        $last = QueueEntry::query()
            ->where('queue_date', $businessDay->toDateString())
            ->lockForUpdate()
            ->max('queue_number');

        return ($last ?? 0) + 1;
    });
}
```
**Testing caveat (flag for planner):** SQLite (used by the Pest suite) does not have InnoDB-style gap locking, and a single-connection `:memory:` SQLite database serializes all writes anyway — so a Pest feature test cannot actually prove the concurrency guarantee described above; it can only prove the *sequential* correctness (numbers increment 1,2,3...). True concurrent-write safety can only be verified against real MySQL (e.g., a manual load test or an integration test gated to a `mysql` CI service), which is outside what the automated suite can exercise. Document this as a known test-coverage gap rather than silently assuming the Pest suite proves it.

### Pattern 2: PHP-attribute-based audit observer registration (established in Phase 1, confirmed by direct source read)
**What:** New models opt into audit coverage with a single class attribute — **not** a service-provider registration call.
**When to use:** `Customer`, `QueueEntry`, `JobOrder` — per CONTEXT.md's Claude's-Discretion note ("apply the existing AuditObserver registration pattern"). CONTEXT.md itself speculated this "likely" lives in a service provider — **confirmed by reading `app/Models/User.php` directly that it does not; it's a class attribute:**
```php
// Source: app/Models/User.php (verified by direct read this session)
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[Fillable(['name', 'contact_number', 'email', 'address'])]
#[ObservedBy(AuditObserver::class)]
class Customer extends Model
{
    // ...
}
```
There is no `bootstrap/providers.php` or `AppServiceProvider::boot()` registration to add or find — grep confirms `AuditObserver` is only referenced from `app/Models/User.php` and `app/Models/SystemConfiguration.php`, both via this same attribute. Apply identically to `QueueEntry` and `JobOrder`.

### Pattern 3: `string` DB column + PHP backed enum via `casts()` (never native DB `enum` type)
**What:** Status/type columns are plain `$table->string('column')` in the migration, cast to a PHP 8.1+ backed enum in the model's `casts(): array` method.
**When to use:** `queue_entries.status`, `job_orders.type`, `job_orders.status` — matches `users.role`'s exact precedent (verified: `2026_08_31_165341_...php` uses `$table->string('role')`, not `$table->enum(...)`).
```php
// Source: app/Enums/UserRole.php pattern, applied to a new enum
namespace App\Enums;

enum QueueStatus: string
{
    case Waiting = 'waiting';
    case Serving = 'serving';
    case Done = 'done';
}
```
```php
// app/Models/QueueEntry.php
protected function casts(): array
{
    return [
        'status' => QueueStatus::class,
        'queue_date' => 'date',
    ];
}
```
**Why this matters for Phase 3/6:** `job_orders.status` will grow more cases (`ForProduction`, `Printing`, `QualityCheck`, `ReadyForPickup`) in later phases. A plain `string` column needs zero migration to add enum cases later; a native MySQL `ENUM(...)` column would require an `ALTER TABLE` every time. This project has already made this choice once (for `role`) — repeat it here.

### Pattern 4: Repeatable job-order rows via nested Inertia `useForm` + dot-notation validation
**What:** A single Vue reactive array of job-order row objects, submitted as one POST alongside the queue/customer fields. Laravel validates the nested array natively via `job_orders.*.field` rules.
**When to use:** QUEUE-04/QUEUE-05, per D-14's "one combined form" requirement.
```vue
<!-- Source: pattern consistent with official Inertia v3 file-upload docs
     (https://inertiajs.com/docs/v3/the-basics/file-uploads) -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    customer_id: props.customer.id,
    job_orders: [
        { description: '', type: 'type_a', file: null as File | null },
    ],
});

function addRow() {
    form.job_orders.push({ description: '', type: 'type_a', file: null });
}

function removeRow(index: number) {
    form.job_orders.splice(index, 1);
}

function submit() {
    form.post(store.url()); // Wayfinder-generated action
}
</script>
```
```php
// app/Http/Requests/FrontlineStaff/StoreQueueEntryRequest.php
public function rules(): array
{
    return [
        'customer_id' => ['required', 'integer', 'exists:customers,id'],
        'job_orders' => ['required', 'array', 'min:1'],
        'job_orders.*.description' => ['required', 'string', 'max:255'],
        'job_orders.*.type' => ['required', Rule::in(['type_a', 'type_b'])],
        // Baseline upload-integrity check only — NOT the DPI/format/size
        // business validation, which D-11 explicitly defers to Phase 3 (JOB-01).
        'job_orders.*.file' => ['required_if:job_orders.*.type,type_a', 'nullable', 'file'],
    ];
}
```
**Verified:** Inertia's official docs confirm requests containing files — including nested ones — are automatically converted to `multipart/form-data` FormData; no third-party form library is needed for this shallow (one-level) nesting depth [CITED: inertiajs.com/docs/v3/the-basics/file-uploads]. This is a POST (create), so the documented PUT/PATCH method-spoofing-with-files caveat does not apply here.

### Pattern 5: Public route registration (mirrors existing `Welcome` precedent)
**What:** The QUEUE-06 display route lives in `routes/web.php`, outside every `auth`/`role` middleware group — exactly like the existing `Route::inertia('/', 'Welcome')`.
```php
// routes/web.php — append, do not nest inside the ['auth','verified'] group
Route::get('queue-display', [QueueDisplayController::class, 'index'])
    ->middleware('throttle:60,1') // new: only fully-public, repeatedly-polled route in the app
    ->name('queue-display');
```
```ts
// resources/js/app.ts — add one case to the existing layout switch
case name.startsWith('public/'):
    return null;
```
**Why the `throttle` middleware is a worthwhile addition here (not in CONTEXT.md, offered as a recommendation):** every other route in the app requires authentication, which already implicitly rate-limits abuse (a session/login is a cost to acquire). This is the only route reachable by an anonymous client that will be hit repeatedly (by design, via polling) — a minimal throttle guards against it being hammered from outside the shop.

### Anti-Patterns to Avoid
- **Computing the queue number in PHP without a DB lock** (e.g., `QueueEntry::whereDate(...)->count() + 1` with no transaction/lock): two staff generating a number in the same second will get the same number. Always wrap in `DB::transaction()` + `lockForUpdate()`.
- **Trusting `getClientOriginalName()`** for the stored file path or display name — Laravel's own docs call this "unsafe, as the file name and extension may be tampered with by a malicious user" [CITED: laravel.com/docs/13.x/filesystem#other-uploaded-file-information]. Use `store()`'s auto-generated hashed name for the storage path; if the original name needs to be shown to staff later, store it as a separate DB column, never as the actual filesystem path.
- **Storing Type A files on the `public` disk** "for convenience" — see Alternatives Considered above.
- **A native MySQL `ENUM(...)` column** for any new status/type field — breaks the project's established `string`+backed-enum convention and makes future enum-case additions (Phase 3/6) require a migration instead of a code change.
- **Building a second full page navigation for "Register New Customer"** — UI-SPEC explicitly locks this as an inline state change on the same search screen, not a route change (`router.get`/`visit()` pattern, not `router.visit(otherRoute)`).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Daily-reset atomic counter | A custom Redis/file-lock mechanism, or a raw `MAX()+1` with no lock | `DB::transaction()` + `lockForUpdate()` (Pattern 1) or `Builder::incrementOrCreate()` (verified present in installed `laravel/framework` 13.29.0 at `Illuminate/Database/Eloquent/Builder.php:785`) | Both are framework-shipped, tested-in-the-wild primitives; a hand-rolled lock is exactly the kind of "looks fine until two people click at once" bug this shop's paper process already has and Inkspire exists to fix. |
| Unauthenticated polling refresh | Raw `fetch()`/`setInterval` XHR calls outside Inertia's request cycle | `usePoll()` from `@inertiajs/vue3` | Official v3 API already handles cleanup-on-unmount, background-tab throttling, and concurrency-mode selection — reimplementing this is pure risk for zero benefit. |
| File-name safety | A custom filename sanitizer/slugifier | `UploadedFile::store()`'s built-in `hashName()`-based auto-naming | Laravel's own docs flag `getClientOriginalName()` as unsafe and ship the safe alternative as the *default* behavior of `store()` — no extra code needed, just don't override it with `storeAs()` + the original name. |
| Nested-array + file form submission | A custom `FormData` builder / third-party form library (`useInertiaForm`) | Native `useForm()` — Inertia auto-converts to `FormData` including one-level-nested files | The third-party `useInertiaForm` package exists specifically for *deeper* nesting/`transform()` edge cases; this phase's nesting (`job_orders[].file`) is exactly the shallow case the official library already handles. |

**Key insight:** Every "hard" problem this phase introduces (concurrency, polling, file safety) already has a first-party, already-installed answer. The only genuine open design decision is the ERD-shape question (new counter table or not) and the timezone question — both are business/architecture calls for the user or planner, not technical gaps.

## Common Pitfalls

### Pitfall 1: Daily reset fires on the wrong clock (UTC vs. shop-local business day)
**What goes wrong:** `today()`/`now()` use `config('app.timezone')`, which is `UTC` [VERIFIED: `config/app.php` line 68, read directly]. If the queue-date boundary uses this unmodified, the counter resets at 8:00 AM Philippine time (UTC+8, assuming the shop is in the Philippines given PayMongo/GCash/Maya context), not at actual shop opening/midnight.
**Why it happens:** Laravel's default timezone is UTC unless explicitly changed; nothing in this codebase or in CONTEXT.md addresses it.
**How to avoid:** Confirm the shop's actual timezone with the user before implementing. Either (a) change `config('app.timezone')` project-wide to `Asia/Manila` (affects every timestamp in the app, a bigger decision than it looks), or (b) compute the queue's "business day" via an explicit timezone conversion scoped only to this feature (`now()->timezone('Asia/Manila')->toDateString()`), leaving `app.timezone` untouched. Option (b) is lower-blast-radius and is the tentative recommendation, but this is genuinely unconfirmed — see Assumptions Log A1.
**Warning signs:** Queue numbers appear to "reset early" or "reset late" relative to when staff open/close the shop; numbers from the tail end of one calendar day (UTC) bleed into what staff perceive as "yesterday's" queue.

### Pitfall 2: Treating `firstOrCreate`/naive `MAX()+1` as race-safe without a lock
**What goes wrong:** `QueueEntry::whereDate('queue_date', today())->max('queue_number') + 1` executed outside a `lockForUpdate()`+transaction pair will produce duplicate numbers under concurrent requests — two Frontline Staff terminals generating a number in the same instant both read the same `MAX()`, both compute the same "next" number, and (if there's no unique constraint) both insert it.
**Why it happens:** MySQL's default `REPEATABLE READ` isolation only protects the *read* value consistently within a transaction; without `lockForUpdate()`, two transactions' reads don't block each other at all.
**How to avoid:** Always pair the `MAX()` read with `lockForUpdate()` inside `DB::transaction()` (Pattern 1), AND add a composite unique index on `(queue_date, queue_number)` as defense-in-depth so even a locking bug surfaces as a loud `QueryException` rather than a silently duplicated queue ticket.
**Warning signs:** Two customers holding the same queue number printed/displayed at the front counter — this is the exact bug the paper process this project replaces was already vulnerable to; regressing to it here would be a notable trust failure for the MVP.

### Pitfall 3: Nested `job_orders.*.file` validation without `required_if` produces confusing errors
**What goes wrong:** If the file field is marked `required` unconditionally, Type B rows (which never show a file input per UI-SPEC) will always fail validation. If it's marked fully optional with no conditional, a Type A row can be silently saved with no file at all, contradicting D-11's intent ("attaches the file at intake").
**Why it happens:** The Type A/B branch is a client-side UI conditional (`v-if` in the row Card); nothing enforces it server-side unless the Form Request rule explicitly mirrors the same condition.
**How to avoid:** Use `required_if:job_orders.*.type,type_a` (Laravel supports `required_if` referencing a sibling wildcard field within the same nested item — confirm exact wildcard-sibling-reference syntax against `search-docs`/official docs during planning, since this specific wildcard-to-wildcard reference pattern was not directly source-verified this session).
**Warning signs:** Type A job orders saved with no file; Type B rows rejected for "missing file" they were never shown.

### Pitfall 4: Storage disk choice quietly requires `storage:link` that hasn't been run
**What goes wrong:** If a future task accidentally uses the `public` disk (e.g., copy-pasting a pattern from a tutorial) instead of `local`, uploaded files will silently 404 in the browser because `php artisan storage:link` has never been run in this environment (confirmed: `public/storage` does not exist).
**Why it happens:** `public` disk URLs assume the symlink exists; Laravel doesn't error at write-time, only at read-time when the URL 404s.
**How to avoid:** Use the `local` disk explicitly (`$request->file('...')->store('job-orders', 'local')`) — which is also the correct *security* choice per D-11/TRACK-02 precedent, so there's no reason to reach for `public` here at all.
**Warning signs:** File uploads that appear to succeed (Inertia 200/redirect) but the stored path 404s when accessed directly.

### Pitfall 5: `RoleBoundaryTest`'s existing parameterized 403 test does NOT cover new Phase 2 routes
**What goes wrong:** Assuming the existing `tests/Feature/RoleBoundaryTest.php` "each role is blocked from every other role's portal" test already proves RBAC-02 compliance for the new customer/queue/job-order routes.
**Why it happens:** That test only iterates over each role's `*.dashboard` route (a fixed list built at the top of the file) — it has no knowledge of new routes added under `frontline-staff.*` beyond `dashboard`.
**How to avoid:** Write dedicated 403 tests for each new route (non-frontline-staff roles blocked; frontline-staff role allowed), following the same `actingAs()->get()/post()->assertForbidden()` pattern used elsewhere in the suite.
**Warning signs:** A new route accidentally left outside the `role:frontline_staff` middleware group passes CI with zero test failures, because nothing was testing it.

## Code Examples

### Migration: `queue_entries` with the composite unique index (Pattern 1's schema requirement)
```php
// Source: pattern matches existing migration conventions
// (database/migrations/2026_08_31_165342_create_audit_trail_table.php)
Schema::create('queue_entries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->restrictOnDelete();
    $table->date('queue_date');
    $table->unsignedInteger('queue_number');
    $table->string('status')->default('waiting');
    $table->timestamps();

    $table->unique(['queue_date', 'queue_number']);
});
```

### Feature test pattern for the counter (sequential correctness — NOT concurrency proof, see Pitfall 2/Pattern 1 caveat)
```php
// Source: pattern matches tests/Feature/Owner/UserManagementTest.php conventions
test('queue numbers increment sequentially within the same business day', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $first = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [['description' => 'Tarpaulin', 'type' => 'type_a']],
    ]);

    $second = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [['description' => 'Sticker', 'type' => 'type_b']],
    ]);

    expect(QueueEntry::orderBy('id')->pluck('queue_number')->all())->toBe([1, 2]);
});
```

### File upload test pattern (official `Storage::fake()` usage)
```php
// Source: laravel.com/docs/13.x/filesystem#testing
Storage::fake('local');

$response = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
    'customer_id' => $customer->id,
    'job_orders' => [[
        'description' => 'Tarpaulin, 3x5ft',
        'type' => 'type_a',
        'file' => UploadedFile::fake()->create('design.pdf', 500),
    ]],
]);

Storage::disk('local')->assertExists(JobOrder::first()->file_path);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|---------------|--------|
| Manual `setInterval` + `router.reload()` for polling | `usePoll()` composable | Introduced as part of Inertia v3 (this project is already on `^3.0.0`) | Less boilerplate, automatic cleanup/throttling — directly relevant to QUEUE-06 |
| `Inertia::lazy()`/`LazyProp` | `Inertia::optional()` | Removed in Inertia v3 (per project's own tech-stack notes) | Not directly used by this phase, but relevant if the internal queue list is later optimized with deferred props |
| Third-party retry-on-duplicate-key packages (`mpyw/laravel-retry-on-duplicate-key`) | `Model::createOrFirst()` (built into Eloquent core) | Laravel 10.29+ per community sources; confirmed present in installed 13.29.0 at `Builder.php:750` | Directly informs Pattern 1's alternative (`incrementOrCreate`) — no third-party package needed for the counter-table alternative if that path is chosen. |

**Deprecated/outdated:** Nothing in this phase's domain uses a genuinely deprecated Laravel/Inertia API — the codebase and its dependencies are current (Laravel 13.29.0 released within the last month, Inertia 3.3.1 released 2026-08-04, both essentially current as of research date).

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | The shop's operating timezone is Asia/Manila (Philippines), based on PayMongo/GCash/Maya being Philippines-specific payment rails mentioned in project constraints | Common Pitfalls #1 | If wrong, the recommended timezone-conversion fix targets the wrong offset; queue reset would still misfire, just at a different (still wrong) hour. **Must be confirmed with the user before implementation, not assumed silently.** |
| A2 | `job_orders.*.file` supports a wildcard-to-wildcard `required_if` reference (`required_if:job_orders.*.type,type_a`) exactly as written | Common Pitfalls #3, Code Examples | If the exact syntax doesn't resolve per-row as expected, either all-or-nothing validation triggers incorrectly (Type B rows wrongly required to have a file, or Type A rows wrongly allowed to skip it). Low risk (Laravel does document wildcard rule support generally) but the specific wildcard-to-wildcard sibling reference was not directly tool-verified this session — confirm via `search-docs`/official docs during planning. |
| A3 | Adding a `throttle:60,1` middleware to the new public route is desirable and won't conflict with the UI-SPEC's suggested ~5s poll cadence | Architecture Patterns, Pattern 5 | Low risk — 60 req/min comfortably covers one client polling every 5s (12 req/min); flagged only because it's a security addition not present in CONTEXT.md/UI-SPEC, so the planner should treat it as a suggestion, not a locked requirement. |

## Open Questions

1. **Should the daily queue counter live on `queue_entries` itself (no new table) or in a dedicated `queue_counters` table?**
   - What we know: Both are technically sound (Pattern 1 vs. the Alternatives Considered entry). The no-new-table approach relies on MySQL-specific InnoDB gap-locking behavior that the SQLite-based Pest suite cannot fully exercise; the counter-table approach is simpler and portable but adds a table beyond the approved 12-table ERD.
   - What's unclear: Whether an additional table needs the same kind of explicit approval `system_configurations` got in Phase 1, per CONTEXT.md's "follow the ERD; no deviation was discussed or approved here."
   - Recommendation: Default to the no-new-table approach (Pattern 1) unless the planner/user prefers to explicitly approve a 13th/14th table for simplicity — either is defensible, this just shouldn't be decided silently.

2. **Does D-15 ("job orders can still be added to a visit even after it's marked Done") require a second, separate UI/route in Phase 2 for adding job orders to an *existing* queue entry, beyond the initial combined-create form (D-14)?**
   - What we know: D-14 describes the *initial* intake (queue number + first batch of job orders in one save). D-15 explicitly contemplates adding *more* job orders later, including after Done — which implies a distinct "append job order(s) to an existing visit" action.
   - What's unclear: The UI-SPEC's Phase-Specific UI Notes describe the combined intake form and the internal queue list (with Call Next/Mark Done actions) but do not describe an "add another job order to an existing visit" affordance anywhere.
   - Recommendation: Confirm with the user/UI-SPEC-checker whether D-15 is (a) a Phase-2 feature requiring its own UI now, or (b) a data-model/business-rule statement only ("don't build any locking mechanism that would block this in the future") with the actual UI for it deferred to a later phase. Do not silently build or silently omit this — it changes the task list either way.

3. **Exact wildcard-to-wildcard Laravel validation syntax for `required_if` inside a nested array (`job_orders.*.file` required when `job_orders.*.type` is `type_a` for the *same* row index)**
   - What we know: Laravel supports wildcard rules (`job_orders.*.field`) and `required_if:other_field,value` generally.
   - What's unclear: Whether `required_if:job_orders.*.type,type_a` resolves per-index correctly, or whether it needs to be expressed differently (e.g., via a custom `Rule` closure per row, or `sometimes`+manual `Validator::after()` check).
   - Recommendation: Verify via `search-docs`/official Laravel validation docs during planning, before writing the Form Request — flagged here so it isn't discovered as a bug during implementation instead.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | All backend work | Yes | 8.4.3 | — |
| Node.js | Frontend build/dev | Yes | v22.12.0 | — |
| MySQL (production target) | `lockForUpdate()`/gap-locking correctness (Pattern 1) | Not directly checked (dev uses SQLite per `.env`) | — | Dev/test run on SQLite, which cannot exercise true concurrent-write races — documented as a test-coverage gap in Pitfall 2/Pattern 1, not a blocker for building the feature. |
| `storage:link` symlink | Only relevant if the `public` disk were used | No (not run) | — | N/A — this phase deliberately uses the private `local` disk instead, so no fallback needed. |
| GD / Imagick PHP extensions | Not needed this phase (DPI/format validation is Phase 3's `JOB-01`) | Both present (`php -m` confirms `gd`, `imagick`) | — | Carried forward as useful context for Phase 3 planning; STATE.md's existing blocker note about Laravel Cloud production availability of these extensions remains open and is out of scope for Phase 2. |

**Missing dependencies with no fallback:** None — everything Phase 2 needs is already installed and working in this environment.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 5.1.3 + pest-plugin-laravel 5.0.1 [VERIFIED: `composer show --direct`] |
| Config file | `phpunit.xml` (suites: Unit, Feature) + `tests/Pest.php` (binds `Tests\TestCase` + `RefreshDatabase` to all of `Feature`) |
| Quick run command | `php artisan test --compact --filter=<TestName>` or `vendor/bin/pest tests/Feature/FrontlineStaff/...` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| QUEUE-01 | Search returns partial/LIKE matches on name or contact | feature | `pestphp/pest tests/Feature/FrontlineStaff/CustomerSearchTest.php` | ❌ Wave 0 |
| QUEUE-02 | Register new customer; duplicate contact_number rejected at DB level | feature | `pestphp/pest tests/Feature/FrontlineStaff/CustomerRegistrationTest.php` | ❌ Wave 0 |
| QUEUE-03 | Queue numbers increment sequentially per business day, reset the next day | feature | `pestphp/pest tests/Feature/FrontlineStaff/QueueNumberGenerationTest.php` | ❌ Wave 0 |
| QUEUE-04 | Combined save creates 1 queue entry + N job orders atomically | feature | `pestphp/pest tests/Feature/FrontlineStaff/QueueEntryIntakeTest.php` | ❌ Wave 0 |
| QUEUE-05 | Type A requires file field validated per-row; Type B does not | feature | `pestphp/pest tests/Feature/FrontlineStaff/JobOrderTypeValidationTest.php` | ❌ Wave 0 |
| QUEUE-06 | Public route returns only `queue_number`/`status`, no customer data, no auth required | feature | `pestphp/pest tests/Feature/Public/QueueDisplayTest.php` | ❌ Wave 0 |
| RBAC-02 (regression) | Non-frontline-staff roles blocked (403) from all new routes | feature | `pestphp/pest tests/Feature/RoleBoundaryTest.php` (extend) or a new `Phase2RoleBoundaryTest.php` | ❌ Wave 0 (extension) |
| AUDIT-01 (regression) | Customer/QueueEntry/JobOrder mutations write audit rows | feature | `pestphp/pest tests/Feature/FrontlineStaff/AuditCoverageTest.php` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=<affected test class>`
- **Per wave merge:** `php artisan test --compact` (full Feature+Unit suite)
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/FrontlineStaff/CustomerSearchTest.php` — covers QUEUE-01
- [ ] `tests/Feature/FrontlineStaff/CustomerRegistrationTest.php` — covers QUEUE-02
- [ ] `tests/Feature/FrontlineStaff/QueueNumberGenerationTest.php` — covers QUEUE-03 (sequential correctness only; concurrency is a documented gap, see Pitfall 2)
- [ ] `tests/Feature/FrontlineStaff/QueueEntryIntakeTest.php` — covers QUEUE-04
- [ ] `tests/Feature/FrontlineStaff/JobOrderTypeValidationTest.php` — covers QUEUE-05
- [ ] `tests/Feature/Public/QueueDisplayTest.php` — covers QUEUE-06, including an explicit assertion that the response payload contains no customer name/contact field
- [ ] `database/factories/CustomerFactory.php`, `QueueEntryFactory.php`, `JobOrderFactory.php` — shared fixtures, none exist yet
- [ ] No framework install needed — Pest is already configured project-wide

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | No (new) | Already enforced globally by Fortify + `auth` middleware (Phase 1); nothing new here except the deliberately-unauthenticated QUEUE-06 route |
| V3 Session Management | No (new) | Unchanged from Phase 1 |
| V4 Access Control | Yes | `role:frontline_staff` middleware on all new authenticated routes (existing `EnsureUserHasRole` middleware, confirmed pattern); QUEUE-06's PII exclusion enforced by controller-side prop selection (`->select(['id','queue_number','status'])`), not client-side hiding |
| V5 Input Validation | Yes | Form Request + Validation Concern trait pattern for every new mutating endpoint; DB-level unique constraint on `customers.contact_number` (belt-and-suspenders with app-level validation, per D-02's explicit "at the database level" requirement) |
| V6 Cryptography | No | Not applicable — no secrets/tokens introduced this phase |
| V12 File Handling | Yes | `UploadedFile::store()` auto-generated filenames (never `getClientOriginalName()`); private `local` disk, never `public`; baseline `file` validation rule even though DPI/format/size thresholds are explicitly deferred to Phase 3 |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Path traversal / overwrite via crafted filename | Tampering | `store()`'s default hashed-name generation (never pass user-supplied filename to `storeAs()`) |
| PII leakage via over-fetched Inertia props on a public route | Information Disclosure | Controller explicitly selects only `id, queue_number, status` — never pass a full `QueueEntry` model (which has a `customer_id` FK and, if eager-loaded, a full `Customer` relation) to `Inertia::render()` on the public route |
| Race condition producing duplicate business identifiers (queue numbers) | Tampering / Repudiation | `lockForUpdate()` + DB transaction + composite unique index (defense in depth) — see Pattern 1 |
| Unauthenticated endpoint abuse (scraping/DoS via repeated polling) | Denial of Service | `throttle` middleware on the one new public route (Assumption A3 — offered as a recommendation, not a locked requirement) |
| Mass-assignment of unintended attributes on Customer/QueueEntry/JobOrder | Tampering | `#[Fillable([...])]` attribute explicitly whitelisting fields, matching `User`'s existing convention — never mass-assign `status`/`queue_number` from request input directly |

## Sources

### Primary (HIGH confidence)
- Direct read of installed `vendor/laravel/framework` 13.29.0 source: `Illuminate/Database/Eloquent/Builder.php` (`createOrFirst`, `firstOrCreate`, `incrementOrCreate` at lines 750/732/785), `Illuminate/Database/Eloquent/Model.php` (`increment`/`incrementOrDecrement`), `Illuminate/Database/Query/Builder.php` (`incrementEach` generating raw `column + amount` SQL, line ~4511)
- Direct read of this repository's existing code: `app/Models/User.php`, `app/Observers/AuditObserver.php`, `app/Support/AuditLogger.php`, `routes/web.php`, `routes/portals.php`, `bootstrap/app.php`, `config/filesystems.php`, `config/database.php`, `config/app.php`, `database/migrations/*`, `resources/js/app.ts`, `resources/js/pages/owner/AuditTrail.vue`, `tests/Feature/RoleBoundaryTest.php`, `tests/Pest.php`
- [CITED: laravel.com/docs/13.x/filesystem] — File Storage: `store()`/`storeAs()` auto-naming, `getClientOriginalName()` "unsafe" warning, local vs. public disk distinction, `Storage::fake()` testing pattern
- [CITED: inertiajs.com/docs/v3/data-props/polling] — `usePoll()` full API (interval/options/config, `mode`, `keepAlive`, `autoStart`, returned `start`/`stop`/`polling`)
- [CITED: inertiajs.com/docs/v3/the-basics/file-uploads] — automatic FormData conversion including nested files, `forceFormData`, PUT/PATCH method-spoofing caveat
- [CITED: dev.mysql.com/doc/refman/8.4/en/innodb-next-key-locking.html] — next-key/gap locking "locks the nonexistence" mechanism used in Pattern 1

### Secondary (MEDIUM confidence)
- Percona Engineering Blog, "InnoDB's Gap Locks" — corroborates the MySQL official docs' gap-locking behavior with a practical example
- laravel.com/docs/13.x/eloquent#retrieving-or-creating-models — documents `firstOrCreate` officially (does not document `incrementOrCreate`, which was verified instead by direct vendor source read — flagged as undocumented-but-present)

### Tertiary (LOW confidence — flagged, not asserted as fact)
- General WebSearch results on "Laravel lockForUpdate race condition" and "Laravel unique constraint retry" (Medium.com posts, Backpack blog, etc.) — used only to triangulate that the pattern is a known, common problem with known common solutions; the actual technical claims in this document are backed by the primary/secondary sources above, not these blog posts

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — every library is already installed and version-confirmed via `composer show`/`package.json`; no new dependencies
- Architecture (models/migrations/controllers/routing): HIGH — directly extrapolated from Phase 1's verified, working conventions in this exact codebase
- Queue counter concurrency mechanics: MEDIUM-HIGH — the MySQL InnoDB locking mechanism is officially documented and cross-verified by a second source, but the SQLite-based test suite cannot itself prove the guarantee (documented as an explicit gap, not glossed over)
- Timezone/business-day boundary: LOW — flagged explicitly as an unconfirmed assumption requiring user input before implementation (Assumption A1)
- Frontend polling/file-upload mechanics: HIGH — verified against official Inertia v3 documentation fetched this session
- Pitfalls: HIGH — each pitfall traces to a specific verified fact (UTC timezone config, missing storage:link, RoleBoundaryTest's actual scope) rather than generic Laravel-app pitfalls

**Research date:** 2026-09-01
**Valid until:** 30 days (stable stack, no fast-moving dependencies; re-verify if `laravel/framework` or `@inertiajs/vue3` receive a major version bump before this phase is planned)
