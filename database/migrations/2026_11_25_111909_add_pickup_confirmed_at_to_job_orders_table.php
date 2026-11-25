<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the customer confirmed, from their tracking page, that they
     * received an order staff had already released.
     */
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->timestamp('pickup_confirmed_at')->nullable()->after('released_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn('pickup_confirmed_at');
        });
    }
};
