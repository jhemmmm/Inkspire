<?php

namespace App\Http\Requests\Cashier;

use App\Actions\POS\ComputeJobOrderPrice;
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

            if ($jobOrder->total_amount !== null) {
                $remainingBalance = $jobOrder->outstandingBalance();
            } else {
                // First pricing/payment visit (WR-01) — no total_amount
                // snapshot exists yet to check the down payment against.
                // If the pricing fields themselves already failed their own
                // rules, skip this check entirely rather than compute a
                // meaningless total from invalid/missing input; those
                // errors already block submission.
                if ($validator->errors()->hasAny(['pricing_entry_id', 'line_amount', 'rush_fee_applied', 'discount_type', 'discount_value'])) {
                    return;
                }

                $computed = app(ComputeJobOrderPrice::class)(
                    (float) $this->input('line_amount', 0),
                    (bool) $this->input('rush_fee_applied', false),
                    $this->input('discount_type'),
                    $this->input('discount_value') !== null ? (float) $this->input('discount_value') : null,
                );

                $remainingBalance = $computed['total_amount'] - $amountPaid;
            }

            if ($downPaymentAmount > $remainingBalance) {
                $validator->errors()->add('down_payment_amount', __('Down payment cannot exceed the remaining balance.'));
            }
        });
    }
}
