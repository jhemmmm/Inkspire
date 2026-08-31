<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('a successful login writes a login audit_trail row and captures the session id', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();

    expect(
        DB::table('audit_trail')->where('action', 'login')->where('user_id', $user->id)->exists()
    )->toBeTrue();

    expect($user->fresh()->current_session_id)->not->toBeNull();
});
