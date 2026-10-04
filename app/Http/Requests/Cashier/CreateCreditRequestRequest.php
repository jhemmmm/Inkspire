<?php

namespace App\Http\Requests\Cashier;

use App\Concerns\PricingValidationRules;
use App\Models\JobOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateCreditRequestRequest extends FormRequest
{
    use PricingValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Open-eligibility per D-08 — any Cashier can request credit for any
     * eligible job order; the real gate is entirely on the Admin-approval
     * side, matching CancelJobOrderRequest's simpler-but-analogous pattern.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * No credit-limit/history input to validate per D-08's open-eligibility
     * model — the balance itself is always server-computed. Pricing fields
     * are only validated when On-Credit is the very first pricing/payment
     * action taken on the job order, mirroring
     * SavePricingAndPaymentRequest::rules()'s identical branch — once
     * pricing is no longer editable (JobOrder::pricingIsEditable()),
     * pricing input must never be accepted from this route again (CR-01).
     *
     * @return array<string, ValidationRule|array<mixed>|string|\Closure>
     */
    public function rules(): array
    {
        $jobOrder = $this->route('jobOrder');

        if (! $jobOrder instanceof JobOrder) {
            abort(404);
        }

        return $jobOrder->pricingIsEditable()
            ? $this->pricingRules()
            : [];
    }
}
