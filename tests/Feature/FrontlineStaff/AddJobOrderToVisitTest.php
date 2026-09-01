<?php

use App\Enums\QueueStatus;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a job order can be added to a waiting entry', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Business Cards, 100pcs',
        'type' => 'type_b',
    ]);

    $response->assertRedirect();
    expect($queueEntry->jobOrders()->count())->toBe(1);
});

test('a job order can also be added to a done entry, per D-15/D-18', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->done()->create();
    JobOrder::factory()->for($queueEntry)->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Sticker, A4',
        'type' => 'type_b',
    ]);

    $response->assertRedirect();
    expect($queueEntry->jobOrders()->count())->toBe(2);
    expect($queueEntry->fresh()->status)->toBe(QueueStatus::Done);
});

test('a type a row with no file fails validation', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Tarpaulin, 3x5ft',
        'type' => 'type_a',
    ]);

    $response->assertSessionHasErrors('file');
    expect($queueEntry->jobOrders()->count())->toBe(0);
});

test('a type a row with a file succeeds and stores it', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Tarpaulin, 3x5ft',
        'type' => 'type_a',
        'file' => UploadedFile::fake()->create('design.pdf', 500),
    ]);

    $response->assertRedirect();
    $jobOrder = $queueEntry->jobOrders()->firstOrFail();
    expect($jobOrder->file_path)->not->toBeNull();
    Storage::disk('local')->assertExists($jobOrder->file_path);
});

test('a non frontline staff role is blocked from adding a job order', function () {
    $user = User::factory()->create(['role' => 'cashier']);
    $queueEntry = QueueEntry::factory()->create();

    $response = $this->actingAs($user)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Sticker, A4',
        'type' => 'type_b',
    ]);

    $response->assertForbidden();
    expect($queueEntry->jobOrders()->count())->toBe(0);
});
