<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JobOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterJobOrdersRequest;
use App\Models\JobOrder;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class JobOrderController extends Controller
{
    /**
     * Show the read-only, filterable Admin Job Orders list — the only
     * place an Admin can see every job order's price across every status,
     * without going through the Cashier/Frontline/Artist portals.
     */
    public function index(FilterJobOrdersRequest $request): Response
    {
        $jobOrders = JobOrder::query()
            ->whereNull('cancelled_at')
            ->when($request->filled('q'), fn (Builder $query) => $query->search((string) $request->string('q')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->with([
                'queueEntry:id,customer_id',
                'queueEntry.customer:id,name',
                'pricingEntry:id,name',
            ])
            ->select(['id', 'number', 'description', 'width_ft', 'height_ft', 'quantity', 'pricing_entry_id', 'queue_entry_id', 'status', 'payment_status', 'total_amount', 'quoted_amount', 'is_rush'])
            ->withAmountPaid()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $jobOrders->getCollection()->each(fn (JobOrder $jobOrder) => $jobOrder->append('display_total'));

        return Inertia::render('admin/JobOrders', [
            'jobOrders' => $jobOrders,
            'filters' => $request->only(['q', 'status']),
            // Bare status values — the frontend already has a shared
            // jobOrderStatusLabel() helper (lib/jobOrders.ts) that every
            // other portal's job order table uses, so labels live there
            // rather than being duplicated on this controller too.
            'statuses' => collect(JobOrderStatus::cases())->map(fn (JobOrderStatus $status): string => $status->value)->all(),
        ]);
    }
}
