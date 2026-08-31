<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('owner can view the user management list', function () {
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->get(route('owner.users.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('owner/UserManagement'));
});

test('owner deactivating a user flips is_active to false and writes an audited update row', function () {
    $owner = User::factory()->owner()->create();
    $target = User::factory()->create();

    $this->actingAs($owner)->patch(route('owner.users.deactivate', $target));

    expect($target->fresh()->is_active)->toBeFalse();

    expect(
        DB::table('audit_trail')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $target->id)
            ->where('action', 'updated')
            ->exists()
    )->toBeTrue();

    $row = DB::table('audit_trail')
        ->where('auditable_type', User::class)
        ->where('auditable_id', $target->id)
        ->where('action', 'updated')
        ->first();

    expect($row->new_values)->toContain('"is_active":false');
});

test('owner cannot deactivate their own account', function () {
    $owner = User::factory()->owner()->create();

    $response = $this->actingAs($owner)->patch(route('owner.users.deactivate', $owner));

    $response->assertForbidden();

    expect($owner->fresh()->is_active)->toBeTrue();
});
