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
            $table->string('number')->nullable()->unique()->after('id');
            $table->timestamp('due_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            // Explicit dropUnique() before dropColumn(): on SQLite <3.35 (no
            // native ALTER TABLE DROP COLUMN), Laravel recreates the table
            // and otherwise leaves job_orders_number_unique behind pointing
            // at the now-dropped column, corrupting every future insert with
            // a stray UNIQUE constraint violation.
            $table->dropUnique(['number']);
            $table->dropColumn(['number', 'due_at']);
        });
    }
};
