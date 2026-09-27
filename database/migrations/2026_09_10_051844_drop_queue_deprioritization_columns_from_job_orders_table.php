<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Both columns existed only to serve the artist queue's Not Appeared
     * button and Forward's "sink to the back of my own list" behaviour.
     * Not Appeared is gone — an Artist works the job, they do not manage
     * the customer — and Forward now hands the job back to the shared pool
     * instead of reordering one artist's queue, so nothing reads or writes
     * either column any more.
     *
     * They carry transient queue position only, never business history, so
     * dropping them loses nothing recoverable.
     */
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn(['queue_deprioritized_at', 'not_appeared']);
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->timestamp('queue_deprioritized_at')->nullable()->after('consultation_notes');
            $table->boolean('not_appeared')->default(false)->after('queue_deprioritized_at');
        });
    }
};
