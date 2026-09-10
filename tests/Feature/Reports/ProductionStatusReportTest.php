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
