<?php

namespace App\Http\Requests\Artist;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConsultationNotesRequest extends FormRequest
{
    /**
     * No authorize() override — the route's role:artist group middleware
     * is the access gate, matching every other non-Admin FormRequest.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'consultation_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
