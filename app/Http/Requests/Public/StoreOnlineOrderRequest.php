<?php

namespace App\Http\Requests\Public;

use App\Concerns\CustomerValidationRules;
use App\Concerns\JobOrderValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOnlineOrderRequest extends FormRequest
{
    use CustomerValidationRules, JobOrderValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * Two rules are dropped on purpose, because validated() only returns keys
     * that have a rule:
     *
     * `quoted_amount` — a price posted by a visitor never reaches
     * CreateJobOrder, and the catalog computes the quote.
     *
     * `description` — the product is required instead, and the controller
     * copies its catalog name. A visitor's own text would otherwise be mailed
     * by the shop to whatever address they typed, links and all.
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
            ...Arr::except($this->jobOrdersRules(), ['job_orders.*.quoted_amount', 'job_orders.*.description']),
            'job_orders' => ['required', 'array', 'min:1', 'max:5'],
            'job_orders.*.pricing_entry_id' => ['required', 'integer', Rule::exists('pricing_database', 'id')->where('is_active', true)],
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
            'job_orders.*.pricing_entry_id.required' => __('Pick a product or service.'),
            'job_orders.*.pricing_entry_id.exists' => __('Pick a product or service from the list.'),
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
                $this->rejectUnusableTypeAFiles($validator, $this->jobOrderRowPrefixes(), customerFacing: true);
            },
        ];
    }
}
