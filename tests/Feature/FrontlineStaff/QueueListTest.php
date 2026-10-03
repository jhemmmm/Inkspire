<?php

use App\Models\QueueEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the internal queue list shows today\'s entries', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create([
        'queue_date' => QueueEntry::currentBusinessDate(),
        'queue_number' => 1,
    ]);

    $response = $this->actingAs($staff)->get(route('frontline-staff.queue-entries.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('frontline-staff/QueueList')
        ->has('queueEntries', 1)
        ->where('queueEntries.0.id', $queueEntry->id));
});

test('a non frontline staff role cannot read the queue list', function () {
    $user = User::factory()->create(['role' => 'cashier']);

    $this->actingAs($user)
        ->get(route('frontline-staff.queue-entries.index'))
        ->assertForbidden();
});

test('an online lane entry is excluded from the staff queue list', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $regular = QueueEntry::factory()->create(['queue_number' => 1]);
    QueueEntry::factory()->create([
        'queue_prefix' => QueueEntry::ONLINE_PREFIX,
        'queue_number' => 1,
    ]);

    $this->actingAs($staff)->get(route('frontline-staff.queue-entries.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('queueEntries', 1)
            ->where('queueEntries.0.id', $regular->id));
});
