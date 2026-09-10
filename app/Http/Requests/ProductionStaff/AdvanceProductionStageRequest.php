<?php

namespace App\Http\Requests\ProductionStaff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdvanceProductionStageRequest extends FormRequest
{
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
     * Body-less — the next stage is always derived server-side from the
     * job order's own current status (T-06-06-01), never from any
     * client-supplied value.
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
