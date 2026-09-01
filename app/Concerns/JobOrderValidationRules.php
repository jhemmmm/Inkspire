<?php

namespace App\Concerns;

use App\Enums\JobOrderType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait JobOrderValidationRules
{
    /**
     * Get the validation rules for a repeatable array of job order rows
     * submitted alongside a queue entry (D-14 — one combined save).
     *
     * The file field's `required_if` parameter interpolates
     * JobOrderType::TypeA->value rather than hardcoding the literal
     * `type_a` a second time, so the rule can never drift from the enum's
     * own value.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function jobOrdersRules(): array
    {
        return [
            'job_orders' => ['required', 'array', 'min:1'],
            'job_orders.*.description' => ['required', 'string', 'max:255'],
            'job_orders.*.type' => ['required', Rule::enum(JobOrderType::class)],
            'job_orders.*.file' => ['nullable', 'file', 'required_if:job_orders.*.type,'.JobOrderType::TypeA->value],
        ];
    }
}
