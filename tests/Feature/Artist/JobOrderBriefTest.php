<?php

use App\Enums\JobOrderStatus;
use App\Models\Customer;
use App\Models\DesignFile;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('the artist queue carries the job type, so Type A and Type B are told apart in the list', function () {
    $artist = User::factory()->artist()->create();
    JobOrder::factory()->assignedTo($artist)->create(['type' => 'type_a']);

    $response = $this->actingAs($artist)->get(route('artist.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('jobOrders.0.type', 'type_a'));
});

test('the shared pool carries the job type too', function () {
    $artist = User::factory()->artist()->create();
    JobOrder::factory()->create([
        'type' => 'type_b',
        'status' => JobOrderStatus::Intake,
        'assigned_artist_id' => null,
    ]);

    $response = $this->actingAs($artist)->get(route('artist.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('availableJobOrders.0.type', 'type_b'));
});

test('the workspace brief carries the design details an artist needs to start work', function () {
    $artist = User::factory()->artist()->create();
    $customer = Customer::factory()->create(['name' => 'Marites Dela Cruz', 'organization' => 'Barangay Hall']);
    $queueEntry = QueueEntry::factory()->for($customer)->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->for($queueEntry)->create([
        'type' => 'type_b',
        'status' => JobOrderStatus::InConsultation,
        'print_size' => 'Tarpaulin 3x6ft',
        'width_ft' => 3,
        'height_ft' => 6,
        'quantity' => 4,
        'deadline' => '2026-10-01',
        'client_notes' => 'Please use the blue logo.',
    ]);

    $response = $this->actingAs($artist)->get(route('artist.job-orders.show', $jobOrder));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('jobOrder.type', 'type_b')
        ->where('jobOrder.deadline', '2026-10-01')
        ->where('jobOrder.customer_name', 'Marites Dela Cruz')
        ->where('jobOrder.customer_organization', 'Barangay Hall')
        ->where('jobOrder.print_size', 'Tarpaulin 3x6ft')
        ->where('jobOrder.width_ft', '3.00')
        ->where('jobOrder.height_ft', '6.00')
        ->where('jobOrder.quantity', 4)
        ->where('jobOrder.client_notes', 'Please use the blue logo.'));
});

test('the workspace exposes the file the customer supplied, not just the verdict on it', function () {
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create([
        'status' => JobOrderStatus::InConsultation,
        'file_path' => UploadedFile::fake()->image('artwork.jpg')->store('job-orders', 'local'),
    ]);

    $response = $this->actingAs($artist)->get(route('artist.job-orders.show', $jobOrder));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->whereNot('design.customerFileUrl', null));
});

test('a job order with no supplied file reports no customer file rather than a broken link', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create([
        'status' => JobOrderStatus::InConsultation,
        'file_path' => null,
    ]);

    $response = $this->actingAs($artist)->get(route('artist.job-orders.show', $jobOrder));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('design.customerFileUrl', null));
});

test('the full-size design link sends the assigned artist to a signed URL for the stored file', function () {
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => JobOrderStatus::PendingReview]);
    DesignFile::factory()->for($jobOrder)->create(['file_path' => 'design-files/poster.png']);

    $response = $this->actingAs($artist)->get(route('artist.job-orders.design.show', $jobOrder));

    $response->assertRedirectContains('design-files/poster.png');
});

test('the full-size design link is forbidden to an artist the job order is not assigned to', function () {
    Storage::fake('local');
    $jobOrder = JobOrder::factory()->assignedTo(User::factory()->artist()->create())->create(['status' => JobOrderStatus::PendingReview]);
    DesignFile::factory()->for($jobOrder)->create();

    $response = $this->actingAs(User::factory()->artist()->create())->get(route('artist.job-orders.design.show', $jobOrder));

    $response->assertForbidden();
});

test('the full-size design link returns 404 before any design has been saved', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => JobOrderStatus::InDesign]);

    $response = $this->actingAs($artist)->get(route('artist.job-orders.design.show', $jobOrder));

    $response->assertNotFound();
});
