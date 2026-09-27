<?php

use App\Enums\JobOrderStatus;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\User;

test('the dashboard returns no search results until a term is given', function () {
    $frontline = User::factory()->frontlineStaff()->create();
    JobOrder::factory()->create(['description' => 'Tarpaulin, 3x5ft']);

    $response = $this->actingAs($frontline)->get(route('frontline-staff.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('searchResults', []));
});

test('a job order is findable by its number', function () {
    $frontline = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create(['number' => 'JO-2026-4242', 'quoted_amount' => 500]);
    JobOrder::factory()->create(['number' => 'JO-2026-9999']);

    $response = $this->actingAs($frontline)->get(route('frontline-staff.dashboard', ['q' => '4242']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('searchResults', 1)
        ->where('searchResults.0.id', $jobOrder->id)
        ->where('searchResults.0.display_total', 500)
        ->has('searchResults.0.amount_paid'));
});

test('a job order is findable by its description and by the customer name', function () {
    $frontline = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create(['name' => 'Maria Santos']);
    $queueEntry = QueueEntry::factory()->for($customer)->create();
    $jobOrder = JobOrder::factory()->for($queueEntry)->create(['description' => 'Tarpaulin, 3x5ft']);
    JobOrder::factory()->create(['description' => 'Sticker, A4']);

    $byDescription = $this->actingAs($frontline)->get(route('frontline-staff.dashboard', ['q' => 'Tarpaulin']));
    $byDescription->assertInertia(fn ($page) => $page
        ->has('searchResults', 1)
        ->where('searchResults.0.id', $jobOrder->id));

    $byCustomer = $this->actingAs($frontline)->get(route('frontline-staff.dashboard', ['q' => 'Santos']));
    $byCustomer->assertInertia(fn ($page) => $page
        ->has('searchResults', 1)
        ->where('searchResults.0.id', $jobOrder->id));
});

test('search reaches job orders at every stage, not only those ready for pickup', function () {
    $frontline = User::factory()->frontlineStaff()->create();
    JobOrder::factory()->create(['description' => 'Banner run', 'status' => JobOrderStatus::InDesign]);
    JobOrder::factory()->create(['description' => 'Banner run', 'status' => JobOrderStatus::ReadyForPickup]);
    JobOrder::factory()->create(['description' => 'Banner run', 'cancelled_at' => now()]);

    $response = $this->actingAs($frontline)->get(route('frontline-staff.dashboard', ['q' => 'Banner run']));

    $response->assertInertia(fn ($page) => $page->has('searchResults', 3));
});

test('a wildcard character in the term is escaped rather than matching everything', function () {
    $frontline = User::factory()->frontlineStaff()->create();
    JobOrder::factory()->create(['description' => 'Sticker, A4']);
    JobOrder::factory()->create(['description' => '100% cotton tote']);

    $response = $this->actingAs($frontline)->get(route('frontline-staff.dashboard', ['q' => '100%']));

    $response->assertInertia(fn ($page) => $page
        ->has('searchResults', 1)
        ->where('searchResults.0.description', '100% cotton tote'));
});

test('search results are capped at 25', function () {
    $frontline = User::factory()->frontlineStaff()->create();
    JobOrder::factory()->count(30)->create(['description' => 'Bulk flyer batch']);

    $response = $this->actingAs($frontline)->get(route('frontline-staff.dashboard', ['q' => 'Bulk flyer']));

    $response->assertInertia(fn ($page) => $page->has('searchResults', 25));
});
