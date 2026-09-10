<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The staff-declared urgency flag, captured at the counter by Frontline
 * Staff at intake.
 *
 * Deliberately distinct from the Production Board's due-date heuristic:
 * that heuristic answers "is this urgent by the clock", this column answers
 * "did the customer ask for urgency". ProductionBoardController::index()
 * reconciles the two by OR-ing them into the in-memory value it displays,
 * and never saves that widened value back.
 *
 * NOT NULL with a false default so no downstream reader ever has to handle
 * a third state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->boolean('is_rush')->default(false)->after('deadline');
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn('is_rush');
        });
    }
};
