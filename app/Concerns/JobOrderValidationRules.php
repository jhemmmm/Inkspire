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
            ...$this->printSpecificationRules('job_orders.*.'),
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
            'file' => ['nullable', 'file', 'required_if:type,'.JobOrderType::TypeA->value],
            ...$this->printSpecificationRules(),
        ];
    }

    /**
     * Get the print specification rules shared by both intake paths,
     * prefixed for whichever payload shape they are being merged into.
     *
     * Every field is optional. The intake form has always accepted a bare
     * description, and requiring specifications here would reject the
     * consultation-first (Type B) walk-ins the queue exists to handle.
     *
     * `print_size` and `material` are validated as free strings rather than
     * against the live `specification_options` catalog on purpose: the
     * columns store a label snapshot, so an Owner retiring an option must
     * not start rejecting a re-submitted form that still carries it.
     *
     * ponytail: `deadline` has no upper bound. Add `before:+2 years` if
     * staff start fat-fingering years into the field.
     *
     * `is_rush`'s `nullable` is load-bearing, not decorative: an unchecked
     * reka-ui `Switch` omits its hidden checkbox from the submission
     * entirely, so `required` would reject every non-rush job order. The
     * `boolean` rule accepts the string "1"/"0" the Inertia FormData path
     * delivers as well as a real JSON boolean.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    private function printSpecificationRules(string $prefix = ''): array
    {
        return [
            $prefix.'print_size' => ['nullable', 'string', 'max:255'],
            $prefix.'material' => ['nullable', 'string', 'max:255'],
            $prefix.'quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            $prefix.'deadline' => ['nullable', 'date', 'after_or_equal:today'],
            $prefix.'is_rush' => ['nullable', 'boolean'],
            $prefix.'pricing_entry_id' => ['nullable', 'integer', 'exists:pricing_database,id'],
            // The customer's own questions and instructions, captured at the
            // counter. Roomy because a Type B brief is where the whole job
            // gets described.
            $prefix.'client_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
