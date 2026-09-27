<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('a new login invalidates the previous session on its next request', function () {
    $user = User::factory()->admin()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $sessionId = $user->fresh()->current_session_id;
    expect($sessionId)->not->toBeNull();

    // Simulate a second login elsewhere winning the row, while this test
    // client still sends the first session's cookie (forwarded explicitly
    // below, since the test HTTP client does not carry cookies between
    // requests automatically the way a real browser does).
    $user->forceFill(['current_session_id' => 'a-different-session-id'])->save();

    // Force the next request to resolve a fresh guard/user from the database
    // instead of reusing the in-memory guard cached during login above. A
    // real second HTTP request in production always boots a fresh guard;
    // only the shared test process would otherwise mask this with a stale,
    // pre-update user instance.
    Auth::forgetGuards();

    $response = $this->withCookie(config('session.cookie'), $sessionId)
        ->get(route('admin.dashboard'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('sessionMessage');
    $this->assertGuest();
});

test('a normal authenticated request with a matching session id is unaffected', function () {
    $user = User::factory()->admin()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $sessionId = $user->fresh()->current_session_id;
    expect($sessionId)->not->toBeNull();

    Auth::forgetGuards();

    // Forward the same session id as a cookie to simulate the same browser
    // making the next request (see note above).
    $response = $this->withCookie(config('session.cookie'), $sessionId)
        ->get(route('admin.dashboard'));

    $response->assertOk();
});
