<?php

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\SystemConfiguration;
use App\Models\User;

/**
 * RefreshDatabase runs migrations only, not seeders -- the expense_categories
 * config row must be seeded per-test for ExpenseValidationRules'
 * Rule::in(SystemConfiguration::getArray(...)) to accept a category,
 * matching CancellationFeeTest's seedCancellationFee() convention.
 *
 * @param  array<int, string>  $categories
 */
function seedExpenseCategories(array $categories = ['Utilities', 'Supplies', 'Rent']): void
{
    SystemConfiguration::create([
        'key' => 'expense_categories',
        'group' => 'business_rules',
        'value' => $categories,
        'type' => 'array',
        'label' => 'Expense categories',
    ]);
}

/**
 * Matches Inertia\Middleware::version()'s default resolver exactly, so a
 * test-issued X-Inertia-Version header matches what the real middleware
 * computes for this request and avoids a spurious 409 version conflict.
 */
function inertiaAssetVersion(): ?string
{
    $manifest = public_path('build/manifest.json');

    return file_exists($manifest) ? hash_file('xxh128', $manifest) : null;
}

test('Accounting Staff can record an expense with a valid category, amount, and date', function () {
    seedExpenseCategories();
    $accountingStaff = User::factory()->accountingStaff()->create();

    $response = $this->actingAs($accountingStaff)->post(route('accounting-staff.expenses.store'), [
        'category' => 'Utilities',
        'amount' => 1500,
        'expense_date' => now()->toDateString(),
        'description' => 'Monthly electric bill',
    ]);

    $response->assertRedirect();
    $expense = Expense::query()->latest('id')->first();
    expect($expense)->not->toBeNull();
    expect($expense->category)->toBe('Utilities');
    expect((float) $expense->amount)->toBe(1500.0);
    expect($expense->recorded_by)->toBe($accountingStaff->id);
});

test('recording an expense with a category not in the current expense_categories list returns a 422 validation error', function () {
    seedExpenseCategories();
    $accountingStaff = User::factory()->accountingStaff()->create();

    $response = $this->actingAs($accountingStaff)->post(route('accounting-staff.expenses.store'), [
        'category' => 'Not A Real Category',
        'amount' => 100,
        'expense_date' => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors('category');
});

test('recording an expense with an amount of zero or less returns a 422 validation error on amount', function () {
    seedExpenseCategories();
    $accountingStaff = User::factory()->accountingStaff()->create();

    $response = $this->actingAs($accountingStaff)->post(route('accounting-staff.expenses.store'), [
        'category' => 'Utilities',
        'amount' => 0,
        'expense_date' => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors('amount');
});

test('recording an expense with a future expense_date returns a 422 validation error on expense_date', function () {
    seedExpenseCategories();
    $accountingStaff = User::factory()->accountingStaff()->create();

    $response = $this->actingAs($accountingStaff)->post(route('accounting-staff.expenses.store'), [
        'category' => 'Utilities',
        'amount' => 100,
        'expense_date' => now('Asia/Manila')->addDay()->toDateString(),
    ]);

    $response->assertSessionHasErrors('expense_date');
});

test('an expense dated on the Manila business date is accepted while that date is still tomorrow in UTC', function () {
    seedExpenseCategories();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $this->travelTo('2026-09-28 17:00:00');

    $response = $this->actingAs($accountingStaff)->post(route('accounting-staff.expenses.store'), [
        'category' => 'Utilities',
        'amount' => 100,
        'expense_date' => '2026-09-29',
    ]);

    $response->assertSessionHasNoErrors();
    expect(Expense::query()->whereDate('expense_date', '2026-09-29')->exists())->toBeTrue();
});

test('Accounting Staff can edit a non-voided expense', function () {
    seedExpenseCategories();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $expense = Expense::factory()->create(['category' => 'Utilities', 'amount' => 100]);

    $response = $this->actingAs($accountingStaff)->patch(route('accounting-staff.expenses.update', $expense), [
        'category' => 'Supplies',
        'amount' => 250,
        'expense_date' => now()->toDateString(),
        'description' => 'Updated description',
    ]);

    $response->assertRedirect();
    $expense->refresh();
    expect($expense->category)->toBe('Supplies');
    expect((float) $expense->amount)->toBe(250.0);
});

test('editing an already-voided expense returns 422 and writes nothing', function () {
    seedExpenseCategories();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $expense = Expense::factory()->voided()->create(['category' => 'Utilities', 'amount' => 100]);

    $response = $this->actingAs($accountingStaff)->patch(route('accounting-staff.expenses.update', $expense), [
        'category' => 'Supplies',
        'amount' => 250,
        'expense_date' => now()->toDateString(),
    ]);

    $response->assertStatus(422);
    $expense->refresh();
    expect($expense->category)->toBe('Utilities');
    expect((float) $expense->amount)->toBe(100.0);
});

test('Accounting Staff can void an expense with a reason and the row survives but is excluded from index', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $expense = Expense::factory()->create(['expense_date' => now()]);

    $response = $this->actingAs($accountingStaff)->patch(route('accounting-staff.expenses.void', $expense), [
        'reason' => 'Duplicate entry made in error',
    ]);

    $response->assertRedirect();
    $expense->refresh();
    expect($expense->voided_at)->not->toBeNull();
    expect($expense->void_reason)->toBe('Duplicate entry made in error');

    $indexResponse = $this->actingAs($accountingStaff)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaAssetVersion()])
        ->get(route('accounting-staff.expenses.index'));
    $indexResponse->assertOk();
    expect($indexResponse->json('props.rows'))->toHaveCount(1);
});

test('voiding an expense without a reason returns a 422 validation error on reason', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $expense = Expense::factory()->create();

    $response = $this->actingAs($accountingStaff)->patch(route('accounting-staff.expenses.void', $expense), [
        'reason' => '',
    ]);

    $response->assertSessionHasErrors('reason');
    $expense->refresh();
    expect($expense->voided_at)->toBeNull();
});

test('voiding an already-voided expense returns 422 and does not overwrite the original void_reason', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();
    $expense = Expense::factory()->voided()->create();
    $originalReason = $expense->void_reason;

    $response = $this->actingAs($accountingStaff)->patch(route('accounting-staff.expenses.void', $expense), [
        'reason' => 'A different reason',
    ]);

    $response->assertStatus(422);
    $expense->refresh();
    expect($expense->void_reason)->toBe($originalReason);
});

test('creating, updating, and voiding an expense each produce exactly one new audit_trail row', function () {
    seedExpenseCategories();
    $accountingStaff = User::factory()->accountingStaff()->create();

    $this->actingAs($accountingStaff)->post(route('accounting-staff.expenses.store'), [
        'category' => 'Utilities',
        'amount' => 100,
        'expense_date' => now()->toDateString(),
    ]);
    $expense = Expense::query()->latest('id')->first();
    expect(AuditLog::where('auditable_type', Expense::class)->where('auditable_id', $expense->id)->where('action', 'created')->count())->toBe(1);

    $this->actingAs($accountingStaff)->patch(route('accounting-staff.expenses.update', $expense), [
        'category' => 'Supplies',
        'amount' => 200,
        'expense_date' => now()->toDateString(),
    ]);
    expect(AuditLog::where('auditable_type', Expense::class)->where('auditable_id', $expense->id)->where('action', 'updated')->count())->toBe(1);

    $this->actingAs($accountingStaff)->patch(route('accounting-staff.expenses.void', $expense), [
        'reason' => 'No longer needed',
    ]);
    expect(AuditLog::where('auditable_type', Expense::class)->where('auditable_id', $expense->id)->where('action', 'updated')->count())->toBe(2);
});

test('a user with a role other than Accounting Staff gets a 403 on every expense route', function () {
    $admin = User::factory()->admin()->create();
    $expense = Expense::factory()->create();

    $this->actingAs($admin)->get(route('accounting-staff.expenses.index'))->assertForbidden();
    $this->actingAs($admin)->post(route('accounting-staff.expenses.store'), [
        'category' => 'Utilities',
        'amount' => 100,
        'expense_date' => now()->toDateString(),
    ])->assertForbidden();
    $this->actingAs($admin)->patch(route('accounting-staff.expenses.update', $expense), [
        'category' => 'Supplies',
        'amount' => 200,
        'expense_date' => now()->toDateString(),
    ])->assertForbidden();
    $this->actingAs($admin)->patch(route('accounting-staff.expenses.void', $expense), [
        'reason' => 'Some reason',
    ])->assertForbidden();
});

test('index with no from/to defaults to This Month and returns rows in range plus a server-computed total excluding voided rows', function () {
    $accountingStaff = User::factory()->accountingStaff()->create();

    $inRangeActive = Expense::factory()->create(['amount' => 100, 'expense_date' => now()]);
    $inRangeVoided = Expense::factory()->voided()->create(['amount' => 50, 'expense_date' => now()]);
    $outOfRange = Expense::factory()->create(['amount' => 999, 'expense_date' => now()->subMonths(2)]);

    $response = $this->actingAs($accountingStaff)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaAssetVersion()])
        ->get(route('accounting-staff.expenses.index'));

    $response->assertOk();
    expect($response->json('props.rows'))->toHaveCount(2);
    expect((float) $response->json('props.total'))->toBe(100.0);
});

test('a historical expense keeps its stored category string after that category is removed from expense_categories config', function () {
    seedExpenseCategories();
    $accountingStaff = User::factory()->accountingStaff()->create();
    $expense = Expense::factory()->create(['category' => 'Rent', 'expense_date' => now()]);

    SystemConfiguration::query()->where('key', 'expense_categories')->update(['value' => ['Utilities', 'Supplies']]);
    SystemConfiguration::invalidate('expense_categories');

    $response = $this->actingAs($accountingStaff)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => inertiaAssetVersion()])
        ->get(route('accounting-staff.expenses.index'));

    $response->assertOk();
    expect($response->json('props.rows.0.category'))->toBe('Rent');
});
