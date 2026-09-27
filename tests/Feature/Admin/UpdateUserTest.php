<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

test('admin can update a user name, email and role', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->cashier()->create();

    $response = $this->actingAs($admin)->patch(route('admin.users.update', $target), [
        'name' => 'Renamed Person',
        'email' => 'renamed.person@example.com',
        'role' => UserRole::ProductionStaff->value,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $target->refresh();
    expect($target->name)->toBe('Renamed Person');
    expect($target->email)->toBe('renamed.person@example.com');
    expect($target->role)->toBe(UserRole::ProductionStaff);
});

test('a user can keep their own email unchanged', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->cashier()->create();

    $response = $this->actingAs($admin)->patch(route('admin.users.update', $target), [
        'name' => 'Same Email',
        'email' => $target->email,
        'role' => UserRole::Cashier->value,
    ]);

    $response->assertSessionHasNoErrors();
    expect($target->refresh()->name)->toBe('Same Email');
});

test('an email already used by another user is rejected', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->cashier()->create();
    $other = User::factory()->create();

    $response = $this->actingAs($admin)->patch(route('admin.users.update', $target), [
        'name' => $target->name,
        'email' => $other->email,
        'role' => UserRole::Cashier->value,
    ]);

    $response->assertSessionHasErrors('email');
    expect($target->refresh()->email)->not->toBe($other->email);
});

test('a blank password keeps the current password', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->cashier()->create();
    $originalHash = $target->password;

    $this->actingAs($admin)->patch(route('admin.users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => UserRole::Cashier->value,
        'password' => '',
        'password_confirmation' => '',
    ])->assertSessionHasNoErrors();

    expect($target->refresh()->password)->toBe($originalHash);
});

test('a new password lets the user log in with it', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->cashier()->create();

    $this->actingAs($admin)->patch(route('admin.users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => UserRole::Cashier->value,
        'password' => 'NewP@ssw0rd2026',
        'password_confirmation' => 'NewP@ssw0rd2026',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('NewP@ssw0rd2026', $target->refresh()->password))->toBeTrue();

    auth()->logout();

    $this->post(route('login.store'), [
        'email' => $target->email,
        'password' => 'NewP@ssw0rd2026',
    ]);

    $this->assertAuthenticatedAs($target);
});

test('a weak new password is rejected', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->cashier()->create();

    $response = $this->actingAs($admin)->patch(route('admin.users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => UserRole::Cashier->value,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('password');
});

test('changing a role to artist assigns an artist label', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->cashier()->create();

    $this->actingAs($admin)->patch(route('admin.users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => UserRole::Artist->value,
    ])->assertSessionHasNoErrors();

    $target->refresh();
    expect($target->role)->toBe(UserRole::Artist);
    expect($target->artist_label)->toBe('Artist 1');
});

test('changing a role away from artist clears the artist label', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->artist()->create();

    $this->actingAs($admin)->patch(route('admin.users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'role' => UserRole::Cashier->value,
    ])->assertSessionHasNoErrors();

    expect($target->refresh()->artist_label)->toBeNull();
});

test('an artist who stays an artist keeps their label', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->artist()->create();
    $target = User::factory()->artist()->create();

    $this->actingAs($admin)->patch(route('admin.users.update', $target), [
        'name' => 'Renamed Artist',
        'email' => $target->email,
        'role' => UserRole::Artist->value,
    ])->assertSessionHasNoErrors();

    expect($target->refresh()->artist_label)->toBe('Artist 2');
});

test('an admin cannot change their own role', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => UserRole::Cashier->value,
    ]);

    $response->assertSessionHasErrors('role');
    expect($admin->refresh()->role)->toBe(UserRole::Admin);
});

test('an admin can update their own name while keeping their role', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
        'name' => 'Self Renamed',
        'email' => $admin->email,
        'role' => UserRole::Admin->value,
    ])->assertSessionHasNoErrors();

    expect($admin->refresh()->name)->toBe('Self Renamed');
});

test('a staff role is forbidden from the update user route', function () {
    $cashier = User::factory()->cashier()->create();
    $target = User::factory()->productionStaff()->create();

    $response = $this->actingAs($cashier)->patch(route('admin.users.update', $target), [
        'name' => 'Hijacked',
        'email' => $target->email,
        'role' => UserRole::Admin->value,
    ]);

    $response->assertForbidden();
    expect($target->refresh()->role)->toBe(UserRole::ProductionStaff);
});

test('updating a user writes an audit_trail row', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->cashier()->create();

    $this->actingAs($admin)->patch(route('admin.users.update', $target), [
        'name' => 'Audited Update',
        'email' => $target->email,
        'role' => UserRole::Cashier->value,
    ])->assertSessionHasNoErrors();

    expect(
        DB::table('audit_trail')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $target->id)
            ->where('action', 'updated')
            ->exists()
    )->toBeTrue();
});
