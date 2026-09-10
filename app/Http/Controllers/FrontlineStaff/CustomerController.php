<?php

namespace App\Http\Controllers\FrontlineStaff;

use App\Http\Controllers\Controller;
use App\Http\Requests\FrontlineStaff\SearchCustomersRequest;
use App\Http\Requests\FrontlineStaff\StoreCustomerRequest;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\QueueEntry;
use App\Models\SpecificationOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * Show the customer search / register / new-visit screen.
     */
    public function index(SearchCustomersRequest $request): Response
    {
        $customers = collect();

        if ($request->filled('q')) {
            $term = addcslashes((string) $request->string('q'), '%_\\');

            $customers = Customer::query()
                ->where(fn ($query) => $query
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('organization', 'like', "%{$term}%")
                    ->orWhere('contact_number', 'like', "%{$term}%"))
                ->orderBy('name')
                ->get();
        }

        $selectedCustomer = $request->filled('customer')
            ? Customer::find($request->integer('customer'))
            : null;

        return Inertia::render('frontline-staff/NewVisit', [
            'customers' => $customers,
            'specificationOptions' => SpecificationOption::activeLabelsByCategory(),
            'pricingEntries' => PricingEntry::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'base_price', 'unit']),
            'filters' => $request->only(['q']),
            'selectedCustomer' => $selectedCustomer,
            'customerJobOrders' => $this->jobOrderHistoryFor($selectedCustomer),
            'confirmedQueueEntry' => $request->filled('queueEntry')
                ? QueueEntry::with([
                    'jobOrders:id,queue_entry_id,number,description,type,status,validation_failure_reason,assigned_artist_id,tracking_token',
                    'jobOrders.assignedArtist:id,name',
                ])->find($request->integer('queueEntry'))
                : null,
            // Built server-side so the QR encodes the deployment's real scheme
            // and host, rather than whatever the staff member's browser
            // happens to be pointed at.
            'trackingBaseUrl' => url('track'),
        ]);
    }

    /**
     * The selected customer's 20 most recent job orders, so staff can see
     * what they ordered last time before starting a new one (HIST-01).
     *
     * Expressed as ONE constrained query over the customer → queueEntries →
     * jobOrders path rather than eager-loading the visit tree and flattening
     * it, precisely because the latter fans out with the customer's visit
     * count. Ordered by `id` descending rather than `created_at`, so several
     * job orders created within the same second of one visit still sort
     * deterministically.
     *
     * This is Frontline Staff's own authenticated screen, which already
     * renders this same customer's name, contact number, email and address —
     * their PII is fine here. Do not mistake this for the public boundary
     * TrackingController defends.
     *
     * @return Collection<int, JobOrder>
     */
    private function jobOrderHistoryFor(?Customer $customer): Collection
    {
        if ($customer === null) {
            return collect();
        }

        return JobOrder::query()
            ->whereRelation('queueEntry', 'customer_id', $customer->id)
            ->latest('id')
            ->limit(20)
            ->get(['id', 'number', 'description', 'type', 'status', 'created_at']);
    }

    /**
     * Register a new customer, gated behind a zero-result search (D-04).
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name registered. Continue to generate a queue number below.', ['name' => $customer->name]),
        ]);

        return to_route('frontline-staff.new-visit', ['customer' => $customer->id]);
    }
}
