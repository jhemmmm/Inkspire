<?php

namespace App\Http\Requests\Artist;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateJobOrderQueuePositionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Body-less — the target transition is implied by which named route was
     * hit (next vs forward), not a client-supplied value. Reused across
     * both.
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
