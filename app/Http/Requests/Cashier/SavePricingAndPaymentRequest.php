<?php

namespace App\Http\Requests\Cashier;

use App\Concerns\PaymentValidationRules;
use App\Concerns\PricingValidationRules;
use App\Enums\TransactionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SavePricingAndPaymentRequest extends FormRequest
{
    use PaymentValidationRules, PricingValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * The route is already gated by the `role:cashier` middleware group;
     * there is no per-job-order ownership concept for Cashier (unlike
     * Artist's assigned_artist_id check).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Pricing fields are only validated on the first pricing/payment save.
     * Once a job order's total_amount is already set (a balance/follow-up
     * payment visit), pricing fields must be entirely ignored rather than
     * re-validated — the price was already snapshotted and must never
     * change after money has moved (RESEARCH.md Pattern 3).
     *
     * @return array<string, ValidationRule|array<mixed>|string|\Closure>
     */
    public function rules(): array
    {
        if ($this->route('jobOrder')->total_amount !== null) {
            return $this->paymentRules();
        }

        return array_merge($this->pricingRules(), $this->paymentRules());
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('payment_type') !== 'down') {
                return;
            }

            $jobOrder = $this->route('jobOrder');
            $downPaymentAmount = (float) $this->input('down_payment_amount', 0);

            // Duplicated rather than injecting PaymentController, since
            // FormRequests are constructed independently of controllers.
            $amountPaid = $jobOrder->transactions()
                ->where('status', TransactionStatus::Completed->value)
                ->sum('amount');

            $remainingBalance = $jobOrder->total_amount !== null
                ? (float) $jobOrder->total_amount - $amountPaid
                : null;

            if ($remainingBalance !== null && $downPaymentAmount > $remainingBalance) {
                $validator->errors()->add('down_payment_amount', __('Down payment cannot exceed the remaining balance.'));
            }
        });
    }
}
