<?php

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\ProductionLog;
use App\Models\User;

test('advancing moves a job order forward exactly one stage and logs the transition', function (JobOrderStatus $from, JobOrderStatus $to) {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => $from->value]);

    $response = $this->actingAs($staff)->patch(route('production-staff.job-orders.advance', $jobOrder));

    $response->assertRedirect();
    expect($jobOrder->fresh()->status)->toBe($to);

    $log = ProductionLog::query()->where('job_order_id', $jobOrder->id)->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->from_status)->toBe($from);
    expect($log->to_status)->toBe($to);
    expect($log->recorded_by)->toBe($staff->id);
    expect($log->reason)->toBeNull();
})->with([
    'ForProduction to Printing' => [JobOrderStatus::ForProduction, JobOrderStatus::Printing],
    'Printing to QualityCheck' => [JobOrderStatus::Printing, JobOrderStatus::QualityCheck],
    'QualityCheck to ReadyForPickup' => [JobOrderStatus::QualityCheck, JobOrderStatus::ReadyForPickup],
]);

test('advancing a job order already at ready for pickup is rejected and creates no log row', function () {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForPickup->value]);

    $response = $this->actingAs($staff)
        ->withHeaders(['X-Inertia' => 'true'])
        ->patch(route('production-staff.job-orders.advance', $jobOrder));

    $response->assertRedirect();
    $response->assertSessionHas(
        'inertia.flash_data',
        fn (array $flash) => $flash['toast']['type'] === 'error'
            && $flash['toast']['message'] === 'This job order already moved on. The board has refreshed — check its current stage before trying again.',
    );
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::ReadyForPickup);
    expect(ProductionLog::query()->where('job_order_id', $jobOrder->id)->count())->toBe(0);
});

test('sending back moves a job order back exactly one stage with a mandatory reason logged', function (JobOrderStatus $from, JobOrderStatus $to) {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => $from->value]);

    $response = $this->actingAs($staff)->patch(route('production-staff.job-orders.send-back', $jobOrder), [
        'reason' => 'Colour banding on the second pass — needs a reprint',
    ]);

    $response->assertRedirect();
    expect($jobOrder->fresh()->status)->toBe($to);

    $log = ProductionLog::query()->where('job_order_id', $jobOrder->id)->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->from_status)->toBe($from);
    expect($log->to_status)->toBe($to);
    expect($log->recorded_by)->toBe($staff->id);
    expect($log->reason)->toBe('Colour banding on the second pass — needs a reprint');
})->with([
    'Printing to ForProduction' => [JobOrderStatus::Printing, JobOrderStatus::ForProduction],
    'QualityCheck to Printing' => [JobOrderStatus::QualityCheck, JobOrderStatus::Printing],
    'ReadyForPickup to QualityCheck' => [JobOrderStatus::ReadyForPickup, JobOrderStatus::QualityCheck],
]);

test('sending back a job order already at for production is rejected and creates no log row', function () {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);

    $response = $this->actingAs($staff)
        ->withHeaders(['X-Inertia' => 'true'])
        ->patch(route('production-staff.job-orders.send-back', $jobOrder), [
            'reason' => 'Some reason',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas(
        'inertia.flash_data',
        fn (array $flash) => $flash['toast']['type'] === 'error'
            && $flash['toast']['message'] === 'This job order already moved on. The board has refreshed — check its current stage before trying again.',
    );
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::ForProduction);
    expect(ProductionLog::query()->where('job_order_id', $jobOrder->id)->count())->toBe(0);
});

test('sending back with a blank reason fails validation and mutates nothing', function () {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value]);

    $response = $this->actingAs($staff)->patch(route('production-staff.job-orders.send-back', $jobOrder), [
        'reason' => '',
    ]);

    $response->assertSessionHasErrors('reason');
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Printing);
    expect(ProductionLog::query()->where('job_order_id', $jobOrder->id)->count())->toBe(0);
});

test('sending back with a missing reason fails validation and mutates nothing', function () {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value]);

    $response = $this->actingAs($staff)->patch(route('production-staff.job-orders.send-back', $jobOrder), []);

    $response->assertSessionHasErrors('reason');
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Printing);
    expect(ProductionLog::query()->where('job_order_id', $jobOrder->id)->count())->toBe(0);
});

test('a cancelled job order rejects advance regardless of its status', function () {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::Printing->value,
        'cancelled_at' => now(),
    ]);

    $response = $this->actingAs($staff)->patch(route('production-staff.job-orders.advance', $jobOrder));

    $response->assertStatus(422);
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Printing);
    expect(ProductionLog::query()->where('job_order_id', $jobOrder->id)->count())->toBe(0);
});

test('a cancelled job order rejects send back regardless of its status', function () {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::Printing->value,
        'cancelled_at' => now(),
    ]);

    $response = $this->actingAs($staff)->patch(route('production-staff.job-orders.send-back', $jobOrder), [
        'reason' => 'Some reason',
    ]);

    $response->assertStatus(422);
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Printing);
    expect(ProductionLog::query()->where('job_order_id', $jobOrder->id)->count())->toBe(0);
});

test('a non-production-staff role is forbidden from advancing or sending back', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);

    $this->actingAs($staff)->patch(route('production-staff.job-orders.advance', $jobOrder))->assertForbidden();
    $this->actingAs($staff)->patch(route('production-staff.job-orders.send-back', $jobOrder), ['reason' => 'x'])->assertForbidden();
});
