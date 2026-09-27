<?php

namespace App\Actions\POS;

use App\Models\PricingEntry;

class QuoteJobOrderLineAmount
{
    /**
     * Compute the intake-time line amount for a job order from its
     * catalog entry, dimensions, and quantity (D-01).
     *
     * ponytail: `unit` is free text on `pricing_database`; this is a
     * prefix match on "sq ft" — add a unit enum on pricing_database later
     * if more unit shapes appear.
     */
    public function __invoke(PricingEntry $entry, ?float $widthFt, ?float $heightFt, int $quantity): ?float
    {
        if (str_starts_with(strtolower($entry->unit ?? ''), 'sq ft')) {
            if ($widthFt === null || $heightFt === null) {
                return null;
            }

            return round((float) $entry->base_price * $widthFt * $heightFt * $quantity, 2);
        }

        return round((float) $entry->base_price * $quantity, 2);
    }
}
