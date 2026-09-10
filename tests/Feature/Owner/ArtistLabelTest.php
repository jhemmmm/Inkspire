<?php

use App\Enums\UserRole;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\User;

test('a newly created artist is given the next free label', function () {
    $owner = User::factory()->owner()->create();
    User::factory()->artist()->create(['artist_label' => 'Artist 1']);
    User::factory()->artist()->create(['artist_label' => 'Artist 2']);

    $this->actingAs($owner)->post(route('owner.users.store'), [
        'name' => 'New Artist',
        'email' => 'new-artist@inkspire.test',
        'role' => UserRole::Artist->value,
        'password' => 'Str0ng-Password!23',
        'password_confirmation' => 'Str0ng-Password!23',
    ])->assertRedirect();

    expect(User::where('email', 'new-artist@inkspire.test')->value('artist_label'))->toBe('Artist 3');
});

test('a freed number is reused rather than skipped', function () {
    $owner = User::factory()->owner()->create();
    User::factory()->artist()->create(['artist_label' => 'Artist 1']);
    User::factory()->artist()->create(['artist_label' => 'Artist 3']);

    $this->actingAs($owner)->post(route('owner.users.store'), [
        'name' => 'Replacement',
        'email' => 'replacement@inkspire.test',
        'role' => UserRole::Artist->value,
        'password' => 'Str0ng-Password!23',
        'password_confirmation' => 'Str0ng-Password!23',
    ])->assertRedirect();

    expect(User::where('email', 'replacement@inkspire.test')->value('artist_label'))->toBe('Artist 2');
});

test('non-artist roles are never given a label', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('owner.users.store'), [
        'name' => 'New Cashier',
        'email' => 'new-cashier@inkspire.test',
        'role' => UserRole::Cashier->value,
        'password' => 'Str0ng-Password!23',
        'password_confirmation' => 'Str0ng-Password!23',
    ])->assertRedirect();

    expect(User::where('email', 'new-cashier@inkspire.test')->value('artist_label'))->toBeNull();
});

test('the owner user list shows each artist their label', function () {
    $owner = User::factory()->owner()->create(['name' => 'AAA Owner']);
    User::factory()->artist()->create(['name' => 'ZZZ Artist', 'artist_label' => 'Artist 4']);

    $response = $this->actingAs($owner)->get(route('owner.users.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('users.0.artist_label', null)
        ->where('users.1.artist_label', 'Artist 4'));
});

test('the frontline queue names the artist a customer is sent to', function () {
    $frontline = User::factory()->frontlineStaff()->create();
    $artist = User::factory()->artist()->create(['artist_label' => 'Artist 2']);
    $queueEntry = QueueEntry::factory()->create();
    JobOrder::factory()->for($queueEntry)->assignedTo($artist)->create();

    $response = $this->actingAs($frontline)->get(route('frontline-staff.queue-entries.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('queueEntries.0.job_orders.0.assigned_artist.artist_label', 'Artist 2'));
});

test('the artist dashboard tells the artist their own label', function () {
    $artist = User::factory()->artist()->create(['artist_label' => 'Artist 5']);

    $response = $this->actingAs($artist)->get(route('artist.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('artistLabel', 'Artist 5'));
});

test('queue numbers are padded to three digits', function () {
    $entry = QueueEntry::factory()->create(['queue_number' => 1]);
    $hundreds = QueueEntry::factory()->create(['queue_number' => 142]);
    $thousands = QueueEntry::factory()->create(['queue_number' => 1042]);

    expect($entry->paddedNumber())->toBe('001')
        ->and($hundreds->paddedNumber())->toBe('142')
        ->and($thousands->paddedNumber())->toBe('1042');
});
