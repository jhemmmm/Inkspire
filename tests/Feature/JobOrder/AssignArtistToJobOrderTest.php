<?php

use App\Actions\JobOrder\AssignArtistToJobOrder;
use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\User;

test('assigns the artist with the oldest or null last_assigned_at among available artists', function () {
    $old = User::factory()->artist()->create(['is_available' => true, 'last_assigned_at' => now()->subDays(3)]);
    $null = User::factory()->artist()->create(['is_available' => true, 'last_assigned_at' => null]);
    User::factory()->artist()->create(['is_available' => true, 'last_assigned_at' => now()->subHour()]);

    $jobOrder = JobOrder::factory()->create(['type' => 'type_b', 'status' => JobOrderStatus::Intake->value]);

    $assigned = (new AssignArtistToJobOrder)($jobOrder);

    expect($assigned->id)->toBe($null->id);
    expect($jobOrder->fresh()->assigned_artist_id)->toBe($null->id);
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Assigned);
    expect($old->fresh()->last_assigned_at)->not->toBeNull();
});

test('does not assign an unavailable artist', function () {
    User::factory()->artist()->unavailable()->create();
    $available = User::factory()->artist()->create(['is_available' => true]);

    $jobOrder = JobOrder::factory()->create(['type' => 'type_b', 'status' => JobOrderStatus::Intake->value]);

    $assigned = (new AssignArtistToJobOrder)($jobOrder);

    expect($assigned->id)->toBe($available->id);
});

test('leaves the job order unassigned when no artist is available', function () {
    $jobOrder = JobOrder::factory()->create(['type' => 'type_b', 'status' => JobOrderStatus::Intake->value]);

    $assigned = (new AssignArtistToJobOrder)($jobOrder);

    expect($assigned)->toBeNull();
    expect($jobOrder->fresh()->assigned_artist_id)->toBeNull();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Intake);
});

test('claimOldestUnassigned assigns the oldest unassigned type b job order to the given artist', function () {
    $older = JobOrder::factory()->create([
        'type' => 'type_b',
        'status' => JobOrderStatus::Intake->value,
        'created_at' => now()->subDays(2),
    ]);
    $newer = JobOrder::factory()->create([
        'type' => 'type_b',
        'status' => JobOrderStatus::Intake->value,
        'created_at' => now()->subHour(),
    ]);
    $artist = User::factory()->artist()->create();

    $claimed = (new AssignArtistToJobOrder)->claimOldestUnassigned($artist);

    expect($claimed->id)->toBe($older->id);
    expect($older->fresh()->assigned_artist_id)->toBe($artist->id);
    expect($older->fresh()->status)->toBe(JobOrderStatus::Assigned);
    expect($newer->fresh()->assigned_artist_id)->toBeNull();
});

test('claimOldestUnassigned returns null when there is no unassigned type b job order', function () {
    $artist = User::factory()->artist()->create();

    $claimed = (new AssignArtistToJobOrder)->claimOldestUnassigned($artist);

    expect($claimed)->toBeNull();
});

test('returns null immediately for a type a job order, per the D-01/D-08 scope guard', function () {
    $jobOrder = JobOrder::factory()->typeA()->create(['status' => JobOrderStatus::Intake->value]);

    $assigned = (new AssignArtistToJobOrder)($jobOrder);

    expect($assigned)->toBeNull();
    expect($jobOrder->fresh()->assigned_artist_id)->toBeNull();
});
