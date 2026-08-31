<?php

use App\Models\SystemConfiguration;
use Database\Seeders\SystemConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('getInt returns the default when no row exists', function () {
    expect(SystemConfiguration::getInt('missing_key', 5))->toBe(5);
});

test('getInt returns the seeded value and caches it', function () {
    SystemConfiguration::create([
        'key' => 't',
        'group' => 'security',
        'value' => 10,
        'type' => 'integer',
        'label' => 'T',
    ]);

    expect(SystemConfiguration::getInt('t', 5))->toBe(10);

    DB::enableQueryLog();
    SystemConfiguration::getInt('t', 5);
    expect(DB::getQueryLog())->toBeEmpty();
    DB::disableQueryLog();
});

test('seeder seeds all 12 CONFIG-01 business rules idempotently', function () {
    $this->seed(SystemConfigurationSeeder::class);

    expect(SystemConfiguration::getInt('account_lockout_max_attempts', 0))->toBe(5);
    expect(SystemConfiguration::getArray('expense_categories', []))->toBe(['Utilities', 'Supplies', 'Rent']);
    expect(SystemConfiguration::query()->count())->toBe(12);

    // Re-run to prove idempotency (updateOrCreate, no duplicates).
    $this->seed(SystemConfigurationSeeder::class);

    expect(SystemConfiguration::query()->count())->toBe(12);
});

test('saving a system configuration writes an audit trail row', function () {
    SystemConfiguration::create([
        'key' => 'audited_key',
        'group' => 'security',
        'value' => 1,
        'type' => 'integer',
        'label' => 'Audited Key',
    ]);

    expect(
        DB::table('audit_trail')
            ->where('auditable_type', SystemConfiguration::class)
            ->where('action', 'created')
            ->exists(),
    )->toBeTrue();
});
