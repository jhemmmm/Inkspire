<?php

use App\Models\User;

/**
 * The `<html>` tag's class drives every `dark:` utility on first paint, so
 * these read it straight off the rendered root view.
 */
const DARK_HTML_TAG = '/<html[^>]*\bclass="dark"/';

test('guest pages render light even when the saved appearance is dark', function (string $routeName) {
    $response = $this->withUnencryptedCookie('appearance', 'dark')->get(route($routeName));

    expect($response->getContent())->not->toMatch(DARK_HTML_TAG);
})->with(['home', 'login', 'password.request', 'public.tracking.show']);

test('the forbidden page renders light even when the saved appearance is dark', function () {
    $cashier = User::factory()->cashier()->create();

    $response = $this->actingAs($cashier)
        ->withUnencryptedCookie('appearance', 'dark')
        ->get(route('admin.dashboard'));

    $response->assertForbidden();
    expect($response->getContent())->not->toMatch(DARK_HTML_TAG);
});

test('portal pages still render dark when the saved appearance is dark', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->withUnencryptedCookie('appearance', 'dark')
        ->get(route('admin.dashboard'));

    expect($response->getContent())->toMatch(DARK_HTML_TAG);
});
