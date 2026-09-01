<?php

use App\Models\Customer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('frontline staff can search for a customer by partial name', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create(['name' => 'Juan Dela Cruz']);
    Customer::factory()->create(['name' => 'Someone Else']);

    $response = $this->actingAs($staff)->get(route('frontline-staff.new-visit', ['q' => 'Dela Cruz']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('frontline-staff/NewVisit')
        ->has('customers', 1)
        ->where('customers.0.id', $customer->id)
    );
});

test('frontline staff can search for a customer by a partial contact number', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create(['contact_number' => '09171234567']);
    Customer::factory()->create(['contact_number' => '09209999999']);

    $response = $this->actingAs($staff)->get(route('frontline-staff.new-visit', ['q' => '1234567']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('frontline-staff/NewVisit')
        ->has('customers', 1)
        ->where('customers.0.id', $customer->id)
    );
});

test('a search with no query returns no results and no q filter key', function () {
    $staff = User::factory()->frontlineStaff()->create();
    Customer::factory()->create();

    $response = $this->actingAs($staff)->get(route('frontline-staff.new-visit'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('frontline-staff/NewVisit')
        ->has('customers', 0)
        ->missing('filters.q')
    );
});

test('a non frontline staff role is blocked from the new visit search route', function () {
    $user = User::factory()->create(['role' => 'cashier']);

    $response = $this->actingAs($user)->get(route('frontline-staff.new-visit'));

    $response->assertForbidden();
});
