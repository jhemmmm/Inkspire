<?php

use App\Models\JobOrder;
use App\Models\User;

test('a cashier viewing the receipt for a job order with zero transactions gets a 404', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    $response = $this->actingAs($cashier)->get(route('cashier.job-orders.receipt.show', $jobOrder));

    $response->assertNotFound();
});
