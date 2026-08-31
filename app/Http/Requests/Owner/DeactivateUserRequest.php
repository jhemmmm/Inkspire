<?php

namespace App\Http\Requests\Owner;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeactivateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * An Owner/Admin may not deactivate their own account (self-lockout guard).
     */
    public function authorize(): bool
    {
        return $this->user()->isNot($this->route('user'));
    }

    /**
     * Get the validation rules that apply to the request.
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
