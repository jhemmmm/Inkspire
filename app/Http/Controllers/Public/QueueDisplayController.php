<?php

namespace App\Http\Controllers\Public;

use App\Enums\JobOrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use Inertia\Inertia;
use Inertia\Response;

class QueueDisplayController extends Controller
{
    /**
     * The stations a number can be sent to, in the order the board lists
     * them when a visit has more than one thing to do.
     *
     * @var array<int, string>
     */
    private const array STATION_ORDER = ['artist', 'cashier', 'frontline'];

    /**
     * The statuses at which an artist holds a job order. The same set
     * SyncQueueEntryStatus reads as "Serving", so the board and the staff
     * queue list cannot disagree about who is with an artist.
     *
     * @var array<int, JobOrderStatus>
     */
    private const array WITH_ARTIST = [
        JobOrderStatus::Assigned,
        JobOrderStatus::InConsultation,
        JobOrderStatus::InDesign,
        JobOrderStatus::PendingReview,
    ];

    /**
     * The statuses at which a job order waits on its first payment: the
     * design part is over and production will not start it unpaid.
     *
     * @var array<int, JobOrderStatus>
     */
    private const array AWAITING_FIRST_PAYMENT = [
        JobOrderStatus::ReadyForProduction,
        JobOrderStatus::DesignApproved,
        JobOrderStatus::ForProduction,
    ];

    /**
     * Show the public, unauthenticated shared queue display: today's
     * numbers, and where each one should go.
     *
     * A number is sent to a station by what its job orders need, with no
     * staff action: to the artist who accepted one, to the Cashier when one
     * has something to pay, to Frontline when one is printed and cleared to
     * hand over. A number with only unclaimed work is waiting (no stations),
     * and a number with nothing left for the customer to do is off the board.
     *
     * Security boundary (D-09, T-02-15): the response is built by hand from
     * the narrow column lists below, which are the only thing standing
     * between this route and a PII leak. Never return the models, never
     * widen a list, and never load the visit-owner relation. An artist is
     * named by their "Artist N" label, never by name.
     */
    public function index(): Response
    {
        $queueEntries = QueueEntry::query()
            ->with(['jobOrders' => fn ($query) => $query
                ->whereNull('cancelled_at')
                ->whereNull('released_at')
                ->select(['id', 'queue_entry_id', 'status', 'payment_status', 'assigned_artist_id'])
                ->with('assignedArtist:id,artist_label')])
            ->todaysFloorQueue()
            ->get(['id', 'queue_prefix', 'queue_number']);

        return Inertia::render('public/QueueDisplay', [
            'queueEntries' => $queueEntries
                ->map(fn (QueueEntry $entry): ?array => $this->boardEntry($entry))
                ->filter()
                ->values(),
        ]);
    }

    /**
     * One number as the board shows it, or null when the visit has nothing
     * left that needs the customer.
     *
     * @return array{id: int, queue_prefix: string, queue_number: int, stations: array<int, array{to: string, label: string}>}|null
     */
    private function boardEntry(QueueEntry $entry): ?array
    {
        $stations = $entry->jobOrders
            ->map(fn (JobOrder $jobOrder): ?array => $this->station($jobOrder))
            ->filter()
            ->unique(fn (array $station): string => $station['to'].$station['label'])
            ->sortBy(fn (array $station): int => (int) array_search($station['to'], self::STATION_ORDER, true))
            ->values()
            ->all();

        $isWaitingForAnArtist = $entry->jobOrders->contains(
            fn (JobOrder $jobOrder): bool => $jobOrder->status === JobOrderStatus::Intake,
        );

        if ($stations === [] && ! $isWaitingForAnArtist) {
            return null;
        }

        return [
            'id' => $entry->id,
            'queue_prefix' => $entry->queue_prefix,
            'queue_number' => $entry->queue_number,
            'stations' => $stations,
        ];
    }

    /**
     * Where one job order sends its customer, or null when it needs nothing
     * from them right now.
     *
     * An order that is printed but not cleared for release goes to the
     * Cashier first; JobOrderReleaseController refuses it with that same
     * instruction. Before production, only Unpaid and CreditRejected go to
     * the Cashier: an online payment in progress or a credit request with
     * Admin has already been to the counter.
     *
     * @return array{to: string, label: string}|null
     */
    private function station(JobOrder $jobOrder): ?array
    {
        if ($jobOrder->assignedArtist !== null && in_array($jobOrder->status, self::WITH_ARTIST, true)) {
            return ['to' => 'artist', 'label' => $jobOrder->assignedArtist->artist_label ?? __('Artist')];
        }

        if ($jobOrder->status === JobOrderStatus::ReadyForPickup) {
            return $jobOrder->isClearedForRelease()
                ? ['to' => 'frontline', 'label' => __('Frontline')]
                : ['to' => 'cashier', 'label' => __('Cashier')];
        }

        if (in_array($jobOrder->status, self::AWAITING_FIRST_PAYMENT, true)
            && in_array($jobOrder->payment_status, [PaymentStatus::Unpaid, PaymentStatus::CreditRejected], true)) {
            return ['to' => 'cashier', 'label' => __('Cashier')];
        }

        return null;
    }
}
