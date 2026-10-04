<?php

namespace App\Services\Reports;

use App\Enums\JobOrderStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\AccountsReceivable;
use App\Models\Expense;
use App\Models\JobOrder;
use App\Models\ProductionLog;
use App\Models\Transaction;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The one query implementation shared by ReportController::index() (capped
 * at 100 rows) and Plan 08-04's export actions (uncapped). Every date-range
 * filter compares full timestamp bounds (`>= $from`, `<= $to`), never a
 * bare equality, to avoid the project's documented SQLite `whereDate()`
 * trap (Pitfall 6).
 *
 * Each public method first turns its range into window(): the shop days it
 * covers, once as `$utc` instants and once as `$days` on the shop's clock.
 * `$utc` compares directly against the UTC timestamps the database stores;
 * `$days` is for the date-only `expense_date` column and the chart's labels.
 */
final class ReportBuilder
{
    /**
     * The full, uncapped row set for a rendered report. Returns no rows for
     * `financial-summary`; see summary() for that report's figure block.
     *
     * @return Collection<int, covariant array<string, mixed>>
     */
    public function rows(string $key, CarbonInterface $from, CarbonInterface $to, string $search = ''): Collection
    {
        ['utc' => $utc, 'days' => $days] = $this->window($from, $to);

        $rows = match ($key) {
            'sales' => $this->salesRows($utc),
            'cancellations' => $this->cancellationsRows($utc),
            'production-status' => $this->productionStatusRows($utc),
            'expenses' => $this->expensesRows($days),
            default => collect(),
        };

        $search = Str::lower(trim($search));

        if ($search === '') {
            return $rows;
        }

        $fields = match ($key) {
            'sales' => ['job_order', 'customer', 'type', 'method'],
            'cancellations' => ['job_order', 'customer', 'payment_status'],
            'production-status' => ['job_order', 'customer', 'product', 'stage', 'urgency'],
            'expenses' => ['category', 'description', 'recorded_by', 'status'],
            default => [],
        };

        return $rows->filter(function (array $row) use ($fields, $search): bool {
            foreach ($fields as $field) {
                $value = match ($field) {
                    'urgency' => $row[$field] ? 'Rush' : 'Normal',
                    'status' => $row[$field] ?? 'Active',
                    'type', 'method', 'payment_status', 'stage' => Str::headline($row[$field] ?? ''),
                    default => $row[$field] ?? '',
                };

                if (str_contains(Str::lower((string) $value), $search)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * The financial/profit figure block (RPT-01/RPT-04's Summary of Sales &
     * Expenses) -- D-08/D-09/D-10. `write_off_total` is disclosed but is
     * never subtracted from `result` (D-09).
     *
     * @return array{job_sales: float, cancellation_fees: float, revenue_total: float, expenses_total: float, result: float, write_off_total: float}
     */
    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        ['utc' => $utc, 'days' => $days] = $this->window($from, $to);

        $jobSales = (float) Transaction::query()
            ->where('status', TransactionStatus::Completed->value)
            ->whereIn('type', [
                TransactionType::DownPayment->value,
                TransactionType::BalancePayment->value,
                TransactionType::FullPayment->value,
            ])
            ->whereBetween('confirmed_at', $utc)
            ->sum('amount');

        $cancellationFees = (float) Transaction::query()
            ->where('status', TransactionStatus::Completed->value)
            ->where('type', TransactionType::CancellationFee->value)
            ->whereBetween('confirmed_at', $utc)
            ->sum('amount');

        $revenueTotal = round($jobSales + $cancellationFees, 2);
        $expensesTotal = round((float) Expense::query()->active()->whereBetween('expense_date', $days)->sum('amount'), 2);

        // Reuses JobOrder::outstandingBalance() -- never re-derive balance
        // math inline (RESEARCH.md's explicit anti-pattern warning).
        $writeOffTotal = AccountsReceivable::query()
            ->whereBetween('written_off_at', $utc)
            ->with('jobOrder')
            ->get()
            ->sum(fn (AccountsReceivable $accountsReceivable): float => $accountsReceivable->jobOrder->outstandingBalance());

        return [
            'job_sales' => round($jobSales, 2),
            'cancellation_fees' => round($cancellationFees, 2),
            'revenue_total' => $revenueTotal,
            'expenses_total' => $expensesTotal,
            'result' => round($revenueTotal - $expensesTotal, 2),
            'write_off_total' => round($writeOffTotal, 2),
        ];
    }

    /**
     * The chart drawn above a report, built from the report's FULL row set --
     * never the 100 rows the table renders, for the same reason the amount
     * total is not.
     *
     * Sales, cancellations and the financial summary are trends over the
     * range. Expenses break down by category and production by stage,
     * because where the money and the work sit is the question those two
     * reports answer.
     *
     * @param  Collection<int, covariant array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function chart(string $key, Collection $rows, CarbonInterface $from, CarbonInterface $to): array
    {
        ['utc' => $utc, 'days' => $days] = $this->window($from, $to);

        return match ($key) {
            'sales' => $this->trend('Sales', 'money', $days, [
                'Sales' => $rows->groupBy('date')->map(fn (Collection $day): float => (float) $day->sum('amount'))->all(),
            ]),
            'cancellations' => $this->trend('Cancellations', 'count', $days, [
                'Cancellations' => $rows->countBy('date')->all(),
            ]),
            // The board's Start / Done steps, then how an order left.
            'production-status' => $this->breakdown('Jobs by stage', 'count', [
                'For Production' => $rows->where('stage', JobOrderStatus::ForProduction->value)->count(),
                'Printing' => $rows->where('stage', JobOrderStatus::Printing->value)->count(),
                'Ready for Pickup' => $rows->where('stage', JobOrderStatus::ReadyForPickup->value)->count(),
                'Released' => $rows->where('stage', JobOrder::DISPLAY_RELEASED)->count(),
                'Cancelled' => $rows->where('stage', JobOrder::DISPLAY_CANCELLED)->count(),
            ]),
            'expenses' => $this->breakdown('Expenses by category', 'money', $rows
                ->whereNull('status')
                ->groupBy('category')
                ->map(fn (Collection $category): float => (float) $category->sum('amount'))
                ->sortDesc()
                ->all()),
            'financial-summary' => $this->trend('Revenue and expenses', 'money', $days, [
                'Revenue' => $this->dailyRevenue($utc),
                'Expenses' => Expense::query()
                    ->active()
                    ->whereBetween('expense_date', $days)
                    ->get(['expense_date', 'amount'])
                    ->groupBy(fn (Expense $expense): string => $expense->expense_date->toDateString())
                    ->map(fn (Collection $day): float => (float) $day->sum('amount'))
                    ->all(),
            ]),
            default => $this->breakdown('', 'count', []),
        };
    }

    /**
     * The dashboards' cash-flow chart: the Financial Summary's revenue and
     * expenses over the last 14 shop days, today included.
     *
     * @return array<string, mixed>
     */
    public function cashFlow(): array
    {
        $today = BusinessTime::now();

        return $this->chart('financial-summary', collect(), $today->subDays(13), $today);
    }

    /**
     * Every completed payment and cancellation fee confirmed in the range,
     * totalled per day -- the same money summary() counts as revenue.
     *
     * @param  array{CarbonImmutable, CarbonImmutable}  $utc
     * @return array<array-key, float>
     */
    private function dailyRevenue(array $utc): array
    {
        return Transaction::query()
            ->where('status', TransactionStatus::Completed->value)
            ->whereIn('type', [
                TransactionType::DownPayment->value,
                TransactionType::BalancePayment->value,
                TransactionType::FullPayment->value,
                TransactionType::CancellationFee->value,
            ])
            ->whereBetween('confirmed_at', $utc)
            ->get(['confirmed_at', 'amount'])
            ->groupBy(fn (Transaction $transaction): string => BusinessTime::local($transaction->confirmed_at)->toDateString())
            ->map(fn (Collection $day): float => (float) $day->sum('amount'))
            ->all();
    }

    /**
     * The shop days [$from, $to] falls on, in the two forms the queries
     * below need: `utc` instants for stored timestamps, and `days` on the
     * shop's own clock for the date-only `expense_date` column (which holds
     * the shop's date with no timezone) and for the chart's day labels.
     *
     * Both are built here, once, so nothing below converts between zones
     * and no query has to remember which one its column wants.
     *
     * @return array{utc: array{CarbonImmutable, CarbonImmutable}, days: array{CarbonImmutable, CarbonImmutable}}
     */
    private function window(CarbonInterface $from, CarbonInterface $to): array
    {
        return [
            'utc' => [BusinessTime::utcStartOfDay($from), BusinessTime::utcEndOfDay($to)],
            'days' => [BusinessTime::local($from)->startOfDay(), BusinessTime::local($to)->endOfDay()],
        ];
    }

    /**
     * Lays each series' daily totals out over every day of the range,
     * zero-filled so a quiet day still shows as a gap rather than vanishing.
     *
     * ponytail: day or month buckets only -- past ~3 months a day per bar is
     * too thin to read, so it switches to months. Add weekly buckets if a
     * 3-to-12-month range ever needs finer grain.
     *
     * @param  array{CarbonImmutable, CarbonImmutable}  $days
     * @param  array<string, array<array-key, float|int>>  $series  series name => [Y-m-d => total]
     * @return array{type: string, title: string, format: string, labels: list<string>, series: list<array{name: string, values: list<float>}>}
     */
    private function trend(string $subject, string $format, array $days, array $series): array
    {
        [$from, $to] = $days;
        $monthly = $from->diffInDays($to) > 93;
        $bucketFormat = $monthly ? 'Y-m' : 'Y-m-d';

        $labels = [];

        foreach (CarbonPeriod::create($monthly ? $from->copy()->startOfMonth() : $from->copy()->startOfDay(), $monthly ? '1 month' : '1 day', $to) as $date) {
            $labels[$date->format($bucketFormat)] = $date->format($monthly ? 'M Y' : 'M j');
        }

        $payload = [];

        foreach ($series as $name => $dailyTotals) {
            $bucketed = [];

            foreach ($dailyTotals as $date => $total) {
                $bucket = $monthly ? substr((string) $date, 0, 7) : (string) $date;
                $bucketed[$bucket] = ($bucketed[$bucket] ?? 0) + $total;
            }

            $payload[] = [
                'name' => $name,
                'values' => array_map(fn (string $bucket): float => round((float) ($bucketed[$bucket] ?? 0), 2), array_keys($labels)),
            ];
        }

        return [
            'type' => 'trend',
            'title' => $subject.($monthly ? ' by month' : ' by day'),
            'format' => $format,
            'labels' => array_values($labels),
            'series' => $payload,
        ];
    }

    /**
     * @param  array<array-key, float|int>  $totals  label => value, already in display order
     * @return array{type: string, title: string, format: string, items: list<array{label: string, value: float}>}
     */
    private function breakdown(string $title, string $format, array $totals): array
    {
        $items = [];

        foreach ($totals as $label => $value) {
            $items[] = ['label' => (string) $label, 'value' => round((float) $value, 2)];
        }

        return [
            'type' => 'breakdown',
            'title' => $title,
            'format' => $format,
            'items' => $items,
        ];
    }

    /**
     * @param  array{CarbonImmutable, CarbonImmutable}  $utc
     * @return Collection<int, covariant array<string, mixed>>
     */
    private function salesRows(array $utc): Collection
    {
        return Transaction::query()
            ->where('status', TransactionStatus::Completed->value)
            ->whereIn('type', [
                TransactionType::DownPayment->value,
                TransactionType::BalancePayment->value,
                TransactionType::FullPayment->value,
            ])
            ->whereBetween('confirmed_at', $utc)
            ->with(['jobOrder:id,number,queue_entry_id', 'jobOrder.queueEntry.customer:id,name'])
            ->orderByDesc('confirmed_at')
            ->get(['id', 'job_order_id', 'type', 'payment_method', 'amount', 'confirmed_at'])
            ->map(fn (Transaction $transaction): array => [
                'date' => BusinessTime::local($transaction->confirmed_at)->toDateString(),
                'job_order' => $transaction->jobOrder->number,
                'customer' => $transaction->jobOrder->queueEntry?->customer?->name,
                'type' => $transaction->type->value,
                'method' => $transaction->payment_method->value,
                'amount' => (float) $transaction->amount,
            ])
            ->values();
    }

    /**
     * @param  array{CarbonImmutable, CarbonImmutable}  $utc
     * @return Collection<int, covariant array<string, mixed>>
     */
    private function cancellationsRows(array $utc): Collection
    {
        return JobOrder::query()
            ->whereNotNull('cancelled_at')
            ->whereBetween('cancelled_at', $utc)
            ->with([
                'queueEntry.customer:id,name',
                'transactions' => fn ($query) => $query
                    ->where('status', TransactionStatus::Completed->value)
                    ->where('type', TransactionType::CancellationFee->value),
            ])
            ->orderByDesc('cancelled_at')
            ->get(['id', 'number', 'queue_entry_id', 'total_amount', 'payment_status', 'cancelled_at'])
            ->map(fn (JobOrder $jobOrder): array => [
                'date' => BusinessTime::local($jobOrder->cancelled_at)->toDateString(),
                'job_order' => $jobOrder->number,
                'customer' => $jobOrder->queueEntry?->customer?->name,
                'job_order_total' => (float) $jobOrder->total_amount,
                // 0 when the fee was already covered by a prior down
                // payment -- CancellationController's "no new Transaction"
                // branch, so this sum is simply empty.
                'cancellation_fee' => (float) $jobOrder->transactions->sum('amount'),
                'payment_status' => $jobOrder->payment_status->value,
            ])
            ->values();
    }

    /**
     * @param  array{CarbonImmutable, CarbonImmutable}  $utc
     * @return Collection<int, covariant array<string, mixed>>
     */
    private function productionStatusRows(array $utc): Collection
    {
        // "Due today" is a business day -- mirrors ProductionBoardController's
        // identical urgency computation.
        $endOfBusinessDay = BusinessTime::now()->endOfDay();

        // One row per job order: its first for_production log. An Undo back
        // to For Production writes another one, which must not list the
        // order twice. "First" is checked per row in range (no earlier
        // for_production log for the same order), so the cost follows the
        // range rather than the whole production history.
        return ProductionLog::query()
            ->where('to_status', JobOrderStatus::ForProduction->value)
            ->whereBetween('created_at', $utc)
            ->whereNotExists(fn (QueryBuilder $earlier) => $earlier
                ->from('production_logs as earlier')
                ->whereColumn('earlier.job_order_id', 'production_logs.job_order_id')
                ->whereColumn('earlier.id', '<', 'production_logs.id')
                ->where('earlier.to_status', JobOrderStatus::ForProduction->value))
            ->with([
                'jobOrder:id,number,description,status,due_at,queue_entry_id,released_at,cancelled_at',
                'jobOrder.queueEntry.customer:id,name',
            ])
            ->orderByDesc('created_at')
            ->get(['id', 'job_order_id', 'created_at'])
            ->map(function (ProductionLog $productionLog) use ($endOfBusinessDay): array {
                $jobOrder = $productionLog->jobOrder;

                return [
                    'job_order' => $jobOrder->number,
                    'customer' => $jobOrder->queueEntry?->customer?->name,
                    'product' => $jobOrder->description,
                    'stage' => $jobOrder->display_status,
                    // An order that has left the shop is never a rush.
                    'urgency' => $jobOrder->released_at === null
                        && $jobOrder->cancelled_at === null
                        && $jobOrder->due_at !== null
                        && $jobOrder->due_at->lessThanOrEqualTo($endOfBusinessDay),
                    'entered_production' => BusinessTime::local($productionLog->created_at),
                    'due' => $jobOrder->due_at === null ? null : BusinessTime::local($jobOrder->due_at),
                ];
            })
            ->values();
    }

    /**
     * @param  array{CarbonImmutable, CarbonImmutable}  $days
     * @return Collection<int, covariant array<string, mixed>>
     */
    private function expensesRows(array $days): Collection
    {
        return Expense::query()
            ->whereBetween('expense_date', $days)
            ->with('recordedBy:id,name')
            ->orderByDesc('expense_date')
            ->get(['id', 'expense_date', 'category', 'description', 'amount', 'recorded_by', 'voided_at'])
            ->map(fn (Expense $expense): array => [
                'date' => $expense->expense_date->toDateString(),
                'category' => $expense->category,
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'recorded_by' => $expense->recordedBy->name,
                'status' => $expense->voided_at !== null ? 'Voided' : null,
            ])
            ->values();
    }
}
