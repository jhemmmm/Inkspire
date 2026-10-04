<?php

use App\Http\Middleware\EnforceIdleSessionTimeout;
use App\Models\User;
use Database\Seeders\SystemConfigurationSeeder;

test('an idle session past the configured timeout is logged out with the expiry message', function () {
    $this->seed(SystemConfigurationSeeder::class);

    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    // First request seeds last_activity_at in the session.
    $this->get(route('admin.dashboard'))->assertOk();

    // Push last_activity_at back in time past the configured 20 minute
    // idle timeout.
    $this->withSession(['last_activity_at' => now()->subMinutes(25)]);

    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('sessionMessage');
    $this->assertGuest();
});

test('an active session within the timeout window is not logged out', function () {
    $this->seed(SystemConfigurationSeeder::class);

    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $this->get(route('admin.dashboard'))->assertOk();

    $this->withSession(['last_activity_at' => now()->subMinutes(5)]);

    $response = $this->get(route('admin.dashboard'));

    $response->assertOk();
});

test('a page refreshing itself in the background does not count as activity', function () {
    $this->seed(SystemConfigurationSeeder::class);

    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $lastActivity = now()->subMinutes(15);
    $this->withSession(['last_activity_at' => $lastActivity]);

    // Still inside the 20 minute window, so the poll is served...
    $this->withHeaders([EnforceIdleSessionTimeout::POLL_HEADER => 'true'])
        ->get(route('admin.dashboard'))
        ->assertOk()
        // ...but it leaves the clock where the person last touched it.
        ->assertSessionHas('last_activity_at', fn ($value) => $lastActivity->equalTo($value));
});

test('a dashboard left open and polling is still logged out once the timeout passes', function () {
    $this->seed(SystemConfigurationSeeder::class);

    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $this->withSession(['last_activity_at' => now()->subMinutes(25)]);

    $response = $this->withHeaders([EnforceIdleSessionTimeout::POLL_HEADER => 'true'])
        ->get(route('admin.dashboard'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
