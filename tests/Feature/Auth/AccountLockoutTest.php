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
