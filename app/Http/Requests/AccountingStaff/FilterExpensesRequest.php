<?php

namespace App\Http\Requests\AccountingStaff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterExpensesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The route is already gated by the `role:accounting_staff` middleware
     * group.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `required_with` on each side prevents a request supplying only one of
     * the two dates -- the controller treats "both absent" as "apply the
     * This Month default" and anything else as a fully-specified range.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date', 'before_or_equal:today', 'required_with:to'],
            'to' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:from', 'required_with:from'],
        ];
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
