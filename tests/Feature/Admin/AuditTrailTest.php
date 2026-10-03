<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can view the audit trail', function () {
    $admin = User::factory()->admin()->create();

    DB::table('audit_trail')->insert([
        'user_id' => $admin->id,
        'action' => 'login',
        'auditable_type' => User::class,
        'auditable_id' => $admin->id,
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.audit-trail.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('admin/AuditTrail'));
});

test('audit trail returns a validation error instead of a 500 for a malformed from date', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.audit-trail.index', ['from' => 'not-a-date']));

    $response->assertSessionHasErrors('from');
});

test('audit trail returns a validation error instead of a 500 for a malformed to date', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.audit-trail.index', ['to' => 'not-a-date']));

    $response->assertSessionHasErrors('to');
});

test('audit trail can be filtered by action', function () {
    $admin = User::factory()->admin()->create();

    DB::table('audit_trail')->insert([
        'user_id' => $admin->id,
        'action' => 'login',
        'auditable_type' => User::class,
        'auditable_id' => $admin->id,
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    DB::table('audit_trail')->insert([
        'user_id' => null,
        'action' => 'lockout',
        'auditable_type' => null,
        'auditable_id' => null,
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.audit-trail.index', ['action' => 'lockout']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/AuditTrail')
        ->has('entries.data', 1)
        ->where('entries.data.0.action', 'lockout')
    );
});

test('audit trail entries preserve the D-03 before/after value shape', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)->patch(route('admin.users.deactivate', $target));

    $response = $this->actingAs($admin)->get(route('admin.audit-trail.index', ['action' => 'updated']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/AuditTrail')
        ->where('entries.data.0.new_values.is_active', false)
    );
});

test('creating a user does not leak the password hash or remember token into the audit trail', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $row = DB::table('audit_trail')
        ->where('auditable_type', User::class)
        ->where('auditable_id', $target->id)
        ->where('action', 'created')
        ->first();

    expect($row)->not->toBeNull();

    $newValues = json_decode($row->new_values, true);

    expect($newValues)
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token')
        ->not->toHaveKey('two_factor_secret')
        ->not->toHaveKey('two_factor_recovery_codes')
        ->toHaveKey('email');

    $response = $this->actingAs($admin)->get(route('admin.audit-trail.index', ['action' => 'created']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/AuditTrail')
        ->missing('entries.data.0.new_values.password')
        ->missing('entries.data.0.new_values.remember_token')
    );
});

test('deleting a user does not leak the password hash into the audit trail', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();
    $targetId = $target->id;

    $target->delete();

    $row = DB::table('audit_trail')
        ->where('auditable_type', User::class)
        ->where('auditable_id', $targetId)
        ->where('action', 'deleted')
        ->first();

    expect($row)->not->toBeNull();

    $oldValues = json_decode($row->old_values, true);

    expect($oldValues)
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});

test('the xlsx export honours the active filters', function () {
    $this->skipUnlessZipAvailable();

    $admin = User::factory()->admin()->create();

    DB::table('audit_trail')->insert([
        'user_id' => $admin->id,
        'action' => 'login',
        'auditable_type' => null,
        'auditable_id' => null,
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    DB::table('audit_trail')->insert([
        'user_id' => null,
        'action' => 'lockout',
        'auditable_type' => null,
        'auditable_id' => null,
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.audit-trail.export.xlsx', ['action' => 'login']));

    $response->assertOk();

    $rows = readXlsxRows($response->streamedContent());

    // Header + the 1 matching login row -- no Total row for Audit Trail,
    // and the export's own report_exported self-row doesn't match the
    // `login` filter so it's excluded.
    expect($rows)->toHaveCount(2);
});

test('exporting audit trail to pdf returns a real PDF and audits exactly one row, and viewing the index writes none', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.audit-trail.index'));
    expect(AuditLog::where('action', 'report_exported')->count())->toBe(0);

    $response = $this->actingAs($admin)->get(route('admin.audit-trail.export.pdf'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');

    $audit = AuditLog::where('action', 'report_exported')->sole();
    expect($audit->new_values['report'])->toBe('audit-trail');
    expect($audit->new_values['format'])->toBe('pdf');
});

test('every other role gets 403 on the audit trail export routes and writes no audit row', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user)->get(route('admin.audit-trail.export.pdf'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.audit-trail.export.xlsx'))->assertForbidden();

    expect(AuditLog::where('action', 'report_exported')->count())->toBe(0);
})->with(['frontlineStaff', 'artist', 'cashier', 'productionStaff', 'accountingStaff']);

test('the xlsx export is uncapped past the 25-row page size', function () {
    $this->skipUnlessZipAvailable();

    $admin = User::factory()->admin()->create();

    for ($i = 0; $i < 30; $i++) {
        DB::table('audit_trail')->insert([
            'user_id' => $admin->id,
            'action' => 'login',
            'auditable_type' => null,
            'auditable_id' => null,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);
    }

    $response = $this->actingAs($admin)->get(route('admin.audit-trail.export.xlsx', ['action' => 'login']));

    $response->assertOk();

    $rows = readXlsxRows($response->streamedContent());

    // Header + 30 -- no Total row, the export's own report_exported
    // self-row excluded by the `login` filter.
    expect($rows)->toHaveCount(31);
});

test('exported label cells are display-ready -- headlined role and action, not raw snake_case', function () {
    $this->skipUnlessZipAvailable();

    $admin = User::factory()->admin()->create();

    DB::table('audit_trail')->insert([
        'user_id' => $admin->id,
        'action' => 'failed_login',
        'auditable_type' => null,
        'auditable_id' => null,
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.audit-trail.export.xlsx', ['action' => 'failed_login']));

    $response->assertOk();

    $rows = readXlsxRows($response->streamedContent());

    expect($rows[1][2])->toBe('Admin');
    expect($rows[1][3])->toBe('Failed Login');
});

test('audit trail date filters name shop days, not UTC days', function (string $day, int $expectedEntries) {
    // Creating the admin is audited too; keep that row out of both days.
    $this->travelTo('2026-10-10 12:00:00');
    $admin = User::factory()->admin()->create();

    // 1 AM on October 3 in Manila, still October 2 in UTC.
    DB::table('audit_trail')->insert([
        'user_id' => $admin->id,
        'action' => 'login',
        'auditable_type' => User::class,
        'auditable_id' => $admin->id,
        'ip_address' => '127.0.0.1',
        'created_at' => '2026-10-02 17:00:00',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.audit-trail.index', ['from' => $day, 'to' => $day]));

    $response->assertInertia(fn (Assert $page) => $page->has('entries.data', $expectedEntries));
})->with([
    'the shop day it happened on' => ['2026-10-03', 1],
    'the UTC day it is stored under' => ['2026-10-02', 0],
]);
