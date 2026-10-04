<?php

use App\Enums\JobOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Expense;
use App\Models\JobOrder;
use App\Models\ProductionLog;
use App\Models\Transaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Testing\AssertableInertia as Assert;

test('sales search matches job numbers customers and displayed labels for every entitled role', function (string $role, string $search) {
    $this->travelTo('2026-10-04 04:00:00');
    $user = User::factory()->{$role}()->create();
    $matching = JobOrder::factory()->create(['number' => 'JO-2026-4711']);
    $matching->queueEntry->customer->update(['name' => 'Maria Santos']);
    Transaction::factory()->downPayment()->for($matching)->create(['payment_method' => PaymentMethod::BankTransfer, 'amount' => 120]);
    $other = JobOrder::factory()->create(['number' => 'JO-2026-2222']);
    $other->queueEntry->customer->update(['name' => 'Juan Cruz']);
    Transaction::factory()->for($other)->create(['amount' => 800]);
    $routeRole = match ($role) {
        'accountingStaff' => 'accounting-staff',
        default => $role,
    };

    $this->actingAs($user)->get(route("{$routeRole}.reports.index", ['report' => 'sales', 'q' => $search]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', trim($search))
            ->where('rowsTotal', 1)
            ->where('rows.0.job_order', 'JO-2026-4711')
            ->where('rowsAmountTotal', 120)
            ->where('chart.series.0.values', [0, 0, 0, 120]));
})->with(['admin', 'cashier', 'accountingStaff'])->with([
    'job number' => '4711',
    'customer and whitespace' => '  sANTos  ',
    'transaction label' => 'down payment',
    'payment method label' => 'bank transfer',
]);

test('expenses search matches category description recorded staff and the active status label', function (string $search) {
    $this->travelTo('2026-10-04 04:00:00');
    $user = User::factory()->accountingStaff()->create(['name' => 'Ledger Writer']);
    $other = User::factory()->accountingStaff()->create(['name' => 'Other Recorder']);
    Expense::factory()->create(['category' => 'Supplies', 'description' => 'Toner refill', 'recorded_by' => $user->id, 'expense_date' => '2026-10-04', 'amount' => 200]);
    Expense::factory()->voided()->create(['category' => 'Rent', 'description' => 'Office lease', 'recorded_by' => $other->id, 'expense_date' => '2026-10-04', 'amount' => 300]);

    $this->actingAs($user)->get(route('accounting-staff.reports.index', ['report' => 'expenses', 'q' => $search]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rowsTotal', 1)
            ->where('rows.0.description', 'Toner refill')
            ->where('rowsAmountTotal', 200)
            ->where('chart.items', [['label' => 'Supplies', 'value' => 200]]));
})->with(['supplies', 'TONER', 'ledger writer', 'active']);

test('matching voided expenses stay visible but contribute nothing to totals or charts', function () {
    $this->travelTo('2026-10-04 04:00:00');
    $user = User::factory()->accountingStaff()->create();
    Expense::factory()->voided()->create(['expense_date' => '2026-10-04', 'amount' => 300]);

    $this->actingAs($user)->get(route('accounting-staff.reports.index', ['report' => 'expenses', 'q' => 'voided']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rowsTotal', 1)
            ->where('rows.0.status', 'Voided')
            ->where('rowsAmountTotal', 0)
            ->where('chart.items', []));
});

test('production search matches products stages customers and urgency labels', function (string $search, bool $matchesRush) {
    $this->travelTo('2026-10-04 04:00:00');
    $user = User::factory()->productionStaff()->create();
    $rush = JobOrder::factory()->create(['description' => 'Festival Banner', 'status' => JobOrderStatus::ReadyForPickup]);
    $rush->queueEntry->customer->update(['name' => 'Banner Buyer']);
    $rush->forceFill(['due_at' => now()->subDay()])->save();
    ProductionLog::factory()->for($rush)->create();
    $normal = JobOrder::factory()->create(['description' => 'Business Cards', 'status' => JobOrderStatus::Printing]);
    $normal->queueEntry->customer->update(['name' => 'Card Buyer']);
    $normal->forceFill(['due_at' => now()->addWeek()])->save();
    ProductionLog::factory()->for($normal)->create();

    $this->actingAs($user)->get(route('production-staff.reports.index', ['report' => 'production-status', 'q' => $search]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rowsTotal', 1)
            ->where('rows.0.job_order', $matchesRush ? $rush->number : $normal->number)
            ->where('chart.items', [
                ['label' => 'For Production', 'value' => 0],
                ['label' => 'Printing', 'value' => $matchesRush ? 0 : 1],
                ['label' => 'Ready for Pickup', 'value' => $matchesRush ? 1 : 0],
                ['label' => 'Released', 'value' => 0],
                ['label' => 'Cancelled', 'value' => 0],
            ]));
})->with([
    'product' => ['festival', true],
    'customer' => ['banner buyer', true],
    'stage' => ['ready for pickup', true],
    'rush' => ['rush', true],
    'normal' => ['normal', false],
]);

test('cancellation search matches the displayed payment status', function () {
    $this->travelTo('2026-10-04 04:00:00');
    $cashier = User::factory()->cashier()->create();
    $matching = JobOrder::factory()->create(['cancelled_at' => now(), 'payment_status' => PaymentStatus::PartiallyPaid]);
    JobOrder::factory()->create(['cancelled_at' => now(), 'payment_status' => PaymentStatus::Paid]);

    $this->actingAs($cashier)->get(route('cashier.reports.index', ['report' => 'cancellations', 'q' => 'partially paid']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rowsTotal', 1)
            ->where('rows.0.job_order', $matching->number)
            ->where('chart.series.0.values', [0, 0, 0, 1]));
});

test('search finds a matching sale beyond the first hundred rows and clearing restores the range', function () {
    $this->travelTo('2026-10-04 04:00:00');
    $cashier = User::factory()->cashier()->create();
    $matching = JobOrder::factory()->create(['number' => 'JO-2026-4711']);
    Transaction::factory()->for($matching)->recycle($cashier)->create(['amount' => 75, 'confirmed_at' => now()->subMinutes(2)]);
    Transaction::factory()->count(101)->for(JobOrder::factory()->create(['number' => 'JO-2026-2222']))->recycle($cashier)->create(['amount' => 10]);

    $this->actingAs($cashier)->get(route('cashier.reports.index', ['q' => '4711']))
        ->assertInertia(fn (Assert $page) => $page->where('rowsTotal', 1)->where('rows.0.job_order', $matching->number)->where('rowsAmountTotal', 75));
    $this->get(route('cashier.reports.index', ['q' => '']))
        ->assertInertia(fn (Assert $page) => $page->where('rowsTotal', 102)->has('rows', 100)->where('rowsAmountTotal', 1085));
});

test('search combines with date filters and a missing match produces empty rows and zero totals', function () {
    $this->travelTo('2026-10-04 04:00:00');
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['number' => 'JO-2026-4711']);
    Transaction::factory()->for($jobOrder)->create(['amount' => 75, 'confirmed_at' => now()->subDay()]);
    Transaction::factory()->for($jobOrder)->create(['amount' => 25, 'confirmed_at' => now()]);

    $this->actingAs($cashier)->get(route('cashier.reports.index', ['q' => '4711', 'from' => '2026-10-04', 'to' => '2026-10-04']))
        ->assertInertia(fn (Assert $page) => $page->where('rowsTotal', 1)->where('rowsAmountTotal', 25));
    $this->get(route('cashier.reports.index', ['q' => '%']))
        ->assertInertia(fn (Assert $page) => $page->where('rowsTotal', 0)->where('rows', [])->where('rowsAmountTotal', 0)->where('chart.series.0.values', [0, 0, 0, 0]));
});

test('report search validates string input and length on the page and exports', function (string $endpoint, mixed $search) {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)->get(route("cashier.reports.{$endpoint}", ['reportKey' => 'sales', 'q' => $search]))
        ->assertSessionHasErrors('q');
})->with(['index', 'export.pdf', 'export.xlsx'])->with([
    'too long' => str_repeat('a', 256),
    'array' => [['invalid']],
]);

test('search cannot bypass report entitlement checks', function (string $endpoint) {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)->get(route("cashier.reports.{$endpoint}", ['report' => 'expenses', 'reportKey' => 'expenses', 'q' => 'supplies']))
        ->assertForbidden();
})->with(['index', 'export.pdf', 'export.xlsx']);

test('financial summary ignores row search', function () {
    $this->travelTo('2026-10-04 04:00:00');
    $user = User::factory()->accountingStaff()->create();
    Expense::factory()->create(['expense_date' => '2026-10-04', 'amount' => 75]);

    $this->actingAs($user)->get(route('accounting-staff.reports.index', ['report' => 'financial-summary', 'q' => 'unmatched']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.q', '')->where('summary.expenses_total', 75));
});

test('PDF and Excel use the matching report rows and matching amount totals', function () {
    $this->skipUnlessZipAvailable();
    $this->travelTo('2026-10-04 04:00:00');
    $cashier = User::factory()->cashier()->create();
    $matching = JobOrder::factory()->create(['number' => 'JO-2026-4711']);
    Transaction::factory()->for($matching)->create(['amount' => 75]);
    Transaction::factory()->for(JobOrder::factory()->create(['number' => 'JO-2026-2222']))->create(['amount' => 900]);
    Pdf::shouldReceive('loadView')->once()->with('reports.table', Mockery::on(fn (array $data): bool => count($data['rows']) === 1
        && $data['rows'][0][1] === 'JO-2026-4711'
        && $data['totalRow'][5] === 75.0))->andReturnSelf();
    Pdf::shouldReceive('setOption')->once()->with('isPhpEnabled', true)->andReturnSelf();
    Pdf::shouldReceive('download')->once()->andReturn(response('PDF', 200, ['Content-Type' => 'application/pdf']));

    $this->actingAs($cashier)->get(route('cashier.reports.export.pdf', ['reportKey' => 'sales', 'q' => '4711']))->assertHeader('Content-Type', 'application/pdf');
    $response = $this->get(route('cashier.reports.export.xlsx', ['reportKey' => 'sales', 'q' => '4711']));
    $response->assertOk();

    $rows = readXlsxRows($response->streamedContent());
    expect($rows)->toHaveCount(3);
    expect($rows[1][1])->toBe('JO-2026-4711');
    expect((float) $rows[2][5])->toBe(75.0);
});

test('searched exports include all matching rows beyond the preview cap', function () {
    $this->skipUnlessZipAvailable();
    $this->travelTo('2026-10-04 04:00:00');
    $cashier = User::factory()->cashier()->create();
    Transaction::factory()->count(101)->for(JobOrder::factory()->create(['number' => 'JO-2026-4711']))->recycle($cashier)->create(['amount' => 10]);
    Transaction::factory()->for(JobOrder::factory()->create(['number' => 'JO-2026-2222']))->create(['amount' => 500]);

    $this->actingAs($cashier)->get(route('cashier.reports.index', ['q' => '4711']))
        ->assertInertia(fn (Assert $page) => $page->has('rows', 100)->where('rowsTotal', 101)->where('rowsAmountTotal', 1010));
    $response = $this->get(route('cashier.reports.export.xlsx', ['reportKey' => 'sales', 'q' => '4711']));
    $response->assertOk();

    $rows = readXlsxRows($response->streamedContent());
    expect($rows)->toHaveCount(103);
    expect((float) end($rows)[5])->toBe(1010.0);
});
