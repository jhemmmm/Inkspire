<?php

namespace App\Http\Requests\Artist;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendForReviewRequest extends FormRequest
{
    /**
     * No authorize() override — the route's role:artist group middleware
     * is the access gate, matching every other non-Owner FormRequest.
     *
     * Tighter than FrontlineStaff's ReplaceJobOrderFileRequest (T-04-08):
     * this endpoint's only legitimate producer is the app's own canvas
     * export (Plan 04-04), not an arbitrary user upload.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'image', 'mimes:png'],
        ];
    }
}
