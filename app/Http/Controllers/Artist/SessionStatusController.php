<?php

namespace App\Http\Controllers\Artist;

use App\Actions\JobOrder\SetArtistSessionStatus;
use App\Enums\ArtistStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artist\UpdateSessionStatusRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SessionStatusController extends Controller
{
    public function __construct(public SetArtistSessionStatus $setArtistSessionStatus) {}

    /**
     * Start a break. Only reachable from Available (D-13).
     */
    public function startBreak(UpdateSessionStatusRequest $request): RedirectResponse
    {
        abort_unless($request->user()->artist_status === ArtistStatus::Available, 422, 'You are not currently available.');

        ($this->setArtistSessionStatus)($request->user(), ArtistStatus::OnBreak);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status updated to :label.', ['label' => 'On Break'])]);

        return back();
    }

    /**
     * End a break, returning to Available. This is the transition that
     * claims the artist's oldest unassigned Type B job order (D-13).
     */
    public function endBreak(UpdateSessionStatusRequest $request): RedirectResponse
    {
        abort_unless($request->user()->artist_status === ArtistStatus::OnBreak, 422, 'You are not currently on break.');

        ($this->setArtistSessionStatus)($request->user(), ArtistStatus::Available);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status updated to :label.', ['label' => 'Available'])]);

        return back();
    }

    /**
     * Come back on shift, from Off Shift to Available.
     *
     * Without this an ended shift was a one-way door: the dashboard showed
     * the Off Shift badge and offered nothing, so the next morning an
     * artist had no way back to Available and could accept nothing.
     */
    public function startShift(UpdateSessionStatusRequest $request): RedirectResponse
    {
        abort_unless($request->user()->artist_status === ArtistStatus::OffShift, 422, 'You are already on shift.');

        ($this->setArtistSessionStatus)($request->user(), ArtistStatus::Available);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome back. Status updated to :label.', ['label' => 'Available'])]);

        return back();
    }

    /**
     * End shift. Allowed at any time from Available or On Break, with no
     * reassignment of in-progress job orders (D-15).
     */
    public function endShift(UpdateSessionStatusRequest $request): RedirectResponse
    {
        abort_unless(in_array($request->user()->artist_status, [ArtistStatus::Available, ArtistStatus::OnBreak], true), 422, 'Your shift has already ended.');

        ($this->setArtistSessionStatus)($request->user(), ArtistStatus::OffShift);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status updated to :label.', ['label' => 'Off Shift'])]);

        return back();
    }
}
