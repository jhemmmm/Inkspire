<?php

namespace App\Http\Requests\FrontlineStaff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceJobOrderFileRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Deliberately no mimes:/max: rule (D-02) — a bad file still updates
     * the job order with a ValidationFailed outcome rather than being HTTP-
     * rejected outright.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
        ];
    }
}
