<?php

namespace App\Http\Requests\Admin;

use App\Concerns\CustomerValidationRules;
use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    use CustomerValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * The same rules Frontline Staff register a customer under, except the
     * customer being edited may keep their own contact number.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return $this->customerRules($customer->id);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_number.unique' => __('Another customer already has that contact number.'),
        ];
    }
}
