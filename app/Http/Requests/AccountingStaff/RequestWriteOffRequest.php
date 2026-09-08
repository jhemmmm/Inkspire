<?php

namespace App\Http\Requests\AccountingStaff;

use App\Concerns\AccountsReceivableValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RequestWriteOffRequest extends FormRequest
{
    use AccountsReceivableValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * The route is already gated by the `role:accounting_staff` middleware
     * group -- D-13's "Accounting requests" carries no per-entry ownership
     * concept, matching UpdateCollectionStatusRequest's un-narrowed pattern.
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
        return $this->writeOffReasonRules();
    }
}
