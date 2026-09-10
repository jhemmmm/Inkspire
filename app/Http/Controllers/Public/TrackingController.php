<?php

namespace App\Http\Controllers\Public;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\TrackJobOrderRequest;
use App\Models\JobOrder;
use App\Models\RevisionLog;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The two public, unauthenticated job-order tracking entry points: lookup by
 * job order number (TRACK-01) and lookup by the QR slip's tracking token
 * (QR-01).
 *
 * Security boundary (D-02, T-06-03-01, T-mbb-01/02) — this statement covers
 * BOTH actions. The narrow column list on each query and the narrow response
 * array are the only things standing between these routes and a
 * PII/pricing/payment leak. Never widen either query to a full model, never
 * eager-load a relation, and never select `tracking_token` back out: it is a
 * bearer credential printed on the customer's slip, so echoing it onto a
 * public surface would hand it to anyone who can see the page. One level
 * stricter than QueueDisplayController: the status-to-label mapping in
 * publicStage() must also never leak the raw enum value to the client.
 */
class TrackingController extends Controller
{
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
            ],
        ]);
    }

    /**
     * Show the same public tracking page for a QR slip's tracking token
     * (QR-01), plus a freshly signed way into the already-existing
     * design-review flow when a verdict is pending.
     *
     * `id` is selected solely so the revision log can be looked up, and is
     * deliberately absent from the response. The four-key result array
     * (`found`, `number`, `stage`, `reviewUrl`) is the whole shape.
     * `description` and `print_size` are excluded on purpose even though a
     * customer arguably owns both: a description is free text a staff member
     * may have typed a customer's name into, so the narrower shape is the
     * defensible one.
     */
    public function showByToken(string $token): Response
    {
        $jobOrder = JobOrder::query()
            ->where('tracking_token', $token)
            ->first(['id', 'number', 'status', 'released_at', 'cancelled_at']);

        // Chosen over abort(404) so a smudged or partially scanned slip gets
        // a readable customer-facing message instead of a raw error page.
        if ($jobOrder === null) {
            return Inertia::render('public/TrackingToken', [
                'result' => ['found' => false],
            ]);
        }

        return Inertia::render('public/TrackingToken', [
            'result' => [
                'found' => true,
                'number' => $jobOrder->number,
                'stage' => $this->publicStage($jobOrder),
                'reviewUrl' => $this->designReviewUrl($jobOrder),
            ],
        ]);
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
            ->latest('submitted_at')
            ->first(['id', 'submitted_at', 'outcome']);

        if (! $revisionLog instanceof RevisionLog || $revisionLog->outcome !== null) {
            return null;
        }

        $expiresAt = $revisionLog->submitted_at->addDays(7);

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
     * signal) and permits cancelling from all four production statuses, so
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
            JobOrderStatus::QualityCheck => 'Quality Check',
            JobOrderStatus::ReadyForPickup => 'Ready for Pickup',
            default => 'In Progress',
        };
    }
}
