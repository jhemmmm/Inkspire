<?php

namespace App\Concerns;

use App\Enums\AccountsReceivableCollectionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait AccountsReceivableValidationRules
{
    /**
     * Get the validation rules for a human-triggered collection status
     * update (D-09/D-10). `Paid` and `WrittenOff` are deliberately excluded
     * from this allowlist -- they are system-set only, never hand-picked
     * from a client request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function collectionStatusRules(): array
    {
        return [
            'collection_status' => [
                'required',
                Rule::in([
                    AccountsReceivableCollectionStatus::Pending->value,
                    AccountsReceivableCollectionStatus::FollowUp->value,
                    AccountsReceivableCollectionStatus::WarningSent->value,
                    AccountsReceivableCollectionStatus::Collections->value,
                ]),
            ],
        ];
    }

    /**
     * Get the validation rules for an Accounting Staff write-off request
     * (D-13) -- a mandatory reason the Owner reads when deciding.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function writeOffReasonRules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
