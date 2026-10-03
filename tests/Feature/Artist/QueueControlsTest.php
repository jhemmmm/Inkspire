<?php

use App\Actions\JobOrder\ClaimJobOrderForArtist;
use App\Enums\ArtistStatus;
use App\Enums\JobOrderStatus;
use App\Models\DesignFile;
use App\Models\JobOrder;
use App\Models\RevisionLog;
use App\Models\User;

test('the dashboard route still returns 200 for an artist with no assigned job orders', function () {
    $artist = User::factory()->artist()->create();

    $response = $this->actingAs($artist)->get(route('artist.dashboard'));

    $response->assertOk();
});

test('a rush job order leads my queue, ahead of one accepted more recently', function () {
    $artist = User::factory()->artist()->create();
    $newerNormal = JobOrder::factory()->assignedTo($artist)->create(['accepted_at' => now()->subMinute()]);
    $rush = JobOrder::factory()->assignedTo($artist)->create(['accepted_at' => now()->subDays(2), 'is_rush' => true]);

    $response = $this->actingAs($artist)->get(route('artist.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('jobOrders.0.id', $rush->id)
        ->where('jobOrders.1.id', $newerNormal->id));
});

test('next is authorised for the rush job at the top, and refused for the one below it', function () {
    $artist = User::factory()->artist()->create();
    $newerNormal = JobOrder::factory()->assignedTo($artist)->create(['accepted_at' => now()->subMinute()]);
    $rush = JobOrder::factory()->assignedTo($artist)->create(['accepted_at' => now()->subDays(2), 'is_rush' => true]);

    // The whole point of keeping the rush key inside QUEUE_ORDER: the row the
    // Artist sees on top must be the row the server lets them call next.
    $this->actingAs($artist)->patch(route('artist.job-orders.next', $newerNormal))->assertStatus(422);
    $this->actingAs($artist)->patch(route('artist.job-orders.next', $rush))
        ->assertRedirect(route('artist.job-orders.show', $rush));

    expect($rush->fresh()->status)->toBe(JobOrderStatus::InConsultation);
});

test('next claims the most recently accepted job order — the one at the top of the queue', function () {
    $artist = User::factory()->artist()->create();
    JobOrder::factory()->assignedTo($artist)->create(['accepted_at' => now()->subDays(3)]);
    JobOrder::factory()->assignedTo($artist)->create(['accepted_at' => now()->subDays(2)]);
    $newest = JobOrder::factory()->assignedTo($artist)->create(['accepted_at' => now()->subDay()]);

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.next', $newest));

    $response->assertRedirect(route('artist.job-orders.show', $newest));
    expect($newest->fresh()->status)->toBe(JobOrderStatus::InConsultation);
});

test('next on a job order that is not at the top of the queue returns a 422 and leaves it unchanged', function () {
    $artist = User::factory()->artist()->create();
    $oldest = JobOrder::factory()->assignedTo($artist)->create(['accepted_at' => now()->subDays(3)]);
    JobOrder::factory()->assignedTo($artist)->create(['accepted_at' => now()->subDays(2)]);
    JobOrder::factory()->assignedTo($artist)->create(['accepted_at' => now()->subDay()]);

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.next', $oldest));

    $response->assertStatus(422);
    expect($oldest->fresh()->status)->toBe(JobOrderStatus::Assigned);
});

test('forwarding a job order returns it to the shared pool for any artist to accept', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['type' => 'type_b']);
    $jobOrder->forceFill(['status' => JobOrderStatus::InConsultation])->save();

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.forward', $jobOrder));

    $response->assertRedirect();
    $jobOrder->refresh();
    expect($jobOrder->assigned_artist_id)->toBeNull();
    expect($jobOrder->accepted_at)->toBeNull();
    expect($jobOrder->status)->toBe(JobOrderStatus::Intake);
    expect(ClaimJobOrderForArtist::pool()->pluck('id')->all())->toContain($jobOrder->id);
});

test('a forwarded job order keeps its consultation notes for whoever picks it up next', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['type' => 'type_b']);
    $jobOrder->forceFill([
        'status' => JobOrderStatus::InConsultation,
        'consultation_notes' => 'Customer wants a matte finish.',
    ])->save();

    $this->actingAs($artist)->patch(route('artist.job-orders.forward', $jobOrder));

    expect($jobOrder->fresh()->consultation_notes)->toBe('Customer wants a matte finish.');
});

test('an artist can forward a job order they accepted by mistake, before starting it', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['type' => 'type_b']);

    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Assigned);

    $this->actingAs($artist)->patch(route('artist.job-orders.forward', $jobOrder))->assertRedirect();

    expect($jobOrder->fresh()->assigned_artist_id)->toBeNull();
});

test('a second artist can accept a job order the first artist forwarded', function () {
    $first = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available->value]);
    $second = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available->value]);
    $jobOrder = JobOrder::factory()->assignedTo($first)->create(['type' => 'type_b']);
    $jobOrder->forceFill(['status' => JobOrderStatus::InConsultation])->save();

    $this->actingAs($first)->patch(route('artist.job-orders.forward', $jobOrder));
    $this->actingAs($second)->patch(route('artist.job-orders.accept', $jobOrder));

    expect($jobOrder->fresh()->assigned_artist_id)->toBe($second->id);
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Assigned);
});

test('forward returns a 422 for a job order whose status is ReadyForProduction', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'ready_for_production']);

    $this->actingAs($artist)->patch(route('artist.job-orders.forward', $jobOrder))->assertStatus(422);
});

test('an artist cannot act on next or forward for a job order assigned to a different artist', function () {
    $artist = User::factory()->artist()->create();
    $otherArtist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($otherArtist)->create();

    $this->actingAs($artist)->patch(route('artist.job-orders.next', $jobOrder))->assertForbidden();
    $this->actingAs($artist)->patch(route('artist.job-orders.forward', $jobOrder))->assertForbidden();
});

test('the artist dashboard queue never lists a job order that has already advanced into production', function (JobOrderStatus $status) {
    $artist = User::factory()->artist()->create();
    JobOrder::factory()->assignedTo($artist)->create(['status' => $status->value]);

    $response = $this->actingAs($artist)->get(route('artist.dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->component('artist/Dashboard')
        ->has('jobOrders', 0));
})->with([
    JobOrderStatus::ForProduction,
    JobOrderStatus::Printing,
    JobOrderStatus::ReadyForPickup,
]);

test('a job order that reached ForProduction through the real approve() flow no longer appears in the artist dashboard queue', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'pending_review']);
    DesignFile::factory()->for($jobOrder)->create();
    RevisionLog::factory()->for($jobOrder)->create();

    $approveResponse = $this->actingAs($artist)->patch(route('artist.job-orders.design.approve', $jobOrder));
    $approveResponse->assertRedirect();
    // Sanity check that 06-04's automatic-advance wiring is actually active —
    // if this assertion fails, the rest of this test is meaningless.
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::ForProduction);

    $response = $this->actingAs($artist)->get(route('artist.dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->component('artist/Dashboard')
        ->has('jobOrders', 0));
});

test('a non-artist role is forbidden from every artist job-orders route', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->assigned()->create();

    $this->actingAs($staff)->get(route('artist.job-orders.show', $jobOrder))->assertForbidden();
    $this->actingAs($staff)->patch(route('artist.job-orders.consultation.update', $jobOrder))->assertForbidden();
    $this->actingAs($staff)->patch(route('artist.job-orders.next', $jobOrder))->assertForbidden();
    $this->actingAs($staff)->patch(route('artist.job-orders.forward', $jobOrder))->assertForbidden();
});
