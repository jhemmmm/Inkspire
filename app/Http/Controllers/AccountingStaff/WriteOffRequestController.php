<?php

namespace App\Http\Controllers\AccountingStaff;

use App\Enums\AccountsReceivableCollectionStatus;
use App\Enums\AccountsReceivableStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingStaff\RequestWriteOffRequest;
use App\Models\AccountsReceivable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class WriteOffRequestController extends Controller
{
    /**
     * Accounting Staff requests a write-off for an Active entry they can't
     * collect (D-13). Only the Admin's approval actually closes the entry --
     * this just records the request with a mandatory reason.
     *
     * `collection_status` and `AccountsReceivableStatus` are both untouched
     * here; a closed entry (by status OR collection_status -- Blocker 2,
     * D-09's orthogonal columns) is rejected outright before the
     * pending-request guard is even reached.
     */
    public function store(RequestWriteOffRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
    {
        abort_unless($accountsReceivable->status === AccountsReceivableStatus::Active, 422, __('This receivable is not active.'));
        abort_if(
            in_array($accountsReceivable->collectionStatus(), [AccountsReceivableCollectionStatus::Paid, AccountsReceivableCollectionStatus::WrittenOff], true),
            422,
            __('This entry is already closed and cannot be written off.'),
        );
        abort_if($accountsReceivable->write_off_requested_at !== null, 422, __('A write-off request is already pending for this entry.'));

        $accountsReceivable->forceFill([
            'write_off_reason' => $request->validated('reason'),
            'write_off_requested_by' => $request->user()->id,
            'write_off_requested_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Write-off requested. Awaiting Admin approval.')]);

        return back();
    }
}
