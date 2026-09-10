<?php

namespace App\Concerns;

use App\Models\SystemConfiguration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ExpenseValidationRules
{
    /**
     * Get the validation rules for recording/editing an expense (D-13/D-14).
     * `category` is validated against the current
     * `expense_categories` config list, never a hardcoded set.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function expenseRules(): array
    {
        return [
            'category' => ['required', 'string', Rule::in(SystemConfiguration::getArray('expense_categories', []))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get the validation rules for voiding an expense (D-11) -- a mandatory
     * reason, matching AccountsReceivableValidationRules::writeOffReasonRules().
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function voidReasonRules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
