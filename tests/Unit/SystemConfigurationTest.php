<?php

use App\Models\SystemConfiguration;
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
