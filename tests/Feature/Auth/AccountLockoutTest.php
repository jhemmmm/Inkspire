<?php

use App\Models\User;
use Database\Seeders\SystemConfigurationSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(SystemConfigurationSeeder::class);
});

test('5 consecutive failed attempts locks the account for the configured duration', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $user->refresh();

    expect($user->locked_until)->not->toBeNull();
    expect($user->locked_until->isFuture())->toBeTrue();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('a deactivated account cannot log in even with the correct password', function () {
    $user = User::factory()->deactivated()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('failed attempts and the resulting lockout are written to the audit trail', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    expect(DB::table('audit_trail')->where('action', 'failed_login')->count())->toBe(5);
    expect(DB::table('audit_trail')->where('action', 'lockout')->exists())->toBeTrue();
});

test('the attempt that locks the account says it is locked', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 4; $i++) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
    }
    $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);

    $response->assertSessionHasErrors(['email' => 'Too many failed attempts. This account is locked for 15 minutes. Contact your Admin if you need immediate access.']);
});

test('attempts after the lockout get the lock message, not a 429', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
    }
    $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);

    $response->assertSessionHasErrors(['email' => 'Too many failed attempts. This account is locked for 15 minutes. Contact your Admin if you need immediate access.']);
});
