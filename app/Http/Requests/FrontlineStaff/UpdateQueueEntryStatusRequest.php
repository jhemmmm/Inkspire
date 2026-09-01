<?php

namespace App\Http\Requests\FrontlineStaff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQueueEntryStatusRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Body-less — the target state is implied by which named route was
     * hit (call-next vs mark-done), not a client-supplied value.
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
