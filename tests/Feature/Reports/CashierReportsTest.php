<?php

use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\Transaction;
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

test('a cash payment recorded at the counter appears in the sales report', function () {
    $this->travelTo('2026-09-10 12:00:00');

    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => PricingEntry::factory()->create(['base_price' => 750])->id,
        'line_amount' => 750,
        'rush_fee_applied' => false,
        'payment_method' => 'cash',
        'payment_type' => 'full',
    ]);

    $response = $this->actingAs($cashier)
        ->withHeaders(cashierReportHeaders())
        ->get(route('cashier.reports.index', ['report' => 'sales']));

    expect($response->json('props.rows'))->toHaveCount(1)
        ->and($response->json('props.rows.0'))->toMatchArray([
            'date' => '2026-09-10',
            'job_order' => $jobOrder->number,
            'method' => 'cash',
        ])
        ->and((float) $response->json('props.rows.0.amount'))->toBe(750.0);
});

test('a payment taken before 8 AM Manila time is filed under that Manila day', function (string $day, array $expectedDates) {
    // 1 AM on October 3 in Manila, still October 2 in UTC.
    $this->travelTo('2026-10-02 17:00:00');

    $cashier = User::factory()->cashier()->create();
    Transaction::factory()->create(['confirmed_at' => now()]);

    $response = $this->actingAs($cashier)
        ->withHeaders(cashierReportHeaders())
        ->get(route('cashier.reports.index', ['report' => 'sales', 'from' => $day, 'to' => $day]));

    expect(array_column($response->json('props.rows'), 'date'))->toBe($expectedDates);
})->with([
    'today in Manila' => ['2026-10-03', ['2026-10-03']],
    'yesterday in Manila' => ['2026-10-02', []],
]);

test('a job order cancelled before 8 AM Manila time is filed under that Manila day', function (string $day, array $expectedDates) {
    // 1 AM on October 3 in Manila, still October 2 in UTC.
    $this->travelTo('2026-10-02 17:00:00');

    $cashier = User::factory()->cashier()->create();
    JobOrder::factory()->create(['total_amount' => 1000, 'cancelled_at' => now()]);

    $response = $this->actingAs($cashier)
        ->withHeaders(cashierReportHeaders())
        ->get(route('cashier.reports.index', ['report' => 'cancellations', 'from' => $day, 'to' => $day]));

    expect(array_column($response->json('props.rows'), 'date'))->toBe($expectedDates);
})->with([
    'today in Manila' => ['2026-10-03', ['2026-10-03']],
    'yesterday in Manila' => ['2026-10-02', []],
]);
