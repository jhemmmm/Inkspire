<?php

use App\Enums\JobOrderStatus;
use App\Models\DesignFile;
use App\Models\JobOrder;
use App\Models\RevisionLog;
use App\Models\User;

test('approving a pending_review job order locks the design file, marks the latest revision_logs row approved, and advances status to design_approved', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'pending_review']);
    DesignFile::factory()->for($jobOrder)->create();
    $revisionLog = RevisionLog::factory()->for($jobOrder)->create();

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.design.approve', $jobOrder));

    $response->assertRedirect();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::DesignApproved);
    expect($jobOrder->fresh()->designFile->locked_at)->not->toBeNull();

    $revisionLog->refresh();
    expect($revisionLog->outcome)->toBe('approved');
    expect($revisionLog->reviewed_at)->not->toBeNull();
});

test('requesting changes bounces status to in_design, marks the revision changes_requested, and leaves design_files.locked_at null', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'pending_review']);
    DesignFile::factory()->for($jobOrder)->create();
    $revisionLog = RevisionLog::factory()->for($jobOrder)->create();

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.design.request-changes', $jobOrder));

    $response->assertRedirect();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::InDesign);
    expect($jobOrder->fresh()->designFile->locked_at)->toBeNull();

    $revisionLog->refresh();
    expect($revisionLog->outcome)->toBe('changes_requested');
    expect($revisionLog->reviewed_at)->not->toBeNull();
});

test('approve on a job order not pending_review returns a 422', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_design']);

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.design.approve', $jobOrder));

    $response->assertStatus(422);
});

test('a non-owning artist is forbidden from approve and request-changes', function () {
    $artist = User::factory()->artist()->create();
    $otherArtist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($otherArtist)->create(['status' => 'pending_review']);
    DesignFile::factory()->for($jobOrder)->create();
    RevisionLog::factory()->for($jobOrder)->create();

    $this->actingAs($artist)->patch(route('artist.job-orders.design.approve', $jobOrder))->assertForbidden();
    $this->actingAs($artist)->patch(route('artist.job-orders.design.request-changes', $jobOrder))->assertForbidden();
});
