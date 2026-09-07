<?php

use App\Models\JobOrder;
use Illuminate\Support\Facades\DB;

/**
 * The backfill is a private helper on the migration's anonymous class. It
 * runs after an ALTER TABLE that implicitly commits on MySQL, so it has to
 * survive being re-run on its own — which is exactly what these exercise.
 */
function backfillJobOrderNumbers(): void
{
    $migration = require database_path('migrations/2026_09_05_120000_add_number_and_due_at_to_job_orders_table.php');

    (new ReflectionMethod($migration, 'backfillNumbers'))->invoke($migration);
}

test('the backfill numbers only unnumbered rows and continues the sequence from the highest existing number', function () {
    $numbered = JobOrder::factory()->create(['number' => 'JO-2026-0007']);
    $unnumbered = JobOrder::factory()->create();

    DB::table('job_orders')->where('id', $unnumbered->id)->update([
        'number' => null,
        'created_at' => '2026-03-01 00:00:00',
    ]);
    DB::table('job_orders')->where('id', $numbered->id)->update([
        'created_at' => '2026-02-01 00:00:00',
    ]);

    backfillJobOrderNumbers();

    expect($numbered->fresh()->number)->toBe('JO-2026-0007');
    expect($unnumbered->fresh()->number)->toBe('JO-2026-0008');
});

test('the backfill is re-runnable and never renumbers a row it already numbered', function () {
    $jobOrder = JobOrder::factory()->create();

    DB::table('job_orders')->where('id', $jobOrder->id)->update([
        'number' => null,
        'created_at' => '2026-03-01 00:00:00',
    ]);

    backfillJobOrderNumbers();
    $firstPass = $jobOrder->fresh()->number;

    backfillJobOrderNumbers();

    expect($firstPass)->toBe('JO-2026-0001');
    expect($jobOrder->fresh()->number)->toBe('JO-2026-0001');
});

test('the backfill files a row with no created_at under the current numbering year instead of guessing silently', function () {
    $jobOrder = JobOrder::factory()->create();

    DB::table('job_orders')->where('id', $jobOrder->id)->update([
        'number' => null,
        'created_at' => null,
    ]);

    backfillJobOrderNumbers();

    expect($jobOrder->fresh()->number)->toBe(sprintf('JO-%d-0001', JobOrder::currentNumberingYear()));
});

test('the backfill sequences rows created in the same second by id, not arbitrarily', function () {
    $first = JobOrder::factory()->create();
    $second = JobOrder::factory()->create();

    DB::table('job_orders')->whereIn('id', [$first->id, $second->id])->update([
        'number' => null,
        'created_at' => '2026-04-01 09:00:00',
    ]);

    backfillJobOrderNumbers();

    expect($first->fresh()->number)->toBe('JO-2026-0001');
    expect($second->fresh()->number)->toBe('JO-2026-0002');
});
