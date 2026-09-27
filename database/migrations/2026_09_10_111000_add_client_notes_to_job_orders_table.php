<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The questions and instructions the customer gave at the counter.
     *
     * Deliberately separate from `consultation_notes`: that column is the
     * artist's own working record, written from their workspace. Overloading
     * one column would let an artist's edit silently overwrite what the
     * customer actually asked for.
     */
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->text('client_notes')->nullable()->after('deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn('client_notes');
        });
    }
};
