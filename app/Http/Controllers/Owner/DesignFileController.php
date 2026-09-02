<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\UnlockDesignFileRequest;
use App\Models\DesignFile;
use App\Models\JobOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DesignFileController extends Controller
{
    /**
     * Show the Owner's Design Overrides list — job orders whose design file
     * is currently locked (JOB-07).
     */
    public function index(Request $request): Response
    {
        return Inertia::render('owner/DesignOverrides', [
            'jobOrders' => JobOrder::query()
                ->whereHas('designFile', fn ($query) => $query->whereNotNull('locked_at'))
                ->with(['designFile', 'assignedArtist:id,name', 'queueEntry.customer:id,name'])
                ->get(['id', 'description', 'assigned_artist_id', 'queue_entry_id']),
        ]);
    }

    /**
     * Unlock a design file so the assigned Artist can edit it again.
     *
     * Deliberately the ONLY mutation here — job_orders.status is never
     * touched; a subsequent Send for Review naturally re-advances it to
     * pending_review (matches Plan 04-03's locked_at-is-the-sole-authority
     * guard model).
     */
    public function unlock(UnlockDesignFileRequest $request, DesignFile $designFile): RedirectResponse
    {
        $designFile->forceFill(['locked_at' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Design unlocked. The Artist can edit it again.')]);

        return back();
    }
}
