<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('owner can view the audit trail', function () {
    $owner = User::factory()->owner()->create();

    DB::table('audit_trail')->insert([
        'user_id' => $owner->id,
        'action' => 'login',
        'auditable_type' => User::class,
        'auditable_id' => $owner->id,
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($owner)->get(route('owner.audit-trail.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('owner/AuditTrail'));
});

test('audit trail can be filtered by action', function () {
    $owner = User::factory()->owner()->create();

    DB::table('audit_trail')->insert([
        'user_id' => $owner->id,
        'action' => 'login',
        'auditable_type' => User::class,
        'auditable_id' => $owner->id,
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

    $response = $this->actingAs($owner)->get(route('owner.audit-trail.index', ['action' => 'lockout']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('owner/AuditTrail')
        ->has('entries.data', 1)
        ->where('entries.data.0.action', 'lockout')
    );
});

test('audit trail entries preserve the D-03 before/after value shape', function () {
    $owner = User::factory()->owner()->create();
    $target = User::factory()->create();

    $this->actingAs($owner)->patch(route('owner.users.deactivate', $target));

    $response = $this->actingAs($owner)->get(route('owner.audit-trail.index', ['action' => 'updated']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('owner/AuditTrail')
        ->where('entries.data.0.new_values.is_active', false)
    );
});
