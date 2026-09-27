<?php

use App\Actions\JobOrder\ClaimJobOrderForArtist;
use App\Enums\ArtistStatus;
use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\User;

function availableArtist(): User
{
    return User::factory()->artist()->create(['artist_status' => ArtistStatus::Available->value]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function poolJobOrder(array $attributes = []): JobOrder
{
    return JobOrder::factory()->create(array_merge([
        'type' => 'type_b',
        'status' => JobOrderStatus::Intake->value,
        'assigned_artist_id' => null,
    ], $attributes));
}

test('an available artist claims an unassigned type b job order', function () {
    $artist = availableArtist();
    $jobOrder = poolJobOrder();

    $claimed = (new ClaimJobOrderForArtist)($jobOrder, $artist);

    expect($claimed)->toBeTrue();
    expect($jobOrder->fresh()->assigned_artist_id)->toBe($artist->id);
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Assigned);
    expect($artist->fresh()->last_assigned_at)->not->toBeNull();
});

test('only the first of two artists racing for the same job order wins', function () {
    $first = availableArtist();
    $second = availableArtist();
    $jobOrder = poolJobOrder();

    // Both artists loaded the pool page before either pressed Accept, so
    // both hold a model instance that still reads assigned_artist_id=null.
    // This is the exact state the guard has to survive: if the check lived
    // in PHP against these stale reads, both would pass it.
    $firstView = JobOrder::findOrFail($jobOrder->id);
    $secondView = JobOrder::findOrFail($jobOrder->id);

    expect($firstView->assigned_artist_id)->toBeNull();
    expect($secondView->assigned_artist_id)->toBeNull();

    $firstResult = (new ClaimJobOrderForArtist)($firstView, $first);
    $secondResult = (new ClaimJobOrderForArtist)($secondView, $second);

    expect($firstResult)->toBeTrue();
    expect($secondResult)->toBeFalse();
    expect($jobOrder->fresh()->assigned_artist_id)->toBe($first->id);
    expect($second->fresh()->last_assigned_at)->toBeNull();
});

test('an artist who is not available cannot claim', function () {
    $onBreak = User::factory()->artist()->create(['artist_status' => ArtistStatus::OnBreak->value]);
    $offShift = User::factory()->artist()->create(['artist_status' => ArtistStatus::OffShift->value]);
    $jobOrder = poolJobOrder();

    expect((new ClaimJobOrderForArtist)($jobOrder, $onBreak))->toBeFalse();
    expect((new ClaimJobOrderForArtist)($jobOrder, $offShift))->toBeFalse();
    expect($jobOrder->fresh()->assigned_artist_id)->toBeNull();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Intake);
});

test('a type a job order the scanner sent for artist work is claimable', function () {
    $jobOrder = JobOrder::factory()->typeA()->create([
        'status' => JobOrderStatus::Intake->value,
        'validation_failure_reason' => 'Below the 100 DPI minimum at this print size.',
    ]);

    expect((new ClaimJobOrderForArtist)($jobOrder, availableArtist()))->toBeTrue();
    expect($jobOrder->fresh()->assigned_artist_id)->not->toBeNull();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Assigned);
});

test('a type a job order the scanner rejected outright is never claimable', function () {
    $jobOrder = JobOrder::factory()->typeA()->create([
        'status' => JobOrderStatus::ValidationFailed->value,
    ]);

    expect((new ClaimJobOrderForArtist)($jobOrder, availableArtist()))->toBeFalse();
    expect($jobOrder->fresh()->assigned_artist_id)->toBeNull();
});

test('a cancelled job order is never claimable', function () {
    $jobOrder = poolJobOrder(['cancelled_at' => now()]);

    expect((new ClaimJobOrderForArtist)($jobOrder, availableArtist()))->toBeFalse();
    expect($jobOrder->fresh()->assigned_artist_id)->toBeNull();
});

test('rush job orders lead the pool, and newest leads within each group', function () {
    $oldestNormal = poolJobOrder(['created_at' => now()->subDays(5)]);
    $newerRush = poolJobOrder(['created_at' => now()->subHour(), 'is_rush' => true]);
    $olderRush = poolJobOrder(['created_at' => now()->subDays(2), 'is_rush' => true]);
    $newestNormal = poolJobOrder(['created_at' => now()->subMinute()]);

    expect(ClaimJobOrderForArtist::pool()->pluck('id')->all())
        ->toBe([$newerRush->id, $olderRush->id, $newestNormal->id, $oldestNormal->id]);
});

test('the pool lists every unclaimed job order waiting on an artist, newest first', function () {
    $older = poolJobOrder(['created_at' => now()->subDays(2)]);
    $newer = poolJobOrder(['created_at' => now()->subHour()]);
    $taken = poolJobOrder(['created_at' => now()->subDays(3)]);

    // The scanner's NeedsArtist verdict parks a Type A here too.
    $needsArtist = JobOrder::factory()->typeA()->create([
        'status' => JobOrderStatus::Intake->value,
        'created_at' => now()->subDays(4),
        'validation_failure_reason' => 'Below the 100 DPI minimum at this print size.',
    ]);

    poolJobOrder(['cancelled_at' => now()]);

    (new ClaimJobOrderForArtist)($taken, availableArtist());

    expect(ClaimJobOrderForArtist::pool()->pluck('id')->all())
        ->toBe([$newer->id, $older->id, $needsArtist->id]);
});
