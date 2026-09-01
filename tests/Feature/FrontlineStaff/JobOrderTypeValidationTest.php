<?php

use App\Models\Customer;
use App\Models\User;

test('a type a row with no file fails validation on that row\'s file field', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            ['description' => 'Tarpaulin, 3x5ft', 'type' => 'type_a'],
        ],
    ]);

    $response->assertSessionHasErrors('job_orders.0.file');
});

test('a type b row with no file passes validation', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            ['description' => 'Sticker, A4', 'type' => 'type_b'],
        ],
    ]);

    $response->assertSessionDoesntHaveErrors('job_orders.0.file');
});

test('the required file rule resolves per row, not globally', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            ['description' => 'Sticker, A4', 'type' => 'type_b'],
            ['description' => 'Tarpaulin, 3x5ft', 'type' => 'type_a'],
        ],
    ]);

    $response->assertSessionDoesntHaveErrors('job_orders.0.file');
    $response->assertSessionHasErrors('job_orders.1.file');
});
