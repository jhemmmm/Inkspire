<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Split the daily queue into a rush lane and a regular lane.
     *
     * The shop calls rush tickets R-001 and regular ones A-001, and each
     * lane counts from 1 independently -- so the number alone is no longer
     * unique for a business day, and the daily unique index has to grow the
     * prefix to match. Existing entries are all regular: rush was a job
     * order flag with no bearing on the ticket until now.
     */
    public function up(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->string('queue_prefix', 1)->default('A')->after('queue_date');
        });

        Schema::table('queue_entries', function (Blueprint $table) {
            $table->dropUnique(['queue_date', 'queue_number']);
            $table->unique(['queue_date', 'queue_prefix', 'queue_number']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Restoring the old two-column unique index can fail where both lanes
     * have issued the same number on the same day -- which is the normal
     * state once this ships. Rolling back therefore needs the rush rows
     * renumbered or removed first; it is not automatic.
     */
    public function down(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->dropUnique(['queue_date', 'queue_prefix', 'queue_number']);
            $table->dropColumn('queue_prefix');
        });

        Schema::table('queue_entries', function (Blueprint $table) {
            $table->unique(['queue_date', 'queue_number']);
        });
    }
};
