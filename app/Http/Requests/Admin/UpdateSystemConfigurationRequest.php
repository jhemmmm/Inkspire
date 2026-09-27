<?php

namespace App\Http\Requests\Admin;

use App\Concerns\SystemConfigValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemConfigurationRequest extends FormRequest
{
    use SystemConfigValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->valueRules($this->route('configuration')->type);
    }
}
