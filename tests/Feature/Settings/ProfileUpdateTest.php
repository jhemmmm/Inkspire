<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('a user can set their own picture', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ]);

    $response->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->avatar_path);
});

test('a user can remove their own picture', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $user->replaceAvatar(UploadedFile::fake()->image('me.jpg'), false);
    $oldPath = $user->avatar_path;

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'remove_avatar' => '1',
        ]);

    $response->assertSessionHasNoErrors();

    expect($user->refresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($oldPath);
});

test('the avatar appears in auth.user', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $user->replaceAvatar(UploadedFile::fake()->image('me.jpg'), false);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertInertia(fn ($page) => $page->where(
        'auth.user.avatar',
        Storage::disk('public')->url($user->avatar_path),
    ));
});

test('a user cannot delete their own account through profile settings', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->delete(route('profile.update'))
        ->assertMethodNotAllowed();

    expect($user->fresh())->not->toBeNull();
});
