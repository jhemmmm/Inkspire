<?php

use App\Enums\ArtistStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can view the user management list', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('admin/UserManagement'));
});

test('admin deactivating a user flips is_active to false and writes an audited update row', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)->patch(route('admin.users.deactivate', $target));

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

test('admin cannot deactivate their own account', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->patch(route('admin.users.deactivate', $admin));

    $response->assertForbidden();

    expect($admin->fresh()->is_active)->toBeTrue();
});

test('an admin can deactivate another admin', function () {
    // Blocked while Admin existed. With one administrative role left, an
    // Admin nobody can deactivate would be permanent.
    $admin = User::factory()->admin()->create();
    $anotherAdmin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.users.deactivate', $anotherAdmin));

    expect($anotherAdmin->fresh()->is_active)->toBeFalse();
});

test('admin can deactivate a staff role user', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->cashier()->create();

    $this->actingAs($admin)->patch(route('admin.users.deactivate', $target));

    expect($target->fresh()->is_active)->toBeFalse();
});

test('admin can deactivate an admin', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.users.deactivate', $target));

    expect($target->fresh()->is_active)->toBeFalse();
});

test('admin can reactivate a previously deactivated user', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->deactivated()->create();

    $this->actingAs($admin)->patch(route('admin.users.reactivate', $target));

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
    $admin = User::factory()->admin()->create();
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available->value]);

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/UserManagement')
        ->where('users', function ($users) use ($admin, $artist) {
            $adminRow = collect($users)->firstWhere('id', $admin->id);
            $artistRow = collect($users)->firstWhere('id', $artist->id);

            expect($adminRow['artist_status'])->toBeNull();
            expect($adminRow['exceeded_break_time'])->toBeFalse();
            expect($artistRow['artist_status'])->toBe(ArtistStatus::Available->value);

            return true;
        })
    );
});

test('exceeded_break_time is true once break_started_at exceeds max_artist_break_minutes and false otherwise', function () {
    $admin = User::factory()->admin()->create();
    $exceeded = User::factory()->artist()->create([
        'artist_status' => ArtistStatus::OnBreak->value,
        'break_started_at' => now()->subMinutes(30),
    ]);
    $withinLimit = User::factory()->artist()->create([
        'artist_status' => ArtistStatus::OnBreak->value,
        'break_started_at' => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

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

test('an account serving a lockout is flagged in the list', function () {
    // The Admin dashboard counts these and links here, so this page has to
    // show which account the count is about.
    $admin = User::factory()->admin()->create();
    $lockedOut = User::factory()->cashier()->create(['locked_until' => now()->addMinutes(15)]);
    $expired = User::factory()->cashier()->create(['locked_until' => now()->subMinutes(15)]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($lockedOut, $expired) {
            $users = collect($page->toArray()['props']['users']);

            expect($users->firstWhere('id', $lockedOut->id)['is_locked_out'])->toBeTrue();
            expect($users->firstWhere('id', $expired->id)['is_locked_out'])->toBeFalse();
        });
});
