<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePricingEntryRequest;
use App\Models\PricingEntry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PricingEntryController extends Controller
{
    /**
     * Show the Admin product and service catalog — the price list behind
     * every intake form's Product/Service select — retired entries
     * included, so they can be restored.
     */
    public function index(): Response
    {
        return Inertia::render('admin/Products', [
            'pricingEntries' => PricingEntry::query()
                ->orderBy('name')
                ->get(['id', 'name', 'base_price', 'unit', 'is_active']),
        ]);
    }

    /**
     * Add a product or service to the catalog.
     */
    public function store(SavePricingEntryRequest $request): RedirectResponse
    {
        PricingEntry::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product added.')]);

        return back();
    }

    /**
     * Rename or reprice an entry, or retire/restore it.
     *
     * There is deliberately no delete: `job_orders.pricing_entry_id` nulls
     * on delete, so removing a row would blank the service on every job
     * order priced from it. Retiring (`is_active = false`) drops the entry
     * from the selects and leaves those job orders intact. A new price never
     * reprices them either — they carry their own `base_price_snapshot`.
     */
    public function update(SavePricingEntryRequest $request, PricingEntry $pricingEntry): RedirectResponse
    {
        $pricingEntry->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return back();
    }
}
