<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

        $this->backfillNumbers();
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

    /**
     * Assign every existing job_orders row a JO-{year}-{seq} number,
     * grouped by the Asia/Manila year of its created_at timestamp and
     * sequenced in creation order — the same shape and per-year reset
     * JobOrder::nextNumberForYear() uses for every number created from
     * this point forward.
     */
    private function backfillNumbers(): void
    {
        $rows = DB::table('job_orders')->orderBy('created_at')->get(['id', 'created_at']);

        $sequences = [];

        foreach ($rows as $row) {
            $year = (int) Carbon::parse($row->created_at)->timezone('Asia/Manila')->format('Y');

            $sequences[$year] = ($sequences[$year] ?? 0) + 1;

            DB::table('job_orders')->where('id', $row->id)->update([
                'number' => sprintf('JO-%d-%04d', $year, $sequences[$year]),
            ]);
        }
    }
};
