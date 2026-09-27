# Phase 8: Expenses & Reporting - Context

**Gathered:** 2026-09-10
**Status:** Ready for planning

<domain>
## Phase Boundary

Accounting Staff records expenses against the already-seeded `expense_categories` configuration, and every upstream module's existing data — transactions, job orders, production logs, accounts receivable — rolls up into read-only, role-scoped reports that export to real PDF and Excel files. Covers EXP-01 and RPT-01 through RPT-05.

The `expenses` table is one of the approved 12-table ERD and **does not exist yet** — this phase creates it, its model, factory, and the Accounting-side CRUD around it. Everything on the reporting side is **read-only aggregation over data other phases already write**; this phase adds no new money path, no new job-order mutation, and no new AR mutation.

**Explicitly not this phase:**

- Recording payments or altering `transactions` — Phase 5's `PaymentController` remains the single money-entry path (Phase 7 D-15).
- Creating or settling AR entries, or changing collection status — Phase 7 owns that lifecycle; this phase only reads it.
- Any change to the Artist Performance Report (JOB-10, Phase 4) — see D-07.
- Emailing or archiving the collection letter — D-03 produces the PDF file that finally makes it possible, but sending it is a separate capability (Phase 7 deferred it, and it stays deferred).
- Customer-facing anything. Every surface in this phase is behind `auth` + `role:` middleware.

</domain>

<decisions>
## Implementation Decisions

### Export & Document Generation (RPT-05)

- **D-01:** **Two new Composer dependencies are approved for this phase: `barryvdh/laravel-dompdf` (server-rendered PDF) and `openspout/openspout` (streamed `.xlsx`).** RPT-05 asks for "PDF or Excel", and this delivers genuine downloadable `.pdf` / `.xlsx` files rather than a CSV standing in for a spreadsheet and a Ctrl+P standing in for a PDF. `openspout` is chosen over `maatwebsite/excel` deliberately — it streams rows and is far lighter on memory than PhpSpreadsheet, which matters on Laravel Cloud. Neither library needs a headless browser, so both work on the managed host (PROJECT.md §Constraints). **This is an explicit user approval of a dependency change**, which PROJECT.md §Application Structure otherwise forbids without one — the approval covers exactly these two packages and nothing else. Rejected: the zero-dependency path (CSV download + browser print), because a `.csv` is not an `.xlsx` and a print dialog is not a file; and the split path (`openspout` only, PDF stays print-only), because it leaves the collection letter unattachable forever.
    - **Consequence for dompdf:** it renders a Blade view, which will be the **only Blade in an otherwise Inertia-only application**. Keep these views isolated (e.g. `resources/views/reports/**`) and do not let them become a second rendering path for anything on screen — the screen is Inertia, the file is Blade.
- **D-02:** **Exporting a report writes one `audit_trail` entry; viewing a report on screen writes nothing.** The entry records who, which report, which date range, and which format. Rationale: an export is financial data leaving the system as a portable file, which is the moment worth recording; a screen view is not. This is the project's **first deliberate non-model-driven audit write** — `AuditObserver` fires on model mutations and an export mutates nothing, so this needs an explicit write call. It must use the existing append-only write path only; no new update/delete surface on `audit_trail` may be introduced (PROJECT.md §Constraints, AUDIT-02). Rejected: no logging at all (no record of who pulled the numbers), and logging every view (fills the Owner's existing filterable audit viewer with routine reads and drowns the mutation events it was built for).
- **D-03:** **Phase 7's collection letter gains a "Download PDF" action; Phase 5's digital receipt stays print-only, untouched.** The letter is the one document that genuinely wants to be a file — mailed, attached, filed against a debtor — and Phase 7 D-11 explicitly parked this here ("when Phase 8 picks a PDF library for report export, the letter is the obvious second consumer"). The receipt already satisfies POS-06 as a print page and rewriting it is rework. This is a deliberate, bounded touch of a completed phase's code: **add a route and a Blade view, do not modify the existing `CollectionLetterController::show` Inertia path or `resources/js/pages/accounting-staff/CollectionLetter.vue`.** The existing terminal-state guards (404 on non-`Active` status, 404 on `Paid`/`WrittenOff` collection status) must be reproduced exactly on the PDF route — a closed entry must never be able to render a demand letter through the new path either.

### Report Surface & Entitlement (RPT-01 – RPT-05)

- **D-04:** **One shared Reports page backed by a single report registry, mounted into each entitled role's portal and filtered by that role's entitlement.** One date-range control, one preview table, one export pipeline — the shape `demo/main.js`'s reports panel already uses. Rationale: RPT-05 says "_any_ role-scoped report can be exported", which describes one export mechanism, not one per role; and adding a report later becomes one registry entry rather than a controller + page + two export routes. Rejected: a bespoke report controller and page per portal following `Artist/PerformanceReportController` — four near-duplicate controllers whose export logic would need a shared service anyway.
    - **The entitlement check is the single security boundary of this phase and MUST be enforced server-side on every read and every export route, not by filtering the report list in Vue.** Phase 1 RBAC-02's "blocked with a 403 even via direct URL" standard applies: a Cashier hitting the financial/profit report's export URL directly gets a 403, not a file. Every report must be tested for cross-role denial, not just for correct rendering.
- **D-05:** **Owner sees every report; Admin sees none.** Owner's entitlement is the union of all report types (RPT-01's financial/profit plus every other role's report). Admin gets no Reports surface at all. Rationale: Phase 1 split Owner from Admin precisely so Owner holds financial/approval powers and Admin holds user-management/config — reports are business data, not administration. RPT-01–RPT-04 never mention Admin, and the demo placing Reports under Admin is a UI reference, never a business-rules source (PROJECT.md §Context). Rejected: Owner limited to financial/profit only (the owner of the business could not see their own Cashier's sales report, which reads as a bug), and an Admin non-financial tier (invents a fifth entitlement level nothing asks for).
- **D-06:** **One report per subject plus a date-range control — "Daily" and "Monthly" are ranges, not report types.** There is one Sales report and one Expenses report; setting the range to a day yields RPT-04's "Daily Sales", setting it to a month yields "Monthly Sales". Rationale: they are the same query over different bounds, and building them as separate types means two of everything to answer the same question. **Verification must read RPT-04's "Daily/Monthly Sales" as "the Sales report, ranged to a day / to a month"** — this is a documented deviation from the requirement's literal wording, in the same spirit as PROJECT.md's `status`/`payment_status` split. Rejected: four separate registry entries (Daily Sales, Monthly Sales, Daily Expenses, Monthly Expenses — a custom range like "last 10 days" fits none of them), and a separate granularity toggle on top of the range (two controls where one does the job).
- **D-07 (derived from D-04/D-05):** **The existing Artist Performance Report (`Artist/PerformanceReportController`, JOB-10, Phase 4) is not folded into the shared Reports page and is not modified.** Artist appears in none of RPT-01–RPT-04, so it has no entitlement in the new registry; it stays exactly where Phase 4 put it, in the Artist portal, on its own route. It remains a useful _pattern_ reference (Inertia report page + Form Request date filter) without becoming a dependency.

### Financial & Profit Report (RPT-01)

- **D-08:** **Revenue is cash basis — the sum of `transactions` at `TransactionStatus::Completed` within the range.** Money that actually arrived. An `Active` on-credit balance contributes nothing until it is paid; a GCash payment still at `PendingConfirmation` contributes nothing until its webhook lands. Rationale: `transactions` is already the single source of truth for money entering the system (Phase 7 D-15), and it is the same basis the receipt and the Daily Sales report use — so Sales and Profit can never disagree with each other or with a receipt in a customer's hand. Rejected: accrual basis on job-order totals (two reports would show two different "revenue" figures for the same month), and showing both bases side by side (profit still has to pick one, and two revenue numbers on one page read as a bug).
- **D-09:** **Write-offs approved in the range are disclosed as their own labeled figure but are NOT subtracted from profit.** Under cash basis the written-off money was never counted as revenue, so deducting it books the same loss twice and can show a loss in a month where none occurred. Displaying it satisfies Phase 7 D-14's "a write-off is a loss to report" — it _is_ reported, visibly, next to the profit rather than inside it. **The line needs unambiguous labeling** (e.g. "Bad debt written off (not deducted — never collected)") or an Owner will expect the subtraction. Phase 7 D-14 also guarantees the source data: a write-off leaves `total_amount` and `transactions` intact, so nothing upstream has been rewritten.
- **D-10:** **Revenue splits into two labeled lines that sum to the total: job sales (`down_payment` + `balance_payment` + `full_payment`) and cancellation fees (`cancellation_fee`).** Rationale: income from work delivered and income from work abandoned are opposite signals — rising cancellation-fee income is a problem, not a success — and RPT-02 already makes cancellation a first-class report, so the concept exists. The `transactions.type` column makes the split free. Note the existing cancellation mechanics this rests on: `CancellationController` records a `CancellationFee` transaction **only for the shortfall** when prior payments are below the fee, and records **nothing** when prior payments already cover it (the excess is informational, no refund is processed). There are **no negative or refund transactions anywhere in the system** — every row is a positive inflow — so summing is safe. Rejected: one blended revenue figure, and excluding cancellation fees from revenue (the cash genuinely entered the business).

### Expenses (EXP-01)

- **D-11:** **An expense is editable and voidable, with every change audited.** Accounting can correct amount, category, date, or note, and can void an entry with a reason; a voided expense stops counting toward reports but the row survives. Auditing comes free by registering the model with `#[ObservedBy(AuditObserver::class)]` like every other model in the project. Rationale: a typo'd amount on a utility bill is an ordinary mistake, and "who changed this and when" is exactly what the audit trail exists to answer. An edited expense does change a previously-printed profit report; the audit trail is what explains the difference. Rejected: structural append-only with compensating negative entries (negative amounts are a concept nothing else in this system has, and PROJECT.md reserves structural append-only specifically for `audit_trail` — extending it here is a stronger constraint than EXP-01 asks for), and void-only with no edit (two rows and a reason string to fix one digit).
- **D-12:** **Accounting Staff is the only role that can create, edit, or void an expense. Owner can read the itemized expense list and the expense figures in the profit report, but cannot record one.** Matches EXP-01's wording on the write path and D-05's entitlement on the read path — the Owner needs to see where the money went without becoming a second data-entry path. Rejected: Owner restricted to the aggregate figure only ("Expenses: ₱48,200" with no breakdown is the first thing an owner asks about), and Owner sharing the write path (two writers into one table, blurring the role separation Phase 1 established).
- **D-13:** **An expense record carries: category, amount, expense date, an optional free-text description, `recorded_by`, and timestamps. No file attachment.** The description is what makes an expense list readable a month later ("Meralco — August") and costs one column; without it staff will abuse the category field as a note field. A receipt-image upload brings validation, storage, retention, and a viewer into a phase scoped to recording a number — that is its own capability. Rejected: the three literal fields only, and adding a receipt attachment.
- **D-14 (locked upstream, not re-opened):** **Categories come from the existing `expense_categories` key in `system_configurations`** (`business_rules` group, `type: array`, seeded default `['Utilities', 'Supplies', 'Rent']`). Phase 1's CONFIG-01 already made this Owner/Admin-editable and already seeded it. Do not create an `expense_categories` table, do not hardcode a category enum, and do not add a category-management UI — one already exists in the system configuration panel. Read it through the established `SystemConfiguration` accessor pattern that Phase 3 (DPI), Phase 4 (break minutes), Phase 5 (discount cap), and Phase 7 (`credit_term_days`) all follow.

### Claude's Discretion

- **Report registry mechanism** — a config array, an enum, a set of small invokable classes, or a service with a `reports()` map. What is locked is D-04's one-page-one-pipeline shape and D-04's server-side entitlement check, not the container.
- **Whether RPT-04's "Summary of Sales & Expenses" and RPT-01's financial/profit report are one registry entry visible to both roles, or two.** They are the same underlying query (D-08's revenue minus D-13's expenses); the practical difference is whether Accounting's version also shows D-09's write-off line and D-10's fee split. One entry with both roles entitled is the simpler read of D-05 — but if the two genuinely need different columns, two entries is fine. Decide it once and do not build a third variant.
- **Exact table, column, and enum naming** for `expenses` (`expense_date` vs `incurred_on`, `voided_at` vs a status enum, `description` vs `note`). Follow the string-backed TitleCase-key enum convention in `app/Enums/**` and the additive-migration style Phases 3–7 all used.
- **Whether a voided expense is a nullable `voided_at` + `void_reason` pair or a status enum.** D-11 locks the behavior (row survives, stops counting, reason required, audited); the shape is an implementation call. Whichever it is, **every report query must exclude voided rows** — that filter is the thing most likely to be forgotten in one place.
- **What happens to historical expenses when a category is removed from `expense_categories` config.** Existing rows must keep their stored category string and keep appearing in reports (a config edit must never rewrite history — the same principle behind Phase 6 D-06 and Phase 7 D-02); only the _entry form's_ dropdown narrows. The mechanism is discretionary; the invariant is not.
- **PDF letterhead and branding.** `demo/business_logo.png` and `demo/logo.png` exist and the shop clearly has one. Whether the report PDF carries a letterhead, and whether the collection-letter PDF (D-03) reuses the same layout, is a presentation call — `/gsd-ui-phase 8` can settle it.
- **Export filename convention and whether generation is synchronous or queued.** Synchronous is strongly preferred and consistent with the project — Phases 3–6 all deliberately chose synchronous processing over queued jobs, and this shop's data volumes are small. Revisit only if a report demonstrably blocks a request.
- **Excel export content shape** — raw rows matching the on-screen table (simplest, and what `openspout` streams most naturally) versus a formatted summary sheet. Prefer raw rows unless the UI phase says otherwise.
- **Reports page layout** — cards + preview panel + preset range buttons (the demo's shape) versus a plain list and a table. `UI hint: yes` on this phase, so `/gsd-ui-phase 8` can settle it against `demo/main.js`.
- **Default date range on first load** and whether the on-screen preview paginates or caps rows while the export carries the full set.

</decisions>

<canonical_refs>

## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements & Roadmap

- `.planning/REQUIREMENTS.md` §Expenses (line 68) — EXP-01 full text ("Accounting Staff can record an expense with a category, amount, and date"), the literal scope D-13 extends by exactly one optional field.
- `.planning/REQUIREMENTS.md` §Reporting (lines 72–76) — RPT-01 through RPT-05 full text. RPT-04's exact wording ("Daily/Monthly Sales, Daily/Monthly Expenses, and Summary of Sales & Expenses") is what D-06 reinterprets as ranges; the absence of Admin from RPT-01–RPT-04 is what grounds D-05.
- `.planning/REQUIREMENTS.md` §Audit Trail — AUDIT-01/AUDIT-02, which bound what D-02's export logging may and may not do.
- `.planning/ROADMAP.md` §Phase 8 — goal, the three success criteria, `Depends on: Phase 1, Phase 5, Phase 6, Phase 7`, `UI hint: yes`. Success criterion 2 is the authoritative per-role report list.

### Project-Level Context

- `.planning/PROJECT.md` §Constraints — **"Do not change the application's dependencies without approval"** is the constraint D-01 explicitly clears for exactly two packages; `audit_trail` structurally append-only (bounds D-02); Laravel Cloud as the hosting target (why D-01 rejects any headless-browser PDF renderer).
- `.planning/PROJECT.md` §Context — the approved 12-table ERD, of which `expenses` is one (so D-13's table needs no special justification, unlike a 14th table would); the demo is a UI reference and never a business-rules source (grounds D-05).
- `.planning/PROJECT.md` §Key Decisions — the `status`/`payment_status` split as precedent for D-06's documented deviation from a requirement's literal wording.

### Prior Phase Context

- `.planning/phases/07-accounts-receivable/07-CONTEXT.md` — **D-11** (collection letter is a print page, "Phase 8's RPT-05 is where PDF/Excel export gets solved once for every report" — the direct handoff D-01/D-03 pick up), **D-14** (a write-off leaves `total_amount` and transactions intact "because Phase 8's profit reporting needs the original figure intact" — the data guarantee D-09 depends on), **D-15/D-16** (`transactions` as the single money source of truth, balance derived never stored — grounds D-08), **D-04** (Owner's broader financial view deferred to RPT-01), and its Deferred Ideas ("Emailing or archiving the collection letter", "AR aging on the Owner dashboard").
- `.planning/phases/05-pos-payments/05-CONTEXT.md` — the transaction model, down-payment/balance semantics, and cancellation-fee netting that D-08 and D-10 aggregate over.
- `.planning/phases/01-foundation-rbac-auth-hardening-audit-trail/01-CONTEXT.md` — `AuditObserver` registration (D-11), the Form Request + Validation Concern trait pairing every write path in this project uses, the `Inertia::flash('toast', …)` mutation-feedback convention, and CONFIG-01's `SystemConfiguration` pattern (D-14).

### Implementation Rules

- `CLAUDE.md` — PHP conventions (curly braces always, constructor property promotion, explicit return types, PHPDoc array shapes, TitleCase enum keys), `vendor/bin/pint --dirty --format agent` after every PHP change, Larastan level 7, test-every-change enforcement, and the Laravel Boost guidelines.
- **Note: `.ai/rules/` does not exist in this repo**, despite `CLAUDE.md` §Project Rules describing it and `07-CONTEXT.md` citing it. Do not waste a step looking for it — `CLAUDE.md` is the whole of the written convention set. If a durable rule emerges during this phase, `record-rule` is how to create it.
- `.planning/codebase/CONVENTIONS.md` and `.planning/codebase/STRUCTURE.md` — the mapped state of the existing conventions and directory layout.

### Library Documentation (new dependencies — verify installed version before use)

- `barryvdh/laravel-dompdf` — confirm the installed major version's facade/API against its own docs before writing the render calls; do not assume from memory.
- `openspout/openspout` — same. Its v4 API differs materially from v3, and the `box/spout` predecessor's API is gone entirely.

### UI Reference (non-authoritative)

- `demo/main.js` lines ~7772–7845 (`rpSetPreset`, `rpMarkActive`, `rpSubFilter`, `rpGetRangeLabel`) — the date-range preset control (Today / week / month / quarter / custom) D-06's range control can follow.
- `demo/main.js` lines ~7847–8581 (`rpReportDefs`) — the client's report catalogue: `sales`, `jo`, `ar`, `prod`, `completed`, `cancel`, `quality`, `fileval`, `audit`, each with `title`, `sub`, `badge`, `cols`, `rows`. A useful column-naming and layout reference. **It is not the report list** — RPT-01–RPT-04 are, and several demo reports (`quality`, `fileval`) correspond to no requirement.
- `demo/main.js` lines ~8583–8711 (`rpPreview`) — the preview-panel shape D-04 follows.
- `demo/main.js` lines ~8717–8751 (`rpExport`) — **a stub**: it fires a toast and logs to the demo's audit array, and produces no file. Its only real contribution is the precedent for D-02 (it logs `Report exported — {title} ({format})` with the date range). Do not read it for export mechanics; there are none.
- Per `.planning/PROJECT.md` §Context, this demo is a UI/interaction reference only and never a business-rules source.

</canonical_refs>

<code_context>

## Existing Code Insights

### Reusable Assets

- `app/Http/Controllers/Artist/PerformanceReportController.php` — the **only existing report** in the project and the closest pattern for a report controller: `Inertia::render` with a `stats` payload plus an echoed `filters` array, a dedicated `PerformanceReportFilterRequest` for date validation, and a `SystemConfiguration::getInt` read for a business threshold. D-07 leaves it alone; copy the shape, not the file.
- `app/Http/Requests/Artist/PerformanceReportFilterRequest.php` — the date-range Form Request to model the shared report filter on.
- `app/Http/Controllers/AccountingStaff/CollectionLetterController.php` — the print-document pattern, and the source of D-03's guard requirements: `abort_unless(status === Active, 404)` plus `abort_if(collection_status ∈ {Paid, WrittenOff}, 404)`.
- `app/Http/Controllers/Cashier/ReceiptController.php` — the completed-transactions summation D-08 generalizes (`transactions->where('status', TransactionStatus::Completed)`).
- `app/Models/SystemConfiguration.php` + `database/seeders/SystemConfigurationSeeder.php` — `expense_categories` is **already seeded** at seeder lines 87–94 (`business_rules`, `type: array`, `['Utilities','Supplies','Rent']`). D-14 reads it; nothing here needs seeding.
- `app/Observers/AuditObserver.php` — registering the new `Expense` model with `#[ObservedBy(AuditObserver::class)]` gives D-11's audit coverage with no new code. Note that D-02's _export_ logging is a different, deliberate write — the observer cannot see it.
- `app/Enums/TransactionType.php` — `DownPayment` / `BalancePayment` / `FullPayment` / `CancellationFee`. D-10's revenue split is exactly this enum partitioned into two groups.
- `app/Enums/TransactionStatus.php`, `app/Enums/PaymentStatus.php` (now carrying Phase 7's terminal write-off case), `app/Enums/JobOrderStatus.php`, `app/Enums/AccountsReceivableCollectionStatus.php` — the vocabularies every report query filters on.

### Established Patterns

- Per-role controller namespaces (`app/Http/Controllers/{Role}/`) with matching page directories (`resources/js/pages/{role-kebab}/`) and a route group per role in `routes/portals.php` under `['auth', 'role:{role}']`. D-04's shared page has to sit inside this convention rather than beside it — expect one shared controller reached from multiple role-scoped routes, or one route group, with entitlement resolved from the authenticated user's role.
- Form Request + Validation Concern trait pair for every write path (`app/Http/Requests/**` + `app/Concerns/*ValidationRules.php`) — the expense create/update/void endpoints each need one.
- `Inertia::flash('toast', [...])` for mutation feedback.
- System config read through the `SystemConfiguration` accessor, never a hardcoded threshold (Phase 3 DPI, Phase 4 break minutes, Phase 5 discount cap, Phase 7 `credit_term_days`).
- String-backed, TitleCase-key enums.
- Wayfinder-generated route/action helpers for every new route — no hardcoded URLs. Regenerate with `--with-form`, not the bare command (01-04's documented trap).
- Additive migrations against existing tables; Phases 3–7 all added columns this way without conflict. `expenses` is a new table, which is the less common case here.
- Synchronous processing throughout — Phases 3–6 deliberately chose it over queued jobs; the only scheduled work in the project is Phase 7's `SendAccountsReceivableReminders` command.

### Integration Points

- **`routes/portals.php`** — every role group is here. The Reports routes and the Accounting expense routes both land in this file. Note the accounting group currently ends at AR; Phase 7 built it out from a single placeholder dashboard.
- **`resources/js/pages/accounting-staff/`** — exists (Phase 7 created it). The expense pages and the Accounting entry into the shared Reports page go here.
- **`resources/js/pages/owner/`** — exists, and Phase 7's `CreditRequests.vue` / write-off queue live here. Owner's Reports entry goes here per D-05.
- **`app/Http/Controllers/Owner/AuditTrailController.php`** — the existing read-only audit viewer. D-02's new export entries surface here, so check that a non-model-scoped entry renders sanely in its filters (it filters by user/action/date).
- **No `resources/views/` beyond the Inertia root and the mail templates** — D-01's dompdf Blade views are new territory in an Inertia-only app. Keep them isolated.
- **`vendor/bin/pint` and Larastan level 7** run over `app/`, `config/`, `database/`, `routes/` — the new dependencies' facades/classes must type-check at level 7, which is where a loosely-typed export helper will bite.

</code_context>

<specifics>
## Specific Ideas

- The demo's export toast copy — `Exporting "{title}" as PDF…` → `"{title}" exported successfully!` — and its audit line `Report exported — Daily Sales (PDF)` with `Date range: {label}` is a good model for D-02's audit entry text and for the export feedback.
- The demo's range label format (`Sep 1, 2026 – Sep 10, 2026`, `en-PH` locale, `month: 'short'`) is the shape to match; the whole system is peso-denominated and Philippine-localized.
- The demo's report cards carry a `badge` (`Sales`, `Audit`, …) alongside `title` and `sub` — a cheap way to group a role's report list visually without inventing a taxonomy.
- The demo's `jo` and `cancel` reports have sub-filters (`all` / `pending` / `active` / `completed` / `cancelled`, and `nofee` / `withfee`). RPT-02's Cancellation report could reasonably carry the fee/no-fee split, since `CancellationController` genuinely produces both cases (a fee transaction, or nothing when prior payments already covered it).
- The demo's audit report pulls the live audit log rather than fixtures — a reminder that an Audit Log _report_ is a distinct idea from the Owner's existing audit _viewer_. RPT-01–RPT-04 do not ask for one, so it is not in scope; the existing viewer stands.

</specifics>

<deferred>
## Deferred Ideas

- **Emailing the collection letter to the debtor.** D-03 produces the PDF file that finally makes this mechanically possible, but sending it is customer-facing automated dunning — rejected in Phase 7 D-06 and deferred again in Phase 7's own deferred list, for the same reason Phase 6 D-13 deferred customer pickup notifications. The letter stays human-triggered and printed.
- **A PDF download for the digital receipt (POS-06).** Considered under D-03 and deliberately left out — the receipt works, and no requirement asks for a file. If a customer ever needs one emailed, that phase can pick it up cheaply now that dompdf exists.
- **Report scheduling / emailed periodic reports** (e.g. a monthly summary mailed to the Owner). Nothing in RPT-01–RPT-05 asks for it, and the project has exactly one scheduled command; adding a second for a convenience feature is its own phase.
- **Receipt/document attachments on expense records.** Rejected in D-13 — storage, validation, retention, and a viewer are a capability, not a field.
- **Reports the demo has but no requirement covers** — `quality` (Quality Check report) and `fileval` (File Validation report). The data exists (`production_logs`, Phase 3's validation results), so they are cheap to add later, but RPT-01–RPT-04 do not name them and D-04's registry makes adding one a single entry when someone asks.
- **AR aging summary on the Owner's dashboard.** Phase 7 D-16 pointed this at RPT-01. This phase gives the Owner the financial/profit report; putting an aging widget on the _dashboard_ is a separate surface and was not discussed.
- **Charts and visualizations.** Every report here is a table plus totals. No requirement asks for a chart, and no charting library is installed — adding one is another dependency decision.

</deferred>

---

_Phase: 8-expenses-reporting_
_Context gathered: 2026-09-10_
