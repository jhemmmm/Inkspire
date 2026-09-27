<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the landing page is public', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});

test('a signed in staff member still lands on the same page', function () {
    $response = $this->actingAs(User::factory()->create())->get('/');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});

test('the landing page points customers at nothing staff-only', function () {
    // The shop hands staff the portal address privately. Nothing on the
    // public page should advertise the sign-in screen or the queue board.
    $html = $this->get('/')->getContent();

    expect($html)->not->toContain('Staff sign in')
        ->and($html)->not->toContain('Live queue')
        ->and($html)->not->toContain('/queue')
        ->and($html)->not->toContain('/login');
});
