<?php

use App\Enums\ArtistStatus;
use App\Models\JobOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('starting a break flips artist_status to on_break, is_available to false, and stamps break_started_at', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available->value, 'is_available' => true]);

    $response = $this->actingAs($artist)->patch(route('artist.session-status.start-break'));

    $response->assertRedirect();
    $artist->refresh();
    expect($artist->artist_status)->toBe(ArtistStatus::OnBreak);
    expect($artist->is_available)->toBeFalse();
    expect($artist->break_started_at)->not->toBeNull();
});

test('ending a break flips artist_status back to available, clears break_started_at, and sets is_available true', function () {
    $artist = User::factory()->artist()->create([
        'artist_status' => ArtistStatus::OnBreak->value,
        'is_available' => false,
        'break_started_at' => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($artist)->patch(route('artist.session-status.end-break'));

    $response->assertRedirect();
    $artist->refresh();
    expect($artist->artist_status)->toBe(ArtistStatus::Available);
    expect($artist->is_available)->toBeTrue();
    expect($artist->break_started_at)->toBeNull();
});

test('returning to available claims the artist\'s oldest unassigned type b job order', function () {
    $artist = User::factory()->artist()->create([
        'artist_status' => ArtistStatus::OnBreak->value,
        'is_available' => false,
        'break_started_at' => now()->subMinutes(5),
    ]);
    $jobOrder = JobOrder::factory()->create(['type' => 'type_b', 'status' => 'intake']);

    $this->actingAs($artist)->patch(route('artist.session-status.end-break'));

    expect($jobOrder->fresh()->assigned_artist_id)->toBe($artist->id);
});

test('ending shift is allowed from available and from on_break, setting off_shift and is_available false', function () {
    $availableArtist = User::factory()->artist()->create(['artist_status' => ArtistStatus::Available->value, 'is_available' => true]);

    $response = $this->actingAs($availableArtist)->patch(route('artist.session-status.end-shift'));

    $response->assertRedirect();
    $availableArtist->refresh();
    expect($availableArtist->artist_status)->toBe(ArtistStatus::OffShift);
    expect($availableArtist->is_available)->toBeFalse();

    $onBreakArtist = User::factory()->artist()->create([
        'artist_status' => ArtistStatus::OnBreak->value,
        'is_available' => false,
        'break_started_at' => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($onBreakArtist)->patch(route('artist.session-status.end-shift'));

    $response->assertRedirect();
    $onBreakArtist->refresh();
    expect($onBreakArtist->artist_status)->toBe(ArtistStatus::OffShift);
    expect($onBreakArtist->is_available)->toBeFalse();
});

test('starting a break while already on break returns a 422', function () {
    $artist = User::factory()->artist()->create([
        'artist_status' => ArtistStatus::OnBreak->value,
        'is_available' => false,
        'break_started_at' => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($artist)->patch(route('artist.session-status.start-break'));

    $response->assertStatus(422);
});

test('ending shift when already off_shift returns a 422', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::OffShift->value, 'is_available' => false]);

    $response = $this->actingAs($artist)->patch(route('artist.session-status.end-shift'));

    $response->assertStatus(422);
});

test('the artist dashboard index exposes the current artistStatus', function () {
    $artist = User::factory()->artist()->create(['artist_status' => ArtistStatus::OnBreak->value]);

    $response = $this->actingAs($artist)->get(route('artist.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->where('artistStatus', ArtistStatus::OnBreak->value));
});
