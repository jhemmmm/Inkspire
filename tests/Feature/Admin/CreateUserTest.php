<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('admin can create an artist', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Ada Artist',
        'email' => 'ada.artist@example.com',
        'role' => UserRole::Artist->value,
        'password' => 'NewP@ssw0rd2026',
        'password_confirmation' => 'NewP@ssw0rd2026',
    ]);

    $response->assertRedirect();

    $created = User::where('email', 'ada.artist@example.com')->firstOrFail();
    expect($created->role)->toBe(UserRole::Artist);
    expect($created->is_active)->toBeTrue();
});

test('admin can create an admin', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Ada Admin',
        'email' => 'ada.admin@example.com',
        'role' => UserRole::Admin->value,
        'password' => 'NewP@ssw0rd2026',
        'password_confirmation' => 'NewP@ssw0rd2026',
    ]);

    $response->assertRedirect();

    $created = User::where('email', 'ada.admin@example.com')->firstOrFail();
    expect($created->role)->toBe(UserRole::Admin);
    expect($created->is_active)->toBeTrue();
});

test('admin can create a staff role user', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Cindy Cashier',
        'email' => 'cindy.cashier@example.com',
        'role' => UserRole::Cashier->value,
        'password' => 'NewP@ssw0rd2026',
        'password_confirmation' => 'NewP@ssw0rd2026',
    ]);

    $response->assertRedirect();

    $created = User::where('email', 'cindy.cashier@example.com')->firstOrFail();
    expect($created->role)->toBe(UserRole::Cashier);
    expect($created->is_active)->toBeTrue();
});

test('admin can create another admin', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Second Admin',
        'email' => 'second.admin@example.com',
        'role' => UserRole::Admin->value,
        'password' => 'NewP@ssw0rd2026',
        'password_confirmation' => 'NewP@ssw0rd2026',
    ]);

    $response->assertSessionHasNoErrors();

    expect(User::where('email', 'second.admin@example.com')->value('role'))->toBe(UserRole::Admin);
});

test('a staff role is forbidden from the create user route', function () {
    $cashier = User::factory()->cashier()->create();

    $response = $this->actingAs($cashier)->post(route('admin.users.store'), [
        'name' => 'Someone New',
        'email' => 'someone.new@example.com',
        'role' => UserRole::Cashier->value,
        'password' => 'NewP@ssw0rd2026',
        'password_confirmation' => 'NewP@ssw0rd2026',
    ]);

    $response->assertForbidden();

    expect(User::where('email', 'someone.new@example.com')->exists())->toBeFalse();
});

test('duplicate email is rejected', function () {
    $admin = User::factory()->admin()->create();
    $existing = User::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Duplicate Email',
        'email' => $existing->email,
        'role' => UserRole::Cashier->value,
        'password' => 'NewP@ssw0rd2026',
        'password_confirmation' => 'NewP@ssw0rd2026',
    ]);

    $response->assertSessionHasErrors('email');
});

test('a weak password is rejected', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Weak Password',
        'email' => 'weak.password@example.com',
        'role' => UserRole::Cashier->value,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('password');
    expect(User::where('email', 'weak.password@example.com')->exists())->toBeFalse();
});

test('a mismatched password confirmation is rejected', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Mismatched Confirmation',
        'email' => 'mismatched.confirmation@example.com',
        'role' => UserRole::Cashier->value,
        'password' => 'NewP@ssw0rd2026',
        'password_confirmation' => 'SomethingElse123!',
    ]);

    $response->assertSessionHasErrors('password');
    expect(User::where('email', 'mismatched.confirmation@example.com')->exists())->toBeFalse();
});

test('an invalid role value is rejected', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Bad Role',
        'email' => 'bad.role@example.com',
        'role' => 'not-a-real-role',
        'password' => 'NewP@ssw0rd2026',
        'password_confirmation' => 'NewP@ssw0rd2026',
    ]);

    $response->assertStatus(403);
    expect(User::where('email', 'bad.role@example.com')->exists())->toBeFalse();
});

test('the created user can log in', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Login Me',
        'email' => 'login.me@example.com',
        'role' => UserRole::Cashier->value,
        'password' => 'NewP@ssw0rd2026',
        'password_confirmation' => 'NewP@ssw0rd2026',
    ]);

    $this->post(route('login.store'), [
        'email' => 'login.me@example.com',
        'password' => 'NewP@ssw0rd2026',
    ]);

    $this->assertAuthenticated();
});

test('creating a user with a picture stores the file, sets avatar_path, and the avatar appears in the Inertia user list', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Picture Person',
        'email' => 'picture.person@example.com',
        'role' => UserRole::Cashier->value,
        'password' => 'NewP@ssw0rd2026',
        'password_confirmation' => 'NewP@ssw0rd2026',
        'avatar' => UploadedFile::fake()->image('avatar.jpg'),
    ]);

    $response->assertRedirect();

    $created = User::where('email', 'picture.person@example.com')->firstOrFail();
    expect($created->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($created->avatar_path);

    $indexResponse = $this->actingAs($admin)->get(route('admin.users.index'));

    $indexResponse->assertInertia(fn ($page) => $page->where(
        'users',
        fn ($users) => $users->firstWhere('id', $created->id)['avatar'] === Storage::disk('public')->url($created->avatar_path),
    ));
});

test('a non-image upload is rejected and stores nothing', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Bad Upload',
        'email' => 'bad.upload@example.com',
        'role' => UserRole::Cashier->value,
        'password' => 'NewP@ssw0rd2026',
        'password_confirmation' => 'NewP@ssw0rd2026',
        'avatar' => UploadedFile::fake()->create('not-an-image.pdf', 10, 'application/pdf'),
    ]);

    $response->assertSessionHasErrors('avatar');
    expect(User::where('email', 'bad.upload@example.com')->exists())->toBeFalse();
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('an oversized picture is rejected and stores nothing', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Oversized Upload',
        'email' => 'oversized.upload@example.com',
        'role' => UserRole::Cashier->value,
        'password' => 'NewP@ssw0rd2026',
        'password_confirmation' => 'NewP@ssw0rd2026',
        'avatar' => UploadedFile::fake()->create('oversized.jpg', 3000),
    ]);

    $response->assertSessionHasErrors('avatar');
    expect(User::where('email', 'oversized.upload@example.com')->exists())->toBeFalse();
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('an svg upload is rejected and stores nothing', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Svg Upload',
        'email' => 'svg.upload@example.com',
        'role' => UserRole::Cashier->value,
        'password' => 'NewP@ssw0rd2026',
        'password_confirmation' => 'NewP@ssw0rd2026',
        'avatar' => UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml'),
    ]);

    $response->assertSessionHasErrors('avatar');
    expect(User::where('email', 'svg.upload@example.com')->exists())->toBeFalse();
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('creating a user writes an audit_trail row', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Audited User',
        'email' => 'audited.user@example.com',
        'role' => UserRole::Cashier->value,
        'password' => 'NewP@ssw0rd2026',
        'password_confirmation' => 'NewP@ssw0rd2026',
    ]);

    $created = User::where('email', 'audited.user@example.com')->firstOrFail();

    expect(
        DB::table('audit_trail')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $created->id)
            ->where('action', 'created')
            ->exists()
    )->toBeTrue();
});
