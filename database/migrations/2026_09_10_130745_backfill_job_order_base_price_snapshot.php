<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give every already-priced job order the base price its receipt prints.
     *
     * The receipt has always had a Base Price line, but rows priced outside
     * the POS flow (seeded demo data) carry `total_amount` with the other
     * three pricing columns left null -- so the receipt read
     * "Base Price P0.00" directly above a P3,000 total.
     *
     * The breakdown is exactly recoverable: total = base + rush - discount,
     * so base = total - rush + discount. Where rush and discount are null
     * there was no rush fee and no discount, which is the same as zero, and
     * the base equals the total.
     */
    public function up(): void
    {
        DB::table('job_orders')
            ->whereNotNull('total_amount')
            ->whereNull('base_price_snapshot')
            ->update([
                'base_price_snapshot' => DB::raw('total_amount - COALESCE(rush_fee_amount, 0) + COALESCE(discount_amount, 0)'),
                'rush_fee_amount' => DB::raw('COALESCE(rush_fee_amount, 0)'),
                'discount_amount' => DB::raw('COALESCE(discount_amount, 0)'),
            ]);
    }

    /**
     * No down path: the nulls this replaced carried no information, so
     * there is nothing to restore them from.
     */
    public function down(): void {}
};
