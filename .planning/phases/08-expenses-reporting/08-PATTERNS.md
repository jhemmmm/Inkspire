# Phase 8: Expenses & Reporting - Pattern Map

**Mapped:** 2026-09-10
**Files analyzed:** 29
**Analogs found:** 24 / 29 (5 are genuinely new territory — see §No Analog Found)

Every analog below was read directly from this repository (not inferred from RESEARCH.md's excerpts) — line numbers refer to the current file state at mapping time. `.ai/rules/` does not exist in this repo (confirmed by 08-CONTEXT.md's own note) — `CLAUDE.md` is the whole written convention set, already loaded.

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `database/migrations/xxxx_create_expenses_table.php` | migration | CRUD | `database/migrations/2026_09_04_090002_create_transactions_table.php` | exact (new-table shape) |
| `app/Models/Expense.php` | model | CRUD | `app/Models/Transaction.php` | exact |
| `database/factories/ExpenseFactory.php` | test/factory | CRUD | `database/factories/TransactionFactory.php` | exact |
| `database/seeders/SystemConfigurationSeeder.php` | config | CRUD | **no change needed** — `expense_categories` already seeded (D-14) | n/a |
| `app/Concerns/ExpenseValidationRules.php` | utility | request-response | `app/Concerns/AccountsReceivableValidationRules.php` | exact |
| `app/Http/Requests/AccountingStaff/StoreExpenseRequest.php` | middleware/validation | request-response | `app/Http/Requests/AccountingStaff/RequestWriteOffRequest.php` | exact |
| `app/Http/Requests/AccountingStaff/UpdateExpenseRequest.php` | middleware/validation | request-response | `app/Http/Requests/AccountingStaff/RequestWriteOffRequest.php` | exact |
| `app/Http/Requests/AccountingStaff/VoidExpenseRequest.php` | middleware/validation | request-response | `app/Http/Requests/AccountingStaff/RequestWriteOffRequest.php` | exact |
| `app/Http/Controllers/AccountingStaff/ExpenseController.php` (index, store, update) | controller | request-response (CRUD) | `app/Http/Controllers/Cashier/ReconciliationController.php` (create) + `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php` (index/list shape) | exact (composite) |
| `app/Http/Controllers/AccountingStaff/ExpenseVoidController.php` (or `ExpenseController::void`) | controller | request-response (state transition) | `app/Http/Controllers/AccountingStaff/WriteOffRequestController.php` | exact |
| `routes/portals.php` (extend `accounting-staff` group) | route | request-response | itself (existing group additions) | exact |
| `app/Http/Requests/Reports/FilterReportRequest.php` | middleware/validation | request-response | `app/Http/Requests/Artist/PerformanceReportFilterRequest.php` | exact |
| `app/Services/Reports/ReportRegistry.php` (or `app/Reports/ReportRegistry.php`) | service | request-response | none in-repo — new territory | partial (see below) |
| `app/Http/Controllers/Reports/ReportController.php` (index) | controller | request-response (read aggregation) | `app/Http/Controllers/Artist/PerformanceReportController.php` | role-match (read shape identical; entitlement logic is new) |
| `app/Http/Controllers/Reports/ReportExportController.php` (exportPdf, exportXlsx) | controller | file-I/O / streaming | none in-repo — first file-download controller | partial (see below) |
| `app/Policies` — **no new Policy class**; entitlement resolved via `ReportRegistry::isEntitled()` | utility | request-response | `app/Policies/AccountsReceivablePolicy.php` (Owner-not-Admin narrowing *shape*, not a Policy class itself) | role-match |
| `routes/reports.php` (new file, or a new block in `routes/portals.php`) — **Owner's group must be `role:owner` alone, not `role:owner,admin`** | route | request-response | `routes/owner.php` (structure) but **deliberately not reused as-is** — see Pitfall below | partial |
| `resources/views/reports/layout.blade.php` | template | transform (PDF render) | none in-repo — first non-mail Blade | none (see below) |
| `resources/views/reports/{sales,cancellations,production-status,expenses,financial-summary}.blade.php` | template | transform (PDF render) | `resources/views/reports/layout.blade.php` (sibling, once written) | none (see below) |
| `resources/views/reports/collection-letter.blade.php` | template | transform (PDF render) | `resources/js/pages/accounting-staff/CollectionLetter.vue` (content/copy source only, not a Blade analog) | none (see below) |
| `app/Http/Controllers/AccountingStaff/CollectionLetterController.php` (extend, +`pdf` action) | controller | file-I/O | itself (`show` method, exact guard clauses to reproduce) | exact |
| `composer.json` (+`barryvdh/laravel-dompdf`, +`openspout/openspout`) | config | — | n/a — first two Composer deps since scaffolding | n/a |
| `resources/js/config/nav/owner.ts` (extend) | config | — | itself | exact |
| `resources/js/config/nav/cashier.ts` (extend) | config | — | itself | exact |
| `resources/js/config/nav/production-staff.ts` (extend) | config | — | itself | exact |
| `resources/js/config/nav/accounting-staff.ts` (extend) | config | — | itself | exact |
| `resources/js/components/reports/ReportsWorkspace.vue` (new) | component | request-response (master/detail) | `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue` (cards+table composite) + `resources/js/pages/artist/PerformanceReport.vue` (date-range form) | exact (composite) |
| `resources/js/components/reports/DateRangeControl.vue` (new, extracted) | component | request-response | `resources/js/pages/artist/PerformanceReport.vue` (native `Input type="date"` filter form) | exact |
| `resources/js/pages/{owner,cashier,production-staff,accounting-staff}/Reports.vue` (4 new thin wrappers) | component | request-response | `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue` (`defineOptions` layout block shape) | exact (for the wrapper shell only) |
| `resources/js/pages/accounting-staff/Expenses/Index.vue` (new) | component | request-response (CRUD list + dialogs) | `resources/js/pages/accounting-staff/AccountsReceivable/Show.vue` (Dialog+Textarea for a reasoned mutation) + `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue` (Table shape) | exact (composite) |
| `resources/js/pages/accounting-staff/AccountsReceivable/Show.vue` (extend, +"Download Letter (PDF)" button) | component | request-response | itself (existing "Request Write-Off" / print button row) | exact |
| `tests/Feature/AccountingStaff/ExpenseTest.php` | test | request-response | `tests/Feature/AccountingStaff/WriteOffRequestTest.php` | exact |
| `tests/Feature/Reports/*ReportsTest.php` (per-role) | test | request-response | `tests/Feature/Artist/PerformanceReportTest.php` (Inertia props assertions) + `tests/Feature/Owner/WriteOffApprovalTest.php` (cross-role 403 assertions) | exact (composite) |

## Pattern Assignments

### `database/migrations/xxxx_create_expenses_table.php`

**Analog:** `database/migrations/2026_09_04_090002_create_transactions_table.php` (full file read above) — the project's only prior "new domain table" migration, not an additive-column one (D-13 needs a genuinely new table, the less common case in this codebase).

```php
public function up(): void
{
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
}

public function down(): void
{
    Schema::dropIfExists('transactions');
}
```

**Delta:** same `Schema::create`/`dropIfExists` shape, on `expenses`, columns per D-13 plus the discretionary void pair:

```php
Schema::create('expenses', function (Blueprint $table) {
    $table->id();
    $table->string('category');
    $table->decimal('amount', 10, 2);
    $table->date('expense_date');
    $table->text('description')->nullable();
    $table->foreignId('recorded_by')->constrained('users');
    $table->timestamp('voided_at')->nullable();
    $table->text('void_reason')->nullable();
    $table->timestamps();
});
```

`category` stays a plain `string`, not a foreign key or enum — D-14 forbids a categories table, and a historical row must keep its stored string even after a config edit narrows the dropdown (CONTEXT.md's discretion note). No `total_amount`/derived column — every report recomputes the sum, matching D-08's cash-basis pattern.

---

### `app/Models/Expense.php`

**Analog:** `app/Models/Transaction.php` (full file read above) — same shape: `#[Fillable]` lists only client-writable columns, `#[ObservedBy(AuditObserver::class)]` for free audit coverage, `HasFactory`, a `casts()` method, one `belongsTo` relation to the recording user.

```php
#[Fillable(['job_order_id', 'type', 'payment_method', 'amount', 'status', 'reference_number', 'paymongo_payment_intent_id', 'recorded_by'])]
#[ObservedBy(AuditObserver::class)]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'payment_method' => PaymentMethod::class,
            'status' => TransactionStatus::class,
            'amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
```

**Delta:**

```php
#[Fillable(['category', 'amount', 'expense_date', 'description', 'recorded_by'])]
#[ObservedBy(AuditObserver::class)]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
```

**Note:** `voided_at`/`void_reason` are deliberately **outside** `#[Fillable]` — matching `AccountsReceivable`'s `write_off_*` columns, which are also written only via `forceFill()` from the controller, never mass-assigned (D-11/Security §"mass assignment" threat pattern in RESEARCH.md). Add a local scope `active()`/`scopeActive(Builder $query)` returning `$query->whereNull('voided_at')` — RESEARCH.md's Pitfall 5 is explicit that this must be a single, reusable scope so every report query (Financial, Expenses, Summary) gets it by construction rather than three separately-remembered `whereNull` calls.

---

### `database/factories/ExpenseFactory.php`

**Analog:** `database/factories/TransactionFactory.php` (full file read above) — plain `definition()` plus named `state()` helpers for each variant, `afterCreating()` reserved only for columns outside `#[Fillable]` (see `AccountsReceivableFactory::active()` in `07-PATTERNS.md` for that half of the convention).

```php
public function definition(): array
{
    return [
        'job_order_id' => JobOrder::factory(),
        'type' => TransactionType::FullPayment->value,
        'payment_method' => PaymentMethod::Cash->value,
        'amount' => fake()->randomFloat(2, 100, 5000),
        'status' => TransactionStatus::Completed->value,
        'recorded_by' => User::factory()->cashier(),
        'confirmed_at' => now(),
    ];
}

public function pendingConfirmation(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => TransactionStatus::PendingConfirmation->value,
        'confirmed_at' => null,
    ]);
}
```

**Delta:**

```php
public function definition(): array
{
    return [
        'category' => fake()->randomElement(['Utilities', 'Supplies', 'Rent']),
        'amount' => fake()->randomFloat(2, 100, 5000),
        'expense_date' => fake()->dateTimeBetween('-30 days', 'now'),
        'description' => fake()->sentence(),
        'recorded_by' => User::factory()->accountingStaff(),
    ];
}

public function voided(): static
{
    return $this->state(fn (array $attributes) => [
        'voided_at' => now(),
        'void_reason' => 'Duplicate entry',
    ]);
}
```

`voided()` uses `state()`, not `afterCreating()`, since `voided_at`/`void_reason` sit outside `#[Fillable]` but the factory's `create()` call still bypasses mass-assignment guarding entirely (Eloquent factories always write through `forceFill()` internally) — unlike a controller-driven `forceFill()->save()` after the row already exists. Confirm this against `AccountsReceivableFactory::active()`'s own comment before assuming — that factory *does* use `afterCreating()` for `approved_by`/`approved_at`. If Larastan/Pint flags a mismatch, prefer `afterCreating()` for consistency with the established convention.

---

### `app/Concerns/ExpenseValidationRules.php`

**Analog:** `app/Concerns/AccountsReceivableValidationRules.php` (full file read above) — one rule-set method per mutating action, `Rule::in()` for enum-shaped fields, PHPDoc array-shape return type.

```php
trait AccountsReceivableValidationRules
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function collectionStatusRules(): array
    {
        return [
            'collection_status' => ['required', Rule::in([...])],
        ];
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function writeOffReasonRules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }
}
```

**Delta:**

```php
trait ExpenseValidationRules
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function expenseRules(): array
    {
        return [
            'category' => ['required', 'string', Rule::in(SystemConfiguration::getArray('expense_categories', []))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function voidReasonRules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }
}
```

`Rule::in(SystemConfiguration::getArray('expense_categories', []))` reads the **current** config list at submit time — this is exactly why D-14 says the dropdown narrows but history doesn't: an *existing* row's stored category was validated against the list at the time it was created, and editing a historical row to a since-removed category is correctly rejected by this same rule (it must be re-picked from the current list, matching the UI-SPEC's "That category is no longer available" error copy).

---

### `app/Http/Requests/AccountingStaff/{Store,Update,Void}ExpenseRequest.php`

**Analog:** `app/Http/Requests/AccountingStaff/RequestWriteOffRequest.php` (full file read above) — `authorize(): bool { return true; }` (route-group `role:accounting_staff` middleware is the only gate, per `07-PATTERNS.md`'s Assumption A3 precedent), `rules()` delegates to the Concern trait.

```php
class RequestWriteOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return $this->writeOffReasonRules();
    }
}
```

**Delta:** `StoreExpenseRequest`/`UpdateExpenseRequest` both `use ExpenseValidationRules;` and return `$this->expenseRules();` from `rules()`. `VoidExpenseRequest` returns `$this->voidReasonRules();`. All three keep `authorize(): bool { return true; }` — D-12's write-path restriction ("only Accounting Staff") is already the route group; no Policy needed here (this domain never narrows *within* a role the way Owner-not-Admin does).

---

### `app/Http/Controllers/AccountingStaff/ExpenseController.php` (index, store, update) + `ExpenseVoidController` (or `::void`)

**Analogs:** `app/Http/Controllers/Cashier/ReconciliationController.php` (simple create, un-narrowed by Policy) for `store`/`update`, and `app/Http/Controllers/AccountingStaff/WriteOffRequestController.php` (full file read above) for `void`'s guard-then-`forceFill()` shape.

```php
// WriteOffRequestController::store — guard-then-forceFill shape
public function store(RequestWriteOffRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
{
    abort_unless($accountsReceivable->status === AccountsReceivableStatus::Active, 422, __('This receivable is not active.'));
    abort_if($accountsReceivable->write_off_requested_at !== null, 422, __('A write-off request is already pending for this entry.'));

    $accountsReceivable->forceFill([
        'write_off_reason' => $request->validated('reason'),
        'write_off_requested_by' => $request->user()->id,
        'write_off_requested_at' => now(),
    ])->save();

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Write-off requested. Awaiting Owner approval.')]);

    return back();
}
```

**Delta — `store()`:**

```php
public function store(StoreExpenseRequest $request): RedirectResponse
{
    Expense::create($request->validated() + ['recorded_by' => $request->user()->id]);

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense recorded.')]);

    return back();
}
```

**Delta — `update()`:**

```php
public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
{
    abort_if($expense->voided_at !== null, 422, __("This expense was voided and can't be edited."));

    $expense->update($request->validated());

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense updated.')]);

    return back();
}
```

**Delta — `void()`:**

```php
public function void(VoidExpenseRequest $request, Expense $expense): RedirectResponse
{
    abort_if($expense->voided_at !== null, 422, __('This expense has already been voided.'));

    $expense->forceFill([
        'voided_at' => now(),
        'void_reason' => $request->validated('reason'),
    ])->save(); // AuditObserver::updated() fires automatically — no extra audit call

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense voided. It no longer counts toward reports.')]);

    return back();
}
```

**Delta — `index()`:** mirrors `AccountsReceivableController::index()`'s list-query shape — `Expense::query()->whereBetween('expense_date', [$from, $to])->orderByDesc('expense_date')->with('recordedBy:id,name')->get([...])`, plus the range-total and count that feed the summary `Card`. **Every query here must call `->active()` when computing the range total** (the scope from the model), but the *ledger list itself* renders voided rows too (UI-SPEC: "Voided entries stay on the list"), so `index()` fetches all rows in range and the client distinguishes voided via the badge — only the **sum** excludes them, computed server-side and passed as a separate `total` prop, not derived client-side from the row array (client-side summation would double as an unaudited second calculation path).

---

### `app/Http/Controllers/Artist/PerformanceReportController.php` — pattern for `app/Http/Requests/Reports/FilterReportRequest.php`

**Analog:** `app/Http/Requests/Artist/PerformanceReportFilterRequest.php` (full file read above, verbatim) — this is named in CONTEXT.md/RESEARCH.md as the exact shape to extend, not just approximate.

```php
class PerformanceReportFilterRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }
}
```

**Delta:** the UI-SPEC's carried-to-planner note #1 requires two additional constraints beyond the existing shape — `to` must not precede `from`, and neither date may be in the future:

```php
class FilterReportRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date', 'before_or_equal:today'],
            'to' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:from'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to.after_or_equal' => __("The end date can't be earlier than the start date."),
            'from.before_or_equal' => __('Pick a date on or before today.'),
            'to.before_or_equal' => __('Pick a date on or before today.'),
        ];
    }
}
```

Note `required` not `nullable` here — unlike `PerformanceReportFilterRequest`, this phase's UI-SPEC locks a mandatory default range ("This Month") that the client always submits, so the server never needs to invent a default.

---

### Report entitlement — new territory, modeled on `app/Policies/AccountsReceivablePolicy.php`'s Owner-not-Admin narrowing *shape*

**Analog:** `app/Policies/AccountsReceivablePolicy.php` (full file read above) — the codebase's only precedent for "narrower than the route-group middleware", even though a report is not an Eloquent model and cannot use Laravel's model-Policy auto-discovery.

```php
class AccountsReceivablePolicy
{
    public function approve(User $actor, AccountsReceivable $accountsReceivable): bool
    {
        return $actor->role === UserRole::Owner;
    }
}
```

**Delta — no Policy class.** Per RESEARCH.md Pattern 4 (and Assumption A3, "no `Gate::` calls exist anywhere in this codebase yet"), the simplest, most idiomatic mechanism is a plain method on the registry service:

```php
final class ReportRegistry
{
    /** @return array<string, array{title:string, subLine:string, badge:string, roles:list<UserRole>}> */
    public static function definitions(): array
    {
        return [
            'sales' => ['title' => 'Sales', /* ... */ 'roles' => [UserRole::Owner, UserRole::Cashier, UserRole::AccountingStaff]],
            'cancellations' => [/* ... */ 'roles' => [UserRole::Owner, UserRole::Cashier]],
            'production-status' => [/* ... */ 'roles' => [UserRole::Owner, UserRole::ProductionStaff]],
            'expenses' => [/* ... */ 'roles' => [UserRole::Owner, UserRole::AccountingStaff]],
            'financial-summary' => [/* ... */ 'roles' => [UserRole::Owner, UserRole::AccountingStaff]],
        ];
    }

    public static function isEntitled(User $user, string $key): bool
    {
        return in_array($user->role, self::definitions()[$key]['roles'] ?? [], true);
    }

    /** @return array<string, array{title:string, subLine:string, badge:string}> */
    public static function entitledFor(User $user): array
    {
        return array_filter(self::definitions(), fn ($def) => in_array($user->role, $def['roles'], true));
    }
}
```

`ReportController::index()`/`ReportExportController::export*()` both call `abort_unless(ReportRegistry::isEntitled($request->user(), $reportKey), 403);` **before building any query** — this is D-04's single security boundary and RESEARCH.md's Pitfall 2 calls out exactly this line as the one a Cashier hitting the URL directly must hit.

---

### Middleware group for the Reports routes — **the critical routing pitfall**

**Analog:** `routes/owner.php` (full file read above) and `app/Http/Middleware/EnsureUserHasRole.php` (full file read above, verbatim — `abort_if(! in_array($user->role->value, $roles, true), 403)`).

```php
// routes/owner.php — the existing group, verbatim
Route::middleware(['auth', 'role:owner,admin'])->prefix('owner')->name('owner.')->group(function () {
    Route::inertia('dashboard', 'owner/Dashboard')->name('dashboard');
    // ...
});
```

**Do NOT** add the Owner's Reports route inside this existing group — `role:owner,admin` lets an Admin through with a 200, directly violating D-05. `EnsureUserHasRole` takes `...roles` as literal comma-separated string arguments to the middleware alias, so the fix is simply a **second, narrower group**, not a code change to the middleware itself:

```php
// A NEW group, owner-only, sitting beside (not inside) the existing role:owner,admin group
Route::middleware(['auth', 'role:owner'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{reportKey}/export/pdf', [ReportExportController::class, 'exportPdf'])->name('reports.export.pdf');
    Route::get('reports/{reportKey}/export/xlsx', [ReportExportController::class, 'exportXlsx'])->name('reports.export.xlsx');
});
```

Cashier/Production Staff/Accounting Staff's Reports routes reuse the **existing** `role:{role}` groups in `routes/portals.php` unchanged (those groups are already single-role, so no split is needed there — only Owner's shared-with-Admin group has this trap).

---

### `app/Http/Controllers/AccountingStaff/CollectionLetterController.php` (extend, +`pdf`)

**Analog:** itself (full file read above) — reproduce the exact guard clauses verbatim, per D-03's binding requirement.

```php
public function show(AccountsReceivable $accountsReceivable): Response
{
    abort_unless($accountsReceivable->status === AccountsReceivableStatus::Active, 404);
    abort_if(in_array($accountsReceivable->collection_status, [AccountsReceivableCollectionStatus::Paid, AccountsReceivableCollectionStatus::WrittenOff], true), 404);
    // ...derive amountDue, bracket, letterBody...
}
```

**Delta:** new `pdf(AccountsReceivable $accountsReceivable): \Symfony\Component\HttpFoundation\Response` action, same two `abort_unless`/`abort_if` lines copied verbatim (not refactored into a shared trait unless the planner prefers that — the guard is only two lines and D-03 asks for exact reproduction, not extraction), then instead of `Inertia::render(...)`:

```php
use Barryvdh\DomPDF\Facade\Pdf;

return Pdf::loadView('reports.collection-letter', [/* same data as show() */])
    ->download("collection-letter_{$accountsReceivable->jobOrder->number}.pdf");
```

Do **not** touch `show()` itself or `CollectionLetter.vue` — D-03 is explicit that this is an additive route + Blade view only.

---

### PDF export (dompdf) and Excel export (openspout) controller actions — new territory

**No in-repo analog** — first file-download responses in the app. Use RESEARCH.md's `## Code Examples` verbatim as the implementation baseline (already vetted against the official docs, not copied from training-data memory):

```php
// PDF — write the D-02 audit entry BEFORE the download stream begins
AuditLog::create([
    'user_id' => $request->user()->id,
    'action' => 'report_exported',
    'auditable_type' => null,
    'auditable_id' => null,
    'new_values' => ['report' => $reportKey, 'format' => 'pdf', 'from' => $from->toDateString(), 'to' => $to->toDateString()],
    'ip_address' => $request->ip(),
    'created_at' => now(),
]);

return Pdf::loadView("reports.{$reportKey}", $data)
    ->download("{$reportKey}_{$from->toDateString()}_{$to->toDateString()}.pdf");
```

```php
// Excel — openspout v5, Writer -> openToFile('php://output') inside streamDownload(), NEVER openToBrowser()
return response()->streamDownload(function () use ($rows) {
    $writer = new OpenSpout\Writer\XLSX\Writer();
    $writer->openToFile('php://output');
    foreach ($rows as $row) {
        $writer->addRow(OpenSpout\Common\Entity\Row::fromValues($row));
    }
    $writer->close();
}, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
```

Use `AuditLog::create()` directly (`App\Support\AuditLogger`'s underlying call, not the observer — D-02 is the project's first non-model-driven audit write, `AuditObserver` cannot see an export). Consider adding a `AuditLogger::recordReportExport(...)` convenience method mirroring `recordAuthEvent()`'s existing non-mutation shape (both write directly to `AuditLog::create()` for an event that isn't a model mutation):

```php
// app/Support/AuditLogger.php:42-54 (existing sibling method, exact shape to extend)
public static function recordAuthEvent(?User $user, string $action, ?Request $request = null): void
{
    AuditLog::create([
        'user_id' => $user?->id,
        'action' => $action,
        'auditable_type' => $user ? User::class : null,
        'auditable_id' => $user?->id,
        'old_values' => null,
        'new_values' => null,
        'ip_address' => ($request ?? request())->ip(),
        'created_at' => now(),
    ]);
}
```

---

### `app/Http/Controllers/Reports/ReportController.php::index()`

**Analog:** `app/Http/Controllers/Artist/PerformanceReportController.php` (full file read above) for the Inertia-render/`stats`+`filters` shape, and `app/Http/Controllers/ProductionStaff/ProductionBoardController.php` (full file read above) for RPT-03's production-status query specifically.

```php
// PerformanceReportController::index — the render shape to follow
return Inertia::render('artist/PerformanceReport', [
    'stats' => [...],
    'filters' => $request->only(['from', 'to']),
]);
```

```php
// ProductionBoardController::index — RPT-03's query base (exclude released/cancelled, urgency flag)
JobOrder::query()
    ->whereIn('status', [JobOrderStatus::ForProduction->value, JobOrderStatus::Printing->value, JobOrderStatus::QualityCheck->value, JobOrderStatus::ReadyForPickup->value])
    ->whereNull('released_at')
    ->whereNull('cancelled_at')
    ->with('queueEntry.customer:id,name')
    ->get([...])
    ->each(fn (JobOrder $jobOrder) => $jobOrder->is_rush = /* ... */);
```

**Delta:** `index()` reads `reports` from `ReportRegistry::entitledFor($request->user())`, resolves the selected `reportKey` (query param, default = first entitled key per UI-SPEC §1), builds that report's rows via a per-key query method, caps at 100 rows **plus an untruncated `rowsTotal` count** (UI-SPEC carried-note #2 — the export path must independently rebuild the full set, never reuse the capped query), and renders:

```php
return Inertia::render('owner/Reports', [ // or the matching role page
    'reports' => ReportRegistry::entitledFor($request->user()),
    'selected' => $reportKey,
    'rows' => $rows->take(100),
    'rowsTotal' => $rows->count(),
    'summary' => $summary, // financial-summary's figure block only
    'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
]);
```

The **cash-basis revenue query** (D-08/D-09/D-10, `sales`/`financial-summary`) filters `transactions.confirmed_at`, never `created_at` — RESEARCH.md Pitfall 1, verified directly against `app/Actions/POS/ConfirmPaymentIntent.php` and `PaymentController`. This is not sourced from an existing controller (no prior report aggregates `transactions` this way) — copy RESEARCH.md's `## Architecture Patterns` → Pattern 2 code block as the concrete query baseline.

---

### Nav config extensions

**Analog:** all four files, in full (read above) — identical `NavItem[]` array-push shape every prior phase used.

```typescript
// accounting-staff.ts, current state (verbatim)
export const accountingStaffNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Accounts Receivable', href: accountsReceivableIndex(), icon: HandCoins },
];
```

**Delta — `owner.ts`:** append `{ title: 'Reports', href: index() /* from '@/routes/owner/reports' */, icon: ChartColumn }` last, after "Write-Off Requests" (per UI-SPEC §6). Import `ChartColumn` from `@lucide/vue` — **`BarChart3` does not exist in this build**, verified by the UI-SPEC.

**Delta — `cashier.ts`:** append `{ title: 'Reports', href: index(), icon: ChartColumn }` after "Dashboard".

**Delta — `production-staff.ts`:** append `{ title: 'Reports', href: index(), icon: ChartColumn }` after "Production Board".

**Delta — `accounting-staff.ts`:** append two entries after "Accounts Receivable": `{ title: 'Expenses', href: expensesIndex(), icon: Wallet }`, then `{ title: 'Reports', href: reportsIndex(), icon: ChartColumn }`.

`resources/js/config/nav/admin.ts` (if it exists as a separate file from owner's) is **not touched** — D-05 gives Admin no Reports item.

---

### `resources/js/components/reports/ReportsWorkspace.vue` (new, shared)

**Analogs:** `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue` (full file read above) for the `Card`-grid + `Table`/`TableEmpty` shape and the `money()` helper, `resources/js/pages/artist/PerformanceReport.vue` for the date-filter form shape (not re-read in full here — its native `Input type="date"` contract is already confirmed by the UI-SPEC's own live-code read).

```vue
<!-- AccountsReceivable/Index.vue — money() helper, verbatim, reuse exactly -->
function money(value: number): string {
    return `₱${Number(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}
```

```vue
<!-- AccountsReceivable/Index.vue — Table/TableEmpty shape, verbatim structure to reuse -->
<Table>
    <TableHeader><TableRow><TableHead>...</TableHead></TableRow></TableHeader>
    <TableBody>
        <TableEmpty v-if="filteredRows.length === 0" :colspan="9">...</TableEmpty>
        <TableRow v-for="row in filteredRows" v-else :key="row.id">...</TableRow>
    </TableBody>
</Table>
```

**Delta:** this component takes `reports`, `selected`, `rows`, `rowsTotal`, `summary`, `filters`, and the two export URLs as props (UI-SPEC §1's exact prop contract) — it is **not** a page, has no `defineOptions`, and contains no role check of any kind (the entitlement boundary lives entirely server-side; UI-SPEC §7 is explicit that a `v-if` filtering by role here breaks the contract). Report-card selection uses `router.get(..., { preserveState: true, preserveScroll: true, replace: true })`, not a plain `<Link>` — the range must carry forward when the report changes. Export buttons are `<Button as="a" :href="...">`, never `<Link>`/`router.visit()` — RESEARCH.md's "Frontend export trigger" code example is the exact snippet to copy; a download response cannot be received through Inertia's XHR machinery.

---

### `resources/js/components/reports/DateRangeControl.vue` (new, extracted)

**Analog:** `resources/js/pages/artist/PerformanceReport.vue`'s native `<Input type="date">` filter form — **no `calendar`/`popover` primitive exists in this codebase**, confirmed by the UI-SPEC's live component-inventory read. This is the project's only date-range-picking precedent; do not introduce a new date-picker pattern.

**Delta:** add five preset `Button`s (Today/This Week/This Month/This Quarter/Custom) computing concrete `from`/`to` dates client-side (never a preset-name param — `FilterReportRequest` only ever sees two dates, UI-SPEC carried-note #1), the active preset `variant="default"`, others `variant="outline"` (same active-filter treatment Phase 6 gave its stage tabs — see `07-PATTERNS.md`'s Tabs precedent for the pattern lineage). This same component is reused verbatim on the Expenses ledger page (UI-SPEC §3 — "two divergent date pickers... is the kind of drift this contract exists to prevent").

---

### `resources/js/pages/{owner,cashier,production-staff,accounting-staff}/Reports.vue` (4 new thin wrappers)

**Analog:** `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue`'s `defineOptions` block shape (full file read above, lines 51-61) — every existing page in this codebase follows this exact `layout: { navItems, breadcrumbs }` contract.

```vue
defineOptions({
    layout: {
        navItems: accountingStaffNavItems,
        breadcrumbs: [{ title: 'Accounts Receivable', href: accountsReceivableIndex() }],
    },
});
```

**Delta:** each of the four files is *only* a `<Head>`, this `defineOptions` block with that role's nav items + a "Reports" breadcrumb, a role-specific sub-copy string (UI-SPEC's Copywriting Contract has all four), and `<ReportsWorkspace v-bind="$props" />`. **If any `v-if` role-check appears in one of these four files, the pattern is broken** — per UI-SPEC §1's explicit binding rule.

---

### `resources/js/pages/accounting-staff/Expenses/Index.vue` (new)

**Analogs:** `resources/js/pages/accounting-staff/AccountsReceivable/Show.vue` (grepped above, lines 329-378) for the plain-`Dialog`+`Textarea`+mandatory-reason shape (this is D-11's "Void Expense" precedent, matching "Request Write-Off" almost exactly), and `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue` (full file read above) for the `Table` + summary `Card` shape.

```vue
<!-- AccountsReceivable/Show.vue:329-378 — the exact Dialog+Textarea+reason shape to copy for Void Expense -->
<Dialog v-if="!isTerminal && !hasPendingWriteOff">
    <DialogTrigger as-child>
        <Button variant="outline" class="w-fit">
            <FileMinus class="size-4" />
            Request Write-Off
        </Button>
    </DialogTrigger>
    <DialogContent>
        <Form v-bind="WriteOffRequestController.store.form(accountsReceivable.id)" :options="{ preserveScroll: true }" class="space-y-4" v-slot="{ errors, processing }">
            <DialogHeader>
                <DialogTitle>Request a write-off for {{ money(accountsReceivable.balance) }}?</DialogTitle>
                <DialogDescription>An Owner reviews every write-off...</DialogDescription>
            </DialogHeader>
            <div class="grid gap-2">
                <Label for="write-off-reason">Reason</Label>
                <Textarea id="write-off-reason" name="reason" rows="4" placeholder="..." />
                <InputError :message="errors.reason" />
            </div>
            <DialogFooter class="gap-2">
                <DialogClose as-child><Button type="button" variant="secondary">Cancel</Button></DialogClose>
                <Button type="submit" :disabled="processing">Submit Request</Button>
            </DialogFooter>
        </Form>
    </DialogContent>
</Dialog>
```

**Delta — Void Expense:** identical structure, `variant="destructive"` on both the row trigger (`text-destructive`, lucide `Ban`) and the confirm button (per UI-SPEC's Destructive Confirmation section — this is the one destructive action in the phase), bound to a new `ExpenseController.void.form(expense.id)`. **Record Expense** and **Edit Expense** are plain `Dialog`s with `--primary` confirms, no `AlertDialog` anywhere in this file (matches the project's "form-bound confirm needs a real form, not an AlertDialog action slot" rule already set by "Request Write-Off"). The ledger `Table` follows `AccountsReceivable/Index.vue`'s exact `Table`/`TableEmpty`/`money()` shape, one summary `Card` (not a stat-card row — UI-SPEC: "there is exactly one number here").

---

### `resources/js/pages/accounting-staff/AccountsReceivable/Show.vue` (extend, +"Download Letter (PDF)")

**Analog:** itself (grepped above, lines 320-378 — the existing Actions row's `Button as-child` + `Link` pattern used for "Print Collection Letter"/similar buttons on this same page).

```vue
<Button as-child variant="outline" size="sm">
    <Link :href="show.url(row.id)" :data-test="`view-entry-${row.id}-link`">View Entry</Link>
</Button>
```

**Delta:** add `<Button as="a" variant="outline" :href="pdf.url(accountsReceivable.id)">` (lucide `FileDown`) beside the existing print button, under the identical `v-if` condition already guarding that button (UI-SPEC's Availability rule — absent for `Current`/terminal entries; the server-side `abort_unless`/`abort_if` on the new `pdf` route is the real enforcement, this is the courtesy). This is a plain anchor (file download), not a `<Link>`, matching every other export button in this phase.

---

## Shared Patterns

### `#[ObservedBy(AuditObserver::class)]` — zero new code for D-11's expense audit coverage

**Source:** `app/Observers/AuditObserver.php` (full file read above) — `created`/`updated`/`deleted` hooks already diff `getChanges()` via `AuditLogger::recordMutation()`.

**Apply to:** `Expense` model — every create, edit, and void (a `forceFill()->save()` is still an `updated` event) is captured with zero extra code, exactly as `AccountsReceivable`'s write-off request/approve/reject already are.

### `App\Support\AuditLogger` — the append-only write path, and D-02's one deliberate exception

**Source:** `app/Support/AuditLogger.php` (full file read above) — `recordMutation()` (model-observer-driven) and `recordAuthEvent()` (a non-mutation event, called directly).

**Apply to:** D-02's export logging follows `recordAuthEvent()`'s exact shape (a direct `AuditLog::create()` call, no model behind it) — add a third static method here, `recordReportExport()`, rather than inlining the `AuditLog::create()` call in two separate export controller methods. **No `update()`/`delete()` call against `AuditLog` may ever be added anywhere** — this file's own docblock states that constraint explicitly.

### Form Request + Concern trait pairing

**Source:** `app/Http/Requests/AccountingStaff/RequestWriteOffRequest.php` + `app/Concerns/AccountsReceivableValidationRules.php` (both full files read above).

**Apply to:** `StoreExpenseRequest`/`UpdateExpenseRequest`/`VoidExpenseRequest` via `ExpenseValidationRules`, and `FilterReportRequest` (concern trait optional there — only two rules, may stay inline like `PerformanceReportFilterRequest` does).

### `SystemConfiguration::getArray()` — D-14's category source, never a hardcoded list

**Source:** `app/Models/SystemConfiguration.php:83-88` (full file read above, verbatim `getArray()` method).

**Apply to:** `ExpenseValidationRules::expenseRules()`'s `Rule::in()` allowlist, and the `ExpenseController::index()`/`create`-time Inertia prop that feeds the category `Select`'s options (`SystemConfiguration::getArray('expense_categories', ['Utilities', 'Supplies', 'Rent'])`).

### `Inertia::flash('toast', [...])` for mutation feedback — exports get none

**Source:** every mutating controller in this codebase, e.g. `WriteOffRequestController::store` (verbatim above).

**Apply to:** `ExpenseController::store/update/void` — exact copy strings locked in the UI-SPEC's Toasts table ("Expense recorded.", "Expense updated.", "Expense voided. It no longer counts toward reports."). **Do not** add a toast to any export action — UI-SPEC is explicit this is deliberate (a download is a plain navigation, not an Inertia visit with a response to read).

### Wayfinder-generated route/action helpers

**Source:** every existing page/controller pairing, e.g. `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue:19` (`import { index as accountsReceivableIndex, show } from '@/routes/accounting-staff/accounts-receivable'`).

**Apply to:** every new controller action in this phase, including the export routes (`<Button as="a" :href="ReportExportController.exportPdf.url({...})">`). Regenerate with `--with-form` after adding routes (documented project trap, `01-04`).

### `role:{role}` middleware — one group per role, Owner's Reports group must stand alone

**Source:** `app/Http/Middleware/EnsureUserHasRole.php` (full file read above) + `routes/owner.php` (full file read above).

**Apply to:** every new route in this phase. **The one exception to "reuse the existing group":** Owner's Reports routes need their own `role:owner` group, never the shared `role:owner,admin` one — see the dedicated pattern entry above.

---

## No Analog Found

| File / Pattern | Role | Data Flow | Reason |
|---|---|---|---|
| `resources/views/reports/layout.blade.php` | template | transform | This app has exactly two prior Blade artifacts — `resources/views/app.blade.php` (the Inertia SPA shell, structurally unrelated) and the two Markdown mail templates under `resources/views/mail/` (a different Blade component system, `<x-mail::message>`, not plain HTML/CSS print layout). Neither is a usable analog for an A4-paged, DejaVu-Sans, `{{ }}`-escaped print document. Build from dompdf's own README examples and the UI-SPEC's exact typography/color/spacing contract (§Typography "PDF documents", §Color "the PDF carries no colour at all"), not from an in-repo file. |
| `resources/views/reports/{sales,cancellations,production-status,expenses,financial-summary,collection-letter}.blade.php` | template | transform | Same reasoning — first-of-their-kind in this app. Once `layout.blade.php` exists, these six become each other's analog (`@extends`/`@yield('content')` siblings); the very first one written has no sibling to copy. |
| `app/Services/Reports/ReportRegistry.php` | service | request-response | No config-array/registry-service pattern exists anywhere in this codebase — every prior "list of things a role can do" (nav items, role enum cases) is a flat TypeScript/PHP array with no entitlement dimension attached. RESEARCH.md's Pattern 4 is the only design guidance; there is no code to point at. |
| `app/Http/Controllers/Reports/ReportExportController.php` (the streaming/PDF mechanics themselves, not the controller shape) | controller | file-I/O / streaming | First binary-file-download response in an otherwise Inertia-only, HTML-response app. `CollectionLetterController`/`ReceiptController` are read-only-render analogs for the *guard clauses*, but neither returns anything but an `Inertia::render()` — the actual `Pdf::loadView()->download()` / `response()->streamDownload()` mechanics have zero in-repo precedent. Use RESEARCH.md's `## Code Examples` (sourced directly from each package's official docs) as the implementation baseline. |
| `app/Console/Commands/` scheduling interaction (N/A — confirmed out of scope) | — | — | Phase 7 already established `SendAccountsReceivableReminders` as the app's only scheduled command; this phase adds no new one (D-01/RESEARCH.md: synchronous export generation, no queue). Listed only to confirm the planner should **not** invent a second scheduled job for anything in this phase. |

## Metadata

**Analog search scope:** `app/Models/`, `app/Enums/`, `app/Http/Controllers/{Artist,AccountingStaff,Cashier,Owner,ProductionStaff}/`, `app/Http/Requests/{Artist,AccountingStaff}/`, `app/Concerns/`, `app/Policies/`, `app/Observers/`, `app/Support/`, `app/Http/Middleware/`, `database/migrations/`, `database/factories/`, `routes/`, `resources/js/pages/{owner,cashier,accounting-staff,artist,production-staff}/`, `resources/js/config/nav/`, `resources/views/`, `tests/Feature/{AccountingStaff,Artist,Owner}/`
**Files scanned:** 26 read in full (cited above with line numbers where relevant), plus 3 targeted greps (`role:`/middleware grep across `routes/portals.php`, Dialog/Textarea shape in `AccountsReceivable/Show.vue`, `outstandingBalance()` in `JobOrder.php`)
**Pattern extraction date:** 2026-09-10
