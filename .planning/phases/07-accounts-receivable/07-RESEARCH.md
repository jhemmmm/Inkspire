# Phase 7: Accounts Receivable - Research

**Researched:** 2026-09-08
**Domain:** Laravel 13 scheduled jobs, transactional mail, derived-balance financial reporting, print-only documents, Owner-gated approval workflows
**Confidence:** HIGH

## Summary

Phase 7 is almost entirely a **mechanism** problem, not a **decision** problem — 07-CONTEXT.md's 16 locked decisions (D-01 through D-16) already settle every business rule. What remains is verifying the Laravel 13/project-specific *how*: the scheduler idiom, the mail-failure-isolation wrapper, the idempotent bracket-crossing algorithm, the derived-balance query shape, and the printable-letter pattern. All five map to an exact precedent already committed in this codebase (Phases 4–6), so this phase is implementation-by-analogy, not new-technology research.

The one area needing genuine design work beyond CONTEXT.md is the **daily reminder command's idempotency algorithm** — D-07 names a single `last_reminder_bracket`-style column, but D-08 requires reminders to keep firing at the terminal 90+ bracket ("rejected: going quiet after the 90+ final notice"), which a single "current bracket > stored bracket" comparison cannot express once the bracket is already at its maximum value. This document proposes a concrete resolution (`## Architecture Patterns` → Pattern 1) and flags it as an item the planner should lock explicitly, since it is a real (if minor) tension between two locked decisions rather than an open discretion area.

No new Composer or npm packages are required. `resend/resend-php` (already installed, Phase 4), Laravel's built-in scheduler (`illuminate/console`, part of `laravel/framework` — already installed), and Vue's native `window.print()` (already used by `cashier/Receipt.vue`) cover every capability this phase needs.

**Primary recommendation:** Build the daily aging/reminder command as `App\Console\Commands\SendAccountsReceivableReminders`, registered via `Schedule::command(...)->daily()` in `routes/console.php` (not `bootstrap/app.php`'s `withSchedule`), with idempotency state on two new nullable `accounts_receivable` columns (`last_reminder_bracket`, `last_reminder_sent_at`) — additive, no new table, matching every precedent in this codebase.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Aging bracket computation (Current/1-15/16-30/31-60/61-90/90+) | API / Backend | — | Pure function of `due_at` vs `now()`; must be server-authoritative since it drives both the UI list and reminder-firing logic — never trust a client-computed bracket (RBAC-02 precedent already established project-wide) |
| Outstanding balance (derived, D-16) | API / Backend | Database / Storage | Computed from `job_orders.total_amount` minus `SUM(transactions.amount WHERE status=completed)` — same shape as `ReceiptController::show()`; never cached or stored |
| Daily bracket-crossing detection & reminder dispatch | API / Backend (scheduled command) | — | Runs headless via Laravel's scheduler (Laravel Cloud managed cron); no browser/Inertia involvement |
| Escalating reminder emails | API / Backend (Mailable + Mail facade) | — | `Mail::to(...)->send(new ...)`, wrapped in try/catch per 04-13's isolation pattern; outbound only, no client-side involvement |
| Accounting aging list & collection-status updates | Frontend Server (Inertia render) + API / Backend | Browser / Client | Inertia page render (`accounting-staff/AccountsReceivable/Index.vue`) backed by a controller query; mutation via Form Request + Policy |
| Printable collection letter | Browser / Client (print) | Frontend Server (Inertia render) | Dedicated read-only Inertia page + `window.print()`, matching `cashier/Receipt.vue` exactly — no PDF service tier involved |
| Write-off request & Owner approval | API / Backend | Frontend Server (Inertia render) | Async request/approve pattern identical to Phase 5's On-Credit flow — Accounting submits, Owner acts from a queue page |
| Audit trail of every AR mutation | Database / Storage (via Observer) | — | `#[ObservedBy(AuditObserver::class)]` already attached to `AccountsReceivable`; zero new code needed, automatic on every `created`/`updated` |

## User Constraints (from CONTEXT.md)

<user_constraints>

### Locked Decisions

**AR Entry Scope**
- **D-01:** Only `accounts_receivable` rows at `AccountsReceivableStatus::Active` appear in the aging report — Owner-approved On-Credit balances and nothing else. A partially-paid walk-in with an outstanding balance is tracked on the job order (`payment_status`), **not** in AR.

**Aging Clock & Brackets**
- **D-02:** A **due date is stamped on the AR row at approval** — a `due_at`-style column written when Owner approves, computed as `approved_at` plus a new configurable `credit_term_days` system configuration key (`business_rules` group, default 30). Aging counts **days past due**, not days since approval.
- **D-03:** Five brackets: **Current** (`due_at` in the future), then **1–15**, **16–30**, **31–60**, **61–90**, and **90+** days past due. Maps 1:1 onto AR-02's four reminder triggers — each reminder fires as an entry enters the next band. The boundary numbers live in code, not in `system_configurations`.

**Audience & Surfaces**
- **D-04:** **Accounting Staff get the full working aging list** — filter by bracket, drill into an entry, update collection status, print letters, request write-offs. **Owner gets a write-off approval queue only**, structurally identical to `resources/js/pages/owner/CreditRequests.vue`. Owner still receives every reminder email (D-06).

**Reminder Delivery (AR-02)**
- **D-05:** Reminders are **emails sent via the installed `resend/resend-php` transport**, following `app/Mail/DesignReviewRequested.php`. **The send MUST be wrapped in Phase 4's mail-failure isolation** (plan 04-13, `app/Actions/JobOrder/RecordDesignRevision.php`) so a Resend outage can never break the bracket transition or the scheduled run.
- **D-06:** All four levels go to **Accounting Staff and Owner, at every bracket** — only the subject line and tone escalate (15 = notice, 30 = urgent, 60 = escalation, 90+ = final, flagged for write-off). **The customer is never auto-emailed.**

**Reminder Trigger & Idempotency**
- **D-07:** A **daily scheduled Artisan command** finds every Active entry whose current bracket is later than the bracket recorded on the row, sends the matching reminder, and stamps the new bracket back. State lives in **one new column on `accounts_receivable`** (e.g. `last_reminder_bracket`) — **no new table**. This is the **first scheduled work in the project**. Laravel Cloud provides the managed scheduler.
- **D-08:** Reminders stop **only when the entry closes** — balance reaches zero, or a write-off is approved. Collection-status changes do **not** suppress them. A submitted-but-unapproved write-off request also keeps reminding. Rejected: going quiet after the 90+ final notice.

**Collection Status (AR-03)**
- **D-09:** Collection status lives in its **own column, separate from `AccountsReceivableStatus`**.
- **D-10:** Six values: **Pending, Follow-up, Warning Sent, Collections, Paid, Written Off**. Accounting sets any of the first four **freely, in any direction**. **Paid and Written Off are system-set**, never hand-picked. Every change is audited automatically through `#[ObservedBy(AuditObserver::class)]`.

**Collection Letter (AR-03)**
- **D-11:** The letter is a **dedicated Inertia page styled for print**, opened and sent to the browser's print dialog — the pattern `app/Http/Controllers/Cashier/ReceiptController.php` + `resources/js/pages/cashier/Receipt.vue` already established. **No new dependency.**
- **D-12:** **One layout, body text selected by the entry's current aging bracket** at print time. **No stored letter state.**

**Write-Off (AR-04)**
- **D-13:** **Accounting requests a write-off with a mandatory reason; Owner approves or rejects** from their queue. Structurally identical to Phase 5 D-06's async credit request.
- **D-14:** On approval, the AR entry's `collection_status` becomes **Written Off** (reminders stop) **and the job order gets a matching terminal `payment_status`**. **The job order's `total_amount` and transactions are NOT altered.**

**Settlement & Balance**
- **D-15:** An AR balance is paid down **through the existing Cashier POS flow**. This phase reads transactions, never writes them.
- **D-16:** **Outstanding balance is derived, never stored** — job order total minus completed transactions, computed the way `ReceiptController::show` already does it. The **existing `accounts_receivable.balance` column is retained as the original approved credit amount** — do not repurpose it as a running balance.

### Claude's Discretion

- Exact column names and enum casing (`due_at`/`credit_due_at`, `last_reminder_bracket`/`last_reminder_sent_bracket`, `collection_status`, the new `PaymentStatus` write-off case, the aging-bracket enum itself). Follow the string-backed TitleCase-key convention.
- Whether the aging bracket is a computed accessor, a query scope, or a small value object.
- The scheduled command's name and run time — either `Schedule::command(...)` in `routes/console.php` or `bootstrap/app.php`'s `withSchedule` is acceptable, "whichever matches Laravel 13's current idiom. Verify the choice against installed-version docs rather than assuming."
- Backfilling `due_at` for AR entries Phase 5 already approved — reuse WR-10's atomic, re-runnable backfill shape.
- Mail class structure — one `Mailable` with a bracket-driven subject/view versus four classes.
- Whether the AR entry detail page shows an activity log (filtered read of `audit_trail`, never a parallel log).
- How the Accounting portal's aging list is laid out (`UI hint: yes` — `/gsd-ui-phase 7` territory).
- What a rejected write-off request does to the entry — "the entry should return to Active and keep aging — but the exact flagging is an implementation call."

### Deferred Ideas (OUT OF SCOPE)

- **Payment adjustments** (Credit Memo / Write-down / Overpayment Correction) — the demo's `acConfirmAdjustment` modal. Not built: contradicts D-16's derived-balance model and D-15's single-money-path rule.
- **Manual reminder snooze / payment-plan hold** — rejected in D-08.
- **Configurable aging bracket boundaries** — D-03 hardcodes 15/30/60/90; a future `business_rules` key if ever needed.
- **Emailing or archiving the collection letter** — D-11's print page cannot be attached to anything; Phase 8 RPT-05 territory.
- **Customer-facing automated dunning** — rejected in D-06; NOTF-01 is deferred to v2.
- **AR aging on the Owner dashboard** — D-04 keeps Owner to an approval queue; Phase 8 RPT-01 territory.

</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| AR-01 | Accounting Staff can view outstanding balances grouped into aging brackets (Current, 15/30/60/90+ days) | `## Architecture Patterns` Pattern 3 (aging bracket accessor, derived-balance query, no-N+1 grouping); `## Code Examples` (ReceiptController-style balance derivation) |
| AR-02 | The system automatically sends escalating reminder notifications as an AR entry crosses each aging bracket | `## Architecture Patterns` Pattern 1 (idempotent daily command), Pattern 2 (Mailable + mail-failure isolation); `## Common Pitfalls` Pitfall 1, 2 |
| AR-03 | Accounting Staff can update an AR entry's collection status and generate a printable collection letter | `## Architecture Patterns` Pattern 4 (collection status Form Request + Policy), Pattern 5 (printable letter, `window.print()`) |
| AR-04 | Owner can approve a write-off of an AR balance | `## Architecture Patterns` Pattern 6 (write-off request/approve, additive columns, `PaymentStatus` regression checklist) |

</phase_requirements>

## Project Constraints (from CLAUDE.md)

- PHP 8.4: curly braces always, constructor property promotion, explicit return types on every method, PHPDoc array-shape blocks over inline comments, TitleCase enum keys.
- Run `vendor/bin/pint --dirty --format agent` after any PHP change (not `--test`).
- Larastan level 7 (`composer types:check` / `phpstan analyse`) — every new class needs full type coverage; generic PHPDoc on relations (`HasMany<T, $this>` etc.) exactly as `ProductionLog`'s relations do.
- Pest for all tests; `php artisan make:test --pest {Name}`; run the narrowest test file/filter first, full suite (`php artisan test --compact`) before considering the phase done.
- Wayfinder: regenerate with `--with-form` after adding routes; never hand-write a URL string in a `.vue` file.
- No new dependencies without approval — this phase needs none (confirmed below).
- Inertia v3 Vue conventions: `<script setup lang="ts">`, single root element, `defineOptions({ layout: {...} })`, Wayfinder-bound `<Form>`.
- Do not create documentation files unless explicitly requested (this RESEARCH.md is the one artifact the workflow itself requires).
- `.ai/rules/` does not exist in this repository — no additional project-level rule files apply beyond CLAUDE.md and the Boost skill guidelines.

## Standard Stack

### Core

No new packages. Every capability this phase needs is already installed and in production use elsewhere in the codebase:

| Library | Version | Purpose | Why Standard (already in this codebase) |
|---------|---------|---------|--------------|
| `laravel/framework` (`illuminate/console` Scheduling) | 13.30.1 [VERIFIED: codebase — `composer show laravel/framework`] | Daily reminder command scheduling | Built into the framework; zero new dependency. `routes/console.php` currently has only the stock `inspire` stub [VERIFIED: codebase]. |
| `resend/resend-php` | 1.12.0 [VERIFIED: codebase — `composer show --direct`] | Transactional email transport for escalating reminders | Already the sole mail precedent (`app/Mail/DesignReviewRequested.php`, Phase 4) [VERIFIED: codebase] |
| `illuminate/mail` (Mailable) | bundled with framework 13.30.1 | Reminder email construction | Same `Mailable` base class `DesignReviewRequested` extends |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Native `window.print()` (browser API, no package) | — | Collection letter print dialog | Exactly `cashier/Receipt.vue`'s `printReceipt()` function |
| `resources/js/components/ui/badge` (shadcn-vue, already generated) | — | Aging bracket / collection status color badges | Already used in `frontline-staff/QueueList.vue` for status badges — same `v-if`/`v-else-if` + `class="text-{color}-600 dark:text-{color}-400"` convention |
| `resources/js/components/ui/table`, `tabs`, `alert-dialog` | — | Aging list table, bracket filter tabs, write-off/status confirmation dialogs | Same components `owner/CreditRequests.vue` and `production-staff/Dashboard.vue` already use |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Laravel's built-in scheduler | `spatie/laravel-schedule-monitor` or a queue-worker-driven cron | Rejected — CONTEXT.md D-07 explicitly scopes this to the built-in scheduler ("nothing here needs the queue"); adding a monitoring package is an unapproved new dependency for a single daily command |
| Browser print (`window.print()`) | `barryvdh/laravel-dompdf`, `spatie/laravel-pdf` | Rejected — D-11 explicitly defers PDF to Phase 8 RPT-05 to avoid picking a PDF library twice |
| Laravel Notifications (`php artisan make:notification`) | `Mail::to(...)->send(new Mailable)` | Rejected — D-05 explicitly follows the one existing precedent (`Mail::to()`), and no `app/Notifications` directory exists in this codebase [VERIFIED: codebase — `find app -type d -name Notifications` returns nothing] |

**Installation:**
```bash
# No installation required — every package above is already in composer.json / package.json.
```

**Version verification:** `composer show laravel/framework` confirmed `v13.30.1` (released 2026-09-01, this week) [VERIFIED: codebase]. `composer show --direct` confirmed `resend/resend-php 1.12.0` [VERIFIED: codebase]. No package.json additions needed — the print pattern uses zero npm dependencies.

## Package Legitimacy Audit

**Not applicable — this phase installs no external packages.** Every library referenced above is already present in `composer.json`/`composer.lock` and was installed and audited in a prior phase (Phase 4 for `resend/resend-php`). No `slopcheck`/registry verification is required.

## Architecture Patterns

### System Architecture Diagram

```
                    ┌─────────────────────────────────────────┐
                    │  Laravel Cloud managed scheduler          │
                    │  (cron: schedule:run every minute)        │
                    └───────────────────┬───────────────────────┘
                                         │ triggers daily
                                         ▼
                    ┌─────────────────────────────────────────┐
                    │ SendAccountsReceivableReminders (Command) │
                    │  1. Query Active AR rows, due_at not null │
                    │  2. Compute AgingBracket per row (pure fn)│
                    │  3. Compare to last_reminder_bracket      │
                    │  4. If crossed a reminder-bearing bracket:│
                    │     Mail::to(Accounting+Owner)->send(...) │
                    │     wrapped in try/catch (04-13 pattern)  │
                    │  5. forceFill + save new bracket/sent_at  │
                    └───────────────────┬───────────────────────┘
                                         │ Mail::send()
                                         ▼
                    ┌─────────────────────────────────────────┐
                    │ Resend transport (resend/resend-php)      │
                    │  → Accounting Staff + Owner inboxes       │
                    └────────────────────────────────────────────┘

  Browser (Accounting Staff)                 Browser (Owner)
        │                                          │
        │ GET accounting-staff/                    │ GET owner/write-offs
        │     accounts-receivable                  │
        ▼                                          ▼
┌───────────────────────┐                ┌──────────────────────────┐
│ AccountsReceivable     │                │ WriteOffApprovalController│
│ Controller::index      │                │  ::index / approve/reject │
│  - WHERE status=Active │                │  - WHERE write_off_       │
│  - eager-load jobOrder,│                │    requested_at NOT NULL  │
│    transactions        │                │  - Owner-only Policy      │
│  - derive balance per  │                │  - locked re-read (WR-04  │
│    row (D-16)          │                │    concurrency pattern)   │
│  - compute AgingBracket│                └──────────────────────────┘
└───────────┬────────────┘
            │
            ▼
┌───────────────────────┐        ┌─────────────────────────────┐
│ CollectionStatus       │        │ CollectionLetterController   │
│ Controller::update     │        │  ::show (read-only render)   │
│  - Form Request + Policy│       │  - derive bracket → body text│
│  - #[ObservedBy] audits │       │  - window.print() on client  │
└───────────────────────┘        └─────────────────────────────┘
```

### Recommended Project Structure

```
app/
├── Console/
│   └── Commands/
│       └── SendAccountsReceivableReminders.php   # D-07 — first command in the project
├── Enums/
│   ├── AccountsReceivableAgingBracket.php         # D-03 — Current/OneToFifteen/.../NinetyPlus
│   ├── AccountsReceivableCollectionStatus.php     # D-09/D-10
│   └── PaymentStatus.php                          # extend: + WrittenOff case (D-14)
├── Mail/
│   └── AccountsReceivableReminder.php             # D-05/D-06 — bracket-driven subject/body
├── Http/
│   ├── Controllers/
│   │   ├── AccountingStaff/
│   │   │   ├── AccountsReceivableController.php   # AR-01 index + show
│   │   │   ├── CollectionStatusController.php     # AR-03 update
│   │   │   ├── CollectionLetterController.php     # AR-03 printable letter
│   │   │   └── WriteOffRequestController.php       # AR-04 Accounting-side request
│   │   └── Owner/
│   │       └── WriteOffApprovalController.php      # AR-04 Owner-side approve/reject
│   └── Requests/
│       └── AccountingStaff/
│           ├── UpdateCollectionStatusRequest.php
│           └── RequestWriteOffRequest.php
├── Concerns/
│   └── AccountsReceivableValidationRules.php       # collection-status + write-off-reason rules
├── Policies/
│   └── AccountsReceivablePolicy.php                # extend: requestWriteOff(), approveWriteOff(), rejectWriteOff()
└── Models/
    └── AccountsReceivable.php                      # extend: due_at, last_reminder_bracket,
                                                      #   last_reminder_sent_at, collection_status,
                                                      #   write_off_* columns + accessors

database/
├── migrations/
│   └── xxxx_add_aging_and_collection_columns_to_accounts_receivable_table.php
└── seeders/
    └── SystemConfigurationSeeder.php                # extend: credit_term_days key

resources/js/
├── pages/accounting-staff/
│   ├── AccountsReceivable/
│   │   ├── Index.vue       # AR-01 aging list (bracket tabs + summary cards)
│   │   └── Show.vue        # AR-03 detail + collection status + write-off request + audit log
│   └── CollectionLetter.vue # AR-03 print page
├── pages/owner/
│   └── WriteOffRequests.vue # AR-04 approval queue (CreditRequests.vue analog)
└── config/nav/
    └── accounting-staff.ts  # extend: "Accounts Receivable" nav item
```

### Pattern 1: Idempotent daily bracket-crossing detection (D-07 + D-08 resolved)

**What:** A scheduled command that (a) fires exactly once per bracket-entry for the four reminder-bearing brackets, and (b) keeps re-firing at the terminal 90+ bracket per D-08's explicit "never go quiet" requirement, without double-firing if run twice in the same day.

**The tension to resolve:** D-07 describes the mechanism as "current bracket later than the bracket recorded on the row" — a strict ordinal comparison. Read literally, once an entry reaches `NinetyPlus` and `last_reminder_bracket = NinetyPlus`, the bracket can never become "later than" `NinetyPlus` again (it is the terminal value), so no ordinal-only comparison can ever re-fire. D-08 explicitly rejects "going quiet after the 90+ final notice," which means the terminal bracket must be able to re-fire on a later day. A single enum-valued column cannot express both "have we ever sent this bracket's reminder" and "have we sent *today's* recurring 90+ reminder yet" — that second question needs a date, not just a bracket label.

**Recommended resolution:** two nullable additive columns, not one. D-07's "no new table" constraint (protecting the approved 12-table ERD from a 14th table) is satisfied — this is still zero new tables, only one extra column beyond what CONTEXT.md's prose illustrates. Treat D-07's `last_reminder_bracket` example as the *general shape* ("state lives on the row"), not a literal column-count cap; the rejected alternative it actually names is a dedicated `ar_reminders` log **table**, not a second column.

```php
// Migration (additive, same convention as every prior phase's AR/job_orders columns)
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

```php
// app/Console/Commands/SendAccountsReceivableReminders.php
final class SendAccountsReceivableReminders extends Command
{
    protected $signature = 'ar:send-reminders';
    protected $description = 'Send escalating AR reminder emails as entries cross aging brackets (AR-02).';

    public function handle(): int
    {
        AccountsReceivable::query()
            ->where('status', AccountsReceivableStatus::Active->value)
            ->whereNotNull('due_at')
            ->chunkById(50, function ($receivables): void {
                foreach ($receivables as $receivable) {
                    $this->processOne($receivable);
                }
            });

        return self::SUCCESS;
    }

    private function processOne(AccountsReceivable $receivable): void
    {
        $bracket = $receivable->agingBracket(); // pure fn of due_at vs now(), see Pattern 3

        // Only these four brackets carry a reminder — SixtyOneToNinety is a
        // silent display-only band per D-03's "maps 1:1 onto AR-02's four
        // reminder triggers" (15/30/60/90+, not five).
        if (! in_array($bracket, AccountsReceivableAgingBracket::reminderBearing(), true)) {
            return;
        }

        $lastBracket = $receivable->last_reminder_bracket;
        $crossedNewBracket = $lastBracket === null
            || $bracket->rank() > AccountsReceivableAgingBracket::from($lastBracket)->rank();

        $isTerminalRepeat = $bracket === AccountsReceivableAgingBracket::NinetyPlus
            && $lastBracket === AccountsReceivableAgingBracket::NinetyPlus->value
            && ($receivable->last_reminder_sent_at === null
                || ! $receivable->last_reminder_sent_at->isToday());

        if (! $crossedNewBracket && ! $isTerminalRepeat) {
            return;
        }

        try {
            Mail::to($this->reminderRecipients())->send(new AccountsReceivableReminder($receivable, $bracket));
        } catch (\Throwable $e) {
            report($e); // 04-13 isolation — a Resend outage never blocks the stamp below
        }

        $receivable->forceFill([
            'last_reminder_bracket' => $bracket->value,
            'last_reminder_sent_at' => now(),
        ])->save();
    }
}
```

Register in `routes/console.php` (see Pattern 1a below for why this location over `bootstrap/app.php`).

**Flag for planner confirmation:** this two-column design is a *research recommendation*, not a locked decision — CONTEXT.md's Claude's Discretion list covers column naming but does not explicitly anticipate the D-07/D-08 tension. If the planner or a follow-up discuss-phase pass concludes D-08's "never go quiet" should instead mean "stays visible in the list/queue forever" (not "re-emails daily forever"), the single-column, fire-once-per-bracket design D-07 describes literally is simpler and should be preferred instead — drop `last_reminder_sent_at` and the `isTerminalRepeat` branch entirely. **This is an Open Question (see below), not something this research locks unilaterally.**

### Pattern 1a: Scheduler registration location [VERIFIED: laravel.com/docs/13.x/scheduling]

Laravel 13's official docs state: "Your task schedule is typically defined in your application's `routes/console.php` file" — this is the primary, illustrated path. `bootstrap/app.php`'s `withSchedule(function (Schedule $schedule) {...})` closure is offered as an alternative "if you prefer to reserve your `routes/console.php` file for command definitions only."

**Recommendation: use `routes/console.php`.** This project's `routes/console.php` currently contains only the stock `inspire` example [VERIFIED: codebase] and nothing yet reserves it for "command definitions only" — there is no existing precedent pulling toward `withSchedule`. Follow the docs' primary/default path:

```php
// routes/console.php
use App\Console\Commands\SendAccountsReceivableReminders;
use Illuminate\Support\Facades\Schedule;

Schedule::command(SendAccountsReceivableReminders::class)
    ->daily()
    ->withoutOverlapping()
    ->onOneServer(); // safe: CACHE_STORE=database in this project's .env [VERIFIED: codebase]
```

`onOneServer()` requires the `database`, `memcached`, `dynamodb`, or `redis` cache driver [CITED: laravel.com/docs/13.x/scheduling#running-tasks-on-one-server]. This project's `.env` sets `CACHE_STORE=database` [VERIFIED: codebase], so `onOneServer()` is safe to add defensively even though Laravel Cloud's scheduler is typically single-instance for a shop this size — cheap insurance against a future multi-instance deployment silently double-firing reminders.

Laravel Cloud's scheduler is enabled via a toggle on the environment's App compute cluster in the dashboard (not a code change); once enabled, Laravel Cloud manages the `* * * * * php artisan schedule:run` cron entry [CITED: cloud.laravel.com/docs/scheduled-tasks]. This is a **deployment-time step**, not something this phase's code can verify or enforce — flag it in `## Environment Availability` below.

### Pattern 2: Mailable + mail-failure isolation (D-05, D-06)

**What:** One `Mailable` class, bracket-driven subject/content, matching `app/Mail/DesignReviewRequested.php`'s shape exactly (CONTEXT.md's discretion note explicitly allows "one Mailable with a bracket-driven subject/view" over four separate classes — this is the simpler, precedent-matching choice).

```php
// app/Mail/AccountsReceivableReminder.php
class AccountsReceivableReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AccountsReceivable $receivable, public AccountsReceivableAgingBracket $bracket) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->bracket->emailSubject($this->receivable));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.accounts-receivable-reminder',
            with: ['bracket' => $this->bracket, 'receivable' => $this->receivable],
        );
    }
}
```

The **mail-failure isolation** is applied at the call site (inside the scheduled command, Pattern 1), not inside the Mailable — exactly where `RecordDesignRevision::__invoke()` applies it: `try { Mail::to(...)->send(...); } catch (\Throwable $e) { report($e); }`. Critically, this means **the bracket/timestamp stamp still happens even if the send fails** — matching 04-13's core lesson ("a mail transport failure is caught and reported, never allowed to fail the write, since the transactional core already committed"). Do not wrap the `forceFill()->save()` in the same try/catch.

**Recipient resolution (D-06 — Accounting + Owner, every bracket, never the customer):**

```php
private function reminderRecipients(): Collection
{
    return User::query()
        ->whereIn('role', [UserRole::AccountingStaff->value, UserRole::Owner->value])
        ->where('is_active', true)
        ->pluck('email');
}
```

### Pattern 3: Aging bracket — computed accessor, no N+1 (AR-01)

**What:** A pure, DB-query-free method on the model, computed from the already-selected `due_at` column. No accessor triggers a query per row, so fetching N Active AR rows with `due_at` eager-selected costs exactly the one query that fetched them — no N+1 risk.

```php
// app/Enums/AccountsReceivableAgingBracket.php
enum AccountsReceivableAgingBracket: string
{
    case Current = 'current';
    case OneToFifteen = 'one_to_fifteen';
    case SixteenToThirty = 'sixteen_to_thirty';
    case ThirtyOneToSixty = 'thirty_one_to_sixty';
    case SixtyOneToNinety = 'sixty_one_to_ninety';
    case NinetyPlus = 'ninety_plus';

    public function rank(): int
    {
        return match ($this) {
            self::Current => 0,
            self::OneToFifteen => 1,
            self::SixteenToThirty => 2,
            self::ThirtyOneToSixty => 3,
            self::SixtyOneToNinety => 4,
            self::NinetyPlus => 5,
        };
    }

    /** @return array<int, self> */
    public static function reminderBearing(): array
    {
        // D-03: "maps 1:1 onto AR-02's four reminder triggers" (15/30/60/90+).
        // SixtyOneToNinety is display-only — no reminder fires on entry.
        return [self::OneToFifteen, self::SixteenToThirty, self::ThirtyOneToSixty, self::NinetyPlus];
    }
}
```

```php
// app/Models/AccountsReceivable.php (new method)
public function agingBracket(): AccountsReceivableAgingBracket
{
    if ($this->due_at === null || $this->due_at->isFuture()) {
        return AccountsReceivableAgingBracket::Current;
    }

    $daysPastDue = (int) $this->due_at->diffInDays(now());

    return match (true) {
        $daysPastDue <= 15 => AccountsReceivableAgingBracket::OneToFifteen,
        $daysPastDue <= 30 => AccountsReceivableAgingBracket::SixteenToThirty,
        $daysPastDue <= 60 => AccountsReceivableAgingBracket::ThirtyOneToSixty,
        $daysPastDue <= 90 => AccountsReceivableAgingBracket::SixtyOneToNinety,
        default => AccountsReceivableAgingBracket::NinetyPlus,
    };
}
```

**Derived balance (D-16), matching `ReceiptController::show()` exactly:**

```php
// app/Http/Controllers/AccountingStaff/AccountsReceivableController.php
public function index(): Response
{
    $receivables = AccountsReceivable::query()
        ->where('status', AccountsReceivableStatus::Active->value)
        ->with(['jobOrder:id,number,description,total_amount', 'jobOrder.queueEntry.customer:id,name', 'jobOrder.transactions:id,job_order_id,amount,status'])
        ->get(['id', 'job_order_id', 'balance', 'due_at', 'collection_status', 'last_reminder_bracket', 'write_off_requested_at']);

    $rows = $receivables->map(function (AccountsReceivable $receivable) {
        $amountPaid = (float) $receivable->jobOrder->transactions
            ->where('status', TransactionStatus::Completed->value)
            ->sum('amount');

        return [
            'id' => $receivable->id,
            'balance' => round((float) $receivable->jobOrder->total_amount - $amountPaid, 2),
            'aging_bracket' => $receivable->agingBracket()->value,
            // ...
        ];
    });

    // Grouping for AR-01's bracket summary: cheap, in-memory groupBy over
    // an already-fetched small collection (single-location shop scale —
    // no aggregate SQL needed; see Pitfall 3 below for the volume ceiling
    // past which this should move to a SQL CASE expression instead).
    $byBracket = $rows->groupBy('aging_bracket');

    return Inertia::render('accounting-staff/AccountsReceivable/Index', [
        'receivables' => $rows->values(),
        'bracketCounts' => $byBracket->map->count(),
    ]);
}
```

### Pattern 4: Collection status update (AR-03)

Matches the Form Request + Concern trait pairing used everywhere else in this codebase (`ProductionLogValidationRules` is the closest recent analog):

```php
// app/Concerns/AccountsReceivableValidationRules.php
trait AccountsReceivableValidationRules
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function collectionStatusRules(): array
    {
        return [
            // Paid/WrittenOff are system-set only (D-10) — reject them here
            // so a crafted request can never hand-set a system-only value.
            'collection_status' => ['required', Rule::in(['pending', 'follow_up', 'warning_sent', 'collections'])],
        ];
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function writeOffReasonRules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }
}
```

No `AccountsReceivablePolicy::updateCollectionStatus()` narrowing is needed beyond the existing `role:accounting_staff` route-group middleware — unlike the Owner-only `approve()`/`reject()` pair, D-10 gives collection-status authority to Accounting Staff generally, matching `ReconciliationController`'s un-narrowed pattern rather than `AccountsReceivablePolicy::approve()`'s Owner-exclusive one.

### Pattern 5: Printable collection letter (D-11, D-12)

Identical shape to `ReceiptController::show()` + `Receipt.vue` — read-only render, `print:hidden` button, `window.print()`. Body text is a `match()` on the bracket, computed server-side (never persisted, per D-12):

```php
// app/Http/Controllers/AccountingStaff/CollectionLetterController.php
public function show(AccountsReceivable $accountsReceivable): Response
{
    abort_unless($accountsReceivable->status === AccountsReceivableStatus::Active, 404);

    $accountsReceivable->loadMissing(['jobOrder.queueEntry.customer:id,name']);

    return Inertia::render('accounting-staff/CollectionLetter', [
        'jobOrderNumber' => $accountsReceivable->jobOrder->number,
        'customerName' => $accountsReceivable->jobOrder->queueEntry?->customer?->name,
        'balance' => $accountsReceivable->balance, // original approved amount, not derived (letter references the credit extended — planner should confirm whether the letter shows the *original* balance or *current outstanding*; D-16 keeps `balance` as the original, but a letter chasing payment plausibly wants the current outstanding figure — flagged in Open Questions)
        'bracket' => $accountsReceivable->agingBracket()->value,
        'letterBody' => $accountsReceivable->agingBracket()->letterBody(), // match() on bracket, D-12
    ]);
}
```

```vue
<!-- resources/js/pages/accounting-staff/CollectionLetter.vue -->
<script setup lang="ts">
function printLetter(): void {
    window.print();
}
</script>
<template>
    <Button variant="outline" class="print:hidden" @click="printLetter">Print Letter</Button>
    <Card class="mx-auto w-full max-w-2xl"> <!-- letter body, plain prose -->
</template>
```

### Pattern 6: Write-off request/approval (D-13, D-14)

Structurally mirrors `CreditRequestController` (Accounting/Cashier-side request) + `CreditApprovalController` (Owner-side approve/reject), including the **locked re-read concurrency guard** (`lockForUpdate()` + `abort_unless` inside `DB::transaction()`) that `CreditApprovalController::approve()`/`reject()` already establishes for exactly this "async approval on a shared row" shape:

```php
// app/Http/Controllers/AccountingStaff/WriteOffRequestController.php
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

```php
// app/Http/Controllers/Owner/WriteOffApprovalController.php
public function approve(ApproveWriteOffRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
{
    DB::transaction(function () use ($request, $accountsReceivable): void {
        $accountsReceivable = AccountsReceivable::query()->whereKey($accountsReceivable->id)->lockForUpdate()->firstOrFail();

        abort_if($accountsReceivable->write_off_requested_at === null, 422, __('No write-off request is pending for this entry.'));

        $accountsReceivable->forceFill(['collection_status' => AccountsReceivableCollectionStatus::WrittenOff->value])->save();

        // D-14: job order gets a matching terminal payment_status; total_amount
        // and transactions are untouched — this is a loss to report, not a sale
        // that shrank.
        $accountsReceivable->jobOrder->forceFill(['payment_status' => PaymentStatus::WrittenOff->value])->save();
    });

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Write-off approved.')]);

    return back();
}

public function reject(RejectWriteOffRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
{
    // "Return to Active and keep aging" (CONTEXT.md discretion note) —
    // AccountsReceivableStatus never left Active, so nulling the write_off_*
    // columns is what actually "returns" the entry to its unflagged state.
    // The prior reason/requester/timestamp survive permanently in
    // audit_trail.old_values via the model's #[ObservedBy] update hook —
    // satisfying "the reason lives with whoever did the chasing" without a
    // dedicated rejected-state column.
    DB::transaction(function () use ($accountsReceivable): void {
        $accountsReceivable = AccountsReceivable::query()->whereKey($accountsReceivable->id)->lockForUpdate()->firstOrFail();

        abort_if($accountsReceivable->write_off_requested_at === null, 422, __('No write-off request is pending for this entry.'));

        $accountsReceivable->forceFill([
            'write_off_reason' => null,
            'write_off_requested_by' => null,
            'write_off_requested_at' => null,
        ])->save();
    });

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Write-off request rejected.')]);

    return back();
}
```

`app/Policies/AccountsReceivablePolicy.php` extends with the exact Owner-only shape `approve()`/`reject()` already use:

```php
public function approveWriteOff(User $actor, AccountsReceivable $accountsReceivable): bool
{
    return $actor->role === UserRole::Owner;
}

public function rejectWriteOff(User $actor, AccountsReceivable $accountsReceivable): bool
{
    return $this->approveWriteOff($actor, $accountsReceivable);
}
```

**`PaymentStatus::WrittenOff` consumer regression checklist** (matching 06-07's precedent — every existing consumer must render/branch correctly for the new case):

| File | What must change |
|------|-------------------|
| `app/Enums/PaymentStatus.php` | Add `case WrittenOff = 'written_off';` [VERIFIED: codebase] |
| `resources/js/pages/cashier/Dashboard.vue` | `paymentStatusLabel()` switch needs a `written_off` case; the `v-if`/`v-else-if` chain (lines ~270-335) needs a new branch or the row silently renders no status badge [VERIFIED: codebase, exact line numbers checked] |
| `resources/js/pages/frontline-staff/Dashboard.vue` | Same `paymentStatusLabel()` + `v-if` chain issue [VERIFIED: codebase] |
| `app/Http/Controllers/FrontlineStaff/JobOrderReleaseController.php` | `abort_unless(in_array($jobOrder->payment_status, [Paid, OnCredit], true), ...)` — a written-off order should almost certainly never reach this check (it was released long before aging reached write-off), but confirm no code path re-attempts release on a written-off order [VERIFIED: codebase] |
| `app/Http/Controllers/ProductionStaff/ProductionBoardController.php` | Only reads `status`/`due_at` for the board query, not `payment_status` directly in the WHERE — low risk, but the UI-SPEC's "no payment hint on the board" rule should be re-confirmed still holds [VERIFIED: codebase — grep found only a docblock comment, not a live filter] |

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Scheduled daily job execution | A custom cron wrapper, a `while(true)` daemon, or a queued-job polling loop | Laravel's built-in `Schedule::command(...)->daily()` + Laravel Cloud's managed scheduler toggle | This is precisely what the framework's scheduler exists for; D-07 already names it explicitly, and Laravel Cloud (the project's stated hosting target) manages the cron entry with zero server access needed [CITED: cloud.laravel.com/docs/scheduled-tasks] |
| PDF collection letter | `barryvdh/laravel-dompdf`, `spatie/laravel-pdf`, or a custom `wkhtmltopdf` shell-out | Browser `window.print()` on a dedicated Inertia page | D-11 explicitly defers PDF to Phase 8 RPT-05 to avoid a duplicate library decision; the existing Receipt.vue precedent already proves this pattern works for the shop's actual printing workflow |
| Reminder recipient/notification routing | Laravel Notifications, a custom notification-preferences table | Plain `Mail::to($emails)->send(new Mailable)` | No `app/Notifications` directory exists anywhere in this codebase; the one precedent (`DesignReviewRequested`) uses raw `Mail::to()`, and D-06's audience is fixed (Accounting + Owner, always) with no per-user preference to model |
| Balance tracking / running ledger | A stored, decrementing `accounts_receivable.balance` column updated on every payment | Derive from `job_orders.total_amount - SUM(completed transactions.amount)` at read time | D-16 explicitly rejects a stored running balance — "one missed path... and AR silently disagrees with the receipt." `ReceiptController::show()` already proves this derivation pattern works correctly for exactly this arithmetic |
| Idempotency ledger for reminders | A dedicated `ar_reminders` log table recording every send | Two nullable columns on `accounts_receivable` (`last_reminder_bracket`, `last_reminder_sent_at`) | D-07 explicitly rejects a 14th ERD table — "the same explicit justification `system_configurations` required" — and `audit_trail` already records every column-write to the row automatically via `#[ObservedBy(AuditObserver::class)]` |

**Key insight:** every "don't hand-roll" item in this phase already has a working precedent committed somewhere in this exact codebase (Phases 4–6). This phase is copy-the-pattern work, not invent-a-solution work — the planner should point every task at the specific analog file named above rather than describing the desired behavior abstractly.

## Common Pitfalls

### Pitfall 1: D-07/D-08 idempotency tension silently mis-implemented

**What goes wrong:** A literal reading of D-07 ("current bracket later than the bracket recorded on the row") implemented as a single ordinal comparison will correctly fire the 15/30/60/90+ reminders once each, but will **never re-fire once an entry sits at 90+ for weeks** — directly violating D-08's explicit "rejected: going quiet after the 90+ final notice."
**Why it happens:** The two decisions were written by different people at different points in the same discussion and describe complementary intents (simple mechanism vs. never-silent behavior) without reconciling them into one algorithm.
**How to avoid:** Implement Pattern 1's two-column approach (or explicitly re-confirm with the user that "stays visible" only means "remains in the list/queue," not "re-emails daily," and drop the terminal-repeat branch). Either way, write a test that asserts what happens to an entry that has sat at 90+ for 30 consecutive daily command runs — this is the exact case CONTEXT.md's D-08 rationale calls out ("the one that most needs to stay visible").
**Warning signs:** A test suite with only "bracket just crossed → email sent" tests and no test for "already at 90+, command runs again the next day."

### Pitfall 2: Reminder email sent from inside the same transaction as the bracket stamp

**What goes wrong:** Wrapping `Mail::to(...)->send(...)` inside the same `DB::transaction()` (or the same try block) as `forceFill(['last_reminder_bracket' => ...])->save()` means a slow/failed mail send either rolls back the stamp (re-sending the same reminder every day forever) or blocks the whole command on a network call per row.
**Why it happens:** It looks more "atomic" to wrap both together, and nothing in Eloquent forces you to separate them.
**How to avoid:** Follow `RecordDesignRevision::__invoke()` exactly — the DB write commits first (or, here, is not itself in a transaction with the mail call at all, since there's only one write), then mail is attempted in its own `try/catch`, and a failure is `report()`-ed, never allowed to prevent the stamp.
**Warning signs:** A test that fakes `Mail::shouldReceive(...)->andThrow(...)` and asserts `last_reminder_bracket` was still updated — if this test doesn't exist, the ordering is unverified.

### Pitfall 3: In-memory bracket grouping becomes an N+1 or a performance cliff at scale

**What goes wrong:** Pattern 3's `groupBy()`-after-fetch approach is correct and simple at this shop's scale (a single-location print shop's Active AR count is expected to be dozens, not thousands — see PROJECT.md's repeated "small single-location shop" framing), but if `AccountsReceivableController::index()` eager-loads `jobOrder.transactions` for every row without a column allowlist, it risks pulling far more data than needed per row.
**How to avoid:** Always pass an explicit column list to `with([...])` exactly as `ReceiptController::show()` and `ProductionBoardController::index()` already do (`'jobOrder.transactions:id,job_order_id,amount,status'`) — never eager-load a bare relation name.
**Warning signs:** A Larastan or query-log check showing `SELECT *` on `transactions` for the aging list.

### Pitfall 4: `PaymentStatus::WrittenOff` breaks existing hardcoded `v-if`/`v-else-if` chains

**What goes wrong:** `cashier/Dashboard.vue` and `frontline-staff/Dashboard.vue` both branch on `payment_status` via an exhaustive-looking `v-if`/`v-else-if` chain with no `v-else` fallback (confirmed by direct read — lines ~270-335 in `cashier/Dashboard.vue`). A job order that reaches `written_off` after being released long ago is unlikely to reappear on these *active* dashboards (both filter to non-released/non-cancelled orders), but `paymentStatusLabel()`'s `switch` has no `default` case reachable from these two files either, and any future surface that lists **all** job orders regardless of state (e.g., a Phase 8 report) will hit this exact gap.
**Why it happens:** This is the identical class of regression 06-07 already had to fix a dedicated plan for (PaymentStatus/JobOrderStatus enum-consumer compatibility) — adding a new enum case is easy; finding every place that pattern-matches on the old exhaustive set is not.
**How to avoid:** Grep `payment_status` across `resources/js/pages/**` and `app/Http/Controllers/**` (the file list under Pattern 6's regression checklist above) before considering AR-04 done, and add the `written_off` branch/label everywhere `on_credit`/`paid` already have one — the Copywriting Contract's tone for this state (e.g., "Written Off") is a planner/UI-phase call, not locked here.
**Warning signs:** A written-off job order rendering a blank payment-status cell instead of a label.

### Pitfall 5: First-ever mail-testing convention gap in this codebase

**What goes wrong:** Neither `tests/Feature/Artist/DesignReviewTest.php` nor `tests/Feature/Public/DesignReviewTest.php` — the only two test files touching the codebase's one existing Mailable — assert anything about `Mail::fake()`/`Mail::assertSent(...)` [VERIFIED: codebase — `grep -n "Mail::fake\|assertSent"` across `tests/` returns zero hits in either file]. AR-02 is this phase's core deliverable and cannot ship untested; the planner must establish this convention from scratch rather than copy it.
**Why it happens:** Phase 4's `RecordDesignRevision` mail send was apparently exercised only end-to-end (or accepted without a mail-specific assertion) since a failed send doesn't fail the request either.
**How to avoid:** Use `Mail::fake()` + `Mail::assertSent(AccountsReceivableReminder::class, fn ($mail) => ...)` in the new command's feature tests — this is standard Laravel testing, not a project-specific pattern to discover, but flag it in `## Validation Architecture` → Wave 0 Gaps below since no existing test file demonstrates the assertion shape in this repo.
**Warning signs:** A "reminder command" test that only asserts the database columns changed, never that a `Mail::assertSent` occurred — this would pass even if the Mailable were never actually constructed correctly.

### Pitfall 6: `credit_term_days` config change retroactively shifting `due_at` for already-approved entries

**What goes wrong:** If `due_at` were computed on-read from `approved_at + SystemConfiguration::getInt('credit_term_days', 30)` instead of stamped once at approval time, changing the config value later would silently re-date every existing AR entry's aging bracket overnight.
**Why it happens:** Reading system config at render time (rather than write time) is the more "obvious" implementation and is exactly what several *other* system-config reads in this codebase correctly do (e.g., `default_sla_days` inside `EnterProduction`, read fresh each time a job order enters production) — the difference here is that D-02 explicitly calls for a stamp-at-event pattern instead, mirroring Phase 6 D-06.
**How to avoid:** Compute `due_at` exactly once, inside `CreditApprovalController::approve()`, at the moment `AccountsReceivableStatus::Active` is set — `'due_at' => now()->addDays(SystemConfiguration::getInt('credit_term_days', 30))` — and never recompute it afterward.
**Warning signs:** A test that changes the `credit_term_days` config value and re-fetches an already-approved AR entry's `due_at`, expecting it to be unchanged — if this test would fail, the stamp-vs-compute distinction was implemented backward.

## Code Examples

### Derived balance (verified pattern, `ReceiptController::show()`)
```php
// Source: app/Http/Controllers/Cashier/ReceiptController.php:27-31 (read directly from codebase)
$completedTransactions = $jobOrder->transactions->where('status', TransactionStatus::Completed);
$amountPaid = (float) $completedTransactions->sum('amount');
$balance = $jobOrder->total_amount !== null
    ? round((float) $jobOrder->total_amount - $amountPaid, 2)
    : 0.0;
```

### Locked re-read concurrency guard (verified pattern, `CreditApprovalController::approve()`)
```php
// Source: app/Http/Controllers/Owner/CreditApprovalController.php:44-58 (read directly from codebase)
DB::transaction(function () use ($request, $accountsReceivable): void {
    $accountsReceivable = AccountsReceivable::query()->whereKey($accountsReceivable->id)->lockForUpdate()->firstOrFail();

    abort_unless(
        $accountsReceivable->status === AccountsReceivableStatus::PendingApproval,
        422,
        __('This credit request has already been resolved.'),
    );

    $accountsReceivable->forceFill([...])->save();
});
```

### Mail-failure isolation (verified pattern, `RecordDesignRevision::__invoke()`)
```php
// Source: app/Actions/JobOrder/RecordDesignRevision.php:47-51 (read directly from codebase)
try {
    Mail::to($jobOrder->queueEntry->customer->email)->send(new DesignReviewRequested($revisionLog));
} catch (\Throwable $e) {
    report($e);
}
```

### System config read (verified pattern, seeded key + static accessor)
```php
// Source: app/Models/SystemConfiguration.php (read directly from codebase)
$creditTermDays = SystemConfiguration::getInt('credit_term_days', 30);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|---------------|--------|
| Laravel <11: scheduled tasks defined in `app/Console/Kernel.php::schedule()` | Laravel 11+: scheduled tasks defined in `routes/console.php` (or `bootstrap/app.php`'s `withSchedule`) | Laravel 11 (2024) restructured the console kernel away; this project is on Laravel 13.30.1, which continues the same `routes/console.php`-first convention [CITED: laravel.com/docs/13.x/scheduling] | No `app/Console/Kernel.php` exists in this codebase [VERIFIED: codebase] — confirms the project was scaffolded post-Laravel-11 restructure; do not look for or recreate a Kernel-based schedule() method |

**Deprecated/outdated:** None specific to this phase's scope — every pattern used here (scheduler, Mailable, derived balance, print-only document) is the current, non-deprecated Laravel/Inertia approach as of framework version 13.30.1.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | The two-column (`last_reminder_bracket` + `last_reminder_sent_at`) resolution to the D-07/D-08 tension is the correct interpretation of "reminders stop only when the entry closes... rejected: going quiet after the 90+ final notice" (i.e., that it means *re-emailing* daily at 90+, not just *remaining visible* in the list) | Architecture Patterns → Pattern 1 | If the intended meaning was only "stays in the list/queue" (no daily re-email), the planner builds unnecessary complexity (a second column, a `isTerminalRepeat` branch, a recurring-mail test) for a requirement that doesn't actually exist. Low-cost to simplify later; flagged explicitly as an Open Question below rather than locked. |
| A2 | The collection letter's `balance` field should show the *original approved credit amount* (D-16's literal `accounts_receivable.balance` column) rather than the *current derived outstanding balance* | Architecture Patterns → Pattern 5 | If the letter should show current outstanding (more likely correct for a collection letter chasing an unpaid amount), the controller needs the same derivation as Pattern 3, not the raw stored column — flagged as Open Question below. |
| A3 | Collection-status update authorization needs no `AccountsReceivablePolicy` narrowing beyond route-group `role:accounting_staff` middleware (i.e., any Accounting Staff user may update any Active entry's status) | Architecture Patterns → Pattern 4 | Low risk — D-10 describes collection status as freely settable with no per-entry ownership concept, matching `ReconciliationController`'s existing un-narrowed pattern; if wrong, adding a Policy method later is a small, additive change. |

## Open Questions

1. **Does D-08's "never go quiet after 90+" mean recurring daily re-emails, or just remaining visible in the aging list/write-off queue?**
   - What we know: D-07 describes a strict "bracket later than stored bracket" mechanism (single-fire per bracket); D-08 explicitly rejects "going quiet after the 90+ final notice." These are in tension if 90+ is treated as a terminal, non-advancing bracket.
   - What's unclear: Whether "stay visible" refers to the email channel recurring, or simply to the entry remaining un-dismissed in Accounting's list and Owner's write-off queue (which it does regardless, since nothing in any decision removes a 90+ entry from either list until it closes).
   - Recommendation: Default to the simpler reading (D-07's literal single-fire mechanism; entries stay visible via the persistent list/queue, not repeat email) **unless** the planner or a quick `/gsd-discuss-phase 7` follow-up confirms recurring email is the actual intent. If recurring email is confirmed, use Pattern 1's two-column design as specified.

2. **Should the printed collection letter reference the original approved credit amount (`balance` column) or the current derived outstanding balance?**
   - What we know: D-16 explicitly keeps `accounts_receivable.balance` as the original approved amount and forbids repurposing it as a running balance. D-12 says the letter's body text is bracket-driven but doesn't specify which figure the letter quotes.
   - What's unclear: A customer who has made a partial payment against their AR balance (via the Cashier POS flow, per D-15) would see a stale, too-high number if the letter quotes the original `balance` rather than the current outstanding amount.
   - Recommendation: Quote the *current derived outstanding balance* (same computation as the aging list, Pattern 3) — a collection letter demanding an amount the customer has already partly paid would undermine its own purpose. Confirm with the user/planner if this reading is wrong.

3. **Exact wording/tone for the four reminder email subject lines and the four collection letter bodies.**
   - What we know: D-06 specifies tone escalation (notice/urgent/escalation/final) and D-12 specifies four body variants (polite/firmer/formal/final).
   - What's unclear: The literal copy — this is a UI/copywriting decision, not a research question, and CONTEXT.md's `UI hint: yes` flags `/gsd-ui-phase 7` as the intended venue for exactly this kind of decision (matching how Phase 6's board/banner copy was settled via its own Copywriting Contract in UI-SPEC.md).
   - Recommendation: Defer exact copy to the UI phase pass, same as Phase 5/6 did for their respective copy decisions; this research only locks the *mechanism* (bracket → subject/body selection via `match()`), not the strings themselves.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Laravel scheduler (`illuminate/console`) | AR-02 daily command | ✓ (bundled with framework) | 13.30.1 | — |
| `resend/resend-php` transport | AR-02 reminder emails | ✓ | 1.12.0 | — |
| Laravel Cloud scheduler toggle (dashboard, not code) | AR-02's cron trigger in production | **Unverified** — this is a deployment-console setting, not something visible from the repository [CITED: cloud.laravel.com/docs/scheduled-tasks — "enable the Scheduler toggle... save and re-deploy"] | — | Local/dev: `php artisan schedule:work` runs the scheduler in the foreground for manual testing; does not require the toggle |
| Mail transport credentials (Resend API key) in production `.env` | AR-02 reminder emails actually delivering | **Unverified** — dev `.env` currently uses `MAIL_MAILER=smtp` pointed at a local mailpit-style catcher (`127.0.0.1:1025`) [VERIFIED: codebase], not `resend` | — | Dev/test: `MAIL_MAILER=array` in `phpunit.xml` already isolates tests from any real transport [VERIFIED: codebase] |

**Missing dependencies with no fallback:**
- None — every code-level dependency is already installed and testable via `MAIL_MAILER=array` / `schedule:work`.

**Missing dependencies with fallback:**
- Laravel Cloud's scheduler toggle and a production Resend API key are both deployment-time configuration, not phase-blocking — local development and the full Pest suite work without either (scheduler tested via direct `artisan ar:send-reminders` invocation in feature tests; mail tested via `Mail::fake()`). Flag both as a `checkpoint:human-verify` item for go-live, matching STATE.md's existing pattern for Phase 1's Laravel Cloud MySQL-privilege blocker and Phase 3's Ghostscript/Imagick blocker.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 5.1.3 + pestphp/pest-plugin-laravel 5.0.1 [VERIFIED: codebase — `composer show --direct`] |
| Config file | `phpunit.xml` (Pest bootstraps through PHPUnit's config); `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync` in the testing environment [VERIFIED: codebase] |
| Quick run command | `php artisan test --compact --filter=AccountsReceivable` (or a specific file path) |
| Full suite command | `php artisan test --compact` (baseline before this phase: 385 tests, 382 passed, 3 skipped [VERIFIED: codebase — `06-REVIEW-FIX.md`]) |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| AR-01 | Aging list groups Active receivables into correct brackets; derived balance matches transactions | feature | `php artisan test --filter=AccountsReceivableListTest` | ❌ Wave 0 |
| AR-02 | Daily command sends the correct reminder on bracket-crossing, is idempotent same-day, skips non-reminder-bearing brackets, isolates mail failures | feature | `php artisan test --filter=SendAccountsReceivableRemindersTest` | ❌ Wave 0 |
| AR-03 | Collection status update persists + audits; collection letter renders correct bracket-driven body | feature | `php artisan test --filter=CollectionStatusTest` / `--filter=CollectionLetterTest` | ❌ Wave 0 |
| AR-04 | Write-off request/approve/reject flow, concurrency guard (locked re-read), `PaymentStatus::WrittenOff` propagation | feature | `php artisan test --filter=WriteOffTest` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** narrow `--filter` run scoped to the file/behavior just changed
- **Per wave merge:** full suite (`php artisan test --compact`)
- **Phase gate:** Full suite green + `vendor/bin/pint --dirty --format agent` + `composer types:check` (Larastan level 7) before `/gsd-verify-work`, matching every prior phase's closing checklist in `06-REVIEW-FIX.md`

### Wave 0 Gaps
- [ ] `tests/Feature/AccountingStaff/AccountsReceivableListTest.php` — covers AR-01 (bracket grouping, derived balance, no-N+1 column allowlist)
- [ ] `tests/Feature/Console/SendAccountsReceivableRemindersTest.php` — covers AR-02, including the **first `Mail::fake()`/`Mail::assertSent()` test in this codebase** (Pitfall 5) and the same-day idempotency + terminal-bracket-repeat cases (Pitfall 1)
- [ ] `tests/Feature/AccountingStaff/CollectionStatusTest.php` + `CollectionLetterTest.php` — covers AR-03
- [ ] `tests/Feature/Owner/WriteOffApprovalTest.php` + `tests/Feature/AccountingStaff/WriteOffRequestTest.php` — covers AR-04, including the locked-re-read concurrency test matching `CreditApprovalTest.php`'s "CR-05" pattern
- [ ] `database/factories/AccountsReceivableFactory.php` — extend with `active()` state additions for `due_at`/`collection_status`, and a new state for entries at each aging bracket (e.g., `atBracket(AccountsReceivableAgingBracket $bracket)`) to make bracket-specific tests concise
- [ ] No new test framework/config install needed — Pest + `MAIL_MAILER=array` + `sync` queue already cover every capability this phase's tests need

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | No (new surface) | Already enforced project-wide by Fortify + existing session middleware; no new auth surface introduced |
| V3 Session Management | No (new surface) | Unchanged — inherits existing `VerifySingleSession`/`EnforceIdleSessionTimeout` middleware |
| V4 Access Control | Yes | `role:accounting_staff` / `role:owner,admin` route-group middleware (existing convention) + `AccountsReceivablePolicy` for the Owner-exclusive `approveWriteOff()`/`rejectWriteOff()` narrowing (matching the existing `approve()`/`reject()` pair exactly) |
| V5 Input Validation | Yes | Form Request + Concern trait pairing (`AccountsReceivableValidationRules`) — `collection_status` restricted to the four human-settable values via `Rule::in()` (rejecting `paid`/`written_off` from direct client input, per D-10's "system-set only"); write-off `reason` required/max:1000 |
| V6 Cryptography | No | No new secrets/crypto surface; Resend API key already managed via existing `config/services.php`-style env var pattern from Phase 4 |
| V8 Data Protection | Yes | `#[ObservedBy(AuditObserver::class)]` (already attached to `AccountsReceivable`) automatically writes every collection-status change and write-off approval to the structurally append-only `audit_trail` — no new code needed, but the planner must not add any update/delete path outside Eloquent's normal `save()` that would bypass the observer |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Double-submit / replay of write-off approve or reject (network retry, double-click, stale tab) | Tampering / Repudiation | Locked re-read (`lockForUpdate()` inside `DB::transaction()`) + `abort_if($write_off_requested_at === null)` re-check, exactly matching `CreditApprovalController`'s "CR-05" concurrency guard (already tested in `CreditApprovalTest.php`) |
| Client sets `collection_status` to `paid` or `written_off` directly via a crafted request | Elevation of Privilege | `Rule::in()` allowlist in `AccountsReceivableValidationRules::collectionStatusRules()` restricted to the four human-settable values only; `Paid`/`WrittenOff` are only ever set by system code paths (payment completion, write-off approval), never accepted from request input |
| Non-Owner (Admin) attempts to approve/reject a write-off directly via URL | Elevation of Privilege | `AccountsReceivablePolicy::approveWriteOff()`/`rejectWriteOff()` Owner-only check, identical shape to the existing `approve()`/`reject()` pair — Admin can view the queue (route-group middleware) but is blocked at the Policy layer, mirroring the existing `CreditApprovalTest.php` "admin can view but is forbidden from approving" test |
| Scheduled command run twice in the same day (manual re-run, deploy hook, or scheduler misfire) double-sends a reminder | Denial of Service (spam) / Repudiation (unreliable audit signal) | Idempotency check against `last_reminder_bracket`/`last_reminder_sent_at` (Pattern 1); `withoutOverlapping()` + `onOneServer()` on the `Schedule::command()` definition prevent concurrent scheduler-triggered overlap |
| Reminder email leaks customer PII to the wrong audience | Information Disclosure | D-06 fixes the audience to exactly Accounting Staff + Owner (internal staff only) — never the customer — so the existing "PII boundary" concerns that apply to `public/*` routes (Phase 6) do not apply here; still, filter `User::where('role', ...)->where('is_active', true)` so a deactivated account never receives reminders |

## Sources

### Primary (HIGH confidence)
- Direct codebase reads (this repository, commit state as of 2026-09-08): `app/Models/AccountsReceivable.php`, `app/Enums/AccountsReceivableStatus.php`, `app/Enums/PaymentStatus.php`, `app/Enums/JobOrderStatus.php`, `app/Http/Controllers/Cashier/ReceiptController.php`, `app/Http/Controllers/Cashier/CreditRequestController.php`, `app/Http/Controllers/Owner/CreditApprovalController.php`, `app/Policies/AccountsReceivablePolicy.php`, `app/Mail/DesignReviewRequested.php`, `app/Actions/JobOrder/RecordDesignRevision.php`, `app/Models/SystemConfiguration.php`, `app/Models/JobOrder.php`, `app/Models/Transaction.php`, `app/Observers/AuditObserver.php`, `app/Http/Controllers/FrontlineStaff/JobOrderReleaseController.php`, `app/Http/Controllers/Cashier/ReconciliationController.php`, `bootstrap/app.php`, `routes/console.php`, `routes/owner.php`, `routes/portals.php`, `database/seeders/SystemConfigurationSeeder.php`, `database/factories/AccountsReceivableFactory.php`, `tests/Feature/Owner/CreditApprovalTest.php`, `phpunit.xml`, `resources/js/pages/owner/CreditRequests.vue`, `resources/js/pages/cashier/Receipt.vue`, `resources/js/pages/cashier/Dashboard.vue`, `resources/js/pages/frontline-staff/Dashboard.vue`, `resources/js/pages/frontline-staff/QueueList.vue`, `resources/js/config/nav/accounting-staff.ts`, `resources/js/pages/accounting-staff/Dashboard.vue`
- `composer show laravel/framework` / `composer show --direct` — confirmed `laravel/framework 13.30.1`, `resend/resend-php 1.12.0`, `laravel/fortify 1.39.0`, `laravel/wayfinder 0.1.21`, `pestphp/pest 5.1.3`
- [Task Scheduling | Laravel 13.x](https://laravel.com/docs/13.x/scheduling) — fetched in full; scheduler location, `withoutOverlapping()`, `onOneServer()`, `schedule:work` vs cron

### Secondary (MEDIUM confidence)
- [Scheduled Tasks - Laravel Cloud](https://cloud.laravel.com/docs/scheduled-tasks) — WebSearch-derived summary of Laravel Cloud's scheduler-toggle mechanism, not independently fetched in full; cross-checked against the official `laravel.com/docs/13.x/scheduling` recommendation to use Laravel Cloud for managed cron, which agrees

### Tertiary (LOW confidence)
- None — every claim in this document is either a direct codebase read or a fetched/cross-checked official doc.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — zero new packages; every version number confirmed via `composer show` against the actual installed lockfile
- Architecture: HIGH — every pattern is a direct analog to already-shipped, already-tested code in this exact repository (Phases 4, 5, 6)
- Pitfalls: HIGH — five of six pitfalls are drawn from direct evidence (grep results, prior REVIEW-FIX.md precedent for the exact same regression class); Pitfall 1 (D-07/D-08 tension) is a reasoned analysis of the locked decisions' text, flagged honestly as requiring planner/user confirmation rather than presented as settled fact

**Research date:** 2026-09-08
**Valid until:** 30 days (stable Laravel-ecosystem patterns; re-verify if `laravel/framework` or `resend/resend-php` receive a major version bump before planning begins)
