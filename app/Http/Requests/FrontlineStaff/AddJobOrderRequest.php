<?php

namespace App\Http\Requests\FrontlineStaff;

use App\Concerns\JobOrderValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AddJobOrderRequest extends FormRequest
{
    use JobOrderValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->jobOrderRules();
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->rejectUnusableTypeAFiles($validator, ['']),
        ];
    }
}
