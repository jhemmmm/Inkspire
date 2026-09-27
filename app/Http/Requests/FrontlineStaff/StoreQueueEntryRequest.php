<?php

namespace App\Http\Requests\FrontlineStaff;

use App\Concerns\JobOrderValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreQueueEntryRequest extends FormRequest
{
    use JobOrderValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(
            ['customer_id' => ['required', 'integer', 'exists:customers,id']],
            $this->jobOrdersRules(),
        );
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $rows = $this->input('job_orders');

                $prefixes = is_array($rows)
                    ? array_map(fn (int|string $index): string => "job_orders.{$index}.", array_keys($rows))
                    : [];

                $this->rejectUnusableTypeAFiles($validator, $prefixes);
            },
        ];
    }
}
