<?php

use App\Models\User;

test('a new login invalidates the previous session on its next request', function () {
    $user = User::factory()->owner()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    expect($user->fresh()->current_session_id)->not->toBeNull();

    // Simulate a second login elsewhere winning the row, while this test
    // client still holds the first session's cookie.
    $user->forceFill(['current_session_id' => 'a-different-session-id'])->save();

    $response = $this->get(route('owner.dashboard'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('sessionMessage');
    $this->assertGuest();
});

test('a normal authenticated request with a matching session id is unaffected', function () {
    $user = User::factory()->owner()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response = $this->get(route('owner.dashboard'));

    $response->assertOk();
});
