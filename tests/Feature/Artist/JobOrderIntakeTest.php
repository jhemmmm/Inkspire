<?php

use App\Enums\ArtistStatus;
use App\Enums\JobOrderStatus;
use App\Mail\JobOrdersReceived;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function intakeTestArtist(ArtistStatus $status = ArtistStatus::Available): User
{
    return User::factory()->artist()->create(['artist_status' => $status->value]);
}

/**
 * @return array<string, mixed>
 */
function intakeTestTypeBRow(string $description = 'Poster, A2'): array
{
    return ['description' => $description, 'type' => 'type_b'];
}

test('the new job order page renders with the intake catalog', function () {
    $artist = intakeTestArtist();
    Customer::factory()->create();

    $this->actingAs($artist)
        ->get(route('artist.job-orders.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('artist/NewJobOrder')
            ->has('customers', 1)
            ->has('specificationOptions')
            ->has('printSizeDimensions')
            ->has('rushFeePercentage')
            ->has('acceptedFileFormats')
            ->has('pricingEntries')
        );
});

test('an on-shift artist books a type b order straight into their own queue', function () {
    Mail::fake();
    $artist = intakeTestArtist();
    $customer = Customer::factory()->create();

    $this->actingAs($artist)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('artist.job-orders.store'), [
            'customer_id' => $customer->id,
            'job_orders' => [intakeTestTypeBRow()],
        ])
        ->assertRedirect(route('artist.dashboard'))
        ->assertSessionHas('inertia.flash_data', fn (array $flash): bool => str_contains($flash['toast']['message'], 'added to your queue'));

    $jobOrder = JobOrder::firstOrFail();

    expect($jobOrder->status)->toBe(JobOrderStatus::Assigned)
        ->and($jobOrder->assigned_artist_id)->toBe($artist->id)
        ->and($jobOrder->accepted_at)->not->toBeNull()
        ->and(QueueEntry::firstOrFail()->queue_prefix)->toBe('O');
});

test('a new customer can be registered inline and is used by the visit', function () {
    Mail::fake();
    $artist = intakeTestArtist();

    $this->actingAs($artist)
        ->post(route('artist.job-orders.store'), [
            'customer' => [
                'name' => 'Maria Santos',
                'organization' => 'Santos Bakery',
                'contact_number' => '09171234567',
                'email' => 'maria@example.com',
                'address' => '12 Rizal St',
            ],
            'job_orders' => [intakeTestTypeBRow()],
        ])
        ->assertSessionHasNoErrors();

    $customer = Customer::where('contact_number', '09171234567')->firstOrFail();

    expect($customer->name)->toBe('Maria Santos')
        ->and($customer->organization)->toBe('Santos Bakery')
        ->and(QueueEntry::firstOrFail()->customer_id)->toBe($customer->id);
});

test('a type a order with a passing file goes to production unassigned', function () {
    Mail::fake();
    Storage::fake('local');
    $artist = intakeTestArtist();
    $customer = Customer::factory()->create();

    $this->actingAs($artist)
        ->post(route('artist.job-orders.store'), [
            'customer_id' => $customer->id,
            'job_orders' => [[
                'description' => 'Tarpaulin, 3x5ft',
                'type' => 'type_a',
                'file' => UploadedFile::fake()->create('design.pdf', 500),
            ]],
        ])
        ->assertSessionHasNoErrors();

    $jobOrder = JobOrder::firstOrFail();

    expect($jobOrder->status)->toBe(JobOrderStatus::ForProduction)
        ->and($jobOrder->assigned_artist_id)->toBeNull();
});

test('an off-shift artist still creates the order and it waits in available jobs', function () {
    Mail::fake();
    $artist = intakeTestArtist(ArtistStatus::OffShift);
    $customer = Customer::factory()->create();

    $this->actingAs($artist)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('artist.job-orders.store'), [
            'customer_id' => $customer->id,
            'job_orders' => [intakeTestTypeBRow()],
        ])
        ->assertRedirect(route('artist.dashboard'))
        ->assertSessionHas('inertia.flash_data', fn (array $flash): bool => str_contains($flash['toast']['message'], 'Available Jobs'));

    $jobOrder = JobOrder::firstOrFail();

    expect($jobOrder->status)->toBe(JobOrderStatus::Intake)
        ->and($jobOrder->assigned_artist_id)->toBeNull();
});

test('roles other than artist cannot reach the intake routes', function (string $state) {
    $user = User::factory()->{$state}()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($user)->get(route('artist.job-orders.create'))->assertForbidden();
    $this->actingAs($user)->post(route('artist.job-orders.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [intakeTestTypeBRow()],
    ])->assertForbidden();

    expect(JobOrder::count())->toBe(0);
})->with(['cashier', 'frontlineStaff']);

test('the customer is emailed one tracking link per job order', function () {
    Mail::fake();
    $artist = intakeTestArtist();
    $customer = Customer::factory()->create();

    $this->actingAs($artist)->post(route('artist.job-orders.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [intakeTestTypeBRow('Poster'), intakeTestTypeBRow('Flyer')],
    ]);

    Mail::assertSent(JobOrdersReceived::class, function (JobOrdersReceived $mail) use ($customer): bool {
        if (! $mail->hasTo($customer->email)) {
            return false;
        }

        $html = $mail->render();

        return JobOrder::all()->every(
            fn (JobOrder $jobOrder): bool => str_contains($html, route('public.tracking.token', ['token' => $jobOrder->tracking_token]))
        );
    });
});

test('a mail failure never fails the save', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));
    $artist = intakeTestArtist();
    $customer = Customer::factory()->create();

    $this->actingAs($artist)
        ->post(route('artist.job-orders.store'), [
            'customer_id' => $customer->id,
            'job_orders' => [intakeTestTypeBRow()],
        ])
        ->assertRedirect(route('artist.dashboard'));

    expect(JobOrder::count())->toBe(1);
});

test('a customer is required and nothing is created without one', function () {
    $artist = intakeTestArtist();

    $this->actingAs($artist)
        ->post(route('artist.job-orders.store'), ['job_orders' => [intakeTestTypeBRow()]])
        ->assertSessionHasErrors(['customer_id', 'customer']);

    expect(QueueEntry::count())->toBe(0)->and(JobOrder::count())->toBe(0);
});

test('a duplicate contact number for a new customer is rejected without creating anything', function () {
    $artist = intakeTestArtist();
    $existing = Customer::factory()->create();

    $this->actingAs($artist)
        ->post(route('artist.job-orders.store'), [
            'customer' => [
                'name' => 'Pedro',
                'contact_number' => $existing->contact_number,
                'email' => 'pedro@example.com',
                'address' => 'Somewhere',
            ],
            'job_orders' => [intakeTestTypeBRow()],
        ])
        ->assertSessionHasErrors('customer.contact_number');

    expect(Customer::count())->toBe(1)
        ->and(QueueEntry::count())->toBe(0)
        ->and(JobOrder::count())->toBe(0);
});
