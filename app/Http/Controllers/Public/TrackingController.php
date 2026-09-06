<?php

namespace App\Http\Controllers\Public;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\TrackJobOrderRequest;
use App\Models\JobOrder;
use Inertia\Inertia;
use Inertia\Response;

class TrackingController extends Controller
{
    /**
     * Show the public, unauthenticated job-order tracking page (TRACK-01).
     *
     * Security boundary (D-02, T-06-03-01): the column list on the query
     * (`['number', 'status', 'released_at']`) and the response shape
     * (`['found', 'number', 'stage']`) are the only things standing between
     * this route and a PII/pricing/payment leak — never widen the query to
     * a full model, never eager-load a relation. One level stricter than
     * QueueDisplayController: the status-to-label mapping in publicStage()
     * must also never leak the raw enum value to the client.
     */
    public function show(TrackJobOrderRequest $request): Response
    {
        $number = $request->validated('number');

        if ($number === null) {
            return Inertia::render('public/Tracking', ['result' => null]);
        }

        $jobOrder = JobOrder::query()
            ->where('number', $number)
            ->first(['number', 'status', 'released_at']);

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
     * Map a job order's internal status (and released state) to the public,
     * customer-facing stage label (D-02, 06-UI-SPEC.md §5). Every
     * pre-production status collapses into "In Progress" so the public page
     * never leaks an internal validation-failure or design-review state.
     */
    private function publicStage(JobOrder $jobOrder): string
    {
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
