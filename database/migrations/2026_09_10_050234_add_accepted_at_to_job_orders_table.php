<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When an Artist accepted a job order out of the shared pool. This is
     * the sort key for their own queue, and it deliberately is not
     * `updated_at`: any later write to the row (a payment, a credit
     * request, a status change) bumps updated_at and would silently
     * reshuffle the artist's queue underneath them.
     */
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('assigned_artist_id');
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn('accepted_at');
        });
    }
};
