<?php

use App\Models\SystemConfiguration;
use App\Models\User;
use Database\Seeders\SystemConfigurationSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can view the system configuration screen grouped by tab', function () {
    $this->seed(SystemConfigurationSeeder::class);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.system-configuration.edit'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('admin/SystemConfiguration')
        ->has('configurations.security')
        ->has('configurations.business_rules')
        ->has('configurations.file_handling')
    );
});

test('admin can update a configuration value and it takes effect on the next read', function () {
    $this->seed(SystemConfigurationSeeder::class);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->patch(
        route('admin.system-configuration.update', 'account_lockout_max_attempts'),
        ['value' => 10],
    );

    $response->assertSessionHasNoErrors();

    expect(SystemConfiguration::getInt('account_lockout_max_attempts', 0))->toBe(10);
});

test('an invalid value is rejected and does not persist', function () {
    $this->seed(SystemConfigurationSeeder::class);

    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->patch(
        route('admin.system-configuration.update', 'account_lockout_max_attempts'),
        ['value' => -5],
    );

    $response->assertSessionHasErrors('value');

    expect(SystemConfiguration::getInt('account_lockout_max_attempts', 0))->toBe(5);
});

test('the minimum down payment only accepts whole percentages from 50 to 60', function (int|float $value, bool $accepted) {
    $response = $this->actingAs(User::factory()->admin()->create())->patch(
        route('admin.system-configuration.update', 'down_payment_minimum_percentage'),
        ['value' => $value],
    );

    $accepted ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('value');

    expect(SystemConfiguration::getInt('down_payment_minimum_percentage', 0))->toBe($accepted ? $value : 50);
})->with([
    'below' => [49, false],
    'lowest' => [50, true],
    'fraction' => [55.5, false],
    'highest' => [60, true],
    'above' => [61, false],
]);
