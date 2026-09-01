<?php

use App\Models\User;
use Database\Seeders\SystemConfigurationSeeder;

test('an idle session past the configured timeout is logged out with the expiry message', function () {
    $this->seed(SystemConfigurationSeeder::class);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    // First request seeds last_activity_at in the session.
    $this->get(route('owner.dashboard'))->assertOk();

    // Push last_activity_at back in time past the configured 20 minute
    // idle timeout.
    $this->withSession(['last_activity_at' => now()->subMinutes(25)]);

    $response = $this->get(route('owner.dashboard'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('sessionMessage');
    $this->assertGuest();
});

test('an active session within the timeout window is not logged out', function () {
    $this->seed(SystemConfigurationSeeder::class);

    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $this->get(route('owner.dashboard'))->assertOk();

    $this->withSession(['last_activity_at' => now()->subMinutes(5)]);

    $response = $this->get(route('owner.dashboard'));

    $response->assertOk();
});
