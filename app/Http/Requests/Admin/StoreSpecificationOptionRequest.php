<?php

namespace App\Http\Requests\Admin;

use App\Enums\SpecificationCategory;
use App\Models\SpecificationOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpecificationOptionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Uniqueness is scoped to the category, matching the table's composite
     * unique index — only a duplicate *within* one catalog is an error.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(SpecificationCategory::class)],
            'label' => [
                'required',
                'string',
                'max:255',
                Rule::unique(SpecificationOption::class, 'label')
                    ->where('category', $this->string('category')->toString()),
            ],
            ...$this->dimensionRules(),
        ];
    }

    /**
     * Printed dimensions, in inches.
     *
     * Only meaningful for the print_size category, and optional even there --
     * "Custom Size" legitimately has none. Recording them is what lets
     * ValidateJobOrderFile judge whether a customer's file is sharp enough for
     * the size ordered, so the form explains that rather than presenting two
     * unexplained number boxes.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function dimensionRules(): array
    {
        return [
            'width_inches' => ['nullable', 'numeric', 'min:0.1', 'max:999999'],
            'height_inches' => ['nullable', 'numeric', 'min:0.1', 'max:999999'],
        ];
    }
}
