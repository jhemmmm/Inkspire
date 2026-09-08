<?php

namespace App\Http\Controllers\Owner;

use App\Enums\AccountsReceivableCollectionStatus;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ApproveWriteOffRequest;
use App\Http\Requests\Owner\RejectWriteOffRequest;
use App\Models\AccountsReceivable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WriteOffApprovalController extends Controller
{
    /**
     * Show the Owner's write-off approval queue (D-04/D-13). Admin can view
     * this page (route-group `role:owner,admin` middleware), but only Owner
     * can act on it -- enforced by AccountsReceivablePolicy.
     */
    public function index(Request $request): Response
    {
        $entries = AccountsReceivable::query()
            ->whereNotNull('write_off_requested_at')
            ->with([
                'jobOrder:id,number,description,total_amount,queue_entry_id',
                'jobOrder.queueEntry.customer:id,name',
                'jobOrder.transactions:id,job_order_id,amount,status',
                'writeOffRequestedBy:id,name',
            ])
            ->get(['id', 'job_order_id', 'balance', 'write_off_reason', 'write_off_requested_by', 'write_off_requested_at', 'due_at']);

        $writeOffRequests = $entries->map(function (AccountsReceivable $accountsReceivable): array {
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
                    'queue_entry' => [
                        'customer' => [
                            'name' => $accountsReceivable->jobOrder->queueEntry?->customer?->name,
                        ],
                    ],
                ],
                'balance' => $balance,
                'days_past_due' => $accountsReceivable->daysPastDue(),
                'write_off_reason' => $accountsReceivable->write_off_reason,
                'write_off_requested_by' => [
                    'name' => $accountsReceivable->writeOffRequestedBy?->name,
                ],
                'write_off_requested_at' => $accountsReceivable->write_off_requested_at,
            ];
        })->values();

        return Inertia::render('owner/WriteOffRequests', [
            'writeOffRequests' => $writeOffRequests,
        ]);
    }

    /**
     * Approve a pending write-off request, closing the AR entry as Written
     * Off and marking the job order's payment status terminal (D-14). The
     * job order's `total_amount` and every `transactions` row are untouched
     * -- a write-off is a loss to report, not a sale that shrank.
     *
     * The re-check that the entry hasn't settled to Paid/Written Off while
     * the request sat pending happens INSIDE the locked re-read, immediately
     * after the existing pending-request guard, so a payment landing in the
     * window between the Owner's page load and their click is still caught
     * (Blocker 2).
     */
    public function approve(ApproveWriteOffRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
    {
        DB::transaction(function () use ($accountsReceivable): void {
            $accountsReceivable = AccountsReceivable::query()->whereKey($accountsReceivable->id)->lockForUpdate()->firstOrFail();

            abort_if($accountsReceivable->write_off_requested_at === null, 422, __('No write-off request is pending for this entry.'));
            abort_if(
                in_array($accountsReceivable->collection_status, [AccountsReceivableCollectionStatus::Paid, AccountsReceivableCollectionStatus::WrittenOff], true),
                422,
                __('This entry was settled or closed before the write-off could be approved.'),
            );

            $accountsReceivable->forceFill(['collection_status' => AccountsReceivableCollectionStatus::WrittenOff->value])->save();
            $accountsReceivable->jobOrder->forceFill(['payment_status' => PaymentStatus::WrittenOff->value])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Write-off approved. :number is now marked Written Off.', ['number' => $accountsReceivable->jobOrder->number])]);

        return back();
    }

    /**
     * Reject a pending write-off request. Nulls the three write-off columns
     * only -- `AccountsReceivableStatus` never left Active, so this is what
     * "returns to Active and keeps aging" means (CONTEXT.md discretion). No
     * `collection_status` re-check is needed here: rejecting a settled entry
     * is harmless, since rejection never touches `payment_status`.
     */
    public function reject(RejectWriteOffRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
    {
        DB::transaction(function () use ($accountsReceivable): void {
            $accountsReceivable = AccountsReceivable::query()->whereKey($accountsReceivable->id)->lockForUpdate()->firstOrFail();

            abort_if($accountsReceivable->write_off_requested_at === null, 422, __('No write-off request is pending for this entry.'));

            $accountsReceivable->forceFill([
                'write_off_reason' => null,
                'write_off_requested_by' => null,
                'write_off_requested_at' => null,
            ])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Write-off request rejected. This balance keeps aging.')]);

        return back();
    }
}
