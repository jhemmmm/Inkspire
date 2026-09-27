<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SpecificationCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSpecificationOptionRequest;
use App\Http\Requests\Admin\UpdateSpecificationOptionRequest;
use App\Models\SpecificationOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpecificationOptionController extends Controller
{
    /**
     * Show the Admin print specification catalog, one panel per
     * category, so the list behind the intake form's Print Size select
     * can be maintained without a developer.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('admin/Specifications', [
            'categories' => collect(SpecificationCategory::cases())
                ->map(fn (SpecificationCategory $category): array => [
                    'value' => $category->value,
                    'label' => $category->label(),
                ])
                ->all(),
            'options' => SpecificationOption::query()
                ->orderBy('category')
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(['id', 'category', 'label', 'width_inches', 'height_inches', 'is_active', 'sort_order'])
                ->groupBy(fn (SpecificationOption $option): string => $option->category->value),
        ]);
    }

    /**
     * Add an option to one of the specification catalogs.
     *
     * New options land at the end of their catalog: `sort_order` is one past
     * the current maximum *for that category*, not a global count.
     */
    public function store(StoreSpecificationOptionRequest $request): RedirectResponse
    {
        $category = SpecificationCategory::from($request->validated('category'));

        SpecificationOption::create([
            'category' => $category,
            'label' => $request->validated('label'),
            'width_inches' => $request->validated('width_inches'),
            'height_inches' => $request->validated('height_inches'),
            'is_active' => true,
            'sort_order' => (int) SpecificationOption::query()->category($category)->max('sort_order') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Specification added.')]);

        return back();
    }

    /**
     * Rename an option, or retire/restore it.
     *
     * Retiring (`is_active = false`) is the non-destructive option: it drops
     * the entry from the intake form's select while leaving every job order
     * that already carries the label untouched.
     */
    public function update(UpdateSpecificationOptionRequest $request, SpecificationOption $specificationOption): RedirectResponse
    {
        $specificationOption->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Specification updated.')]);

        return back();
    }

    /**
     * Permanently remove an option from its catalog.
     *
     * Safe to do outright: job orders snapshot the label into their own
     * `print_size` column rather than referencing this row, so deleting a
     * retired size cannot orphan or blank historical orders.
     */
    public function destroy(Request $request, SpecificationOption $specificationOption): RedirectResponse
    {
        $specificationOption->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Specification deleted.')]);

        return back();
    }
}
