<?php

namespace App\Http\Requests\Admin;

use App\Concerns\SystemConfigValidationRules;
use App\Models\SystemConfiguration;
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
        $configuration = $this->route('configuration');

        if (! $configuration instanceof SystemConfiguration) {
            abort(404);
        }

        return $this->valueRules($configuration->type);
    }
}
