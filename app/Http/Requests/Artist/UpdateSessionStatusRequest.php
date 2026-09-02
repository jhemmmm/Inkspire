<?php

namespace App\Http\Requests\Artist;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSessionStatusRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Body-less — the target status is implied by which named route was
     * hit (start-break vs end-break vs end-shift), not a client-supplied
     * value. Reused across all three session-status routes.
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
