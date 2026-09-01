<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait SystemConfigValidationRules
{
    /**
     * Get the validation rules used to validate a system configuration value,
     * driven by the configuration row's own declared `type` column.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function valueRules(string $type): array
    {
        return match ($type) {
            'integer' => ['value' => ['required', 'integer', 'min:0']],
            'decimal' => ['value' => ['required', 'numeric', 'min:0']],
            'boolean' => ['value' => ['required', 'boolean']],
            'array' => [
                'value' => ['required', 'array'],
                'value.*' => ['string'],
            ],
            default => ['value' => ['required', 'string', 'max:255']],
        };
    }
}
