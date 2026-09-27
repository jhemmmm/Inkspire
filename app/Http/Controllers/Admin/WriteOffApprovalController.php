<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountsReceivableCollectionStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveWriteOffRequest;
use App\Http\Requests\Admin\RejectWriteOffRequest;
use App\Models\AccountsReceivable;
use App\Models\JobOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WriteOffApprovalController extends Controller
{
    /**
     * Show the Admin's write-off approval queue (D-04/D-13). Admin can view
     * this page (route-group `role:admin` middleware), but only Admin
     * can act on it -- enforced by AccountsReceivablePolicy.
     */
    public function index(Request $request): Response
    {
        $entries = AccountsReceivable::query()
            ->whereNotNull('write_off_requested_at')
            ->whereNotIn('collection_status', [AccountsReceivableCollectionStatus::Paid->value, AccountsReceivableCollectionStatus::WrittenOff->value])
            ->with([
                'jobOrder:id,number,description,total_amount,queue_entry_id',
                'jobOrder.queueEntry.customer:id,name',
                'jobOrder.transactions:id,job_order_id,amount,status',
                'writeOffRequestedBy:id,name',
            ])
            ->get(['id', 'job_order_id', 'balance', 'collection_status', 'write_off_reason', 'write_off_requested_by', 'write_off_requested_at', 'due_at'])
            // The SQL filter above only catches an entry whose STORED
            // collection status is closed. Collection status is derived from
            // aging now, so an entry settled since the request was raised
            // reads Paid without that column ever being rewritten -- the
            // derived value has to be re-checked in PHP. The transactions it
            // needs are already eager-loaded for the balance figure below.
            ->reject(fn (AccountsReceivable $accountsReceivable): bool => $accountsReceivable->isClosed());

        $writeOffRequests = $entries->map(function (AccountsReceivable $accountsReceivable): array {
            $balance = $accountsReceivable->jobOrder->outstandingBalance();

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

        return Inertia::render('admin/WriteOffRequests', [
            'writeOffRequests' => $writeOffRequests,
        ]);
    }

    /**
     * Approve a pending write-off request, closing the AR entry as Written
     * Off and marking the job order's payment status terminal (D-14). The
     * job order's `total_amount` and every `transactions` row are untouched
     * -- a write-off is a loss to report, not a sale that shrank.
     *
     * Also nulls `write_off_requested_at` in the same write, so the entry
     * leaves this queue immediately (CR-02) -- `index()` additionally
     * excludes `paid`/`written_off` `collection_status` rows as a second,
     * independent layer of protection.
     *
     * Two settlement checks run inside the locked re-read, immediately after
     * the existing pending-request guard: the `collection_status` check is a
     * cheap short-circuit for the common case where the daily `ar:send-
     * reminders` cron has already reconciled the flag, while the derived-
     * balance check (`outstandingBalance`, computed identically to
     * `index()`) is the AUTHORITATIVE guard -- it re-reads the job order
     * under lock and sums its Completed transactions itself, so a real
     * payment landing in the window between the Admin's page load and their
     * approve click is still caught even though the lag-prone flag hasn't
     * caught up yet.
     */
    public function approve(ApproveWriteOffRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
    {
        DB::transaction(function () use ($accountsReceivable): void {
            $accountsReceivable = AccountsReceivable::query()->whereKey($accountsReceivable->id)->lockForUpdate()->firstOrFail();

            abort_if($accountsReceivable->write_off_requested_at === null, 422, __('No write-off request is pending for this entry.'));
            abort_if(
                in_array($accountsReceivable->collectionStatus(), [AccountsReceivableCollectionStatus::Paid, AccountsReceivableCollectionStatus::WrittenOff], true),
                422,
                __('This entry was settled or closed before the write-off could be approved.'),
            );

            $jobOrder = JobOrder::query()->whereKey($accountsReceivable->job_order_id)->lockForUpdate()->firstOrFail();

            $outstandingBalance = $jobOrder->outstandingBalance();

            abort_if($outstandingBalance <= 0.0, 422, __('This entry was settled or closed before the write-off could be approved.'));

            $accountsReceivable->forceFill([
                'collection_status' => AccountsReceivableCollectionStatus::WrittenOff->value,
                'write_off_requested_at' => null,
                'written_off_at' => now(),
            ])->save();
            $jobOrder->forceFill(['payment_status' => PaymentStatus::WrittenOff->value])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Write-off approved. :number is now marked Written Off.', ['number' => $accountsReceivable->jobOrder->number])]);

        return back();
    }

    /**
     * Reject a pending write-off request. Nulls the three write-off columns
     * only -- `AccountsReceivableStatus` never left Active, so this is what
     * "returns to Active and keeps aging" means (CONTEXT.md discretion). A
     * `collection_status` re-check IS needed here (CR-01): an already-
     * approved write-off still satisfies the `write_off_requested_at !==
     * null` guard below (approve() nulls it, but a stale or replayed Reject
     * click must never erase an already-booked loss's reason/requester/
     * timestamp), so an already-`written_off` entry is rejected outright.
     */
    public function reject(RejectWriteOffRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
    {
        DB::transaction(function () use ($accountsReceivable): void {
            $accountsReceivable = AccountsReceivable::query()->whereKey($accountsReceivable->id)->lockForUpdate()->firstOrFail();

            abort_if($accountsReceivable->write_off_requested_at === null, 422, __('No write-off request is pending for this entry.'));
            abort_if(
                $accountsReceivable->collectionStatus() === AccountsReceivableCollectionStatus::WrittenOff,
                422,
                __('This write-off has already been approved and cannot be rejected.'),
            );

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
