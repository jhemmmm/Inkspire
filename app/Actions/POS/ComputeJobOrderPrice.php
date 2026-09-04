<?php

namespace App\Actions\POS;

use App\Models\SystemConfiguration;

class ComputeJobOrderPrice
{
    /**
     * Compute a job order's price breakdown from the Cashier's confirmed
     * line amount, rush fee toggle, and discount input (POS-01).
     *
     * Server-authoritative: this is the only place `total_amount` is ever
     * computed. `$lineAmount` — the Cashier's confirmed line amount, not
     * the live `pricing_database.base_price` — becomes the snapshot, so a
     * later catalog price change never reprices an already-priced job
     * order (RESEARCH.md Pattern 3).
     *
     * @return array{base_price_snapshot: float, rush_fee_amount: float, discount_amount: float, total_amount: float}
     */
    public function __invoke(
        float $lineAmount,
        bool $rushFeeApplied,
        ?string $discountType,
        ?float $discountValue,
    ): array {
        $rushFeeAmount = $rushFeeApplied
            ? round($lineAmount * SystemConfiguration::getFloat('rush_fee_percentage', 0.0) / 100, 2)
            : 0.0;

        $subtotal = $lineAmount + $rushFeeAmount;

        $discountAmount = match ($discountType) {
            'percentage' => round($subtotal * ($discountValue ?? 0.0) / 100, 2),
            'flat' => $discountValue ?? 0.0,
            default => 0.0,
        };

        $totalAmount = max(0.0, round($subtotal - $discountAmount, 2));

        return [
            'base_price_snapshot' => $lineAmount,
            'rush_fee_amount' => $rushFeeAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
        ];
    }
}
