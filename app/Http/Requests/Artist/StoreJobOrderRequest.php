<?php

namespace App\Http\Requests\Artist;

use App\Concerns\CustomerValidationRules;
use App\Concerns\JobOrderValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreJobOrderRequest extends FormRequest
{
    use CustomerValidationRules, JobOrderValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'customer_id' => ['nullable', 'required_without:customer', 'integer', 'exists:customers,id'],
            'customer' => ['nullable', 'required_without:customer_id', 'array'],
        ];

        if (! $this->filled('customer_id')) {
            $rules += [
                'customer.name' => $this->customerNameRules(),
                'customer.organization' => $this->organizationRules(),
                'customer.contact_number' => $this->contactNumberRules(),
                'customer.email' => $this->customerEmailRules(),
                'customer.address' => $this->addressRules(),
            ];
        }

        return array_merge($rules, $this->jobOrdersRules());
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required_without' => __('Pick a customer, or register a new one.'),
            ...$this->jobOrderMessages('job_orders.*.'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer.name' => __('name'),
            'customer.organization' => __('organization'),
            'customer.contact_number' => __('contact number'),
            'customer.email' => __('email'),
            'customer.address' => __('address'),
            ...$this->jobOrderAttributes('job_orders.*.'),
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectUnusableTypeAFiles($validator, $this->jobOrderRowPrefixes());
            },
        ];
    }
}
