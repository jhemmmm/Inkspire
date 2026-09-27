<?php

namespace App\Http\Requests\Admin;

use App\Models\SpecificationOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSpecificationOptionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The category is intentionally not editable — allowing it here would
     * let one edit silently invalidate the composite unique key this rule
     * is scoped against.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var SpecificationOption $option */
        $option = $this->route('specificationOption');

        return [
            'label' => [
                'required',
                'string',
                'max:255',
                Rule::unique(SpecificationOption::class, 'label')
                    ->where('category', $option->category->value)
                    ->ignore($option),
            ],
            'is_active' => ['required', 'boolean'],
            'width_inches' => ['nullable', 'numeric', 'min:0.1', 'max:999999'],
            'height_inches' => ['nullable', 'numeric', 'min:0.1', 'max:999999'],
        ];
    }
}
