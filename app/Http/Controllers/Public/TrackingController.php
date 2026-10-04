<?php

namespace App\Http\Controllers\Public;

use App\Actions\POS\SettlePaymongoPayment;
use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\TrackJobOrderRequest;
use App\Models\JobOrder;
use App\Models\RevisionLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The two public, unauthenticated job-order tracking entry points: lookup by
 * job order number (TRACK-01) and lookup by the QR slip's tracking token
 * (QR-01).
 *
 * Security boundary (D-02, T-06-03-01, T-mbb-01/02) — the narrow column list
 * on each query and the narrow response array are the only things standing
 * between these routes and a PII/pricing/payment leak. Never widen either
 * query to a full model, never eager-load a relation, and never select
 * `tracking_token` back out: it is a bearer credential printed on the
 * customer's slip, so echoing it onto a public surface would hand it to
 * anyone who can see the page. One level stricter than
 * QueueDisplayController: the status-to-label mapping in publicStage() must
 * also never leak the raw enum value to the client.
 *
 * The two actions differ on purpose. show() looks an order up by its number,
 * which is guessable, so it exposes the stage and nothing about money.
 * showByToken() is reached with the customer's own credential, so it also
 * shows its bearer the amount due and a three-value payment state (see
 * paymentSummary()) -- the customer can pay from here. It still never
 * returns customer PII, the raw status value, the token, or the pricing and
 * payment column names.
 */
class TrackingController extends Controller
{
    /**
     * The only job order columns the token page may read. `id` is there to
     * look up the revision log, the amount due and a pending checkout; it
     * never reaches the response.
     *
     * @var array<int, string>
     */
    private const array TOKEN_COLUMNS = ['id', 'number', 'status', 'released_at', 'cancelled_at', 'total_amount', 'payment_status'];

    /**
     * Seconds between PayMongo look-ups for one job order's pending
     * checkout. The page polls every 5s from every open tab.
     */
    private const int SETTLE_EVERY_SECONDS = 10;

    public function __construct(public SettlePaymongoPayment $settlePaymongoPayment) {}

    /**
     * The journey a customer sees, as an ordered list of the labels
     * publicStage() returns. Position in this array is what the page's
     * progress ladder renders -- see publicStageStep().
     *
     * Deliberately a list of LABELS, not of status keys. A key like
     * `ready_for_pickup` would be the internal enum value verbatim, and this
     * controller's whole contract is that the raw status never reaches the
     * client; a test asserts the response body contains no enum value, and
     * it should keep passing.
     *
     * `Cancelled` is absent on purpose: it is not a step on the journey, it
     * ends it.
     *
     * @var array<int, string>
     */
    private const array PUBLIC_STAGE_ORDER = [
        'In Progress',
        'For Production',
        'Printing',
        'Ready for Pickup',
        'Completed',
    ];

    /**
     * Show the public, unauthenticated job-order tracking page (TRACK-01).
     */
    public function show(TrackJobOrderRequest $request): Response
    {
        $number = $request->validated('number');

        if ($number === null) {
            return Inertia::render('public/Tracking', ['result' => null]);
        }

        $jobOrder = JobOrder::query()
            ->where('number', $number)
            ->first(['number', 'status', 'released_at', 'cancelled_at']);

        if ($jobOrder === null) {
            return Inertia::render('public/Tracking', [
                'result' => ['found' => false],
            ]);
        }

        return Inertia::render('public/Tracking', [
            'result' => [
                'found' => true,
                'number' => $jobOrder->number,
                'stage' => $this->publicStage($jobOrder),
                'stageStep' => $this->publicStageStep($jobOrder),
            ],
        ]);
    }

    /**
     * Show the same public tracking page for a QR slip's tracking token
     * (QR-01), plus a freshly signed way into the already-existing
     * design-review flow when a verdict is pending.
     *
     * The query is TOKEN_COLUMNS and nothing else. The result is `found`,
     * `number`, `stage`, `stageStep`, `reviewUrl` and `payment`.
     * `description` and `print_size` are excluded on purpose even though a
     * customer arguably owns both: a description is free text a staff member
     * may have typed a customer's name into, so the narrower shape is the
     * defensible one. `stageStep` is an integer position, not a status --
     * see PUBLIC_STAGE_ORDER. `payment` is deliberately shown to the token's
     * bearer, because the token is the customer's own credential; it is null
     * whenever there is nothing to pay online.
     */
    public function showByToken(string $token): Response
    {
        $jobOrder = JobOrder::query()
            ->where('tracking_token', $token)
            ->first(self::TOKEN_COLUMNS);

        // Chosen over abort(404) so a smudged or partially scanned slip gets
        // a readable customer-facing message instead of a raw error page.
        if ($jobOrder === null) {
            return Inertia::render('public/TrackingToken', [
                'result' => ['found' => false],
            ]);
        }

        if ($this->settlePendingPayment($jobOrder)) {
            $jobOrder = JobOrder::query()->whereKey($jobOrder->id)->firstOrFail(self::TOKEN_COLUMNS);
        }

        return Inertia::render('public/TrackingToken', [
            'result' => [
                'found' => true,
                'number' => $jobOrder->number,
                'stage' => $this->publicStage($jobOrder),
                'stageStep' => $this->publicStageStep($jobOrder),
                'reviewUrl' => $this->designReviewUrl($jobOrder),
                'payment' => $this->paymentSummary($jobOrder),
            ],
        ]);
    }

    /**
     * Ask PayMongo about a checkout that is still open, so the page turns to
     * "paid" on its own once the customer comes back from their wallet --
     * without waiting on a webhook that may be late, or that cannot reach
     * this server at all.
     *
     * Limited to one look-up per job order every SETTLE_EVERY_SECONDS, and a
     * PayMongo failure is reported and swallowed: this page has to keep
     * showing the order's stage whatever PayMongo is doing.
     *
     * Returns whether PayMongo was asked, in which case the caller re-reads
     * the job order.
     */
    private function settlePendingPayment(JobOrder $jobOrder): bool
    {
        if ($jobOrder->onlinePaymentState() !== 'pending') {
            return false;
        }

        if (! Cache::add('paymongo-settle:'.$jobOrder->id, true, self::SETTLE_EVERY_SECONDS)) {
            return false;
        }

        $transaction = $jobOrder->pendingPaymongoTransaction();

        if ($transaction === null) {
            return false;
        }

        rescue(fn () => ($this->settlePaymongoPayment)($transaction));

        return true;
    }

    /**
     * What the token page shows about paying: the outstanding amount and a
     * three-value state, or null when there is nothing to pay online. The
     * state rules live in JobOrder::onlinePaymentState(), shared with
     * OnlinePaymentController so what is offered and what is accepted match.
     *
     * @return array{amountDue: float, state: 'due'|'pending'|'paid'}|null
     */
    private function paymentSummary(JobOrder $jobOrder): ?array
    {
        $state = $jobOrder->onlinePaymentState();

        if ($state === null) {
            return null;
        }

        return ['amountDue' => $jobOrder->outstandingBalance(), 'state' => $state];
    }

    /**
     * A freshly signed `public.design-review.show` URL when — and only when —
     * this job order's LATEST revision is genuinely actionable.
     *
     * Mirrors DesignReviewController::isCurrentRevision() plus isActionable()
     * exactly, so a superseded or already-resolved revision never gets a
     * link. Expiry uses the same `submitted_at->addDays(7)` basis that
     * DesignReviewController::render() uses for the verdict URLs it emits; an
     * already-past expiry yields null rather than a link that 403s the moment
     * the customer taps it.
     *
     * This mints a link into the existing boundary. It does not widen it: no
     * route is added to the design-review prefix and its `signed` middleware
     * is untouched.
     */
    private function designReviewUrl(JobOrder $jobOrder): ?string
    {
        if ($jobOrder->status !== JobOrderStatus::PendingReview) {
            return null;
        }

        $revisionLog = $jobOrder->revisionLogs()
            ->latest('submitted_at')->latest('id')
            ->first(['id', 'submitted_at', 'outcome']);

        if (! $revisionLog instanceof RevisionLog || $revisionLog->outcome !== null) {
            return null;
        }

        $expiresAt = $revisionLog->submitted_at->copy()->addDays(7);

        if ($expiresAt->isPast()) {
            return null;
        }

        return URL::temporarySignedRoute('public.design-review.show', $expiresAt, ['revisionLog' => $revisionLog->id]);
    }

    /**
     * Map a job order's internal status (and released/cancelled state) to
     * the public, customer-facing stage label (D-02, 06-UI-SPEC.md §5).
     * Every pre-production status collapses into "In Progress" so the
     * public page never leaks an internal validation-failure or
     * design-review state.
     *
     * cancelled_at is consulted first and outranks everything else:
     * CancellationController::store() deliberately leaves `status`
     * untouched (cancelled_at is the authoritative "no longer actionable"
     * signal) and permits cancelling from all three production statuses, so
     * a cancelled order would otherwise keep telling the customer it is
     * "Printing" while the page polls that answer forever.
     */
    private function publicStage(JobOrder $jobOrder): string
    {
        if ($jobOrder->cancelled_at !== null) {
            return 'Cancelled';
        }

        if ($jobOrder->released_at !== null) {
            return 'Completed';
        }

        return match ($jobOrder->status) {
            JobOrderStatus::ForProduction => 'For Production',
            JobOrderStatus::Printing => 'Printing',
            JobOrderStatus::ReadyForPickup => 'Ready for Pickup',
            default => 'In Progress',
        };
    }

    /**
     * How far along PUBLIC_STAGE_ORDER this job order is, zero-indexed.
     *
     * Null means the order is off the ladder -- cancelled -- and the page
     * renders an ended journey instead of a step. An integer carries no
     * status vocabulary at all, which is what keeps the raw enum out of the
     * response.
     *
     * The page's own step copy must stay the same length and order as
     * PUBLIC_STAGE_ORDER; OrderProgress.vue names this file for that reason,
     * and PublicStageStepTest pins every status to its position.
     */
    private function publicStageStep(JobOrder $jobOrder): ?int
    {
        $index = array_search($this->publicStage($jobOrder), self::PUBLIC_STAGE_ORDER, true);

        return $index === false ? null : $index;
    }
}
