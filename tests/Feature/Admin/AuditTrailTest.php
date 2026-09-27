<?php

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
