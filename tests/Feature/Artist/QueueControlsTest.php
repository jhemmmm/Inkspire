<?php

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

test('next claims the oldest eligible assigned job order and moves it to in_consultation', function () {
    $artist = User::factory()->artist()->create();
    $oldest = JobOrder::factory()->assignedTo($artist)->create(['created_at' => now()->subDays(3)]);
    JobOrder::factory()->assignedTo($artist)->create(['created_at' => now()->subDays(2)]);
    JobOrder::factory()->assignedTo($artist)->create(['created_at' => now()->subDay()]);

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.next', $oldest));

    $response->assertRedirect(route('artist.job-orders.show', $oldest));
    expect($oldest->fresh()->status)->toBe(JobOrderStatus::InConsultation);
});

test('next on a non-oldest eligible job order returns a 422 and leaves it unchanged', function () {
    $artist = User::factory()->artist()->create();
    JobOrder::factory()->assignedTo($artist)->create(['created_at' => now()->subDays(3)]);
    JobOrder::factory()->assignedTo($artist)->create(['created_at' => now()->subDays(2)]);
    $newest = JobOrder::factory()->assignedTo($artist)->create(['created_at' => now()->subDay()]);

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.next', $newest));

    $response->assertStatus(422);
    expect($newest->fresh()->status)->toBe(JobOrderStatus::Assigned);
});

test('next on a not_appeared job order succeeds regardless of ordering, per the D-04 manual-resume path', function () {
    $artist = User::factory()->artist()->create();
    $older = JobOrder::factory()->assignedTo($artist)->create(['created_at' => now()->subDays(5)]);
    $notAppeared = JobOrder::factory()->assignedTo($artist)->create(['created_at' => now()->subDays(4)]);
    $notAppeared->forceFill(['not_appeared' => true, 'queue_deprioritized_at' => now()->subDays(3)])->save();

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.next', $notAppeared));

    $response->assertRedirect(route('artist.job-orders.show', $notAppeared));
    expect($notAppeared->fresh()->status)->toBe(JobOrderStatus::InConsultation);
    expect($notAppeared->fresh()->not_appeared)->toBeFalse();
    expect($older->fresh()->status)->toBe(JobOrderStatus::Assigned);
});

test('forward moves an in_consultation job order back to assigned with a fresh queue_deprioritized_at, without touching assigned_artist_id', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_consultation']);

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.forward', $jobOrder));

    $response->assertRedirect();
    $jobOrder->refresh();
    expect($jobOrder->status)->toBe(JobOrderStatus::Assigned);
    expect($jobOrder->assigned_artist_id)->toBe($artist->id);
    expect($jobOrder->queue_deprioritized_at)->not->toBeNull();
});

test('forward on an already-assigned (not-yet-claimed) job order returns a 422', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create();

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.forward', $jobOrder));

    $response->assertStatus(422);
});

test('forward and not-appear both return a 422 for a job order whose status is ReadyForProduction', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'ready_for_production']);

    $this->actingAs($artist)->patch(route('artist.job-orders.forward', $jobOrder))->assertStatus(422);
    $this->actingAs($artist)->patch(route('artist.job-orders.not-appear', $jobOrder))->assertStatus(422);
});

test('not_appear moves an in_consultation job order to assigned with not_appeared true', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_consultation']);

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.not-appear', $jobOrder));

    $response->assertRedirect();
    $jobOrder->refresh();
    expect($jobOrder->status)->toBe(JobOrderStatus::Assigned);
    expect($jobOrder->not_appeared)->toBeTrue();
    expect($jobOrder->queue_deprioritized_at)->not->toBeNull();
});

test('an artist cannot act on next, forward, or not-appear for a job order assigned to a different artist', function () {
    $artist = User::factory()->artist()->create();
    $otherArtist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($otherArtist)->create();

    $this->actingAs($artist)->patch(route('artist.job-orders.next', $jobOrder))->assertForbidden();
    $this->actingAs($artist)->patch(route('artist.job-orders.forward', $jobOrder))->assertForbidden();
    $this->actingAs($artist)->patch(route('artist.job-orders.not-appear', $jobOrder))->assertForbidden();
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
    JobOrderStatus::QualityCheck,
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
    $this->actingAs($staff)->patch(route('artist.job-orders.not-appear', $jobOrder))->assertForbidden();
});
