<?php

use App\Enums\TransactionType;
use App\Models\AccountsReceivable;
use App\Models\Expense;
use App\Models\JobOrder;
use App\Models\Transaction;
use App\Models\User;

/**
 * Matches Inertia\Middleware::version()'s default resolver exactly, so a
 * test-issued X-Inertia-Version header matches what the real middleware
 * computes for this request and avoids a spurious 409 version conflict.
 * Role-scoped Reports.vue pages don't exist yet (Plan 08-05's scope), so
 * every read here is an XHR-style Inertia request, matching ExpenseTest's
 * established convention.
 */
function financialReportHeaders(): array
{
    $manifest = public_path('build/manifest.json');

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : null,
    ];
}

test("owner's reports prop is the union of all five report types (D-05)", function () {
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->withHeaders(financialReportHeaders())->get(route('owner.reports.index'));

    $response->assertOk();
    expect(array_keys($response->json('props.reports')))->toBe(['sales', 'cancellations', 'production-status', 'expenses', 'financial-summary']);
});

test('an admin hitting the owner reports route directly gets a 403, not a 200 (D-05/Pitfall 2)', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('owner.reports.index'))->assertForbidden();
});

test('financial-summary splits revenue into job sales and cancellation fees, and discloses an in-range write-off without subtracting it from result (D-09/D-10)', function () {
    $owner = User::factory()->owner()->create();

    $salesJobOrder = JobOrder::factory()->create(['total_amount' => 1000]);
    Transaction::factory()->for($salesJobOrder)->create([
        'type' => TransactionType::DownPayment->value,
        'amount' => 400,
        'confirmed_at' => now(),
    ]);

    $cancelledJobOrder = JobOrder::factory()->create(['total_amount' => 500, 'cancelled_at' => now()]);
    Transaction::factory()->for($cancelledJobOrder)->create([
        'type' => TransactionType::CancellationFee->value,
        'amount' => 200,
        'confirmed_at' => now(),
    ]);

    Expense::factory()->create(['amount' => 100, 'expense_date' => now()]);

    // An entry written off in range: outstandingBalance() is total_amount
    // (800) minus its Completed transactions (300) = 500. Disclosed as its
    // own figure, but never subtracted from `result`.
    $writeOffJobOrder = JobOrder::factory()->create(['total_amount' => 800]);
    Transaction::factory()->for($writeOffJobOrder)->create([
        'type' => TransactionType::DownPayment->value,
        'amount' => 300,
        'confirmed_at' => now(),
    ]);
    $accountsReceivable = AccountsReceivable::factory()->active()->for($writeOffJobOrder)->create();
    $accountsReceivable->forceFill(['written_off_at' => now()])->save();

    $response = $this->actingAs($owner)
        ->withHeaders(financialReportHeaders())
        ->get(route('owner.reports.index', ['report' => 'financial-summary']));

    $response->assertOk();
    $summary = $response->json('props.summary');

    expect((float) $summary['job_sales'])->toBe(700.0); // 400 + the write-off job order's 300 down payment
    expect((float) $summary['cancellation_fees'])->toBe(200.0);
    expect((float) $summary['revenue_total'])->toBe(900.0);
    expect((float) $summary['expenses_total'])->toBe(100.0);
    expect((float) $summary['result'])->toBe(800.0); // 900 - 100, NOT further reduced by the write-off
    expect((float) $summary['write_off_total'])->toBe(500.0);
});

test('revenue is computed from confirmed_at, not created_at -- a transaction confirmed outside the range is excluded even when created inside it, and vice versa (D-08/Pitfall 1)', function () {
    $owner = User::factory()->owner()->create();

    $from = now()->startOfMonth();
    $to = now()->endOfMonth();

    // Created inside the range, confirmed the day after it ends -- excluded.
    Transaction::factory()->create([
        'type' => TransactionType::FullPayment->value,
        'amount' => 700,
        'created_at' => now(),
        'confirmed_at' => $to->copy()->addDay(),
    ]);

    // Created before the range starts, confirmed inside it -- included.
    Transaction::factory()->create([
        'type' => TransactionType::FullPayment->value,
        'amount' => 300,
        'created_at' => $from->copy()->subDay(),
        'confirmed_at' => now(),
    ]);

    $response = $this->actingAs($owner)
        ->withHeaders(financialReportHeaders())
        ->get(route('owner.reports.index', ['report' => 'sales']));

    $response->assertOk();
    $rows = $response->json('props.rows');

    expect($rows)->toHaveCount(1);
    expect((float) $rows[0]['amount'])->toBe(300.0);
});
