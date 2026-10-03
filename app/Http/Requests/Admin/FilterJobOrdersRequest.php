<?php

namespace App\Http\Requests\Admin;

use App\Enums\JobOrderStatus;
use App\Enums\PaymentStatus;
use App\Models\JobOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterJobOrdersRequest extends FormRequest
{
    /**
     * The Status filter's choices: every stage, plus Released (a released
     * order keeps its last stage as `status`). Cancelled orders are never
     * listed, so they get no choice.
     *
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [...array_column(JobOrderStatus::cases(), 'value'), JobOrder::DISPLAY_RELEASED];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(self::statuses())],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }
}
