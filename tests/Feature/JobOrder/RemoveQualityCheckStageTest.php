<?php

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\ProductionLog;
use Illuminate\Support\Facades\DB;

function removeQualityCheckStage(): void
{
    (require database_path('migrations/2026_10_03_141745_remove_quality_check_stage.php'))->up();
}

test('an order left in Quality Check becomes Printing', function () {
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);
    DB::table('job_orders')->where('id', $jobOrder->id)->update(['status' => 'quality_check']);

    removeQualityCheckStage();

    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Printing);
});

test('the history keeps every real move and drops the step into Quality Check', function () {
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForPickup->value]);
    $started = ProductionLog::factory()->for($jobOrder)->create(['from_status' => 'for_production', 'to_status' => 'printing']);
    $intoQualityCheck = ProductionLog::factory()->for($jobOrder)->create(['from_status' => 'for_production', 'to_status' => 'printing']);
    $outOfQualityCheck = ProductionLog::factory()->for($jobOrder)->create(['from_status' => 'for_production', 'to_status' => 'ready_for_pickup']);
    DB::table('production_logs')->where('id', $intoQualityCheck->id)->update(['from_status' => 'printing', 'to_status' => 'quality_check']);
    DB::table('production_logs')->where('id', $outOfQualityCheck->id)->update(['from_status' => 'quality_check']);

    removeQualityCheckStage();

    $history = DB::table('production_logs')->where('job_order_id', $jobOrder->id)->orderBy('id')->get()
        ->map(fn (object $log): array => [$log->id, $log->from_status, $log->to_status])
        ->all();
    expect($history)->toBe([
        [$started->id, 'for_production', 'printing'],
        [$outOfQualityCheck->id, 'printing', 'ready_for_pickup'],
    ]);
});

test('orders and history that never touched Quality Check are left alone', function () {
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForPickup->value]);
    $log = ProductionLog::factory()->for($jobOrder)->create(['from_status' => 'printing', 'to_status' => 'ready_for_pickup']);

    removeQualityCheckStage();

    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::ReadyForPickup)
        ->and($log->fresh()->only(['from_status', 'to_status']))->toBe([
            'from_status' => JobOrderStatus::Printing,
            'to_status' => JobOrderStatus::ReadyForPickup,
        ]);
});
