# Phase 4: Artist Workflow & Design Editor - Pattern Map

**Mapped:** 2026-09-02
**Files analyzed:** 44 (new + modified)
**Analogs found:** 42 / 44 (2 have no in-repo analog — the TOAST UI wrapper component and its type shim; both flagged below with RESEARCH.md fallback guidance)

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `app/Enums/JobOrderStatus.php` (MODIFY) | model (enum) | transform | itself (existing file, additive edit) | exact |
| `app/Enums/ArtistStatus.php` (NEW) | model (enum) | transform | `app/Enums/UserRole.php` | exact |
| `app/Models/DesignFile.php` (NEW) | model | CRUD | `app/Models/JobOrder.php` | exact |
| `app/Models/RevisionLog.php` (NEW) | model | CRUD | `app/Models/JobOrder.php` | exact |
| `app/Models/JobOrder.php` (MODIFY — add `consultation_notes` fillable, `designFile()`/`revisionLogs()` relations) | model | CRUD | `app/Models/QueueEntry.php` (`jobOrders()` HasMany pattern) | exact |
| `app/Models/User.php` (MODIFY — add `artist_status`/`break_started_at` casts) | model | CRUD | itself (existing file, additive edit, same shape as Phase 3's `is_available`/`last_assigned_at` addition) | exact |
| `app/Policies/DesignFilePolicy.php` (NEW) | middleware (policy/guard) | request-response | `app/Policies/UserPolicy.php` | exact |
| `app/Actions/JobOrder/RecordDesignRevision.php` (NEW) | service | file-I/O + CRUD | `app/Actions/JobOrder/AssignArtistToJobOrder.php` (DB::transaction shape) + `app/Http/Controllers/FrontlineStaff/JobOrderController.php::replaceFile` (file store) | role-match |
| `app/Actions/JobOrder/SetArtistSessionStatus.php` (NEW) | service | event-driven | `app/Actions/JobOrder/AssignArtistToJobOrder.php` | exact |
| `app/Actions/JobOrder/AdvanceArtistQueue.php` (NEW — Next/Forward/Not-Appear, if planner splits this out of the controller) | service | event-driven | `app/Actions/JobOrder/AssignArtistToJobOrder.php` (single-purpose invokable Action class) | role-match |
| `app/Http/Controllers/Artist/JobOrderQueueController.php` (NEW) | controller | CRUD (status transitions) | `app/Http/Controllers/FrontlineStaff/QueueEntryController.php` (not read directly, but same shape as `JobOrderController`/`UserManagementController` below — thin controller, body-less status-transition requests) | role-match |
| `app/Http/Controllers/Artist/DesignEditorController.php` (NEW) | controller | file-I/O | `app/Http/Controllers/FrontlineStaff/JobOrderController.php` | exact |
| `app/Http/Controllers/Artist/SessionStatusController.php` (NEW) | controller | event-driven | `app/Http/Controllers/Owner/UserManagementController.php` (`deactivate`/`reactivate` toggle-and-flash shape) | exact |
| `app/Http/Controllers/Artist/PerformanceReportController.php` (NEW) | controller | batch (aggregation report) | `app/Http/Controllers/Owner/AuditTrailController.php` (date-range filtered Inertia index) | exact |
| `app/Http/Controllers/Owner/DesignFileController.php` (NEW — `unlock()` only) | controller | request-response | `app/Http/Controllers/Owner/UserManagementController.php::deactivate` | exact |
| `app/Http/Requests/Artist/UpdateConsultationNotesRequest.php` (NEW) | middleware (FormRequest) | request-response | `app/Http/Requests/FrontlineStaff/ReplaceJobOrderFileRequest.php` | role-match |
| `app/Http/Requests/Artist/SendForReviewRequest.php` (NEW) | middleware (FormRequest) | file-I/O | `app/Http/Requests/FrontlineStaff/ReplaceJobOrderFileRequest.php` (file rule) — tighten per RESEARCH.md V5 (`image`, `mimes:png`) | role-match |
| `app/Http/Requests/Artist/RecordDesignVerdictRequest.php` (NEW — approve / request-changes) | middleware (FormRequest) | request-response | `app/Http/Requests/FrontlineStaff/UpdateQueueEntryStatusRequest.php` (body-less, target state implied by route) | exact |
| `app/Http/Requests/Artist/NextJobOrderRequest.php` / `ForwardJobOrderRequest.php` / `NotAppearJobOrderRequest.php` (NEW) | middleware (FormRequest) | event-driven | `app/Http/Requests/FrontlineStaff/UpdateQueueEntryStatusRequest.php` | exact |
| `app/Http/Requests/Artist/UpdateSessionStatusRequest.php` (NEW) | middleware (FormRequest) | event-driven | `app/Http/Requests/FrontlineStaff/UpdateQueueEntryStatusRequest.php` | exact |
| `app/Http/Requests/Owner/UnlockDesignFileRequest.php` (NEW) | middleware (FormRequest) | request-response | `app/Http/Requests/Owner/DeactivateUserRequest.php` | exact |
| `app/Concerns/ConsultationValidationRules.php` (NEW, optional — only if notes validation grows beyond one line) | utility | transform | `app/Concerns/JobOrderValidationRules.php` | role-match |
| `database/migrations/xxxx_add_consultation_and_status_columns_to_job_orders_table.php` (NEW) | migration | batch | `database/migrations/2026_09_01_224409_add_validation_and_assignment_columns_to_job_orders_table.php` | exact |
| `database/migrations/xxxx_add_artist_status_columns_to_users_table.php` (NEW) | migration | batch | `database/migrations/2026_09_01_224410_add_availability_columns_to_users_table.php` | exact |
| `database/migrations/xxxx_create_design_files_table.php` (NEW) | migration | batch | `database/migrations/2026_09_01_154403_create_job_orders_table.php` | exact |
| `database/migrations/xxxx_create_revision_logs_table.php` (NEW) | migration | batch | `database/migrations/2026_09_01_154403_create_job_orders_table.php` | exact |
| `database/factories/DesignFileFactory.php` (NEW) | utility (factory) | batch | `database/factories/JobOrderFactory.php` | exact |
| `database/factories/RevisionLogFactory.php` (NEW) | utility (factory) | batch | `database/factories/JobOrderFactory.php` | exact |
| `routes/portals.php` (MODIFY — extend `artist.` group) | route | request-response | itself (existing `role:artist` group in same file) | exact |
| `routes/owner.php` (MODIFY — add `design-files/{id}/unlock`) | route | request-response | itself (existing `role:owner,admin` group in same file) | exact |
| `resources/js/pages/artist/Dashboard.vue` (MODIFY — replace placeholder) | component | request-response | `resources/js/pages/frontline-staff/QueueList.vue` | exact |
| `resources/js/pages/artist/DesignEditor.vue` (NEW) | component | file-I/O | `resources/js/pages/frontline-staff/QueueList.vue` (page shell) + `resources/js/components/ReplaceJobOrderFileDialog.vue` (file-mutation Form pattern) | role-match |
| `resources/js/pages/artist/PerformanceReport.vue` (NEW) | component | batch | `resources/js/pages/owner/AuditTrail.vue` (not read directly — same analog family as `AuditTrailController`, date-range filter UI) | role-match |
| `resources/js/components/ToastImageEditor.vue` (NEW) | component | file-I/O | **none** — see No Analog Found | none |
| `resources/js/config/nav/artist.ts` (NEW) | config | transform | `resources/js/config/nav/frontline-staff.ts` | exact |
| `resources/js/types/tui-image-editor.d.ts` (NEW) | config | transform | **none** — see No Analog Found | none |
| `tests/Feature/Artist/ConsultationNotesTest.php` (NEW) | test | CRUD | `tests/Feature/FrontlineStaff/JobOrderProcessingTest.php` | exact |
| `tests/Feature/Artist/QueueControlsTest.php` (NEW) | test | event-driven | `tests/Feature/JobOrder/AssignArtistToJobOrderTest.php` | exact |
| `tests/Feature/Artist/DesignEditorTest.php` (NEW) | test | request-response | `tests/Feature/FrontlineStaff/JobOrderProcessingTest.php` (prop-shape/status assertions) | role-match |
| `tests/Feature/Artist/SendForReviewTest.php` (NEW) | test | file-I/O | `tests/Feature/FrontlineStaff/JobOrderProcessingTest.php` (`Storage::fake('local')` + `assertExists`) | exact |
| `tests/Feature/Artist/DesignLockTest.php` (NEW) | test | request-response | `tests/Feature/FrontlineStaff/JobOrderProcessingTest.php` (`replace-file on a type b job order returns a 422` — wrong-state guard test) | exact |
| `tests/Feature/Owner/UnlockDesignFileTest.php` (NEW) | test | request-response | `tests/Feature/Owner/UserManagementTest.php` (not read directly — same analog family as `UserPolicy`/`DeactivateUserRequest`) + audit assertion from `JobOrderProcessingTest.php` | role-match |
| `tests/Feature/Artist/SessionStatusTest.php` (NEW) | test | event-driven | `tests/Feature/JobOrder/AssignArtistToJobOrderTest.php` | exact |
| `tests/Feature/Artist/PerformanceReportTest.php` (NEW) | test | batch | `tests/Feature/Owner/AuditTrailTest.php` (not read directly — same analog family as `AuditTrailController`) | role-match |

## Pattern Assignments

### Enums — `JobOrderStatus.php` (modify), `ArtistStatus.php` (new)

**Analog:** `app/Enums/JobOrderStatus.php` (existing) + `app/Enums/UserRole.php`

**Full existing file** (`app/Enums/JobOrderStatus.php`, 11 lines):
```php
<?php

namespace App\Enums;

enum JobOrderStatus: string
{
    case Intake = 'intake';
    case ValidationFailed = 'validation_failed';
    case ReadyForProduction = 'ready_for_production';
    case Assigned = 'assigned';
}
```
Add cases additively, following D-06's cycle (`InConsultation`, `InDesign`, `PendingReview`, `DesignApproved` — never renumber/remove existing cases).

**`UserRole`'s helper-method convention** (lines 15-28) — worth mirroring if `ArtistStatus` ever needs a display/behavior helper:
```php
public function portalRoute(): string
{
    return match ($this) {
        self::Owner, self::Admin => 'owner.dashboard',
        // ...
    };
}
```
New `ArtistStatus` enum, same TitleCase-key/snake_case-value convention:
```php
enum ArtistStatus: string
{
    case Available = 'available';
    case OnBreak = 'on_break';
    case OffShift = 'off_shift';
}
```

---

### Models — `DesignFile.php`, `RevisionLog.php` (new)

**Analog:** `app/Models/JobOrder.php` (full file, 68 lines)

Copy this exact shape — `#[Fillable]` + `#[ObservedBy(AuditObserver::class)]` attributes, PHPDoc `@property` block, `casts()` method (Laravel 11+ style, not legacy `$casts`), `BelongsTo` relation to `JobOrder`:
```php
<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_order_id
 * @property string $file_path
 * @property Carbon|null $locked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['job_order_id', 'file_path'])]
#[ObservedBy(AuditObserver::class)]
class DesignFile extends Model
{
    /** @use HasFactory<\Database\Factories\DesignFileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['locked_at' => 'datetime'];
    }

    /** @return BelongsTo<JobOrder, $this> */
    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }
}
```
Note: per RESEARCH.md Open Question #2, `locked_at` is intentionally **not** in `#[Fillable]` — it's set via `forceFill()` in the unlock action, same pattern `JobOrder::assigned_artist_id`/`validation_failure_reason` use (see `JobOrderFactory::validationFailed()` comment: "outside JobOrder's #[Fillable] list and would be silently dropped by a state()-merged create() call").

`RevisionLog` follows the identical shape, `belongsTo(JobOrder::class)`, with columns per planner's schema design (`job_order_id`, `submitted_at`, outcome/notes fields).

---

### Model modifications — `JobOrder.php`, `User.php`

**Analog:** `app/Models/QueueEntry.php` lines 56-64 (`HasMany` relation pattern) + `app/Models/JobOrder.php` lines 58-66 (`BelongsTo` relation pattern)

```php
/**
 * The job orders created during this visit.
 *
 * @return HasMany<JobOrder, $this>
 */
public function jobOrders(): HasMany
{
    return $this->hasMany(JobOrder::class);
}
```
Add to `JobOrder`: `designFile(): HasOne` (D-08 — single current row) and `revisionLogs(): HasMany`. Add `consultation_notes` to the existing `#[Fillable([...])]` array (line 28) — it's a direct Artist-editable field, not a system-set column like `assigned_artist_id`, so it belongs in `#[Fillable]` unlike the lock/assignment columns above.

**`User.php` casts() modification** — mirror the existing Phase 3 addition already in this file (lines 51-63):
```php
protected function casts(): array
{
    return [
        // ...existing casts...
        'is_available' => 'boolean',
        'last_assigned_at' => 'datetime',
        // Phase 4 additions:
        'artist_status' => ArtistStatus::class,
        'break_started_at' => 'datetime',
    ];
}
```

---

### Policy — `DesignFilePolicy.php`

**Analog:** `app/Policies/UserPolicy.php` (full file, 44 lines)

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function deactivate(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        if ($actor->role === UserRole::Owner) {
            return true;
        }

        if ($actor->role === UserRole::Admin) {
            return ! in_array($target->role, [UserRole::Owner, UserRole::Admin], true);
        }

        return false;
    }
}
```
`DesignFilePolicy::unlock()` is simpler (Owner only, per JOB-07's exact wording — not Admin, unlike `UserPolicy` which lets Admin act on staff roles):
```php
class DesignFilePolicy
{
    public function unlock(User $actor, DesignFile $designFile): bool
    {
        return $actor->role === UserRole::Owner;
    }
}
```
Relies on Laravel's convention-based policy auto-discovery (no explicit registration found anywhere in `app/Providers/` — confirmed this is already relied on for `UserPolicy`, same will work for `DesignFilePolicy` → `DesignFile` model naming convention).

---

### Actions — `RecordDesignRevision.php`, `SetArtistSessionStatus.php`

**Analog:** `app/Actions/JobOrder/AssignArtistToJobOrder.php` (full file, 85 lines)

**DB::transaction + forceFill + save shape** (lines 29-51):
```php
return DB::transaction(function () use ($jobOrder): ?User {
    $artist = User::query()
        ->where('role', UserRole::Artist->value)
        ->where('is_available', true)
        ->orderByRaw('last_assigned_at IS NOT NULL, last_assigned_at ASC')
        ->lockForUpdate()
        ->first();

    if ($artist === null) {
        return null;
    }

    $jobOrder->forceFill([
        'assigned_artist_id' => $artist->id,
        'status' => JobOrderStatus::Assigned,
    ])->save();

    $artist->forceFill(['last_assigned_at' => now()])->saveQuietly();

    return $artist;
});
```
`RecordDesignRevision` (D-07/D-08 transaction) combines this shape with the file-store call from `JobOrderController::replaceFile` (see Shared Patterns below):
```php
public function __invoke(JobOrder $jobOrder, UploadedFile $file): void
{
    DB::transaction(function () use ($jobOrder, $file) {
        $path = $file->store('design-files', 'local');

        DesignFile::updateOrCreate(
            ['job_order_id' => $jobOrder->id], // D-08: single current row
            ['file_path' => $path],
        );

        RevisionLog::create([
            'job_order_id' => $jobOrder->id,
            'submitted_at' => now(),
        ]);

        $jobOrder->forceFill(['status' => JobOrderStatus::PendingReview])->save();
    });
}
```

**`SetArtistSessionStatus`** — directly follows `AssignArtistToJobOrder::claimOldestUnassigned()`'s existing docblock, which explicitly forward-references this phase (lines 54-58 of the analog):
```php
/**
 * Claim the oldest unassigned Type B job order for an artist who has
 * just become available (D-07). No caller exists yet this phase —
 * Phase 4's On Break/End Shift toggle will invoke this.
 */
public function claimOldestUnassigned(User $artist): ?JobOrder
```
`SetArtistSessionStatus::__invoke()` is the caller RESEARCH.md already recommends:
```php
public function __invoke(User $artist, ArtistStatus $status): void
{
    $artist->forceFill([
        'artist_status' => $status,
        'is_available' => $status === ArtistStatus::Available,
        'break_started_at' => $status === ArtistStatus::OnBreak ? now() : null,
    ])->save();

    if ($status === ArtistStatus::Available) {
        app(AssignArtistToJobOrder::class)->claimOldestUnassigned($artist);
    }
}
```

---

### Controllers — `JobOrderQueueController.php`, `DesignEditorController.php`, `SessionStatusController.php`, `Owner/DesignFileController.php`

**Analog for mutating single-purpose actions:** `app/Http/Controllers/Owner/UserManagementController.php` (full file, 53 lines) — thin controller, constructor-injected action class not needed here (plain `forceFill()->save()`), Inertia flash on every mutation:
```php
public function deactivate(DeactivateUserRequest $request, User $user): RedirectResponse
{
    $user->forceFill(['is_active' => false])->save();

    Inertia::flash('toast', ['type' => 'success', 'message' => __(":name's account has been deactivated.", ['name' => $user->name])]);

    return back();
}
```
`SessionStatusController::update()` and `Owner\DesignFileController::unlock()` follow this exact shape.

**Analog for a controller with an injected Action + `abort_unless` state guard:** `app/Http/Controllers/FrontlineStaff/JobOrderController.php` (full file, 45 lines):
```php
class JobOrderController extends Controller
{
    public function __construct(public ValidateJobOrderFile $validateJobOrderFile) {}

    public function replaceFile(ReplaceJobOrderFileRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        abort_unless($jobOrder->type === JobOrderType::TypeA, 422, 'Only a Type A job order accepts a file replacement.');

        $file = $request->file('file');
        $outcome = ($this->validateJobOrderFile)($file);

        $jobOrder->forceFill([
            'file_path' => $file->store('job-orders', 'local'),
            'status' => $outcome['passed'] ? JobOrderStatus::ReadyForProduction : JobOrderStatus::ValidationFailed,
            'validation_failure_reason' => $outcome['reason'],
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => /* ... */]);

        return back();
    }
}
```
`DesignEditorController::sendForReview()` uses this exact `__construct(public RecordDesignRevision $recordDesignRevision) {}` + `abort_unless($jobOrder->status !== JobOrderStatus::DesignApproved, 422, ...)` shape (this is also the JOB-06 lock-enforcement guard RESEARCH.md's Security Domain calls out).

**Analog for a report/index controller with date-range filtering:** `app/Http/Controllers/Owner/AuditTrailController.php` (full file, 37 lines):
```php
public function index(FilterAuditTrailRequest $request): Response
{
    $entries = AuditLog::query()
        ->with('user:id,name,email,role')
        ->latest('created_at')
        ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
        ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
        ->paginate(25)
        ->withQueryString();

    return Inertia::render('owner/AuditTrail', [
        'entries' => $entries,
        'filters' => $request->only(['user', 'action', 'from', 'to']),
    ]);
}
```
`PerformanceReportController::index()` follows this exact `when($request->filled(...))` date-range shape for JOB-10's "selectable date range" over `job_orders`/`revision_logs` aggregates, reusing `FilterAuditTrailRequest`'s `['nullable', 'date']` rule shape (see Form Requests below).

**Serving the private-disk design image into the editor** — `Storage::disk('local')->temporaryUrl()` (verified already enabled: `config/filesystems.php` line 36 has `'serve' => true` on the `local` disk):
```php
'initialImageUrl' => $designFile?->file_path
    ? Storage::disk('local')->temporaryUrl($designFile->file_path, now()->addMinutes(10))
    : null,
```
`DesignEditorController::edit()` (the Inertia page render for `resources/js/pages/artist/DesignEditor.vue`) uses this to prepare the `initialImageUrl` prop — no analog exists in-repo for this specific call, but it's a single documented Laravel API call, not a pattern requiring a codebase precedent (Don't Hand-Roll table in RESEARCH.md).

---

### Form Requests — all `app/Http/Requests/Artist/*.php`, `app/Http/Requests/Owner/UnlockDesignFileRequest.php`

**Analog for a body-less, route-implied-target FormRequest:** `app/Http/Requests/FrontlineStaff/UpdateQueueEntryStatusRequest.php` (full file, 25 lines):
```php
<?php

namespace App\Http\Requests\FrontlineStaff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQueueEntryStatusRequest extends FormRequest
{
    /**
     * Body-less — the target state is implied by which named route was
     * hit (call-next vs mark-done), not a client-supplied value.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }
}
```
Use this exact shape for `NextJobOrderRequest`, `ForwardJobOrderRequest`, `NotAppearJobOrderRequest`, and `RecordDesignVerdictRequest` (approve/request-changes — target state implied by route, same as `call-next`/`mark-done`).

**Analog for an authorize()-gated FormRequest (policy check):** `app/Http/Requests/Owner/DeactivateUserRequest.php` (full file, 29 lines):
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
`UnlockDesignFileRequest::authorize()` is `$this->user()->can('unlock', $this->route('designFile'))` — identical shape.

**Analog for a file-upload FormRequest:** `app/Http/Requests/FrontlineStaff/ReplaceJobOrderFileRequest.php` (full file, 25 lines):
```php
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
`SendForReviewRequest` tightens this per RESEARCH.md's V5 Input Validation note (this file's only legitimate producer is the app's own canvas export, unlike Type A's arbitrary user upload):
```php
public function rules(): array
{
    return [
        'file' => ['required', 'file', 'image', 'mimes:png'],
    ];
}
```

**Analog for a date-range filter FormRequest:** `app/Http/Requests/Owner/FilterAuditTrailRequest.php` (full file, 25 lines):
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
A `PerformanceReportFilterRequest` (if the planner splits the report's filter into its own request rather than inlining into the controller) follows this `['nullable', 'date']` shape for `from`/`to`.

**Analog for a Concerns validation trait** (only needed if `consultation_notes` validation grows beyond a single `nullable|string` rule): `app/Concerns/JobOrderValidationRules.php` (full file, 47 lines) — trait method returning a rules array, composed into the FormRequest's `rules()`:
```php
trait JobOrderValidationRules
{
    protected function jobOrderRules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            // ...
        ];
    }
}
```

---

### Migrations — `create_design_files_table`, `create_revision_logs_table`, `add_*_columns_to_*_table`

**Analog for creating a new domain table:** `database/migrations/2026_09_01_154403_create_job_orders_table.php` (full file, 34 lines):
```php
Schema::create('job_orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('queue_entry_id')->constrained()->cascadeOnDelete();
    $table->string('description');
    $table->string('type');
    $table->string('status')->default(JobOrderStatus::Intake->value);
    $table->string('file_path')->nullable();
    $table->timestamps();
});
```
`create_design_files_table` and `create_revision_logs_table` follow this exact shape: `$table->foreignId('job_order_id')->constrained()->cascadeOnDelete()`, string/timestamp columns per planner's schema, `$table->timestamps()`.

**Analog for additive columns on an existing table:** `database/migrations/2026_09_01_224409_add_validation_and_assignment_columns_to_job_orders_table.php` (full file, 32 lines) and `2026_09_01_224410_add_availability_columns_to_users_table.php` (full file, 30 lines):
```php
Schema::table('job_orders', function (Blueprint $table) {
    $table->foreignId('assigned_artist_id')->nullable()->after('status')
        ->constrained('users')->nullOnDelete();
    $table->text('validation_failure_reason')->nullable()->after('file_path');
});
```
```php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_available')->default(true)->after('last_activity_at');
    $table->timestamp('last_assigned_at')->nullable()->after('is_available');
});
```
New migrations follow this exact `->after(...)` ordering convention: `users` gets `artist_status` (string, default `'available'`) + `break_started_at` (nullable timestamp) after `last_assigned_at`; `job_orders` gets `consultation_notes` (nullable text) plus whatever Not-Appear/Forward ordering column the planner picks (RESEARCH.md Pitfall 6 suggests nullable `queue_deprioritized_at` timestamp).

---

### Factories — `DesignFileFactory.php`, `RevisionLogFactory.php`

**Analog:** `database/factories/JobOrderFactory.php` (full file, 96 lines)

**Base `definition()` shape** (lines 22-35):
```php
public function definition(): array
{
    return [
        'queue_entry_id' => QueueEntry::factory(),
        'description' => fake()->randomElement([...]),
        'type' => JobOrderType::TypeB->value,
        'status' => JobOrderStatus::Intake->value,
        'file_path' => null,
    ];
}
```
**`afterCreating()` state for a non-`#[Fillable]` column** (lines 84-94, `assigned()` state) — use this exact pattern for `DesignFileFactory::locked()`:
```php
public function assigned(): static
{
    return $this->afterCreating(function (JobOrder $jobOrder) {
        $artist = User::factory()->artist()->create();

        $jobOrder->forceFill([
            'status' => JobOrderStatus::Assigned->value,
            'assigned_artist_id' => $artist->id,
        ])->save();
    });
}
```
`DesignFileFactory::locked()` sets `locked_at` this same way (it's outside `#[Fillable]`, per the Model excerpt above).

---

### Routes — `routes/portals.php`, `routes/owner.php`

**Analog:** existing `artist.` group in `routes/portals.php` (lines 20-22) and `owner.` group in `routes/owner.php` (full file, 16 lines):
```php
Route::middleware(['auth', 'role:artist'])->prefix('artist')->name('artist.')->group(function () {
    Route::inertia('dashboard', 'artist/Dashboard')->name('dashboard');
});
```
```php
Route::middleware(['auth', 'role:owner,admin'])->prefix('owner')->name('owner.')->group(function () {
    Route::inertia('dashboard', 'owner/Dashboard')->name('dashboard');
    Route::patch('users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('users.deactivate');
});
```
Extend the `artist.` group with `job-orders` (index/next/forward/not-appear/consultation), `design/*` (send-for-review/approve/request-changes), `session-status`, `performance-report` routes — same `Route::patch`/`Route::get`/`Route::post` + `->name(...)` shape. Add `Route::patch('design-files/{designFile}/unlock', [DesignFileController::class, 'unlock'])->name('design-files.unlock')` inside the existing `owner.` group (Owner/Admin middleware already covers this, but `DesignFilePolicy::unlock()` narrows to Owner-only inside the FormRequest).

---

### Vue Pages/Components — `artist/Dashboard.vue`, `artist/DesignEditor.vue`, `artist/PerformanceReport.vue`, `config/nav/artist.ts`

**Analog for page shell + `defineOptions({ layout: {...} })` + nav config:** `resources/js/pages/frontline-staff/QueueList.vue` (lines 61-90) + `resources/js/config/nav/frontline-staff.ts` (full file, 22 lines):
```ts
import { LayoutGrid, ListOrdered, UserPlus } from '@lucide/vue';
import { dashboard, newVisit } from '@/routes/frontline-staff';
import { index as queueEntriesIndex } from '@/routes/frontline-staff/queue-entries';
import type { NavItem } from '@/types';

export const frontlineStaffNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'New Visit', href: newVisit(), icon: UserPlus },
    { title: 'Queue', href: queueEntriesIndex(), icon: ListOrdered },
];
```
```vue
defineOptions({
    layout: {
        navItems: frontlineStaffNavItems,
        breadcrumbs: [{ title: 'Queue', href: queueEntriesIndex() }],
    },
});
```
`config/nav/artist.ts` follows this exact shape (Dashboard / Design Editor / Performance Report nav items). The current placeholder `resources/js/pages/artist/Dashboard.vue` (full file, 32 lines) already has the `defineOptions({ layout: { navItems: [], breadcrumbs: [...] } })` scaffold — just needs `navItems: []` replaced with the new `artistNavItems` import and the placeholder `<p>` replaced with the real queue table.

**Analog for a Table + status-Badge + row-action-Form page:** `resources/js/pages/frontline-staff/QueueList.vue` (full file, 397 lines) — Table/TableHeader/TableBody structure (lines 103-116), status Badge conditionals (lines 189-207), and per-row mutating `<Form>` (lines 210-227):
```vue
<Form
    v-if="entry.status === 'waiting'"
    v-bind="QueueEntryController.callNext.form(entry.id)"
    :options="{ preserveScroll: true }"
    v-slot="{ processing }"
>
    <Button type="submit" :disabled="processing" :data-test="`call-next-${entry.id}-button`">
        Call Next
    </Button>
</Form>
```
`artist/Dashboard.vue`'s own-queue list (Next/Forward/Not-Appear buttons) copies this exact `<Form v-bind="Controller.action.form(id)">` per-row-button pattern.

**Analog for a file-mutation Dialog+Form component:** `resources/js/components/ReplaceJobOrderFileDialog.vue` (full file, 63 lines) — but per RESEARCH.md Pitfall 5, the Send-for-Review flow **cannot** reuse this uncontrolled `<Form>` + native `<input type="file">` shape because the export is a client-generated `Blob`/`File`, not a user-picked file. Use `useForm()` instead (RESEARCH.md Code Example §5, already vetted against this project's Inertia version):
```vue
const form = useForm<{ file: File | null }>({ file: null });

async function sendForReview(jobOrderId: number) {
    const dataUrl = editorRef.value!.exportPng();
    const blob = await (await fetch(dataUrl)).blob();
    form.file = new File([blob], 'design.png', { type: 'image/png' });

    form.post(`/artist/job-orders/${jobOrderId}/design/send-for-review`, {
        forceFormData: true,
        preserveScroll: true,
    });
}
```

**Analog for report page with date-range filters:** same analog family as `AuditTrailController`/`FilterAuditTrailRequest` above — `resources/js/pages/owner/AuditTrail.vue` was not read directly in this pass (file exists at `resources/js/pages/owner/AuditTrail.vue`), but its controller/request pair is fully verified above; `PerformanceReport.vue` should mirror whatever date-picker + submit-on-change form shape that page uses for its `from`/`to` filters (planner: read `resources/js/pages/owner/AuditTrail.vue` directly during implementation for the exact filter-form markup — it was not re-read here per the no-duplicate-read rule since its backend twin was already fully verified).

---

## Shared Patterns

### Audit Trail Coverage (automatic, zero extra code)
**Source:** `app/Observers/AuditObserver.php` (full file, 51 lines) + `app/Support/AuditLogger.php` (full file, 55 lines)
**Apply to:** `DesignFile`, `RevisionLog` models — just add `#[ObservedBy(AuditObserver::class)]` to the class attributes; every `create()`/`save()`/`update()`/`delete()` call is audited automatically. **Do not** write a manual `AuditLogger::recordMutation()` call anywhere in the new Actions/Controllers — this is the exact "don't hand-roll" item RESEARCH.md calls out for JOB-07.
```php
public function updated(Model $model): void
{
    AuditLogger::recordMutation(
        'updated',
        $model,
        $this->redact($model, array_intersect_key($model->getOriginal(), $model->getChanges())),
        $this->redact($model, $model->getChanges()),
    );
}
```

### Inertia Mutation Feedback
**Source:** `app/Http/Controllers/Owner/UserManagementController.php` lines 32-39
**Apply to:** every mutating Artist/Owner controller action in this phase
```php
Inertia::flash('toast', ['type' => 'success', 'message' => __(":name's account has been deactivated.", ['name' => $user->name])]);

return back();
```

### DB Transaction for Multi-Table Writes
**Source:** `app/Actions/JobOrder/AssignArtistToJobOrder.php` lines 29-51
**Apply to:** `RecordDesignRevision` (writes `design_files` + `revision_logs` + `job_orders.status` atomically, per D-07/D-08)
```php
return DB::transaction(function () use ($jobOrder) {
    // ...multiple forceFill()->save() / create() calls...
});
```

### State-Guard via `abort_unless`
**Source:** `app/Http/Controllers/FrontlineStaff/JobOrderController.php` line 24
**Apply to:** `DesignEditorController::sendForReview()` (JOB-06 lock enforcement — reject edits once `DesignApproved`)
```php
abort_unless($jobOrder->type === JobOrderType::TypeA, 422, 'Only a Type A job order accepts a file replacement.');
```
Equivalent for this phase: `abort_unless($jobOrder->status !== JobOrderStatus::DesignApproved, 422, 'This design is locked and cannot be edited.');`

### Private-Disk File Storage
**Source:** `app/Http/Controllers/FrontlineStaff/JobOrderController.php` line 30 (`$file->store('job-orders', 'local')`) + `config/filesystems.php` lines 33-39 (`'local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'serve' => true, ...]`)
**Apply to:** `RecordDesignRevision` (`$file->store('design-files', 'local')`) and `DesignEditorController::edit()` (`Storage::disk('local')->temporaryUrl(...)` to hand the browser a signed URL — no new storage config needed, `serve: true` is already set).

### Policy-Gated FormRequest `authorize()`
**Source:** `app/Http/Requests/Owner/DeactivateUserRequest.php` lines 13-16
**Apply to:** `UnlockDesignFileRequest` (Owner-only, JOB-07)
```php
public function authorize(): bool
{
    return $this->user()->can('deactivate', $this->route('user'));
}
```

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `resources/js/components/ToastImageEditor.vue` | component | file-I/O | No canvas/third-party-JS wrapper exists anywhere in this codebase — this is the first non-Inertia, imperative-API, `onMounted`/`onBeforeUnmount`-lifecycle component in the project. **Use RESEARCH.md Pattern 4 verbatim** (full worked example already written and verified against `tui-image-editor@3.15.3`'s actual source, including the blank-canvas `loadImage: undefined` behavior, `usageStatistics: false` opt-out, and `defineExpose({ exportPng })` imperative-API shape). Do not invent a different wrapper shape. |
| `resources/js/types/tui-image-editor.d.ts` | config | transform | No third-party JS library without bundled types exists in this project yet (`fabric` is a transitive dependency of `tui-image-editor`, never imported directly, so its own type situation is irrelevant here) — there is no existing `.d.ts` ambient-module-declaration precedent to copy. **Use RESEARCH.md Pitfall 2's two documented options**: either a minimal `declare module 'tui-image-editor'` ambient file, or a scoped `// @ts-expect-error` comment at the import site (RESEARCH.md's own Pattern 4 example uses the inline `@ts-expect-error` approach — prefer that unless `vue-tsc`/`vp check` proves it insufficient during the Wave-0 spike). |

## Metadata

**Analog search scope:** `app/Enums/`, `app/Models/`, `app/Policies/`, `app/Actions/JobOrder/`, `app/Http/Controllers/{FrontlineStaff,Owner}/`, `app/Http/Requests/{FrontlineStaff,Owner}/`, `app/Concerns/`, `app/Observers/`, `app/Support/`, `database/migrations/`, `database/factories/`, `routes/`, `resources/js/pages/`, `resources/js/components/`, `resources/js/config/nav/`, `tests/Feature/{FrontlineStaff,JobOrder,Owner}/`
**Files scanned:** 24 read in full (backend) + 3 read in full (frontend) + 2 read in full (tests) = 29 files read directly for this map, plus directory listings across `app/`, `database/`, `routes/`, `resources/js/`, `tests/Feature/`
**Pattern extraction date:** 2026-09-02

---

## PATTERN MAPPING COMPLETE

**Phase:** 04 - Artist Workflow & Design Editor
**Files classified:** 44
**Analogs found:** 42 / 44

### Coverage
- Files with exact analog: 32
- Files with role-match analog: 10
- Files with no analog: 2 (`ToastImageEditor.vue`, `tui-image-editor.d.ts` — both have a fully-specified RESEARCH.md fallback pattern instead of a codebase analog)

### Key Patterns Identified
- Every domain model in this phase (`DesignFile`, `RevisionLog`) is a mechanical copy of `JobOrder.php`'s `#[Fillable]` + `#[ObservedBy(AuditObserver::class)]` + `casts()` shape — zero deviation needed.
- Every mutating controller action follows `forceFill([...])->save()` + `Inertia::flash('toast', [...])` + `return back();` — no controller in this codebase returns JSON or throws past a validation/authorization boundary.
- Status-machine transitions (Next/Forward/Not-Appear, design review cycle, artist session status) all reuse the exact `DB::transaction` + `forceFill` + string-backed-enum-cast shape already proven in `AssignArtistToJobOrder` — this phase adds no new architectural shape for state transitions, only new enum values and new tables.
- `AuditObserver` coverage is automatic and must never be duplicated with a manual `AuditLogger` call — this satisfies JOB-07's "written to audit trail" requirement for free.
- The one genuinely new pattern this phase introduces (TOAST UI Image Editor Vue 3 wrapper) has no in-repo precedent by definition — RESEARCH.md's Pattern 4 is the authoritative, source-verified substitute for a codebase analog.

### File Created
`.planning/phases/04-artist-workflow-design-editor/04-PATTERNS.md`

### Ready for Planning
Pattern mapping complete. Planner can now reference analog patterns in PLAN.md files.
