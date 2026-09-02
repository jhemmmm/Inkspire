<?php

namespace App\Http\Requests\Artist;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordDesignVerdictRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Body-less — the target verdict is implied by which named route was
     * hit (approve vs request-changes), not a client-supplied value.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }
}
