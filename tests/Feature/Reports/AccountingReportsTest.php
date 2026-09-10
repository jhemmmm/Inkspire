<?php

use App\Enums\TransactionType;
use App\Models\Expense;
use App\Models\Transaction;
use App\Models\User;

/**
 * Matches Inertia\Middleware::version()'s default resolver exactly, so a
 * test-issued X-Inertia-Version header matches what the real middleware
 * computes for this request and avoids a spurious 409 version conflict.
 */
function accountingReportHeaders(): array
{
    $manifest = public_path('build/manifest.json');

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : null,
    ];
}

test("accounting staff's reports prop contains exactly sales, expenses, and financial-summary", function () {
    $accountingStaff = User::factory()->accountingStaff()->create();

    $response = $this->actingAs($accountingStaff)->withHeaders(accountingReportHeaders())->get(route('accounting-staff.reports.index'));

    $response->assertOk();
    expect(array_keys($response->json('props.reports')))->toBe(['sales', 'expenses', 'financial-summary']);
});

test('a voided expense is excluded from financial-summary\'s expenses_total but still appears as a row on the expenses report with status Voided (D-11/Pitfall 5)', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();

    Expense::factory()->create(['amount' => 100, 'expense_date' => now()]);
    Expense::factory()->voided()->create(['amount' => 50, 'expense_date' => now()]);

    $rowsResponse = $this->actingAs($accountingStaff)
        ->withHeaders(accountingReportHeaders())
        ->get(route('accounting-staff.reports.index', ['report' => 'expenses']));

    $rowsResponse->assertOk();
    $rows = collect($rowsResponse->json('props.rows'));
    expect($rows)->toHaveCount(2);
    expect($rows->firstWhere('amount', 50.0)['status'])->toBe('Voided');
    expect($rows->firstWhere('amount', 100.0)['status'])->toBeNull();

    $summaryResponse = $this->actingAs($accountingStaff)
        ->withHeaders(accountingReportHeaders())
        ->get(route('accounting-staff.reports.index', ['report' => 'financial-summary']));

    $summaryResponse->assertOk();
    expect((float) $summaryResponse->json('props.summary.expenses_total'))->toBe(100.0);
});

test('the same sales report ranged to a single day vs. a full month returns the days subset, not a separate report type (D-06)', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $today = now();

    Transaction::factory()->create([
        'type' => TransactionType::FullPayment->value,
        'amount' => 500,
        'confirmed_at' => $today,
    ]);
    Transaction::factory()->create([
        'type' => TransactionType::FullPayment->value,
        'amount' => 300,
        'confirmed_at' => $today->copy()->subDays(3),
    ]);

    $dailyResponse = $this->actingAs($accountingStaff)
        ->withHeaders(accountingReportHeaders())
        ->get(route('accounting-staff.reports.index', [
            'report' => 'sales',
            'from' => $today->toDateString(),
            'to' => $today->toDateString(),
        ]));
    $dailyResponse->assertOk();
    expect($dailyResponse->json('props.rows'))->toHaveCount(1);

    $monthlyResponse = $this->actingAs($accountingStaff)
        ->withHeaders(accountingReportHeaders())
        ->get(route('accounting-staff.reports.index', [
            'report' => 'sales',
            'from' => $today->copy()->startOfMonth()->toDateString(),
            'to' => $today->toDateString(),
        ]));
    $monthlyResponse->assertOk();
    expect($monthlyResponse->json('props.rows'))->toHaveCount(2);
});

test('accounting staff requesting production-status gets a 403', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();

    $this->actingAs($accountingStaff)->get(route('accounting-staff.reports.index', ['report' => 'production-status']))->assertForbidden();
});
