<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('owner logging in lands on the owner dashboard', function () {
    $user = User::factory()->owner()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('owner.dashboard', absolute: false));
});

test('a non owner/admin role is blocked from the owner portal with a 403', function () {
    $user = User::factory()->create(['role' => 'cashier']);

    $response = $this->actingAs($user)->get(route('owner.dashboard'));

    $response->assertForbidden();
    $response->assertInertia(fn (Assert $page) => $page->component('errors/Forbidden'));
});
