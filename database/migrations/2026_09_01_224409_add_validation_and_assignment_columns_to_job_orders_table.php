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
            $table->foreignId('assigned_artist_id')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->text('validation_failure_reason')->nullable()->after('file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_artist_id');
            $table->dropColumn('validation_failure_reason');
        });
    }
};
