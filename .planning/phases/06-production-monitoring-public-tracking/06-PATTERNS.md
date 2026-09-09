# Phase 6: Production Monitoring & Public Tracking - Pattern Map

**Mapped:** 2026-09-05
**Files analyzed:** 22
**Analogs found:** 20 / 22

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `database/migrations/xxxx_create_production_logs_table.php` | migration | CRUD | `database/migrations/2026_09_04_090002_create_transactions_table.php` | exact (child-of-job-order log table) |
| `app/Models/ProductionLog.php` | model | CRUD | `app/Models/Transaction.php` (+ `app/Models/RevisionLog.php` for nullable outcome/reason shape) | exact |
| `database/migrations/xxxx_add_number_and_due_at_to_job_orders_table.php` | migration | CRUD | `database/migrations/2026_09_04_110000_add_released_at_to_job_orders_table.php` | exact |
| `app/Enums/JobOrderStatus.php` (extend) | model/enum | CRUD | itself + `app/Enums/PaymentStatus.php` (casing precedent) | exact |
| `app/Models/JobOrder.php` (extend) | model | CRUD | itself | exact |
| `app/Actions/JobOrder/EnterProduction.php` (new, invokable) | service | event-driven | `app/Actions/JobOrder/AssignArtistToJobOrder.php` | exact |
| `app/Http/Controllers/ProductionStaff/ProductionBoardController.php` (new) | controller | request-response | `app/Http/Controllers/FrontlineStaff/QueueEntryController.php::index` | exact |
| `app/Http/Controllers/ProductionStaff/ProductionStageController.php` (new, advance+sendBack) | controller | request-response (state transition) | `app/Http/Controllers/Artist/JobOrderQueueController.php` (`next`/`forward`) + `app/Http/Controllers/FrontlineStaff/JobOrderReleaseController.php` | exact |
| `app/Http/Requests/ProductionStaff/AdvanceProductionStageRequest.php` | middleware/validation | request-response | `app/Http/Requests/FrontlineStaff/UpdateQueueEntryStatusRequest.php` (body-less) | exact |
| `app/Http/Requests/ProductionStaff/SendBackProductionStageRequest.php` | middleware/validation | request-response | `app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php` (trait + `rules()`) | role-match |
| `app/Concerns/ProductionLogValidationRules.php` (new trait) | utility | request-response | `app/Concerns/PricingValidationRules.php` | exact |
| `app/Http/Controllers/Public/TrackingController.php` (new) | controller | request-response | `app/Http/Controllers/Public/QueueDisplayController.php` (allowlist) + `app/Http/Controllers/FrontlineStaff/CustomerController.php::index` (dual lookup/result state) | exact |
| `app/Http/Requests/Public/TrackJobOrderRequest.php` (new) | middleware/validation | request-response | `app/Http/Requests/FrontlineStaff/SearchCustomersRequest.php` | role-match |
| `app/Http/Controllers/FrontlineStaff/DashboardController.php` (new — replaces `Route::inertia`) | controller | request-response | `app/Http/Controllers/Cashier/DashboardController.php` | exact |
| `routes/portals.php` (extend `production-staff` + `frontline-staff` groups) | route | request-response | itself (existing groups) | exact |
| `routes/web.php` (extend, new `GET /track`, `GET /track/{number}`) | route | request-response | itself (`queue-display`, `design-review` public routes) | exact |
| `resources/js/config/nav/production-staff.ts` (new) | config | — | `resources/js/config/nav/accounting-staff.ts` | exact |
| `resources/js/pages/production-staff/Dashboard.vue` (replace placeholder) | component | streaming (poll) | `resources/js/pages/frontline-staff/QueueList.vue` (Table) + `resources/js/pages/public/QueueDisplay.vue` (`usePoll`) + `resources/js/pages/owner/SystemConfiguration.vue` (`Tabs`) + `resources/js/pages/artist/PerformanceReport.vue` (stat cards) | exact (composite) |
| `resources/js/pages/frontline-staff/Dashboard.vue` (replace placeholder) | component | streaming (poll) | `resources/js/pages/frontline-staff/QueueList.vue` (Table + release Form) | exact |
| `resources/js/pages/frontline-staff/QueueList.vue` (extend: banner + poll) | component | streaming (poll) | itself + `resources/js/pages/public/QueueDisplay.vue` (`usePoll`) | exact |
| `resources/js/pages/public/Tracking.vue` (new) | component | streaming (poll) | `resources/js/pages/public/QueueDisplay.vue` (dark shell + poll) + `resources/js/pages/public/DesignReview.vue` (multi-state Card) + `resources/js/pages/cashier/JobOrderPayment.vue` (`Spinner`) | exact (composite) |
| `resources/js/components/TrackingQrCode.vue` (new) | component | transform | `resources/js/components/PaymentQrCode.vue` | exact |
| `resources/js/pages/cashier/Receipt.vue` (extend: QR + JO number row) | component | transform | itself + `resources/js/components/PaymentQrCode.vue` | exact |
| `resources/js/pages/cashier/Dashboard.vue` (extend: JO number column) | component | CRUD | itself | exact |
| `resources/js/pages/owner/CreditRequests.vue` (extend: JO number column) | component | CRUD | itself | exact |

## Pattern Assignments

### `database/migrations/xxxx_create_production_logs_table.php`

**Analog:** `database/migrations/2026_09_04_090002_create_transactions_table.php` (full file read above)

```php
Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('job_order_id')->constrained()->cascadeOnDelete();
    $table->string('type');
    $table->string('payment_method');
    $table->decimal('amount', 10, 2);
    $table->string('status')->default('completed');
    $table->string('reference_number')->nullable();
    $table->string('paymongo_payment_intent_id')->nullable()->unique();
    $table->foreignId('recorded_by')->constrained('users');
    $table->timestamp('confirmed_at')->nullable();
    $table->timestamps();
});
```

**Delta for `production_logs`:** `job_order_id` FK identical. Replace `type`/`payment_method`/`amount`/`reference_number`/`paymongo_payment_intent_id` with `from_status` (string, nullable — null on the very first system-written row) and `to_status` (string, not null). `recorded_by` must be **nullable** here (unlike Transaction's non-nullable version) because D-09 requires the very first row to be written by "system" with no authenticated actor — `$table->foreignId('recorded_by')->nullable()->constrained('users')`. Add `$table->text('reason')->nullable()` for D-11's mandatory-on-backward-move reason. No `confirmed_at`-equivalent needed. `#[ObservedBy(AuditObserver::class)]` on the model gives audit coverage automatically — do not hand-roll a duplicate log.

---

### `app/Models/ProductionLog.php`

**Analog:** `app/Models/Transaction.php` (full file read above) for the `#[Fillable]` + `#[ObservedBy]` + `casts()` + `belongsTo` shape; `app/Models/RevisionLog.php` for the nullable-outcome-field precedent (`outcome` there ≈ `reason` here).

```php
#[Fillable(['job_order_id', 'type', 'payment_method', 'amount', 'status', 'reference_number', 'paymongo_payment_intent_id', 'recorded_by'])]
#[ObservedBy(AuditObserver::class)]
class Transaction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            ...
        ];
    }

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
```

**Delta:** `#[Fillable(['job_order_id', 'from_status', 'to_status', 'reason', 'recorded_by'])]`. Cast `from_status`/`to_status` to `JobOrderStatus::class` (both nullable-safe — Laravel's enum cast returns `null` through for a `null` column value on `from_status`). Keep `jobOrder(): BelongsTo` and `recordedBy(): BelongsTo` exactly as Transaction's shape (note: `recordedBy` must tolerate a null FK — Eloquent's `belongsTo` already returns `null` gracefully). Add this new relation on `JobOrder`: `productionLogs(): HasMany` mirroring `JobOrder::transactions()`.

---

### `database/migrations/xxxx_add_number_and_due_at_to_job_orders_table.php`

**Analog:** `database/migrations/2026_09_04_110000_add_released_at_to_job_orders_table.php` (full file read above — single-column additive pattern) and `database/migrations/2026_09_04_090001_add_payment_columns_to_job_orders_table.php` (multi-column additive pattern with `->after()`).

```php
public function up(): void
{
    Schema::table('job_orders', function (Blueprint $table) {
        $table->timestamp('released_at')->nullable()->after('cancelled_at');
    });
}

public function down(): void
{
    Schema::table('job_orders', function (Blueprint $table) {
        $table->dropColumn('released_at');
    });
}
```

**Delta:** Add `$table->string('number')->nullable()->unique()->after('id')` (nullable so pre-existing rows are valid before/without backfill per CONTEXT.md's discretion note — planner decides whether a same-migration `DB::table('job_orders')->orderBy('created_at')->get()->each(...)` backfill loop runs before the `unique()` is safe to enforce, or whether uniqueness is added in a second step) and `$table->timestamp('due_at')->nullable()->after('status')`. Follow the existing convention: every additive migration on `job_orders` in this codebase (Phases 3/4/5) only ever adds columns in `up()` and drops them in `down()` — never touches existing columns.

---

### `app/Enums/JobOrderStatus.php` (extend)

**Analog:** itself (full file read above) — string-backed, `TitleCase` keys, `snake_case` values, no methods.

```php
enum JobOrderStatus: string
{
    case Intake = 'intake';
    case ValidationFailed = 'validation_failed';
    case ReadyForProduction = 'ready_for_production';
    case Assigned = 'assigned';
    case InConsultation = 'in_consultation';
    case InDesign = 'in_design';
    case PendingReview = 'pending_review';
    case DesignApproved = 'design_approved';
}
```

**Delta:** Append exactly four cases in board order, matching the discretion note's naming: `case ForProduction = 'for_production';`, `case Printing = 'printing';`, `case QualityCheck = 'quality_check';`, `case ReadyForPickup = 'ready_for_pickup';`. No enum methods exist on this class anywhere in the codebase (`grep` confirms zero `public function` inside `JobOrderStatus`) — do not add a `label()`/`color()` method here; badge label mapping lives in the Vue layer per the existing convention seen in `QueueList.vue`'s inline `v-if`/`v-else-if` chain, not on the PHP enum.

---

### `app/Actions/JobOrder/EnterProduction.php` (new, invokable Action)

**Analog:** `app/Actions/JobOrder/AssignArtistToJobOrder.php` (full file read above) — the exact precedent for "a domain event auto-fires a status transition + timestamp stamp, invoked from inside another controller's flow, under `DB::transaction()` + `lockForUpdate()` where concurrency matters."

```php
class AssignArtistToJobOrder
{
    public function __invoke(JobOrder $jobOrder): ?User
    {
        if ($jobOrder->type !== JobOrderType::TypeB) {
            return null;
        }

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
    }
}
```

**Delta:** `__invoke(JobOrder $jobOrder): void` — no locking needed here (single-row write, not a shared-resource claim like artist assignment), so a plain `forceFill()->save()` plus a `ProductionLog::create()` inside one `DB::transaction()` is enough:

```php
public function __invoke(JobOrder $jobOrder): void
{
    DB::transaction(function () use ($jobOrder): void {
        $dueAt = now()->addDays(SystemConfiguration::getInt('default_sla_days', 3));

        $jobOrder->forceFill([
            'status' => JobOrderStatus::ForProduction,
            'due_at' => $dueAt,
        ])->save();

        ProductionLog::create([
            'job_order_id' => $jobOrder->id,
            'from_status' => null, // D-09: system-authored first row
            'to_status' => JobOrderStatus::ForProduction,
            'reason' => null,
            'recorded_by' => null, // actor = system
        ]);
    });
}
```

Call this from wherever Type A validation passes (`ValidateJobOrderFile` outcome path in `QueueEntryController::applyIntakeOutcome`, currently sets `ReadyForProduction` directly) and wherever `DesignEditorController::approve()` sets `DesignApproved` — D-08 requires **automatic** entry the moment either status is reached, meaning `ForProduction` should be set as an immediate follow-on write, not a separately-triggered action. Read `SystemConfiguration::getInt('default_sla_days', 3)` exactly the way `CancellationController::store` reads `SystemConfiguration::getFloat('cancellation_fee_amount', 500.0)` (both already-seeded `business_rules` keys, same static-facade call shape).

---

### `app/Http/Controllers/ProductionStaff/ProductionBoardController.php` (new)

**Analog:** `app/Http/Controllers/FrontlineStaff/QueueEntryController.php::index` (full file read above) for the `Inertia::render` + eager-load + explicit `get([...])` column list shape.

```php
public function index(Request $request): Response
{
    return Inertia::render('frontline-staff/QueueList', [
        'queueEntries' => QueueEntry::query()
            ->with([
                'customer:id,name',
                'jobOrders:id,queue_entry_id,description,type,status,validation_failure_reason,assigned_artist_id,payment_status,released_at',
                'jobOrders.assignedArtist:id,name',
            ])
            ->whereDate('queue_date', QueueEntry::currentBusinessDate())
            ->orderBy('queue_number')
            ->get(['id', 'customer_id', 'queue_number', 'status']),
    ]);
}
```

**Delta:**

```php
public function index(): Response
{
    return Inertia::render('production-staff/Dashboard', [
        'jobOrders' => JobOrder::query()
            ->whereIn('status', [
                JobOrderStatus::ForProduction->value,
                JobOrderStatus::Printing->value,
                JobOrderStatus::QualityCheck->value,
                JobOrderStatus::ReadyForPickup->value,
            ])
            ->whereNull('released_at') // D-12: board-exit filter
            ->with('queueEntry.customer:id,name')
            ->orderByRaw('due_at IS NULL, due_at ASC') // Rush-first, per Copywriting Contract default sort
            ->get(['id', 'number', 'description', 'status', 'due_at', 'queue_entry_id']),
    ]);
}
```

Never eager-load or select payment columns — the UI-SPEC explicitly bars a payment hint on this surface ("Deliberately absent from the board — payment"). Route registration follows the `frontline-staff.` group's naming exactly (see routes section below).

---

### `app/Http/Controllers/ProductionStaff/ProductionStageController.php` (new — `advance` + `sendBack`)

**Analogs:** `app/Http/Controllers/Artist/JobOrderQueueController.php::forward` (sequential single-stage move, ownership guard) and `app/Http/Controllers/FrontlineStaff/JobOrderReleaseController.php::store` (state-gate via `abort_if`/`abort_unless`, `Inertia::flash('toast', ...)`).

```php
public function forward(UpdateJobOrderQueuePositionRequest $request, JobOrder $jobOrder): RedirectResponse
{
    abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, '...');
    abort_unless($jobOrder->status === JobOrderStatus::InConsultation || $jobOrder->status->value === 'in_design', 422, '...');

    $jobOrder->forceFill([
        'status' => JobOrderStatus::Assigned,
        'queue_deprioritized_at' => now(),
    ])->save();

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Forwarded. ...')]);

    return back();
}
```

```php
public function store(ReleaseJobOrderRequest $request, JobOrder $jobOrder): RedirectResponse
{
    abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
    abort_if($jobOrder->released_at !== null, 422, __('This job order has already been released.'));
    abort_unless(in_array($jobOrder->payment_status, [...], true), 422, match(...));

    $jobOrder->forceFill(['released_at' => Carbon::now()])->save();

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Released to customer.')]);

    return back();
}
```

**Delta — `advance()` (D-10, strictly one stage forward, server-authoritative sequence):**

```php
private const SEQUENCE = [
    JobOrderStatus::ForProduction,
    JobOrderStatus::Printing,
    JobOrderStatus::QualityCheck,
    JobOrderStatus::ReadyForPickup,
];

public function advance(AdvanceProductionStageRequest $request, JobOrder $jobOrder): RedirectResponse
{
    $currentIndex = array_search($jobOrder->status, self::SEQUENCE, true);

    abort_if($currentIndex === false, 422, __('This job order is not on the production board.'));
    abort_if($currentIndex === array_key_last(self::SEQUENCE), 422, __('This job order is already at the last stage.'));

    $nextStatus = self::SEQUENCE[$currentIndex + 1];

    DB::transaction(function () use ($jobOrder, $nextStatus, $request): void {
        $from = $jobOrder->status;
        $jobOrder->forceFill(['status' => $nextStatus])->save();

        ProductionLog::create([
            'job_order_id' => $jobOrder->id,
            'from_status' => $from,
            'to_status' => $nextStatus,
            'reason' => null,
            'recorded_by' => $request->user()->id,
        ]);
    });

    Inertia::flash('toast', ['type' => 'success', 'message' => __(':number moved to :stage.', [...])]);

    return back();
}
```

**Delta — `sendBack()` (D-11, exactly one stage back, mandatory reason):** identical shape but moves to `self::SEQUENCE[$currentIndex - 1]`, `abort_if($currentIndex === 0, ...)` (nothing precedes `ForProduction`), and writes `'reason' => $request->validated('reason')` from `SendBackProductionStageRequest`. The **stale-advance handling** the UI-SPEC calls for ("the server is authoritative...surface the 'already moved on' Alert") is exactly what `abort_if($currentIndex === false, ...)` already gives for free if the poll re-fetched a status the client's stale copy of the row doesn't expect — no extra optimistic-locking column is needed; re-deriving `$currentIndex` from the freshly-loaded `$jobOrder->status` on every request (route-model-bound, so always current) is the same T-04-02 "never trust a client-side-only decision" precedent `JobOrderQueueController::oldestEligibleId` documents.

---

### `app/Http/Requests/ProductionStaff/AdvanceProductionStageRequest.php`

**Analog:** `app/Http/Requests/FrontlineStaff/UpdateQueueEntryStatusRequest.php` (full file read above).

```php
class UpdateQueueEntryStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            //
        ];
    }
}
```

**Delta:** Identical shape — body-less, target stage implied by `array_search` off the current status server-side, not a client-supplied value. Add the docblock note explaining why (mirrors the analog's own docblock verbatim in spirit).

---

### `app/Http/Requests/ProductionStaff/SendBackProductionStageRequest.php` + `app/Concerns/ProductionLogValidationRules.php`

**Analog:** `app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php` + `app/Concerns/PricingValidationRules.php` (both fully read above) — the concrete Form-Request-uses-Concern-trait pair with a real field-level rule (not body-less).

```php
// app/Concerns/PricingValidationRules.php
trait PricingValidationRules
{
    protected function pricingRules(): array
    {
        return [
            'pricing_entry_id' => ['required', 'exists:pricing_database,id'],
            ...
        ];
    }
}

// app/Http/Requests/Cashier/SavePricingAndPaymentRequest.php
class SavePricingAndPaymentRequest extends FormRequest
{
    use PaymentValidationRules, PricingValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->route('jobOrder')->total_amount !== null) {
            return $this->paymentRules();
        }

        return array_merge($this->pricingRules(), $this->paymentRules());
    }
}
```

**Delta:**

```php
// app/Concerns/ProductionLogValidationRules.php
trait ProductionLogValidationRules
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function sendBackReasonRules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
```

```php
// app/Http/Requests/ProductionStaff/SendBackProductionStageRequest.php
class SendBackProductionStageRequest extends FormRequest
{
    use ProductionLogValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->sendBackReasonRules();
    }
}
```

This produces the Copywriting Contract's "Enter a reason before sending this job order back." error automatically via Laravel's default `required` message resolution, rendered client-side through the existing `InputError` component — no custom message override needed unless the planner wants the exact wording, in which case add a `messages()` override (no existing Form Request in this codebase overrides `messages()`, so if the planner needs verbatim copy, use `->max:1000` default first and only add `messages()` if the Copywriting Contract's exact string must be forced).

---

### `app/Http/Controllers/Public/TrackingController.php` (new)

**Analog:** `app/Http/Controllers/Public/QueueDisplayController.php` (full file read above, the PII-boundary allowlist) + `app/Http/Controllers/FrontlineStaff/CustomerController.php::index` (full file read above, the "empty state vs. result state driven by a filled request param" shape).

```php
// QueueDisplayController — the allowlist doctrine
class QueueDisplayController extends Controller
{
    /**
     * Security boundary (D-09, T-02-15): the column list below is the only
     * thing standing between this route and a PII leak — never widen it to
     * a full model, never eager-load or reference the visit-owner relation.
     */
    public function index(): Response
    {
        return Inertia::render('public/QueueDisplay', [
            'queueEntries' => QueueEntry::query()
                ->whereDate('queue_date', QueueEntry::currentBusinessDate())
                ->orderBy('queue_number')
                ->get(['id', 'queue_number', 'status']),
        ]);
    }
}
```

```php
// CustomerController::index — dual lookup/result state off a request param
public function index(SearchCustomersRequest $request): Response
{
    $customers = collect();

    if ($request->filled('q')) {
        ...
    }

    return Inertia::render('frontline-staff/NewVisit', [
        'customers' => $customers,
        'filters' => $request->only(['q']),
        ...
    ]);
}
```

**Delta:**

```php
class TrackingController extends Controller
{
    /**
     * D-02/TRACK-02 PII boundary, one level stricter than QueueDisplayController:
     * the mapping from internal JobOrderStatus to a public label happens here,
     * server-side — the raw enum value must never reach the client, and no
     * column besides `number` and the mapped label ever joins the payload.
     */
    public function show(?string $number = null): Response
    {
        if ($number === null) {
            return Inertia::render('public/Tracking', ['result' => null]);
        }

        $jobOrder = JobOrder::query()->where('number', $number)->first(['status', 'released_at', 'number']);

        return Inertia::render('public/Tracking', [
            'result' => $jobOrder === null
                ? ['found' => false]
                : ['found' => true, 'number' => $jobOrder->number, 'stage' => $this->publicStage($jobOrder)],
        ]);
    }

    private function publicStage(JobOrder $jobOrder): string
    {
        if ($jobOrder->released_at !== null) {
            return 'Completed';
        }

        return match ($jobOrder->status) {
            JobOrderStatus::ForProduction => 'For Production',
            JobOrderStatus::Printing => 'Printing',
            JobOrderStatus::QualityCheck => 'Quality Check',
            JobOrderStatus::ReadyForPickup => 'Ready for Pickup',
            default => 'In Progress',
        };
    }
}
```

Route as `GET /track` (form, no `{number}`) and `GET /track/{number}` (result) per the UI-SPEC's recommended shape — both hit the same controller method with an optional route parameter, exactly mirroring how `CustomerController::index` handles "form with no query vs. form with a filled query" as one action rather than two.

---

### `app/Http/Requests/Public/TrackJobOrderRequest.php`

**Analog:** `app/Http/Requests/FrontlineStaff/SearchCustomersRequest.php` (full file read above).

```php
class SearchCustomersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            ...
        ];
    }
}
```

**Delta:** `'number' => ['required', 'string', 'regex:/^JO-\d{4}-\d+$/']` — matches the Copywriting Contract's malformed-input error ("Enter a job order number like JO-2026-0001.") via a custom `messages()` override, the one departure from the analog's default-message reliance since the regex failure message needs the exact placeholder format. Only needed if the form-submit path is a POST that redirects to `GET /track/{number}`; if the planner instead makes the lookup form a plain `<Form method="get">` action, this Form Request may be unnecessary and validation can live inline via a `Route::get('track/{number}')` regex constraint (`->where('number', 'JO-\d{4}-\d+')`) instead — planner's call, both are established conventions in this codebase (regex route constraints are not yet used anywhere, so the Form Request approach is the safer, more idiomatic choice here).

---

### `app/Http/Controllers/FrontlineStaff/DashboardController.php` (new)

**Analog:** `app/Http/Controllers/Cashier/DashboardController.php` (full file read above).

```php
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('cashier/Dashboard', [
            'jobOrders' => JobOrder::query()
                ->whereIn('status', [...])
                ->whereNull('cancelled_at')
                ->with([...])
                ->orderBy('created_at')
                ->get(['id', 'description', 'status', ...]),
            'cancellationFeeAmount' => SystemConfiguration::getFloat(...),
        ]);
    }
}
```

**Delta:**

```php
public function index(): Response
{
    return Inertia::render('frontline-staff/Dashboard', [
        'readyForPickup' => JobOrder::query()
            ->where('status', JobOrderStatus::ReadyForPickup->value)
            ->whereNull('released_at')
            ->with('queueEntry.customer:id,name')
            ->orderBy('updated_at')
            ->get(['id', 'number', 'description', 'payment_status', 'queue_entry_id', 'updated_at']),
    ]);
}
```

This replaces `Route::inertia('dashboard', 'frontline-staff/Dashboard')` in `routes/portals.php` with `Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard')` — the exact same swap pattern the UI-SPEC documents for `production-staff.dashboard` ("Route name ... unchanged — only `Route::inertia` is swapped for a controller").

---

## Shared Patterns

### Sequential-number generator (job order number, D-04)

**Source:** `app/Models/QueueEntry.php::nextForBusinessDay()` (full file read above), consumed by `app/Http/Controllers/FrontlineStaff/QueueEntryController.php::store` (full file read above).

```php
public static function nextForBusinessDay(string $businessDate): int
{
    return DB::transaction(fn (): int => (static::query()
        ->whereDate('queue_date', $businessDate)
        ->lockForUpdate()
        ->max('queue_number') ?? 0) + 1);
}
```

```php
// call site
$queueEntry = DB::transaction(function () use ($request): QueueEntry {
    $businessDate = QueueEntry::currentBusinessDate();
    $number = QueueEntry::nextForBusinessDay($businessDate);

    $entry = QueueEntry::create([...'queue_number' => $number, ...]);
    ...
    return $entry;
});
```

**Apply to:** a new `JobOrder::nextNumberForYear(int $year): int` static method, keyed on year instead of business date (D-04: "keyed on year rather than date"):

```php
public static function nextNumberForYear(int $year): string
{
    $sequence = DB::transaction(fn (): int => (static::query()
        ->where('number', 'like', "JO-{$year}-%")
        ->lockForUpdate()
        ->count()) + 1);
    // or: max on a derived numeric substring if collision-safety under
    // concurrent deletes matters more than a COUNT-based approach — the
    // analog itself uses max(), so prefer max() over count() for parity:
    // ->max(DB::raw("CAST(SUBSTR(number, -4) AS INTEGER)")) style, but
    // note SQLite/MySQL portability of SUBSTR/CAST differs — verify against
    // the target DB driver before committing to one approach.

    return sprintf('JO-%d-%04d', $year, $sequence);
}
```

Call it exactly where `QueueEntry::nextForBusinessDay` is called — inside the same `DB::transaction()` that creates the `JobOrder` row, so the lock and the insert are atomic. The year boundary uses `Asia/Manila`, narrowly scoped exactly like `QueueEntry::currentBusinessDate()` does (D-16), not `config('app.timezone')`:

```php
public static function currentBusinessDate(): string
{
    return now()->timezone('Asia/Manila')->toDateString();
}
```

### System-config read pattern (D-06's `default_sla_days`)

**Source:** `app/Models/SystemConfiguration.php::getInt()`/`getFloat()` (full file read above), consumed by `app/Http/Controllers/Cashier/CancellationController.php`:

```php
$fee = SystemConfiguration::getFloat('cancellation_fee_amount', 500.0);
```

**Apply to:** `SystemConfiguration::getInt('default_sla_days', 3)` inside `EnterProduction`. Never hardcode `3` as a literal fallback constant elsewhere — this one call site is the only place the default should appear outside the seeder.

### Audit coverage (all new/modified models)

**Source:** `app/Observers/AuditObserver.php` (full file read above), attached via the `#[ObservedBy(AuditObserver::class)]` PHP attribute on every domain model (`Transaction`, `JobOrder`, `QueueEntry`, `SystemConfiguration`, `RevisionLog` all carry it).

**Apply to:** `ProductionLog` gets `#[ObservedBy(AuditObserver::class)]` — no custom audit code needed; `created`/`updated`/`deleted` hooks fire automatically and write to the structurally-append-only `audit_trail` table. `JobOrder`'s existing attribute already covers the `number`/`due_at`/`status` column writes this phase adds.

### `Inertia::flash('toast', [...])` for mutation feedback

**Source:** every mutating controller in the codebase (e.g. `JobOrderReleaseController::store`, `CancellationController::store`, `QueueEntryController::callNext`):

```php
Inertia::flash('toast', [
    'type' => 'success',
    'message' => __('Released to customer.'),
]);

return back();
```

**Apply to:** `ProductionStageController::advance`/`sendBack` toasts, per the Copywriting Contract's "{JO number} moved to {stage}." / "{JO number} sent back to {stage}." strings.

### Wayfinder-generated route/action helpers (frontend)

**Source:** every existing page — e.g. `resources/js/pages/frontline-staff/QueueList.vue`:

```typescript
import JobOrderReleaseController from '@/actions/App/Http/Controllers/FrontlineStaff/JobOrderReleaseController';
...
<Form v-bind="JobOrderReleaseController.store.form(jobOrder.id)" ...>
```

**Apply to:** every new controller action in this phase gets a generated Wayfinder action (`ProductionStageController.advance.form(jobOrder.id)`, `ProductionStageController.sendBack.form(jobOrder.id)`, `TrackingController.show.url(number)`). Run `npm run dev`/Wayfinder generation after adding routes — never hand-write a URL string in any new `.vue` file.

### `usePoll(5000, { only: [...] })` (D-14, all three polled surfaces)

**Source:** `resources/js/pages/public/QueueDisplay.vue` (full file read above):

```typescript
import { Head, usePoll } from '@inertiajs/vue3';

usePoll(5000, { only: ['queueEntries'] });
```

**Apply to:**
- Production Board: `usePoll(5000, { only: ['jobOrders'] })`
- Frontline Dashboard: `usePoll(5000, { only: ['readyForPickup'] })`
- Frontline QueueList.vue (banner extension): `usePoll(5000, { only: ['readyForPickupCount'] })` or similar, added alongside the existing static `queueEntries` prop — do not poll the whole page, only the count needed for the banner
- Public Tracking result state: `usePoll(5000, { only: ['result'] })`, started only when a result is being shown (not on the bare lookup form) — gate the `usePoll` call behind `onMounted`/a `watch` on the state, or call it unconditionally but rely on Inertia's partial-reload semantics no-op'ing usefully; the DesignReview.vue analog has no poll to copy for this conditional-start nuance, so this is a genuinely new wiring the planner should verify against Inertia v3's `usePoll` API (`search-docs` recommended per project rules before finalizing).

### `Tabs` component (Production Board stage filter)

**Source:** `resources/js/pages/owner/SystemConfiguration.vue`:

```vue
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
...
<Tabs default-value="security" class="w-full">
    <TabsList>
        <TabsTrigger v-for="group in groups" :key="group.value" :value="group.value">
            {{ group.label }}
        </TabsTrigger>
    </TabsList>
    <TabsContent v-for="group in groups" :key="group.value" :value="group.value" class="space-y-6 pt-4">
        ...
    </TabsContent>
</Tabs>
```

**Apply to:** the board's "All / Rush / For Production / Printing / Quality Check / Ready for Pickup" filter tabs — client-side filtering over the already-polled array per the UI-SPEC (no `TabsContent`-per-server-fetch; use a single `TabsList` with a `ref` tracking the active tab, and a `computed` filtering `props.jobOrders` locally, since this analog's `TabsContent` pattern is for statically-different content per tab, not what Phase 6 needs — the board's rows are the same array filtered, not different content blocks).

### `Alert` / `AlertTitle` / `AlertDescription` (rush banner, ready-for-pickup banner, not-found error)

**Source:** `resources/js/components/AlertError.vue` (full file read above) is the only existing consumer of the raw `ui/alert` primitives in this codebase — no page currently renders a bare `<Alert>` directly, so this is the closest available shape:

```vue
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
...
<Alert variant="destructive">
    <AlertCircle class="size-4" />
    <AlertTitle>{{ title }}</AlertTitle>
    <AlertDescription>
        <ul class="list-inside list-disc text-sm">
            <li v-for="(error, index) in uniqueErrors" :key="index">{{ error }}</li>
        </ul>
    </AlertDescription>
</Alert>
```

**Apply to:** the rush banner (amber, `Zap` icon, `variant="default"` never `"destructive"` per the UI-SPEC's explicit "a rush order is a priority signal, not an error" rule), the Frontline ready-for-pickup banner (`PackageCheck` icon), and the public tracking not-found `Alert`. This is a genuinely new visual pattern for the codebase (see "No Analog Found" below) — `AlertError.vue` is the structural template to copy (icon + `AlertTitle` + `AlertDescription`), but every one of this phase's alerts needs `variant="default"` styling with custom Tailwind color utilities layered on top (`text-amber-600 dark:text-amber-400` etc.) rather than `variant="destructive"`, since `AlertError.vue` is the only precedent and it happens to be the destructive case.

### `Spinner` (public tracking lookup in-flight state)

**Source:** `resources/js/pages/cashier/JobOrderPayment.vue`:

```vue
import { Spinner } from '@/components/ui/spinner';
...
<Button ... @click="checkPaymentStatus">
    <Spinner v-if="checkingPaymentStatus" />
    Check Payment Status
</Button>
```

**Apply to:** the public tracking "Check Status" submit button — `<Spinner v-if="processing" />` inside the `<Form>` `v-slot="{ processing }"` binding, same shape as every other submit button in the codebase, just with the `Spinner` prepended per this analog.

### Job order number identifier swap (`tabular-nums`)

**Source:** `resources/js/pages/public/QueueDisplay.vue` numeric treatment (`class="tabular-nums"` convention referenced in UI-SPEC §6, applied to `queue_number` displays across the codebase).

**Apply to:** every table column in `cashier/Dashboard.vue`, `cashier/Receipt.vue`, `frontline-staff/QueueList.vue`, `owner/CreditRequests.vue` that starts rendering `jobOrder.number` — wrap in `<span class="tabular-nums">{{ jobOrder.number ?? '—' }}</span>`, with the em-dash fallback in `text-muted-foreground` for rows with no backfilled number (planner's call per CONTEXT.md discretion).

---

## No Analog Found

| File / Pattern | Role | Data Flow | Reason |
|---|---|---|---|
| Rush/ready-for-pickup/not-found `Alert` banners | component | streaming (poll-driven) | No page in this codebase currently renders a bare `ui/alert` `Alert` — only `AlertError.vue` (destructive-only wrapper) and `AlertDialog` (a different, confirmation-modal component) exist. Use `AlertError.vue`'s structural shape (icon + `AlertTitle` + `AlertDescription`) as the template, but style per the UI-SPEC's non-destructive amber/success rules — RESEARCH.md would normally carry an external reference here, but this phase has none; the UI-SPEC.md's Copywriting/Color Contract sections are the authoritative source instead. |
| Conditional `usePoll` start (public tracking result-state-only polling) | component | streaming | Every existing `usePoll` call in the codebase (`QueueDisplay.vue`) runs unconditionally at component mount — no existing page starts/stops polling based on a client-side state toggle. Verify against Inertia v3 docs (`search-docs`) before implementation; this is new wiring, not a copy-paste. |
| `production_logs.recorded_by` nullable FK (system actor) | model/migration | CRUD | Every existing "recorded_by"-style FK in the codebase (`transactions.recorded_by`) is **non-nullable** because a human always initiates those rows. `ProductionLog`'s first row (D-09, written by "system") is the first genuinely-nullable actor FK in the schema — no existing migration or model demonstrates this exact nullable-FK-to-users shape to copy verbatim; the delta section above documents the concrete change (`->nullable()->constrained('users')`), but there is no sibling file that already does it. |

## Metadata

**Analog search scope:** `app/Models/`, `app/Http/Controllers/`, `app/Http/Requests/`, `app/Concerns/`, `app/Actions/`, `app/Enums/`, `app/Observers/`, `database/migrations/`, `database/seeders/`, `routes/`, `resources/js/pages/`, `resources/js/components/`, `resources/js/config/nav/`
**Files scanned:** ~70 (targeted reads/greps, not exhaustive directory dump)
**Pattern extraction date:** 2026-09-05
