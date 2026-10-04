<?php

use App\Enums\JobOrderStatus;
use App\Mail\DesignReviewRequested;
use App\Models\DesignFile;
use App\Models\JobOrder;
use App\Models\RevisionLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

test('an unauthenticated visitor with a valid signed link sees the active review state', function () {
    Storage::fake('local');
    $jobOrder = JobOrder::factory()->create(['status' => 'pending_review']);
    DesignFile::factory()->for($jobOrder)->create();
    $revisionLog = RevisionLog::factory()->for($jobOrder)->create();

    $signedUrl = URL::temporarySignedRoute('public.design-review.show', now()->addDays(7), ['revisionLog' => $revisionLog->id]);

    $response = $this->get($signedUrl);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('public/DesignReview')
        ->where('state', 'active')
        ->where('jobOrderNumber', $jobOrder->number)
        ->where('trackingUrl', route('public.tracking.token', ['token' => $jobOrder->tracking_token]))
        ->has('imageUrl')
        ->has('approveUrl')
        ->has('requestChangesUrl'));
});

test('a request without a valid signature returns 403 and renders the expired state', function () {
    $jobOrder = JobOrder::factory()->create(['status' => 'pending_review']);
    DesignFile::factory()->for($jobOrder)->create();
    $revisionLog = RevisionLog::factory()->for($jobOrder)->create();

    $response = $this->get(route('public.design-review.show', $revisionLog));

    $response->assertStatus(403);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('public/DesignReview')
        ->where('state', 'expired'));
});

test('posting to a valid signed approve url locks the design file and advances the job order to design_approved', function () {
    Storage::fake('local');
    $jobOrder = JobOrder::factory()->create(['status' => 'pending_review']);
    $designFile = DesignFile::factory()->for($jobOrder)->create();
    $revisionLog = RevisionLog::factory()->for($jobOrder)->create();

    $signedUrl = URL::temporarySignedRoute('public.design-review.approve', now()->addDays(7), ['revisionLog' => $revisionLog->id]);

    $response = $this->post($signedUrl);

    $response->assertOk();
    expect($revisionLog->fresh()->outcome)->toBe('approved');
    expect($designFile->fresh()->locked_at)->not->toBeNull();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::ForProduction);
});

test('posting to a valid signed request-changes url bounces the job order to in_design without touching the lock', function () {
    Storage::fake('local');
    $jobOrder = JobOrder::factory()->create(['status' => 'pending_review']);
    $designFile = DesignFile::factory()->for($jobOrder)->create();
    $revisionLog = RevisionLog::factory()->for($jobOrder)->create();

    $signedUrl = URL::temporarySignedRoute('public.design-review.request-changes', now()->addDays(7), ['revisionLog' => $revisionLog->id]);

    $response = $this->post($signedUrl, ['message' => 'Please enlarge the heading.']);

    $response->assertOk();
    expect($revisionLog->fresh()->outcome)->toBe('changes_requested');
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::InDesign);
    expect($designFile->fresh()->locked_at)->toBeNull();
});

test('posting approve on an already-resolved revision performs no further mutation and renders the closed state', function () {
    Storage::fake('local');
    $jobOrder = JobOrder::factory()->create(['status' => 'design_approved']);
    $designFile = DesignFile::factory()->locked()->for($jobOrder)->create();
    $revisionLog = RevisionLog::factory()->approved()->for($jobOrder)->create();
    $lockedAtBefore = $designFile->fresh()->locked_at;

    $signedUrl = URL::temporarySignedRoute('public.design-review.approve', now()->addDays(7), ['revisionLog' => $revisionLog->id]);

    $response = $this->post($signedUrl);

    $response->assertOk();
    // A customer who has given their verdict is pointed at their order,
    // which is also where they pay for it.
    $response->assertInertia(fn (Assert $page) => $page
        ->component('public/DesignReview')
        ->where('state', 'closed')
        ->where('outcome', 'approved')
        ->where('jobOrderNumber', $jobOrder->number)
        ->where('trackingUrl', route('public.tracking.token', ['token' => $jobOrder->tracking_token])));
    expect($designFile->fresh()->locked_at->equalTo($lockedAtBefore))->toBeTrue();
});

test('an older superseded revision renders the stale state', function () {
    Storage::fake('local');
    $jobOrder = JobOrder::factory()->create(['status' => 'pending_review']);
    DesignFile::factory()->for($jobOrder)->create();
    $olderRevisionLog = RevisionLog::factory()->for($jobOrder)->create(['submitted_at' => now()->subDay()]);
    RevisionLog::factory()->for($jobOrder)->create(['submitted_at' => now()]);

    $signedUrl = URL::temporarySignedRoute('public.design-review.show', now()->addDays(7), ['revisionLog' => $olderRevisionLog->id]);

    $response = $this->get($signedUrl);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('public/DesignReview')
        ->where('state', 'stale'));
});

test('a newly emailed design-review link opens the submitted version', function () {
    Mail::fake();
    Storage::fake('local');
    $this->travelTo('2026-10-03 23:30:00');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'in_consultation']);

    $this->actingAs($artist)->post(route('artist.job-orders.design.send-for-review', $jobOrder), [
        'file' => UploadedFile::fake()->image('design.png'),
    ]);

    $reviewUrl = null;
    Mail::assertSent(DesignReviewRequested::class, function (DesignReviewRequested $mail) use ($jobOrder, &$reviewUrl): bool {
        $reviewUrl = $mail->content()->with['reviewUrl'];

        return $mail->hasTo($jobOrder->fresh()->queueEntry->customer->email);
    });

    auth()->logout();
    $this->get($reviewUrl)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/DesignReview')
            ->where('state', 'active')
            ->where('jobOrderNumber', $jobOrder->number));
});
