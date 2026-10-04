<?php

use App\Actions\JobOrder\ValidateJobOrderFile;
use App\Enums\FileValidationOutcome;
use App\Enums\JobOrderStatus;
use App\Mail\ConfirmOnlineOrder;
use App\Mail\JobOrdersReceived;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\OnlineOrder;
use App\Models\PricingEntry;
use App\Models\QueueEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function onlineOrderTestPayload(array $overrides = []): array
{
    return [
        'name' => 'Maria Santos',
        'organization' => 'Santos Bakery',
        'contact_number' => '09171234567',
        'email' => 'maria@example.test',
        'address' => '12 Rizal St, Cebu City',
        'job_orders' => [['pricing_entry_id' => onlineOrderTestProduct()->id, 'type' => 'type_b']],
        ...$overrides,
    ];
}

function onlineOrderTestProduct(string $name = 'Poster, A2'): PricingEntry
{
    return PricingEntry::factory()->create(['name' => $name, 'base_price' => 200, 'unit' => null]);
}

function onlineOrderTestConfirmUrl(OnlineOrder $order): string
{
    return URL::temporarySignedRoute('public.orders.confirm', now()->addHours(48), ['onlineOrder' => $order->id]);
}

test('the order page renders without prices', function () {
    PricingEntry::factory()->create(['name' => 'Tarpaulin', 'base_price' => 123.45]);

    $this->get(route('public.orders.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Order')
            ->has('pricingEntries', 1, fn (Assert $entry) => $entry->hasAll(['id', 'name', 'unit']))
            ->missing('rushFeePercentage')
        );
});

test('a valid submission is parked and a confirmation email is sent', function () {
    Mail::fake();

    $this->post(route('public.orders.store'), onlineOrderTestPayload())
        ->assertRedirect(route('public.orders.create'))
        ->assertSessionHas('orderSentTo', 'maria@example.test');

    expect(OnlineOrder::count())->toBe(1)
        ->and(Customer::count())->toBe(0)
        ->and(QueueEntry::count())->toBe(0)
        ->and(JobOrder::count())->toBe(0);

    Mail::assertSent(ConfirmOnlineOrder::class, fn (ConfirmOnlineOrder $mail): bool => $mail->hasTo('maria@example.test'));
});

test('confirming creates the customer, an online lane visit and the receipt email', function () {
    Mail::fake();
    $order = OnlineOrder::factory()->create();

    $this->post(onlineOrderTestConfirmUrl($order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/OrderConfirm')->where('state', 'confirmed')->has('jobOrders', 1));

    expect(Customer::count())->toBe(1)
        ->and(QueueEntry::firstOrFail()->queue_prefix)->toBe('O')
        ->and(JobOrder::count())->toBe(1)
        ->and($order->refresh()->confirmed_at)->not->toBeNull();

    Mail::assertSent(JobOrdersReceived::class, fn (JobOrdersReceived $mail): bool => $mail->hasTo($order->email));
});

test('confirming twice creates nothing the second time', function () {
    Mail::fake();
    $order = OnlineOrder::factory()->create();

    $this->post(onlineOrderTestConfirmUrl($order))->assertOk();
    $this->post(onlineOrderTestConfirmUrl($order))->assertOk();

    expect(QueueEntry::count())->toBe(1)
        ->and(JobOrder::count())->toBe(1);

    Mail::assertSent(JobOrdersReceived::class, 1);
});

test('an unsigned confirm link is refused and creates nothing', function () {
    Mail::fake();
    $order = OnlineOrder::factory()->create();

    $this->post(route('public.orders.confirm', ['onlineOrder' => $order->id]))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('public/OrderConfirm')->where('state', 'expired'));

    expect(Customer::count())->toBe(0)
        ->and(QueueEntry::count())->toBe(0)
        ->and($order->refresh()->confirmed_at)->toBeNull();
});

test('a price posted by the visitor is ignored', function () {
    Mail::fake();
    $entry = PricingEntry::factory()->create(['base_price' => 200, 'unit' => null]);

    $this->post(route('public.orders.store'), onlineOrderTestPayload([
        'job_orders' => [[
            'description' => $entry->name,
            'type' => 'type_b',
            'pricing_entry_id' => $entry->id,
            'quantity' => 2,
            'quoted_amount' => 1,
        ]],
    ]))->assertRedirect();

    $this->post(onlineOrderTestConfirmUrl(OnlineOrder::firstOrFail()))->assertOk();

    expect((float) JobOrder::firstOrFail()->quoted_amount)->toBe(400.0);
});

test('an unusable print file is rejected with a customer-facing reason', function () {
    Mail::fake();
    Storage::fake('local');

    $response = $this->post(route('public.orders.store'), onlineOrderTestPayload([
        'job_orders' => [[
            'pricing_entry_id' => onlineOrderTestProduct('Banner')->id,
            'type' => 'type_a',
            'file' => UploadedFile::fake()->create('design.xyz', 10),
        ]],
    ]));

    $response->assertSessionHasErrors('job_orders.0.file');
    expect(session('errors')->first('job_orders.0.file'))->not->toContain('Ask the customer')
        ->and(OnlineOrder::count())->toBe(0);
});

test('a filled honeypot is rejected', function () {
    Mail::fake();

    $this->post(route('public.orders.store'), onlineOrderTestPayload(['website' => 'http://spam.test']))
        ->assertSessionHasErrors('website');

    expect(OnlineOrder::count())->toBe(0);
    Mail::assertNothingSent();
});

test('an existing customer is reused and not overwritten', function () {
    Mail::fake();
    $customer = Customer::factory()->create(['contact_number' => '09171234567', 'name' => 'Original Name']);
    $order = OnlineOrder::factory()->create();
    $order->update(['payload' => [...$order->payload, 'customer' => [...$order->payload['customer'], 'contact_number' => '09171234567', 'name' => 'Someone Else']]]);

    $this->post(onlineOrderTestConfirmUrl($order))->assertOk();

    expect(Customer::count())->toBe(1)
        ->and($customer->refresh()->name)->toBe('Original Name')
        ->and(QueueEntry::firstOrFail()->customer_id)->toBe($customer->id);
});

test('a checked print-ready file goes to production and a consultation order waits at intake', function () {
    Mail::fake();
    $order = OnlineOrder::factory()->create();
    $order->update(['payload' => [...$order->payload, 'job_orders' => [
        ['description' => 'Flyer', 'type' => 'type_a', 'file_path' => 'job-orders/flyer.pdf', 'file_check' => ['outcome' => 'passed', 'reason' => null]],
        ['description' => 'Custom sign', 'type' => 'type_b'],
    ]]]);

    $this->post(onlineOrderTestConfirmUrl($order))->assertOk();

    expect(JobOrder::where('description', 'Flyer')->firstOrFail()->status)->toBe(JobOrderStatus::ForProduction)
        ->and(JobOrder::where('description', 'Custom sign')->firstOrFail()->status)->toBe(JobOrderStatus::Intake)
        ->and(JobOrder::where('description', 'Custom sign')->firstOrFail()->assigned_artist_id)->toBeNull();
});

test('pruning removes stale unconfirmed orders with their files and keeps confirmed files', function () {
    Storage::fake('local');
    Storage::disk('local')->put('job-orders/stale.pdf', 'x');
    Storage::disk('local')->put('job-orders/kept.pdf', 'x');

    $stale = OnlineOrder::factory()->create();
    $stale->update(['payload' => [...$stale->payload, 'job_orders' => [['description' => 'A', 'type' => 'type_a', 'file_path' => 'job-orders/stale.pdf']]]]);
    $stale->forceFill(['created_at' => now()->subDays(4)])->save();

    $confirmed = OnlineOrder::factory()->confirmed()->create();
    $confirmed->update(['payload' => [...$confirmed->payload, 'job_orders' => [['description' => 'B', 'type' => 'type_a', 'file_path' => 'job-orders/kept.pdf']]]]);
    $confirmed->forceFill(['created_at' => now()->subDays(4)])->save();

    $this->artisan('model:prune', ['--model' => [OnlineOrder::class]])->assertSuccessful();

    expect(OnlineOrder::whereKey($stale->id)->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists('job-orders/stale.pdf'))->toBeFalse()
        ->and(Storage::disk('local')->exists('job-orders/kept.pdf'))->toBeTrue();
});

test('more than five items are rejected', function () {
    Mail::fake();

    $rows = array_fill(0, 6, ['pricing_entry_id' => onlineOrderTestProduct()->id, 'type' => 'type_b']);

    $this->post(route('public.orders.store'), onlineOrderTestPayload(['job_orders' => $rows]))
        ->assertSessionHasErrors(['job_orders' => 'You can order up to 5 items at a time.']);

    expect(OnlineOrder::count())->toBe(0);
});

test('the product names an item, never the visitor\'s own text', function () {
    Mail::fake();
    $product = onlineOrderTestProduct('Tarpaulin');

    $this->post(route('public.orders.store'), onlineOrderTestPayload([
        'job_orders' => [[
            'pricing_entry_id' => $product->id,
            'description' => 'Free prize [click here](http://evil.test)',
            'type' => 'type_b',
        ]],
    ]))->assertRedirect();

    expect(OnlineOrder::firstOrFail()->payload['job_orders'][0]['description'])->toBe('Tarpaulin');
});

test('a product is required and must be one the shop still offers', function () {
    Mail::fake();
    $retired = PricingEntry::factory()->create(['is_active' => false]);

    $this->post(route('public.orders.store'), onlineOrderTestPayload(['job_orders' => [['type' => 'type_b']]]))
        ->assertSessionHasErrors(['job_orders.0.pricing_entry_id' => 'Pick a product or service.']);

    $this->post(route('public.orders.store'), onlineOrderTestPayload(['job_orders' => [['pricing_entry_id' => $retired->id, 'type' => 'type_b']]]))
        ->assertSessionHasErrors(['job_orders.0.pricing_entry_id' => 'Pick a product or service from the list.']);

    expect(OnlineOrder::count())->toBe(0);
});

test('a file attached to a design request is not kept', function () {
    Mail::fake();
    Storage::fake('local');

    $this->post(route('public.orders.store'), onlineOrderTestPayload([
        'job_orders' => [[
            'pricing_entry_id' => onlineOrderTestProduct()->id,
            'type' => 'type_b',
            'file' => UploadedFile::fake()->create('anything.exe', 10),
        ]],
    ]))->assertRedirect();

    expect(OnlineOrder::firstOrFail()->payload['job_orders'][0])->not->toHaveKey('file_path')
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('only orders that send mail count toward the limit, and the twenty-first is refused', function () {
    Mail::fake();
    $payload = onlineOrderTestPayload();

    foreach (range(1, 8) as $attempt) {
        $this->post(route('public.orders.store'), [...$payload, 'email' => 'not-an-email'])->assertSessionHasErrors('email');
    }

    foreach (range(1, 20) as $attempt) {
        $this->post(route('public.orders.store'), $payload)->assertSessionHasNoErrors();
    }

    $this->post(route('public.orders.store'), $payload)->assertSessionHasErrors('email');

    expect(OnlineOrder::count())->toBe(20);
    Mail::assertSent(ConfirmOnlineOrder::class, 20);
});

test('an order placed with a mobile number already on file keeps its own email for the visit', function () {
    Mail::fake();
    $customer = Customer::factory()->create(['contact_number' => '09171234567', 'email' => 'on-file@example.test']);
    $order = OnlineOrder::factory()->create(['email' => 'visitor@example.test']);
    $order->update(['payload' => [...$order->payload, 'customer' => [...$order->payload['customer'], 'contact_number' => '09171234567', 'email' => 'visitor@example.test']]]);

    $this->post(onlineOrderTestConfirmUrl($order))->assertOk();

    expect($customer->refresh()->email)->toBe('on-file@example.test')
        ->and(QueueEntry::firstOrFail()->contactEmail())->toBe('visitor@example.test');
});

test('a visit opened at the counter is contacted at the customer\'s own email', function () {
    $entry = QueueEntry::factory()->for(Customer::factory()->create(['email' => 'on-file@example.test']))->create();

    expect($entry->contactEmail())->toBe('on-file@example.test');
});

test('a print-ready upload is inspected once and parked with its verdict', function () {
    Mail::fake();
    Storage::fake('local');

    $this->mock(ValidateJobOrderFile::class)
        ->shouldReceive('__invoke')
        ->once()
        ->andReturn(['outcome' => FileValidationOutcome::Passed, 'reason' => null]);

    $this->post(route('public.orders.store'), onlineOrderTestPayload([
        'job_orders' => [[
            'pricing_entry_id' => onlineOrderTestProduct('Banner')->id,
            'type' => 'type_a',
            'file' => UploadedFile::fake()->create('design.pdf', 10),
        ]],
    ]))->assertRedirect(route('public.orders.create'));

    $row = OnlineOrder::firstOrFail()->payload['job_orders'][0];

    expect($row['file_check'])->toBe(['outcome' => FileValidationOutcome::Passed->value, 'reason' => null]);
    Storage::disk('local')->assertExists($row['file_path']);
});
