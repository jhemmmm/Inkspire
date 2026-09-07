<?php

namespace App\Http\Controllers\AccountingStaff;

use App\Enums\AccountsReceivableCollectionStatus;
use App\Enums\AccountsReceivableStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingStaff\UpdateCollectionStatusRequest;
use App\Models\AccountsReceivable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CollectionStatusController extends Controller
{
    /**
     * Update an Active AR entry's human-set collection status (D-09/D-10).
     * `paid`/`written_off` can never reach here -- excluded from the
     * request's own validation allowlist -- and a closed entry (non-Active,
     * or already `paid`/`written_off`) re-checks server-side independent of
     * whatever the client's UI happened to render (T-07-04-02).
     */
    public function update(UpdateCollectionStatusRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
    {
        abort_unless($accountsReceivable->status === AccountsReceivableStatus::Active, 422, __('This entry is closed and its collection status can\'t be changed.'));
        abort_if(
            in_array($accountsReceivable->collection_status, [AccountsReceivableCollectionStatus::Paid, AccountsReceivableCollectionStatus::WrittenOff], true),
            422,
            __('This entry is closed and its collection status can\'t be changed.'),
        );

        $accountsReceivable->forceFill([
            'collection_status' => $request->validated('collection_status'),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Collection status updated.')]);

        return back();
    }
}
