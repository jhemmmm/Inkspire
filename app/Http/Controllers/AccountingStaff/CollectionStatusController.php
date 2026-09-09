<?php

namespace App\Http\Controllers\AccountingStaff;

use App\Enums\AccountsReceivableCollectionStatus;
use App\Enums\AccountsReceivableStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountingStaff\UpdateCollectionStatusRequest;
use App\Models\AccountsReceivable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CollectionStatusController extends Controller
{
    /**
     * Update an Active AR entry's human-set collection status (D-09/D-10).
     * `paid`/`written_off` can never reach here -- excluded from the
     * request's own validation allowlist -- and a closed entry (non-Active,
     * or already `paid`/`written_off`) re-checks server-side independent of
     * whatever the client's UI happened to render (T-07-04-02).
     *
     * Both guards and the write run inside a single database transaction,
     * against a freshly locked re-read instance rather than the
     * route-model-bound one passed into this method -- matching every other
     * mutating AR controller's locked-re-read boundary in this phase
     * (CR-04), so this can never resurrect an already-closed entry on a
     * stale read.
     */
    public function update(UpdateCollectionStatusRequest $request, AccountsReceivable $accountsReceivable): RedirectResponse
    {
        DB::transaction(function () use ($request, $accountsReceivable): void {
            $accountsReceivable = AccountsReceivable::query()->whereKey($accountsReceivable->id)->lockForUpdate()->firstOrFail();

            abort_unless($accountsReceivable->status === AccountsReceivableStatus::Active, 422, __('This entry is closed and its collection status can\'t be changed.'));
            abort_if(
                in_array($accountsReceivable->collection_status, [AccountsReceivableCollectionStatus::Paid, AccountsReceivableCollectionStatus::WrittenOff], true),
                422,
                __('This entry is closed and its collection status can\'t be changed.'),
            );

            $accountsReceivable->forceFill([
                'collection_status' => $request->validated('collection_status'),
            ])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Collection status updated.')]);

        return back();
    }
}
