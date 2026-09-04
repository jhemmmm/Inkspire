<?php

namespace App\Concerns;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait PaymentValidationRules
{
    /**
     * Get the validation rules for a job order's Payment card fields
     * (POS-02/POS-03/POS-05).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function paymentRules(): array
    {
        return [
            'payment_method' => ['required', Rule::in([
                PaymentMethod::Cash->value,
                PaymentMethod::BankTransfer->value,
                PaymentMethod::Gcash->value,
                PaymentMethod::Maya->value,
            ])],
            'payment_type' => ['required', Rule::in(['full', 'down'])],
            'amount_tendered' => ['required_if:payment_method,'.PaymentMethod::Cash->value, 'nullable', 'numeric', 'min:0'],
            'reference_number' => ['required_if:payment_method,'.PaymentMethod::BankTransfer->value, 'nullable', 'string', 'max:255'],
            // D-14: no configured minimum down payment — min:0.01 only
            // excludes a literal zero/blank submission, not a business rule.
            'down_payment_amount' => ['required_if:payment_type,down', 'nullable', 'numeric', 'min:0.01'],
        ];
    }
}
