<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Put the minimum down payment setting in front of Admin on a database
     * that is migrated but not re-seeded, which is how production deploys.
     */
    public function up(): void
    {
        DB::table('system_configurations')->insertOrIgnore([
            'key' => 'down_payment_minimum_percentage',
            'group' => 'business_rules',
            'value' => json_encode(50),
            'type' => 'integer',
            'label' => 'Minimum down payment (%)',
            'description' => 'From 50 to 60. Payments already made count toward it.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_configurations')->where('key', 'down_payment_minimum_percentage')->delete();
    }
};
