<?php

use App\Models\JobOrder;
use App\Models\User;

/**
 * Matches Inertia\Middleware::version()'s default resolver exactly, so a
 * test-issued X-Inertia-Version header matches what the real middleware
 * computes for this request and avoids a spurious 409 version conflict.
 */
function cashierReportHeaders(): array
{
    $manifest = public_path('build/manifest.json');

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : null,
    ];
}

test("cashier's reports prop contains exactly sales and cancellations", function () {
    $cashier = User::factory()->cashier()->create();

    $response = $this->actingAs($cashier)->withHeaders(cashierReportHeaders())->get(route('cashier.reports.index'));

    $response->assertOk();
    expect(array_keys($response->json('props.reports')))->toBe(['sales', 'cancellations']);
});

test('cashier requesting a report key it is not entitled to gets a 403 even though the route itself is reachable (T-08-08)', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)->get(route('cashier.reports.index', ['report' => 'expenses']))->assertForbidden();
    $this->actingAs($cashier)->get(route('cashier.reports.index', ['report' => 'financial-summary']))->assertForbidden();
});

test('a cancelled job order with no cancellation-fee transaction (prior payment already covered it) shows a zero fee in the cancellations report', function () {
    $cashier = User::factory()->cashier()->create();
    JobOrder::factory()->create(['total_amount' => 1000, 'cancelled_at' => now()]);

    $response = $this->actingAs($cashier)
        ->withHeaders(cashierReportHeaders())
        ->get(route('cashier.reports.index', ['report' => 'cancellations']));

    $response->assertOk();
    $rows = $response->json('props.rows');

    expect($rows)->toHaveCount(1);
    expect((float) $rows[0]['cancellation_fee'])->toBe(0.0);
});
