---
quick_id: 261001-few
mode: quick
status: approved
---

# Quick Task 261001-few: Admin Job Orders and Audit Trail exports

Source: `let-s-create-a-plan-twinkly-breeze.md`, section "2. Job Orders and Audit Trail exports" ONLY. Section 1 (report export parity) is already done (`261001-er9`, commit `f253aa3`). Sections 3-5 (profile pictures, search/filters, responsive layout) are separate later tasks — out of scope here.

Goal: the Admin can export the Job Orders list and the Audit Trail to PDF and Excel, each export containing exactly what the page's current filters match. Both reuse `app/Support/TableExport.php` from workstream 1 — no new rendering path is created.

Locked decisions are numbered 1-12 in the task brief; task text below cites them inline as `(D1)`...`(D12)` for traceability. Do not revisit a locked decision, do not implement anything from "out of scope" above.

## Task 1: Shared primitives — `TableExport` landscape + cap note, nullable `AuditLogger` range, the four new routes, Wayfinder regen, test-helper relocation

**Files:** `app/Support/TableExport.php`, `resources/views/reports/layout.blade.php`, `app/Support/AuditLogger.php`, `routes/admin.php`, `tests/Pest.php`, `tests/Feature/Reports/ReportExportTest.php` (modify only), `resources/js/routes/**` (regenerated, do not hand-edit)

This task lays the groundwork Task 2 and Task 3 both build on. Do it first and verify it in isolation before touching either controller.

1. **`TableExport::pdf()` — landscape + cap-note hook (D8, D9).** Add one trailing optional parameter `bool $landscape = false`. When `true`, call `->setPaper('a4', 'landscape')` on the `Pdf::loadView(...)` instance before `->setOption('isPhpEnabled', true)` is applied (dompdf's own default is `a4`/`portrait` — no existing caller passes `$landscape`, so every current report export is byte-for-byte unaffected). Extend the `$meta` array-shape PHPDoc on BOTH `pdf()` and `xlsx()` to add `note?: string` (xlsx's copy stays "accepted for signature parity only", exactly like its existing unused `$title`/`$meta` note). `pdf()` already merges `$meta` into the view's `$data` array, so no further wiring is needed there — `$note` just becomes an available Blade variable.

2. **`reports/layout.blade.php` — render the cap note (D8).** Immediately after the existing `@isset($generatedAt, $generatedBy)` meta-line block and before `<hr class="header-rule">`, add `@isset($note)<div class="meta-line">{{ $note }}</div>@endisset`. No CSS changes. Existing report exports never set `note`, so this is inert for them.

3. **`AuditLogger::recordReportExport()` — nullable range (D7).** Change the signature from `(User $user, string $reportKey, string $format, CarbonInterface $from, CarbonInterface $to)` to `(User $user, string $reportKey, string $format, ?CarbonInterface $from, ?CarbonInterface $to)`. Build `$newValues` starting with `['report' => $reportKey, 'format' => $format]`, then `if ($from !== null) { $newValues['from'] = $from->toDateString(); }` and the same for `$to`, before passing `$newValues` as `AuditLog::create()`'s `new_values`. Do NOT add a second method — this is the one method both the existing Reports workspace callers (`ReportExportController`, which always resolves a non-null month-to-date default today) and the new Job Orders/Audit Trail callers (Task 2/3, which may pass `null`) now share. Existing callers keep passing non-null Carbon instances, so their audit rows are byte-for-byte unchanged.

4. **Four new routes in `routes/admin.php` (D1).** Inside the existing `role:admin` group, add — directly after the matching `index` route in each case, matching the existing `reports.export.*` naming convention exactly:

    ```
    Route::get('job-orders/export/pdf', [JobOrderController::class, 'exportPdf'])->name('job-orders.export.pdf');
    Route::get('job-orders/export/xlsx', [JobOrderController::class, 'exportXlsx'])->name('job-orders.export.xlsx');
    ```

    and

    ```
    Route::get('audit-trail/export/pdf', [AuditTrailController::class, 'exportPdf'])->name('audit-trail.export.pdf');
    Route::get('audit-trail/export/xlsx', [AuditTrailController::class, 'exportXlsx'])->name('audit-trail.export.xlsx');
    ```

    These controller methods don't exist yet (Task 2/3 add them) — that's fine, routes reference them by array callable, no autoload error at this stage.

5. **Regenerate Wayfinder (D12).** Run `php artisan wayfinder:generate --with-form --no-interaction` — the `--with-form` flag matters (per prior project decision: `vite.config.ts` has `formVariants: true`; the bare command silently drops every route helper's `.form()`). Confirm `resources/js/routes/admin/job-orders/export/index.ts` and `resources/js/routes/admin/audit-trail/export/index.ts` now exist, each exporting `pdf`/`xlsx`, and that `resources/js/routes/admin/job-orders/index.ts` / `.../audit-trail/index.ts` now re-export an `export` member (same shape as `resources/js/routes/admin/reports/index.ts`'s existing `export: Object.assign(exportMethod, exportMethod)`). Do not hand-edit any file under `resources/js/routes/**`.

6. **Move `readXlsxRows()` into `tests/Pest.php` (both Task 2 and Task 3's tests need it).** Cut the `readXlsxRows(string $content): array` function (and its `use OpenSpout\Reader\XLSX\Reader;` import) out of `tests/Feature/Reports/ReportExportTest.php` and into `tests/Pest.php`'s "Functions" section, verbatim — same body, same doc comment. Add `use OpenSpout\Reader\XLSX\Reader;` near Pest.php's existing top-of-file `use` statements. Leave `reportExportHeaders()` where it is (only `ReportExportTest.php` uses it). After the move, `ReportExportTest.php` must still pass unchanged — it calls the now-global `readXlsxRows()` exactly as before, just without a local definition.

Verify: `vendor/bin/pint --dirty --format agent`, `composer types:check`, `php artisan test --compact tests/Feature/Reports/ReportExportTest.php` (must still fully pass — this proves the `AuditLogger` signature widening and the helper relocation didn't regress workstream 1), `php artisan route:list --name=admin.job-orders.export --name=admin.audit-trail.export` to confirm all four new route names resolve.

## Task 2: Job Orders — filters, export, UI

**Files:** `app/Http/Requests/Admin/FilterJobOrdersRequest.php`, `app/Http/Controllers/Admin/JobOrderController.php`, `resources/js/pages/admin/JobOrders.vue`, `tests/Feature/Admin/JobOrderIndexTest.php`

### Backend

1. **`FilterJobOrdersRequest` (D3).** Add `use App\Enums\PaymentStatus;`. Extend `rules()` with `'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)]`, `'from' => ['nullable', 'date']`, `'to' => ['nullable', 'date']`.

2. **`JobOrderController` — extract the filtered query (D2, D3).** Add `private function filteredQuery(FilterJobOrdersRequest $request): Builder` containing exactly today's `index()` query chain (`whereNull('cancelled_at')`, `q` search, `status`) PLUS two new `when()` clauses using `payment_status` and the two new helper methods below, PLUS `'created_at'` added to the existing `->select([...])` column list (needed for the export's Created column; harmless extra prop on the Inertia page). Keep the existing `with([...])` and `->withAmountPaid()` calls unchanged.

    Add two private helpers used both inside `filteredQuery()` and by the export methods below:

    ```
    private function resolveFrom(FilterJobOrdersRequest $request): ?CarbonInterface
    {
        return $request->filled('from') ? $request->date('from')->startOfDay() : null;
    }

    private function resolveTo(FilterJobOrdersRequest $request): ?CarbonInterface
    {
        return $request->filled('to') ? $request->date('to')->endOfDay() : null;
    }
    ```

    Full timestamp bounds against `created_at`, never `whereDate()` (D3 — the SQLite trap `ReportBuilder`'s class docblock documents). Wire them into `filteredQuery()` as:

    ```
    ->when($this->resolveFrom($request), fn (Builder $query, CarbonInterface $from) => $query->where('created_at', '>=', $from))
    ->when($this->resolveTo($request), fn (Builder $query, CarbonInterface $to) => $query->where('created_at', '<=', $to))
    ```

    (`when()`'s second closure argument receives the truthy value itself, so `resolveFrom()`/`resolveTo()` are each called once.)

    Rewrite `index()` to call `$this->filteredQuery($request)->latest('id')->paginate(25)->withQueryString()`, keep the existing `->append('display_total')` loop, and extend the two Inertia props: `'filters' => $request->only(['q', 'status', 'payment_status', 'from', 'to'])` and a new `'paymentStatuses' => collect(PaymentStatus::cases())->map(fn (PaymentStatus $status): string => $status->value)->all()` alongside the existing `statuses` prop. The page's existing behavior — cancelled orders excluded, `q`/`status` filters, 25/page — is unchanged (D3).

3. **`JobOrderController` — exports (D1, D2, D4, D6, D7, D8, D9).** Add:

    ```
    private const PDF_ROW_CAP = 1000;
    ```

    ```
    public function exportPdf(FilterJobOrdersRequest $request): Response
    public function exportXlsx(FilterJobOrdersRequest $request): StreamedResponse
    ```

    Both: resolve `$user = $request->user()`, `$from = $this->resolveFrom($request)`, `$to = $this->resolveTo($request)`, call `AuditLogger::recordReportExport($user, 'job-orders', 'pdf'|'xlsx', $from, $to)` BEFORE building any row data or touching the response (D7 — audit before output, matching `ReportExportController`'s existing ordering exactly).

    `exportPdf()`: build `$query = $this->filteredQuery($request)->latest('id')`, `$matched = $query->count()`, `$jobOrders = $query->limit(self::PDF_ROW_CAP)->get()` (count-then-limit on the same builder is safe — Eloquent's `count()` clones internally and never mutates the original query). Build `$meta` as a plain array: always `['generatedAt' => now(), 'generatedBy' => $user->name]`; add `'from' => $from, 'to' => $to` only when both are non-null; add a `'note'` key only `if ($matched > self::PDF_ROW_CAP)`, reading `'Showing '.number_format(self::PDF_ROW_CAP).' of '.number_format($matched).' — narrow the filters or use Excel.'` — tag this `if` with a `ponytail:` comment naming the 1,000 ceiling and "move to a queued export" as the upgrade path (D8). Call `TableExport::pdf('Job Orders', $this->exportColumns(), $built['rows'], $this->moneyColumnIndexes(), $meta, $built['totalRow'], 'job-orders_'.now()->toDateString(), landscape: true)` (D9 — 11 columns, landscape) where `$built = $this->buildTableRows($jobOrders)`.

    `exportXlsx()`: `$jobOrders = $this->filteredQuery($request)->latest('id')->get()` (uncapped, D8), `$built = $this->buildTableRows($jobOrders)`, `return TableExport::xlsx('Job Orders', $this->exportColumns(), $built['rows'], $this->moneyColumnIndexes(), null, $built['totalRow'], 'job-orders_'.now()->toDateString())`.

    Add the row-shaping helpers (one definition feeding both formats, matching workstream 1's parity pattern):

    ```
    /** @return list<string> */
    private function exportColumns(): array
    {
        return ['Job Order', 'Customer', 'Product', 'Size', 'Status', 'Payment', 'Urgency', 'Total', 'Paid', 'Balance', 'Created'];
    }

    /** @return list<int> */
    private function moneyColumnIndexes(): array
    {
        return [7, 8, 9]; // Total, Paid, Balance
    }

    /**
     * @param  Collection<int, JobOrder>  $jobOrders
     * @return array{rows: list<list<mixed>>, totalRow: list<mixed>}
     */
    private function buildTableRows(Collection $jobOrders): array
    {
        $moneyIndexes = $this->moneyColumnIndexes();
        $totals = array_fill_keys($moneyIndexes, 0.0);

        $rows = $jobOrders->map(function (JobOrder $jobOrder) use ($moneyIndexes, &$totals): array {
            $values = $this->exportRowValues($jobOrder);
            foreach ($moneyIndexes as $index) {
                $totals[$index] += (float) ($values[$index] ?? 0);
            }
            return $values;
        })->all();

        $totalRow = array_map(
            fn (int $index): mixed => match (true) {
                $index === 0 => 'Total',
                in_array($index, $moneyIndexes, true) => $totals[$index],
                default => null,
            },
            array_keys($this->exportColumns())
        );

        return ['rows' => $rows, 'totalRow' => $totalRow];
    }

    /** @return list<mixed> */
    private function exportRowValues(JobOrder $jobOrder): array
    {
        $paid = (float) ($jobOrder->amount_paid ?? 0);
        $total = $jobOrder->display_total; // accessor works without ->append()

        return [
            $jobOrder->number ?? '—',
            $jobOrder->queueEntry?->customer?->name ?? '—',
            $jobOrder->pricingEntry?->name ?? $jobOrder->description,
            $this->sizeLabel($jobOrder),
            Str::headline($jobOrder->status->value),
            Str::headline($jobOrder->payment_status->value),
            $jobOrder->is_rush ? 'Rush' : 'Normal',
            $total,
            $paid,
            $total === null ? null : max(0.0, $total - $paid),
            $jobOrder->created_at,
        ];
    }

    private function sizeLabel(JobOrder $jobOrder): string
    {
        if ($jobOrder->width_ft !== null && $jobOrder->height_ft !== null) {
            $quantity = $jobOrder->quantity ?? 1;
            return "{$jobOrder->width_ft}ft × {$jobOrder->height_ft}ft × {$quantity}";
        }
        if ($jobOrder->quantity) {
            return (string) $jobOrder->quantity;
        }
        return '—';
    }
    ```

    `sizeLabel()` mirrors `JobOrders.vue`'s existing `sizeLabel()` JS function exactly, so the exported Size column matches what the page already shows. The Balance formula mirrors `lib/jobOrders.ts`'s `balanceLabel()` (never negative, `null` when there's no total yet — `TableExport`/`reports/table.blade.php` already render a `null` cell as `—` even in a money column). `created_at` is already a `CarbonInterface` via Eloquent's datetime cast — no `Carbon::parse()` needed, unlike `ReportExportController`'s raw-array rows. No boolean and no raw snake_case enum value reaches `TableExport` (`Str::headline()` on both enum `->value`s, `Rush`/`Normal` for `is_rush`) — `TableExport`'s class docblock contract (D4).

    Add imports: `App\Enums\PaymentStatus`, `App\Support\AuditLogger`, `App\Support\TableExport`, `Carbon\CarbonInterface`, `Illuminate\Support\Collection`, `Illuminate\Support\Str`, `Symfony\Component\HttpFoundation\Response`, `Symfony\Component\HttpFoundation\StreamedResponse`.

### Frontend (D10, D11)

4. **`JobOrders.vue`.** Extend the props type: `filters: { q?: string; status?: string; payment_status?: string; from?: string; to?: string }`, add `paymentStatuses: string[]`. Add `import { paymentStatusLabel } from '@/lib/jobOrders';` to the existing `@/lib/jobOrders` import line. Add refs `selectedPaymentStatus = ref(props.filters.payment_status ?? ALL)`, `fromDate = ref(props.filters.from ?? '')`, `toDate = ref(props.filters.to ?? '')`. Add a `paymentStatusOptions` computed, same shape as the existing `statusOptions` computed but mapping `props.paymentStatuses` through `paymentStatusLabel()`. Extend `visit()`'s query-param object with `payment_status`/`from`/`to` (same `!== ALL`/truthy-string guards as the existing `status`/`q` entries). Add `watch(selectedPaymentStatus, () => visit())`, `watch(fromDate, () => visit())`, `watch(toDate, () => visit())` (status already does this; search is debounced separately — leave both patterns as-is).

    Add a `clearFilters()` function resetting `searchTerm`, `selectedStatus`, `selectedPaymentStatus`, `fromDate`, `toDate` to their empty/`ALL` defaults and calling `visit()` once (D11 — one-click reset).

    In the template's Filters `<Card>`, change the grid to `grid gap-4 sm:grid-cols-2 lg:grid-cols-3` and add, after the existing Status field: a Payment Status field using `SearchableSelect` with `paymentStatusOptions` (D11 — "same style as the existing status filter"), a From field (`Label` + `Input id="job-order-from-filter" v-model="fromDate" type="date"`), a To field (same pattern, `toDate`), and a final grid cell with a `Button type="button" variant="secondary" data-test="clear-job-order-filters-button" @click="clearFilters"` reading "Clear filters". Import `Button` from `@/components/ui/button` (not currently imported in this file).

    Add export buttons to `PageHeader`'s `#actions` slot (convert the current self-closing `<PageHeader title=... description=... />` into a block with `<template #actions>`), mirroring `ReportsWorkspace.vue`'s pattern exactly: plain `<a>` links via `Button as="a" variant="outline"`, `FileText`/`FileSpreadsheet` icons from `@lucide/vue` (merge into the existing `Filter, Search` import), `data-test="export-job-orders-pdf-button"` / `"export-job-orders-xlsx-button"`. Import `import { pdf as jobOrdersExportPdf, xlsx as jobOrdersExportXlsx } from '@/routes/admin/job-orders/export';`. Build the href from the CURRENT filter state (D10) via a computed, not from `props.filters` (which only updates after a round-trip and would lag one keystroke/selection behind the visible filter state):

    ```ts
    const exportQuery = computed(() => ({
        ...(searchTerm.value.trim() !== ''
            ? { q: searchTerm.value.trim() }
            : {}),
        ...(selectedStatus.value !== ALL
            ? { status: selectedStatus.value }
            : {}),
        ...(selectedPaymentStatus.value !== ALL
            ? { payment_status: selectedPaymentStatus.value }
            : {}),
        ...(fromDate.value ? { from: fromDate.value } : {}),
        ...(toDate.value ? { to: toDate.value } : {}),
    }));
    const exportPdfUrl = computed(() =>
        jobOrdersExportPdf.url({ query: exportQuery.value }),
    );
    const exportXlsxUrl = computed(() =>
        jobOrdersExportXlsx.url({ query: exportQuery.value }),
    );
    ```

    These routes take no URL segment (no `{id}`), so `.url({ query })` is the full signature — same shape as `resources/js/pages/admin/Reports.vue`'s `reportsExportPdf.url(key, { query })` minus the leading `key` arg.

    User-friendly check: all 6 filter-card cells and the two export links are native/keyboard-reachable elements already; confirm the grid doesn't overflow at 375px (stacks to 1 column) and both themes use existing semantic tokens only (no new raw colors introduced).

### Tests (D on all — see "Tests (locked)" in the task brief)

5. **`tests/Feature/Admin/JobOrderIndexTest.php`.** Add `use App\Enums\JobOrderStatus; use App\Enums\PaymentStatus; use App\Models\AuditLog;` to the existing `use` block. Every xlsx-exporting test MUST call `$this->skipUnlessZipAvailable();` first (TestCase convention — PDF-only tests don't need it). Add:
    - `the payment_status filter narrows results` — two job orders with different `payment_status` values, filter by one, assert only the matching one is in `jobOrders.data`.
    - `the from and to filters narrow results by created_at` — three job orders at distinct `created_at` timestamps (one inside, two outside an explicit `from`/`to` window), assert only the in-range one is returned.
    - `the xlsx export honours the active filters` — one matching + one excluded job order (e.g. by `status`), export xlsx with that filter, assert `readXlsxRows()` has exactly 3 rows (header + 1 + Total) and the data row's first cell is the matching job order's `number`.
    - `exporting job orders to pdf returns a real PDF and audits exactly one row` — assert `AuditLog::where('action','report_exported')->count()` is 0 before, `assertHeader('Content-Type','application/pdf')`, then exactly 1 after, with `new_values['report'] === 'job-orders'` and `new_values['format'] === 'pdf'`.
    - `exporting job orders to xlsx audits exactly one row, and viewing the index writes none` — visit `index` first and assert 0 audit rows, then export xlsx and assert exactly 1.
    - `every other role gets 403 on the job orders export routes and writes no audit row` — same `->with([...])` role list as the existing index 403 test, hit both `admin.job-orders.export.pdf` and `.xlsx`, assert both forbidden and 0 audit rows.
    - `the xlsx export is uncapped past the 25-row page size` — `JobOrder::factory()->count(30)->create()`, export xlsx with no filters, assert `readXlsxRows()` has exactly 32 rows (header + 30 + Total).
    - `exported label cells are display-ready, not raw enum values or booleans` — one `JobOrder::factory()->rush()->create(['status' => JobOrderStatus::ForProduction->value])`, export xlsx, assert the Status cell (index 4) is `'For Production'` and the Urgency cell (index 6) is `'Rush'`.

Verify: `php artisan test --compact tests/Feature/Admin/JobOrderIndexTest.php`, `vendor/bin/pint --dirty --format agent`, `composer types:check`, `npm run check:fix`, `npm run types:check`.

## Task 3: Audit Trail — export, actions list, UI

**Files:** `app/Http/Controllers/Admin/AuditTrailController.php`, `resources/js/pages/admin/AuditTrail.vue`, `tests/Feature/Admin/AuditTrailTest.php`

`FilterAuditTrailRequest` needs no changes — `action` is already validated as a free-form `nullable|string`, and no new filter fields are being added to this page (only Job Orders got new filters, D3).

### Backend

1. **`AuditTrailController` — extract the filtered query.** Add `private function filteredQuery(FilterAuditTrailRequest $request): Builder` containing exactly today's `index()` query chain (`with('user:id,name,email,role')`, `user`/`action`/`from`/`to` `when()` clauses — leave the existing `whereDate()` semantics for `from`/`to` untouched; that's pre-existing behavior for an already-shipped filter and D3's full-timestamp-bound requirement is scoped to Job Orders' NEW filters only). Rewrite `index()` to call `$this->filteredQuery($request)->latest('created_at')->paginate(25)->withQueryString()`. Add `'report_exported'` to the end of the hard-coded `'actions'` array (D6) — it becomes `['created', 'updated', 'deleted', 'login', 'logout', 'failed_login', 'lockout', 'report_exported']`.

2. **`AuditTrailController` — exports (D1, D5, D7, D8, D9).** Add `private const PDF_ROW_CAP = 1000;` and:

    ```
    public function exportPdf(FilterAuditTrailRequest $request): Response
    public function exportXlsx(FilterAuditTrailRequest $request): StreamedResponse
    ```

    Both: `$user = $request->user()`, `$from = $request->filled('from') ? $request->date('from') : null`, `$to = $request->filled('to') ? $request->date('to') : null`, then `AuditLogger::recordReportExport($user, 'audit-trail', 'pdf'|'xlsx', $from, $to)` BEFORE any query runs (D7).

    **Important ordering nuance:** this audit write lands in the very `audit_trail` table the export then reads, and it sorts newest-first — so an UNFILTERED export of Audit Trail legitimately includes its own just-written `report_exported` row as the first data row. This is correct, not a bug (D6 exists precisely so that row is filterable); account for it when writing tests (prefer asserting counts under an `action` filter that excludes `report_exported`, e.g. `action=login`).

    `exportPdf()`: `$query = $this->filteredQuery($request)->latest('created_at')`, `$matched = $query->count()`, `$entries = $query->limit(self::PDF_ROW_CAP)->get()`. Build `$meta` the same way as Task 2 (`generatedAt`/`generatedBy` always; `note` only when `$matched > self::PDF_ROW_CAP`, same `ponytail:` comment and message format). `return TableExport::pdf('Audit Trail', $this->exportColumns(), $this->exportRows($entries), [], $meta, null, 'audit-trail_'.now()->toDateString(), landscape: true)` — empty money-column list and `null` totalRow (D5 — no money columns, no Total row), landscape for the 7-wide table (D9).

    `exportXlsx()`: `$entries = $this->filteredQuery($request)->latest('created_at')->get()` (uncapped), `return TableExport::xlsx('Audit Trail', $this->exportColumns(), $this->exportRows($entries), [], null, null, 'audit-trail_'.now()->toDateString())`.

    Add the row-shaping helpers (D5 — "When, User, Role, Action, Record, Changes, IP"):

    ```
    /** @return list<string> */
    private function exportColumns(): array
    {
        return ['When', 'User', 'Role', 'Action', 'Record', 'Changes', 'IP'];
    }

    /**
     * @param  Collection<int, AuditLog>  $entries
     * @return list<list<string>>
     */
    private function exportRows(Collection $entries): array
    {
        return $entries->map(fn (AuditLog $entry): array => [
            $entry->created_at?->format('M j, Y g:i A') ?? '—', // string, not Carbon -- TableExport would otherwise strip the time
            $entry->user?->name ?? '—',
            $entry->user !== null ? Str::headline($entry->user->role->value) : '—',
            Str::headline($entry->action),
            $this->record($entry),
            $this->formatChanges($entry->old_values, $entry->new_values),
            $entry->ip_address ?? '—',
        ])->all();
    }

    private function record(AuditLog $entry): string
    {
        if ($entry->auditable_type === null) {
            return '—';
        }
        $class = class_basename($entry->auditable_type); // same helper DashboardController::recentActivity() already uses
        return $entry->auditable_id !== null ? "{$class} #{$entry->auditable_id}" : $class;
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function formatChanges(?array $old, ?array $new): string
    {
        if ($old === null && $new === null) {
            return '—'; // auth events and report_exported's old_values side carry nothing
        }
        if ($old !== null && $new !== null) {
            $parts = [];
            foreach ($new as $key => $value) {
                $parts[] = "{$key}: {$this->stringifyValue($old[$key] ?? null)} → {$this->stringifyValue($value)}";
            }
            return implode('; ', $parts); // e.g. "status: intake → assigned; total_amount: 0 → 1500"
        }
        return $this->joinValues($new ?? $old); // 'created' (new only) or 'deleted' (old only)
    }

    /** @param  array<string, mixed>  $values */
    private function joinValues(array $values): string
    {
        $parts = [];
        foreach ($values as $key => $value) {
            $parts[] = "{$key}: {$this->stringifyValue($value)}";
        }
        return implode('; ', $parts);
    }

    private function stringifyValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => (string) json_encode($value),
            default => (string) $value,
        };
    }
    ```

    `AuditObserver::updated()` already stores only the changed keys on both sides, so `old`/`new` always share the same key set in the "both present" branch. `created`/`report_exported` (old `null`, new present) and `deleted` (old present, new `null`) both fall through to `joinValues()`.

    Add imports: `App\Support\AuditLogger`, `App\Support\TableExport`, `Illuminate\Database\Eloquent\Builder`, `Illuminate\Support\Collection`, `Illuminate\Support\Str`, `Symfony\Component\HttpFoundation\Response`, `Symfony\Component\HttpFoundation\StreamedResponse`.

### Frontend (D10)

3. **`AuditTrail.vue`.** Add `FileSpreadsheet, FileText` to the existing `@lucide/vue` import. Add `import { pdf as auditTrailExportPdf, xlsx as auditTrailExportXlsx } from '@/routes/admin/audit-trail/export';`. Add a computed `exportQuery` built from the page's existing local refs (`selectedUser`, `selectedAction`, `fromDate`, `toDate` — same `!== ALL`/truthy guards `visit()` already uses) and `exportPdfUrl`/`exportXlsxUrl` computeds calling `.url({ query: exportQuery.value })`, same pattern as Task 2. Convert the self-closing `<PageHeader title="Audit Trail" description="..." />` into a block with a `<template #actions>` containing the two `Button as="a" variant="outline"` export links (`data-test="export-audit-trail-pdf-button"` / `"export-audit-trail-xlsx-button"`), same icon/markup pattern as Task 2 and `ReportsWorkspace.vue`. `Button` is already imported in this file.

### Tests

4. **`tests/Feature/Admin/AuditTrailTest.php`.** Add `use App\Models\AuditLog;`. Every xlsx-exporting test calls `$this->skipUnlessZipAvailable();` first. Add:
    - `the xlsx export honours the active filters` — insert one `login` row and one `lockout` row via `DB::table('audit_trail')->insert([...])` (existing file convention), export xlsx filtered to `action=login`, assert `readXlsxRows()` has exactly 2 rows (header + the 1 matching row — no Total row for Audit Trail, and the export's own `report_exported` self-row doesn't match the `login` filter so it's excluded).
    - `exporting audit trail to pdf returns a real PDF and audits exactly one row, and viewing the index writes none` — visit `index` first and assert 0 `report_exported` rows, export pdf, assert `Content-Type: application/pdf` and exactly 1 audit row with `new_values['report'] === 'audit-trail'` / `['format'] === 'pdf'`.
    - `every other role gets 403 on the audit trail export routes and writes no audit row` — same role list/pattern as Task 2's equivalent test.
    - `the xlsx export is uncapped past the 25-row page size` — insert 30 `login` rows via a loop of `DB::table('audit_trail')->insert([...])`, export xlsx filtered to `action=login`, assert exactly 31 rows (header + 30, no Total row, self-row excluded by the filter).
    - `exported label cells are display-ready -- headlined role and action, not raw snake_case` — insert one `failed_login` row for an admin user, export xlsx filtered to `action=failed_login`, assert the Role cell (index 2) is `'Admin'` and the Action cell (index 3) is `'Failed Login'`.

Verify: `php artisan test --compact tests/Feature/Admin/AuditTrailTest.php`, `vendor/bin/pint --dirty --format agent`, `composer types:check`, `npm run check:fix`, `npm run types:check`.

## Verification

- `php artisan test --compact tests/Feature/Admin tests/Feature/Reports` — all existing and new tests pass (includes the Task 1 `ReportExportTest.php` regression check and both new Admin test files).
- `vendor/bin/pint --dirty --format agent` — run after every PHP file touched across all three tasks.
- `composer types:check` — Larastan level 7. The baseline already has 21 pre-existing errors in unrelated files; the bar is zero new errors and zero errors in any file this task creates or modifies (`TableExport.php`, `AuditLogger.php`, `JobOrderController.php`, `FilterJobOrdersRequest.php`, `AuditTrailController.php`).
- `npm run check:fix` then `npm run types:check` — Vue/TS changes in `JobOrders.vue` and `AuditTrail.vue`.

Not required to close this task, but worth doing before merging (per `CLAUDE.md`'s "UI Changes Require a User-Friendly Check" and the task brief's note that the orchestrator will do the real-browser pass): open both pages, apply filters, download both formats for each, and confirm the Job Orders PDF/Excel row counts and labels match the on-screen table, the Audit Trail export includes a legible Changes column, both are landscape, and each download added exactly one Audit Trail row.
