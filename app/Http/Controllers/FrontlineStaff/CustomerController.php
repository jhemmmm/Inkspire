<?php

namespace App\Http\Controllers\FrontlineStaff;

use App\Http\Controllers\Controller;
use App\Http\Requests\FrontlineStaff\SearchCustomersRequest;
use App\Http\Requests\FrontlineStaff\StoreCustomerRequest;
use App\Models\Customer;
use App\Models\QueueEntry;
use Illuminate\Http\RedirectResponse;
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
                    ->orWhere('contact_number', 'like', "%{$term}%"))
                ->orderBy('name')
                ->get();
        }

        return Inertia::render('frontline-staff/NewVisit', [
            'customers' => $customers,
            'filters' => $request->only(['q']),
            'selectedCustomer' => $request->filled('customer')
                ? Customer::find($request->integer('customer'))
                : null,
            'confirmedQueueEntry' => $request->filled('queueEntry')
                ? QueueEntry::with('jobOrders:id,queue_entry_id,description,type')->find($request->integer('queueEntry'))
                : null,
        ]);
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
