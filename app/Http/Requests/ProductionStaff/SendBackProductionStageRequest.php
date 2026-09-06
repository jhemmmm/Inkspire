<?php

namespace App\Http\Requests\ProductionStaff;

use App\Concerns\ProductionLogValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendBackProductionStageRequest extends FormRequest
{
    use ProductionLogValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * The route is already gated by the `role:production_staff` middleware
     * group; there is no per-job-order ownership concept for Production
     * Staff (unlike Artist's assigned_artist_id check).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->sendBackReasonRules();
    }
}
