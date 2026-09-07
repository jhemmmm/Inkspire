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
     * Assign every unnumbered job_orders row a JO-{year}-{seq} number,
     * grouped by the Asia/Manila year of its created_at timestamp and
     * sequenced in creation order — the same shape and per-year reset
     * JobOrder::nextNumberForYear() uses for every number created from
     * this point forward.
     *
     * On MySQL the ALTER TABLE above implicitly commits, so Laravel's
     * per-migration transaction cannot roll it back. This method therefore
     * has to be re-runnable on its own: it takes its own transaction (one
     * bare UPDATE per row otherwise leaves a half-numbered table on a
     * mid-loop failure), skips rows that already carry a number, and
     * continues each year's sequence from the highest number already
     * present rather than restarting at 0001 and colliding on the new
     * unique index.
     *
     * `orderBy('id')` is the tiebreaker for rows created in the same
     * second, which would otherwise be sequenced arbitrarily.
     */
    private function backfillNumbers(): void
    {
        DB::transaction(function (): void {
            $sequences = $this->highestSequencePerYear();

            $rows = DB::table('job_orders')
                ->whereNull('number')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'created_at']);

            foreach ($rows as $row) {
                $year = $this->numberingYearFor($row->created_at);

                $sequences[$year] = ($sequences[$year] ?? 0) + 1;

                DB::table('job_orders')->where('id', $row->id)->update([
                    'number' => sprintf('JO-%d-%04d', $year, $sequences[$year]),
                ]);
            }
        });
    }

    /**
     * The highest sequence already assigned for each year, so a re-run
     * continues the numbering instead of colliding with it.
     *
     * @return array<int, int>
     */
    private function highestSequencePerYear(): array
    {
        $sequences = [];

        foreach (DB::table('job_orders')->whereNotNull('number')->pluck('number') as $number) {
            if (! is_string($number) || preg_match('/^JO-(\d{4})-(\d+)$/', $number, $matches) !== 1) {
                continue;
            }

            $year = (int) $matches[1];

            $sequences[$year] = max($sequences[$year] ?? 0, (int) $matches[2]);
        }

        return $sequences;
    }

    /**
     * The Asia/Manila numbering year for a raw created_at value.
     *
     * created_at is nullable ($table->timestamps()) and Carbon::parse(null)
     * silently returns *now*, which would file an unstamped legacy row
     * under the current year without saying so. Do it explicitly instead.
     */
    private function numberingYearFor(mixed $createdAt): int
    {
        if (! is_string($createdAt)) {
            return (int) Carbon::now()->timezone('Asia/Manila')->format('Y');
        }

        return (int) Carbon::parse($createdAt)->timezone('Asia/Manila')->format('Y');
    }
};
