<?php

namespace App\Concerns;

use App\Models\SystemConfiguration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait PricingValidationRules
{
    /**
     * Get the validation rules for a job order's Pricing card fields
     * (POS-01/D-01/D-02/D-03), submitted only on the first pricing/payment
     * save — see SavePricingAndPaymentRequest::rules() for the branch that
     * skips these once total_amount is already set.
     *
     * @return array<string, ValidationRule|array<mixed>|string|\Closure>
     */
    protected function pricingRules(): array
    {
        return [
            'pricing_entry_id' => ['required', 'exists:pricing_database,id'],
            'line_amount' => ['required', 'numeric', 'min:0'],
            'rush_fee_applied' => ['required', 'boolean'],
            'discount_type' => ['nullable', Rule::in(['percentage', 'flat'])],
            'discount_value' => [
                'nullable',
                'numeric',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $discountType = request()->input('discount_type');

                    if ($discountType === 'percentage'
                        && (float) $value > SystemConfiguration::getFloat('discount_cap_percentage', 20.0)) {
                        $fail(__("Discount can't exceed the configured cap."));
                    }

                    if ($discountType === 'flat'
                        && (float) $value > SystemConfiguration::getFloat('discount_cap_flat_amount', 500.0)) {
                        $fail(__("Discount can't exceed the configured cap."));
                    }
                },
            ],
        ];
    }
}
