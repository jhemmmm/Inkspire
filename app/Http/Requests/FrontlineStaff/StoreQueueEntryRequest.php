<?php

namespace App\Http\Requests\FrontlineStaff;

use App\Concerns\JobOrderValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreQueueEntryRequest extends FormRequest
{
    use JobOrderValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(
            ['customer_id' => ['required', 'integer', 'exists:customers,id']],
            $this->jobOrdersRules(),
        );
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->jobOrderMessages('job_orders.*.');
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->jobOrderAttributes('job_orders.*.');
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectUnusableTypeAFiles($validator, $this->jobOrderRowPrefixes());
            },
        ];
    }
}
