<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the intake-time price quote columns and drop `material`.
     *
     * `width_ft`/`height_ft` capture the dimensions Frontline enters for a
     * sq-ft-priced service. `quoted_amount` is the line amount before rush
     * and discount — mirrors `base_price_snapshot`'s precision, but is a
     * distinct column: `total_amount` stays the Cashier-only sentinel every
     * PayMongo/credit/AR code path reads as "unpriced", so intake must never
     * write it.
     *
     * `material` is removed: the catalog entry Product/Service selects *is*
     * the material, so the field only ever duplicated it.
     */
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->decimal('width_ft', 8, 2)->nullable()->after('quantity');
            $table->decimal('height_ft', 8, 2)->nullable()->after('width_ft');
            $table->decimal('quoted_amount', 10, 2)->nullable()->after('pricing_entry_id');
            $table->dropColumn('material');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->string('material')->nullable()->after('print_size');
            $table->dropColumn(['width_ft', 'height_ft', 'quoted_amount']);
        });
    }
};
