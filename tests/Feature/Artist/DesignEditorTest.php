<?php

use App\Models\DesignFile;
use App\Models\JobOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the workspace exposes a null initialImageUrl and canEdit true when no design file exists yet', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_consultation']);

    $response = $this->actingAs($artist)->get(route('artist.job-orders.show', $jobOrder));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('design.initialImageUrl', null)
        ->where('design.canEdit', true)
    );
});

test('the workspace exposes a signed initialImageUrl when a design file exists', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_design']);
    DesignFile::factory()->for($jobOrder)->create();

    $response = $this->actingAs($artist)->get(route('artist.job-orders.show', $jobOrder));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('design.canEdit', true)
        ->has('design.initialImageUrl')
    );
});

test('canEdit is false when the design file is locked, but initialImageUrl still exposes the locked design for viewing', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'pending_review']);
    DesignFile::factory()->for($jobOrder)->locked()->create();

    $response = $this->actingAs($artist)->get(route('artist.job-orders.show', $jobOrder));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('design.canEdit', false)
        ->has('design.initialImageUrl')
    );
});

test('canEdit is false when the job order is still at assigned (not yet claimed)', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create();

    $response = $this->actingAs($artist)->get(route('artist.job-orders.show', $jobOrder));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('design.canEdit', false)
        ->where('design.initialImageUrl', null)
    );
});
