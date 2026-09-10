<?php

use App\Actions\JobOrder\SyncQueueEntryStatus;
use App\Enums\JobOrderStatus;
use App\Enums\QueueStatus;
use App\Models\Customer;
use App\Models\DesignFile;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\RevisionLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

test('a visit closes when its last job order leaves the counter', function () {
    $queueEntry = QueueEntry::factory()->create(['status' => QueueStatus::Serving]);
    JobOrder::factory()->for($queueEntry)->create(['status' => JobOrderStatus::DesignApproved]);

    (new SyncQueueEntryStatus)($queueEntry);

    expect($queueEntry->fresh()->status)->toBe(QueueStatus::Done);
});

test('a visit stays open while any job order still needs an artist', function () {
    $artist = User::factory()->artist()->create();
    $queueEntry = QueueEntry::factory()->create(['status' => QueueStatus::Serving]);
    JobOrder::factory()->for($queueEntry)->create(['status' => JobOrderStatus::DesignApproved]);
    JobOrder::factory()->for($queueEntry)->assignedTo($artist)->create(['status' => JobOrderStatus::InDesign]);

    (new SyncQueueEntryStatus)($queueEntry);

    expect($queueEntry->fresh()->status)->toBe(QueueStatus::Serving);
});

test('a cancelled job order does not hold its visit open forever', function () {
    $queueEntry = QueueEntry::factory()->create(['status' => QueueStatus::Serving]);
    JobOrder::factory()->for($queueEntry)->create(['status' => JobOrderStatus::DesignApproved]);
    JobOrder::factory()->for($queueEntry)->create([
        'status' => JobOrderStatus::InDesign,
        'cancelled_at' => now(),
    ]);

    (new SyncQueueEntryStatus)($queueEntry);

    expect($queueEntry->fresh()->status)->toBe(QueueStatus::Done);
});

test('a print-ready type A visit closes at intake, without ever seeing an artist', function () {
    Storage::fake('local');
    $frontline = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($frontline)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [[
            'description' => 'Reprint, file supplied',
            'type' => 'type_a',
            // Large enough to clear the DPI check for an unspecified print
            // size, so the outcome is Passed rather than NeedsArtist.
            'file' => UploadedFile::fake()->image('artwork.jpg', 3000, 2000),
        ]],
    ])->assertRedirect();

    $queueEntry = QueueEntry::query()->latest('id')->first();

    // EnterProduction runs in the same breath as a Passed validation, so the
    // job order is already ForProduction here -- either way it has left the
    // counter, which is what closes the visit.
    expect($queueEntry->jobOrders()->first()->status)->not->toBe(JobOrderStatus::Intake)
        ->and($queueEntry->fresh()->status)->toBe(QueueStatus::Done);
});

test('a type B visit stays open at intake — it still needs an artist', function () {
    $frontline = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($frontline)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [[
            'description' => 'Tarpaulin consultation',
            'type' => 'type_b',
        ]],
    ])->assertRedirect();

    expect(QueueEntry::query()->latest('id')->first()->status)->toBe(QueueStatus::Waiting);
});

test('the artist recording an approval in person closes the visit', function () {
    $artist = User::factory()->artist()->create();
    $queueEntry = QueueEntry::factory()->create(['status' => QueueStatus::Serving]);
    $jobOrder = JobOrder::factory()->for($queueEntry)->assignedTo($artist)->create([
        'status' => JobOrderStatus::PendingReview,
    ]);
    DesignFile::factory()->for($jobOrder)->create(['locked_at' => null]);
    RevisionLog::factory()->for($jobOrder)->create(['outcome' => null, 'reviewed_at' => null]);

    $this->actingAs($artist)
        ->patch(route('artist.job-orders.design.approve', $jobOrder))
        ->assertRedirect();

    expect($queueEntry->fresh()->status)->toBe(QueueStatus::Done);
});

test('a client approving remotely closes the visit the same way', function () {
    $artist = User::factory()->artist()->create();
    $queueEntry = QueueEntry::factory()->create(['status' => QueueStatus::Serving]);
    $jobOrder = JobOrder::factory()->for($queueEntry)->assignedTo($artist)->create([
        'status' => JobOrderStatus::PendingReview,
    ]);
    DesignFile::factory()->for($jobOrder)->create(['locked_at' => null]);
    $revisionLog = RevisionLog::factory()->for($jobOrder)->create(['outcome' => null, 'reviewed_at' => null]);

    $this->post(URL::temporarySignedRoute(
        'public.design-review.approve',
        now()->addDay(),
        ['revisionLog' => $revisionLog->id],
    ))->assertOk();

    expect($queueEntry->fresh()->status)->toBe(QueueStatus::Done);
});

test('a closed visit reopens when work on it resumes', function () {
    // The customer is back with an artist, so the queue has to say so -- a
    // row reading Done above a job order still in design is exactly the
    // confusion the queue exists to prevent.
    $artist = User::factory()->artist()->create();
    $queueEntry = QueueEntry::factory()->create(['status' => QueueStatus::Done]);
    JobOrder::factory()->for($queueEntry)->assignedTo($artist)->create(['status' => JobOrderStatus::InDesign]);

    (new SyncQueueEntryStatus)($queueEntry);

    expect($queueEntry->fresh()->status)->toBe(QueueStatus::Serving);
});

test('a visit with unclaimed work waits rather than serving', function () {
    $queueEntry = QueueEntry::factory()->create(['status' => QueueStatus::Done]);
    JobOrder::factory()->for($queueEntry)->create([
        'status' => JobOrderStatus::Intake,
        'assigned_artist_id' => null,
    ]);

    (new SyncQueueEntryStatus)($queueEntry);

    expect($queueEntry->fresh()->status)->toBe(QueueStatus::Waiting);
});

test('cancelling the last outstanding job order closes the visit', function () {
    $cashier = User::factory()->cashier()->create();
    $queueEntry = QueueEntry::factory()->create(['status' => QueueStatus::Waiting]);
    $jobOrder = JobOrder::factory()->for($queueEntry)->create([
        'status' => JobOrderStatus::Intake,
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs($cashier)
        ->post(route('cashier.job-orders.cancel', $jobOrder), ['reason' => 'Customer changed their mind.'])
        ->assertRedirect();

    expect($jobOrder->fresh()->cancelled_at)->not->toBeNull()
        ->and($queueEntry->fresh()->status)->toBe(QueueStatus::Done);
});
