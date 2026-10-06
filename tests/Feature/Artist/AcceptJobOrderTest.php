<?php

use App\Enums\ArtistStatus;
use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $attributes
 */
function unclaimedJobOrder(array $attributes = []): JobOrder
{
    return JobOrder::factory()->create(array_merge([
        'type' => 'type_b',
        'status' => JobOrderStatus::Intake->value,
        'assigned_artist_id' => null,
    ], $attributes));
}

test('the dashboard lists unclaimed job orders in the shared pool', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available->value]);
    unclaimedJobOrder(['description' => 'Sticker, A4']);

    $this->actingAs($artist)
        ->get(route('artist.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('artist/Dashboard')
            ->has('availableJobOrders', 1)
            ->where('availableJobOrders.0.description', 'Sticker, A4')
        );
});

test('the available pool keeps rush jobs first and sorts each group newest first', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available->value]);
    $olderRegular = unclaimedJobOrder(['is_rush' => false, 'created_at' => now()->subHours(4)]);
    $olderRush = unclaimedJobOrder(['is_rush' => true, 'created_at' => now()->subHours(3)]);
    $newerRegular = unclaimedJobOrder(['is_rush' => false, 'created_at' => now()->subHour()]);
    $newerRush = unclaimedJobOrder(['is_rush' => true, 'created_at' => now()]);

    $this->actingAs($artist)
        ->get(route('artist.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableJobOrders.0.id', $newerRush->id)
            ->where('availableJobOrders.1.id', $olderRush->id)
            ->where('availableJobOrders.2.id', $newerRegular->id)
            ->where('availableJobOrders.3.id', $olderRegular->id)
        );
});

test('an available artist can accept a job order out of the pool', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available->value]);
    $jobOrder = unclaimedJobOrder();

    $this->actingAs($artist)
        ->patch(route('artist.job-orders.accept', $jobOrder))
        ->assertRedirect();

    expect($jobOrder->fresh()->assigned_artist_id)->toBe($artist->id);
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Assigned);
});

test('an available artist can take over an unclaimed design without resetting its stage', function (JobOrderStatus $status) {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available->value]);
    $jobOrder = unclaimedJobOrder(['status' => $status]);

    $this->actingAs($artist)
        ->get(route('artist.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('availableJobOrders.0.status', $status->value));

    $this->patch(route('artist.job-orders.accept', $jobOrder))->assertRedirect();

    expect($jobOrder->fresh()->assigned_artist_id)->toBe($artist->id);
    expect($jobOrder->fresh()->status)->toBe($status);
})->with([JobOrderStatus::InDesign, JobOrderStatus::PendingReview]);

test('the second artist to accept the same job order is told they lost the race', function () {
    $first = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available->value]);
    $second = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available->value]);
    $jobOrder = unclaimedJobOrder();

    $this->actingAs($first)->patch(route('artist.job-orders.accept', $jobOrder));

    $response = $this->actingAs($second)->patch(route('artist.job-orders.accept', $jobOrder));

    $response->assertRedirect();
    $response->assertInertiaFlash('toast', [
        'type' => 'error',
        'message' => __('Another artist accepted that job order first.'),
    ]);

    expect($jobOrder->fresh()->assigned_artist_id)->toBe($first->id);
    expect($second->fresh()->last_assigned_at)->toBeNull();
});

test('an artist on break cannot accept a job order', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::OnBreak->value]);
    $jobOrder = unclaimedJobOrder();

    $this->actingAs($artist)
        ->patch(route('artist.job-orders.accept', $jobOrder))
        ->assertStatus(422);

    expect($jobOrder->fresh()->assigned_artist_id)->toBeNull();
});

test('an artist off shift cannot accept a job order', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::OffShift->value]);
    $jobOrder = unclaimedJobOrder();

    $this->actingAs($artist)
        ->patch(route('artist.job-orders.accept', $jobOrder))
        ->assertStatus(422);

    expect($jobOrder->fresh()->assigned_artist_id)->toBeNull();
});

test('a non-artist role cannot reach the accept endpoint', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = unclaimedJobOrder();

    $this->actingAs($cashier)
        ->patch(route('artist.job-orders.accept', $jobOrder))
        ->assertForbidden();

    expect($jobOrder->fresh()->assigned_artist_id)->toBeNull();
});

test('a guest cannot reach the accept endpoint', function () {
    $jobOrder = unclaimedJobOrder();

    $this->patch(route('artist.job-orders.accept', $jobOrder))
        ->assertRedirect(route('login'));
});
