<?php

use App\Actions\JobOrder\EnterProduction;
use App\Enums\JobOrderStatus;
use App\Models\DesignFile;
use App\Models\JobOrder;
use App\Models\ProductionLog;
use App\Models\QueueEntry;
use App\Models\RevisionLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

test('invoking EnterProduction sets ForProduction, stamps due_at from default_sla_days, and writes exactly one system-authored production_logs row', function () {
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForProduction->value]);

    (new EnterProduction)($jobOrder);

    $jobOrder->refresh();
    expect($jobOrder->status)->toBe(JobOrderStatus::ForProduction);
    expect($jobOrder->due_at)->not->toBeNull();
    expect($jobOrder->due_at->diffInSeconds(now()->addDays(3)))->toBeLessThan(5);

    expect(ProductionLog::where('job_order_id', $jobOrder->id)->count())->toBe(1);

    $log = ProductionLog::where('job_order_id', $jobOrder->id)->firstOrFail();
    expect($log->from_status)->toBeNull();
    expect($log->to_status)->toBe(JobOrderStatus::ForProduction);
    expect($log->reason)->toBeNull();
    expect($log->recorded_by)->toBeNull();
});

test('invoking EnterProduction twice writes only one production_logs row and does not reset due_at', function () {
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForProduction->value]);

    (new EnterProduction)($jobOrder);
    $firstDueAt = $jobOrder->refresh()->due_at;

    $this->travel(2)->days();
    (new EnterProduction)($jobOrder);

    $jobOrder->refresh();
    expect(ProductionLog::where('job_order_id', $jobOrder->id)->count())->toBe(1);
    expect($jobOrder->due_at->equalTo($firstDueAt))->toBeTrue();
});

test('EnterProduction never re-enters a cancelled or released job order', function (string $stampedColumn) {
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::ReadyForProduction->value]);
    $jobOrder->forceFill([$stampedColumn => now()])->save();

    (new EnterProduction)($jobOrder);

    $jobOrder->refresh();
    expect($jobOrder->status)->toBe(JobOrderStatus::ReadyForProduction);
    expect($jobOrder->due_at)->toBeNull();
    expect(ProductionLog::where('job_order_id', $jobOrder->id)->count())->toBe(0);
})->with(['cancelled_at', 'released_at']);

test('a type a addJobOrder post with a valid file redirects successfully and lands the job order at ForProduction', function () {
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
    expect($jobOrder->status)->toBe(JobOrderStatus::ForProduction);
});

test('replacing a type a job orders file with a passing file lands the job order at ForProduction', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->typeA()->validationFailed()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.job-orders.replace-file', $jobOrder), [
        'file' => UploadedFile::fake()->create('design.pdf', 500),
    ]);

    $response->assertRedirect();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::ForProduction);
});

test('an artist approving a pending_review job order in-person lands it at ForProduction', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'pending_review']);
    DesignFile::factory()->for($jobOrder)->create();
    RevisionLog::factory()->for($jobOrder)->create();

    $response = $this->actingAs($artist)->patch(route('artist.job-orders.design.approve', $jobOrder));

    $response->assertRedirect();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::ForProduction);
});

test('a client approving remotely via the signed design-review link lands the job order at ForProduction', function () {
    Storage::fake('local');
    $jobOrder = JobOrder::factory()->create(['status' => 'pending_review']);
    DesignFile::factory()->for($jobOrder)->create();
    $revisionLog = RevisionLog::factory()->for($jobOrder)->create();

    $signedUrl = URL::temporarySignedRoute('public.design-review.approve', now()->addDays(7), ['revisionLog' => $revisionLog->id]);

    $response = $this->post($signedUrl);

    $response->assertOk();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::ForProduction);
});

test('a type a job order whose file is rejected is never created and never invokes EnterProduction', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Tarpaulin, 3x5ft',
        'type' => 'type_a',
        'file' => UploadedFile::fake()->create('design.xyz', 500),
    ])->assertSessionHasErrors('file');

    expect($queueEntry->jobOrders()->exists())->toBeFalse();
    expect(ProductionLog::count())->toBe(0);
});
