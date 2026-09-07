<?php

namespace App\Http\Controllers\ProductionStaff;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Production Board (PROD-01/PROD-02) — every job order currently in
 * production, color-coded by urgency.
 *
 * This board never selects or eager-loads any payment column
 * (`payment_status`, `total_amount`) — per the UI-SPEC's explicit "no
 * payment hint on this surface" rule, Production Staff act on production
 * stage alone.
 */
class ProductionBoardController extends Controller
{
    /**
     * Show every job order on the four production stages, excluding
     * released and cancelled job orders (D-12), each carrying a
     * server-computed `is_rush` boolean (D-05, D-07).
     *
     * "Due today" is an Asia/Manila business day, not a UTC one —
     * config('app.timezone') stays UTC project-wide, so a bare
     * now()->endOfDay() would end the day at 07:59:59 the next Manila
     * morning and badge tomorrow-morning work as Rush. Mirrors the same
     * narrow, per-call-site scoping as JobOrder::currentNumberingYear()
     * and QueueEntry::currentBusinessDate().
     */
    public function index(Request $request): Response
    {
        $endOfBusinessDay = now()->timezone('Asia/Manila')->endOfDay();

        return Inertia::render('production-staff/Dashboard', [
            'jobOrders' => JobOrder::query()
                ->whereIn('status', [
                    JobOrderStatus::ForProduction->value,
                    JobOrderStatus::Printing->value,
                    JobOrderStatus::QualityCheck->value,
                    JobOrderStatus::ReadyForPickup->value,
                ])
                ->whereNull('released_at')
                ->whereNull('cancelled_at')
                ->with('queueEntry.customer:id,name')
                ->orderByRaw('due_at IS NULL, due_at ASC')
                ->get(['id', 'number', 'description', 'status', 'due_at', 'queue_entry_id'])
                ->each(fn (JobOrder $jobOrder) => $jobOrder->is_rush = $jobOrder->due_at !== null && $jobOrder->due_at->lessThanOrEqualTo($endOfBusinessDay)),
        ]);
    }
}
