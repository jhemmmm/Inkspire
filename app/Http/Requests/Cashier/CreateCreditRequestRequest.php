<?php

namespace App\Http\Requests\Cashier;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateCreditRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Open-eligibility per D-08 — any Cashier can request credit for any
     * eligible job order; the real gate is entirely on the Owner-approval
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
     * model — the balance itself is always server-computed.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }
}
