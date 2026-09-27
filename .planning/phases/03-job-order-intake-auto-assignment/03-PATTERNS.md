# Phase 3: Job Order Intake & Auto-Assignment - Pattern Map

**Mapped:** 2026-09-02
**Files analyzed:** 16
**Analogs found:** 13 / 16

## File Classification

| New/Modified File                                                         | Role               | Data Flow        | Closest Analog                                                                          | Match Quality |
| ------------------------------------------------------------------------- | ------------------ | ---------------- | --------------------------------------------------------------------------------------- | ------------- |
| `app/Enums/JobOrderStatus.php`                                            | enum/config        | transform        | `app/Enums/UserRole.php`                                                                | exact         |
| `database/migrations/xxxx_add_assigned_artist_id_to_job_orders_table.php` | migration          | CRUD             | `database/migrations/2026_08_31_165341_add_rbac_and_lockout_columns_to_users_table.php` | exact         |
| `database/migrations/xxxx_add_availability_columns_to_users_table.php`    | migration          | CRUD             | `database/migrations/2026_08_31_165341_add_rbac_and_lockout_columns_to_users_table.php` | exact         |
| `app/Models/JobOrder.php`                                                 | model              | CRUD             | (self — existing file, modify)                                                          | exact         |
| `app/Models/User.php`                                                     | model              | CRUD             | (self — existing file, modify)                                                          | exact         |
| `app/Actions/JobOrder/ValidateJobOrderFile.php` (new)                     | service/action     | transform        | `app/Actions/Fortify/EnsureAccountIsNotLocked.php`                                      | role-match    |
| `app/Actions/JobOrder/AssignArtistToJobOrder.php` (new)                   | service/action     | event-driven     | `app/Models/QueueEntry.php::nextForBusinessDay()`                                       | role-match    |
| `app/Concerns/JobOrderValidationRules.php`                                | utility (trait)    | transform        | (self — existing file, extend)                                                          | exact         |
| `app/Http/Controllers/FrontlineStaff/QueueEntryController.php`            | controller         | request-response | (self — existing file, modify `addJobOrder`/`store`)                                    | exact         |
| `app/Http/Controllers/FrontlineStaff/JobOrderController.php` (new)        | controller         | request-response | `app/Http/Controllers/FrontlineStaff/QueueEntryController.php`                          | exact         |
| `app/Http/Requests/FrontlineStaff/ReplaceJobOrderFileRequest.php` (new)   | request/validation | request-response | `app/Http/Requests/FrontlineStaff/AddJobOrderRequest.php`                               | exact         |
| `database/factories/JobOrderFactory.php`                                  | test-support       | CRUD             | (self — existing file, add states)                                                      | exact         |
| `database/factories/UserFactory.php`                                      | test-support       | CRUD             | (self — existing file, add states)                                                      | exact         |
| `routes/portals.php`                                                      | route              | request-response | (self — existing file, add route)                                                       | exact         |
| `resources/js/pages/frontline-staff/NewVisit.vue`                         | component          | request-response | (self — existing file, modify)                                                          | exact         |
| `resources/js/pages/frontline-staff/QueueList.vue`                        | component          | request-response | (self — existing file, modify)                                                          | exact         |

## Pattern Assignments

### `app/Enums/JobOrderStatus.php` (enum, transform)

**Analog:** `app/Enums/UserRole.php` (lines 1-29)

**Current state to extend:**

```php
<?php

namespace App\Enums;

enum JobOrderStatus: string
{
    case Intake = 'intake';
}
```

**Convention to follow** (string-backed, TitleCase keys, snake_case values):

```php
enum UserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case FrontlineStaff = 'frontline_staff';
    // ...
}
```

Add `ValidationFailed = 'validation_failed'`, `ReadyForProduction = 'ready_for_production'`, `Assigned = 'assigned'` cases, matching the UI-SPEC's exact string values (`03-UI-SPEC.md` badge mapping table). No `portalRoute()`-style match method needed here — `JobOrderStatus` currently has no methods; keep it a plain backed enum unless a future phase needs one.

---

### `database/migrations/xxxx_add_assigned_artist_id_to_job_orders_table.php` (migration, CRUD)

**Analog:** `database/migrations/2026_08_31_165341_add_rbac_and_lockout_columns_to_users_table.php` (full file, lines 1-42)

**Additive `Schema::table` pattern to copy:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->foreignId('assigned_artist_id')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_artist_id');
        });
    }
};
```

Note the original `job_orders` create migration (`database/migrations/2026_09_01_154403_create_job_orders_table.php`, lines 1-33) uses `$table->foreignId('queue_entry_id')->constrained()->cascadeOnDelete()` for its one FK — same `foreignId()->constrained()` idiom, but this new column must be `nullable()` (D-07: unassigned is a valid resting state) and should NOT cascade-delete artist history, so `nullOnDelete()` is the safer default (open discretion — CONTEXT.md does not lock delete behavior here).

Also consider whether a `validation_failure_reason` (nullable string/text) column belongs on this same migration — D-02/UI-SPEC's copywriting contract needs a stored reason string (DPI actual/minimum, rejected extension, actual/max size) to render on reload, not just a one-time flash message. No existing analog column for "stored failure reason" exists yet in this schema; follow the same additive `Schema::table('job_orders', ...)` shape.

---

### `database/migrations/xxxx_add_availability_columns_to_users_table.php` (migration, CRUD)

**Analog:** same as above — `database/migrations/2026_08_31_165341_add_rbac_and_lockout_columns_to_users_table.php`

```php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_available')->default(true)->after('last_activity_at');
    $table->timestamp('last_assigned_at')->nullable()->after('is_available');
});
```

D-05 requires `is_available` to default `true` for Artist-role users specifically, but since `users` has no per-role default mechanism at the schema level (role is just a string column), a global `default(true)` is correct here — non-Artist roles simply never read/write this column, same as how `failed_login_attempts`/`locked_until` exist on every row regardless of role today.

---

### `app/Models/JobOrder.php` (model, CRUD)

**Analog:** self (existing file, lines 1-55) — extend, don't replace

**Current casts/fillable to extend:**

```php
#[Fillable(['queue_entry_id', 'description', 'type', 'status', 'file_path'])]
#[ObservedBy(AuditObserver::class)]
class JobOrder extends Model
{
    protected function casts(): array
    {
        return [
            'type' => JobOrderType::class,
            'status' => JobOrderStatus::class,
        ];
    }

    public function queueEntry(): BelongsTo
    {
        return $this->belongsTo(QueueEntry::class);
    }
}
```

Add `assigned_artist_id` (and any `validation_failure_reason` column) to the `#[Fillable]` list, and add an `assignedArtist(): BelongsTo` relation mirroring `queueEntry()`'s exact shape:

```php
/**
 * @return BelongsTo<User, $this>
 */
public function assignedArtist(): BelongsTo
{
    return $this->belongsTo(User::class, 'assigned_artist_id');
}
```

`#[ObservedBy(AuditObserver::class)]` is already present — status transitions (Intake → ValidationFailed/ReadyForProduction/Assigned) and the `assigned_artist_id` write are automatically audit-logged with zero extra wiring, per CONTEXT.md's "Reusable Assets" note.

---

### `app/Models/User.php` (model, CRUD)

**Analog:** self (existing file, lines 1-60) — extend, don't replace

Add `is_available` and `last_assigned_at` to the `casts()` array following the exact idiom already used for `is_active`/`locked_until`:

```php
protected function casts(): array
{
    return [
        // ...existing...
        'is_available' => 'boolean',
        'last_assigned_at' => 'datetime',
    ];
}
```

**Important:** `#[Fillable(['name', 'email', 'password', 'role'])]` (line 36) intentionally does NOT include `is_active`, `failed_login_attempts`, `locked_until`, etc. — those are written exclusively via `forceFill()` (see `UserManagementController::deactivate()` and `UserFactory::locked()`/`deactivated()` states). Follow the same convention: do **not** add `is_available`/`last_assigned_at` to `#[Fillable]`; write them via `forceFill()` from the assignment action, exactly like existing bookkeeping columns.

---

### `app/Actions/JobOrder/ValidateJobOrderFile.php` (new — service/action, transform)

**Analog:** `app/Actions/Fortify/EnsureAccountIsNotLocked.php` (full file, lines 1-44) for the single-purpose invokable-class shape; `app/Models/SystemConfiguration.php` (lines 50-88) for reading configured thresholds.

**Action-class shape to copy** (this project already has an `App\Actions` convention — a subnamespace per domain, one `__invoke()` per class, PHPDoc explaining _why_, not just _what_):

```php
<?php

namespace App\Actions\Fortify;

class EnsureAccountIsNotLocked
{
    public function __invoke(Request $request, callable $next): mixed
    {
        // ...single responsibility, throws/returns a result...
    }
}
```

Mirror this as `App\Actions\JobOrder\ValidateJobOrderFile` with `__invoke(UploadedFile $file): JobOrderValidationOutcome` (or a simple array/DTO return — no DTO convention exists yet in this codebase, so a small readonly value object or `array{passed: bool, reason: ?string}` is equally in-house). Per D-01, only `jpg`/`png` get a real DPI check via `getimagesize()`/`exif_read_data()`; `pdf`/`ai`/`eps` skip DPI and validate format+size only.

**Threshold reads — copy this exact idiom** (`app/Models/SystemConfiguration.php`, lines 50-65):

```php
public static function getInt(string $key, int $default): int
{
    $value = self::resolve($key);
    return $value === null ? $default : (int) $value;
}
```

Call `SystemConfiguration::getInt('dpi_threshold_minimum', 300)`, `SystemConfiguration::getArray('accepted_file_formats', [...])`, `SystemConfiguration::getInt('max_file_size_mb', 50)` — never hardcode thresholds, per CONTEXT.md's Claude's Discretion note.

---

### `app/Actions/JobOrder/AssignArtistToJobOrder.php` (new — service/action, event-driven)

**Analog:** `app/Models/QueueEntry.php::nextForBusinessDay()` (lines 79-88) — the strongest pattern match in the codebase for "atomically pick one row under concurrency and stamp a fairness field," which is exactly D-06/D-07's requirement.

**Locking pattern to copy:**

```php
public static function nextForBusinessDay(string $businessDate): int
{
    return DB::transaction(fn (): int => (static::query()
        ->whereDate('queue_date', $businessDate)
        ->lockForUpdate()
        ->max('queue_number') ?? 0) + 1);
}
```

Apply the same `DB::transaction()` + `lockForUpdate()` shape to select the available Artist with the oldest/null `last_assigned_at`:

```php
DB::transaction(function () use ($jobOrder) {
    $artist = User::query()
        ->where('role', UserRole::Artist->value)
        ->where('is_available', true)
        ->orderByRaw('last_assigned_at IS NOT NULL, last_assigned_at ASC')
        ->lockForUpdate()
        ->first();

    if ($artist === null) {
        return null; // D-07: stays unassigned, no fallback
    }

    $jobOrder->forceFill([
        'assigned_artist_id' => $artist->id,
        'status' => JobOrderStatus::Assigned,
    ])->save();

    // saveQuietly() — see Shared Patterns below — routine fairness
    // bookkeeping, not a business-meaningful mutation on its own.
    $artist->forceFill(['last_assigned_at' => now()])->saveQuietly();

    return $artist;
});
```

D-07's "claim oldest unassigned Type B job when an artist becomes available" is a second entry point on this same action/service (e.g. a `claimOldestUnassigned(User $artist)` method) — no existing analog for this specific "reverse lookup" trigger exists in the codebase yet; it is new capability, not adapted from a prior pattern. Where it gets called from is out of this phase's UI scope (Phase 4 builds the On Break/End Shift toggle); expose it as a plain method ready for Phase 4 to invoke.

---

### `app/Concerns/JobOrderValidationRules.php` (utility/trait, transform)

**Analog:** self (existing file, lines 1-46) — extend, don't replace.

Current rules only check presence/`required_if` at the FormRequest layer:

```php
'job_orders.*.file' => ['nullable', 'file', 'required_if:job_orders.*.type,'.JobOrderType::TypeA->value],
```

Per D-02, a file that fails DPI/format/size does **not** get rejected by FormRequest validation — the job order is still created, just with `status = ValidationFailed`. So the _structural_ `file`/`required_if` rule here stays as-is (or gains a hard `mimes:`/`max:` cap sourced from `SystemConfiguration::getArray('accepted_file_formats')`/`getInt('max_file_size_mb')` for basic malformed-upload rejection); the _business-threshold_ DPI/format/size check that determines `ValidationFailed` vs `ReadyForProduction` belongs in `ValidateJobOrderFile` above, called from the controller after the FormRequest passes and the file is stored — matching D-03's "runs synchronously in the same request that stores the file."

**`SystemConfigValidationRules` trait** (`app/Concerns/SystemConfigValidationRules.php`, lines 1-28) is a second, smaller analog for "trait method returning a `match()`-driven rules array" if the format/size checks end up expressed as Form Request rules per Claude's Discretion:

```php
protected function valueRules(string $type): array
{
    return match ($type) {
        'integer' => ['value' => ['required', 'integer', 'min:0']],
        // ...
    };
}
```

---

### `app/Http/Controllers/FrontlineStaff/QueueEntryController.php` (controller, request-response)

**Analog:** self (existing file, full file lines 1-135) — modify `addJobOrder()` (lines 76-91) and `store()` (lines 97-134).

**Current `addJobOrder()` to extend with validation+assignment:**

```php
public function addJobOrder(AddJobOrderRequest $request, QueueEntry $queueEntry): RedirectResponse
{
    $queueEntry->jobOrders()->create([
        'description' => $request->validated('description'),
        'type' => $request->validated('type'),
        'status' => JobOrderStatus::Intake,
        'file_path' => $request->file('file')?->store('job-orders', 'local'),
    ]);

    Inertia::flash('toast', [
        'type' => 'success',
        'message' => __('Job order added to queue number :number.', ['number' => $queueEntry->queue_number]),
    ]);

    return back();
}
```

After `create()`, branch on `JobOrderType`: Type A calls `ValidateJobOrderFile` and updates status/failure_reason; Type B calls `AssignArtistToJobOrder` and updates status/assigned_artist_id. The `store()` method's `foreach ($request->validated('job_orders') as $index => $row)` loop (lines 110-117) needs the identical per-row branch added inside the existing `DB::transaction()` closure — reuse the same transaction, don't open a nested one.

**Toast copy** — swap the generic success message for the outcome-specific copy from `03-UI-SPEC.md`'s Copywriting Contract table (e.g. `"Job order added — ready for production."` / `"...file needs replacement..."` / `"...assigned to :artist."` / `"...awaiting an available artist."`), following the exact `Inertia::flash('toast', ['type' => 'success', 'message' => __(...)])` call shape already used on every line of this controller.

---

### `app/Http/Controllers/FrontlineStaff/JobOrderController.php` (new — controller, request-response)

**Analog:** `app/Http/Controllers/FrontlineStaff/QueueEntryController.php::addJobOrder()` (lines 76-91) — same "single mutating action on a nested resource, redirect back with a toast" shape.

```php
<?php

namespace App\Http\Controllers\FrontlineStaff;

use App\Http\Controllers\Controller;
use App\Http\Requests\FrontlineStaff\ReplaceJobOrderFileRequest;
use App\Models\JobOrder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class JobOrderController extends Controller
{
    public function replaceFile(ReplaceJobOrderFileRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        $jobOrder->forceFill([
            'file_path' => $request->file('file')->store('job-orders', 'local'),
        ])->save();

        // ...re-run ValidateJobOrderFile, update status/failure_reason...

        Inertia::flash('toast', ['type' => 'success', 'message' => __('...')]);

        return back();
    }
}
```

Route registration follows `routes/portals.php`'s exact nested-resource shape (lines 15): `Route::post('queue-entries/{queueEntry}/job-orders', [QueueEntryController::class, 'addJobOrder'])->name('queue-entries.job-orders.store')` → add e.g. `Route::post('job-orders/{jobOrder}/replace-file', [JobOrderController::class, 'replaceFile'])->name('job-orders.replace-file')` inside the same `role:frontline_staff` group (lines 7-16).

---

### `app/Http/Requests/FrontlineStaff/ReplaceJobOrderFileRequest.php` (new — request/validation, request-response)

**Analog:** `app/Http/Requests/FrontlineStaff/AddJobOrderRequest.php` (full file, lines 1-22)

```php
<?php

namespace App\Http\Requests\FrontlineStaff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceJobOrderFileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
        ];
    }
}
```

No `authorize()` override needed — this file follows `AddJobOrderRequest`/`StoreQueueEntryRequest`'s pattern of relying on the route's `role:frontline_staff` middleware for access control rather than a Policy (contrast with Owner's `DeactivateUserRequest`, which does need `$this->user()->can(...)` because Owner-vs-Admin-vs-target authorization is actor-relative, not just role-gated).

---

### `database/factories/JobOrderFactory.php` (test-support, CRUD)

**Analog:** self (existing file, lines 1-33) — add states, don't replace.

```php
public function typeA(): static
{
    return $this->state(fn (array $attributes) => [
        'type' => JobOrderType::TypeA->value,
    ]);
}
```

Add `validationFailed()`, `readyForProduction()`, `assigned()` states following this exact `state(fn ...)` shape (`assigned()` will also need `assigned_artist_id => User::factory()->artist()`).

---

### `database/factories/UserFactory.php` (test-support, CRUD)

**Analog:** self (existing file, lines 1-129) — `locked()`/`deactivated()` states (lines 105-129) are the exact pattern to copy for availability.

```php
public function locked(): static
{
    return $this->afterCreating(fn (User $user) => $user->forceFill([
        'failed_login_attempts' => 5,
        'locked_until' => now()->addMinutes(15),
    ])->save());
}
```

Add e.g. `unavailable()` using `afterCreating()` + `forceFill()` (since `is_available`/`last_assigned_at` are intentionally outside `#[Fillable]`, per the `User` model note above — a plain `state()` call would silently drop them, exactly as the existing comment on `locked()`/`deactivated()` warns).

---

## Shared Patterns

### Invokable single-purpose Action classes

**Source:** `app/Actions/Fortify/EnsureAccountIsNotLocked.php`, `app/Actions/Fortify/CaptureAuthenticatedSessionId.php`
**Apply to:** `ValidateJobOrderFile`, `AssignArtistToJobOrder`

```php
class EnsureAccountIsNotLocked
{
    public function __invoke(Request $request, callable $next): mixed { /* ... */ }
}
```

This project already has an `App\Actions\{Domain}\{Verb}{Noun}` convention (currently only `App\Actions\Fortify`) — extend it with a new `App\Actions\JobOrder` subnamespace rather than inventing an `app/Services` directory.

### Atomic "pick one row fairly under concurrency" locking

**Source:** `app/Models/QueueEntry.php::nextForBusinessDay()` (lines 79-88)
**Apply to:** `AssignArtistToJobOrder`'s round-robin artist selection (D-06/D-07)

```php
DB::transaction(fn () => static::query()->...->lockForUpdate()->first());
```

### `saveQuietly()` for routine bookkeeping writes that shouldn't spam the audit trail

**Source:** `app/Listeners/Auth/HandleSuccessfulLogin.php` (lines 26-35), `app/Actions/Fortify/CaptureAuthenticatedSessionId.php` (lines 27-33)
**Apply to:** the `last_assigned_at` stamp on `User` inside `AssignArtistToJobOrder` — this is fairness bookkeeping analogous to `last_activity_at`/`current_session_id`, not a business-meaningful mutation in its own right. The `assigned_artist_id`/`status` write on `JobOrder` itself should still go through a normal `save()` (not quiet) so it stays fully audit-logged, matching how `HandleSuccessfulLogin` still calls `AuditLogger::recordAuthEvent()` explicitly for the meaningful signal while silencing only the routine column bump.

```php
$user->forceFill(['last_activity_at' => now(), ...])->saveQuietly();
```

### `forceFill()` for columns outside `#[Fillable]`

**Source:** `app/Http/Controllers/Owner/UserManagementController.php` (lines 34, 46), `database/factories/UserFactory.php::locked()`/`deactivated()` (lines 105-129)
**Apply to:** every write to `User::is_available`/`last_assigned_at` and `JobOrder::assigned_artist_id` (assuming these are kept out of `#[Fillable]` to match the codebase's existing convention of only fillable-listing user-editable form fields, not system-managed bookkeeping columns)

```php
$user->forceFill(['is_active' => false])->save();
```

### Reading configured thresholds, never hardcoding

**Source:** `app/Models/SystemConfiguration.php` (lines 50-88), consumed today by nothing yet (first real consumer is this phase)
**Apply to:** `ValidateJobOrderFile`'s DPI/format/size checks

```php
SystemConfiguration::getInt('dpi_threshold_minimum', 300);
SystemConfiguration::getArray('accepted_file_formats', ['pdf', 'ai', 'eps', 'jpg', 'png']);
SystemConfiguration::getInt('max_file_size_mb', 50);
```

### `Inertia::flash('toast', ...)` mutation feedback

**Source:** every controller method in `QueueEntryController.php` and `UserManagementController.php`
**Apply to:** `QueueEntryController::addJobOrder()`/`store()` (outcome-specific copy) and the new `JobOrderController::replaceFile()`

```php
Inertia::flash('toast', ['type' => 'success', 'message' => __('...')]);
return back();
```

### Audit coverage via `#[ObservedBy(AuditObserver::class)]`

**Source:** `app/Models/JobOrder.php` (line 27), `app/Models/SystemConfiguration.php` (line 24)
**Apply to:** already present on `JobOrder`; no new wiring needed — every new status/assignment column write is automatically captured in `audit_trail` via `AuditObserver::updated()` (`app/Observers/AuditObserver.php`, lines 21-29) as long as the write goes through `save()`/`update()` and not `saveQuietly()`.

### Frontend: status badge variants

**Source:** `resources/js/pages/frontline-staff/QueueList.vue` (lines 108-127) — the existing Queue-status badge `v-if`/`v-else-if` chain
**Apply to:** the new job-order-status badges in both `NewVisit.vue` and `QueueList.vue`, per `03-UI-SPEC.md`'s exact mapping table

```vue
<Badge v-if="entry.status === 'waiting'" variant="outline">Waiting</Badge>
<Badge v-else-if="entry.status === 'serving'" variant="default">Serving</Badge>
<Badge
    v-else-if="entry.status === 'done'"
    class="text-green-600 dark:text-green-400"
>Done</Badge>
```

### Frontend: `Dialog` + `Form` shell for a scoped mutating action

**Source:** `resources/js/pages/frontline-staff/QueueList.vue` (lines 167-308) — the "Add Job Order" dialog
**Apply to:** the new "Replace File" dialog in both `NewVisit.vue` and `QueueList.vue`, per UI-SPEC's explicit instruction to mirror this shell exactly

```vue
<Dialog>
  <DialogTrigger as-child><Button ...><Plus class="size-4" /><span class="sr-only">Add Job Order</span></Button></DialogTrigger>
  <DialogContent>
    <Form v-bind="Controller.action.form(id)" :options="{ preserveScroll: true }" v-slot="{ errors, processing }">
      <DialogHeader><DialogTitle>...</DialogTitle></DialogHeader>
      <!-- fields + InputError -->
      <DialogFooter class="gap-2">
        <DialogClose as-child><Button type="button" variant="secondary">Cancel</Button></DialogClose>
        <Button type="submit" :disabled="processing">...</Button>
      </DialogFooter>
    </Form>
  </DialogContent>
</Dialog>
```

### Frontend: `AlertError.vue` for a failure-reason display

**Source:** `resources/js/components/AlertError.vue` (full file, lines 1-31)
**Apply to:** the `validation_failed` job order row in `NewVisit.vue`, per UI-SPEC line 146 (`:errors="[jobOrder.failure_reason]"`)

```vue
<Alert variant="destructive">
  <AlertCircle class="size-4" />
  <AlertTitle>{{ title }}</AlertTitle>
  <AlertDescription><ul class="list-inside list-disc text-sm"><li v-for="...">{{ error }}</li></ul></AlertDescription>
</Alert>
```

## No Analog Found

| File                                                                                                                                       | Role           | Data Flow    | Reason                                                                                                                                                                                                                                                                                                            |
| ------------------------------------------------------------------------------------------------------------------------------------------ | -------------- | ------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Actions/JobOrder/AssignArtistToJobOrder.php` — the D-07 "claim oldest unassigned job on availability-change" entry point specifically | service/action | event-driven | No prior "reverse trigger" pattern exists in this codebase (everything else is a direct request-response mutation); the closest available building block is `QueueEntry::nextForBusinessDay()`'s locking idiom, not a full analog for the trigger shape itself. Phase 4 owns the UI that will actually invoke it. |
| `validation_failure_reason` storage/rendering (exact column + DTO shape for DPI/format/size reason text)                                   | data model     | transform    | No prior "stored structured failure reason" field exists anywhere in the schema (contrast with generic `errors` bags, which are request-scoped and never persisted) — this is new schema surface, not adapted from an existing column.                                                                            |

## Metadata

**Analog search scope:** `app/Http/Controllers/`, `app/Http/Requests/`, `app/Concerns/`, `app/Models/`, `app/Enums/`, `app/Actions/`, `app/Listeners/`, `app/Observers/`, `app/Policies/`, `database/migrations/`, `database/factories/`, `database/seeders/`, `routes/`, `tests/Feature/FrontlineStaff/`, `resources/js/pages/frontline-staff/`, `resources/js/components/`
**Files scanned:** ~35 read directly, plus directory listings across `app/`, `database/`, `routes/`, `resources/js/pages/`, `resources/js/components/`
**Pattern extraction date:** 2026-09-02
