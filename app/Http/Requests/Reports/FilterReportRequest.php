<?php

namespace App\Http\Requests\Reports;

use App\Models\QueueEntry;
use App\Support\BusinessTime;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * The route is already gated by that role's route-group middleware;
     * ReportRegistry::isEntitled() is the report-key-level boundary,
     * enforced explicitly in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Mirrors `AccountingStaff\FilterExpensesRequest` exactly (deliberately
     * duplicated rather than shared, per PATTERNS.md) -- `required_with` on
     * each side prevents a request supplying only one of the two dates.
     *
     * "Today" is the shop's Asia/Manila business date, not the server's
     * UTC one -- from midnight to 8am Manila time the browser's today is
     * already tomorrow in UTC, and a plain `before_or_equal:today` rejected
     * the "Today" preset.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $today = QueueEntry::currentBusinessDate();

        return [
            'q' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date', "before_or_equal:{$today}", 'required_with:to'],
            'to' => ['nullable', 'date', "before_or_equal:{$today}", 'after_or_equal:from', 'required_with:from'],
        ];
    }

    /**
     * The requested range as day bounds in the shop's business timezone (so a
     * 7 AM sale is "Today"), defaulting to the month to date.
     *
     * @return array{CarbonInterface, CarbonInterface}
     */
    public function range(): array
    {
        if ($this->filled('from') && $this->filled('to')) {
            return [
                $this->date('from', null, BusinessTime::zone())->startOfDay(),
                $this->date('to', null, BusinessTime::zone())->endOfDay(),
            ];
        }

        $today = BusinessTime::now();

        return [$today->startOfMonth(), $today->endOfDay()];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.after_or_equal' => __("The end date can't be earlier than the start date."),
            'from.before_or_equal' => __('Pick a date on or before today.'),
            'to.before_or_equal' => __('Pick a date on or before today.'),
        ];
    }
}
