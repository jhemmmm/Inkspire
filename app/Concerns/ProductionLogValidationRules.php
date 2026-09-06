<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait ProductionLogValidationRules
{
    /**
     * Get the validation rules for a Send Back's mandatory reason (D-11).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function sendBackReasonRules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
