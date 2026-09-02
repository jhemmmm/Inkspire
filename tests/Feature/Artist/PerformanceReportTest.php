<?php

use App\Models\JobOrder;
use App\Models\RevisionLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('performance report shows zero stats for an artist with no completed job orders', function () {
    $artist = User::factory()->artist()->create();

    $response = $this->actingAs($artist)->get(route('artist.performance-report.index'));

    $response->assertOk();
    // The `false` skips Inertia's page-file-exists check: PerformanceReport.vue
    // is Plan 04-10's deliverable, not this (backend-only) plan's.
    $response->assertInertia(fn (Assert $page) => $page
        ->component('artist/PerformanceReport', false)
        ->where('stats.jobsCompleted', 0)
        ->where('stats.avgRevisions', 0)
        ->where('stats.slaAdherence', 0));
});

test('counts a design_approved job order with an approved revision log as completed', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'design_approved']);
    RevisionLog::factory()->for($jobOrder)->approved()->create();

    $response = $this->actingAs($artist)->get(route('artist.performance-report.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('stats.jobsCompleted', 1));
});

test('excludes a completed job order whose approved reviewed_at falls outside the requested from/to range', function () {
    $artist = User::factory()->artist()->create();
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create(['status' => 'design_approved']);
    RevisionLog::factory()->for($jobOrder)->approved()->create();
    RevisionLog::where('job_order_id', $jobOrder->id)->update(['reviewed_at' => now()->subDays(30)]);

    $response = $this->actingAs($artist)->get(route('artist.performance-report.index', [
        'from' => now()->subDays(2)->toDateString(),
        'to' => now()->toDateString(),
    ]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('stats.jobsCompleted', 0));
});

test('average revisions per job averages revision_logs count across completed job orders', function () {
    $artist = User::factory()->artist()->create();

    $jobOrderOne = JobOrder::factory()->assignedTo($artist)->create(['status' => 'design_approved']);
    RevisionLog::factory()->for($jobOrderOne)->approved()->create();

    $jobOrderTwo = JobOrder::factory()->assignedTo($artist)->create(['status' => 'design_approved']);
    RevisionLog::factory()->for($jobOrderTwo)->count(2)->create();
    RevisionLog::factory()->for($jobOrderTwo)->approved()->create();

    $response = $this->actingAs($artist)->get(route('artist.performance-report.index'));

    // Loose (==) comparison: PHP's json_encode drops the trailing zero from a
    // whole-number float (2.0 -> "2"), so the decoded prop is int(2), not
    // identical to a strict float(2.0).
    $response->assertInertia(fn (Assert $page) => $page
        ->where('stats.jobsCompleted', 2)
        ->where('stats.avgRevisions', fn ($value) => $value == 2.0));
});

test('sla adherence reflects the percentage of completed job orders approved within default_sla_days', function () {
    $artist = User::factory()->artist()->create();

    $withinSla = JobOrder::factory()->assignedTo($artist)->create(['status' => 'design_approved']);
    RevisionLog::factory()->for($withinSla)->approved()->create();
    RevisionLog::where('job_order_id', $withinSla->id)->update(['reviewed_at' => $withinSla->created_at->copy()->addDay()]);

    $outsideSla = JobOrder::factory()->assignedTo($artist)->create(['status' => 'design_approved']);
    RevisionLog::factory()->for($outsideSla)->approved()->create();
    RevisionLog::where('job_order_id', $outsideSla->id)->update(['reviewed_at' => $outsideSla->created_at->copy()->addDays(10)]);

    $response = $this->actingAs($artist)->get(route('artist.performance-report.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('stats.jobsCompleted', 2)
        ->where('stats.slaAdherence', 50));
});

test("an artist's performance report never counts another artist's completed job orders", function () {
    $artist = User::factory()->artist()->create();
    $otherArtist = User::factory()->artist()->create();

    $otherJobOrder = JobOrder::factory()->assignedTo($otherArtist)->create(['status' => 'design_approved']);
    RevisionLog::factory()->for($otherJobOrder)->approved()->create();

    $response = $this->actingAs($artist)->get(route('artist.performance-report.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('stats.jobsCompleted', 0));
});
