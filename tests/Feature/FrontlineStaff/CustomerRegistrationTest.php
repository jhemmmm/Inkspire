<?php

use App\Models\Customer;
use App\Models\User;

test('frontline staff can register a new customer', function () {
    $staff = User::factory()->frontlineStaff()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.customers.store'), [
        'name' => 'Juan Dela Cruz',
        'contact_number' => '09171234567',
        'email' => 'juan@example.com',
        'address' => '123 Rizal St, Quezon City',
    ]);

    $customer = Customer::firstWhere('contact_number', '09171234567');

    expect($customer)->not->toBeNull();
    $response->assertRedirect(route('frontline-staff.new-visit', ['customer' => $customer->id]));
});

test('registering a customer with a duplicate contact number fails validation', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $existing = Customer::factory()->create(['contact_number' => '09171234567']);

    $response = $this->actingAs($staff)->post(route('frontline-staff.customers.store'), [
        'name' => 'Another Person',
        'contact_number' => $existing->contact_number,
        'email' => 'another@example.com',
        'address' => '456 Bonifacio St, Quezon City',
    ]);

    $response->assertSessionHasErrors('contact_number');
    expect(Customer::count())->toBe(1);
});

test('registering a customer requires name, contact number, email, and address', function () {
    $staff = User::factory()->frontlineStaff()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.customers.store'), []);

    $response->assertSessionHasErrors(['name', 'contact_number', 'email', 'address']);
});

test('a non frontline staff role is blocked from registering a customer', function () {
    $user = User::factory()->create(['role' => 'cashier']);

    $response = $this->actingAs($user)->post(route('frontline-staff.customers.store'), [
        'name' => 'Juan Dela Cruz',
        'contact_number' => '09171234567',
        'email' => 'juan@example.com',
        'address' => '123 Rizal St, Quezon City',
    ]);

    $response->assertForbidden();
    expect(Customer::count())->toBe(0);
});
