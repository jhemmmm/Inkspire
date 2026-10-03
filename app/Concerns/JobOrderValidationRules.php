<?php

namespace App\Concerns;

use App\Actions\JobOrder\ValidateJobOrderFile;
use App\Enums\FileValidationOutcome;
use App\Enums\JobOrderType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
     * `print_size` is validated as a free string rather than against the
     * live `specification_options` catalog on purpose: the column stores a
     * label snapshot, so an Admin retiring an option must not start
     * rejecting a re-submitted form that still carries it.
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
            $prefix.'quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            $prefix.'width_ft' => ['nullable', 'numeric', 'min:0.01', 'max:1000'],
            $prefix.'height_ft' => ['nullable', 'numeric', 'min:0.01', 'max:1000'],
            $prefix.'deadline' => ['nullable', 'date', 'after_or_equal:today'],
            $prefix.'is_rush' => ['nullable', 'boolean'],
            $prefix.'pricing_entry_id' => ['nullable', 'integer', 'exists:pricing_database,id'],
            // The Frontline override — matches the decimal(10,2) column
            // exactly: 99999999.99 fits.
            $prefix.'quoted_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // The customer's own questions and instructions, captured at the
            // counter. Roomy because a Type B brief is where the whole job
            // gets described.
            $prefix.'client_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Plain-language messages for the two rules whose default wording names
     * the raw key ("The job_orders.0.description field is required."), which
     * means nothing to the person filling in the form.
     *
     * @return array<string, string>
     */
    protected function jobOrderMessages(string $prefix = ''): array
    {
        return [
            $prefix.'description.required' => __('Pick a product or service.'),
            $prefix.'file.required_if' => __('Attach the print-ready file.'),
        ];
    }

    /**
     * Readable names for the job order fields, so every other rule's default
     * message says "quantity" rather than "job_orders.0.quantity".
     *
     * @return array<string, string>
     */
    protected function jobOrderAttributes(string $prefix = ''): array
    {
        return [
            $prefix.'description' => __('product or service'),
            $prefix.'pricing_entry_id' => __('product or service'),
            $prefix.'type' => __('job order type'),
            $prefix.'file' => __('file'),
            $prefix.'print_size' => __('print size'),
            $prefix.'quantity' => __('quantity'),
            $prefix.'width_ft' => __('width'),
            $prefix.'height_ft' => __('height'),
            $prefix.'deadline' => __('deadline'),
            $prefix.'is_rush' => __('rush print'),
            $prefix.'quoted_amount' => __('price'),
            $prefix.'client_notes' => __('notes'),
        ];
    }

    /**
     * Reject a Type A file the shop cannot print before any job order is
     * created for it.
     *
     * Runs as a FormRequest `after()` hook, so a bad file stops the request
     * at validation: no queue entry, no job order and no stored file are left
     * behind, and the reason lands on the file field the staff member has to
     * fix. Only the `Rejected` outcome blocks -- a `NeedsArtist` file (too
     * few pixels for its print size) is still usable work and is created as
     * normal, then routed to the artist pool.
     *
     * @param  list<string>  $prefixes  One key prefix per job order row, e.g. `job_orders.0.` or `` for a single row.
     * @param  bool  $customerFacing  Drop the staff-directed "Ask the customer ..." sentence, for a form the customer fills in themselves.
     */
    protected function rejectUnusableTypeAFiles(Validator $validator, array $prefixes, bool $customerFacing = false): void
    {
        foreach ($prefixes as $prefix) {
            $fileKey = $prefix.'file';

            if ($validator->errors()->has($fileKey) || $this->input($prefix.'type') !== JobOrderType::TypeA->value) {
                continue;
            }

            $file = $this->file($fileKey);

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $printSize = $this->input($prefix.'print_size');
            $widthFt = $this->input($prefix.'width_ft');
            $heightFt = $this->input($prefix.'height_ft');

            $result = app(ValidateJobOrderFile::class)(
                $file,
                is_string($printSize) ? $printSize : null,
                is_numeric($widthFt) ? (float) $widthFt : null,
                is_numeric($heightFt) ? (float) $heightFt : null,
            );

            if ($result['outcome'] === FileValidationOutcome::Rejected) {
                $reason = $result['reason'] ?? __('This file cannot be used for printing.');

                if ($customerFacing) {
                    // ponytail: coupled to the wording of ValidateJobOrderFile's rejection reasons, which end in an "Ask the customer ..." sentence.
                    $reason = Str::before($reason, ' Ask the customer');
                }

                $validator->errors()->add($fileKey, $reason);
            }
        }
    }
}
