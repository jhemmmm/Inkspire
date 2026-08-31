<?php

use App\Models\User;

test('weak password is rejected outside production', function () {
    expect(app()->environment())->toBe('testing');

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $response->assertSessionHasErrors('password');
});
