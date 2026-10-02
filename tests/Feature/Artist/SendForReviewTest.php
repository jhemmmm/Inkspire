<?php

use App\Enums\JobOrderStatus;
use App\Models\DesignFile;
use App\Models\JobOrder;
use App\Models\RevisionLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

test('starting a design from in_consultation transitions the job order to in_design', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_consultation']);

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.design.start', $jobOrder));

    $response->assertRedirect();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::InDesign);
});

test('starting a design on a job order that already has one (in_design or beyond) returns a 422', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_design']);

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.design.start', $jobOrder));

    $response->assertStatus(422);
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::InDesign);
});

test('starting a design via an Inertia request on an already-started job order redirects back with a flashed error toast instead of a raw exception page', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_design']);

    $response = $this->actingAs($artist)
        ->withHeaders(['X-Inertia' => 'true'])
        ->patch(route('artist.job-orders.design.start', $jobOrder));

    $response->assertRedirect();
    $response->assertSessionHas(
        'inertia.flash_data',
        fn (array $flash) => $flash['toast']['type'] === 'error'
            && $flash['toast']['message'] === 'This job order is not ready to start a design.',
    );
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::InDesign);
});

test('sending a design for review from in_consultation creates a design_files row and a revision_logs row, and advances status to pending_review', function () {
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_consultation']);

    $response = $this->actingAs($artist)->post(route('artist.job-orders.design.send-for-review', $jobOrder), [
        'file' => UploadedFile::fake()->image('design.png'),
    ]);

    $response->assertRedirect();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::PendingReview);
    expect(DesignFile::where('job_order_id', $jobOrder->id)->count())->toBe(1);
    expect(RevisionLog::where('job_order_id', $jobOrder->id)->count())->toBe(1);
});

test('sending for review still succeeds and commits the revision even when the mail transport throws', function () {
    Storage::fake('local');
    Exceptions::fake();
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_consultation']);

    Mail::shouldReceive('to')->once()->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new Exception('Resend is down.'));

    $response = $this->actingAs($artist)->post(route('artist.job-orders.design.send-for-review', $jobOrder), [
        'file' => UploadedFile::fake()->image('design.png'),
    ]);

    $response->assertRedirect();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::PendingReview);
    expect(DesignFile::where('job_order_id', $jobOrder->id)->count())->toBe(1);
    expect(RevisionLog::where('job_order_id', $jobOrder->id)->count())->toBe(1);
    Exceptions::assertReported(Exception::class);
});

test('sending for review twice creates two revision_logs rows while design_files stays a single row with the newest file_path', function () {
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_consultation']);

    $this->actingAs($artist)->post(route('artist.job-orders.design.send-for-review', $jobOrder), [
        'file' => UploadedFile::fake()->image('design-v1.png'),
    ]);

    // Resolve the first submission's review directly, bypassing the HTTP
    // layer (Plan 04-05 owns the review endpoints), so the second POST
    // below isn't itself blocked by the pending-review guard.
    RevisionLog::where('job_order_id', $jobOrder->id)->update([
        'outcome' => 'changes_requested',
        'reviewed_at' => now(),
    ]);
    $jobOrder->forceFill(['status' => JobOrderStatus::InDesign])->save();

    $firstFilePath = $jobOrder->fresh()->designFile->file_path;

    $this->actingAs($artist)->post(route('artist.job-orders.design.send-for-review', $jobOrder), [
        'file' => UploadedFile::fake()->image('design-v2.png'),
    ]);

    expect(RevisionLog::where('job_order_id', $jobOrder->id)->count())->toBe(2);
    expect(DesignFile::where('job_order_id', $jobOrder->id)->count())->toBe(1);
    expect($jobOrder->fresh()->designFile->file_path)->not->toBe($firstFilePath);
});

test('sending for review a second time while the first submission is still pending_review returns 422 and does not create a second revision_logs row', function () {
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'pending_review']);
    DesignFile::factory()->for($jobOrder)->create();
    RevisionLog::factory()->for($jobOrder)->create();

    $countBefore = RevisionLog::where('job_order_id', $jobOrder->id)->count();

    $response = $this->actingAs($artist)->post(route('artist.job-orders.design.send-for-review', $jobOrder), [
        'file' => UploadedFile::fake()->image('design.png'),
    ]);

    $response->assertStatus(422);
    expect(RevisionLog::where('job_order_id', $jobOrder->id)->count())->toBe($countBefore);
});

test('a JPG is accepted', function () {
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_consultation']);

    $response = $this->actingAs($artist)->post(route('artist.job-orders.design.send-for-review', $jobOrder), [
        'file' => UploadedFile::fake()->image('design.jpg'),
    ]);

    $response->assertSessionHasNoErrors();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::PendingReview);
    expect(DesignFile::where('job_order_id', $jobOrder->id)->count())->toBe(1);
    expect(RevisionLog::where('job_order_id', $jobOrder->id)->count())->toBe(1);
});

test('a non-image (PDF) fails validation and changes nothing', function () {
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_consultation']);

    $response = $this->actingAs($artist)->post(route('artist.job-orders.design.send-for-review', $jobOrder), [
        'file' => UploadedFile::fake()->create('design.pdf', 100, 'application/pdf'),
    ]);

    $response->assertSessionHasErrors('file');
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::InConsultation);
    expect(RevisionLog::where('job_order_id', $jobOrder->id)->count())->toBe(0);
});

test('sending for review on a job order still at assigned (not yet claimed) returns 422', function () {
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create();

    $response = $this->actingAs($artist)->post(route('artist.job-orders.design.send-for-review', $jobOrder), [
        'file' => UploadedFile::fake()->image('design.png'),
    ]);

    $response->assertStatus(422);
});

test('an artist cannot send for review a job order assigned to a different artist', function () {
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $otherArtist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($otherArtist)->create(['status' => 'in_consultation']);

    $response = $this->actingAs($artist)->post(route('artist.job-orders.design.send-for-review', $jobOrder), [
        'file' => UploadedFile::fake()->image('design.png'),
    ]);

    $response->assertForbidden();
});
