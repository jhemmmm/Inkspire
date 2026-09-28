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

test('a mistyped job order number comes back to the landing page with its error', function () {
    // The home page's tracker keeps the typed value and shows this message
    // under the field, so the error has to land back on `/`, not /track.
    $response = $this->from('/')->get(route('public.tracking.show', ['number' => '2026-0001']));

    $response->assertRedirect('/');
    $response->assertSessionHasErrors(['number' => 'Enter a job order number like JO-2026-0001.']);
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
