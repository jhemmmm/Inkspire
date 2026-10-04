<?php

namespace App\Concerns;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait CustomerValidationRules
{
    /**
     * Get the validation rules used to validate a customer's full profile.
     *
     * Pass the id of the customer being edited so their own contact number
     * is not reported as already taken.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function customerRules(?int $customerId = null): array
    {
        return [
            'name' => $this->customerNameRules(),
            'organization' => $this->organizationRules(),
            'contact_number' => $this->contactNumberRules($customerId),
            'email' => $this->customerEmailRules(),
            'address' => $this->addressRules(),
        ];
    }

    /**
     * Get the validation rules used to validate a customer's name.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function customerNameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate the organization a customer
     * is buying on behalf of.
     *
     * Optional: most walk-ins are private individuals, and requiring it would
     * force staff to invent a value for them.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function organizationRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate a customer's contact number.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function contactNumberRules(?int $customerId = null): array
    {
        return [
            'required',
            'string',
            'max:20',
            Rule::unique(Customer::class, 'contact_number')->ignore($customerId),
        ];
    }

    /**
     * Get the validation rules used to validate a customer's email.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function customerEmailRules(): array
    {
        return ['required', 'string', 'email', 'max:255'];
    }

    /**
     * Get the validation rules used to validate a customer's address.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function addressRules(): array
    {
        return ['required', 'string', 'max:500'];
    }
}
