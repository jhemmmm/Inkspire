<?php

use App\Enums\JobOrderStatus;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\SpecificationOption;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('a customer can be registered against an organization', function () {
    $staff = User::factory()->frontlineStaff()->create();

    $this->actingAs($staff)->post(route('frontline-staff.customers.store'), [
        'name' => 'Maria Santos',
        'organization' => 'Ateneo de Naga University',
        'contact_number' => '09171234567',
        'email' => 'maria@example.test',
        'address' => '12 Panganiban Drive, Naga City',
    ])->assertSessionHasNoErrors();

    expect(Customer::firstOrFail()->organization)->toBe('Ateneo de Naga University');
});

test('the organization is optional, so a private walk-in still registers', function () {
    $staff = User::factory()->frontlineStaff()->create();

    $this->actingAs($staff)->post(route('frontline-staff.customers.store'), [
        'name' => 'Jose Cruz',
        'contact_number' => '09181234567',
        'email' => 'jose@example.test',
        'address' => '5 Magsaysay Ave, Naga City',
    ])->assertSessionHasNoErrors();

    expect(Customer::firstOrFail()->organization)->toBeNull();
});

test('customer search matches the organization, not just the person', function () {
    Customer::factory()->create(['name' => 'Maria Santos', 'organization' => 'Ateneo de Naga University']);
    Customer::factory()->create(['name' => 'Pedro Reyes', 'organization' => null]);

    $staff = User::factory()->frontlineStaff()->create();

    $this->actingAs($staff)
        ->get(route('frontline-staff.new-visit', ['q' => 'Ateneo']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers', 1)
            ->where('customers.0.name', 'Maria Santos')
        );
});

test('the intake screen carries the priced service catalog', function () {
    PricingEntry::factory()->create(['name' => 'Tarpaulin', 'base_price' => 12, 'unit' => 'sq ft']);
    PricingEntry::factory()->inactive()->create(['name' => 'Retired Service']);

    $staff = User::factory()->frontlineStaff()->create();

    $this->actingAs($staff)
        ->get(route('frontline-staff.new-visit'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('pricingEntries', 1)
            ->where('pricingEntries.0.name', 'Tarpaulin')
        );
});

test('a job order records the catalog entry it was priced from', function () {
    $entry = PricingEntry::factory()->create(['name' => 'Tarpaulin', 'base_price' => 12]);

    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [[
            'description' => 'Tarpaulin',
            'pricing_entry_id' => $entry->id,
            'type' => 'type_b',
        ]],
    ])->assertSessionHasNoErrors();

    expect(JobOrder::firstOrFail()->pricing_entry_id)->toBe($entry->id);
});

test('a type b job order keeps the client instructions given at the counter', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [[
            'description' => 'Wedding invitations',
            'type' => 'type_b',
            'client_notes' => "Gold on cream. Names are Ana & Miguel.\nAsked if we can do a foil finish.",
        ]],
    ])->assertSessionHasNoErrors();

    $jobOrder = JobOrder::firstOrFail();

    expect($jobOrder->client_notes)->toContain('Ana & Miguel');
    // The artist's own record must start empty -- the brief is not their notes.
    expect($jobOrder->consultation_notes)->toBeNull();
});

test('a type a file too low-resolution for its size goes to the artist pool, not back to the counter', function () {
    Storage::fake('local');

    SpecificationOption::factory()->printSize()->create([
        'label' => 'Tarpaulin 3x6ft',
        'width_inches' => 36,
        'height_inches' => 72,
    ]);

    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [[
            'description' => 'Tarpaulin',
            'type' => 'type_a',
            'print_size' => 'Tarpaulin 3x6ft',
            'file' => UploadedFile::fake()->image('design.jpg', 600, 300),
        ]],
    ])->assertSessionHasNoErrors();

    $jobOrder = JobOrder::firstOrFail();

    // Intake is the shared artist pool, the same place a Type B waits.
    expect($jobOrder->status)->toBe(JobOrderStatus::Intake);
    expect($jobOrder->validation_failure_reason)->toContain('DPI');
    expect($jobOrder->assigned_artist_id)->toBeNull();
});

test('a type a custom-size file too low-resolution for its intake width/height goes to the artist pool (bug 4)', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [[
            'description' => 'Custom Tarpaulin',
            'type' => 'type_a',
            'width_ft' => 10,
            'height_ft' => 20,
            'file' => UploadedFile::fake()->image('design.jpg', 720, 360),
        ]],
    ])->assertSessionHasNoErrors();

    $jobOrder = JobOrder::firstOrFail();

    expect($jobOrder->status)->toBe(JobOrderStatus::Intake);
    expect($jobOrder->validation_failure_reason)->toContain('DPI');
    expect($jobOrder->validation_failure_reason)->toContain('10 × 20 ft');
    expect($jobOrder->assigned_artist_id)->toBeNull();
});

test('a type a file with the wrong format is rejected to the counter, not the artist', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [[
            'description' => 'Tarpaulin',
            'type' => 'type_a',
            'file' => UploadedFile::fake()->create('design.xyz', 100),
        ]],
    ])->assertSessionHasErrors('job_orders.0.file');

    // No artist can turn a .xyz into artwork; only the customer has the file,
    // so the counter is told before anything is saved.
    expect(JobOrder::count())->toBe(0);
});

test('a type a file sharp enough for its size skips the artist entirely', function () {
    Storage::fake('local');

    SpecificationOption::factory()->printSize()->create([
        'label' => 'Tarpaulin 3x6ft',
        'width_inches' => 36,
        'height_inches' => 72,
    ]);

    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [[
            'description' => 'Tarpaulin',
            'type' => 'type_a',
            'print_size' => 'Tarpaulin 3x6ft',
            'file' => UploadedFile::fake()->image('design.jpg', 7200, 3600),
        ]],
    ])->assertSessionHasNoErrors();

    $jobOrder = JobOrder::firstOrFail();

    // Straight into production, where the Cashier dashboard also picks it up
    // for payment.
    expect($jobOrder->status)->toBe(JobOrderStatus::ForProduction);
    expect($jobOrder->assigned_artist_id)->toBeNull();
    expect($jobOrder->validation_failure_reason)->toBeNull();
});
