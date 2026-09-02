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
            $table->text('consultation_notes')->nullable()->after('validation_failure_reason');
            $table->timestamp('queue_deprioritized_at')->nullable()->after('consultation_notes');
            $table->boolean('not_appeared')->default(false)->after('queue_deprioritized_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn(['consultation_notes', 'queue_deprioritized_at', 'not_appeared']);
        });
    }
};
