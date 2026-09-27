<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Capture the print specifications the customer agreed to at intake.
     *
     * `print_size` and `material` are label snapshots, not foreign keys into
     * `specification_options`: retiring a size the shop no longer offers must
     * not blank out the historical job orders printed at that size.
     *
     * `deadline` is deliberately its own column and not `due_at` —
     * EnterProduction overwrites `due_at` from the SLA config the moment a
     * job order enters production, which would silently erase the date the
     * customer was actually promised.
     *
     * Every column is nullable. Job orders created before this migration have
     * no specifications to backfill, and the intake form has always accepted
     * a bare description.
     */
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->string('print_size')->nullable()->after('description');
            $table->string('material')->nullable()->after('print_size');
            $table->unsignedInteger('quantity')->nullable()->after('material');
            $table->date('deadline')->nullable()->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn(['print_size', 'material', 'quantity', 'deadline']);
        });
    }
};
