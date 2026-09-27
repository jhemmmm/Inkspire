<?php

use App\Enums\UserRole;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\User;

test('a newly created artist is given the next free label', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->artist()->create(['artist_label' => 'Artist 1']);
    User::factory()->artist()->create(['artist_label' => 'Artist 2']);

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'New Artist',
        'email' => 'new-artist@inkspire.test',
        'role' => UserRole::Artist->value,
        'password' => 'Str0ng-Password!23',
        'password_confirmation' => 'Str0ng-Password!23',
    ])->assertRedirect();

    expect(User::where('email', 'new-artist@inkspire.test')->value('artist_label'))->toBe('Artist 3');
});

test('a freed number is reused rather than skipped', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->artist()->create(['artist_label' => 'Artist 1']);
    User::factory()->artist()->create(['artist_label' => 'Artist 3']);

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Replacement',
        'email' => 'replacement@inkspire.test',
        'role' => UserRole::Artist->value,
        'password' => 'Str0ng-Password!23',
        'password_confirmation' => 'Str0ng-Password!23',
    ])->assertRedirect();

    expect(User::where('email', 'replacement@inkspire.test')->value('artist_label'))->toBe('Artist 2');
});

test('non-artist roles are never given a label', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'New Cashier',
        'email' => 'new-cashier@inkspire.test',
        'role' => UserRole::Cashier->value,
        'password' => 'Str0ng-Password!23',
        'password_confirmation' => 'Str0ng-Password!23',
    ])->assertRedirect();

    expect(User::where('email', 'new-cashier@inkspire.test')->value('artist_label'))->toBeNull();
});

test('the admin user list shows each artist their label', function () {
    $admin = User::factory()->admin()->create(['name' => 'AAA Admin']);
    User::factory()->artist()->create(['name' => 'ZZZ Artist', 'artist_label' => 'Artist 4']);

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

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

test('queue numbers are padded to three digits behind their lane prefix', function () {
    $entry = QueueEntry::factory()->create(['queue_number' => 1]);
    $hundreds = QueueEntry::factory()->create(['queue_number' => 142]);
    $thousands = QueueEntry::factory()->create(['queue_number' => 1042]);
    $rush = QueueEntry::factory()->create(['queue_prefix' => 'R', 'queue_number' => 7]);

    expect($entry->paddedNumber())->toBe('A-001')
        ->and($hundreds->paddedNumber())->toBe('A-142')
        ->and($thousands->paddedNumber())->toBe('A-1042')
        ->and($rush->paddedNumber())->toBe('R-007');
});
