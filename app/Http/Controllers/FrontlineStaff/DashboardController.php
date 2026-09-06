<?php

namespace App\Http\Controllers\FrontlineStaff;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show every job order waiting to be picked up (PROD-03) — a derived,
     * polled query with nothing stored (D-13). A job order disappears from
     * this list the instant it's released or sent back a stage, with no
     * code path needed to explicitly clear it.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('frontline-staff/Dashboard', [
            'readyForPickup' => JobOrder::query()
                ->where('status', JobOrderStatus::ReadyForPickup->value)
                ->whereNull('released_at')
                ->whereNull('cancelled_at')
                ->with('queueEntry.customer:id,name')
                ->orderBy('updated_at')
                ->get(['id', 'number', 'description', 'payment_status', 'queue_entry_id', 'updated_at']),
        ]);
    }
}
