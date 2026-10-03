<?php

namespace App\Http\Requests\Public;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PayOnlineRequest extends FormRequest
{
    /**
     * Public and unauthenticated: the tracking token in the URL is the
     * customer's credential.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the wallet is taken from the customer. The amount is never read
     * from the request; it is always the job order's outstanding balance.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'in:gcash,maya'],
        ];
    }
}
