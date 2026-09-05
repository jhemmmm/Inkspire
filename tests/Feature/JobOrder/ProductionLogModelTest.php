<?php

use App\Models\AuditLog;
use App\Models\ProductionLog;
use App\Models\User;

test('creating a ProductionLog writes an audit_trail row', function () {
    $productionLog = ProductionLog::factory()->create();

    expect(
        AuditLog::where('auditable_type', ProductionLog::class)
            ->where('auditable_id', $productionLog->id)
            ->where('action', 'created')
            ->exists()
    )->toBeTrue();
});

test('a null recorded_by and from_status round-trip correctly', function () {
    $productionLog = ProductionLog::factory()->create([
        'recorded_by' => null,
        'from_status' => null,
    ]);

    expect($productionLog->fresh()->recorded_by)->toBeNull();
    expect($productionLog->fresh()->from_status)->toBeNull();
});

test('jobOrder and recordedBy relations resolve', function () {
    $productionLog = ProductionLog::factory()->create();

    expect($productionLog->jobOrder)->not->toBeNull();
    expect($productionLog->jobOrder->id)->toBe($productionLog->job_order_id);

    $withActor = ProductionLog::factory()->create(['recorded_by' => User::factory()->create()->id]);

    expect($withActor->recordedBy)->not->toBeNull();
    expect($withActor->recordedBy->id)->toBe($withActor->recorded_by);
});
