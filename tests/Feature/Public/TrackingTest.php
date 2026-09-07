<?php

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use Inertia\Testing\AssertableInertia as Assert;

test('the bare tracking page renders the lookup state with no result', function () {
    $response = $this->get(route('public.tracking.show'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('public/Tracking')
        ->where('result', null));
});

test('looking up an existing job order returns its mapped public stage', function () {
    $jobOrder = JobOrder::factory()->create([
        'number' => 'JO-2026-0001',
        'status' => JobOrderStatus::Printing->value,
    ]);

    $response = $this->get(route('public.tracking.show', ['number' => $jobOrder->number]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('public/Tracking')
        ->where('result.found', true)
        ->where('result.number', 'JO-2026-0001')
        ->where('result.stage', 'Printing'));
});

test('every pre-production status collapses to the In Progress public label', function (JobOrderStatus $status) {
    $jobOrder = JobOrder::factory()->create([
        'number' => 'JO-2026-0002',
        'status' => $status->value,
    ]);

    $response = $this->get(route('public.tracking.show', ['number' => $jobOrder->number]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('result.stage', 'In Progress'));
})->with([
    JobOrderStatus::Intake,
    JobOrderStatus::ValidationFailed,
    JobOrderStatus::ReadyForProduction,
    JobOrderStatus::Assigned,
    JobOrderStatus::InConsultation,
    JobOrderStatus::InDesign,
    JobOrderStatus::PendingReview,
    JobOrderStatus::DesignApproved,
]);

test('an order with released_at set shows Completed regardless of status', function () {
    $jobOrder = JobOrder::factory()->create([
        'number' => 'JO-2026-0003',
        'status' => JobOrderStatus::ReadyForPickup->value,
        'released_at' => now(),
    ]);

    $response = $this->get(route('public.tracking.show', ['number' => $jobOrder->number]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('result.stage', 'Completed'));
});

test('a cancelled job order reports Cancelled, not its production stage', function () {
    // CancellationController::store() leaves `status` untouched on purpose,
    // so a cancelled order sits at its last production status forever.
    $jobOrder = JobOrder::factory()->create([
        'number' => 'JO-2026-0005',
        'status' => JobOrderStatus::Printing->value,
    ]);
    $jobOrder->forceFill(['cancelled_at' => now()])->save();

    $response = $this->get(route('public.tracking.show', ['number' => $jobOrder->number]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('result.found', true)
        ->where('result.stage', 'Cancelled'));
});

test('a cancelled job order reports Cancelled even when it was already released', function () {
    $jobOrder = JobOrder::factory()->create([
        'number' => 'JO-2026-0006',
        'status' => JobOrderStatus::ReadyForPickup->value,
    ]);
    $jobOrder->forceFill(['released_at' => now(), 'cancelled_at' => now()])->save();

    $response = $this->get(route('public.tracking.show', ['number' => $jobOrder->number]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('result.stage', 'Cancelled'));
});

test('looking up a non-existent job order number returns a not-found result', function () {
    $response = $this->get(route('public.tracking.show', ['number' => 'JO-2026-9999']));

    $response->assertOk();
    // Omitting ->etc() inside the scope means AssertableJson's own
    // interacted() check fails if `result` carries any key beyond `found`
    // (e.g. a stray `number`/`stage`) — the "only these fields" assertion.
    $response->assertInertia(fn (Assert $page) => $page
        ->component('public/Tracking')
        ->has('result', fn (Assert $result) => $result->where('found', false)));
});

test('a five-digit sequence number is accepted, not rejected, by validation', function () {
    $jobOrder = JobOrder::factory()->create([
        'number' => 'JO-2026-10000',
        'status' => JobOrderStatus::ForProduction->value,
    ]);

    $response = $this->get(route('public.tracking.show', ['number' => $jobOrder->number]));

    $response->assertOk();
    $response->assertSessionHasNoErrors();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('result.found', true)
        ->where('result.number', 'JO-2026-10000'));
});

test('a malformed job order number fails validation and never sets a result', function () {
    $response = $this->get(route('public.tracking.show', ['number' => 'not-a-number']));

    $response->assertSessionHasErrors('number');
});

test('the tracking route is throttled with enough headroom for a 5s poll behind one NAT', function () {
    // The limiter keys guests by IP and the page polls 12 req/min per open
    // tab, so 60/min starts 429ing five customers on the same shop Wi-Fi.
    expect(collect(app('router')->getRoutes())
        ->first(fn ($route) => $route->getName() === 'public.tracking.show')
        ->middleware())
        ->toContain('throttle:120,1');
});

test('the tracking response never leaks pricing, payment, or file data for a found order', function () {
    $jobOrder = JobOrder::factory()->create([
        'number' => 'JO-2026-0004',
        'status' => JobOrderStatus::Printing->value,
        'total_amount' => 1234.56,
        'file_path' => 'job-orders/secret-file.pdf',
        'description' => 'Confidential customer description text',
    ]);

    $response = $this->get(route('public.tracking.show', ['number' => $jobOrder->number]));

    $response->assertOk();
    // Omitting ->etc() means the scope's own interacted() check fails if
    // `result` carries anything beyond these three fields.
    $response->assertInertia(fn (Assert $page) => $page
        ->has('result', fn (Assert $result) => $result
            ->where('found', true)
            ->has('number')
            ->has('stage')));

    $content = $response->getContent();
    expect($content)->not->toContain('total_amount');
    expect($content)->not->toContain('payment_status');
    expect($content)->not->toContain('file_path');
    expect($content)->not->toContain('Confidential customer description text');
});
