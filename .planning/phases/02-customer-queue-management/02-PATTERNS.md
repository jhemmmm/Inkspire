# Phase 2: Customer & Queue Management - Pattern Map

**Mapped:** 2026-09-01
**Files analyzed:** 24 (new) + 2 (modified)
**Analogs found:** 24 / 24 (all files have at least a role-match analog; Phase 2 is greenfield so no file has an _exact_ prior-domain analog — all are extrapolated from Phase 1's `User`/`SystemConfiguration`/`AuditLog` conventions, per CONTEXT.md and RESEARCH.md)

## File Classification

| New/Modified File                                                    | Role               | Data Flow                                     | Closest Analog                                                                                                                                     | Match Quality                      |
| -------------------------------------------------------------------- | ------------------ | --------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------- |
| `app/Enums/QueueStatus.php`                                          | enum/model-support | transform                                     | `app/Enums/UserRole.php`                                                                                                                           | exact (pattern)                    |
| `app/Enums/JobOrderType.php`                                         | enum/model-support | transform                                     | `app/Enums/UserRole.php`                                                                                                                           | exact (pattern)                    |
| `app/Enums/JobOrderStatus.php`                                       | enum/model-support | transform                                     | `app/Enums/UserRole.php`                                                                                                                           | exact (pattern)                    |
| `app/Models/Customer.php`                                            | model              | CRUD                                          | `app/Models/User.php`                                                                                                                              | exact (attribute/observer pattern) |
| `app/Models/QueueEntry.php`                                          | model              | CRUD + concurrency-guarded counter            | `app/Models/SystemConfiguration.php` (static helper methods) + `app/Models/User.php` (attributes)                                                  | role-match                         |
| `app/Models/JobOrder.php`                                            | model              | CRUD + file-I/O                               | `app/Models/User.php`                                                                                                                              | role-match                         |
| `database/migrations/*_create_customers_table.php`                   | migration          | schema                                        | `database/migrations/2026_08_31_171450_create_system_configurations_table.php`                                                                     | role-match                         |
| `database/migrations/*_create_queue_entries_table.php`               | migration          | schema                                        | `database/migrations/2026_08_31_165342_create_audit_trail_table.php` (FK + index conventions)                                                      | role-match                         |
| `database/migrations/*_create_job_orders_table.php`                  | migration          | schema                                        | same as above                                                                                                                                      | role-match                         |
| `database/factories/CustomerFactory.php`                             | factory            | test-data                                     | `database/factories/UserFactory.php`                                                                                                               | role-match                         |
| `database/factories/QueueEntryFactory.php`                           | factory            | test-data                                     | `database/factories/UserFactory.php` (state methods for enum cases, `afterCreating` for non-fillable columns)                                      | role-match                         |
| `database/factories/JobOrderFactory.php`                             | factory            | test-data                                     | `database/factories/UserFactory.php`                                                                                                               | role-match                         |
| `app/Concerns/CustomerValidationRules.php`                           | validation-concern | transform                                     | `app/Concerns/ProfileValidationRules.php`                                                                                                          | exact (unique-with-ignore pattern) |
| `app/Concerns/JobOrderValidationRules.php`                           | validation-concern | transform                                     | `app/Concerns/SystemConfigValidationRules.php` (type-driven rule branching)                                                                        | role-match                         |
| `app/Http/Requests/FrontlineStaff/SearchCustomersRequest.php`        | form-request       | request-response                              | `app/Http/Requests/Owner/FilterAuditTrailRequest.php`                                                                                              | exact                              |
| `app/Http/Requests/FrontlineStaff/StoreCustomerRequest.php`          | form-request       | CRUD                                          | `app/Http/Requests/Settings/ProfileUpdateRequest.php`                                                                                              | exact                              |
| `app/Http/Requests/FrontlineStaff/StoreQueueEntryRequest.php`        | form-request       | CRUD (nested array + file)                    | `app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php` (trait delegation) — no existing nested-array/file analog exists                    | role-match                         |
| `app/Http/Requests/FrontlineStaff/UpdateQueueEntryStatusRequest.php` | form-request       | CRUD                                          | `app/Http/Requests/Owner/DeactivateUserRequest.php` (authorize()-only, empty rules)                                                                | exact                              |
| `app/Http/Requests/FrontlineStaff/AddJobOrderRequest.php`            | form-request       | CRUD (file)                                   | `app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php`                                                                                     | role-match                         |
| `app/Http/Controllers/FrontlineStaff/CustomerController.php`         | controller         | request-response + CRUD                       | `app/Http/Controllers/Owner/UserManagementController.php`                                                                                          | exact                              |
| `app/Http/Controllers/FrontlineStaff/QueueEntryController.php`       | controller         | CRUD (transactional, multi-row)               | `app/Http/Controllers/Owner/AuditTrailController.php` (index/filter) + `SystemConfigurationController.php` (update + side-effect)                  | role-match                         |
| `app/Http/Controllers/Public/QueueDisplayController.php`             | controller         | request-response (unauthenticated, polling)   | `app/Http/Controllers/Owner/AuditTrailController.php` (index, prop-shaping via `->select()`)                                                       | role-match                         |
| `routes/portals.php` (modify — extend `frontline-staff` group)       | route              | request-response                              | `routes/owner.php`                                                                                                                                 | exact                              |
| `routes/web.php` (modify — add public `queue-display` route)         | route              | request-response                              | `Route::inertia('/', 'Welcome')->name('home');` in `routes/web.php`                                                                                | exact                              |
| `resources/js/pages/frontline-staff/Dashboard.vue` (replaced)        | component/page     | request-response                              | current file itself (being replaced) + `resources/js/pages/owner/AuditTrail.vue` for real content pattern                                          | exact                              |
| `resources/js/pages/frontline-staff/NewVisit.vue`                    | component/page     | CRUD (multi-step: search → register → intake) | `resources/js/pages/owner/AuditTrail.vue` (search/filter + Table) + `resources/js/pages/settings/Profile.vue` (Form + InputError)                  | role-match                         |
| `resources/js/pages/frontline-staff/QueueList.vue`                   | component/page     | CRUD (status transitions + nested dialog)     | `resources/js/pages/owner/UserManagement.vue` (per-row action buttons via `Form`) + `resources/js/components/DeleteUser.vue` (Dialog-wrapped Form) | role-match                         |
| `resources/js/pages/public/QueueDisplay.vue`                         | component/page     | streaming/polling (client-side)               | `resources/js/pages/Welcome.vue` (layout opt-out precedent)                                                                                        | exact (layout-opt-out mechanism)   |
| `resources/js/config/nav/frontline-staff.ts`                         | config             | transform                                     | `resources/js/config/nav/owner.ts`                                                                                                                 | exact                              |
| `resources/js/app.ts` (modify — add `public/` layout-switch case)    | config             | transform                                     | existing `case name === 'Welcome': return null;` in same file                                                                                      | exact                              |

## Pattern Assignments

### `app/Enums/QueueStatus.php`, `app/Enums/JobOrderType.php`, `app/Enums/JobOrderStatus.php` (enum, transform)

**Analog:** `app/Enums/UserRole.php`

**Full pattern** (`app/Enums/UserRole.php:1-14`):

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case FrontlineStaff = 'frontline_staff';
    case Artist = 'artist';
    case Cashier = 'cashier';
    case ProductionStaff = 'production_staff';
    case AccountingStaff = 'accounting_staff';
```

Apply verbatim: string-backed enum, TitleCase case names, snake_case string values. `QueueStatus` gets cases `Waiting|Serving|Done`; `JobOrderType` gets `TypeA|TypeB`; `JobOrderStatus` gets a single `Intake` case for now (per D-13 — later phases add `ForProduction`, `Printing`, etc., with zero migration since the column is `string`, not native `enum` — see Migration pattern below). No `portalRoute()`-style helper method is needed unless a controller needs one; `UserRole::portalRoute()` shown above is a _method-on-enum_ precedent to reuse only if similar per-case branching becomes necessary (e.g., a `label()` method for the badge/copy text in Vue could be considered, but is optional — UI-SPEC's copy mapping can also live client-side).

---

### `app/Models/Customer.php`, `app/Models/QueueEntry.php`, `app/Models/JobOrder.php` (model, CRUD)

**Analog:** `app/Models/User.php` (attribute pattern) + `app/Models/SystemConfiguration.php` (casts + static helpers)

**Imports + class-attribute pattern** (`app/Models/User.php:1-14, 36-38`):

```php
namespace App\Models;

use App\Enums\UserRole;
use App\Observers\AuditObserver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
...

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[ObservedBy(AuditObserver::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
```

Apply to all three new models: `#[Fillable([...])]` whitelisting exactly the columns each request may set (never `status`/`queue_number` — those are server-computed, per RESEARCH.md's Security Domain), `#[ObservedBy(AuditObserver::class)]` for automatic audit coverage (confirmed this is a class attribute, **not** a service-provider registration — `grep` confirms `AuditObserver` is only referenced via this attribute on `User` and `SystemConfiguration`), and `use HasFactory;` with the matching Factory.

**Casts pattern** (`app/Models/User.php:49-59`, `app/Models/SystemConfiguration.php:32-37`):

```php
protected function casts(): array
{
    return [
        'role' => UserRole::class,
        'is_active' => 'boolean',
        ...
    ];
}
```

`QueueEntry::casts()` → `['status' => QueueStatus::class, 'queue_date' => 'date']`. `JobOrder::casts()` → `['type' => JobOrderType::class, 'status' => JobOrderStatus::class]`.

**Static domain-logic helper pattern** (`app/Models/SystemConfiguration.php:90-111`, precedent for encapsulating a business calculation on the model rather than in the controller):

```php
public static function invalidate(string $key): void
{
    Cache::forget("config.{$key}");
}

private static function resolve(string $key): mixed
{
    return Cache::rememberForever(
        "config.{$key}",
        fn (): mixed => static::query()->where('key', $key)->first()?->value,
    );
}
```

Use this exact shape for `QueueEntry::nextForBusinessDay(CarbonImmutable $businessDay): int` (RESEARCH.md Pattern 1) — a `public static function` on the model, keeping the `DB::transaction()`+`lockForUpdate()` concurrency logic out of the controller.

**Relationships:** No existing `belongsTo`/`hasMany` example exists in this codebase yet (only `User` exists as a model in Phase 1). Use standard Eloquent conventions: `QueueEntry::customer(): BelongsTo` / `QueueEntry::jobOrders(): HasMany`; `JobOrder::queueEntry(): BelongsTo`; `Customer::queueEntries(): HasMany`. No analog needed — this is textbook Eloquent, not a project-specific convention.

---

### Migrations (`create_customers_table`, `create_queue_entries_table`, `create_job_orders_table`)

**Analog:** `database/migrations/2026_08_31_171450_create_system_configurations_table.php` (simple table) + `database/migrations/2026_08_31_165342_create_audit_trail_table.php` (FK conventions)

**Full pattern** (`database/migrations/2026_08_31_171450_create_system_configurations_table.php:1-33`):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            ...
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_configurations');
    }
};
```

**FK convention** (`database/migrations/2026_08_31_165342_create_audit_trail_table.php:16`):

```php
$table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
```

`queue_entries.customer_id` should use `->constrained()->restrictOnDelete()` (per RESEARCH.md's Code Examples section — customers are never hard-deleted per project constraints, so `restrictOnDelete` is defensive but `nullOnDelete`/`cascadeOnDelete` would never actually fire; `restrictOnDelete` is still the semantically correct choice). `job_orders.queue_entry_id` likewise `->constrained()->restrictOnDelete()` (or `cascadeOnDelete()` if job orders should never outlive their visit — follow RESEARCH.md's exact recommendation, not a new decision here).

**Status/type column convention** (`database/migrations/2026_08_31_165341_add_rbac_and_lockout_columns_to_users_table.php:16`):

```php
$table->string('role')->default(UserRole::Owner->value)->after('password');
```

Never `$table->enum(...)`. Apply identically: `$table->string('status')->default(QueueStatus::Waiting->value);` on `queue_entries`, `$table->string('type')` and `$table->string('status')->default(JobOrderStatus::Intake->value)` on `job_orders`.

**Composite unique index for the counter** (RESEARCH.md Code Examples, D-17):

```php
$table->date('queue_date');
$table->unsignedInteger('queue_number');
$table->unique(['queue_date', 'queue_number']);
```

**Unique-at-DB-level for D-02:**

```php
$table->string('contact_number')->unique();
```

---

### `database/factories/CustomerFactory.php`, `QueueEntryFactory.php`, `JobOrderFactory.php` (factory, test-data)

**Analog:** `database/factories/UserFactory.php`

**Definition + state-method pattern** (`database/factories/UserFactory.php:14-36, 71-76`):

```php
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            ...
        ];
    }

    public function frontlineStaff(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::FrontlineStaff->value,
        ]);
    }
```

`CustomerFactory::definition()` → `name`, `contact_number` (use `fake()->unique()->phoneNumber()` or similar, must satisfy D-02's uniqueness), `email`, `address`. `QueueEntryFactory::definition()` → `customer_id` (factory relationship), `queue_date` (`today()`), `queue_number` (sequential or `fake()->numberBetween(1,999)`), `status` default `waiting`. `JobOrderFactory::definition()` → `queue_entry_id`, `description`, `type`, `status` default `intake`, nullable `file_path`.

**`afterCreating()` for non-fillable columns pattern** (`database/factories/UserFactory.php:118-131`, directly relevant if `status`/`queue_number` end up outside the model's `#[Fillable]` list):

```php
public function locked(): static
{
    return $this->afterCreating(fn (User $user) => $user->forceFill([
        'failed_login_attempts' => 5,
        'locked_until' => now()->addMinutes(15),
    ])->save());
}
```

Use this exact shape for factory states like `QueueEntryFactory::serving()` / `::done()` if `status` is excluded from `#[Fillable]` (mirrors how `User`'s `locked()`/`deactivated()` states work around the same constraint).

---

### `app/Concerns/CustomerValidationRules.php` (validation-concern, transform)

**Analog:** `app/Concerns/ProfileValidationRules.php`

**Full pattern — unique-with-ignore** (`app/Concerns/ProfileValidationRules.php:1-51`):

```php
namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    protected function emailRules(?int $userId = null): array
    {
        return [
            'required', 'string', 'email', 'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
```

Apply identically for `CustomerValidationRules::customerRules(): array` returning `name`, `contact_number` (`Rule::unique(Customer::class)` — D-02, no `ignore()` needed since Phase 2 has no customer-edit flow), `email`, `address` rules. Method-per-field naming convention (`nameRules()`, `emailRules()`) should be followed for `contactNumberRules()`, `addressRules()` if broken out similarly.

---

### `app/Concerns/JobOrderValidationRules.php` (validation-concern, transform)

**Analog:** `app/Concerns/SystemConfigValidationRules.php` (type-driven `match()` branching)

**Full pattern** (`app/Concerns/SystemConfigValidationRules.php:1-28`):

```php
trait SystemConfigValidationRules
{
    protected function valueRules(string $type): array
    {
        return match ($type) {
            'integer' => ['value' => ['required', 'integer', 'min:0']],
            'decimal' => ['value' => ['required', 'numeric', 'min:0']],
            'boolean' => ['value' => ['required', 'boolean']],
            'array' => ['value' => ['required', 'array'], 'value.*' => ['string']],
            default => ['value' => ['required', 'string', 'max:255']],
        };
    }
}
```

Use this `match()`-branching shape for the Type A/B conditional file rule (Pitfall 3 in RESEARCH.md): a `jobOrderRules(): array` method returning the nested-array rule set including `'job_orders.*.file' => ['required_if:job_orders.*.type,type_a', 'nullable', 'file']` — verify the wildcard-to-wildcard `required_if` syntax against `search-docs` before finalizing (RESEARCH.md Open Question #3/Assumption A2).

---

### `app/Http/Requests/FrontlineStaff/SearchCustomersRequest.php` (form-request, request-response)

**Analog:** `app/Http/Requests/Owner/FilterAuditTrailRequest.php`

**Full pattern** (`app/Http/Requests/Owner/FilterAuditTrailRequest.php:1-24`):

```php
class FilterAuditTrailRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user' => ['nullable', 'integer'],
            'action' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }
}
```

Apply identically: `SearchCustomersRequest::rules()` → `['q' => ['nullable', 'string', 'max:255']]` (no `authorize()` override needed — inherits default `true`, matching this analog's omission of `authorize()`).

---

### `app/Http/Requests/FrontlineStaff/StoreCustomerRequest.php` (form-request, CRUD)

**Analog:** `app/Http/Requests/Settings/ProfileUpdateRequest.php`

**Full pattern** (`app/Http/Requests/Settings/ProfileUpdateRequest.php:1-22`):

```php
class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    public function rules(): array
    {
        return $this->profileRules($this->user()->id);
    }
}
```

`StoreCustomerRequest` → `use CustomerValidationRules; public function rules(): array { return $this->customerRules(); }` (no id to ignore since this is always a create, unlike Profile's update-with-ignore case).

---

### `app/Http/Requests/FrontlineStaff/UpdateQueueEntryStatusRequest.php` (form-request, CRUD)

**Analog:** `app/Http/Requests/Owner/DeactivateUserRequest.php`

**Full pattern — `authorize()`-driven, empty `rules()`** (`app/Http/Requests/Owner/DeactivateUserRequest.php:1-29`):

```php
class DeactivateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deactivate', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            //
        ];
    }
}
```

Apply for a body-less PATCH like "Call Next"/"Mark Done" if no request body is needed (the action itself, e.g. `->status()` route param or a dedicated `next`/`done` endpoint, drives the transition). If a `status` field is submitted in the body instead, add `'status' => ['required', Rule::enum(QueueStatus::class)]` to `rules()`.

---

### `app/Http/Requests/FrontlineStaff/StoreQueueEntryRequest.php`, `AddJobOrderRequest.php` (form-request, CRUD with nested array + file)

**No existing analog with nested-array or file validation exists in this codebase** — flagged below in "No Analog Found." Closest structural analog for trait delegation is `app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php:1-22`:

```php
class UpdateSystemConfigurationRequest extends FormRequest
{
    use SystemConfigValidationRules;

    public function rules(): array
    {
        return $this->valueRules($this->route('configuration')->type);
    }
}
```

Follow the same `use {Concern}; public function rules(): array { return $this->{method}(); }` structure. The actual nested-array + `required_if` rule content should follow RESEARCH.md's Code Examples section (Pattern 4) verbatim:

```php
public function rules(): array
{
    return [
        'customer_id' => ['required', 'integer', 'exists:customers,id'],
        'job_orders' => ['required', 'array', 'min:1'],
        'job_orders.*.description' => ['required', 'string', 'max:255'],
        'job_orders.*.type' => ['required', Rule::in(['type_a', 'type_b'])],
        'job_orders.*.file' => ['required_if:job_orders.*.type,type_a', 'nullable', 'file'],
    ];
}
```

---

### `app/Http/Controllers/FrontlineStaff/CustomerController.php` (controller, request-response + CRUD)

**Analog:** `app/Http/Controllers/Owner/UserManagementController.php`

**Imports + index() prop-shaping pattern** (`app/Http/Controllers/Owner/UserManagementController.php:1-27`):

```php
namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\DeactivateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('owner/UserManagement', [
            'users' => User::query()
                ->select(['id', 'name', 'email', 'role', 'is_active'])
                ->orderBy('name')
                ->get(),
        ]);
    }
```

`CustomerController::index()` (search, QUEUE-01) should follow the `AuditTrailController::index()` filter-query shape instead (below) since it takes a query param, not this plain `::get()`. `CustomerController::store()` (QUEUE-02, D-02/D-04) follows this file's mutation shape:

```php
public function deactivate(DeactivateUserRequest $request, User $user): RedirectResponse
{
    $user->forceFill(['is_active' => false])->save();

    Inertia::flash('toast', ['type' => 'success', 'message' => __(":name's account has been deactivated.", ['name' => $user->name])]);

    return back();
}
```

→ `store(StoreCustomerRequest $request): RedirectResponse { $customer = Customer::create($request->validated()); Inertia::flash('toast', [...]); return back(); }` (or `to_route()` to continue the intake flow — UI-SPEC keeps this on one screen, so `back()` with flashed state is likely correct; confirm against UI-SPEC's "one continuous screen" note during planning).

**Filter/search query pattern** (`app/Http/Controllers/Owner/AuditTrailController.php:17-35`):

```php
public function index(FilterAuditTrailRequest $request): Response
{
    $entries = AuditLog::query()
        ->with('user:id,name,email,role')
        ->latest('created_at')
        ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
        ...
        ->paginate(25)
        ->withQueryString();

    return Inertia::render('owner/AuditTrail', [
        'entries' => $entries,
        'filters' => $request->only(['user', 'action', 'from', 'to']),
        ...
    ]);
}
```

`CustomerController::index()` (search) → `Customer::query()->when($request->filled('q'), fn ($q) => $q->where(fn ($sub) => $sub->where('name', 'like', "%{$request->string('q')}%")->orWhere('contact_number', 'like', "%{$request->string('q')}%")))->get()` — D-03's partial/LIKE match, D-04's "must search first" is enforced client-side by not rendering "Register New" until this returns empty, not by a server-side gate.

---

### `app/Http/Controllers/FrontlineStaff/QueueEntryController.php` (controller, CRUD, transactional multi-row)

**Analog:** `app/Http/Controllers/Owner/SystemConfigurationController.php` (mutation + side-effect pattern) + RESEARCH.md's Architecture Patterns diagram for the transaction shape

**Mutation + side-effect pattern** (`app/Http/Controllers/Owner/SystemConfigurationController.php:29-43`):

```php
public function update(UpdateSystemConfigurationRequest $request, SystemConfiguration $configuration): RedirectResponse
{
    $configuration->update(['value' => $request->validated('value')]);

    SystemConfiguration::invalidate($configuration->key);

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Configuration updated.')]);

    return back();
}
```

`QueueEntryController::store()` (D-14, combined intake) mirrors this "validated mutation + side-effect + flash + `back()`" shape, but wraps in `DB::transaction()` per RESEARCH.md Pattern 1:

```php
public function store(StoreQueueEntryRequest $request): RedirectResponse
{
    $queueEntry = DB::transaction(function () use ($request) {
        $businessDay = CarbonImmutable::now('Asia/Manila')->toDateString(); // D-16, scoped locally — see below
        $number = QueueEntry::nextForBusinessDay($businessDay);

        $entry = QueueEntry::create([
            'customer_id' => $request->validated('customer_id'),
            'queue_date' => $businessDay,
            'queue_number' => $number,
            'status' => QueueStatus::Waiting,
        ]);

        foreach ($request->validated('job_orders') as $index => $row) {
            $entry->jobOrders()->create([
                'description' => $row['description'],
                'type' => $row['type'],
                'status' => JobOrderStatus::Intake,
                'file_path' => $request->file("job_orders.{$index}.file")?->store('job-orders', 'local'),
            ]);
        }

        return $entry;
    });

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Queue number :number created with :count job order(s).', ['number' => $queueEntry->queue_number, 'count' => $queueEntry->jobOrders()->count()])]);

    return back();
}
```

`QueueEntryController::index()` (internal queue list, D-05/D-08) follows `AuditTrailController::index()`'s shape (query today's entries, no pagination needed at shop scale — confirm with UI-SPEC). `QueueEntryController::updateStatus()` follows the `UserManagementController::deactivate()`/`reactivate()` two-tiny-mutation-methods shape.

**D-16 timezone note (apply narrowly, do not touch `config('app.timezone')`):**

```php
// Source: CONTEXT.md D-16 — scoped only to queue-number generation
$businessDay = now()->timezone('Asia/Manila')->toDateString();
```

---

### `app/Http/Controllers/Public/QueueDisplayController.php` (controller, request-response, unauthenticated)

**Analog:** `app/Http/Controllers/Owner/AuditTrailController.php` (prop-shaping via explicit `->select()`)

**PII-exclusion-by-select pattern**, adapted from `AuditTrailController.php:19-27` (`->select()`/`->when()` chain shape) and RESEARCH.md's Architecture diagram:

```php
public function index(): Response
{
    $businessDay = now()->timezone('Asia/Manila')->toDateString();

    return Inertia::render('public/QueueDisplay', [
        'queueEntries' => QueueEntry::query()
            ->where('queue_date', $businessDay)
            ->select(['id', 'queue_number', 'status'])
            ->orderBy('queue_number')
            ->get(),
    ]);
}
```

Critical: never pass a full `QueueEntry` model or an eager-loaded `customer` relation to `Inertia::render()` here — D-09's no-PII rule is enforced by this explicit column list, mirroring how `UserManagementController::index()` already explicitly lists `['id', 'name', 'email', 'role', 'is_active']` rather than passing whole `User` models.

---

### `routes/portals.php` (modify) and `routes/web.php` (modify)

**Analog — extending an existing role group** (`routes/owner.php:1-14`, showing the target shape `portals.php`'s `frontline-staff` group should grow into):

```php
Route::middleware(['auth', 'role:owner,admin'])->prefix('owner')->name('owner.')->group(function () {
    Route::inertia('dashboard', 'owner/Dashboard')->name('dashboard');
    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('users.deactivate');
    ...
});
```

Current `frontline-staff` group (`routes/portals.php:5-7`) is only:

```php
Route::middleware(['auth', 'role:frontline_staff'])->prefix('frontline-staff')->name('frontline-staff.')->group(function () {
    Route::inertia('dashboard', 'frontline-staff/Dashboard')->name('dashboard');
});
```

Extend with `customers.index`/`customers.store`, `queue-entries.index`/`.store`/`.status`/`.job-orders.store` etc., named consistently with `owner.php`'s `{resource}.{action}` convention.

**Public route registration pattern** (`routes/web.php:5`):

```php
Route::inertia('/', 'Welcome')->name('home');
```

Add the QUEUE-06 route at the same top-level, outside any `auth` group, per RESEARCH.md Pattern 5:

```php
Route::get('queue-display', [QueueDisplayController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('queue-display');
```

---

### `resources/js/config/nav/frontline-staff.ts` (config, transform)

**Analog:** `resources/js/config/nav/owner.ts` (full file, 29 lines)

```typescript
import { LayoutGrid, ScrollText, Settings, Users } from '@lucide/vue';
import { dashboard } from '@/routes/owner';
import { index as auditTrailIndex } from '@/routes/owner/audit-trail';
import { edit as systemConfigurationEditRoute } from '@/routes/owner/system-configuration';
import { index as usersIndex } from '@/routes/owner/users';
import type { NavItem } from '@/types';

export const ownerNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'User Management', href: usersIndex(), icon: Users },
    { title: 'Audit Trail', href: auditTrailIndex(), icon: ScrollText },
    {
        title: 'System Configuration',
        href: systemConfigurationEditRoute(),
        icon: Settings,
    },
];
```

Copy this exact shape for `frontlineStaffNavItems`: `Dashboard` (existing), `New Visit` (→ `NewVisit.vue`), `Queue` (→ `QueueList.vue`), each importing its Wayfinder-generated route helper from `@/routes/frontline-staff/...`, not hardcoded strings. Pick `@lucide/vue` icons matching semantic intent (e.g. `UserPlus` for New Visit, `ListOrdered` or `Users` for Queue).

---

### `resources/js/app.ts` (modify — add `public/` layout-switch case)

**Analog — existing layout-opt-out precedent** (`resources/js/app.ts:12-23`):

```typescript
layout: (name) => {
    switch (true) {
        case name === 'Welcome':
            return null;
        case name.startsWith('auth/'):
        case name.startsWith('errors/'):
            return AuthLayout;
        case name.startsWith('settings/'):
            return [AppLayout, SettingsLayout];
        default:
            return AppLayout;
    }
},
```

Add one new `case` (per UI-SPEC's explicit instruction) directly alongside the existing `'Welcome'` case:

```typescript
case name.startsWith('public/'):
    return null;
```

Place `resources/js/pages/public/QueueDisplay.vue` under this namespace so it inherits zero chrome, exactly like `Welcome.vue` does today.

---

### `resources/js/pages/frontline-staff/NewVisit.vue` (component/page, CRUD multi-step)

**Analog 1 — Table + search-as-filter** (`resources/js/pages/owner/AuditTrail.vue`, full imports at lines 1-33, `visit()` function at lines 124-136):

```typescript
import { Head, router } from '@inertiajs/vue3';
...
import {
    Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';

function visit(page?: number): void {
    router.get(
        auditTrailIndex.url(),
        { ...(selectedUser.value !== ALL ? { user: selectedUser.value } : {}), ... },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
```

Use `Table`/`TableEmpty` for search results (not a raw `<table>` like the older `UserManagement.vue`), and `router.get(url, params, {preserveState:true, preserveScroll:true, replace:true})` for the customer search-as-you-type/submit interaction (QUEUE-01, D-03).

**Analog 2 — `Form` + `InputError` for the registration step** (`resources/js/pages/settings/Profile.vue:40-57`):

```vue
<Form
    v-bind="ProfileController.update.form()"
    class="space-y-6"
    v-slot="{ errors, processing }"
>
    <div class="grid gap-2">
        <Label for="name">Name</Label>
        <Input id="name" name="name" :default-value="user.name" required />
        <InputError class="mt-2" :message="errors.name" />
    </div>
    ...
    <Button :disabled="processing" data-test="update-profile-button">Save</Button>
</Form>
```

Apply this exact `Form`/`InputError`/`:disabled="processing"`/`data-test` shape for the "Register New Customer" step, binding to `CustomerController.store.form()` (Wayfinder-generated). Field-level uniqueness error (D-02's duplicate contact number) surfaces via `errors.contact_number` through the same `InputError` component per UI-SPEC.

**Repeatable job-order rows (no in-repo analog — new territory):** follow RESEARCH.md's Code Examples `useForm` array pattern verbatim:

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    customer_id: props.customer.id,
    job_orders: [
        { description: '', type: 'type_a', file: null as File | null },
    ],
});

function addRow(): void {
    form.job_orders.push({ description: '', type: 'type_a', file: null });
}
function removeRow(index: number): void {
    form.job_orders.splice(index, 1);
}
</script>
```

---

### `resources/js/pages/frontline-staff/QueueList.vue` (component/page, CRUD status transitions + nested dialog)

**Analog 1 — per-row `Form`-bound action buttons** (`resources/js/pages/owner/UserManagement.vue:110-129, 133-153`):

```vue
<Form
    v-bind="UserManagementController.deactivate.form(user.id)"
    :options="{ preserveScroll: true }"
    v-slot="{ processing }"
>
    <Button type="submit" variant="destructive" :disabled="processing" :data-test="`deactivate-user-${user.id}-button`">
        Deactivate Account
    </Button>
</Form>
```

Use this exact per-row `Form v-bind="{Controller}.{action}.form(entry.id)"` shape for "Call Next" (Waiting→Serving) and "Mark Done" (Serving→Done) buttons, contextually shown per status per UI-SPEC.

**Analog 2 — Dialog-wrapped Form for "Add Job Order" per row** (`resources/js/components/DeleteUser.vue:40-110`, full Dialog+Form composition):

```vue
<Dialog>
    <DialogTrigger as-child>
        <Button variant="destructive" data-test="delete-user-button">Delete account</Button>
    </DialogTrigger>
    <DialogContent>
        <Form v-bind="ProfileController.destroy.form()" reset-on-success :options="{ preserveScroll: true }" v-slot="{ errors, processing, reset, clearErrors }">
            <DialogHeader>...</DialogHeader>
            <div class="grid gap-2">
                <Label for="password" class="sr-only">Password</Label>
                <PasswordInput id="password" name="password" />
                <InputError :message="errors.password" />
            </div>
            <DialogFooter class="gap-2">
                <DialogClose as-child><Button variant="secondary" @click="() => { clearErrors(); reset(); }">Cancel</Button></DialogClose>
                <Button type="submit" :disabled="processing" data-test="confirm-delete-user-button">Delete account</Button>
            </DialogFooter>
        </Form>
    </DialogContent>
</Dialog>
```

Apply this `Dialog > DialogTrigger + DialogContent > Form > DialogFooter` composition for the "Add Job Order" icon-button-triggered dialog (D-15/D-18), reusing the same description/RadioGroup/file-input fields as the combined intake form's row Card, bound to `QueueEntryController.addJobOrder.form(entry.id)` (or a dedicated `JobOrderController.store.form(entry.id)`, per whichever controller/route naming the planner locks in).

---

### `resources/js/pages/public/QueueDisplay.vue` (component/page, streaming/polling)

**Analog — layout-opt-out only** (`resources/js/pages/Welcome.vue:1-4`):

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login } from '@/routes';
</script>
```

`Welcome.vue` demonstrates the _mechanism_ (no `defineOptions({layout: ...})` call at all — the `app.ts` switch handles it entirely via filename), not its visual content (Welcome.vue's marketing-page body is irrelevant here). Use `usePoll()` from `@inertiajs/vue3` per RESEARCH.md's recommendation (materially better than UI-SPEC's manual `setInterval` + `router.reload()` suggestion — flag this upgrade to the planner):

```typescript
import { usePoll } from '@inertiajs/vue3';

usePoll(5000, { only: ['queueEntries'] });
```

---

## Shared Patterns

### Audit Trail Coverage (all three new models)

**Source:** `app/Models/User.php:38` (`#[ObservedBy(AuditObserver::class)]`), enforced by `app/Observers/AuditObserver.php` (full file, 51 lines) and `app/Support/AuditLogger.php` (full file, 55 lines — **append-only**: only ever calls `AuditLog::create()`, never `update()`/`delete()`).
**Apply to:** `Customer`, `QueueEntry`, `JobOrder` — add the class attribute, nothing else. No service-provider registration exists or is needed.

```php
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(AuditObserver::class)]
class Customer extends Model { ... }
```

### Mutation Feedback (`Inertia::flash('toast', ...)`)

**Source:** `app/Http/Controllers/Owner/UserManagementController.php:36`, `:48`; `app/Http/Controllers/Owner/SystemConfigurationController.php:39`; `app/Http/Controllers/Settings/ProfileController.php:41`.
**Apply to:** Every mutating controller action in this phase (`CustomerController::store`, `QueueEntryController::store`/`::updateStatus`/`::addJobOrder`).

```php
Inertia::flash('toast', ['type' => 'success', 'message' => __('Queue number :number created with :count job order(s).', ['number' => $entry->queue_number, 'count' => $count])]);
return back();
```

### Role Middleware Gate

**Source:** `app/Http/Middleware/EnsureUserHasRole.php` (full file, 22 lines) — registered via `->middleware(['auth', 'role:frontline_staff'])` string syntax in `routes/portals.php`.

```php
abort_if(! $user || ! in_array($user->role->value, $roles, true), 403);
```

**Apply to:** All new authenticated routes under `frontline-staff` prefix — already the existing group, just add routes inside it. The public `queue-display` route deliberately does **not** get this middleware (D-09).

### Form Request + Validation Concern Trait Pairing

**Source:** `app/Http/Requests/Settings/ProfileUpdateRequest.php` + `app/Concerns/ProfileValidationRules.php`; `app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php` + `app/Concerns/SystemConfigValidationRules.php`.
**Apply to:** Every new FormRequest in this phase — `use {Concern}Trait; public function rules(): array { return $this->{method}(); }`. Never inline validation arrays directly in the FormRequest when a Concern trait already exists for the domain.

### `string` column + PHP backed enum via `casts()`

**Source:** `app/Models/User.php:54` (`'role' => UserRole::class`), migration `database/migrations/2026_08_31_165341_add_rbac_and_lockout_columns_to_users_table.php:16` (`$table->string('role')`).
**Apply to:** `queue_entries.status`, `job_orders.type`, `job_orders.status` — never `$table->enum(...)`.

### Route naming + Wayfinder-only frontend calls

**Source:** `routes/owner.php` (`.name('users.index')`, `.name('users.deactivate')` etc.) consumed via `resources/js/actions/App/Http/Controllers/Owner/UserManagementController.ts` in `resources/js/pages/owner/UserManagement.vue:3, 112, 136`.
**Apply to:** Every new route must have a `->name(...)`; every Vue page must import the generated Wayfinder action/route helper (`@/actions/App/Http/Controllers/FrontlineStaff/...`, `@/routes/frontline-staff/...`), never a hardcoded URL string. Regenerate with `--with-form` after adding routes (per CLAUDE.md/RESEARCH.md warning — bare `wayfinder:generate` drops `.form()`).

## No Analog Found

| File                                                                                                                      | Role           | Data Flow                    | Reason                                                                                                                                                                                                                                                                                                            |
| ------------------------------------------------------------------------------------------------------------------------- | -------------- | ---------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Http/Requests/FrontlineStaff/StoreQueueEntryRequest.php` (nested-array validation content)                           | form-request   | CRUD (nested array + file)   | No existing FormRequest in the codebase validates a nested array (`job_orders.*.field`) or a file upload — closest is `UpdateSystemConfigurationRequest`'s trait-delegation _structure_ only, not its _content_. Use RESEARCH.md's Code Examples section (Pattern 4) as the authoritative reference instead.      |
| `app/Http/Controllers/FrontlineStaff/QueueEntryController.php::store` (DB::transaction + lockForUpdate concurrency logic) | controller     | CRUD (transactional counter) | No existing controller in this codebase uses `DB::transaction()`/`lockForUpdate()` — this is Phase 2's first concurrency-guarded write. Use RESEARCH.md's Pattern 1 code example as the authoritative reference; test-coverage gap (SQLite cannot prove the concurrency guarantee) is documented there too.       |
| `resources/js/pages/public/QueueDisplay.vue` (polling body content, dark-theme kiosk layout)                              | component/page | streaming/polling            | No existing page uses `usePoll()`, dark-theme-by-default, or full-bleed kiosk-style large-format typography. `Welcome.vue` only supplies the layout-opt-out _mechanism_; the visual content must be built from UI-SPEC's Phase-Specific UI Notes and `demo/queue-display.html` (style cues only, per CONTEXT.md). |
| File upload storage (`store('job-orders', 'local')`)                                                                      | file-I/O       | file-I/O                     | No existing controller in this codebase handles a file upload yet (Phase 1 has none). Use RESEARCH.md's Common Pitfalls #4 and Code Examples' `Storage::fake('local')` test pattern as the authoritative reference — never `getClientOriginalName()`, never the `public` disk.                                    |

## Metadata

**Analog search scope:** `app/Models/`, `app/Observers/`, `app/Support/`, `app/Enums/`, `app/Http/Controllers/`, `app/Http/Requests/`, `app/Concerns/`, `database/migrations/`, `database/factories/`, `routes/`, `resources/js/pages/`, `resources/js/config/nav/`, `resources/js/components/`, `resources/js/app.ts`, `tests/Feature/`
**Files scanned:** ~35 (all of Phase 1's app/ and resources/js/ surface area, since Phase 2 is greenfield and every new file must extrapolate from Phase 1 conventions)
**Pattern extraction date:** 2026-09-01
