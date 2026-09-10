<?php

use App\Actions\JobOrder\ClaimJobOrderForArtist;
use App\Enums\ArtistStatus;
use App\Enums\JobOrderStatus;
use App\Enums\QueueStatus;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\User;

test('an artist on break cannot call next on their own queue', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::OnBreak]);
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create();

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.next', $jobOrder));

    $response->assertStatus(422);
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Assigned);
});

test('an artist on break cannot forward a job order back to the pool', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::OnBreak]);
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create();

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.forward', $jobOrder));

    $response->assertStatus(422);
    expect($jobOrder->fresh()->assigned_artist_id)->toBe($artist->id);
});

test('an artist off shift cannot work their queue either', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::OffShift]);
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create();

    $this->actingAs($artist)->patch(route('artist.job-orders.next', $jobOrder))->assertStatus(422);
    $this->actingAs($artist)->patch(route('artist.job-orders.forward', $jobOrder))->assertStatus(422);
});

test('ending the break restores the ability to work the queue', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::OnBreak]);
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create();

    $this->actingAs($artist)->patch(route('artist.session-status.end-break'))->assertRedirect();

    $this->actingAs($artist->fresh())
        ->patch(route('artist.job-orders.next', $jobOrder))
        ->assertRedirect(route('artist.job-orders.show', $jobOrder));
});

test('an off-shift artist can start their shift again and become available', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::OffShift]);

    $response = $this->actingAs($artist)->patch(route('artist.session-status.start-shift'));

    $response->assertRedirect();
    expect($artist->fresh()->artist_status)->toBe(ArtistStatus::Available)
        ->and($artist->fresh()->is_available)->toBeTrue();
});

test('starting a shift is refused when the artist is already on shift', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available]);

    $this->actingAs($artist)->patch(route('artist.session-status.start-shift'))->assertStatus(422);
});

test('accepting a job order calls that customer to the counter', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available]);
    $queueEntry = QueueEntry::factory()->create(['status' => QueueStatus::Waiting]);
    $jobOrder = JobOrder::factory()->for($queueEntry)->create([
        'type' => 'type_b',
        'status' => JobOrderStatus::Intake,
        'assigned_artist_id' => null,
    ]);

    (new ClaimJobOrderForArtist)($jobOrder, $artist);

    expect($queueEntry->fresh()->status)->toBe(QueueStatus::Serving);
});

test('a visit whose remaining job orders are all settled closes on accept', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available]);
    $queueEntry = QueueEntry::factory()->create(['status' => QueueStatus::Waiting]);
    $jobOrder = JobOrder::factory()->for($queueEntry)->create([
        'type' => 'type_b',
        'status' => JobOrderStatus::Intake,
        'assigned_artist_id' => null,
    ]);
    JobOrder::factory()->for($queueEntry)->create(['status' => JobOrderStatus::ForProduction]);

    (new ClaimJobOrderForArtist)($jobOrder, $artist);

    // The accepted job order is the only one still open, and it now has an
    // artist, so the customer has somewhere to go.
    expect($queueEntry->fresh()->status)->toBe(QueueStatus::Serving);
});

test('a lost claim race leaves the queue entry untouched', function () {
    $winner = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available]);
    $loser = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available]);
    $queueEntry = QueueEntry::factory()->create(['status' => QueueStatus::Waiting]);
    $jobOrder = JobOrder::factory()->for($queueEntry)->create([
        'type' => 'type_b',
        'status' => JobOrderStatus::Intake,
        'assigned_artist_id' => $winner->id,
    ]);

    expect((new ClaimJobOrderForArtist)($jobOrder, $loser))->toBeFalse()
        ->and($queueEntry->fresh()->status)->toBe(QueueStatus::Waiting);
});
