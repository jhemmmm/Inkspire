<?php

namespace App\Http\Requests\Public;

use App\Concerns\CustomerValidationRules;
use App\Concerns\JobOrderValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class StoreOnlineOrderRequest extends FormRequest
{
    use CustomerValidationRules, JobOrderValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * The `quoted_amount` rule is dropped on purpose: validated() only returns
     * keys that have a rule, so a price posted by a visitor never reaches
     * CreateJobOrder and the catalog computes the quote.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => $this->customerNameRules(),
            'organization' => $this->organizationRules(),
            // No unique rule: a returning customer must be able to order.
            'contact_number' => ['required', 'string', 'max:20'],
            'email' => $this->customerEmailRules(),
            'address' => $this->addressRules(),
            ...Arr::except($this->jobOrdersRules(), ['job_orders.*.quoted_amount']),
            'job_orders' => ['required', 'array', 'min:1', 'max:5'],
            // Honeypot: hidden from people, filled in by form-stuffing bots.
            'website' => ['prohibited'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->jobOrderMessages('job_orders.*.'),
            'job_orders.max' => __('You can order up to 5 items at a time.'),
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
            'contact_number' => __('mobile number'),
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
                $rows = $this->input('job_orders');

                $prefixes = is_array($rows)
                    ? array_map(fn (int|string $index): string => "job_orders.{$index}.", array_keys($rows))
                    : [];

                $this->rejectUnusableTypeAFiles($validator, $prefixes, customerFacing: true);
            },
        ];
    }
}
