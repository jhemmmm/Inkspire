<?php

namespace App\Actions\POS;

use App\Models\JobOrder;

class PriceJobOrder
{
    public function __construct(public ComputeJobOrderPrice $computeJobOrderPrice) {}

    /**
     * Write the server-computed pricing snapshot onto a job order, without
     * saving it. The caller owns the (locked) transaction and the save.
     *
     * This is the only place the eight pricing snapshot columns are filled.
     * `total_amount` is always recomputed here; a client-supplied total is
     * never read (T-05-03).
     *
     * @param  array<string, mixed>  $pricing
     */
    public function __invoke(JobOrder $jobOrder, array $pricing): void
    {
        $discountValue = $pricing['discount_value'] ?? null;

        $computed = ($this->computeJobOrderPrice)(
            (float) $pricing['line_amount'],
            (bool) $pricing['rush_fee_applied'],
            $pricing['discount_type'] ?? null,
            $discountValue !== null ? (float) $discountValue : null,
        );

        $jobOrder->forceFill([
            'pricing_entry_id' => $pricing['pricing_entry_id'],
            'base_price_snapshot' => $computed['base_price_snapshot'],
            'rush_fee_applied' => $pricing['rush_fee_applied'],
            'rush_fee_amount' => $computed['rush_fee_amount'],
            'discount_type' => $pricing['discount_type'] ?? null,
            'discount_value' => $discountValue,
            'discount_amount' => $computed['discount_amount'],
            'total_amount' => $computed['total_amount'],
        ]);
    }
}
