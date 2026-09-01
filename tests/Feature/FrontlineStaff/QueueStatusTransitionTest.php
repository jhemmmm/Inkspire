<?php

use App\Enums\QueueStatus;
use App\Models\QueueEntry;
use App\Models\User;

test('call next moves a waiting entry to serving', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();

    $response = $this->actingAs($staff)->patch(route('frontline-staff.queue-entries.call-next', $queueEntry));

    $response->assertRedirect();
    expect($queueEntry->fresh()->status)->toBe(QueueStatus::Serving);
});

test('call next on a serving or done entry is rejected with 422', function (string $state) {
    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->{$state}()->create();

    $response = $this->actingAs($staff)->patch(route('frontline-staff.queue-entries.call-next', $queueEntry));

    $response->assertStatus(422);
    expect($queueEntry->fresh()->status->value)->toBe($queueEntry->status->value);
})->with(['serving', 'done']);

test('mark done moves a serving entry to done', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->serving()->create();

    $response = $this->actingAs($staff)->patch(route('frontline-staff.queue-entries.mark-done', $queueEntry));

    $response->assertRedirect();
    expect($queueEntry->fresh()->status)->toBe(QueueStatus::Done);
});

test('mark done on a waiting entry is rejected with 422', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();

    $response = $this->actingAs($staff)->patch(route('frontline-staff.queue-entries.mark-done', $queueEntry));

    $response->assertStatus(422);
    expect($queueEntry->fresh()->status)->toBe(QueueStatus::Waiting);
});

test('a non frontline staff role is blocked from both transition routes', function () {
    $user = User::factory()->create(['role' => 'cashier']);
    $queueEntry = QueueEntry::factory()->create();

    $this->actingAs($user)
        ->patch(route('frontline-staff.queue-entries.call-next', $queueEntry))
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('frontline-staff.queue-entries.mark-done', $queueEntry))
        ->assertForbidden();
});
