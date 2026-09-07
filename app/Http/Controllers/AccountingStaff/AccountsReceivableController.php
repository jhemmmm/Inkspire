<?php

namespace App\Http\Controllers\AccountingStaff;

use App\Enums\AccountsReceivableAgingBracket;
use App\Enums\AccountsReceivableCollectionStatus;
use App\Enums\AccountsReceivableStatus;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountsReceivable;
use Inertia\Inertia;
use Inertia\Response;

/**
 * @phpstan-type AccountsReceivableRow array{
 *     id: int,
 *     job_order: array{id: int, number: string|null, description: string, total_amount: float|null, queue_entry: array{customer: array{name: string|null}|null}|null},
 *     balance: float,
 *     credit_extended: float,
 *     aging_bracket: string,
 *     days_past_due: int|null,
 *     collection_status: string,
 *     due_at: \Illuminate\Support\Carbon|null,
 *     write_off_requested_at: \Illuminate\Support\Carbon|null,
 *     last_reminder_sent_at: \Illuminate\Support\Carbon|null,
 * }
 */
class AccountsReceivableController extends Controller
{
    /**
     * The eager-load column allowlist shared by index() and show() (Pitfall
     * 3) — `queue_entry_id` is included on `jobOrder` so the nested
     * `jobOrder.queueEntry` load can resolve; without it the foreign key
     * column would be missing from the loaded model and the relation would
     * always resolve to null.
     *
     * @return array<int, string>
     */
    private function eagerLoads(): array
    {
        return [
            'jobOrder:id,number,description,total_amount,queue_entry_id',
            'jobOrder.queueEntry.customer:id,name',
            'jobOrder.transactions:id,job_order_id,amount,status',
        ];
    }

    /**
     * Show Accounting Staff's aging list (AR-01, D-01/D-03) — every Active
     * receivable, split into open vs. closed (paid/written-off) and
     * bucketed into six aging brackets over the open set only.
     */
    public function index(): Response
    {
        $entries = AccountsReceivable::query()
            ->where('status', AccountsReceivableStatus::Active->value)
            ->with($this->eagerLoads())
            ->get(['id', 'job_order_id', 'balance', 'status', 'collection_status', 'due_at', 'write_off_requested_at']);

        $rows = $entries->map(fn (AccountsReceivable $accountsReceivable): array => $this->deriveRow($accountsReceivable));

        $closedStatuses = [
            AccountsReceivableCollectionStatus::Paid->value,
            AccountsReceivableCollectionStatus::WrittenOff->value,
        ];

        $closed = $rows->filter(fn (array $row): bool => in_array($row['collection_status'], $closedStatuses, true))->values();
        $open = $rows->reject(fn (array $row): bool => in_array($row['collection_status'], $closedStatuses, true))->values();

        $bracketSummaries = collect(AccountsReceivableAgingBracket::cases())->map(function (AccountsReceivableAgingBracket $bracket) use ($open): array {
            $inBracket = $open->where('aging_bracket', $bracket->value);

            return [
                'bracket' => $bracket->value,
                'total' => round((float) $inBracket->sum('balance'), 2),
                'count' => $inBracket->count(),
            ];
        })->values();

        return Inertia::render('accounting-staff/AccountsReceivable/Index', [
            'receivables' => $this->sortRows($open->all()),
            'closedReceivables' => $this->sortRows($closed->all()),
            'bracketSummaries' => $bracketSummaries,
        ]);
    }

    /**
     * Sort rows most-overdue-first: `due_at` ascending with nulls (Current
     * entries with a future due date) last, then `balance` descending as
     * the tiebreak. Takes/returns a plain array rather than a Collection —
     * Collection's TValue template is invariant, so a Collection typed
     * with a narrower/literal-refined shape (e.g. after a filter()/
     * reject() closure) cannot be passed to a method declaring the general
     * AccountsReceivableRow shape even though the arrays are structurally
     * identical.
     *
     * @param  array<int, AccountsReceivableRow>  $rows
     * @return array<int, AccountsReceivableRow>
     */
    private function sortRows(array $rows): array
    {
        usort($rows, function (array $a, array $b): int {
            $aDue = $a['due_at'] ?? null;
            $bDue = $b['due_at'] ?? null;

            $aTimestamp = $aDue instanceof \Illuminate\Support\Carbon ? $aDue->timestamp : PHP_INT_MAX;
            $bTimestamp = $bDue instanceof \Illuminate\Support\Carbon ? $bDue->timestamp : PHP_INT_MAX;

            return $aTimestamp <=> $bTimestamp ?: $b['balance'] <=> $a['balance'];
        });

        return $rows;
    }

    /**
     * Show a single AR entry's full amounts/detail data (AR-01 completion).
     * 404s for a non-Active entry (T-07-02-01) — a role with no business
     * seeing pre-approval or rejected credit requests never gets a
     * different signal than "not found".
     */
    public function show(AccountsReceivable $accountsReceivable): Response
    {
        abort_unless($accountsReceivable->status === AccountsReceivableStatus::Active, 404);

        $accountsReceivable->loadMissing($this->eagerLoads());

        return Inertia::render('accounting-staff/AccountsReceivable/Show', [
            'accountsReceivable' => $this->deriveRow($accountsReceivable) + [
                'approved_at' => $accountsReceivable->approved_at,
            ],
        ]);
    }

    /**
     * Derive a single AR entry's row shape — balance is computed live from
     * completed transactions (D-16), never read from the stored `balance`
     * column, matching `ReceiptController::show()`'s identical derivation.
     *
     * @return AccountsReceivableRow
     */
    private function deriveRow(AccountsReceivable $accountsReceivable): array
    {
        $amountPaid = (float) $accountsReceivable->jobOrder->transactions->where('status', TransactionStatus::Completed->value)->sum('amount');
        $balance = $accountsReceivable->jobOrder->total_amount !== null
            ? round((float) $accountsReceivable->jobOrder->total_amount - $amountPaid, 2)
            : 0.0;

        return [
            'id' => $accountsReceivable->id,
            'job_order' => [
                'id' => $accountsReceivable->jobOrder->id,
                'number' => $accountsReceivable->jobOrder->number,
                'description' => $accountsReceivable->jobOrder->description,
                'total_amount' => $accountsReceivable->jobOrder->total_amount,
                'queue_entry' => [
                    'customer' => [
                        'name' => $accountsReceivable->jobOrder->queueEntry?->customer?->name,
                    ],
                ],
            ],
            'balance' => $balance,
            'credit_extended' => (float) $accountsReceivable->balance,
            'aging_bracket' => $accountsReceivable->agingBracket()->value,
            'days_past_due' => $accountsReceivable->daysPastDue(),
            'collection_status' => $accountsReceivable->collection_status->value,
            'due_at' => $accountsReceivable->due_at,
            'write_off_requested_at' => $accountsReceivable->write_off_requested_at,
            'last_reminder_sent_at' => $accountsReceivable->last_reminder_sent_at,
        ];
    }
}
