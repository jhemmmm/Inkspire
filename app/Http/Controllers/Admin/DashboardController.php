<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountsReceivableCollectionStatus;
use App\Enums\AccountsReceivableStatus;
use App\Enums\JobOrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountsReceivable;
use App\Models\AuditLog;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The Admin's landing page.
     *
     * Two questions, in this order: what is waiting on a decision only an
     * Admin can make, and is the shop healthy. The approval queues come
     * first because nothing else on this page is blocked on the Admin --
     * a credit request nobody approves stops a customer paying.
     *
     * Every count here re-uses the exact predicate of the page it links to,
     * so a tile reading 3 always opens a list of 3. Where that predicate is
     * not expressible in SQL alone -- write-offs, whose collection status is
     * derived from aging rather than stored -- the same PHP re-check runs
     * here too.
     */
    public function index(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'attention' => [
                'creditRequests' => $this->pendingCreditRequests(),
                'writeOffRequests' => $this->pendingWriteOffRequests(),
                'lockedDesigns' => $this->lockedDesignFiles(),
                'lockedAccounts' => $this->lockedOutAccounts(),
            ],
            'shop' => $this->shopHealth(),
            'recentActivity' => $this->recentActivity(),
        ]);
    }

    /**
     * Mirrors CreditApprovalController@index.
     */
    private function pendingCreditRequests(): int
    {
        return AccountsReceivable::query()
            ->where('status', AccountsReceivableStatus::PendingApproval->value)
            ->count();
    }

    /**
     * Mirrors WriteOffApprovalController@index, including its PHP-side
     * re-check.
     *
     * The SQL filter alone only excludes entries whose STORED collection
     * status is closed. Collection status is derived from aging now, so an
     * entry settled since the request was raised reads Paid without that
     * column ever being rewritten. Counting on SQL alone would show the
     * Admin a queue longer than the page they click through to.
     */
    private function pendingWriteOffRequests(): int
    {
        return AccountsReceivable::query()
            ->whereNotNull('write_off_requested_at')
            ->whereNotIn('collection_status', [
                AccountsReceivableCollectionStatus::Paid->value,
                AccountsReceivableCollectionStatus::WrittenOff->value,
            ])
            ->with(['jobOrder:id,total_amount', 'jobOrder.transactions:id,job_order_id,amount,status'])
            ->get(['id', 'job_order_id', 'collection_status', 'due_at'])
            ->reject(fn (AccountsReceivable $accountsReceivable): bool => $accountsReceivable->isClosed())
            ->count();
    }

    /**
     * Mirrors DesignFileController@index.
     */
    private function lockedDesignFiles(): int
    {
        return JobOrder::query()
            ->whereHas('designFile', fn (Builder $query) => $query->whereNotNull('locked_at'))
            ->count();
    }

    /**
     * Accounts currently serving a lockout, which only an Admin can see
     * the scale of. `locked_until` in the past is a lockout that has
     * already expired on its own.
     */
    private function lockedOutAccounts(): int
    {
        return User::query()->where('locked_until', '>', now())->count();
    }

    /**
     * @return array{
     *     queuedToday: int,
     *     inProduction: int,
     *     unpaidJobOrders: int,
     *     outstandingAmount: float,
     *     activeStaff: int,
     *     totalStaff: int
     * }
     */
    private function shopHealth(): array
    {
        $unpaid = $this->unpaidBalances();

        return [
            'queuedToday' => QueueEntry::query()
                ->whereDate('queue_date', QueueEntry::currentBusinessDate())
                ->count(),
            'inProduction' => JobOrder::query()
                ->whereIn('status', [
                    JobOrderStatus::ForProduction->value,
                    JobOrderStatus::Printing->value,
                    JobOrderStatus::QualityCheck->value,
                ])
                ->whereNull('cancelled_at')
                ->count(),
            'unpaidJobOrders' => $unpaid->count(),
            'outstandingAmount' => round($unpaid->sum(), 2),
            'activeStaff' => User::query()->where('is_active', true)->count(),
            'totalStaff' => User::query()->count(),
        ];
    }

    /**
     * Every still-owed balance, using JobOrder::outstandingBalance()'s
     * definition (D-16: total_amount minus Completed transactions).
     *
     * Summed in PHP off one aggregated query rather than re-deriving the
     * arithmetic in SQL, so this figure cannot drift from the one the
     * Cashier and Accounting screens show. Only positive balances count --
     * an overpaid job order is not negative debt the shop is owed. A
     * written-off balance is excluded up front -- it's a recognized loss
     * the shop has already written off, not money still outstanding --
     * mirroring the same exclusion pendingWriteOffRequests() already
     * applies to its own collection status.
     *
     * @return Collection<int, float>
     */
    private function unpaidBalances(): Collection
    {
        return JobOrder::query()
            ->whereNull('cancelled_at')
            ->whereNotNull('total_amount')
            ->where('payment_status', '!=', PaymentStatus::WrittenOff->value)
            ->withSum(
                ['transactions as completed_amount' => fn (Builder $query) => $query->where('status', TransactionStatus::Completed->value)],
                'amount',
            )
            ->get(['id', 'total_amount'])
            ->map(fn (JobOrder $jobOrder): float => round((float) $jobOrder->total_amount - (float) $jobOrder->completed_amount, 2))
            ->filter(fn (float $balance): bool => $balance > 0)
            ->values();
    }

    /**
     * The last few audit entries, as a window onto everything the other
     * portals are doing. Capped low on purpose -- the full, filterable
     * record is one click away on the Audit Trail page.
     *
     * @return array<int, array{id: int, action: string, entity: string, user: ?string, created_at: ?string}>
     */
    private function recentActivity(): array
    {
        return AuditLog::query()
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(8)
            ->get(['id', 'user_id', 'action', 'auditable_type', 'created_at'])
            ->map(fn (AuditLog $entry): array => [
                'id' => $entry->id,
                'action' => $entry->action,
                'entity' => class_basename((string) $entry->auditable_type),
                'user' => $entry->user?->name,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
