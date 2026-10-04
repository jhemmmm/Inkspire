<?php

namespace App\Http\Requests\Admin;

use App\Models\PricingEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePricingEntryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Shared by store and update: the route's entry is null on store, so
     * `ignore()` only exempts a row when one is being edited.
     *
     * `unit` stays free text on purpose — the shop's own list carries
     * qualifiers like "sq ft (150 installed)". Pricing only reads whether it
     * starts with "sq ft" (QuoteJobOrderLineAmount).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(PricingEntry::class, 'name')->ignore($this->route('pricingEntry')),
            ],
            // Matches the decimal(10,2) column exactly.
            'base_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'unit' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Plain-language messages for the two mistakes an Admin can actually
     * make on this form.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('That product or service is already in the catalog.'),
            'base_price.min' => __('The price cannot be negative.'),
        ];
    }
}
