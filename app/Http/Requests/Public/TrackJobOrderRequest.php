<?php

namespace App\Http\Requests\Public;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TrackJobOrderRequest extends FormRequest
{
    /**
     * Anyone may look up a job order's public tracking status (TRACK-01) —
     * this is a deliberately unauthenticated, public endpoint.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `number` is nullable so the bare `/track` GET with no query string
     * never fails validation (that's the lookup state, not an error). The
     * suffix is `\d{4,}` (four digits MINIMUM), not `\d{4}` (exactly four)
     * — JobOrder::nextNumberForYear() formats via `sprintf('JO-%d-%04d', ...)`,
     * which zero-pads to a minimum width of 4 digits, so a year that reaches
     * a 5-digit sequence (e.g. JO-2026-10000) is a legitimate number this
     * validation must accept, not reject.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number' => ['nullable', 'string', 'regex:/^JO-\d{4}-\d{4,}$/'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'number.regex' => 'Enter a job order number like JO-2026-0001.',
        ];
    }
}
