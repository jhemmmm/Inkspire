<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RequestDesignChangesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('message'))) {
            $this->merge(['message' => trim($this->input('message'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['message' => ['required', 'string', 'max:5000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'message.required' => __('Please describe the changes you would like.'),
            'message.max' => __('The message must not exceed 5,000 characters.'),
        ];
    }
}
