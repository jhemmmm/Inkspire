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
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function customerRules(): array
    {
        return [
            'name' => $this->customerNameRules(),
            'contact_number' => $this->contactNumberRules(),
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
     * Get the validation rules used to validate a customer's contact number.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function contactNumberRules(): array
    {
        return ['required', 'string', 'max:20', Rule::unique(Customer::class)];
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
