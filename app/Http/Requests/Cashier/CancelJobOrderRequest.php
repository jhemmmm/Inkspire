<?php

namespace App\Http\Requests\Cashier;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelJobOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Role-gated by the `role:cashier` route middleware; there is no
     * per-row ownership concept for Cashier, matching
     * DeactivateUserRequest's simpler-but-analogous pattern.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
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
