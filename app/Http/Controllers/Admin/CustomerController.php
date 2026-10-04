<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterCustomersRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\JobOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * How many of a customer's newest job orders the list carries. The full
     * history is one click away on the Job Orders page.
     */
    private const int RECENT_JOB_ORDERS = 5;

    /**
     * Show every customer the shop has served, each with a count of their
     * job orders and their most recent few.
     *
     * This is the Admin's own authenticated screen, so the customer's
     * contact details are shown in full, exactly as Frontline Staff's New
     * Visit screen shows them. Do not mistake it for the public boundary
     * TrackingController defends.
     *
     * The recent job orders are ONE windowed query for the whole page (an
     * eager-load `limit` partitions by customer), not one query per row.
     * `select()` runs before `withCount()` on purpose: withAggregate() falls
     * back to `table.*` when no columns are set yet.
     */
    public function index(FilterCustomersRequest $request): Response
    {
        $customers = Customer::query()
            ->select(['id', 'name', 'organization', 'contact_number', 'email', 'address'])
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $query->search((string) $request->string('q'), ['name', 'organization', 'contact_number', 'email']);
            })
            ->withCount('jobOrders')
            ->with(['jobOrders' => fn ($query) => $query
                ->select([
                    'job_orders.id',
                    'job_orders.number',
                    'job_orders.description',
                    'job_orders.status',
                    'job_orders.payment_status',
                    'job_orders.total_amount',
                    'job_orders.quoted_amount',
                    'job_orders.is_rush',
                    'job_orders.released_at',
                    'job_orders.cancelled_at',
                    'job_orders.created_at',
                ])
                ->latest('job_orders.id')
                ->limit(self::RECENT_JOB_ORDERS),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $customers->getCollection()->each(fn (Customer $customer) => $customer->jobOrders
            ->each(fn (JobOrder $jobOrder) => $jobOrder->append(['display_total', 'display_status'])));

        return Inertia::render('admin/Customers', [
            'customers' => $customers,
            'filters' => $request->only(['q']),
        ]);
    }

    /**
     * Correct a customer's details.
     *
     * There is deliberately no delete: a customer's visits and job orders
     * hang off this row. The change is written to the audit trail by the
     * model's AuditObserver.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer updated.')]);

        return back();
    }
}
