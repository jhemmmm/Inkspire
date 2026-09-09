# Phase 8: Expenses & Reporting - Research

**Researched:** 2026-09-10
**Domain:** Laravel 13/PHP 8.4 server-rendered PDF export (dompdf) + streamed Excel export (openspout) + cross-module read aggregation over an existing Inertia/Vue RBAC app
**Confidence:** HIGH (codebase facts, verified via direct file reads) / MEDIUM (new package APIs, verified via official docs but not yet run in this repo)

## Summary

Phase 8 has two genuinely separate halves. The **expenses half** (EXP-01) is a small, ordinary vertical slice: one new table, one new model observed by the existing `AuditObserver`, a Form Request + Concern pair, and Accounting-Staff-scoped CRUD — nothing here deviates from patterns already proven in Phases 1–7. The **reporting half** (RPT-01–05) is where the real risk lives: it introduces the app's first two Composer dependencies since scaffolding (`barryvdh/laravel-dompdf`, `openspout/openspout`), its first Blade views in an Inertia-only app, its first non-model-driven audit write, and — critically — a role-entitlement shape (Owner-yes/Admin-no) that the codebase's one existing blanket `role:owner,admin` route group cannot express as-is.

All the aggregation data already exists and is well-typed: `transactions` (Phase 5), `production_logs` (Phase 6), `accounts_receivable` (Phase 7), and the `AuditObserver`/`AuditLogger`/`SystemConfiguration` substrate (Phase 1). `JobOrder::outstandingBalance()` and `AccountsReceivable::agingBracket()` already centralize the derived-value logic reports need — reports should call these, not recompute them. The one column every date-ranged money report must filter on is `transactions.confirmed_at`, not `created_at`: it is the single column set to `now()` at the exact moment a transaction resolves to `Completed` for **every** payment method (Cash/Bank Transfer set it at creation in `PaymentController`; GCash/Maya set it later, in `ConfirmPaymentIntent`, shared by both the webhook and manual reconciliation paths). Filtering on `created_at` would silently misdate every GCash/Maya sale to its initiation time instead of its confirmation time.

**Primary recommendation:** Build one `ReportController` (or a thin family of role-scoped controllers sharing a `ReportRegistry`/`ReportBuilder` service) that resolves a role-scoped list of report definitions and a shared date-range Form Request; gate every read and every export **in the controller/service layer** with an explicit entitlement check (not middleware alone, since Owner-not-Admin cannot be expressed by the existing `role:owner,admin` group); render exports via `barryvdh/laravel-dompdf`'s `Pdf::loadView()->download()` for PDF and `openspout/openspout` v5's `Writer::openToFile('php://output')` wrapped in Laravel's `response()->streamDownload()` for `.xlsx` (not `openToBrowser()` — it bypasses Laravel's response lifecycle); write the D-02 audit entry via `AuditLogger`-style explicit `AuditLog::create()` immediately before streaming begins, never after.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| EXP-01 | Accounting Staff can record an expense with a category, amount, and date | New `expenses` table/model/factory; `expense_categories` already seeded in `system_configurations` (read via `SystemConfiguration::getArray()`); Form Request + Concern trait pattern from `RequestWriteOffRequest`/`AccountsReceivableValidationRules`; `#[ObservedBy(AuditObserver::class)]` for free audit coverage |
| RPT-01 | Owner can view financial/profit reports | Cash-basis revenue query over `transactions` (D-08/D-09/D-10); Owner-not-Admin entitlement gap identified below (no existing route group expresses this) |
| RPT-02 | Cashier can view Daily Sales & Cancellation reports | Same `transactions` query, ranged to a day (D-06); Cancellation report reads `transactions.type = cancellation_fee` plus `job_orders.cancelled_at` |
| RPT-03 | Production Staff can view a Production Status report | `production_logs` + `job_orders.status`; existing `ProductionBoardController::index()`'s urgency/status computation is the pattern to mirror, read-only |
| RPT-04 | Accounting Staff can view Daily/Monthly Sales, Daily/Monthly Expenses, and Summary of Sales & Expenses reports | Sales = same query as RPT-02 ranged differently (D-06); Expenses = sum over new `expenses` table excluding voided rows; Summary = D-08 revenue minus non-voided expenses, sharing the RPT-01 query per Claude's Discretion note |
| RPT-05 | Any role-scoped report can be exported to PDF or Excel | `barryvdh/laravel-dompdf` (PDF, Blade-rendered) + `openspout/openspout` v5 (streamed `.xlsx`); Inertia-does-not-do-file-downloads pattern (plain `<a href>` / `window.location`, not `<Link>`); D-02's audit write |

</phase_requirements>

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01:** Two new Composer dependencies approved: `barryvdh/laravel-dompdf` (server-rendered PDF) and `openspout/openspout` (streamed `.xlsx`). Rejected: CSV+print-dialog path, and openspout-only-with-print-only-PDF path. dompdf renders a Blade view — the only Blade in an otherwise Inertia-only app; keep isolated under `resources/views/reports/**`.
- **D-02:** Exporting a report writes one `audit_trail` entry (who, which report, date range, format); viewing on screen writes nothing. First deliberate non-model-driven audit write — `AuditObserver` cannot see it, needs an explicit write call using the existing append-only path only.
- **D-03:** Phase 7's collection letter gains a "Download PDF" action (new route + Blade view only — do not touch `CollectionLetterController::show` or `CollectionLetter.vue`). Reproduce the exact terminal-state guards (404 on non-Active status, 404 on Paid/WrittenOff collection_status) on the new PDF route.
- **D-04:** One shared Reports page backed by a single report registry, mounted into each entitled role's portal, filtered by that role's entitlement. **The entitlement check is the single security boundary of this phase and MUST be enforced server-side on every read and every export route, not by filtering the report list in Vue.** Every report must be tested for cross-role denial via direct URL, not just correct rendering.
- **D-05:** Owner sees every report (union of all report types); Admin sees none. Owner/Admin split exists precisely because Owner holds financial/approval powers and Admin holds user-management/config.
- **D-06:** One report per subject plus a date-range control — "Daily"/"Monthly" are ranges, not report types. RPT-04's literal wording is read as "the Sales report, ranged to a day/month" (documented deviation, same spirit as the `status`/`payment_status` split).
- **D-07:** The existing Artist Performance Report (`Artist/PerformanceReportController`, JOB-10) is NOT folded into the shared Reports page and is NOT modified. Stays in the Artist portal, on its own route, as a pattern reference only.
- **D-08:** Revenue is cash basis — sum of `transactions` at `TransactionStatus::Completed` within range. An `Active` on-credit balance contributes nothing until paid; a `PendingConfirmation` GCash/Maya payment contributes nothing until confirmed.
- **D-09:** Write-offs approved in range are disclosed as their own labeled figure but NOT subtracted from profit (cash basis never counted them as revenue; subtracting double-books the loss). Needs unambiguous labeling, e.g. "Bad debt written off (not deducted — never collected)".
- **D-10:** Revenue splits into two labeled lines summing to the total: job sales (`down_payment` + `balance_payment` + `full_payment`) and cancellation fees (`cancellation_fee`) — free via `transactions.type`. No negative/refund transactions exist anywhere; summing is always safe.
- **D-11:** An expense is editable and voidable, with every change audited via `#[ObservedBy(AuditObserver::class)]`. A voided expense stops counting toward reports but the row survives. Rejected: structural append-only with compensating negative entries (PROJECT.md reserves structural append-only specifically for `audit_trail`); void-only with no edit.
- **D-12:** Accounting Staff is the only role that can create/edit/void an expense. Owner can read the itemized list and the profit report's expense figures but cannot record one.
- **D-13:** An expense carries: category, amount, expense date, optional free-text description, `recorded_by`, timestamps. No file attachment.
- **D-14 (locked upstream):** Categories come from the existing `expense_categories` key in `system_configurations` (`business_rules` group, `type: array`, seeded `['Utilities', 'Supplies', 'Rent']`). Do NOT create an `expense_categories` table, hardcode an enum, or add category-management UI.

### Claude's Discretion

- Report registry mechanism (config array, enum, invokable classes, or a service with a `reports()` map) — container is free, D-04's one-page-one-pipeline shape and server-side entitlement check are not.
- Whether RPT-04's "Summary of Sales & Expenses" and RPT-01's financial/profit report are one registry entry visible to both roles, or two. Decide once, do not build a third variant.
- Exact table/column/enum naming for `expenses` (`expense_date` vs `incurred_on`, `voided_at` vs a status enum, `description` vs `note`) — follow the string-backed TitleCase-key enum convention and additive-migration style.
- Whether voided is a nullable `voided_at` + `void_reason` pair or a status enum — D-11 locks the behavior, shape is discretionary. **Every report query must exclude voided rows** — most likely place to forget this filter.
- What happens to historical expenses when a category is removed from config — existing rows keep their stored category string and keep appearing in reports; only the entry form's dropdown narrows.
- PDF letterhead/branding — presentation call for `/gsd-ui-phase 8`.
- Export filename convention and whether generation is synchronous or queued — synchronous strongly preferred, consistent with Phases 3–6.
- Excel export content shape — raw rows matching on-screen table (simplest, matches how openspout streams) vs formatted summary sheet. Prefer raw rows unless UI phase says otherwise.
- Reports page layout — cards + preview panel + preset range buttons vs plain list/table. `UI hint: yes`, `/gsd-ui-phase 8` settles it.
- Default date range on first load; whether on-screen preview paginates/caps rows while export carries the full set.

### Deferred Ideas (OUT OF SCOPE)

- Emailing the collection letter to the debtor.
- A PDF download for the digital receipt (POS-06).
- Report scheduling / emailed periodic reports.
- Receipt/document attachments on expense records.
- Reports the demo has but no requirement covers (`quality`/Quality Check, `fileval`/File Validation).
- AR aging summary on the Owner's dashboard (Phase 7 D-16 pointed this at RPT-01; this phase gives the financial/profit report, not a dashboard widget).
- Charts and visualizations — every report here is a table plus totals; no charting library is installed.

</user_constraints>

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Expense create/edit/void | API/Backend (Controller + Form Request) | Database (new `expenses` table) | Ordinary mutating vertical slice, no client-side business logic |
| Category dropdown source | API/Backend (`SystemConfiguration::getArray`) | Frontend (Vue select options passed as Inertia prop) | Config is already Owner/Admin-editable server-side data; frontend only renders it |
| Report data aggregation (Sales/Cancellation/Production/Expenses/Financial) | API/Backend (query/service layer) | Database (existing `transactions`, `production_logs`, `accounts_receivable`, new `expenses`) | All source-of-truth data and derived-value logic (`outstandingBalance()`, `agingBracket()`) already lives server-side; reports must reuse it, not recompute in Vue |
| Report entitlement (which role sees which report) | API/Backend (Gate/Policy or registry service, evaluated per-request) | — | D-04 explicitly forbids client-side-only filtering; must 403 on direct URL |
| Date-range filter UI | Frontend (Vue date-range control) | API/Backend (Form Request validates `from`/`to`) | Same shape as `PerformanceReportFilterRequest` — UI picks the range, backend validates and applies it |
| Report preview table | Frontend (Vue component, Inertia props) | — | Read-only render of controller-computed `stats`/`rows`, matching `PerformanceReportController`'s existing shape |
| PDF export rendering | API/Backend (dompdf renders a Blade view) | — | New territory: the *only* Blade in an Inertia-only app; explicitly isolated per D-01 |
| Excel export streaming | API/Backend (openspout writes to `php://output` via `response()->streamDownload()`) | — | Must stay inside Laravel's response lifecycle so the D-02 audit write and terminating middleware still run |
| Export audit logging | API/Backend (explicit `AuditLog::create()` call, not the observer) | Database (`audit_trail`, append-only) | D-02: an export mutates no model, so `AuditObserver` never fires; this is a deliberate, hand-written write using the existing append-only path |

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `barryvdh/laravel-dompdf` | ^3.1 (latest 3.1.2, 2026-02-21) [CITED: packagist.org/barryvdh/laravel-dompdf, github.com/barryvdh/laravel-dompdf] | Server-rendered PDF from a Blade view, no headless browser | User-approved in CONTEXT.md D-01; pure-PHP rendering works unmodified on Laravel Cloud (no Chromium/Puppeteer dependency to provision); requires `dompdf/dompdf` ^3.0 and PHP ^8.1 — both satisfied |
| `openspout/openspout` | **^5.0** (latest stable 5.11.3, released 2026-09-02) [CITED: packagist.org/openspout/openspout, github.com/openspout/openspout docs] | Streamed `.xlsx` writing with low memory footprint | User-approved in CONTEXT.md D-01 specifically for its streaming/low-memory model vs `maatwebsite/excel`/PhpSpreadsheet |

### Version verification

```bash
composer show --direct 2>/dev/null | grep -iE "dompdf|openspout"
# (no output) — confirmed NEITHER package is installed yet; both are genuinely new for this phase.
```

**IMPORTANT version correction to CONTEXT.md's canonical refs:** CONTEXT.md's library-documentation note says "openspout v4's API differs materially from v3" and implies v4 is current. **That is stale.** Packagist's live listing shows **openspout v5.11.3 (2026-09-02) is now the latest stable release**, and it requires `php: ~8.4.0 || ~8.5.0` — a narrower constraint than this project's own `composer.json` `"php": "^8.3"`. v5 changed several APIs from v4 (e.g. option objects are now readonly, constructed via named-argument `Options`/`Properties` classes passed into the `Writer` constructor, rather than static factories). **Do not blindly install "latest" without re-confirming this at implementation time** — pin `"openspout/openspout": "^5.0"` explicitly and read `composer show openspout/openspout` immediately after installing to confirm what actually landed, since a security patch could bump the major again between this research and execution.

**Composer PHP-constraint interaction to flag for the planner:** this project's `composer.json` declares `"php": "^8.3"` (i.e., allows 8.3.x–8.x). openspout v5 declares `"php": "~8.4.0 || ~8.5.0"` (8.4.x or 8.5.x only — excludes 8.3.x). The local dev/CLI PHP is 8.4.3, so `composer require` will succeed here, but if this app is ever run in an environment still on PHP 8.3.x, `composer require openspout/openspout:^5.0` will fail dependency resolution. Not a blocker (the project's real PHP is 8.4.3), but worth a one-line note in the plan's risk section.

**Installation (run during execution, not now — CLAUDE.md requires user approval for new dependencies, which CONTEXT.md D-01 already grants for exactly these two):**

```bash
composer require barryvdh/laravel-dompdf:^3.1 openspout/openspout:^5.0
```

## Package Legitimacy Audit

| Package | Registry | Age | Downloads | Source Repo | slopcheck | Disposition |
|---------|----------|-----|-----------|-------------|-----------|-------------|
| `barryvdh/laravel-dompdf` | packagist | 10+ years (long-established Laravel ecosystem package) | Very high (standard Laravel PDF package) | github.com/barryvdh/laravel-dompdf | OK | Approved |
| `openspout/openspout` | packagist | Active community fork of `box/spout`, years of releases through v5.x | High (widely used for large spreadsheet export) | github.com/openspout/openspout | OK | Approved |

```
slopcheck checking barryvdh/laravel-dompdf on packagist...  -> status: OK, flags: []
slopcheck checking openspout/openspout on packagist...       -> status: OK, flags: []
```

**Packages removed due to slopcheck [SLOP] verdict:** none.
**Packages flagged as suspicious [SUS]:** none.

Both package names originate from CONTEXT.md's D-01 (an already-user-approved locked decision, not a name this research session discovered independently), and both were additionally cross-checked against their own official GitHub docs (dompdf's README API surface, openspout's `docs/index.md` getting-started guide) — both pass at `[CITED]` confidence, and slopcheck independently returned `OK` for both. No `[ASSUMED]` package-name risk here; the only genuine uncertainty is the **version number** (openspout v4 vs v5 — see State of the Art below), which is why the exact installed version must be re-verified with `composer show` immediately after `composer require` during execution, not assumed from this document.

## Architecture Patterns

### System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│  Browser (Vue/Inertia)                                               │
│                                                                       │
│  Reports.vue (per-role, one shared component)                        │
│   ├─ date-range control ──────────────► GET  {role}/reports?from=&to=│
│   ├─ report list (from Inertia props, already role-filtered by       │
│   │  the backend — Vue never decides entitlement)                    │
│   ├─ preview table (rows from Inertia props)                         │
│   └─ Export PDF / Export Excel  ──► plain <a href> (NOT <Link>) ─────┼──┐
│                                                                       │  │
│  ExpenseForm.vue (accounting-staff only)                              │  │
│   └─ create/edit/void ─────────────► POST/PATCH via Inertia <Form>   │  │
└─────────────────────────────────────────────────────────────────────┘  │
                                                                           │
        Plain browser navigation (download), NOT an XHR/Inertia visit ───┘
                                                                           │
┌──────────────────────────────────────────────────────────────────────┐ │
│  Laravel (routes/{role}.php, each wrapped in that role's existing    │ │
│  ['auth','role:{role}'] group EXCEPT Owner's Reports route, which    │ │
│  needs its OWN ['auth','role:owner'] group — see Pitfall 5)          │ │
│                                                                        │◄┘
│  ReportController::index()          ReportController::export()       │
│   ├─ FilterReportRequest validates  ├─ FilterReportRequest validates │
│   │  from/to                        │  from/to + format(pdf|xlsx)    │
│   ├─ entitlement check (Gate or     ├─ entitlement check (same gate) │
│   │  registry lookup) — 403 if      ├─ builds same query as index()  │
│   │  role not entitled to this      ├─ AuditLog::create() — D-02     │
│   │  report key, even via direct    ├─ dompdf: Pdf::loadView(...)    │
│   │  URL                            │    ->download() [Blade, isolated│
│   ├─ runs query against             │    under resources/views/reports]│
│   │  transactions / production_logs ├─ openspout: response()->       │
│   │  / accounts_receivable /        │    streamDownload(fn () use    │
│   │  expenses                       │    ($writer) { ... })          │
│   └─ Inertia::render('.../Reports', └─ returns BinaryFileResponse /  │
│      ['reports'=>[...],'rows'=>...])   StreamedResponse (NOT Inertia) │
└────────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
        transactions · production_logs · accounts_receivable · expenses
        (all existing/new tables — read-only for this phase except `expenses`)
```

### Recommended Project Structure

```
app/
├── Models/
│   └── Expense.php                          # new — mirrors Transaction.php's shape
├── Enums/
│   └── ExpenseStatus.php  (if status-enum chosen over voided_at pair)
├── Http/
│   ├── Controllers/
│   │   ├── AccountingStaff/
│   │   │   └── ExpenseController.php         # create/edit/void — role:accounting_staff group
│   │   └── Reports/                          # NEW namespace — the shared registry lives here,
│   │       ├── ReportController.php          # reached from multiple role route groups
│   │       └── ReportExportController.php    # separate export action (or a 2nd method on ReportController)
│   └── Requests/
│       ├── AccountingStaff/
│       │   ├── StoreExpenseRequest.php
│       │   ├── UpdateExpenseRequest.php
│       │   └── VoidExpenseRequest.php
│       └── Reports/
│           └── FilterReportRequest.php       # from/to (+ optional report key), mirrors PerformanceReportFilterRequest
├── Concerns/
│   └── ExpenseValidationRules.php            # category/amount/date rules, mirrors AccountsReceivableValidationRules
├── Services/  (or App\Reports\)
│   └── ReportRegistry.php                    # Claude's Discretion — the report-key -> query/entitlement map
├── Policies/
│   └── ReportPolicy.php   (if Gate-per-model doesn't fit — see Pitfall 5; may instead be Gate::define closures)
resources/
├── views/
│   └── reports/                              # NEW — the only Blade in this app, per D-01
│       ├── layout.blade.php                  # shared letterhead/branding shell
│       ├── financial.blade.php
│       ├── sales.blade.php
│       ├── expenses.blade.php
│       ├── production-status.blade.php
│       └── collection-letter.blade.php       # D-03's new consumer
└── js/pages/
    ├── owner/Reports.vue                     # Owner's entry — union of all report types (D-05)
    ├── cashier/Reports.vue                   # or a single shared component rendered per role
    ├── production-staff/Reports.vue
    ├── accounting-staff/Reports.vue
    └── accounting-staff/Expenses/
        ├── Index.vue
        ├── Create.vue  (or a dialog on Index.vue, matching CreditRequests.vue's shape)
        └── Edit.vue
```

### Pattern 1: Date-range Form Request (already established)

**What:** A `FormRequest` with nullable `from`/`to` date rules; the controller reads `$request->date('from')`/`$request->date('to')` and applies them to a query.
**When to use:** Every report's read AND export action.
**Example:**
```php
// Source: app/Http/Requests/Artist/PerformanceReportFilterRequest.php (existing, verbatim pattern)
class FilterReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }
}
```

### Pattern 2: Cash-basis revenue query (D-08/D-09/D-10)

**What:** Sum `transactions.amount` where `status = Completed`, filtered by `confirmed_at` (not `created_at`), split by `type`.
**When to use:** RPT-01 (financial/profit), RPT-02 (sales/cancellation), RPT-04 (sales/expenses summary).
**Example:**
```php
// Source: app/Models/JobOrder.php::outstandingBalance() (existing pattern to mirror,
// not copy verbatim — that method is per-job-order, this is a date-ranged aggregate)
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;

$jobSales = Transaction::query()
    ->where('status', TransactionStatus::Completed->value)
    ->whereIn('type', [
        TransactionType::DownPayment->value,
        TransactionType::BalancePayment->value,
        TransactionType::FullPayment->value,
    ])
    ->when($from, fn ($q) => $q->where('confirmed_at', '>=', $from))
    ->when($to, fn ($q) => $q->where('confirmed_at', '<=', $to->copy()->endOfDay()))
    ->sum('amount');

$cancellationFees = Transaction::query()
    ->where('status', TransactionStatus::Completed->value)
    ->where('type', TransactionType::CancellationFee->value)
    ->when($from, fn ($q) => $q->where('confirmed_at', '>=', $from))
    ->when($to, fn ($q) => $q->where('confirmed_at', '<=', $to->copy()->endOfDay()))
    ->sum('amount');
```
**Why `confirmed_at` not `created_at`:** verified directly in `app/Actions/POS/ConfirmPaymentIntent.php` and `app/Http/Controllers/Cashier/PaymentController.php` — `confirmed_at` is the one column set to `now()` at the exact moment ANY transaction (cash, bank transfer, GCash, or Maya) resolves to `Completed`. `created_at` would misdate every GCash/Maya sale to its *initiation* time.

### Pattern 3: Write-off disclosure line, not a subtraction (D-09)

**What:** Query `accounts_receivable` where `write_off_requested_at` (or the terminal write-off approval timestamp — confirm exact "approved in range" column with the Owner's `WriteOffApprovalController::approve()` — likely needs its own `written_off_at` stamp; `approved_at` is reused for credit approval, not write-off approval, per the migration's column list) falls in range, `collection_status = WrittenOff`, summed and displayed as its own labeled figure. **Do not subtract this from the revenue total.**

### Pattern 4: Report registry entitlement (the pattern that does NOT already exist — new territory)

**What:** A per-report-key map of `role => allowed`, checked server-side on both `index()` and `export()`.
**When to use:** Every report route.
**Precedent to model on:** `app/Policies/AccountsReceivablePolicy.php` and `app/Policies/DesignFilePolicy.php` — both narrow an action to `Owner` specifically (`$actor->role === UserRole::Owner`) *underneath* a blanket `role:owner,admin` middleware group, exactly the shape D-05 needs. Example:
```php
// Source: app/Policies/AccountsReceivablePolicy.php (existing, verbatim except for renaming)
class AccountsReceivablePolicy
{
    public function approve(User $actor, AccountsReceivable $accountsReceivable): bool
    {
        return $actor->role === UserRole::Owner;
    }
}
```
Because a "report" is not an Eloquent model, Laravel's model-based Policy auto-discovery does not apply cleanly — the equivalent mechanism here is either (a) `Gate::define('view-report', fn (User $user, string $reportKey) => ...)` registered in `AppServiceProvider::boot()` (note: **no `Gate::` calls exist anywhere in this codebase yet** — this phase would be the first), or (b) a plain PHP method on the registry service, e.g. `ReportRegistry::isEntitled(User $user, string $key): bool`, called explicitly in the controller before running the query — simpler, and consistent with `EnsureUserHasRole`'s own plain-array-check style. Either is fine; a full custom `ReportPolicy` class bound to no model is unidiomatic Laravel and not necessary here.

### Pattern 5: Expense void (D-11) mirrors write-off request, not append-only

**What:** `forceFill()` + `save()` on the existing row (not a new negative-amount row), with a mandatory reason, audited automatically by `AuditObserver`'s `updated()` hook (which already diffs `getChanges()` — no extra work needed to capture the before/after of a void).
**Example:**
```php
// Source: app/Http/Controllers/AccountingStaff/WriteOffRequestController.php (existing pattern, adapted)
public function void(VoidExpenseRequest $request, Expense $expense): RedirectResponse
{
    abort_if($expense->voided_at !== null, 422, __('This expense is already voided.'));

    $expense->forceFill([
        'voided_at' => now(),
        'void_reason' => $request->validated('reason'),
    ])->save(); // AuditObserver::updated() fires automatically — no extra audit call needed
    // ...
}
```

### Anti-Patterns to Avoid

- **Filtering the report list in Vue:** D-04 is explicit — the entitlement check MUST be server-side. A Cashier who edits localStorage or hits the URL directly must get a 403, not a client-side-hidden report.
- **Reusing `created_at` for revenue date ranges:** silently wrong for every PayMongo transaction (see Pattern 2).
- **Recomputing `outstandingBalance()`'s logic inline in a report query:** the method already exists on `JobOrder`; call it (or its underlying `transactions`-sum expression) rather than re-deriving balance logic a third time.
- **Putting the Owner Reports route inside `routes/owner.php`'s existing `role:owner,admin` group without a secondary Owner-only check:** an Admin would get a 200, not a 403 — direct violation of D-05 and RBAC-02's "blocked even via direct URL" standard already established in Phase 1.
- **Calling `openToBrowser()` from inside a Laravel controller:** it writes headers and exits the script directly, bypassing Laravel's response object entirely — this breaks the D-02 audit-write-before-download sequencing guarantee and is untestable with normal Pest HTTP assertions. Use `openToFile('php://output')` inside `response()->streamDownload()` instead.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| PDF rendering | A custom HTML-to-PDF pipeline, or shelling out to a headless browser | `barryvdh/laravel-dompdf`'s `Pdf::loadView()->download()` | Pure-PHP, no headless-browser provisioning needed on Laravel Cloud; already user-approved (D-01) |
| Streamed `.xlsx` writing | Hand-rolled OOXML/ZIP writing, or loading a full spreadsheet into memory with a `PhpSpreadsheet`-style in-memory model | `openspout/openspout`'s `Writer` (row-at-a-time streaming) | Explicitly chosen over `maatwebsite/excel`/PhpSpreadsheet in D-01 for memory reasons on a managed host |
| Outstanding balance / aging bracket calculation | A second copy of the balance/aging math inside a report query | `JobOrder::outstandingBalance()`, `AccountsReceivable::agingBracket()`/`daysPastDue()` (existing, already the "single source of truth" per D-16 from Phase 7) | Phase 7 explicitly replaced 8 duplicated copies of this logic; a 9th copy in a report query recreates the exact bug class Phase 7 closed |
| Category management UI/table | A new `expense_categories` table or hardcoded enum | Existing `system_configurations` row (`key = 'expense_categories'`), read via `SystemConfiguration::getArray('expense_categories', [...])` | D-14 — already built, seeded, and Owner/Admin-editable since Phase 1 |
| Audit trail write path | A second `update()`/`delete()`-capable table or a bypass of `AuditLogger` | `AuditLog::create()` directly (D-02's export entry) or `#[ObservedBy(AuditObserver::class)]` (D-11's expense mutations) | AUDIT-02 is structural, not permission-based — no new write surface may exist, only the two established append-only paths |

**Key insight:** almost nothing in this phase's data layer is new work — the two genuinely new pieces are (1) the export file-generation mechanics themselves, and (2) the entitlement mechanism for a non-model "report" concept, because every other piece of infrastructure this phase needs (audit, config, derived-balance helpers, Form Request/Concern pairing) was already built by Phases 1, 5, 6, and 7 specifically to be reused here.

## Common Pitfalls

### Pitfall 1: Filtering revenue by `created_at` instead of `confirmed_at`

**What goes wrong:** A GCash/Maya sale confirmed on Sept 2 but initiated (created) on Sept 1 lands in the wrong day's Daily Sales report.
**Why it happens:** `created_at` is the more obvious/default column to reach for; `confirmed_at` is a deliberately Phase-5-added column most people won't think to check.
**How to avoid:** Always filter cash-basis reports (`Transaction::where('confirmed_at', ...)`) — verified this is set uniformly for both direct (Cash/Bank Transfer, in `PaymentController`) and asynchronous (GCash/Maya, in `ConfirmPaymentIntent`, shared by the webhook and manual-reconciliation paths) confirmation flows.
**Warning signs:** A test asserting a report's total against a GCash transaction whose `created_at` and `confirmed_at` are deliberately set to different days should be part of Wave 0.

### Pitfall 2: Owner-not-Admin entitlement silently degrading to Owner-and-Admin

**What goes wrong:** Reports route placed inside `routes/owner.php`'s existing `['auth', 'role:owner,admin']` group with no further check — an Admin account gets a 200 on the Reports page, violating D-05 and the project's own RBAC-02 "blocked even via direct URL" standard.
**Why it happens:** It's the path of least resistance — every other Owner route already lives in that one group, and adding a route there "just works" without an error, so nothing forces the extra check to be written.
**How to avoid:** Either give the Owner's Reports route its own `['auth', 'role:owner']` middleware group (simplest, and the group at the route-file level is itself the enforcement — no extra Gate needed), or if it must stay inside the shared group for portal-navigation reasons, add an explicit `abort_unless($request->user()->role === UserRole::Owner, 403)` / Gate check mirroring `AccountsReceivablePolicy::approve()`'s exact narrowing pattern.
**Warning signs:** No test exists asserting `actingAs($admin)->get(route('owner.reports.index'))->assertForbidden()`. Per D-04, this exact test is mandatory, not optional.

### Pitfall 3: `openToBrowser()` bypassing the D-02 audit write and Laravel's response cycle

**What goes wrong:** Calling `$writer->openToBrowser($filename)` sends headers and can `exit`/terminate output directly from inside the writer, which happens *outside* the normal controller-return-a-Response flow. If the D-02 `AuditLog::create()` call is placed after this in the same method, it may never run (or runs in an inconsistent request lifecycle state), silently breaking the export-audit guarantee.
**Why it happens:** `openToBrowser()` is openspout's advertised "convenience" method for exactly this use case, so it looks like the obvious choice for a Laravel export action.
**How to avoid:** Write the audit entry *first*, then use `openToFile('php://output')` wrapped inside Laravel's own `response()->streamDownload(fn () => ..., $filename)` — this keeps Laravel's own response object (and its headers/terminating middleware) in control, and guarantees the audit write happens before any byte of the file streams.
**Warning signs:** A Pest test for the export route that can't reliably assert `audit_trail` got a new row, or a test that must resort to inspecting raw output buffers because the response object never got a body.

### Pitfall 4: openspout v4 vs v5 API assumptions

**What goes wrong:** CONTEXT.md's canonical-refs note ("v4's API differs materially from v3") primes an implementer to reach for v4-era code (e.g. `WriterEntityFactory::createXLSXWriter()`), which does not exist in the now-current v5 (`new OpenSpout\Writer\XLSX\Writer(new Options(...))` constructor style with readonly option objects).
**Why it happens:** Training data and even recent web search snippets skew toward v4 examples; v5.11.3 was released 2026-09-02, days before this research.
**How to avoid:** Pin `^5.0` explicitly in the `composer require` call, then read the *installed* package's own `docs/` (or run `composer show openspout/openspout` to confirm the resolved version) before writing any Writer code — do not copy v4-style factory calls from memory or old blog posts.
**Warning signs:** A `Class "OpenSpout\Common\Entity\Style\Style"` — style class namespace changed too — or "Call to undefined method" error referencing `WriterEntityFactory`.

### Pitfall 5: Voided expenses leaking into report sums

**What goes wrong:** A report query sums `expenses.amount` without excluding voided rows, so a corrected/voided expense still depresses profit twice (once via the original entry, again via a corrected duplicate, or the voided one just never stops counting).
**Why it happens:** Every *new* report query needs this filter added independently — there's no single shared "active expenses" scope yet, and it's easy to write one query with the filter and forget it in a sibling query (Financial report vs Expenses report vs Summary report all separately sum `expenses`).
**How to avoid:** Put the filter on an Eloquent local scope (`Expense::query()->active()` / `->whereNull('voided_at')`) on the model itself, so every call site gets it by construction rather than by each author remembering it.
**Warning signs:** A voided-expense factory state exists in tests but no test asserts it's excluded from every one of the three expense-touching reports (Expenses, Financial/Profit, Summary).

### Pitfall 6: SQLite `whereDate()` vs raw date-cast serialization (established project-wide trap)

**What goes wrong:** Filtering by a plain `where('confirmed_at', $date)` instead of `whereDate('confirmed_at', $date)` silently never matches on SQLite, because the cast reformats stored datetimes with a time component that a bare date string can't equal.
**Why it happens:** This is a documented, recurring trap in this exact codebase — see `QueueEntry::nextForBusinessDay()` (Phase 2) and `QueueDisplayController` (Phase 2) both needing the same fix.
**How to avoid:** Use `whereDate()` (or explicit `>=`/`<=` range bounds as shown in Pattern 2, which sidesteps the issue entirely by comparing full timestamps) — never a bare equality on a date-cast datetime column.
**Warning signs:** A report shows 0 rows for "Today" in local dev (SQLite) that would show correctly in MySQL production — this exact production/dev divergence is called out by the project as a known SQLite quirk.

## Code Examples

### PDF export controller action (dompdf)

```php
// Source: https://github.com/barryvdh/laravel-dompdf (v3 facade API, confirmed against
// official README — loadView/download/stream/output method signatures)
use Barryvdh\DomPDF\Facade\Pdf;

public function exportPdf(FilterReportRequest $request, string $reportKey): \Symfony\Component\HttpFoundation\Response
{
    $this->authorizeReport($request->user(), $reportKey); // 403 if not entitled — D-04

    $data = $this->buildReportData($reportKey, $request->date('from'), $request->date('to'));

    AuditLog::create([ // D-02 — written BEFORE the PDF stream begins
        'user_id' => $request->user()->id,
        'action' => 'report_exported',
        'auditable_type' => null,
        'auditable_id' => null,
        'new_values' => ['report' => $reportKey, 'format' => 'pdf', 'from' => $request->input('from'), 'to' => $request->input('to')],
        'ip_address' => $request->ip(),
        'created_at' => now(),
    ]);

    return Pdf::loadView("reports.{$reportKey}", $data)
        ->download("{$reportKey}-" . now()->format('Y-m-d') . '.pdf');
}
```

### Excel export controller action (openspout v5)

```php
// Source: https://raw.githubusercontent.com/openspout/openspout/5.x/docs/index.md
// (fetched directly from the openspout repository's current 5.x branch — the
// getting-started guide's exact "Writer" usage section)
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

public function exportXlsx(FilterReportRequest $request, string $reportKey): \Symfony\Component\HttpFoundation\StreamedResponse
{
    $this->authorizeReport($request->user(), $reportKey);

    $rows = $this->buildReportRows($reportKey, $request->date('from'), $request->date('to'));

    AuditLog::create([/* same D-02 entry as above, format => 'xlsx' */]);

    $filename = "{$reportKey}-" . now()->format('Y-m-d') . '.xlsx';

    return response()->streamDownload(function () use ($rows) {
        $writer = new Writer();
        $writer->openToFile('php://output'); // NOT openToBrowser() — see Pitfall 3
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
    }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
}
```

### Frontend export trigger (plain link, not Inertia visit)

```html
<!-- Source: verified pattern from Laravel/Inertia community consensus (laracasts.com
     discussion "Force file downloads in Laravel + Inertia + Vue?" and
     github.com/inertiajs/inertia-laravel issue #255) — a download response is not
     XHR-compatible with Inertia's visit mechanism; a plain <a> or window.location
     bypasses Inertia entirely and lets the browser handle Content-Disposition. -->
<a :href="reportExportUrl('pdf')" target="_blank" rel="noopener">Export PDF</a>
<a :href="reportExportUrl('xlsx')" target="_blank" rel="noopener">Export Excel</a>
```
Wayfinder's generated `.url()` helper (e.g. `ReportController.exportPdf.url({ query: { from, to } })`) is exactly right here — it only produces the URL string, it does not perform a fetch — so it stays consistent with the project's "no hardcoded URLs" convention without fighting Inertia's visit interception. Do NOT use `<Link>` or `router.visit()` for this button; both go through Inertia's XHR machinery, which cannot receive a binary/`Content-Disposition: attachment` response correctly.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|---------------|--------|
| `openspout` v4 static-factory writer construction (`WriterEntityFactory::createXLSXWriter()`) — what CONTEXT.md's canonical refs assume is "current" | `openspout` v5 constructor-based, readonly `Options`/`Properties` value objects passed to `new Writer(new Options(...))` | v5.0 (exact date not confirmed in this session; v5.11.3 confirmed live 2026-09-02) | Any implementer copying v4-era snippets from memory or older blog posts will hit `undefined method`/`class not found` errors; re-verify against the installed version's actual `docs/` at execution time |
| `box/spout` (openspout's predecessor) | `openspout/openspout` (community fork) | Predates this project | Already correctly identified as dead by CONTEXT.md — no action needed, just confirming the fork lineage |

**Deprecated/outdated:** `box/spout` is unmaintained; `openspout` is its actively maintained successor and the one already locked in by D-01.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | openspout v5.11.3 is genuinely the latest stable tag and PHP `~8.4.0\|\|~8.5.0` is its real constraint | Standard Stack | If Packagist's live page changed again since this session (unlikely within days but possible), the planner should re-run `composer show openspout/openspout` post-install rather than trust this document as final |
| A2 | The exact "write-off approved in range" timestamp column for D-09's disclosure line is `written_off_at`-equivalent, not the existing `approved_at` (which the migration comment ties to credit approval, not write-off approval) | Architecture Patterns, Pattern 3 | If wrong, the write-off disclosure line could double-count credit-approval events as write-off events, or miss write-offs entirely; the planner MUST re-read `Owner/WriteOffApprovalController::approve()` and the `accounts_receivable` migration's exact column semantics before writing this query — this research did not fully trace that controller's mutation, only the request-side (`WriteOffRequestController`) |
| A3 | A plain PHP `ReportRegistry::isEntitled()` check (not a Laravel `Gate::define()`) is the right mechanism, given no `Gate::` calls exist anywhere in this codebase yet | Architecture Patterns, Pattern 4 | Low risk either way — both are valid Laravel patterns; this is genuinely "Claude's Discretion" per CONTEXT.md, flagged only so the planner doesn't invent a third, heavier pattern (e.g. a full custom middleware) |

**If this table is empty:** N/A — three items above need planner attention before implementation, not user reconfirmation (none of these are business-rule assumptions the user needs to approve; they're implementation-detail verifications).

## Open Questions (RESOLVED)

1. **Which exact timestamp marks a write-off as "approved in range" for D-09's disclosure line?**
   - What we know: `accounts_receivable` has `approved_at` (tied to credit approval per the original migration) and `write_off_requested_at` (tied to the *request*, not the Owner's approval). The migration list from `2026_09_08_090000_add_aging_and_collection_columns_to_accounts_receivable_table.php` does not show a distinct `written_off_at` column.
   - What's unclear: whether `Owner/WriteOffApprovalController::approve()` reuses `approved_at` (overwriting the credit-approval timestamp) or writes into `collection_status = WrittenOff` with no dedicated timestamp at all, in which case "written off in range" would need to be derived from `audit_trail` instead.
   - Recommendation: the planner should read `app/Http/Controllers/Owner/WriteOffApprovalController.php::approve()` directly (not researched in depth this session — out of this phase's dependency chain, sits in Phase 7) before finalizing the D-09 query.
   - **(RESOLVED)** `08-03-PLAN.md` Task 1 adds a `written_off_at` timestamp column to `accounts_receivable` and extends `WriteOffApprovalController::approve()`'s existing `forceFill()` call to set it — this is the exact timestamp D-09's disclosure line filters on.

2. **Is the Reports page one Vue component reused across four role directories, or four thin per-role Vue components sharing one composable?**
   - What we know: D-04 locks "one shared Reports page" at the *pipeline* level (one registry, one date control, one export mechanism); it does not explicitly say one `.vue` file.
   - What's unclear: whether Inertia's page-resolution convention (`resources/js/pages/{role}/Reports.vue`, one file per role folder per this project's established layout-by-namespace pattern) forces four separate files that each import a shared composable, or whether a single file lives in one place and multiple routes render it.
   - Recommendation: given the project's `app.ts` layout-resolution switches on page *path* (not a shared cross-namespace import), four thin per-role `.vue` files delegating to a shared composable/component is more consistent with existing conventions than one file referenced from four route namespaces — but this is a `/gsd-ui-phase 8` call, not a backend research call.
   - **(RESOLVED)** `08-UI-SPEC.md` §1 settles this explicitly: four thin per-role `Reports.vue` wrapper pages, each delegating to one shared `resources/js/components/reports/ReportsWorkspace.vue` component — matching this research's own recommendation, implemented in `08-05-PLAN.md`.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Everything | ✓ | 8.4.3 | — |
| Composer | Package install | ✓ | (not directly checked, assumed present — `composer show` ran successfully) | — |
| `barryvdh/laravel-dompdf` | RPT-05 PDF export, D-03 | ✗ (not yet installed) | — | None needed — user-approved install (D-01) |
| `openspout/openspout` | RPT-05 Excel export | ✗ (not yet installed) | — | None needed — user-approved install (D-01) |
| SQLite (dev) / MySQL (prod) | Report queries, `expenses` table | ✓ (SQLite in dev per `.env` `DB_CONNECTION`) | — | Report date-range queries must avoid SQLite `whereDate()` pitfalls (Pitfall 6) so behavior matches MySQL in prod |
| Queue worker / headless browser | Not needed | N/A | — | Both chosen libraries are synchronous, pure-PHP — explicitly why D-01 rejected any headless-browser PDF renderer for Laravel Cloud compatibility |

**Missing dependencies with no fallback:** none — both new dependencies are a planned `composer require` this phase performs itself, not an external prerequisite to provision.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest 5.1.3 (`pestphp/pest`) + `pestphp/pest-plugin-laravel` 5.0.1 |
| Config file | `phpunit.xml` / `tests/Pest.php` |
| Quick run command | `php artisan test --compact --filter=Expense` (or `--filter=Report`) |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| EXP-01 | Accounting Staff creates an expense with category/amount/date; row persists | Feature (HTTP) | `php artisan test --compact --filter="expense can be recorded"` | ❌ Wave 0 |
| EXP-01 (D-11) | Accounting Staff edits an expense; `AuditObserver` writes an `updated` audit_trail row with old/new values | Feature (HTTP + DB assertion) | `php artisan test --compact --filter="editing an expense is audited"` | ❌ Wave 0 |
| EXP-01 (D-11) | Voiding an expense sets `voided_at`/reason, row survives, is excluded from every report sum | Feature (HTTP + DB assertion) | `php artisan test --compact --filter="voided expense excluded"` | ❌ Wave 0 |
| EXP-01 (D-12) | Owner cannot create/edit/void an expense (403 or route not reachable); Owner CAN read the itemized list | Feature (HTTP, cross-role denial) | `php artisan test --compact --filter="owner cannot record an expense"` | ❌ Wave 0 |
| EXP-01 (D-14) | Category dropdown/options reflect `system_configurations.expense_categories`; removing a category from config doesn't hide historical rows | Feature (Inertia props assertion) | `php artisan test --compact --filter="expense category options"` | ❌ Wave 0 |
| RPT-01 | Owner sees financial/profit report with revenue split (job sales vs cancellation fees) and a non-subtracted write-off disclosure line | Feature (Inertia props assertion) | `php artisan test --compact --filter="financial report"` | ❌ Wave 0 |
| RPT-01 | Owner sees EVERY report type (union); Admin gets 403 on the Reports route via direct URL | Feature (HTTP, cross-role denial — MANDATORY per D-04) | `php artisan test --compact --filter="admin cannot view reports"` | ❌ Wave 0 |
| RPT-02 | Cashier sees Daily Sales report scoped to a day range; Cancellation report shows fee/no-fee split | Feature (Inertia props assertion) | `php artisan test --compact --filter="cashier daily sales"` | ❌ Wave 0 |
| RPT-02 | Cashier cannot view Owner's financial report or Accounting's expense report (403 via direct URL) | Feature (HTTP, cross-role denial) | `php artisan test --compact --filter="cashier cannot view financial report"` | ❌ Wave 0 |
| RPT-03 | Production Staff sees a Production Status report derived from `production_logs`/`job_orders.status` | Feature (Inertia props assertion) | `php artisan test --compact --filter="production status report"` | ❌ Wave 0 |
| RPT-04 | Accounting Staff sees Sales report ranged to a day = "Daily", ranged to a month = "Monthly" (same query, D-06) | Feature (Inertia props assertion, two range params) | `php artisan test --compact --filter="accounting sales report ranges"` | ❌ Wave 0 |
| RPT-04 | Accounting's Expenses report excludes voided rows; Summary report = revenue minus non-voided expenses | Feature (Inertia props assertion + DB seeding) | `php artisan test --compact --filter="summary of sales and expenses"` | ❌ Wave 0 |
| RPT-05 | Export PDF route returns a `Content-Type: application/pdf` response (or equivalent binary signature) for an entitled role | Feature (HTTP, response header/content assertion) | `php artisan test --compact --filter="export pdf"` | ❌ Wave 0 |
| RPT-05 | Export Excel route returns a valid `.xlsx` binary (assert `Content-Type`/`Content-Disposition` headers; full content parsing is optional/manual) | Feature (HTTP, response header assertion) | `php artisan test --compact --filter="export xlsx"` | ❌ Wave 0 |
| RPT-05 | Export route is denied (403) for a non-entitled role via direct URL, matching the on-screen read denial | Feature (HTTP, cross-role denial — MANDATORY per D-04) | `php artisan test --compact --filter="export denied for wrong role"` | ❌ Wave 0 |
| RPT-05 (D-02) | Every export writes exactly one `audit_trail` row (who, report key, date range, format); viewing on screen writes zero | Feature (HTTP + DB assertion) | `php artisan test --compact --filter="export writes audit entry"` | ❌ Wave 0 |
| D-08/Pitfall 1 | Report revenue total is computed from `confirmed_at`, not `created_at` — regression test with a GCash transaction whose two timestamps fall on different days | Unit or Feature (DB assertion) | `php artisan test --compact --filter="revenue uses confirmed_at"` | ❌ Wave 0 |
| D-03 | Collection letter PDF route reproduces the exact 404 guards from `CollectionLetterController::show` (non-Active status, Paid/WrittenOff collection_status) | Feature (HTTP, 404 assertion) | `php artisan test --compact --filter="collection letter pdf guards"` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `php artisan test --compact --filter={Feature}` (narrowest slice covering the just-written behavior)
- **Per wave merge:** `php artisan test --compact` (full suite)
- **Phase gate:** Full suite green before `/gsd-verify-work`; also run `vendor/bin/pint --dirty --format agent` and `composer types:check`/`npm run types:check` since this phase introduces two new Composer packages that must type-check at Larastan level 7 (dompdf/openspout facades and return types are a plausible source of level-7 friction — untyped facade returns, mixed types on `Options`/`Properties` constructor args).

### Wave 0 Gaps

- [ ] `tests/Feature/AccountingStaff/ExpenseTest.php` — covers EXP-01, D-11, D-12, D-14
- [ ] `tests/Feature/Reports/FinancialReportTest.php` (or per-role: `OwnerReportsTest.php`) — covers RPT-01, D-08, D-09, D-10, D-04's cross-role denial
- [ ] `tests/Feature/Reports/CashierReportsTest.php` — covers RPT-02
- [ ] `tests/Feature/Reports/ProductionStatusReportTest.php` — covers RPT-03
- [ ] `tests/Feature/Reports/AccountingReportsTest.php` — covers RPT-04, D-06
- [ ] `tests/Feature/Reports/ReportExportTest.php` — covers RPT-05, D-02 (PDF + Excel content-type/audit assertions)
- [ ] `tests/Feature/AccountingStaff/CollectionLetterPdfTest.php` — covers D-03's reproduced guards
- [ ] Factory: `database/factories/ExpenseFactory.php` (new — mirrors `TransactionFactory.php`'s shape, needs a `voided()` state)
- [ ] No new test framework install needed — Pest + `pestphp/pest-plugin-laravel` already fully configured and used by every prior phase's tests.

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | No (unchanged — Fortify already covers this) | — |
| V3 Session Management | No (unchanged) | — |
| V4 Access Control | **Yes — this phase's central risk** | Server-side entitlement check on every report read AND export route (D-04); route middleware groups (`role:{role}`) are necessary but NOT sufficient for the Owner-not-Admin split (see Pitfall 2) — needs an explicit narrowing check |
| V5 Input Validation | Yes | Form Requests (`FilterReportRequest`, `StoreExpenseRequest`, etc.) with `date`/`numeric`/`Rule::in()` rules, matching every existing Request in this codebase — never raw `$request->input()` interpolated into a query |
| V6 Cryptography | No new surface | — |
| V7 (implied) File/Output handling | Yes | `Pdf::loadView()`'s Blade views must not echo unescaped user-supplied strings (`{{ }}` not `{!! !!}` for any customer name, description, or reason field rendered into a PDF); openspout writes raw cell values, not HTML, so no equivalent XSS-in-file risk there |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Direct-URL access to a report/export route by a role not entitled to it (Cashier hitting the Owner's financial-report export URL) | Elevation of Privilege | Explicit server-side entitlement check on both `index()` and `export()` actions — not middleware-group membership alone, since the existing `role:owner,admin` group cannot express Owner-not-Admin (D-04/D-05) |
| Report data leaking across roles via a shared component that receives more data than the requesting role should see | Information Disclosure | Controller builds the Inertia props payload AFTER the entitlement check resolves which report keys are allowed — never send the full registry to the client and hide entries with `v-if` |
| Un-validated `from`/`to` query params reaching a raw SQL date comparison | Tampering / Injection | `FilterReportRequest`'s `'date'` validation rule rejects non-date input before it reaches the query builder; Eloquent's parameter binding (not raw SQL string concatenation) handles the rest — every existing report/filter controller in this codebase already does this correctly (`PerformanceReportFilterRequest`, `FilterAuditTrailRequest`) |
| Mass assignment on the new `Expense` model exposing `recorded_by` or `voided_at` to client-controlled input | Tampering | `#[Fillable([...])]` attribute lists only the client-writable fields (category, amount, expense_date, description); `recorded_by` and void fields set via `forceFill()` server-side only, exactly matching `Transaction`'s and `AccountsReceivable`'s existing `#[Fillable]` lists which both omit their own server-set audit columns |
| An export route becoming a secondary, unaudited path to read financial data (screenshot the exported PDF, bypass the "view leaves no trace but export does" intent) | Repudiation | D-02's mandatory audit write on every export closes this — the control here IS the requirement, not a gap to separately mitigate |
| A voided expense still being editable, letting Accounting quietly "unvoid" and manipulate historical figures without a new audit trail entry | Tampering / Repudiation | `abort_if($expense->voided_at !== null, 422, ...)` guard on the edit action, matching `WriteOffRequestController`'s existing "already closed" guard shape; `AuditObserver::updated()` still captures any state change that does occur |

## Sources

### Primary (HIGH confidence — direct codebase reads)

- `app/Models/Transaction.php`, `app/Models/JobOrder.php`, `app/Models/AccountsReceivable.php`, `app/Models/ProductionLog.php`, `app/Models/SystemConfiguration.php` — schema, casts, relations, derived-value methods
- `database/migrations/2026_09_04_090002_create_transactions_table.php`, `2026_09_04_100000_create_accounts_receivable_table.php`, `2026_09_08_090000_add_aging_and_collection_columns_to_accounts_receivable_table.php`, `2026_09_05_120100_create_production_logs_table.php`, `2026_08_31_165342_create_audit_trail_table.php` — real column names
- `app/Http/Controllers/Artist/PerformanceReportController.php` + `PerformanceReportFilterRequest.php` — the only existing report pattern
- `app/Http/Controllers/AccountingStaff/CollectionLetterController.php`, `WriteOffRequestController.php` — Blade/print-document and void-with-reason precedents
- `app/Http/Controllers/Cashier/ReceiptController.php`, `PaymentController.php`, `CancellationController.php`, `app/Actions/POS/ConfirmPaymentIntent.php` — confirmed `confirmed_at` semantics across all payment methods
- `app/Observers/AuditObserver.php`, `app/Support/AuditLogger.php` — audit write mechanics
- `app/Policies/AccountsReceivablePolicy.php`, `app/Policies/DesignFilePolicy.php` — the Owner-not-Admin narrowing precedent
- `routes/owner.php`, `routes/portals.php` — confirmed the `role:owner,admin` blanket group problem
- `database/seeders/SystemConfigurationSeeder.php` — confirmed `expense_categories` already seeded
- `composer.json`, `composer show --direct` — confirmed neither dompdf nor openspout is installed yet; confirmed app's own `"php": "^8.3"` constraint

### Secondary (MEDIUM confidence — official docs, fetched this session)

- [barryvdh/laravel-dompdf GitHub](https://github.com/barryvdh/laravel-dompdf) — facade API (`loadView`, `download`, `stream`, `output`, method chaining)
- [openspout/openspout getting-started (5.x branch)](https://raw.githubusercontent.com/openspout/openspout/5.x/docs/index.md) — confirmed v5's constructor-based Writer API, `openToFile`/`openToBrowser`, `Row::fromValues`, the explicit warning about not writing after `close()` when using `openToBrowser()`
- [openspout/openspout documentation.md (5.x branch)](https://raw.githubusercontent.com/openspout/openspout/5.x/docs/documentation.md) — confirmed readonly `Options`/`Properties` value-object construction style
- [Packagist: barryvdh/laravel-dompdf](https://packagist.org/packages/barryvdh/laravel-dompdf) — version 3.1.2, PHP ^8.1, dompdf/dompdf ^3.0
- [Packagist: openspout/openspout](https://packagist.org/packages/openspout/openspout) — version 5.11.3 (2026-09-02), PHP `~8.4.0 || ~8.5.0`

### Tertiary (LOW confidence — community discussion, used only for the Inertia-download pattern, which is a well-established consensus not a disputed claim)

- [Laracasts: "Force file downloads in Laravel + Inertia + Vue?"](https://laracasts.com/discuss/channels/inertia/force-file-downloads-in-laravel-inertia-vue) — confirms plain `<a>`/`window.location` pattern
- [github.com/inertiajs/inertia-laravel issue #255](https://github.com/inertiajs/inertia-laravel/issues/255) — "Not able to download a file from Controller (file sent via XHR)" — corroborates why `<Link>`/`router.visit()` cannot be used for exports

## Metadata

**Confidence breakdown:**
- Standard stack (dompdf): HIGH — installed-version-pending but API confirmed against official GitHub source, package not yet installed so no local `composer show` cross-check possible until execution
- Standard stack (openspout): MEDIUM — v5 API confirmed against the live 5.x branch docs, but this is a fast-moving package (v5 shipped mid-way through this very research session's context window) — re-verify installed version immediately post-`composer require`
- Architecture (report registry/entitlement): MEDIUM — the Owner-not-Admin problem and its precedent (`AccountsReceivablePolicy`) are HIGH confidence (directly read), but the exact registry container is genuinely open (CONTEXT.md's own "Claude's Discretion")
- Aggregation queries (transactions/production_logs/accounts_receivable): HIGH — verified against real migrations, models, and the controllers that write these columns
- Pitfalls: HIGH — five of six pitfalls are drawn from patterns already proven to have bitten this exact codebase in prior phases (SQLite whereDate, confirmed_at semantics, the Owner/Admin policy split), not speculative

**Research date:** 2026-09-10
**Valid until:** 7 days for the openspout version claim specifically (fast-moving package, confirmed mid-session version bump); 30 days for everything else (stable codebase facts and dompdf, which is a mature, slow-moving package)
