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

test('change requests validate the message without changing a pending revision', function (string $source, mixed $message, string $error) {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => JobOrderStatus::PendingReview]);
    $design = DesignFile::factory()->for($jobOrder)->create();
    $revision = RevisionLog::factory()->for($jobOrder)->create();

    $response = $source === 'public'
        ? $this->post(URL::temporarySignedRoute('public.design-review.request-changes', now()->addDay(), $revision), ['message' => $message])
        : $this->actingAs($artist)->patch(route('artist.job-orders.design.request-changes', $jobOrder), ['message' => $message]);

    $response->assertSessionHasErrors(['message' => $error]);
    expect($revision->fresh()->outcome)->toBeNull();
    expect($revision->fresh()->message)->toBeNull();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::PendingReview);
    expect($design->fresh()->locked_at)->toBeNull();
})->with(['public', 'artist'])->with([
    'missing' => [null, 'Please describe the changes you would like.'],
    'blank' => [" \n\t ", 'Please describe the changes you would like.'],
    'too long' => [str_repeat('a', 5001), 'The message must not exceed 5,000 characters.'],
    'array' => [['unexpected'], 'The message field must be a string.'],
]);

test('public and artist change requests save trimmed multiline feedback', function (string $source) {
    $this->freezeTime();
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => JobOrderStatus::PendingReview]);
    DesignFile::factory()->for($jobOrder)->create();
    $revision = RevisionLog::factory()->for($jobOrder)->create();
    $message = "Correct the name.\n<script>Make the title larger</script>";

    $response = $source === 'public'
        ? $this->post(URL::temporarySignedRoute('public.design-review.request-changes', now()->addDay(), $revision), ['message' => "  {$message}  "])
        : $this->actingAs($artist)->patch(route('artist.job-orders.design.request-changes', $jobOrder), ['message' => "  {$message}  "]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('revision_logs', ['id' => $revision->id, 'message' => $message, 'outcome' => 'changes_requested', 'reviewed_at' => now()]);
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::InDesign);
    expect($jobOrder->fresh()->designFile->locked_at)->toBeNull();
    $this->actingAs($artist)->get(route('artist.job-orders.show', $jobOrder))
        ->assertInertia(fn (Assert $page) => $page->where('review.revisionLogs.0.message', $message));
})->with(['public', 'artist']);

test('a maximum length message is accepted', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => JobOrderStatus::PendingReview]);
    DesignFile::factory()->for($jobOrder)->create();
    $revision = RevisionLog::factory()->for($jobOrder)->create();

    $this->actingAs($artist)->patch(route('artist.job-orders.design.request-changes', $jobOrder), ['message' => str_repeat('a', 5000)])
        ->assertSessionHasNoErrors();

    expect($revision->fresh()->message)->toHaveLength(5000);
});

test('superseded signed verdicts cannot resolve the latest pending design', function (string $verdict) {
    Storage::fake('local');
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::PendingReview]);
    $design = DesignFile::factory()->for($jobOrder)->create();
    $older = RevisionLog::factory()->for($jobOrder)->create();
    $latest = RevisionLog::factory()->for($jobOrder)->create(['submitted_at' => $older->submitted_at]);

    $response = $this->post(URL::temporarySignedRoute("public.design-review.{$verdict}", now()->addDay(), $older), ['message' => 'Outdated feedback']);

    $response->assertInertia(fn (Assert $page) => $page->where('state', 'stale'));
    expect($older->fresh()->outcome)->toBeNull();
    expect($latest->fresh()->outcome)->toBeNull();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::PendingReview);
    expect($design->fresh()->locked_at)->toBeNull();
})->with(['approve', 'request-changes']);

test('feedback preserves the submission timestamp and the next emailed review opens the latest version', function () {
    $this->freezeTime();
    Mail::fake();
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => JobOrderStatus::PendingReview]);
    DesignFile::factory()->for($jobOrder)->create();
    $older = RevisionLog::factory()->for($jobOrder)->create();
    $submittedAt = $older->submitted_at;
    $olderReviewUrl = (new DesignReviewRequested($older))->content()->with['reviewUrl'];
    $this->travel(8)->hours();
    $this->post(URL::temporarySignedRoute('public.design-review.request-changes', $submittedAt->copy()->addDays(7), $older), ['message' => 'Make the heading larger'])
        ->assertInertia(fn (Assert $page) => $page->where('outcome', 'changes_requested'));
    expect($older->fresh()->submitted_at->equalTo($submittedAt))->toBeTrue();
    expect((new DesignReviewRequested($older->fresh()))->content()->with['reviewUrl'])->toBe($olderReviewUrl);
    $this->actingAs($artist)->post(route('artist.job-orders.design.send-for-review', $jobOrder), ['file' => UploadedFile::fake()->image('next.png')])
        ->assertRedirect();
    $latest = $jobOrder->revisionLogs()->latest('submitted_at')->latest('id')->first();
    Mail::assertSent(DesignReviewRequested::class, fn (DesignReviewRequested $mail) => $mail->revisionLog->is($latest));
    auth()->logout();
    $reviewUrl = (new DesignReviewRequested($latest))->content()->with['reviewUrl'];

    $this->get($reviewUrl)->assertInertia(fn (Assert $page) => $page
        ->where('state', 'active')
        ->where('approveUrl', URL::temporarySignedRoute('public.design-review.approve', now()->addDays(7), $latest)));
    $this->get($olderReviewUrl)
        ->assertInertia(fn (Assert $page) => $page->where('state', 'stale'));
    $this->get(route('public.tracking.token', $jobOrder->tracking_token))->assertInertia(fn (Assert $page) => $page
        ->where('result.reviewUrl', $reviewUrl));
    $this->actingAs($artist)->get(route('artist.job-orders.show', $jobOrder))->assertInertia(fn (Assert $page) => $page
        ->where('review.revisionLogs.0.id', $latest->id)
        ->where('review.revisionLogs.0.version', 2)
        ->where('review.revisionLogs.1.id', $older->id)
        ->where('review.revisionLogs.1.version', 1));
});

test('public and artist verdicts resolve the newest pending submission and preserve earlier feedback', function (string $source, string $verdict) {
    $this->freezeTime();
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => JobOrderStatus::PendingReview]);
    $design = DesignFile::factory()->for($jobOrder)->create();
    $older = RevisionLog::factory()->changesRequested()->for($jobOrder)->create();
    $older->forceFill(['message' => 'Earlier feedback'])->save();
    $this->travel(1)->minutes();
    $latest = RevisionLog::factory()->for($jobOrder)->create();
    $submittedAt = $latest->submitted_at;
    $this->travel(8)->hours();

    $response = $source === 'public'
        ? $this->post(URL::temporarySignedRoute("public.design-review.{$verdict}", now()->addDay(), $latest), ['message' => 'Make the heading larger'])
        : $this->actingAs($artist)->patch(route("artist.job-orders.design.{$verdict}", $jobOrder), ['message' => 'Make the heading larger']);

    if ($source === 'public') {
        $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('state', 'closed'));
    } else {
        $response->assertRedirect();
    }

    $response->assertSessionHasNoErrors();
    $outcome = $verdict === 'approve' ? 'approved' : 'changes_requested';
    $this->assertDatabaseHas('revision_logs', ['id' => $latest->id, 'outcome' => $outcome, 'reviewed_at' => now()]);
    expect($latest->fresh()->message)->toBe($verdict === 'approve' ? null : 'Make the heading larger');
    expect($latest->fresh()->submitted_at->equalTo($submittedAt))->toBeTrue();
    expect($older->fresh()->outcome)->toBe('changes_requested');
    expect($older->fresh()->message)->toBe('Earlier feedback');
    expect($older->fresh()->reviewed_at->equalTo($older->reviewed_at))->toBeTrue();
    expect($jobOrder->fresh()->status)->toBe($verdict === 'approve' ? JobOrderStatus::ForProduction : JobOrderStatus::InDesign);
    expect($design->fresh()->locked_at !== null)->toBe($verdict === 'approve');
})->with(['public', 'artist'])->with(['approve', 'request-changes']);

test('repeated public or artist verdicts preserve the first feedback and timestamp', function (string $source) {
    $this->freezeTime();
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => JobOrderStatus::PendingReview]);
    DesignFile::factory()->for($jobOrder)->create();
    $revision = RevisionLog::factory()->for($jobOrder)->create();
    $url = URL::temporarySignedRoute('public.design-review.request-changes', now()->addDay(), $revision);
    $this->post($url, ['message' => 'First feedback']);
    $reviewedAt = $revision->fresh()->reviewed_at;
    $this->travel(1)->minutes();

    if ($source === 'public') {
        $this->post($url, ['message' => 'Replacement feedback'])->assertInertia(fn (Assert $page) => $page->where('state', 'closed'));
        $this->post(URL::temporarySignedRoute('public.design-review.approve', now()->addDay(), $revision))
            ->assertInertia(fn (Assert $page) => $page->where('outcome', 'changes_requested'));
    } else {
        $this->actingAs($artist)->patch(route('artist.job-orders.design.request-changes', $jobOrder), ['message' => 'Replacement feedback'])->assertUnprocessable();
        $this->patch(route('artist.job-orders.design.approve', $jobOrder))->assertUnprocessable();
    }

    expect($revision->fresh()->message)->toBe('First feedback');
    expect($revision->fresh()->reviewed_at->equalTo($reviewedAt))->toBeTrue();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::InDesign);
})->with(['public', 'artist']);

test('change requests cannot overwrite an approved design', function (string $source) {
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => JobOrderStatus::ForProduction]);
    $design = DesignFile::factory()->locked()->for($jobOrder)->create();
    $revision = RevisionLog::factory()->approved()->for($jobOrder)->create();
    $lockedAt = $design->locked_at;

    if ($source === 'public') {
        $this->post(URL::temporarySignedRoute('public.design-review.request-changes', now()->addDay(), $revision), ['message' => 'Too late'])
            ->assertInertia(fn (Assert $page) => $page->where('outcome', 'approved'));
    } else {
        $this->actingAs($artist)->patch(route('artist.job-orders.design.request-changes', $jobOrder), ['message' => 'Too late'])->assertUnprocessable();
    }

    expect($revision->fresh()->outcome)->toBe('approved');
    expect($revision->fresh()->message)->toBeNull();
    expect($design->fresh()->locked_at->equalTo($lockedAt))->toBeTrue();
})->with(['public', 'artist']);

test('submissions retain each file and show version history after approval', function () {
    $this->freezeTime();
    Mail::fake();
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => JobOrderStatus::InConsultation]);

    $this->actingAs($artist)->post(route('artist.job-orders.design.send-for-review', $jobOrder), ['file' => UploadedFile::fake()->image('first.png')]);
    $first = $jobOrder->revisionLogs()->sole();
    $this->patch(route('artist.job-orders.design.request-changes', $jobOrder), ['message' => 'Use a larger heading']);
    $this->post(route('artist.job-orders.design.send-for-review', $jobOrder), ['file' => UploadedFile::fake()->image('second.png')]);
    $second = $jobOrder->revisionLogs()->latest('id')->first();
    $this->patch(route('artist.job-orders.design.approve', $jobOrder))->assertRedirect();

    Storage::disk('local')->assertExists([$first->file_path, $second->file_path]);
    expect($second->file_path)->not->toBe($first->file_path);
    expect($jobOrder->fresh()->designFile->file_path)->toBe($second->file_path);
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::ForProduction);
    Mail::assertSentCount(2);
    $this->get(route('artist.job-orders.show', $jobOrder))->assertInertia(fn (Assert $page) => $page
        ->where('review.canRecordVerdict', false)
        ->has('review.revisionLogs', 2)
        ->where('review.revisionLogs.0.version', 2)
        ->where('review.revisionLogs.0.outcome', 'approved')
        ->where('review.revisionLogs.0.has_file', true)
        ->where('review.revisionLogs.1.version', 1)
        ->where('review.revisionLogs.1.message', 'Use a larger heading'));
});

test('historical file links enforce assignment and revision ownership', function () {
    Storage::fake('local');
    Storage::disk('local')->put('design-files/old.png', 'old version');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create();
    $revision = RevisionLog::factory()->for($jobOrder)->create(['file_path' => 'design-files/old.png']);
    $otherOrder = JobOrder::factory()->assignedTo($artist)->create();

    $this->get(route('artist.job-orders.revisions.file', [$jobOrder, $revision]))->assertRedirect(route('login'));
    $this->actingAs($artist)->get(route('artist.job-orders.revisions.file', [$jobOrder, $revision]))->assertRedirect();
    $this->get(route('artist.job-orders.revisions.file', [$otherOrder, $revision]))->assertNotFound();
    $this->actingAs(User::factory()->artist()->create())->get(route('artist.job-orders.revisions.file', [$jobOrder, $revision]))->assertForbidden();
    $this->actingAs(User::factory()->cashier()->create())->get(route('artist.job-orders.revisions.file', [$jobOrder, $revision]))->assertForbidden();
});

test('revisions without a stored file have no preview and return 404', function (?string $path) {
    Storage::fake('local');
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create();
    $revision = RevisionLog::factory()->for($jobOrder)->create(['file_path' => $path]);

    $this->actingAs($artist)->get(route('artist.job-orders.show', $jobOrder))->assertInertia(fn (Assert $page) => $page
        ->where('review.revisionLogs.0.has_file', false));
    $this->get(route('artist.job-orders.revisions.file', [$jobOrder, $revision]))->assertNotFound();
})->with([null, 'design-files/missing.png']);
