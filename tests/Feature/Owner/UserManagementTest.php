<?php

use App\Enums\ArtistStatus;
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

test('admin cannot deactivate the owner or another admin', function () {
    $admin = User::factory()->admin()->create();

    $owner = User::factory()->owner()->create();
    $this->actingAs($admin)->patch(route('owner.users.deactivate', $owner))->assertForbidden();

    $anotherAdmin = User::factory()->admin()->create();
    $this->actingAs($admin)->patch(route('owner.users.deactivate', $anotherAdmin))->assertForbidden();
});

test('admin can deactivate a staff role user', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->cashier()->create();

    $this->actingAs($admin)->patch(route('owner.users.deactivate', $target));

    expect($target->fresh()->is_active)->toBeFalse();
});

test('owner can deactivate an admin', function () {
    $owner = User::factory()->owner()->create();
    $target = User::factory()->admin()->create();

    $this->actingAs($owner)->patch(route('owner.users.deactivate', $target));

    expect($target->fresh()->is_active)->toBeFalse();
});

test('owner can reactivate a previously deactivated user', function () {
    $owner = User::factory()->owner()->create();
    $target = User::factory()->deactivated()->create();

    $this->actingAs($owner)->patch(route('owner.users.reactivate', $target));

    expect($target->fresh()->is_active)->toBeTrue();

    expect(
        DB::table('audit_trail')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $target->id)
            ->where('action', 'updated')
            ->where('new_values', 'like', '%"is_active":true%')
            ->exists()
    )->toBeTrue();
});

test('the user list exposes artist_status and exceeded_break_time only for artist-role users, null for everyone else', function () {
    $owner = User::factory()->owner()->create();
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available->value]);

    $response = $this->actingAs($owner)->get(route('owner.users.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('owner/UserManagement')
        ->where('users', function ($users) use ($owner, $artist) {
            $ownerRow = collect($users)->firstWhere('id', $owner->id);
            $artistRow = collect($users)->firstWhere('id', $artist->id);

            expect($ownerRow['artist_status'])->toBeNull();
            expect($ownerRow['exceeded_break_time'])->toBeFalse();
            expect($artistRow['artist_status'])->toBe(ArtistStatus::Available->value);

            return true;
        })
    );
});

test('exceeded_break_time is true once break_started_at exceeds max_artist_break_minutes and false otherwise', function () {
    $owner = User::factory()->owner()->create();
    $exceeded = User::factory()->artist()->create([
        'artist_status' => ArtistStatus::OnBreak->value,
        'break_started_at' => now()->subMinutes(30),
    ]);
    $withinLimit = User::factory()->artist()->create([
        'artist_status' => ArtistStatus::OnBreak->value,
        'break_started_at' => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($owner)->get(route('owner.users.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('users', function ($users) use ($exceeded, $withinLimit) {
            $exceededRow = collect($users)->firstWhere('id', $exceeded->id);
            $withinLimitRow = collect($users)->firstWhere('id', $withinLimit->id);

            expect($exceededRow['exceeded_break_time'])->toBeTrue();
            expect($withinLimitRow['exceeded_break_time'])->toBeFalse();

            return true;
        })
    );
});
