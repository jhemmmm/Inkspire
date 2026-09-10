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
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The one query implementation shared by ReportController::index() (capped
 * at 100 rows) and Plan 08-04's export actions (uncapped). Every date-range
 * filter compares full timestamp bounds (`>= $from`, `<= $to`), never a
 * bare equality, to avoid the project's documented SQLite `whereDate()`
 * trap (Pitfall 6).
 */
final class ReportBuilder
{
    /**
     * The full, uncapped row set for a rendered report. Never called for
     * `financial-summary` -- see summary() for that report's figure block.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(string $key, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $to = $to->copy()->endOfDay();

        return match ($key) {
            'sales' => $this->salesRows($from, $to),
            'cancellations' => $this->cancellationsRows($from, $to),
            'production-status' => $this->productionStatusRows($from, $to),
            'expenses' => $this->expensesRows($from, $to),
            default => collect(),
        };
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
        $to = $to->copy()->endOfDay();

        $jobSales = (float) Transaction::query()
            ->where('status', TransactionStatus::Completed->value)
            ->whereIn('type', [
                TransactionType::DownPayment->value,
                TransactionType::BalancePayment->value,
                TransactionType::FullPayment->value,
            ])
            ->whereBetween('confirmed_at', [$from, $to])
            ->sum('amount');

        $cancellationFees = (float) Transaction::query()
            ->where('status', TransactionStatus::Completed->value)
            ->where('type', TransactionType::CancellationFee->value)
            ->whereBetween('confirmed_at', [$from, $to])
            ->sum('amount');

        $revenueTotal = round($jobSales + $cancellationFees, 2);
        $expensesTotal = round((float) Expense::query()->active()->whereBetween('expense_date', [$from, $to])->sum('amount'), 2);

        // Reuses JobOrder::outstandingBalance() -- never re-derive balance
        // math inline (RESEARCH.md's explicit anti-pattern warning).
        $writeOffTotal = AccountsReceivable::query()
            ->whereBetween('written_off_at', [$from, $to])
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
     * @return Collection<int, array<string, mixed>>
     */
    private function salesRows(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Transaction::query()
            ->where('status', TransactionStatus::Completed->value)
            ->whereIn('type', [
                TransactionType::DownPayment->value,
                TransactionType::BalancePayment->value,
                TransactionType::FullPayment->value,
            ])
            ->whereBetween('confirmed_at', [$from, $to])
            ->with(['jobOrder:id,number,queue_entry_id', 'jobOrder.queueEntry.customer:id,name'])
            ->orderByDesc('confirmed_at')
            ->get(['id', 'job_order_id', 'type', 'payment_method', 'amount', 'confirmed_at'])
            ->map(fn (Transaction $transaction): array => [
                'date' => $transaction->confirmed_at->toDateString(),
                'job_order' => $transaction->jobOrder->number,
                'customer' => $transaction->jobOrder->queueEntry?->customer?->name,
                'type' => $transaction->type->value,
                'method' => $transaction->payment_method->value,
                'amount' => (float) $transaction->amount,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function cancellationsRows(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return JobOrder::query()
            ->whereNotNull('cancelled_at')
            ->whereBetween('cancelled_at', [$from, $to])
            ->with([
                'queueEntry.customer:id,name',
                'transactions' => fn ($query) => $query
                    ->where('status', TransactionStatus::Completed->value)
                    ->where('type', TransactionType::CancellationFee->value),
            ])
            ->orderByDesc('cancelled_at')
            ->get(['id', 'number', 'queue_entry_id', 'total_amount', 'payment_status', 'cancelled_at'])
            ->map(fn (JobOrder $jobOrder): array => [
                'date' => $jobOrder->cancelled_at->toDateString(),
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
     * @return Collection<int, array<string, mixed>>
     */
    private function productionStatusRows(CarbonInterface $from, CarbonInterface $to): Collection
    {
        // "Due today" is an Asia/Manila business day -- mirrors
        // ProductionBoardController's identical urgency computation.
        $endOfBusinessDay = now()->timezone('Asia/Manila')->endOfDay();

        return ProductionLog::query()
            ->where('to_status', JobOrderStatus::ForProduction->value)
            ->whereBetween('created_at', [$from, $to])
            ->with([
                'jobOrder:id,number,description,status,due_at,queue_entry_id',
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
                    'stage' => $jobOrder->status->value,
                    'urgency' => $jobOrder->due_at !== null && $jobOrder->due_at->lessThanOrEqualTo($endOfBusinessDay),
                    'entered_production' => $productionLog->created_at,
                    'due' => $jobOrder->due_at,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function expensesRows(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Expense::query()
            ->whereBetween('expense_date', [$from, $to])
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
