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
            'job_orders.*.file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,ai,psd,eps', 'required_if:job_orders.*.type,'.JobOrderType::TypeA->value],
        ];
    }

    /**
     * Get the validation rules for a single job order being added to an
     * existing visit (D-15/D-18), outside the combined intake form.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function jobOrderRules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(JobOrderType::class)],
            'file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,ai,psd,eps', 'required_if:type,'.JobOrderType::TypeA->value],
        ];
    }
}
