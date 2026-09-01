<?php

use App\Enums\JobOrderStatus;
use App\Enums\UserRole;
use App\Models\JobOrder;
use App\Models\User;

test('validationFailed factory state sets ValidationFailed status and a non-empty failure reason', function () {
    $jobOrder = JobOrder::factory()->validationFailed()->create();

    expect($jobOrder->status)->toBe(JobOrderStatus::ValidationFailed);
    expect($jobOrder->validation_failure_reason)->toBeString()->not->toBeEmpty();
});

test('readyForProduction factory state sets ReadyForProduction status', function () {
    $jobOrder = JobOrder::factory()->readyForProduction()->create();

    expect($jobOrder->status)->toBe(JobOrderStatus::ReadyForProduction);
});

test('assigned factory state creates an artist and links assigned_artist_id', function () {
    $jobOrder = JobOrder::factory()->assigned()->create();

    expect($jobOrder->status)->toBe(JobOrderStatus::Assigned);
    expect($jobOrder->assigned_artist_id)->not->toBeNull();
    expect($jobOrder->assignedArtist->role)->toBe(UserRole::Artist);
});

test('unavailable UserFactory state sets is_available to false', function () {
    $user = User::factory()->unavailable()->create();

    expect($user->is_available)->toBeFalse();
});
