<?php

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\ProductionLog;
use App\Models\User;

/**
 * Matches Inertia\Middleware::version()'s default resolver exactly, so a
 * test-issued X-Inertia-Version header matches what the real middleware
 * computes for this request and avoids a spurious 409 version conflict.
 */
function productionReportHeaders(): array
{
    $manifest = public_path('build/manifest.json');

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : null,
    ];
}

test("production staff's reports prop contains exactly production-status", function () {
    $productionStaff = User::factory()->productionStaff()->create();

    $response = $this->actingAs($productionStaff)->withHeaders(productionReportHeaders())->get(route('production-staff.reports.index'));

    $response->assertOk();
    expect(array_keys($response->json('props.reports')))->toBe(['production-status']);
});

test('a job order entering production inside the range appears; one dated outside the range does not', function () {
    $productionStaff = User::factory()->productionStaff()->create();
    $from = now()->startOfMonth();

    $inRangeJobOrder = JobOrder::factory()->create();
    ProductionLog::factory()->for($inRangeJobOrder)->create([
        'to_status' => JobOrderStatus::ForProduction->value,
        'created_at' => now(),
    ]);

    $outOfRangeJobOrder = JobOrder::factory()->create();
    ProductionLog::factory()->for($outOfRangeJobOrder)->create([
        'to_status' => JobOrderStatus::ForProduction->value,
        'created_at' => $from->copy()->subMonth(),
    ]);

    $response = $this->actingAs($productionStaff)
        ->withHeaders(productionReportHeaders())
        ->get(route('production-staff.reports.index', ['report' => 'production-status']));

    $response->assertOk();
    $rows = $response->json('props.rows');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['job_order'])->toBe($inRangeJobOrder->number);
});

test('production staff requesting a report key it is not entitled to gets a 403', function () {
    $productionStaff = User::factory()->productionStaff()->create();

    $this->actingAs($productionStaff)->get(route('production-staff.reports.index', ['report' => 'sales']))->assertForbidden();
});

test('the production chart counts jobs per board step in order, empty steps included', function () {
    $productionStaff = User::factory()->productionStaff()->create();

    foreach ([JobOrderStatus::Printing, JobOrderStatus::Printing, JobOrderStatus::ReadyForPickup] as $stage) {
        ProductionLog::factory()->for(JobOrder::factory()->create(['status' => $stage->value]))->create([
            'to_status' => JobOrderStatus::ForProduction->value,
            'created_at' => now(),
        ]);
    }

    $response = $this->actingAs($productionStaff)
        ->withHeaders(productionReportHeaders())
        ->get(route('production-staff.reports.index', ['report' => 'production-status']));

    $response->assertOk();
    expect($response->json('props.chart.items'))->toEqual([
        ['label' => 'For Production', 'value' => 0],
        ['label' => 'Printing', 'value' => 2],
        ['label' => 'Ready for Pickup', 'value' => 1],
        ['label' => 'Released', 'value' => 0],
        ['label' => 'Cancelled', 'value' => 0],
    ]);
});

test('production report distinguishes deadline urgency from customer-requested rush', function () {
    $productionStaff = User::factory()->productionStaff()->create();
    $overdueRegular = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);
    $overdueRegular->forceFill(['due_at' => now()->subDay()])->save();
    ProductionLog::factory()->for($overdueRegular)->create(['to_status' => JobOrderStatus::ForProduction->value]);
    $futureRush = JobOrder::factory()->rush()->create(['status' => JobOrderStatus::ForProduction->value]);
    $futureRush->forceFill(['due_at' => now()->addWeek()])->save();
    ProductionLog::factory()->for($futureRush)->create(['to_status' => JobOrderStatus::ForProduction->value]);

    $response = $this->actingAs($productionStaff)
        ->withHeaders(productionReportHeaders())
        ->get(route('production-staff.reports.index', ['report' => 'production-status']));

    $rows = collect($response->json('props.rows'));
    expect($rows->firstWhere('job_order', $overdueRegular->number)['urgency'])->toBe('Urgent')
        ->and($rows->firstWhere('job_order', $futureRush->number)['urgency'])->toBe('Rush');
});

test('production report shows both priority reasons for a rush order with an urgent deadline', function () {
    $productionStaff = User::factory()->productionStaff()->create();
    $rush = JobOrder::factory()->rush()->create(['status' => JobOrderStatus::ForProduction->value]);
    $rush->forceFill(['due_at' => now()->subDay()])->save();
    ProductionLog::factory()->for($rush)->create(['to_status' => JobOrderStatus::ForProduction->value]);

    $response = $this->actingAs($productionStaff)
        ->withHeaders(productionReportHeaders())
        ->get(route('production-staff.reports.index', ['report' => 'production-status']));

    expect($response->json('props.rows.0.urgency'))->toBe('Rush + Urgent');
});

test('a job order that left the shop reports how it left, not the stage it was last at', function (array $leftAt, string $stage, string $chartLabel) {
    $this->travelTo('2026-09-10 12:00:00');

    $productionStaff = User::factory()->productionStaff()->create();
    // A marked Rush order stops counting as urgent after leaving the shop.
    $jobOrder = JobOrder::factory()->rush()->create(['status' => JobOrderStatus::ReadyForPickup->value, ...$leftAt]);
    $jobOrder->forceFill(['due_at' => '2026-09-09 09:00:00'])->save();
    ProductionLog::factory()->for($jobOrder)->create([
        'to_status' => JobOrderStatus::ForProduction->value,
        'created_at' => '2026-09-09 09:00:00',
    ]);

    $response = $this->actingAs($productionStaff)
        ->withHeaders(productionReportHeaders())
        ->get(route('production-staff.reports.index', ['report' => 'production-status']));

    expect($response->json('props.rows.0.stage'))->toBe($stage)
        ->and($response->json('props.rows.0.urgency'))->toBe('Normal')
        ->and(collect($response->json('props.chart.items'))->pluck('value', 'label')->all())->toMatchArray([
            'Ready for Pickup' => 0,
            $chartLabel => 1,
        ]);
})->with([
    'released' => [['released_at' => '2026-09-10 10:00:00'], 'released', 'Released'],
    'cancelled' => [['cancelled_at' => '2026-09-10 10:00:00'], 'cancelled', 'Cancelled'],
]);

test('a job order entering production before 8 AM Manila time is filed under that Manila day', function (string $day, int $expectedRows) {
    // 1 AM on October 3 in Manila, still October 2 in UTC.
    $this->travelTo('2026-10-02 17:00:00');

    $productionStaff = User::factory()->productionStaff()->create();
    ProductionLog::factory()->for(JobOrder::factory()->create())->create([
        'to_status' => JobOrderStatus::ForProduction->value,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($productionStaff)
        ->withHeaders(productionReportHeaders())
        ->get(route('production-staff.reports.index', ['report' => 'production-status', 'from' => $day, 'to' => $day]));

    expect($response->json('props.rows'))->toHaveCount($expectedRows);
})->with([
    'today in Manila' => ['2026-10-03', 1],
    'yesterday in Manila' => ['2026-10-02', 0],
]);

test('a job order sent back to For Production by an undo is listed once', function () {
    $this->travelTo('2026-09-10 12:00:00');

    $productionStaff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);
    // Entered from Design Approved, as older rows record it, then undone.
    ProductionLog::factory()->for($jobOrder)->create([
        'from_status' => JobOrderStatus::DesignApproved->value,
        'created_at' => '2026-09-10 08:00:00',
    ]);
    ProductionLog::factory()->for($jobOrder)->create([
        'from_status' => JobOrderStatus::Printing->value,
        'to_status' => JobOrderStatus::ForProduction->value,
        'created_at' => '2026-09-10 09:00:00',
    ]);

    $response = $this->actingAs($productionStaff)
        ->withHeaders(productionReportHeaders())
        ->get(route('production-staff.reports.index', ['report' => 'production-status']));

    expect($response->json('props.rows'))->toHaveCount(1)
        ->and(collect($response->json('props.chart.items'))->sum('value'))->toEqual(1);
});

test('an undo inside the range does not list an order that entered production before it', function () {
    $this->travelTo('2026-09-12 12:00:00');

    $productionStaff = User::factory()->productionStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ForProduction->value]);
    ProductionLog::factory()->for($jobOrder)->create([
        'to_status' => JobOrderStatus::ForProduction->value,
        'created_at' => '2026-09-01 08:00:00',
    ]);
    ProductionLog::factory()->for($jobOrder)->create([
        'from_status' => JobOrderStatus::Printing->value,
        'to_status' => JobOrderStatus::ForProduction->value,
        'created_at' => '2026-09-11 09:00:00',
    ]);

    $response = $this->actingAs($productionStaff)
        ->withHeaders(productionReportHeaders())
        ->get(route('production-staff.reports.index', ['report' => 'production-status', 'from' => '2026-09-10', 'to' => '2026-09-12']));

    expect($response->json('props.rows'))->toBe([]);
});
