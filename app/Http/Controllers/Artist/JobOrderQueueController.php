<?php

namespace App\Http\Controllers\Artist;

use App\Actions\JobOrder\ClaimJobOrderForArtist;
use App\Enums\ArtistStatus;
use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Artist\UpdateJobOrderQueuePositionRequest;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobOrderQueueController extends Controller
{
    /**
     * The Artist's own queue order, shared by the dashboard list and by
     * nextEligibleId() so the row the Artist sees on top is always the row
     * the server will let them call next.
     *
     * Rush leads, matching the shared pool (ClaimJobOrderForArtist::pool()).
     * Below that, most recently accepted first -- an Artist pulls a job
     * because they intend to start it, so it belongs where they are looking.
     * A job they no longer want does not sink down this list, it leaves it
     * entirely via forward().
     *
     * The rush key MUST stay in this one constant rather than being added to
     * the listing query alone. Both the list and nextEligibleId() read it,
     * and the Next button is authorised by re-deriving the top row
     * server-side (T-04-02): if the two orders ever disagree, the button on
     * the row the Artist can see is rejected with "Another job order is next
     * in your queue" and the queue becomes unworkable.
     */
    private const string QUEUE_ORDER = 'is_rush DESC, accepted_at DESC';

    /**
     * The artist's own dashboard queue — every in-progress job order
     * assigned to them, ordered oldest-first (deprioritized ones sort by
     * their deprioritization timestamp instead).
     */
    public function index(Request $request): Response
    {
        return Inertia::render('artist/Dashboard', [
            'jobOrders' => JobOrder::query()
                ->where('assigned_artist_id', $request->user()->id)
                ->whereNotIn('status', [
                    JobOrderStatus::Intake->value,
                    JobOrderStatus::ValidationFailed->value,
                    JobOrderStatus::ReadyForProduction->value,
                    JobOrderStatus::ForProduction->value,
                    JobOrderStatus::Printing->value,
                    JobOrderStatus::ReadyForPickup->value,
                ])
                ->orderByRaw(self::QUEUE_ORDER)
                ->get(['id', 'number', 'description', 'status', 'created_at', 'is_rush', 'type', 'deadline']),
            'availableJobOrders' => ClaimJobOrderForArtist::pool()
                ->with('queueEntry.customer:id,name')
                ->get(['id', 'number', 'description', 'queue_entry_id', 'created_at', 'is_rush', 'type', 'deadline']),
            'artistStatus' => $request->user()->artist_status,
            'artistLabel' => $request->user()->artist_label,
        ]);
    }

    /**
     * Accept an unclaimed Type B job order out of the shared pool.
     *
     * The claim itself is a compare-and-swap UPDATE inside
     * ClaimJobOrderForArtist -- when two Artists press Accept on the same
     * row at once exactly one of them gets `true` back, and the other is
     * told, without any write having happened, that they lost.
     *
     * Losing the race is not an error state to abort on: the request was
     * well-formed and authorized, it just arrived second. It redirects
     * back with an error toast so the loser's pool list simply re-renders
     * without that job order, rather than throwing an exception page at
     * an Artist who did nothing wrong.
     */
    public function accept(UpdateJobOrderQueuePositionRequest $request, JobOrder $jobOrder, ClaimJobOrderForArtist $claim): RedirectResponse
    {
        $artist = $request->user();

        abort_unless(
            $artist->artist_status === ArtistStatus::Available,
            422,
            __('Set yourself to Available before accepting a job order.'),
        );

        if (! $claim($jobOrder, $artist)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Another artist accepted that job order first.'),
            ]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job order accepted. It is now in your queue.')]);

        return back();
    }

    /**
     * Move the job order at the top of this artist's queue into
     * in_consultation (D-04).
     */
    public function next(UpdateJobOrderQueuePositionRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        $this->abortUnlessOnShift($request->user());

        abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, 'This job order is not assigned to you.');

        abort_unless($jobOrder->status === JobOrderStatus::Assigned, 422, 'This job order is not waiting to be called.');
        abort_unless($this->nextEligibleId($request->user()) === $jobOrder->id, 422, 'Another job order is next in your queue.');

        $jobOrder->forceFill(['status' => JobOrderStatus::InConsultation])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job order moved to consultation.')]);

        return to_route('artist.job-orders.show', $jobOrder);
    }

    /**
     * Hand a job order back to the shared pool so a different Artist can
     * take it — accepted by mistake, or better suited to someone else.
     *
     * The row returns to exactly the state the pool selects on (Intake,
     * no artist, no acceptance stamp) and reappears in every available
     * Artist's Available Jobs list. Consultation notes and any design file
     * are deliberately left intact, so whoever picks it up inherits the
     * work already done rather than starting the customer over.
     */
    public function forward(UpdateJobOrderQueuePositionRequest $request, JobOrder $jobOrder): RedirectResponse
    {
        $this->abortUnlessOnShift($request->user());

        abort_unless($jobOrder->assigned_artist_id === $request->user()->id, 403, 'This job order is not assigned to you.');
        abort_unless(
            in_array($jobOrder->status, [JobOrderStatus::Assigned, JobOrderStatus::InConsultation, JobOrderStatus::InDesign], true),
            422,
            'This job order cannot be forwarded in its current status.',
        );

        $jobOrder->forceFill([
            'status' => JobOrderStatus::Intake,
            'assigned_artist_id' => null,
            'accepted_at' => null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Forwarded. Another artist can now accept this job order.')]);

        return back();
    }

    /**
     * An Artist on break or off shift works nothing in their own queue.
     *
     * Enforced here and not only by disabling the buttons: the dashboard
     * greys the row's actions out, but the routes behind them stay
     * reachable, and "on break" has to mean the same thing on both sides.
     * Accept already carries its own equivalent guard.
     */
    private function abortUnlessOnShift(User $artist): void
    {
        abort_if(
            $artist->artist_status === ArtistStatus::OnBreak,
            422,
            __('End your break before working your queue.'),
        );

        abort_if(
            $artist->artist_status === ArtistStatus::OffShift,
            422,
            __('Start your shift before working your queue.'),
        );
    }

    /**
     * The job order at the top of this artist's queue — independently
     * re-derived server-side so a tampered request targeting a different
     * row is rejected (T-04-02).
     */
    private function nextEligibleId(User $artist): ?int
    {
        return JobOrder::query()
            ->where('assigned_artist_id', $artist->id)
            ->where('status', JobOrderStatus::Assigned->value)
            ->orderByRaw(self::QUEUE_ORDER)
            ->value('id');
    }
}
