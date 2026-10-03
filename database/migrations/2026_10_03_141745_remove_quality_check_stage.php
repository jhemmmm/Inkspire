<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Take the retired Quality Check stage out of the data.
     *
     * Production is Start, Done and Undo now: an order goes For Production,
     * Printing, Ready for Pickup. `quality_check` is no longer a
     * JobOrderStatus case, so a row still holding it could not be loaded.
     *
     * An order left in Quality Check becomes Printing, the stage Done and
     * Undo already treated it as. Its history is rewritten the same way,
     * and a log that now reads Printing to Printing recorded only the
     * retired step, so it goes.
     */
    public function up(): void
    {
        DB::table('job_orders')->where('status', 'quality_check')->update(['status' => 'printing']);

        DB::table('production_logs')->where('from_status', 'quality_check')->update(['from_status' => 'printing']);
        DB::table('production_logs')->where('to_status', 'quality_check')->update(['to_status' => 'printing']);

        DB::table('production_logs')
            ->where('from_status', 'printing')
            ->where('to_status', 'printing')
            ->delete();
    }

    /**
     * No down path: once merged into Printing, the orders that had been in
     * Quality Check cannot be told apart from the ones that never were.
     */
    public function down(): void {}
};
