# Phase 5: POS & Payments - Pattern Map

**Mapped:** 2026-09-04
**Files analyzed:** 46 (28 backend, 18 frontend)
**Analogs found:** 43 / 46

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|--------------------|------|-----------|-----------------|----------------|
| `database/migrations/*_create_pricing_database_table.php` | migration | CRUD | `database/migrations/2026_09_02_084148_create_design_files_table.php` | exact |
| `database/migrations/*_create_transactions_table.php` | migration | CRUD | `database/migrations/2026_09_02_084148_create_design_files_table.php` | exact |
| `database/migrations/*_create_accounts_receivable_table.php` | migration | CRUD | `database/migrations/2026_09_02_084148_create_design_files_table.php` | exact |
| `database/migrations/*_add_payment_columns_to_job_orders_table.php` | migration | CRUD | `database/migrations/2026_09_01_224409_add_validation_and_assignment_columns_to_job_orders_table.php` | exact |
| `app/Enums/PaymentStatus.php` | model (enum) | transform | `app/Enums/JobOrderStatus.php` | exact |
| `app/Enums/PaymentMethod.php` | model (enum) | transform | `app/Enums/JobOrderStatus.php` | exact |
| `app/Enums/TransactionType.php` | model (enum) | transform | `app/Enums/JobOrderStatus.php` | exact |
| `app/Enums/TransactionStatus.php` | model (enum) | transform | `app/Enums/JobOrderStatus.php` | exact |
| `app/Models/PricingEntry.php` | model | CRUD | `app/Models/SystemConfiguration.php` | role-match |
| `app/Models/Transaction.php` | model | CRUD | `app/Models/JobOrder.php` | exact |
| `app/Models/AccountsReceivable.php` | model | CRUD | `app/Models/JobOrder.php` | role-match |
| `app/Models/JobOrder.php` (modify: relations, casts) | model | CRUD | itself (existing) | exact |
| `app/Actions/POS/ComputeJobOrderPrice.php` | service (action) | transform | `app/Actions/JobOrder/ValidateJobOrderFile.php` | role-match |
| `app/Actions/POS/ConfirmPaymentIntent.php` | service (action) | event-driven | `app/Actions/JobOrder/AssignArtistToJobOrder.php` | exact |
| `app/Http/Controllers/Cashier/PricingController.php` | controller | request-response | `app/Http/Controllers/FrontlineStaff/JobOrderController.php` | role-match |
| `app/Http/Controllers/Cashier/PaymentController.php` | controller | request-response | `app/Http/Controllers/Artist/DesignEditorController.php` | exact |
| `app/Http/Controllers/Cashier/ReconciliationController.php` | controller | event-driven | `app/Actions/JobOrder/AssignArtistToJobOrder.php` + `app/Http/Controllers/Artist/DesignEditorController.php` | role-match |
| `app/Http/Controllers/Cashier/ReceiptController.php` | controller | request-response | `app/Http/Controllers/Owner/AuditTrailController.php` (index-style, read-only render) | partial-match |
| `app/Http/Controllers/Cashier/CreditRequestController.php` | controller | request-response | `app/Http/Controllers/Owner/UserManagementController.php` | role-match |
| `app/Http/Controllers/Cashier/CancellationController.php` | controller | request-response | `app/Http/Controllers/Artist/DesignEditorController.php` (`requestChanges`, DB::transaction + status flip) | role-match |
| `app/Http/Controllers/Owner/CreditApprovalController.php` | controller | request-response | `app/Http/Controllers/Owner/UserManagementController.php` (`deactivate`/`reactivate`) | exact |
| `app/Http/Controllers/Webhooks/PaymongoWebhookController.php` | controller | event-driven | `app/Http/Controllers/Public/DesignReviewController.php` (public, unauthenticated, dual-caller idempotency) | role-match |
| `app/Http/Controllers/FrontlineStaff/JobOrderReleaseController.php` | controller | request-response | `app/Http/Controllers/FrontlineStaff/QueueEntryController.php` (`callNext`/`markDone` — guarded status transition) | exact |
| `app/Http/Requests/Cashier/SavePricingRequest.php` | middleware (form request) | request-response | `app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php` | exact |
| `app/Http/Requests/Cashier/RecordPaymentRequest.php` | middleware (form request) | request-response | `app/Http/Requests/FrontlineStaff/AddJobOrderRequest.php` | role-match |
| `app/Http/Requests/Cashier/CreateCreditRequestRequest.php` | middleware (form request) | request-response | `app/Http/Requests/Owner/DeactivateUserRequest.php` | role-match |
| `app/Http/Requests/Owner/ApproveCreditRequest.php` | middleware (form request) | request-response | `app/Http/Requests/Owner/DeactivateUserRequest.php` | exact |
| `app/Concerns/PricingValidationRules.php` | utility (concern) | transform | `app/Concerns/JobOrderValidationRules.php` | exact |
| `app/Concerns/PaymentValidationRules.php` | utility (concern) | transform | `app/Concerns/SystemConfigValidationRules.php` | role-match |
| `app/Policies/AccountsReceivablePolicy.php` | middleware (policy) | request-response | `app/Policies/DesignFilePolicy.php` | exact |
| `config/services.php` (modify: add `paymongo` key) | config | request-response | existing `resend` key in same file | exact |
| `config/paymongo.php` (published by `luigel/laravel-paymongo`) | config | request-response | n/a (vendor-published) | n/a |
| `bootstrap/app.php` (modify: `validateCsrfTokens(except:)`) | config | request-response | itself (existing `withMiddleware`/`withExceptions` blocks) | exact |
| `routes/portals.php` (modify: cashier.*, accounting-staff.* additions) | route | request-response | itself (existing `role:cashier`/`role:accounting_staff` groups) | exact |
| `routes/owner.php` (modify: credit-requests.* additions) | route | request-response | itself (existing `role:owner,admin` group) — but see Open Question on Owner-exclusive gating | exact |
| `routes/web.php` (modify: webhooks/paymongo route) | route | event-driven | itself (existing `design-review` public/signed group) | role-match |
| `database/seeders/SystemConfigurationSeeder.php` (modify: 2 new `business_rules` keys) | config | CRUD | itself (existing `rush_fee_percentage` entry) | exact |
| `database/factories/PricingEntryFactory.php` | test | transform | `database/factories/CustomerFactory.php` (simple flat model) | role-match |
| `database/factories/TransactionFactory.php` | test | transform | `database/factories/JobOrderFactory.php` | exact |
| `database/factories/AccountsReceivableFactory.php` | test | transform | `database/factories/JobOrderFactory.php` | role-match |
| `resources/js/pages/cashier/Dashboard.vue` (replace placeholder) | component (page) | CRUD | `resources/js/pages/owner/DesignOverrides.vue` | exact |
| `resources/js/pages/cashier/JobOrderPayment.vue` | component (page) | CRUD | `resources/js/pages/frontline-staff/NewVisit.vue` | exact |
| `resources/js/pages/cashier/Receipt.vue` | component (page) | request-response | `resources/js/pages/frontline-staff/NewVisit.vue` (Card-based summary section) | partial-match |
| `resources/js/pages/owner/CreditRequests.vue` | component (page) | CRUD | `resources/js/pages/owner/DesignOverrides.vue` | exact |
| `resources/js/pages/accounting-staff/Dashboard.vue` (replace placeholder) | component (page) | CRUD | `resources/js/pages/owner/DesignOverrides.vue` | exact |
| `resources/js/pages/frontline-staff/QueueList.vue` (modify: add Release action) | component (page) | request-response | itself (existing `callNext`/`markDone` Form buttons) | exact |
| `resources/js/components/PaymentQrCode.vue` | component | transform | none (new `qrcode.vue` wrapper) | no-analog |
| `resources/js/config/nav/owner.ts` (modify: add Credit Requests item) | config | transform | itself | exact |
| `resources/js/config/nav/accounting-staff.ts` (new) | config | transform | `resources/js/config/nav/frontline-staff.ts` | exact |
| `resources/js/config/nav/cashier.ts` (new) | config | transform | `resources/js/config/nav/frontline-staff.ts` | exact |

## Pattern Assignments

### `app/Models/Transaction.php` / `app/Models/PricingEntry.php` / `app/Models/AccountsReceivable.php` (model, CRUD)

**Analog:** `app/Models/JobOrder.php` (full file read)

**Fillable + audit observer pattern** (lines 1-36):
```php
namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $queue_entry_id
 * ...
 */
#[Fillable(['queue_entry_id', 'description', 'type', 'status', 'file_path', 'consultation_notes'])]
#[ObservedBy(AuditObserver::class)]
class JobOrder extends Model
{
    /** @use HasFactory<JobOrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => JobOrderType::class,
            'status' => JobOrderStatus::class,
            'queue_deprioritized_at' => 'datetime',
            'not_appeared' => 'boolean',
        ];
    }

    public function assignedArtist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_artist_id');
    }
}
```
Apply identically: `Transaction` gets `#[Fillable(['job_order_id', 'type', 'payment_method', 'amount', 'status', 'reference_number', 'paymongo_payment_intent_id', 'recorded_by'])]` + `#[ObservedBy(AuditObserver::class)]`, casts `type`/`payment_method`/`status` to their new enums, `confirmed_at` to `datetime`. `PricingEntry` and `AccountsReceivable` follow the same shape — `SystemConfiguration.php` (read in full) is the closer analog for `PricingEntry` specifically because it is also a flat lookup-style table with a `getRouteKeyName()` override potential (not needed here unless the catalog is routed by slug, which it isn't per the research schema).

---

### `app/Actions/POS/ConfirmPaymentIntent.php` (service/action, event-driven)

**Analog:** `app/Actions/JobOrder/AssignArtistToJobOrder.php` (full file read)

**Locked, guarded, idempotent transaction pattern** (lines 12-52):
```php
namespace App\Actions\JobOrder;

use App\Enums\JobOrderStatus;
use App\Enums\JobOrderType;
use App\Enums\UserRole;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
**Apply to `ConfirmPaymentIntent`:** re-fetch the `Transaction` row under `lockForUpdate()` inside `DB::transaction()`, guard on `$locked->status !== TransactionStatus::PendingConfirmation` before writing (mirrors the `if ($artist === null) return null;` early-exit shape), then flip both `Transaction.status` and `JobOrder.payment_status` inside the same transaction. This is the exact class the shared webhook + reconciliation caller must invoke — see RESEARCH.md Pattern 1 for the full drafted body, already grounded in this same analog.

**Unit test pattern to copy:** `tests/Feature/JobOrder/AssignArtistToJobOrderTest.php` (full file read) — direct `(new Action)($model)` invocation, `expect($model->fresh()->field)->toBe(...)`, one test per guard branch (happy path, no-op/already-resolved branch, wrong-precondition-type branch). Write `tests/Unit/Actions/ConfirmPaymentIntentTest.php` with: (1) webhook confirms a pending transaction, (2) reconciliation confirms a pending transaction, (3) calling it twice on an already-resolved transaction is a no-op (the highest-value test per RESEARCH.md's Wave 0 gap list).

---

### `app/Http/Controllers/Cashier/PaymentController.php` (controller, request-response)

**Analog:** `app/Http/Controllers/Artist/DesignEditorController.php` (full file read)

**Guarded status-transition pattern with DB::transaction + toast** (lines 73-92, `approve()`):
```php
public function approve(RecordDesignVerdictRequest $request, JobOrder $jobOrder): RedirectResponse
{
    abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, 'This job order is not assigned to you.');
    abort_unless($jobOrder->status === JobOrderStatus::PendingReview, 422, 'This job order is not pending review.');

    DB::transaction(function () use ($jobOrder) {
        $this->latestUnreviewedRevisionLog($jobOrder)->forceFill([
            'outcome' => 'approved',
            'reviewed_at' => now(),
        ])->save();

        $jobOrder->designFile->forceFill(['locked_at' => now()])->save();

        $jobOrder->forceFill(['status' => JobOrderStatus::DesignApproved])->save();
    });

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Design approved. This job order is ready for pricing at the Cashier.')]);

    return back();
}
```
Apply identically for Cash/Bank Transfer full-payment recording: `abort_unless` on job order eligibility (production-ready/design-approved, no existing completed full-payment), `DB::transaction` wrapping the `Transaction::create()` + `job_orders.payment_status` flip, `Inertia::flash('toast', ...)` per the Copywriting Contract's "Payment recorded..." strings, `return back()`.

**Constructor DI pattern** (line 19): `public function __construct(public RecordDesignRevision $recordDesignRevision) {}` — inject `ComputeJobOrderPrice`/`ConfirmPaymentIntent` the same way into `PricingController`/`PaymentController`/`ReconciliationController`.

---

### `app/Http/Controllers/Owner/CreditApprovalController.php` (controller, request-response)

**Analog:** `app/Http/Controllers/Owner/UserManagementController.php` (full file read)

**Approve/reject-shaped mutation pair** (lines 50-73):
```php
public function deactivate(DeactivateUserRequest $request, User $user): RedirectResponse
{
    $user->forceFill(['is_active' => false])->save();

    Inertia::flash('toast', ['type' => 'success', 'message' => __(":name's account has been deactivated.", ['name' => $user->name])]);

    return back();
}

public function reactivate(ReactivateUserRequest $request, User $user): RedirectResponse
{
    $user->forceFill(['is_active' => true])->save();

    Inertia::flash('toast', ['type' => 'success', 'message' => __(":name's account has been reactivated.", ['name' => $user->name])]);

    return back();
}
```
Apply as `approve(ApproveCreditRequest $request, AccountsReceivable $ar)` / `reject(...)`: `forceFill(['status' => ..., 'approved_by' => $request->user()->id, 'approved_at' => now()])->save()`, plus the job order's `payment_status` flip to `OnCredit` or `CreditRejected` in the same method (single-row mutation, no `DB::transaction()` needed here since it's one Eloquent model per call unless the AR write and JobOrder write must be atomic together — in that case wrap both writes in `DB::transaction()` per the Money-Moving precedent in `ConfirmPaymentIntent`).

**Authorization delegation pattern** — see `DeactivateUserRequest` below; `ApproveCreditRequest`/rejection should follow the identical `$this->user()->can('approve', $this->route('accountsReceivable'))` shape, backed by `AccountsReceivablePolicy` (see Policy section below), since PROJECT.md and CONTEXT.md D-06/D-07/D-08 specify Owner-exclusive approval — mirroring `DesignFilePolicy::unlock()`'s "Owner only, deliberately not extended to Admin" exact wording and reasoning.

---

### `app/Http/Controllers/Webhooks/PaymongoWebhookController.php` (controller, event-driven)

**Analog:** `app/Http/Controllers/Public/DesignReviewController.php` (full file read)

**Public/unauthenticated dual-caller idempotency-guard shape** (lines 33-52, `approve()`):
```php
public function approve(RevisionLog $revisionLog): Response
{
    $revisionLog->loadMissing('jobOrder.designFile');
    $jobOrder = $revisionLog->jobOrder;

    if ($this->isActionable($revisionLog, $jobOrder)) {
        DB::transaction(function () use ($revisionLog, $jobOrder): void {
            $revisionLog->forceFill(['outcome' => 'approved', 'reviewed_at' => now()])->save();
            $jobOrder->designFile->forceFill(['locked_at' => now()])->save();
            $jobOrder->forceFill(['status' => JobOrderStatus::DesignApproved])->save();
        });
    }

    return $this->render($revisionLog->fresh(['jobOrder.designFile']));
}

private function isActionable(RevisionLog $revisionLog, JobOrder $jobOrder): bool
{
    return $jobOrder->status === JobOrderStatus::PendingReview && $revisionLog->outcome === null;
}
```
**Important deviation flagged by RESEARCH.md's Anti-Patterns section:** this codebase's own `DesignReviewController`/`DesignEditorController` pair *duplicates* the guard logic across two independent callers by explicit design (cheap to re-derive). For the webhook, do **not** copy that duplication — instead call the single shared `ConfirmPaymentIntent` action from both `PaymongoWebhookController::__invoke()` and `ReconciliationController`, per Pattern 1. Use `DesignReviewController` only for the *routing shape* (public, unauthenticated, outside `role:*`) and the *idempotency-guard concept* (an `isActionable`-style check before writing), not for the duplicate-controller-logic structure itself.

---

### Public/webhook route registration (route, event-driven)

**Analog:** `routes/web.php` (full file read)

**Existing public/signed route group** (lines 16-23):
```php
// Public, unauthenticated, signed-URL-protected (D-17 through D-21) — the
// client's remote design-review path, reached only via an emailed
// temporarySignedRoute() link. Deliberately outside every role:* group.
Route::middleware(['signed', 'throttle:60,1'])->prefix('design-review')->group(function () {
    Route::get('{revisionLog}', [DesignReviewController::class, 'show'])->name('public.design-review.show');
    Route::post('{revisionLog}/approve', [DesignReviewController::class, 'approve'])->name('public.design-review.approve');
});
```
**Apply, with a key difference:** the webhook route has no signed-URL equivalent (PayMongo signs the body via HMAC, not a `temporarySignedRoute()`), so it must instead use `luigel/laravel-paymongo`'s `paymongo.signature` middleware and be explicitly CSRF-excluded (net new to this codebase — confirmed via `grep -rn "validateCsrfTokens" bootstrap/app.php` returning nothing). See RESEARCH.md Pattern 2/Pitfall 1 for the exact `bootstrap/app.php` addition — this is the single highest-risk net-new wiring in this phase and has zero existing precedent to copy verbatim; the `web.php` comment-convention (documenting *why* a route sits outside `role:*`) is the only directly-reusable piece.

---

### `app/Http/Requests/Owner/ApproveCreditRequest.php` / `CreateCreditRequestRequest.php` (middleware/form request, request-response)

**Analog:** `app/Http/Requests/Owner/DeactivateUserRequest.php` (full file read)

**Policy-delegated authorize() pattern**:
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
Apply identically: `ApproveCreditRequest::authorize()` returns `$this->user()->can('approve', $this->route('accountsReceivable'))`, backed by a new `AccountsReceivablePolicy::approve()`/`reject()` matching `DesignFilePolicy`'s shape below. Empty `rules()` array is correct here too — the mutation takes no body input beyond the route-bound model.

---

### `app/Policies/AccountsReceivablePolicy.php` (middleware/policy, request-response)

**Analog:** `app/Policies/DesignFilePolicy.php` (full file read)

```php
namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DesignFile;
use App\Models\User;

class DesignFilePolicy
{
    /**
     * Owner only, deliberately not extended to Admin (unlike
     * UserPolicy::deactivate()), matching JOB-07's exact "Owner can
     * authorize" wording.
     */
    public function unlock(User $actor, DesignFile $designFile): bool
    {
        return $actor->role === UserRole::Owner;
    }
}
```
Apply identically for `approve(User $actor, AccountsReceivable $ar)` / `reject(...)`: `return $actor->role === UserRole::Owner;` — this is a direct, load-bearing precedent for RESEARCH.md's Open Question 1 ("Owner-only, not Owner+Admin") since it's the *only* existing example of an Owner-exclusive (excluding Admin) authorization check in this codebase, despite `routes/owner.php`'s route-group middleware being the broader `role:owner,admin`. Gate the credit-approval mutation via this Policy + FormRequest `authorize()`, not via route middleware, exactly as `DesignFileController::unlock` already does (route stays inside the shared `role:owner,admin` group for page-visibility, the Policy narrows to Owner-only for the actual mutation).

---

### `app/Concerns/PricingValidationRules.php` / `PaymentValidationRules.php` (utility/concern, transform)

**Analog:** `app/Concerns/JobOrderValidationRules.php` + `app/Concerns/SystemConfigValidationRules.php` (both full files read)

**Reusable rule-set trait, enum-driven `Rule::enum()`** (lines 22-30 of `JobOrderValidationRules.php`):
```php
protected function jobOrderRules(): array
{
    return [
        'description' => ['required', 'string', 'max:255'],
        'type' => ['required', Rule::enum(JobOrderType::class)],
        'file' => ['nullable', 'file', 'required_if:type,'.JobOrderType::TypeA->value],
    ];
}
```
**Config-driven conditional rules** (lines 15-27 of `SystemConfigValidationRules.php`):
```php
protected function valueRules(string $type): array
{
    return match ($type) {
        'integer' => ['value' => ['required', 'integer', 'min:0']],
        'decimal' => ['value' => ['required', 'numeric', 'min:0']],
        default => ['value' => ['required', 'string', 'max:255']],
    };
}
```
Apply to `PaymentValidationRules::paymentRules()`: `'payment_method' => ['required', Rule::enum(PaymentMethod::class)]`, plus method-conditional rules via `match($method)` (e.g. `reference_number` required only when `bank_transfer`), mirroring `SystemConfigValidationRules`'s `match()`-on-type shape. For `PricingValidationRules::pricingRules()`, validate `discount_value` against the live `system_configurations` discount-cap key using a closure-based `Rule` (server-side authoritative per RESEARCH.md's explicit "never trust client-computed total" callout) — no direct existing precedent for a config-cap-bound closure rule in this codebase, flag this piece as net-new logic built *using* the existing trait/pattern, not copied verbatim.

---

### `config/services.php` (config, request-response)

**Analog:** itself — existing `resend` key (full file read, lines 21-23)

```php
'resend' => [
    'key' => env('RESEND_API_KEY'),
],
```
Add a `'paymongo' => ['secret_key' => env('PAYMONGO_SECRET_KEY'), 'public_key' => env('PAYMONGO_PUBLIC_KEY'), 'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET')]` entry the same way (though `luigel/laravel-paymongo` publishes its own `config/paymongo.php` — check whether the package reads from its own config file vs. `services.php` before duplicating keys; RESEARCH.md's Environment Availability table confirms no `config('services.paymongo')` block exists yet and recommends adding one "mirroring the existing `resend` key pattern").

---

### `database/seeders/SystemConfigurationSeeder.php` (config, CRUD)

**Analog:** itself — existing `rush_fee_percentage` entry (full file read, lines 55-62)

```php
[
    'key' => 'rush_fee_percentage',
    'group' => 'business_rules',
    'value' => 0,
    'type' => 'decimal',
    'label' => 'Rush fee (%)',
    'description' => null,
],
```
Add two new entries in the same `business_rules` group array, same `updateOrCreate` + `SystemConfiguration::invalidate($key)` loop (lines 123-130) — no new code path needed, just two new array rows for the discount cap and cancellation fee amount.

---

### `routes/portals.php` / `routes/owner.php` (route, request-response)

**Analog:** itself (full file read)

**Role-scoped prefixed group** (lines 42-44 of `portals.php`):
```php
Route::middleware(['auth', 'role:cashier'])->prefix('cashier')->name('cashier.')->group(function () {
    Route::inertia('dashboard', 'cashier/Dashboard')->name('dashboard');
});
```
Replace the placeholder `Route::inertia` with real controller routes (`pricing`, `payments`, `reconcile`, `receipt`, `credit-requests`) inside the same existing `role:cashier` group; add a parallel `role:accounting_staff` block using the identical shape for the reconciliation-list route. For `routes/owner.php` (lines 9-19), add `credit-requests.*` routes inside the existing `role:owner,admin` group for index/list visibility, while the actual approve/reject *mutation* narrows via the Policy (see above) — do not create a separate `role:owner`-only route group, since no precedent for that exists and the Policy-narrowing approach is directly grounded in `DesignFileController::unlock`'s exact existing shape.

---

## Shared Patterns

### Audit trail coverage (all new models)
**Source:** `app/Observers/AuditObserver.php` (full file read) + `#[ObservedBy(AuditObserver::class)]` attribute on `app/Models/JobOrder.php`
**Apply to:** `Transaction`, `PricingEntry`, `AccountsReceivable` models — add `#[ObservedBy(AuditObserver::class)]` directly on the class, exactly like every existing domain model. No controller-side code needed; `created`/`updated`/`deleted` are captured automatically and redacted via `getHidden()`.

### Mutation feedback toast
**Source:** used throughout — e.g. `app/Http/Controllers/Owner/UserManagementController.php` lines 57, 69; `app/Http/Controllers/Artist/DesignEditorController.php` lines 64, 89, 116
```php
Inertia::flash('toast', ['type' => 'success', 'message' => __('...')]);
return back();
```
**Apply to:** every mutating POS controller method — use the exact Copywriting Contract strings from `05-UI-SPEC.md` (e.g. `"Payment recorded. Job order is fully paid."`, `"Credit approved and posted to Accounts Receivable."`).

### Guarded state-transition preconditions
**Source:** `app/Http/Controllers/Artist/DesignEditorController.php` (`abort_unless`/`abort_if` pairs), `app/Http/Controllers/FrontlineStaff/QueueEntryController.php` (`callNext`/`markDone`)
```php
abort_unless($queueEntry->status === QueueStatus::Waiting, 422, 'This queue entry is not waiting.');
```
**Apply to:** `PaymentController`, `ReconciliationController`, `CreditApprovalController`, `JobOrderReleaseController` — every action that depends on the job order/transaction/AR row being in a specific state must `abort_unless`/`abort_if` before mutating, with a human-readable message (these reach the user via the `422` → `Inertia::flash('toast', ['type' => 'error', ...])` global handler already wired in `bootstrap/app.php` lines 67-71 — no new exception-handling code needed).

### `system_configurations` read pattern (never hardcode business-rule thresholds)
**Source:** `app/Models/SystemConfiguration.php` (full file read, `getInt`/`getBool`/`getArray`/`getString` static helpers), used e.g. in `app/Http/Controllers/Owner/UserManagementController.php` line 24: `SystemConfiguration::getInt('max_artist_break_minutes', 15)`
**Apply to:** rush-fee percentage read (`SystemConfiguration::getInt('rush_fee_percentage', 0)` — note: seeded as `type: decimal`, likely needs a `getFloat`/cast-aware helper or `getString`+cast; check the existing helper set covers `decimal` before assuming `getInt` is correct), discount cap, cancellation fee amount — every POS-01/POS-02/POS-04/POS-07 money computation must read through this cache-invalidated static accessor, never a raw `SystemConfiguration::where(...)->first()->value` or a hardcoded literal.

### Server-authoritative recomputation (never trust client totals)
**Source:** RESEARCH.md's explicit architecture-map row + Anti-Patterns section, grounded in this codebase's existing FormRequest+Concern validation-owns-the-truth pattern (`app/Concerns/SystemConfigValidationRules.php`, `app/Http/Requests/Owner/UpdateSystemConfigurationRequest.php`)
**Apply to:** `ComputeJobOrderPrice` action must be the single source of truth for `total_amount`; `PricingController`/`PaymentController` recompute server-side from submitted catalog pick + rush toggle + discount value and persist the *server-computed* result, never trusting a client-submitted `total_amount` field directly (Vue only renders a live preview per `05-UI-SPEC.md`'s explicit "server recomputes and is authoritative on submit" note).

### Frontend: Table + status Badge + DropdownMenu/AlertDialog row actions
**Source:** `resources/js/pages/owner/DesignOverrides.vue` (full file read) — `Table`/`TableHeader`/`TableBody`/`TableEmpty` shadcn primitives, per-row `AlertDialog` wrapping a `Form v-bind="Controller.action.form(id)"`
```vue
<AlertDialog>
    <AlertDialogTrigger as-child>
        <Button variant="destructive" :data-test="`unlock-design-${jobOrder.id}-button`">Unlock Design</Button>
    </AlertDialogTrigger>
    <AlertDialogContent>
        <AlertDialogHeader>
            <AlertDialogTitle>...</AlertDialogTitle>
            <AlertDialogDescription>...</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <Form v-bind="DesignFileController.unlock.form(jobOrder.design_file.id)" :options="{ preserveScroll: true }" v-slot="{ processing }">
                <Button type="submit" variant="destructive" :disabled="processing" :data-test="`confirm-unlock-${jobOrder.id}-button`">Unlock Design</Button>
            </Form>
        </AlertDialogFooter>
    </AlertDialogContent>
</AlertDialog>
```
**Apply to:** `cashier/Dashboard.vue`, `owner/CreditRequests.vue`, `accounting-staff/Dashboard.vue` — this is the exact shape for "Approve Credit"/"Reject Credit" (owner/CreditRequests.vue), "Cancel Job Order" (cashier/Dashboard.vue), and every other `AlertDialog`-gated action `05-UI-SPEC.md` calls for. Status badges follow the existing per-status `v-if`/`v-else-if` chain visible in `resources/js/pages/frontline-staff/QueueList.vue` lines 135-166 (outline/default/destructive/green-success variants keyed off the enum string value) — apply the same chain shape for the new `payment_status`/`TransactionStatus`/AR `status` badge mappings defined in `05-UI-SPEC.md`.

### Frontend: multi-section `useForm` with live client-side computed preview
**Source:** `resources/js/pages/frontline-staff/NewVisit.vue` (full file read) — `useForm({...})`, reactive row arrays, `watch()`-synced derived state, conditional field rendering by a radio/type value
**Apply to:** `cashier/JobOrderPayment.vue` — same `useForm` shape for the combined Pricing+Payment submission (`05-UI-SPEC.md`'s "no standalone Save Pricing button" — submitted together in one form, exactly matching `NewVisit.vue`'s single combined `intakeForm` submitted via `submitIntake()`), same `v-if`/`v-else-if` conditional-field-by-method-radio pattern already proven for Type A/B's conditional file input (lines 480-528).

### Frontend: Dialog + Form combined component
**Source:** `resources/js/components/ReplaceJobOrderFileDialog.vue` (referenced/used in `NewVisit.vue` line 9 and `QueueList.vue` line 7 — a slot-triggered `Dialog` wrapping a `Form`)
**Apply to:** if planning wants a reusable "On-Credit request" or "Cancellation" dialog component (rather than inlining the `AlertDialog` per-row in `Dashboard.vue`), this is the component-extraction precedent to follow — a small wrapper taking a `job-order-id` prop and exposing a trigger slot.

### Nav config registration
**Source:** `resources/js/config/nav/frontline-staff.ts` (full file read) + `resources/js/config/nav/owner.ts` (full file read)
```ts
import { LayoutGrid, ... } from '@lucide/vue';
import { dashboard } from '@/routes/<portal>';
import type { NavItem } from '@/types';

export const <portal>NavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
];
```
**Apply to:** new `resources/js/config/nav/cashier.ts` and `resources/js/config/nav/accounting-staff.ts` (both currently pass `navItems: []` per `cashier/Dashboard.vue` line 8) — same array-of-`NavItem` shape, Wayfinder-generated `href` values, never hardcoded URL strings. Extend `owner.ts` with a `{ title: 'Credit Requests', href: creditRequestsIndex(), icon: CreditCard }` entry after the existing "Design Overrides" entry (line 33), per `05-UI-SPEC.md`'s explicit nav-addition instruction.

## No Analog Found

| File | Role | Data Flow | Reason |
|------|------|-----------|--------|
| `resources/js/components/PaymentQrCode.vue` | component | transform | No existing component wraps a third-party client-side rendering library (`qrcode.vue`) in this codebase — every existing custom component wraps only first-party shadcn primitives or Inertia forms. Build as a thin prop-in (`redirect-url: string`) wrapper per `05-UI-SPEC.md`'s exact spec (no wrapper logic beyond passing the URL to `<qrcode-vue>`); use RESEARCH.md's Code Examples section for the PayMongo `next_action.redirect.url` shape the prop will receive. |
| `resources/js/pages/cashier/Receipt.vue` print-styling (`print:` Tailwind variants, `window.print()`, `print:hidden` on layout chrome) | component (page) | request-response | No existing page in this codebase uses print media queries or `window.print()` — every prior phase's "receipt-shaped" output has been a normal screen page. `05-UI-SPEC.md`'s Spacing section and Phase-Specific UI Notes §3 fully specify the layout (`max-w-sm mx-auto` on screen, `print:` collapse, `print:hidden` on the "Print Receipt" button and layout sidebar/header) — treat that spec section as the authoritative source since no codebase precedent exists to extract concrete excerpts from. |
| `app/Http/Controllers/Webhooks/PaymongoWebhookController.php`'s CSRF-exclusion wiring in `bootstrap/app.php` | config | event-driven | Confirmed via direct read of `bootstrap/app.php` that zero `validateCsrfTokens(except: [...])` calls exist anywhere in this codebase today — this is genuinely net-new wiring, not an extension of an existing pattern. RESEARCH.md Pattern 2 / Pitfall 1 is the authoritative source (already cross-checked against Laravel 12.x/13.x official docs). |

## Metadata

**Analog search scope:** `app/Models/`, `app/Actions/`, `app/Http/Controllers/`, `app/Http/Requests/`, `app/Concerns/`, `app/Policies/`, `app/Enums/`, `app/Observers/`, `routes/`, `bootstrap/app.php`, `config/services.php`, `database/migrations/`, `database/seeders/`, `database/factories/`, `resources/js/pages/`, `resources/js/components/`, `resources/js/config/nav/`, `tests/Feature/`
**Files scanned:** ~55 read directly (full-file reads for all files under 2,000 lines; no file in this codebase exceeded that threshold, so no offset/limit targeting was necessary)
**Pattern extraction date:** 2026-09-04

**Key structural note for the planner:** every hard architectural problem this phase faces (idempotent payment confirmation, public/unauthenticated routing, Owner-exclusive approval gating, cache-invalidated config reads, audit coverage) already has a direct, load-bearing precedent somewhere in Phases 1-4 of this exact codebase — confirmed by reading each analog in full rather than inferring from RESEARCH.md's summaries alone. The two genuine net-new items (CSRF exclusion for the webhook route, and the `qrcode.vue`/print-styling frontend pieces) are called out explicitly above with no analog, matching RESEARCH.md's own "the actual net-new risk is narrower than it looks" conclusion.
