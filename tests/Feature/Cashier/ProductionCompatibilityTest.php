<?php

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the cashier dashboard lists a job order that has already advanced into production', function (JobOrderStatus $status) {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['status' => $status->value, 'description' => 'In Production']);

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('cashier/Dashboard')
        ->has('jobOrders', 1)
        ->where('jobOrders.0.id', $jobOrder->id));
})->with([
    JobOrderStatus::ForProduction,
    JobOrderStatus::Printing,
    JobOrderStatus::QualityCheck,
    JobOrderStatus::ReadyForPickup,
]);

test('the job order payment page is reachable for a job order that has already advanced into production', function (JobOrderStatus $status) {
    $cashier = User::factory()->cashier()->create();
    PricingEntry::factory()->create(['is_active' => true]);
    $jobOrder = JobOrder::factory()->create(['status' => $status->value]);

    $response = $this->actingAs($cashier)->get(route('cashier.job-orders.payment.edit', $jobOrder));

    $response->assertOk();
})->with([
    JobOrderStatus::ForProduction,
    JobOrderStatus::Printing,
    JobOrderStatus::QualityCheck,
    JobOrderStatus::ReadyForPickup,
]);

test('a cash payment can still be recorded for a job order that has already advanced into production', function (JobOrderStatus $status) {
    $cashier = User::factory()->cashier()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 1000]);
    $jobOrder = JobOrder::factory()->create(['status' => $status->value]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.payment.store', $jobOrder), [
        'pricing_entry_id' => $pricingEntry->id,
        'line_amount' => 1000,
        'rush_fee_applied' => false,
        'payment_method' => 'cash',
        'payment_type' => 'full',
        'amount_tendered' => 1000,
    ]);

    $response->assertRedirect();
    expect($jobOrder->fresh()->payment_status->value)->toBe('paid');
})->with([
    JobOrderStatus::ForProduction,
    JobOrderStatus::Printing,
    JobOrderStatus::QualityCheck,
    JobOrderStatus::ReadyForPickup,
]);

test('an OnCredit request can still be made for a job order that has already advanced into production', function (JobOrderStatus $status) {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create(['status' => $status->value, 'total_amount' => 1000]);

    $response = $this->actingAs($cashier)->post(route('cashier.job-orders.credit-request.store', $jobOrder));

    $response->assertRedirect(route('cashier.dashboard'));
    expect($jobOrder->fresh()->payment_status->value)->toBe('credit_pending_approval');
})->with([
    JobOrderStatus::ForProduction,
    JobOrderStatus::Printing,
    JobOrderStatus::QualityCheck,
    JobOrderStatus::ReadyForPickup,
]);
