<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Record when a write-off is approved.
     */
    public function up(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->timestamp('written_off_at')->nullable()->after('collection_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->dropColumn(['written_off_at']);
        });
    }
};
