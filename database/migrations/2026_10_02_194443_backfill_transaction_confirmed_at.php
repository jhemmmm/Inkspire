<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give every completed payment the confirmation time the reports need.
     *
     * `confirmed_at` was missing from Transaction's fillable list, so every
     * Cash, Bank Transfer and cancellation-fee payment recorded at the
     * counter was saved with it null -- and each report filters on that
     * column, so none of that money ever appeared in one.
     *
     * Those payments are confirmed the moment they are recorded, so
     * `created_at` is the exact value that was dropped.
     */
    public function up(): void
    {
        DB::table('transactions')
            ->where('status', 'completed')
            ->whereNull('confirmed_at')
            ->update(['confirmed_at' => DB::raw('created_at')]);
    }

    /**
     * No down path: the nulls this replaced carried no information, so
     * there is nothing to restore them from.
     */
    public function down(): void {}
};
