<?php

use App\Actions\JobOrder\ClaimJobOrderForArtist;
use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('frontline staff can open any job order, whatever stage it is at', function () {
    $frontline = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create(['status' => JobOrderStatus::Printing->value]);

    $response = $this->actingAs($frontline)->get(route('frontline-staff.job-orders.show', $jobOrder));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('frontline-staff/JobOrderDetail')
        ->where('jobOrder.id', $jobOrder->id)
        ->where('jobOrder.status', JobOrderStatus::Printing->value)
        ->has('jobOrder.transactions')
        ->has('jobOrder.production_logs'));
});

test('the detail view carries the file check reason so the counter can explain it', function () {
    $frontline = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->typeA()->create([
        'status' => JobOrderStatus::Intake->value,
        'validation_failure_reason' => 'At 3x6 ft the file works out to 40 DPI, below the 100 DPI minimum.',
    ]);

    $this->actingAs($frontline)
        ->get(route('frontline-staff.job-orders.show', $jobOrder))
        ->assertInertia(fn (Assert $page) => $page
            ->where('jobOrder.validation_failure_reason', 'At 3x6 ft the file works out to 40 DPI, below the 100 DPI minimum.'));
});

test('the detail view never leaks the artist consultation scratchpad', function () {
    $frontline = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create([
        'consultation_notes' => 'Customer is difficult, quoted high deliberately.',
    ]);

    $this->actingAs($frontline)
        ->get(route('frontline-staff.job-orders.show', $jobOrder))
        ->assertInertia(fn (Assert $page) => $page->missing('jobOrder.consultation_notes'));
});

test('the detail view opens for a job order an artist has already accepted', function () {
    // Regression: `accepted_at` was an uncast timestamp column, so reading it
    // as a date threw once — and only once — a job order had been claimed.
    $frontline = User::factory()->frontlineStaff()->create();
    $artist = User::factory()->artist()->create(['artist_status' => 'available']);
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::Intake->value,
        'assigned_artist_id' => null,
    ]);

    expect((new ClaimJobOrderForArtist)($jobOrder, $artist))->toBeTrue();

    $this->actingAs($frontline)
        ->get(route('frontline-staff.job-orders.show', $jobOrder))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->whereNot('jobOrder.accepted_at', null));
});

test('another portal cannot reach the frontline job order view', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->create();

    $this->actingAs($cashier)
        ->get(route('frontline-staff.job-orders.show', $jobOrder))
        ->assertForbidden();
});

test('a guest is sent to log in', function () {
    $jobOrder = JobOrder::factory()->create();

    $this->get(route('frontline-staff.job-orders.show', $jobOrder))->assertRedirect(route('login'));
});
