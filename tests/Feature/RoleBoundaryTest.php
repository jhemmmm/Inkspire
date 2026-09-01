<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<int, array{0: string, 1: string}>
 */
function roleBoundaryPortals(): array
{
    return [
        ['owner', 'owner.dashboard'],
        ['admin', 'owner.dashboard'],
        ['frontlineStaff', 'frontline-staff.dashboard'],
        ['artist', 'artist.dashboard'],
        ['cashier', 'cashier.dashboard'],
        ['productionStaff', 'production-staff.dashboard'],
        ['accountingStaff', 'accounting-staff.dashboard'],
    ];
}

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

test('the 403 forbidden page links to the signed-in user\'s own portal, not the generic dashboard', function () {
    $user = User::factory()->create(['role' => 'cashier']);

    $response = $this->actingAs($user)->get(route('owner.dashboard'));

    $response->assertForbidden();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('errors/Forbidden')
        ->where('dashboardHref', route('cashier.dashboard'))
    );
});

test('each role can access its own portal dashboard', function (string $state, string $routeName) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get(route($routeName))->assertOk();
})->with(roleBoundaryPortals());

test('each role is blocked with 403 from every other role\'s portal', function () {
    $portals = roleBoundaryPortals();

    foreach ($portals as [$state, $ownRoute]) {
        $user = User::factory()->{$state}()->create();

        foreach ($portals as [, $otherRoute]) {
            if ($otherRoute === $ownRoute) {
                continue;
            }

            $this->actingAs($user)->get(route($otherRoute))->assertForbidden();
        }
    }
});
