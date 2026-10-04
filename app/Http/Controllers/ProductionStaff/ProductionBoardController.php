<?php

namespace App\Http\Controllers\ProductionStaff;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use App\Support\BusinessTime;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Production Board (PROD-01/PROD-02) — every job order currently in
 * production, color-coded by urgency.
 *
 * The board exposes `payment_status` and a `cleared_for_production` flag
 * so unpaid orders show locked, but never selects or eager-loads an amount
 * (`total_amount`) — Production Staff see whether an order may be printed,
 * not what it costs.
 */
class ProductionBoardController extends Controller
{
    /**
     * Show every job order on the three production stages, excluding
     * released and cancelled job orders (D-12). Intake Rush and deadline
     * Urgent are separate flags; overdue deadlines lead the board.
     */
    public function index(Request $request): Response
    {
        $endOfBusinessDay = BusinessTime::now()->endOfDay();

        return Inertia::render('production-staff/Dashboard', [
            'jobOrders' => JobOrder::query()
                ->whereIn('status', [
                    JobOrderStatus::ForProduction->value,
                    JobOrderStatus::Printing->value,
                    JobOrderStatus::ReadyForPickup->value,
                ])
                ->whereNull('released_at')
                ->whereNull('cancelled_at')
                ->with('queueEntry.customer:id,name')
                ->orderByRaw('CASE WHEN due_at <= ? THEN 0 ELSE 1 END', [$endOfBusinessDay->utc()])
                ->orderByRaw('CASE WHEN due_at <= ? THEN due_at END ASC', [$endOfBusinessDay->utc()])
                ->orderByDesc('is_rush')
                ->orderByRaw('due_at IS NULL, due_at ASC')
                ->get(['id', 'number', 'description', 'status', 'due_at', 'queue_entry_id', 'is_rush', 'payment_status'])
                ->each(function (JobOrder $jobOrder) use ($endOfBusinessDay): void {
                    $jobOrder->setAttribute('is_urgent', $jobOrder->isUrgentByDeadline($endOfBusinessDay));
                    $jobOrder->setAttribute('cleared_for_production', $jobOrder->isClearedForProduction());
                }),
        ]);
    }
}
