<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Enforces at the database level what JobOrder::accountsReceivable()'s
     * own docblock already states as a rule (D-08 — at most one open credit
     * request/receivable per job order), closing the gap flagged by WR-04
     * where a double-submitted request could otherwise race past the
     * application-level checks and create two rows for the same job order.
     */
    public function up(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->unique('job_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->dropUnique(['job_order_id']);
        });
    }
};
