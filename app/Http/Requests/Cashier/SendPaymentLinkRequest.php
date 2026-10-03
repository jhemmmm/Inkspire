<?php

namespace App\Http\Requests\Cashier;

use App\Concerns\PricingValidationRules;
use App\Models\JobOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendPaymentLinkRequest extends FormRequest
{
    use PricingValidationRules;

    /**
     * The route is already gated by the `role:cashier` middleware group.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Pricing fields are only validated while the price can still be set;
     * once money or a credit decision touches the job order, the snapshot
     * is final and pricing input is ignored.
     *
     * @return array<string, ValidationRule|array<mixed>|string|\Closure>
     */
    public function rules(): array
    {
        $jobOrder = $this->route('jobOrder');

        return $jobOrder instanceof JobOrder && $jobOrder->pricingIsEditable()
            ? $this->pricingRules()
            : [];
    }
}
