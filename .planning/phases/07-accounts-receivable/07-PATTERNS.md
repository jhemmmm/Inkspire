# Phase 7: Accounts Receivable - Pattern Map

**Mapped:** 2026-09-08
**Files analyzed:** 27
**Analogs found:** 26 / 27

Every analog below was read directly from this repository (not inferred from RESEARCH.md's excerpts) — line numbers refer to the current file state at mapping time.

## File Classification

| New/Modified File                                                                               | Role                  | Data Flow                               | Closest Analog                                                                                                                                                     | Match Quality                                |
| ----------------------------------------------------------------------------------------------- | --------------------- | --------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------- |
| `database/migrations/xxxx_add_aging_and_collection_columns_to_accounts_receivable_table.php`    | migration             | CRUD                                    | `database/migrations/2026_09_04_110000_add_released_at_to_job_orders_table.php`                                                                                    | exact (additive-columns shape)               |
| `app/Enums/AccountsReceivableAgingBracket.php`                                                  | model/enum            | CRUD                                    | `app/Enums/AccountsReceivableStatus.php`                                                                                                                           | exact (string-backed, same namespace)        |
| `app/Enums/AccountsReceivableCollectionStatus.php`                                              | model/enum            | CRUD                                    | `app/Enums/AccountsReceivableStatus.php`                                                                                                                           | exact                                        |
| `app/Enums/PaymentStatus.php` (extend, +`WrittenOff`)                                           | model/enum            | CRUD                                    | itself                                                                                                                                                             | exact                                        |
| `app/Models/AccountsReceivable.php` (extend)                                                    | model                 | CRUD                                    | itself                                                                                                                                                             | exact                                        |
| `database/factories/AccountsReceivableFactory.php` (extend)                                     | test/factory          | CRUD                                    | itself                                                                                                                                                             | exact                                        |
| `database/seeders/SystemConfigurationSeeder.php` (extend, +`credit_term_days`)                  | config                | CRUD                                    | itself (`default_sla_days` / `cancellation_fee_amount` entries)                                                                                                    | exact                                        |
| `app/Mail/AccountsReceivableReminder.php`                                                       | service (mailable)    | event-driven                            | `app/Mail/DesignReviewRequested.php`                                                                                                                               | exact                                        |
| `resources/views/mail/accounts-receivable-reminder.blade.php`                                   | template              | event-driven                            | `resources/views/mail/design-review-requested.blade.php`                                                                                                           | exact                                        |
| `app/Console/Commands/SendAccountsReceivableReminders.php`                                      | service (command)     | batch / event-driven                    | none in-repo (first Console Command) — closest process shape is `app/Actions/JobOrder/RecordDesignRevision.php` (mail-failure isolation)                           | partial                                      |
| `routes/console.php` (extend, `Schedule::command`)                                              | config/route          | batch                                   | itself (currently stock `inspire` only)                                                                                                                            | partial (no scheduling precedent exists yet) |
| `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php` (index, show)           | controller            | request-response (CRUD read)            | `app/Http/Controllers/Cashier/ReceiptController.php` (derived-balance shape) + `app/Http/Controllers/Owner/CreditApprovalController.php::index` (list query shape) | exact (composite)                            |
| `app/Http/Controllers/AccountingStaff/CollectionStatusController.php`                           | controller            | request-response (CRUD update)          | `app/Http/Controllers/Cashier/ReconciliationController.php` (un-narrowed mutation, role-middleware-only)                                                           | role-match                                   |
| `app/Http/Controllers/AccountingStaff/CollectionLetterController.php`                           | controller            | request-response (read-only render)     | `app/Http/Controllers/Cashier/ReceiptController.php`                                                                                                               | exact                                        |
| `app/Http/Controllers/AccountingStaff/WriteOffRequestController.php`                            | controller            | request-response                        | `app/Http/Controllers/Cashier/CreditRequestController.php`                                                                                                         | exact                                        |
| `app/Http/Controllers/Owner/WriteOffApprovalController.php` (approve, reject)                   | controller            | request-response (state transition)     | `app/Http/Controllers/Owner/CreditApprovalController.php`                                                                                                          | exact                                        |
| `app/Http/Requests/AccountingStaff/UpdateCollectionStatusRequest.php`                           | middleware/validation | request-response                        | `app/Http/Requests/Owner/ApproveCreditRequest.php` (Policy-driven `authorize()`) + new `AccountsReceivableValidationRules` trait for `rules()`                     | role-match                                   |
| `app/Http/Requests/AccountingStaff/RequestWriteOffRequest.php`                                  | middleware/validation | request-response                        | `app/Http/Requests/Cashier/CreateCreditRequestRequest.php` (Cashier-initiated async request)                                                                       | exact                                        |
| `app/Http/Requests/Owner/ApproveWriteOffRequest.php` + `RejectWriteOffRequest.php`              | middleware/validation | request-response                        | `app/Http/Requests/Owner/ApproveCreditRequest.php` / `RejectCreditRequest.php`                                                                                     | exact                                        |
| `app/Concerns/AccountsReceivableValidationRules.php` (new trait)                                | utility               | request-response                        | `app/Concerns/ProductionLogValidationRules.php`                                                                                                                    | exact                                        |
| `app/Policies/AccountsReceivablePolicy.php` (extend, +`approveWriteOff`/`rejectWriteOff`)       | middleware/validation | request-response                        | itself (`approve`/`reject` pair)                                                                                                                                   | exact                                        |
| `routes/portals.php` (extend `accounting-staff` group + new `owner` write-off routes)           | route                 | request-response                        | itself (existing groups)                                                                                                                                           | exact                                        |
| `resources/js/config/nav/accounting-staff.ts` (extend)                                          | config                | —                                       | itself                                                                                                                                                             | exact                                        |
| `resources/js/config/nav/owner.ts` (extend)                                                     | config                | —                                       | itself                                                                                                                                                             | exact                                        |
| `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue` (new)                        | component             | request-response (client-filtered list) | `resources/js/pages/owner/CreditRequests.vue` (Table shape)                                                                                                        | exact                                        |
| `resources/js/pages/accounting-staff/AccountsReceivable/Show.vue` (new)                         | component             | request-response                        | `resources/js/pages/owner/CreditRequests.vue` (Form + AlertDialog) + `resources/js/pages/cashier/Receipt.vue` (panel/Card layout)                                  | exact (composite)                            |
| `resources/js/pages/accounting-staff/CollectionLetter.vue` (new)                                | component             | transform (print)                       | `resources/js/pages/cashier/Receipt.vue`                                                                                                                           | exact                                        |
| `resources/js/pages/owner/WriteOffRequests.vue` (new)                                           | component             | request-response                        | `resources/js/pages/owner/CreditRequests.vue`                                                                                                                      | exact (near-copy, per UI-SPEC)               |
| `resources/js/pages/cashier/Dashboard.vue` (extend `paymentStatusLabel` + `v-if` chain)         | component             | CRUD                                    | itself                                                                                                                                                             | exact                                        |
| `resources/js/pages/frontline-staff/Dashboard.vue` (extend `paymentStatusLabel` + `v-if` chain) | component             | CRUD                                    | itself                                                                                                                                                             | exact                                        |

## Pattern Assignments

### `database/migrations/xxxx_add_aging_and_collection_columns_to_accounts_receivable_table.php`

**Analog:** `database/migrations/2026_09_04_110000_add_released_at_to_job_orders_table.php` (full file read above — the project's canonical single-column additive migration shape: `up()` adds with `->after()`, `down()` drops).

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

**Delta:** Same shape, on `accounts_receivable`, adding every column D-02/D-07/D-09/D-13 needs:

```php
Schema::table('accounts_receivable', function (Blueprint $table) {
    $table->timestamp('due_at')->nullable()->after('approved_at');
    $table->string('last_reminder_bracket')->nullable()->after('due_at');
    $table->timestamp('last_reminder_sent_at')->nullable()->after('last_reminder_bracket');
    $table->string('collection_status')->default('pending')->after('status');
    $table->text('write_off_reason')->nullable();
    $table->foreignId('write_off_requested_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('write_off_requested_at')->nullable();
});
```

`down()` reverses with `dropColumn([...])` and `dropConstrainedForeignId('write_off_requested_by')`, matching this repo's other multi-column additive migrations (e.g. `2026_09_04_090001_add_payment_columns_to_job_orders_table.php`, same pattern, not re-read here since the single-column analog above already demonstrates the full shape). A second migration step handling the `due_at` backfill for already-approved rows (CONTEXT.md's discretion note) should follow the WR-10 "atomic, re-runnable" shape — write it as a data migration (`DB::table('accounts_receivable')->whereNull('due_at')->where('status', 'active')->update(...)` inside `up()`, using `SystemConfiguration::getInt('credit_term_days', 30)` and `approved_at`), not a separate seeder.

---

### `app/Enums/AccountsReceivableAgingBracket.php` + `app/Enums/AccountsReceivableCollectionStatus.php`

**Analog:** `app/Enums/AccountsReceivableStatus.php` (full file read above) — string-backed, TitleCase keys, snake_case values, zero methods on the sibling enum in this same domain.

```php
<?php

namespace App\Enums;

enum AccountsReceivableStatus: string
{
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Rejected = 'rejected';
}
```

**Delta — `AccountsReceivableAgingBracket`:** six cases (D-03), plus a `rank()` method for D-07's ordinal comparison and a `reminderBearing()` static for D-03's "maps 1:1 onto AR-02's four triggers" (RESEARCH.md Pattern 3 has the full body — reuse it verbatim, it is the only place in this codebase an `AccountsReceivableStatus`-sibling enum carries methods, since D-03's bracket ranking genuinely needs one). Note this is a deliberate, justified departure from the "no enum methods" convention `JobOrderStatus` follows — document why in a class docblock the way `AccountsReceivablePolicy`'s docblock explains its own Owner-only departure.

**Delta — `AccountsReceivableCollectionStatus`:** six cases matching D-10 exactly, no methods:

```php
enum AccountsReceivableCollectionStatus: string
{
    case Pending = 'pending';
    case FollowUp = 'follow_up';
    case WarningSent = 'warning_sent';
    case Collections = 'collections';
    case Paid = 'paid';
    case WrittenOff = 'written_off';
}
```

---

### `app/Enums/PaymentStatus.php` (extend)

**Analog:** itself (full file read above).

```php
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case PendingConfirmation = 'pending_confirmation';
    case Paid = 'paid';
    case CreditPendingApproval = 'credit_pending_approval';
    case OnCredit = 'on_credit';
    case CreditRejected = 'credit_rejected';
}
```

**Delta:** append exactly one case, `case WrittenOff = 'written_off';` (D-14). This is an append-only edit — do not reorder or rename existing cases (their string values are already persisted in the `job_orders.payment_status` column and read by every consumer listed in the regression checklist below).

---

### `app/Models/AccountsReceivable.php` (extend)

**Analog:** itself (full file read above).

```php
#[Fillable(['job_order_id', 'balance', 'status', 'requested_by'])]
#[ObservedBy(AuditObserver::class)]
class AccountsReceivable extends Model
{
    /** @use HasFactory<AccountsReceivableFactory> */
    use HasFactory;

    protected $table = 'accounts_receivable';

    protected function casts(): array
    {
        return [
            'status' => AccountsReceivableStatus::class,
            'balance' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function jobOrder(): BelongsTo { return $this->belongsTo(JobOrder::class); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
```

**Delta:** keep the `#[Fillable]` list as-is (the new columns are all system/controller-`forceFill`-written, never mass-assigned from request input — matches D-10's "Paid/WrittenOff are system-set" and D-16's derived-balance intent). Extend `casts()` with `'due_at' => 'datetime'`, `'last_reminder_sent_at' => 'datetime'`, `'write_off_requested_at' => 'datetime'`, `'collection_status' => AccountsReceivableCollectionStatus::class`. Add `writeOffRequestedBy(): BelongsTo` (`belongsTo(User::class, 'write_off_requested_by')`, same shape as `requestedBy()`/`approvedBy()`). Add the pure `agingBracket(): AccountsReceivableAgingBracket` accessor method (RESEARCH.md Pattern 3, full body — a `match(true)` on `due_at`-vs-`now()`, no query). Keep `$table = 'accounts_receivable'` untouched — do not remove it.

---

### `database/factories/AccountsReceivableFactory.php` (extend)

**Analog:** itself (full file read above) — note the `afterCreating()`-not-`state()` convention documented in its own docblock, because `approved_by`/`approved_at` sit outside `#[Fillable]`.

```php
public function active(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => AccountsReceivableStatus::Active->value,
    ])->afterCreating(fn (AccountsReceivable $accountsReceivable) => $accountsReceivable->forceFill([
        'approved_by' => User::factory()->owner(),
        'approved_at' => now(),
    ])->save());
}
```

**Delta:** extend `active()`'s `afterCreating()` closure to also stamp `due_at` (`now()->addDays(30)` default, matching `credit_term_days`'s seeded default), leaving `collection_status` at its column default (`pending`). Add a new `atBracket(AccountsReceivableAgingBracket $bracket)` state (Wave 0 Gaps calls for this explicitly) that backdates `due_at` into the past by the right number of days per bracket, using the same `afterCreating()`-with-`forceFill()` pattern since `due_at` is outside `#[Fillable]`. Do not add `state()`-only bracket helpers — any column outside the `#[Fillable]` list must go through `afterCreating()->forceFill()`, exactly as `active()`/`rejected()` already establish.

---

### `database/seeders/SystemConfigurationSeeder.php` (extend)

**Analog:** itself (full file read above) — `business_rules` group, `updateOrCreate` + `invalidate()` per entry.

```php
[
    'key' => 'cancellation_fee_amount',
    'group' => 'business_rules',
    'value' => 500,
    'type' => 'decimal',
    'label' => 'Cancellation fee (flat ₱)',
    'description' => null,
],
```

**Delta:** append one array entry to the `business_rules` group:

```php
[
    'key' => 'credit_term_days',
    'group' => 'business_rules',
    'value' => 30,
    'type' => 'integer',
    'label' => 'Credit term (days)',
    'description' => 'Days after Owner approval before an On-Credit balance is considered due (D-02).',
],
```

No structural change to the seeder's `foreach`/`updateOrCreate`/`invalidate` loop — it already iterates every entry in the array generically.

---

### `app/Mail/AccountsReceivableReminder.php` + `resources/views/mail/accounts-receivable-reminder.blade.php`

**Analog:** `app/Mail/DesignReviewRequested.php` (full file read above) — the only Mailable in the codebase, and `resources/views/mail/design-review-requested.blade.php` (full file read above) — the only Markdown mail view.

```php
class DesignReviewRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RevisionLog $revisionLog) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your design is ready for review — :description', ['description' => $this->revisionLog->jobOrder->description]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.design-review-requested',
            with: [
                'jobOrderDescription' => $this->revisionLog->jobOrder->description,
                'reviewUrl' => URL::temporarySignedRoute(...),
            ],
        );
    }

    public function attachments(): array { return []; }
}
```

```blade
<x-mail::message>
# Your design is ready for review

The design for **{{ $jobOrderDescription }}** is ready for your review.

<x-mail::button :url="$reviewUrl">
Review Your Design
</x-mail::button>

This link is valid for 7 days.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
```

**Delta:** single class, bracket-driven (CONTEXT.md discretion explicitly allows this over four classes). `__construct(public AccountsReceivable $receivable, public AccountsReceivableAgingBracket $bracket)`. `envelope()` selects the subject via a `match($this->bracket)` using the exact four subject-line strings the UI-SPEC's Copywriting Contract locks (`"AR notice — {JO number} is {n} days past due"` etc. — interpolate `$this->receivable->jobOrder->number` and days-past-due). `content()` points at a new `mail.accounts-receivable-reminder` Markdown view with `with: ['bracket' => $this->bracket, 'receivable' => $this->receivable, 'daysPastDue' => ..., 'leadParagraph' => ...]` — compute the lead paragraph and closing line server-side via `match()` on the bracket (four variants each, per UI-SPEC §5) rather than branching inside the Blade template, matching `DesignReviewRequested`'s convention of pre-computing view data in `content()` rather than in the view. **No `<x-mail::button>`** — UI-SPEC §5 is explicit that the email contains no deep link (different portals, different role middleware, one link would 403 for one recipient). Use `<x-mail::table>` or a plain heading list for the detail block (Customer / Job Order / Outstanding Balance / Due Date / Days Past Due / Collection Status) instead of a button CTA.

---

### `app/Console/Commands/SendAccountsReceivableReminders.php`

**Analog:** none in-repo — this is the project's first Console Command (`app/Console/Commands/` does not exist yet). Structural precedent for **mail-failure isolation only** comes from `app/Actions/JobOrder/RecordDesignRevision.php` (full file read above):

```php
public function __invoke(JobOrder $jobOrder, UploadedFile $file): void
{
    $revisionLog = DB::transaction(function () use ($jobOrder, $file): RevisionLog {
        // ... transactional writes ...
        return $revisionLog;
    });

    try {
        Mail::to($jobOrder->queueEntry->customer->email)->send(new DesignReviewRequested($revisionLog));
    } catch (\Throwable $e) {
        report($e);
    }
}
```

**Delta:** the send-then-catch-then-report shape is identical, but inverted in order for this command per RESEARCH.md Pitfall 2 — attempt the mail **first**, `report()` on failure, and only then `forceFill()->save()` the bracket/timestamp stamp **outside any transaction wrapping the mail call** (a single-row write needs no `DB::transaction()` at all here, unlike `RecordDesignRevision`'s multi-row case). Use `AccountsReceivable::query()->where(...)->chunkById(50, ...)` for the query loop (no existing chunked-command precedent in-repo; this is standard Eloquent, not a project-specific pattern). Full algorithm in RESEARCH.md `## Architecture Patterns` → Pattern 1 — copy that code block as the concrete implementation baseline; the planner's job is to lock the D-07/D-08 idempotency resolution (two-column vs. single-column) explicitly per RESEARCH.md's Open Question 1 before implementing, not to re-derive the algorithm from scratch.

---

### `routes/console.php` (extend)

**Analog:** itself (full file read above — currently only the stock `inspire` example).

```php
<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
```

**Delta:** append, do not replace, the `inspire` stub. Add:

```php
use App\Console\Commands\SendAccountsReceivableReminders;
use Illuminate\Support\Facades\Schedule;

Schedule::command(SendAccountsReceivableReminders::class)
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();
```

`onOneServer()` is safe — this project's `.env` sets `CACHE_STORE=database` [verified in RESEARCH.md], one of the drivers `onOneServer()` requires. No existing routes/console.php precedent exists for scheduling in this repo; this is new wiring against the Laravel 13 docs, not a copy-paste from a sibling file.

---

### `app/Http/Controllers/AccountingStaff/AccountsReceivableController.php` (index, show)

**Analogs:** `app/Http/Controllers/Cashier/ReceiptController.php::show` (full file read above, derived-balance shape) and `app/Http/Controllers/Owner/CreditApprovalController.php::index` (full file read above, list-query shape).

```php
// ReceiptController::show — derived balance
$completedTransactions = $jobOrder->transactions->where('status', TransactionStatus::Completed);
$amountPaid = (float) $completedTransactions->sum('amount');
$balance = $jobOrder->total_amount !== null
    ? round((float) $jobOrder->total_amount - $amountPaid, 2)
    : 0.0;
```

```php
// CreditApprovalController::index — filtered list + eager-load + explicit get([...])
public function index(Request $request): Response
{
    return Inertia::render('owner/CreditRequests', [
        'creditRequests' => AccountsReceivable::query()
            ->where('status', AccountsReceivableStatus::PendingApproval->value)
            ->with(['jobOrder:id,number,description', 'jobOrder.queueEntry.customer:id,name', 'requestedBy:id,name'])
            ->get(['id', 'job_order_id', 'balance', 'requested_by', 'created_at']),
    ]);
}
```

**Delta — `index()`:** `where('status', AccountsReceivableStatus::Active->value)`, eager-load `['jobOrder:id,number,description,total_amount', 'jobOrder.queueEntry.customer:id,name', 'jobOrder.transactions:id,job_order_id,amount,status']` (explicit column allowlists per RESEARCH.md Pitfall 3 — never a bare relation name), map each row through the `ReceiptController`-style balance derivation, call `->agingBracket()` per row, and — per the UI-SPEC's binding open/closed split carried to the planner — filter/group the six bracket cards and tabs to `collection_status` not in (`paid`, `written_off`), with a separate server-computed "Closed" set for the eighth tab. Full query shape in RESEARCH.md Pattern 3.

**Delta — `show()`:** same eager-load/derivation, single record, plus `loadMissing` the 20-most-recent `audit_trail` rows scoped to this model (only if the planner keeps the optional Activity panel — CONTEXT.md explicitly permits dropping it).

---

### `app/Http/Controllers/AccountingStaff/CollectionStatusController.php`

**Analog:** `app/Http/Controllers/Cashier/ReconciliationController.php` — the un-narrowed, role-middleware-only mutation shape (no Policy call), matching RESEARCH.md's Assumption A3 that D-10 needs no Policy narrowing beyond `role:accounting_staff`. (Not re-read in full here — RESEARCH.md's Pattern 4 already documents the exact `Rule::in()` allowlist restricting `collection_status` to the four human-settable values, rejecting `paid`/`written_off` from client input.)

```php
// app/Concerns/AccountsReceivableValidationRules.php pattern (see below)
'collection_status' => ['required', Rule::in(['pending', 'follow_up', 'warning_sent', 'collections'])],
```

**Delta:** a single `update(UpdateCollectionStatusRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse` action. `abort_unless($accountsReceivable->status === AccountsReceivableStatus::Active, ...)` guard (a closed/terminal entry's control isn't rendered client-side per UI-SPEC §2, but the server must re-check independently). `forceFill(['collection_status' => $request->validated('collection_status')])->save()`. `Inertia::flash('toast', ['type' => 'success', 'message' => __("Collection status updated to :status.", [...])])`. `AuditObserver` on the model already covers the audit write — no extra code.

---

### `app/Http/Controllers/AccountingStaff/CollectionLetterController.php`

**Analog:** `app/Http/Controllers/Cashier/ReceiptController.php::show` (full file read above) — near-identical shape: read-only render, `abort_unless` a precondition, derive balance, `Inertia::render()`.

```php
public function show(Request $request, JobOrder $jobOrder): Response
{
    abort_unless($jobOrder->transactions()->exists(), 404, 'No payment has been recorded for this job order yet.');

    $jobOrder->loadMissing(['pricingEntry', 'queueEntry.customer:id,name', 'transactions.recordedBy:id,name']);

    $completedTransactions = $jobOrder->transactions->where('status', TransactionStatus::Completed);
    $amountPaid = (float) $completedTransactions->sum('amount');
    $balance = $jobOrder->total_amount !== null
        ? round((float) $jobOrder->total_amount - $amountPaid, 2)
        : 0.0;

    return Inertia::render('cashier/Receipt', [...]);
}
```

**Delta:** `show(AccountsReceivable $accountsReceivable): Response`. `abort_unless($accountsReceivable->status === AccountsReceivableStatus::Active, 404)`. `abort_if($accountsReceivable->agingBracket() === AccountsReceivableAgingBracket::Current, ...)` — UI-SPEC §3's "not yet due" guard renders a message instead of a 404, so pass a `pastDue: false` flag into the Inertia props rather than aborting, and let `CollectionLetter.vue` render the guard copy. **Quote the current derived outstanding balance as "Amount Due"** (UI-SPEC §3, settling RESEARCH.md Open Question 2) — same derivation as above, not the raw `accounts_receivable.balance` column (that column feeds the separate "Credit Extended" line). Select `letterBody` via a `match()` on `agingBracket()` per D-12/UI-SPEC's four body variants.

---

### `app/Http/Controllers/AccountingStaff/WriteOffRequestController.php`

**Analog:** `app/Http/Controllers/Cashier/CreditRequestController.php::store` (full file read above) — the Accounting/Cashier-initiated async-request-with-guard shape.

```php
public function store(CreateCreditRequestRequest $request, JobOrder $jobOrder): RedirectResponse
{
    abort_if($jobOrder->cancelled_at !== null, 422, __('This job order has been cancelled.'));
    // ...eligibility checks...

    DB::transaction(function () use ($request, $jobOrder): void {
        $jobOrder = JobOrder::query()->whereKey($jobOrder->id)->lockForUpdate()->firstOrFail();
        // ...re-checked preconditions...

        AccountsReceivable::create([...]);
        $jobOrder->forceFill(['payment_status' => PaymentStatus::CreditPendingApproval])->save();
    });

    Inertia::flash('toast', ['type' => 'success', 'message' => __('On-Credit requested. Awaiting Owner approval.')]);

    return to_route('cashier.dashboard');
}
```

**Delta:** simpler — no pricing snapshot branch needed (an AR row already exists). `store(RequestWriteOffRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse`:

```php
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

No `DB::transaction()`/`lockForUpdate()` needed on the request side (single-row write, and a double-submit race is caught by the Owner-side approve/reject locked re-read, matching `CreditRequestController`'s own asymmetry — its lock exists because it also creates a new row from an unlocked read; this action only updates an existing row and the `write_off_requested_at !== null` guard is naturally idempotent-safe under Laravel's default request serialization). If the planner wants parity with `CreditRequestController`'s locking discipline, wrapping in `DB::transaction()` + `lockForUpdate()` is a safe, low-cost addition — not required by any locked decision either way.

---

### `app/Http/Controllers/Owner/WriteOffApprovalController.php` (approve, reject)

**Analog:** `app/Http/Controllers/Owner/CreditApprovalController.php` (full file read above) — this is the exact structural precedent D-13 names by name.

```php
public function approve(ApproveCreditRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
{
    DB::transaction(function () use ($request, $accountsReceivable): void {
        $accountsReceivable = AccountsReceivable::query()->whereKey($accountsReceivable->id)->lockForUpdate()->firstOrFail();

        abort_unless(
            $accountsReceivable->status === AccountsReceivableStatus::PendingApproval,
            422,
            __('This credit request has already been resolved.'),
        );

        $accountsReceivable->forceFill([
            'status' => AccountsReceivableStatus::Active,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ])->save();

        $accountsReceivable->jobOrder->forceFill(['payment_status' => PaymentStatus::OnCredit])->save();
    });

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Credit approved and posted to Accounts Receivable.')]);

    return back();
}
```

**Delta — `approve()`:** identical locked-re-read shape, but the guard checks `write_off_requested_at !== null` (not the `AccountsReceivableStatus` column — D-13/D-14 never move `status` off `Active`) and the terminal write is `collection_status => WrittenOff` plus the paired `jobOrder->payment_status => WrittenOff` write, both inside the same `DB::transaction()`:

```php
public function approve(ApproveWriteOffRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
{
    DB::transaction(function () use ($accountsReceivable): void {
        $accountsReceivable = AccountsReceivable::query()->whereKey($accountsReceivable->id)->lockForUpdate()->firstOrFail();

        abort_if($accountsReceivable->write_off_requested_at === null, 422, __('No write-off request is pending for this entry.'));

        $accountsReceivable->forceFill(['collection_status' => AccountsReceivableCollectionStatus::WrittenOff->value])->save();

        $accountsReceivable->jobOrder->forceFill(['payment_status' => PaymentStatus::WrittenOff->value])->save();
    });

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Write-off approved. :number is now marked Written Off.', [...])]);

    return back();
}
```

**Delta — `reject()`:** same locked-re-read guard, but nulls the three `write_off_*` columns instead of flipping status (CONTEXT.md discretion: "return to Active and keep aging" — `AccountsReceivableStatus` never left `Active` in the first place, so nulling is what "returns" the entry). `AuditObserver`'s existing `updated` hook captures the prior reason/requester/timestamp in `audit_trail.old_values` automatically — no extra logging code.

`AccountsReceivablePolicy` extends with the identical Owner-only shape:

```php
public function approve(User $actor, AccountsReceivable $accountsReceivable): bool
{
    return $actor->role === UserRole::Owner;
}

public function reject(User $actor, AccountsReceivable $accountsReceivable): bool
{
    return $this->approve($actor, $accountsReceivable);
}
```

**Delta:** add `approveWriteOff()` / `rejectWriteOff()` with the exact same one-line body (`$actor->role === UserRole::Owner`), matching the class's own existing docblock rationale for why this stays Owner-only below the `role:owner,admin` route-group middleware.

---

### `app/Http/Requests/AccountingStaff/UpdateCollectionStatusRequest.php` + `app/Http/Requests/AccountingStaff/RequestWriteOffRequest.php` + `app/Concerns/AccountsReceivableValidationRules.php`

**Analogs:** `app/Http/Requests/Owner/ApproveCreditRequest.php` (full file read above, for the Policy-driven `authorize()` shape) and `app/Concerns/ProductionLogValidationRules.php` (full file read above, for the concern-trait shape).

```php
// ApproveCreditRequest — Policy-driven authorize(), body-less rules()
class ApproveCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve', $this->route('accountsReceivable'));
    }

    public function rules(): array
    {
        return [
            //
        ];
    }
}
```

```php
// ProductionLogValidationRules — concern trait, single rule method
trait ProductionLogValidationRules
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function sendBackReasonRules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
```

**Delta — `app/Concerns/AccountsReceivableValidationRules.php`:**

```php
trait AccountsReceivableValidationRules
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function collectionStatusRules(): array
    {
        return [
            'collection_status' => ['required', Rule::in([
                AccountsReceivableCollectionStatus::Pending->value,
                AccountsReceivableCollectionStatus::FollowUp->value,
                AccountsReceivableCollectionStatus::WarningSent->value,
                AccountsReceivableCollectionStatus::Collections->value,
            ])],
        ];
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function writeOffReasonRules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }
}
```

**Delta — `UpdateCollectionStatusRequest`:** `authorize(): bool { return true; }` (no Policy narrowing per Assumption A3 — route-group `role:accounting_staff` middleware is the only gate), `rules(): array { return $this->collectionStatusRules(); }` via `use AccountsReceivableValidationRules;`.

**Delta — `RequestWriteOffRequest`:** same `authorize(): bool { return true; }`, `rules(): array { return $this->writeOffReasonRules(); }`.

**Delta — `ApproveWriteOffRequest` / `RejectWriteOffRequest`:** copy `ApproveCreditRequest`/`RejectCreditRequest` verbatim in shape — `authorize()` calls `$this->user()->can('approveWriteOff', $this->route('accountsReceivable'))` / `'rejectWriteOff'`, body-less `rules()`.

---

### `routes/portals.php` (extend)

**Analog:** itself (full file read above — every existing role group).

```php
Route::middleware(['auth', 'role:accounting_staff'])->prefix('accounting-staff')->name('accounting-staff.')->group(function () {
    Route::get('dashboard', [ReconciliationController::class, 'index'])->name('dashboard');
    Route::post('job-orders/{jobOrder}/reconcile', [ReconciliationController::class, 'store'])->name('job-orders.reconcile');
});
```

**Delta:** append inside the same `accounting-staff` group (this phase adds a surface, does not replace the placeholder — UI-SPEC §6):

```php
Route::get('accounts-receivable', [AccountsReceivableController::class, 'index'])->name('accounts-receivable.index');
Route::get('accounts-receivable/{accountsReceivable}', [AccountsReceivableController::class, 'show'])->name('accounts-receivable.show');
Route::patch('accounts-receivable/{accountsReceivable}/collection-status', [CollectionStatusController::class, 'update'])->name('accounts-receivable.collection-status.update');
Route::get('accounts-receivable/{accountsReceivable}/collection-letter', [CollectionLetterController::class, 'show'])->name('accounts-receivable.collection-letter.show');
Route::post('accounts-receivable/{accountsReceivable}/write-off', [WriteOffRequestController::class, 'store'])->name('accounts-receivable.write-off.store');
```

And a new block inside the existing `owner` route group (not shown in `routes/portals.php` — Owner's credit-requests routes live in `routes/owner.php` per RESEARCH.md's Sources list; the planner should locate and extend that file with the same `role:owner,admin` group, matching `CreditApprovalController`'s route names):

```php
Route::get('write-off-requests', [WriteOffApprovalController::class, 'index'])->name('write-off-requests.index');
Route::patch('accounts-receivable/{accountsReceivable}/write-off/approve', [WriteOffApprovalController::class, 'approve'])->name('write-off-requests.approve');
Route::patch('accounts-receivable/{accountsReceivable}/write-off/reject', [WriteOffApprovalController::class, 'reject'])->name('write-off-requests.reject');
```

Regenerate Wayfinder with `--with-form` after adding these (project convention, `01-04`'s documented trap).

---

### `resources/js/config/nav/accounting-staff.ts` + `resources/js/config/nav/owner.ts`

**Analog:** both files, in full, read above.

```typescript
// accounting-staff.ts, current state
export const accountingStaffNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
];
```

```typescript
// owner.ts, current state (relevant tail)
{ title: 'Credit Requests', href: creditRequestsIndex(), icon: CreditCard },
```

**Delta — `accounting-staff.ts`:** append `{ title: 'Accounts Receivable', href: index() /* from '@/routes/accounting-staff/accounts-receivable' */, icon: HandCoins }`, importing `HandCoins` from `@lucide/vue` (UI-SPEC §6, icon verified present).

**Delta — `owner.ts`:** append directly after "Credit Requests": `{ title: 'Write-Off Requests', href: index() /* from '@/routes/owner/write-off-requests' */, icon: FileMinus }`.

---

### `resources/js/pages/accounting-staff/AccountsReceivable/Index.vue`

**Analog:** `resources/js/pages/owner/CreditRequests.vue` (full file read above) — `Table`/`TableEmpty` shape, breadcrumb/nav wiring, `defineOptions({ layout: {...} })`.

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ownerNavItems } from '@/config/nav/owner';
import { index as creditRequestsIndex } from '@/routes/owner/credit-requests';

defineProps<{ creditRequests: CreditRequest[] }>();

defineOptions({
    layout: {
        navItems: ownerNavItems,
        breadcrumbs: [
            { title: 'Credit Requests', href: creditRequestsIndex() },
        ],
    },
});
</script>
```

**Delta:** swap `ownerNavItems`/breadcrumb for `accountingStaffNavItems` + the new accounts-receivable index route. Add the six bracket `Card`s (`grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6`) above the `Tabs`/`Table`, per UI-SPEC §1 — no existing page in this codebase combines stat cards + tabs + table on one page; the closest three-part composite precedent is `resources/js/pages/production-staff/Dashboard.vue` from Phase 6 (stat cards + `Tabs` + `Table`, per `06-PATTERNS.md`'s own composite mapping) — read that file directly before implementing if the planner wants a second concrete reference beyond `CreditRequests.vue`'s table alone. Client-side `Tabs` filtering over the already-fetched array (no server round-trip), `TableEmpty` per empty-state copy in UI-SPEC's Copywriting Contract. Money columns render via the new `₱{toLocaleString('en-PH', {...})}` formatting contract, not `CreditRequests.vue`'s existing `.toFixed(2)` (UI-SPEC §Typography: "Existing Phase 5 surfaces keep their toFixed(2) ... this phase introduces thousands separators").

---

### `resources/js/pages/accounting-staff/AccountsReceivable/Show.vue`

**Analogs:** `resources/js/pages/owner/CreditRequests.vue` (Form-bound-inside-AlertDialog pattern, full file read above) + `resources/js/pages/cashier/Receipt.vue` (stacked-Card panel layout, full file read above).

```vue
<!-- CreditRequests.vue's Form-inside-AlertDialog shape -->
<AlertDialog>
    <AlertDialogTrigger as-child>
        <Button :data-test="`approve-credit-${creditRequest.id}-button`">Approve Credit</Button>
    </AlertDialogTrigger>
    <AlertDialogContent>
        <AlertDialogHeader>
            <AlertDialogTitle>Approve On-Credit for ₱{{ Number(creditRequest.balance).toFixed(2) }}?</AlertDialogTitle>
        </AlertDialogHeader>
        <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <Form v-bind="CreditApprovalController.approve.form(creditRequest.id)" :options="{ preserveScroll: true }" v-slot="{ processing }">
                <Button type="submit" :disabled="processing">Confirm Approval</Button>
            </Form>
        </AlertDialogFooter>
    </AlertDialogContent>
</AlertDialog>
```

**Delta:** the "Request Write-Off" action uses a plain `Dialog` + `Textarea` + `Label` (not `AlertDialog`, per UI-SPEC's Destructive Confirmation section — collecting a reason is not a warning), bound to `WriteOffRequestController.store.form(accountsReceivable.id)`. The "Update Status" action is a plain `<Form>` (no dialog at all, per UI-SPEC — freely reversible, D-10) bound to a `Select` + submit button. Stack five `Card` panels (Amounts, Account Details, Collection Status, Actions row, Activity) at `lg` gap, matching `Receipt.vue`'s `CardContent` `grid gap-4` internal spacing convention. The pending-write-off `Alert` uses `AlertError.vue`'s icon+`AlertTitle`+`AlertDescription` structural shape (see 06-PATTERNS.md's "No Analog Found" entry for the same `Alert` primitive — still the only reference in this codebase) but with default, non-destructive styling.

---

### `resources/js/pages/accounting-staff/CollectionLetter.vue`

**Analog:** `resources/js/pages/cashier/Receipt.vue` (full file read above) — this is the exact pattern D-11 names by name.

```vue
<script setup lang="ts">
function printReceipt(): void {
    window.print();
}
</script>
<template>
    <Button
        variant="outline"
        class="mx-auto w-fit print:hidden"
        @click="printReceipt"
        >Print Receipt</Button
    >
    <Card class="mx-auto w-full max-w-sm">
        <CardContent class="grid gap-4">
            <!-- caption/value rows -->
        </CardContent>
    </Card>
</template>
```

**Delta:** rename the print handler/button to "Print Letter", widen the `Card` to `max-w-2xl` (UI-SPEC §3 — a letter is prose, not a till slip) and add `print:border-0 print:shadow-none` (the one refinement over `Receipt.vue`, which prints with a visible border). Document body order per UI-SPEC §3 (letterhead → rule → date → addressee → reference → bracket body paragraph → amount block → due-date line → payment instruction → signature). No `TrackingQrCode` — this page has no QR code. Reuse `Receipt.vue`'s exact `money()` helper function for figures inside this letter (the phase's new `toLocaleString`-with-separators contract applies to the _aging list/detail_ surfaces per UI-SPEC §Typography; the letter itself isn't listed among those, so default to matching `Receipt.vue`'s `toFixed(2)` unless the planner decides the letter's larger amounts warrant separators too — call this out for the planner to lock, it is a genuinely ambiguous edge in the UI-SPEC's Typography section).

---

### `resources/js/pages/owner/WriteOffRequests.vue`

**Analog:** `resources/js/pages/owner/CreditRequests.vue` (full file read above, in full — UI-SPEC §4 explicitly calls this a "near-copy" and directs reuse of the exact skeleton).

The entire file above is the template. **Delta (per UI-SPEC §4):**

- Add "Reason" (`line-clamp-2` in the cell) and "Days Past Due" (`tabular-nums`) table columns.
- **Invert button polarity**: "Approve Write-Off" becomes the `variant="destructive"` trigger+confirm pair (was `Button` default in `CreditRequests.vue`'s "Approve Credit"); "Reject Request" becomes the `variant="outline"` trigger with a default (`--primary`) confirm button (was `variant="destructive"` in `CreditRequests.vue`'s "Reject Credit"). This is the one structural difference from a literal copy — every other prop/slot/`Form v-bind` wiring is identical.
- Swap `CreditApprovalController.approve.form(...)` / `.reject.form(...)` for `WriteOffApprovalController.approve.form(...)` / `.reject.form(...)`.
- Keep the two-line "number over description" job-order cell and its `—` null fallback verbatim.

---

### `resources/js/pages/cashier/Dashboard.vue` + `resources/js/pages/frontline-staff/Dashboard.vue` (extend)

**Analog:** both files, themselves — the existing `paymentStatusLabel()` switch (verified at `cashier/Dashboard.vue:171-190`) and the `v-if`/`v-else-if` badge chain (verified at `cashier/Dashboard.vue:270-335`, `frontline-staff/Dashboard.vue:207-272`).

```typescript
// cashier/Dashboard.vue:171-190, verbatim current state
function paymentStatusLabel(status: string): string {
    switch (status) {
        case 'unpaid':
            return 'Unpaid';
        case 'partially_paid':
            return 'Partially Paid';
        case 'pending_confirmation':
            return 'Pending Confirmation';
        case 'paid':
            return 'Paid';
        case 'credit_pending_approval':
            return 'Credit Pending Approval';
        case 'on_credit':
            return 'On Credit';
        case 'credit_rejected':
            return 'Credit Rejected';
        default:
            return status;
    }
}
```

```vue
<!-- cashier/Dashboard.vue:299-336, verbatim shape — no v-else fallback -->
<Badge
    v-if="jobOrder.payment_status === 'unpaid'"
    ...
>{{ paymentStatusLabel(jobOrder.payment_status) }}</Badge>
<Badge v-else-if="jobOrder.payment_status === 'partially_paid'" ...>...</Badge>
<Badge v-else-if="jobOrder.payment_status === 'paid'" ...>...</Badge>
<Badge v-else-if="jobOrder.payment_status === 'on_credit'" ...>...</Badge>
<!-- no v-else — a payment_status not matched above renders nothing -->
```

**Delta:** add `case 'written_off': return 'Written Off';` to both files' `paymentStatusLabel()` switches, and one more `v-else-if="jobOrder.payment_status === 'written_off'"` branch to both `v-if` chains, using the `variant="outline" class="text-muted-foreground"` badge treatment from UI-SPEC's Color section. This is the exact regression class Phase 6's `06-07` fix plan addressed — treat the grep across both files as mandatory before considering AR-04 done (RESEARCH.md Pitfall 4 / UI-SPEC §7).

---

## Shared Patterns

### `#[ObservedBy(AuditObserver::class)]` — zero new code for audit coverage

**Source:** `app/Models/AccountsReceivable.php:27` (already attached).

**Apply to:** every collection-status update, write-off request/approve/reject write on `AccountsReceivable`, and the `PaymentStatus::WrittenOff` write on `JobOrder` (already observed from Phase 3/4). No new observer, no new table — the Activity panel (if built) reads this same `audit_trail` table.

### Locked re-read concurrency guard

**Source:** `app/Http/Controllers/Owner/CreditApprovalController.php:44-58` (verbatim above).

**Apply to:** `WriteOffApprovalController::approve()`/`reject()` — identical `lockForUpdate()` + `abort_unless`/`abort_if` inside `DB::transaction()` shape, guarding on `write_off_requested_at !== null` instead of `status === PendingApproval`.

### `Inertia::flash('toast', [...])` for mutation feedback

**Source:** every mutating controller, e.g. `CreditApprovalController::approve` (verbatim above: `Inertia::flash('toast', ['type' => 'success', 'message' => __('Credit approved...')]); return back();`).

**Apply to:** `CollectionStatusController::update`, `WriteOffRequestController::store`, `WriteOffApprovalController::approve`/`reject` — exact copy strings are locked in `07-UI-SPEC.md`'s Toasts table.

### System-config read pattern (`credit_term_days`)

**Source:** `SystemConfiguration::getFloat('cancellation_fee_amount', 500.0)` (project-wide static accessor convention, seeded in `SystemConfigurationSeeder`).

**Apply to:** the Owner's credit-approval controller (existing `app/Http/Controllers/Owner/CreditApprovalController.php::approve`, not shown as "new" above because it is a modification to Phase 5 code, not a Phase 7-created file, but it is where `due_at` must actually be stamped per D-02/RESEARCH.md Pitfall 6): `'due_at' => now()->addDays(SystemConfiguration::getInt('credit_term_days', 30))`, added inside the same `forceFill()` call that already sets `status => Active` / `approved_by` / `approved_at`. **This file is a required modification the file lists above under-represent** — flag it to the planner explicitly: `app/Http/Controllers/Owner/CreditApprovalController.php::approve()` needs one added line, or D-02 has no due date to compute brackets from.

### Wayfinder-generated route/action helpers

**Source:** every existing page, e.g. `resources/js/pages/owner/CreditRequests.vue:3,25` (`import CreditApprovalController from '@/actions/...'`, `import { index as creditRequestsIndex } from '@/routes/owner/credit-requests'`).

**Apply to:** every new controller action in this phase. Regenerate with `--with-form` after adding routes.

### Form Request + Concern trait pairing

**Source:** `app/Http/Requests/Owner/ApproveCreditRequest.php` + (for a real rule body) `app/Concerns/ProductionLogValidationRules.php` (both verbatim above).

**Apply to:** all four new Form Requests in this phase, via the new `AccountsReceivableValidationRules` trait.

---

## No Analog Found

| File / Pattern                                                                 | Role              | Data Flow        | Reason                                                                                                                                                                                                                                                                                                                                                                                                                           |
| ------------------------------------------------------------------------------ | ----------------- | ---------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Console/Commands/SendAccountsReceivableReminders.php`                     | service (command) | batch            | `app/Console/Commands/` does not exist in this repo — this is genuinely the first scheduled Artisan command (D-07 names this explicitly). `RecordDesignRevision`'s mail-failure-isolation shape is the only borrowable fragment; the chunked-query-over-a-status-filter loop and the scheduler registration itself have no in-repo precedent. Follow RESEARCH.md `## Architecture Patterns` → Pattern 1 and Pattern 1a directly. |
| `routes/console.php` scheduling block                                          | config/route      | batch            | Currently holds only the stock `inspire` example — no existing `Schedule::command(...)` call anywhere in the codebase to copy. Verified against Laravel 13 docs in RESEARCH.md rather than an in-repo analog.                                                                                                                                                                                                                    |
| Bracket summary `Card` row + `Tabs` + `Table` three-part composite on one page | component         | request-response | No page in this exact domain (Accounts Receivable) combines all three; the closest composite precedent is Phase 6's `resources/js/pages/production-staff/Dashboard.vue` (stat cards + Tabs + Table, per `06-PATTERNS.md`), not re-read in full here — read it directly before implementing `Index.vue` if `CreditRequests.vue` alone isn't enough.                                                                               |
| `Mail::fake()`/`Mail::assertSent()` test convention                            | test              | event-driven     | Per RESEARCH.md Pitfall 5, neither existing `DesignReviewRequested`-touching test file asserts on the mail facade. AR-02's command tests will be this codebase's first `Mail::fake()` usage — standard Pest/Laravel syntax, not a project-specific pattern to copy.                                                                                                                                                              |

## Metadata

**Analog search scope:** `app/Models/`, `app/Enums/`, `app/Http/Controllers/{Cashier,Owner,AccountingStaff}/`, `app/Http/Requests/{Owner,Cashier}/`, `app/Concerns/`, `app/Mail/`, `app/Policies/`, `resources/views/mail/`, `database/migrations/`, `database/factories/`, `database/seeders/`, `routes/`, `resources/js/pages/{owner,cashier,accounting-staff,frontline-staff}/`, `resources/js/config/nav/`, `tests/Feature/Owner/`
**Files scanned:** 24 read in full (all cited above with line numbers where relevant), plus 2 targeted greps (`payment_status` badge chains)
**Pattern extraction date:** 2026-09-08
