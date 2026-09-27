<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait AccountsReceivableValidationRules
{
    /**
     * Get the validation rules for an Accounting Staff write-off request
     * (D-13) -- a mandatory reason the Admin reads when deciding.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function writeOffReasonRules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
