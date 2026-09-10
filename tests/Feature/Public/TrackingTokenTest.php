<?php

use App\Enums\JobOrderStatus;
use App\Models\Customer;
use App\Models\DesignFile;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\RevisionLog;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Build a job order under a customer whose PII is distinctive enough that any
 * leak into the public response is unmistakable.
 */
function trackedJobOrder(array $attributes = []): JobOrder
{
    $customer = Customer::factory()->create([
        'name' => 'Zenaida Villanueva',
        'contact_number' => '09171234567',
        'email' => 'zenaida.villanueva@example.test',
        'address' => '12 Mabini St',
    ]);

    $jobOrder = JobOrder::factory()
        ->for(QueueEntry::factory()->for($customer))
        ->create(array_merge(['number' => 'JO-2026-0777'], $attributes));

    $jobOrder->forceFill(['total_amount' => 8642.75])->save();

    return $jobOrder->fresh();
}

test('every job order gets a unique 32 character tracking token, whatever created it', function () {
    $fromFactory = JobOrder::factory()->create();
    $fromController = JobOrder::create([
        'queue_entry_id' => QueueEntry::factory()->create()->id,
        'description' => 'Sticker, A4',
        'type' => 'type_b',
        'status' => JobOrderStatus::Intake->value,
    ]);

    expect(strlen($fromFactory->tracking_token))->toBe(32);
    expect(strlen($fromController->tracking_token))->toBe(32);
    expect($fromFactory->tracking_token)->not->toBe($fromController->tracking_token);
});

test('a valid token renders the tracking page with the number and its public stage', function () {
    $jobOrder = trackedJobOrder(['status' => JobOrderStatus::Printing->value]);

    $response = $this->get(route('public.tracking.token', ['token' => $jobOrder->tracking_token]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('public/TrackingToken')
        // Exactly four keys. Any future widening of this payload fails here,
        // loudly, which is the point.
        ->has('result', 4)
        ->where('result.found', true)
        ->where('result.number', 'JO-2026-0777')
        ->where('result.stage', 'Printing')
        ->where('result.reviewUrl', null));
});

test('an unknown token renders a readable not-found state rather than an error page', function () {
    $response = $this->get(route('public.tracking.token', ['token' => 'thisisnotarealtrackingtoken00000']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('public/TrackingToken')
        ->has('result', 1)
        ->where('result.found', false));
});

test('the response leaks no customer pii, pricing, payment or raw status value', function () {
    $jobOrder = trackedJobOrder(['status' => JobOrderStatus::QualityCheck->value]);

    $response = $this->get(route('public.tracking.token', ['token' => $jobOrder->tracking_token]));

    $response->assertOk();

    // `false` as the second argument runs each check against the UNESCAPED
    // body — the Inertia data prop is JSON-encoded into the page, so an
    // escaped-only assertion would sail straight past a leak.
    $response->assertDontSee('Zenaida Villanueva', false);
    $response->assertDontSee('09171234567', false);
    $response->assertDontSee('zenaida.villanueva@example.test', false);
    $response->assertDontSee('Mabini', false);
    $response->assertDontSee('8642', false);
    $response->assertDontSee('quality_check', false);
    $response->assertDontSee('payment_status', false);
    $response->assertDontSee('total_amount', false);
    // The token is a bearer credential printed on a paper slip, so it must
    // never be selected back out of the database into the payload we control.
    // It does still appear once, inside Inertia's own `url` property, which
    // is simply the request path the visitor already has in their address
    // bar — that echo is unavoidable and discloses nothing they did not
    // already hold. Pinning the count at exactly one is what makes a genuine
    // re-selection of the column fail this test.
    $props = $response->viewData('page')['props'];
    expect(json_encode($props['result']))->not->toContain($jobOrder->tracking_token);
    expect(substr_count($response->getContent(), $jobOrder->tracking_token))->toBe(1);
    expect($response->viewData('page')['url'])->toContain($jobOrder->tracking_token);
});

test('a job order awaiting a verdict offers a freshly signed design review link', function () {
    $jobOrder = trackedJobOrder(['status' => JobOrderStatus::PendingReview->value]);
    DesignFile::factory()->for($jobOrder)->create();
    $revisionLog = RevisionLog::factory()->for($jobOrder)->create();

    $response = $this->get(route('public.tracking.token', ['token' => $jobOrder->tracking_token]));

    $reviewUrl = $response->viewData('page')['props']['result']['reviewUrl'];

    expect($reviewUrl)->not->toBeNull();
    expect($reviewUrl)->toContain(route('public.design-review.show', $revisionLog, false));

    $this->get($reviewUrl)->assertOk();

    // The same URL with its signature stripped must still be refused — this
    // action mints links into the existing boundary, it never widens it.
    $this->get(route('public.design-review.show', $revisionLog))->assertStatus(403);
});

test('no review link is offered when the job order is not awaiting a verdict', function () {
    $jobOrder = trackedJobOrder(['status' => JobOrderStatus::InDesign->value]);
    DesignFile::factory()->for($jobOrder)->create();
    RevisionLog::factory()->for($jobOrder)->create();

    $response = $this->get(route('public.tracking.token', ['token' => $jobOrder->tracking_token]));

    $response->assertInertia(fn (Assert $page) => $page->where('result.reviewUrl', null));
});

test('no review link is offered when the latest revision has already been resolved', function () {
    $jobOrder = trackedJobOrder(['status' => JobOrderStatus::PendingReview->value]);
    DesignFile::factory()->for($jobOrder)->create();
    RevisionLog::factory()->for($jobOrder)->approved()->create();

    $response = $this->get(route('public.tracking.token', ['token' => $jobOrder->tracking_token]));

    $response->assertInertia(fn (Assert $page) => $page->where('result.reviewUrl', null));
});

test('no review link is offered when the latest revision is already past its expiry', function () {
    $jobOrder = trackedJobOrder(['status' => JobOrderStatus::PendingReview->value]);
    DesignFile::factory()->for($jobOrder)->create();
    RevisionLog::factory()->for($jobOrder)->create(['submitted_at' => now()->subDays(8)]);

    $response = $this->get(route('public.tracking.token', ['token' => $jobOrder->tracking_token]));

    // A minted URL that 403s the moment the customer taps it is worse than no
    // link at all, so an already-past expiry yields null instead.
    $response->assertInertia(fn (Assert $page) => $page->where('result.reviewUrl', null));
});

test('the token route is public, unauthenticated and outside every role group', function () {
    $jobOrder = trackedJobOrder(['status' => JobOrderStatus::ReadyForPickup->value]);

    $route = app('router')->getRoutes()->getByName('public.tracking.token');

    expect($route->gatherMiddleware())->not->toContain('auth');
    expect($route->gatherMiddleware())->toContain('throttle:120,1');
    expect(collect($route->gatherMiddleware())->filter(fn ($m) => str_starts_with((string) $m, 'role:')))->toBeEmpty();

    $this->get(route('public.tracking.token', ['token' => $jobOrder->tracking_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('result.stage', 'Ready for Pickup'));
});

test('a cancelled or released job order reports its terminal stage, not its production status', function (array $overrides, string $expected) {
    $jobOrder = trackedJobOrder(['status' => JobOrderStatus::Printing->value]);
    $jobOrder->forceFill($overrides)->save();

    $response = $this->get(route('public.tracking.token', ['token' => $jobOrder->tracking_token]));

    $response->assertInertia(fn (Assert $page) => $page->where('result.stage', $expected));
})->with([
    'cancelled' => [['cancelled_at' => '2026-09-10 00:00:00'], 'Cancelled'],
    'released' => [['released_at' => '2026-09-10 00:00:00'], 'Completed'],
]);
