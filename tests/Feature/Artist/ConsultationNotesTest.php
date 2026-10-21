<?php

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an artist can view their own assigned job order workspace', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_consultation']);

    $response = $this->actingAs($artist)->get(route('artist.job-orders.show', $jobOrder));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('artist/JobOrderWorkspace'));
});

test('an artist is forbidden from viewing a job order assigned to a different artist', function () {
    $artist = User::factory()->artist()->create();
    $otherArtist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($otherArtist)->create(['status' => 'in_consultation']);

    $response = $this->actingAs($artist)->get(route('artist.job-orders.show', $jobOrder));

    $response->assertForbidden();
});

test('an artist can save consultation notes while the job order is in_consultation', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_consultation']);

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.consultation.update', $jobOrder), [
        'consultation_notes' => 'Client wants a matte finish.',
    ]);

    $response->assertRedirect();
    expect($jobOrder->fresh()->consultation_notes)->toBe('Client wants a matte finish.');
});

test('saving consultation notes on an assigned not yet in_consultation job order returns a 422', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create();

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.consultation.update', $jobOrder), [
        'consultation_notes' => 'Client wants a matte finish.',
    ]);

    $response->assertStatus(422);
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Assigned);
    expect($jobOrder->fresh()->consultation_notes)->toBeNull();
});

test('the workspace hands the consultation notes field its notes, editable only during consultation', function () {
    $artist = User::factory()->artist()->create();
    $inConsultation = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_consultation', 'consultation_notes' => 'Brown and cream, logo on top.']);
    $inDesign = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_design', 'consultation_notes' => 'Bold red text.']);

    $editable = $this->actingAs($artist)->get(route('artist.job-orders.show', $inConsultation));
    $readOnly = $this->actingAs($artist)->get(route('artist.job-orders.show', $inDesign));

    $editable->assertInertia(fn (Assert $page) => $page
        ->where('jobOrder.consultation_notes', 'Brown and cream, logo on top.')
        ->where('jobOrder.canEditConsultation', true));
    $readOnly->assertInertia(fn (Assert $page) => $page
        ->where('jobOrder.consultation_notes', 'Bold red text.')
        ->where('jobOrder.canEditConsultation', false));
});
