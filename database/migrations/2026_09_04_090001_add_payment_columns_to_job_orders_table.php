<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid')->after('status');
            $table->foreignId('pricing_entry_id')->nullable()->after('payment_status')
                ->constrained('pricing_database')->nullOnDelete();
            $table->decimal('base_price_snapshot', 10, 2)->nullable();
            $table->boolean('rush_fee_applied')->default(false);
            $table->decimal('rush_fee_amount', 10, 2)->nullable();
            $table->string('discount_type')->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pricing_entry_id');
            $table->dropColumn([
                'payment_status',
                'base_price_snapshot',
                'rush_fee_applied',
                'rush_fee_amount',
                'discount_type',
                'discount_value',
                'discount_amount',
                'total_amount',
                'cancelled_at',
            ]);
        });
    }
};
