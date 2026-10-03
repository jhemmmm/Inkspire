<?php

namespace App\Http\Requests\Artist;

use App\Concerns\CustomerValidationRules;
use App\Concerns\JobOrderValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
                // The shared rule infers its unique column from the attribute name,
                // which is wrong under the `customer.` prefix.
                'customer.contact_number' => ['required', 'string', 'max:20', Rule::unique('customers', 'contact_number')],
                'customer.email' => $this->customerEmailRules(),
                'customer.address' => $this->addressRules(),
            ];
        }

        return array_merge($rules, $this->jobOrdersRules());
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

                $this->rejectUnusableTypeAFiles($validator, $prefixes);
            },
        ];
    }
}
