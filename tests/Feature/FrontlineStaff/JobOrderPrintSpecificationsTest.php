<?php

use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\PricingEntry;
use App\Models\QueueEntry;
use App\Models\SpecificationOption;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('intake persists the print specifications captured at the counter', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            [
                'description' => 'Tarpaulin, 3x5ft',
                'type' => 'type_a',
                'print_size' => 'Tarpaulin 3x5ft',
                'quantity' => 12,
                'deadline' => now()->addDays(3)->toDateString(),
                'file' => UploadedFile::fake()->create('design.pdf', 500),
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $jobOrder = JobOrder::firstOrFail();

    expect($jobOrder->print_size)->toBe('Tarpaulin 3x5ft');
    expect($jobOrder->quantity)->toBe(12);
    expect($jobOrder->deadline->toDateString())->toBe(now()->addDays(3)->toDateString());
});

test('the customer deadline survives the job order entering production', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();
    $deadline = now()->addDays(10)->toDateString();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            [
                'description' => 'Tarpaulin, 3x5ft',
                'type' => 'type_a',
                'deadline' => $deadline,
                'file' => UploadedFile::fake()->create('design.pdf', 500),
            ],
        ],
    ]);

    $jobOrder = JobOrder::firstOrFail();

    // A passing Type A file enters production immediately, which stamps
    // `due_at` from the SLA config. That must not touch `deadline`.
    expect($jobOrder->due_at)->not->toBeNull();
    expect($jobOrder->deadline->toDateString())->toBe($deadline);
});

test('specifications are optional so a consultation walk-in can still be queued', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            ['description' => 'Wedding invitations', 'type' => 'type_b'],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $jobOrder = JobOrder::firstOrFail();

    expect($jobOrder->print_size)->toBeNull();
    expect($jobOrder->quantity)->toBeNull();
    expect($jobOrder->deadline)->toBeNull();
    expect($jobOrder->quoted_amount)->toBeNull();
});

test('a deadline in the past is rejected and nothing is queued', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            [
                'description' => 'Tarpaulin, 3x5ft',
                'type' => 'type_b',
                'deadline' => now()->subDay()->toDateString(),
            ],
        ],
    ]);

    $response->assertSessionHasErrors('job_orders.0.deadline');

    expect(QueueEntry::count())->toBe(0);
    expect(JobOrder::count())->toBe(0);
});

test('a zero quantity is rejected', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            ['description' => 'Stickers', 'type' => 'type_b', 'quantity' => 0],
        ],
    ])->assertSessionHasErrors('job_orders.0.quantity');

    expect(JobOrder::count())->toBe(0);
});

test('specifications are captured when a job order is added to an existing visit', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();

    $response = $this->actingAs($staff)->post(
        route('frontline-staff.queue-entries.job-orders.store', $queueEntry),
        [
            'description' => 'Sticker sheet',
            'type' => 'type_b',
            'print_size' => 'A4 (210x297mm)',
            'quantity' => 50,
        ],
    );

    $response->assertSessionHasNoErrors();

    $jobOrder = JobOrder::firstOrFail();

    expect($jobOrder->print_size)->toBe('A4 (210x297mm)');
    expect($jobOrder->quantity)->toBe(50);
});

test('the intake screen carries the active specification catalog', function () {
    SpecificationOption::factory()->printSize()->create(['label' => 'Tarpaulin 3x5ft']);
    SpecificationOption::factory()->printSize()->inactive()->create(['label' => 'Discontinued Size']);

    $staff = User::factory()->frontlineStaff()->create();

    $this->actingAs($staff)
        ->get(route('frontline-staff.new-visit'))
        ->assertInertia(fn ($page) => $page
            ->where('specificationOptions.print_size', ['Tarpaulin 3x5ft'])
        );
});

test('a sq-ft service computes quoted_amount from base price times width times height times quantity on store', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 12, 'unit' => 'sq ft']);

    $response = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            [
                'description' => 'Tarpaulin',
                'type' => 'type_b',
                'pricing_entry_id' => $pricingEntry->id,
                'width_ft' => 3,
                'height_ft' => 4,
                'quantity' => 2,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $jobOrder = JobOrder::firstOrFail();

    expect((float) $jobOrder->width_ft)->toBe(3.0);
    expect((float) $jobOrder->height_ft)->toBe(4.0);
    expect((float) $jobOrder->quoted_amount)->toBe(288.0);
});

test('a piece service computes quoted_amount from base price times quantity on store', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 500, 'unit' => 'piece']);

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            [
                'description' => 'X-Stand Banner',
                'type' => 'type_b',
                'pricing_entry_id' => $pricingEntry->id,
                'quantity' => 3,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $jobOrder = JobOrder::firstOrFail();

    expect((float) $jobOrder->quoted_amount)->toBe(1500.0);
});

test('a submitted quoted_amount override wins over the computed value on store', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 12, 'unit' => 'sq ft']);

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            [
                'description' => 'Tarpaulin',
                'type' => 'type_b',
                'pricing_entry_id' => $pricingEntry->id,
                'width_ft' => 3,
                'height_ft' => 4,
                'quantity' => 2,
                'quoted_amount' => 250,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $jobOrder = JobOrder::firstOrFail();

    expect((float) $jobOrder->quoted_amount)->toBe(250.0);
});

test('no pricing_entry_id leaves quoted_amount null on store', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            ['description' => 'Wedding invitations', 'type' => 'type_b'],
        ],
    ])->assertSessionHasNoErrors();

    $jobOrder = JobOrder::firstOrFail();

    expect($jobOrder->quoted_amount)->toBeNull();
});

test('a sq-ft service computes quoted_amount when a job order is added to an existing visit', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 12, 'unit' => 'sq ft']);

    $response = $this->actingAs($staff)->post(
        route('frontline-staff.queue-entries.job-orders.store', $queueEntry),
        [
            'description' => 'Tarpaulin',
            'type' => 'type_b',
            'pricing_entry_id' => $pricingEntry->id,
            'width_ft' => 3,
            'height_ft' => 4,
            'quantity' => 2,
        ],
    );

    $response->assertSessionHasNoErrors();

    $jobOrder = JobOrder::firstOrFail();

    expect((float) $jobOrder->quoted_amount)->toBe(288.0);
});

test('a submitted quoted_amount override wins on the add-job-order endpoint', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 12, 'unit' => 'sq ft']);

    $this->actingAs($staff)->post(
        route('frontline-staff.queue-entries.job-orders.store', $queueEntry),
        [
            'description' => 'Tarpaulin',
            'type' => 'type_b',
            'pricing_entry_id' => $pricingEntry->id,
            'width_ft' => 3,
            'height_ft' => 4,
            'quantity' => 2,
            'quoted_amount' => 250,
        ],
    )->assertSessionHasNoErrors();

    $jobOrder = JobOrder::firstOrFail();

    expect((float) $jobOrder->quoted_amount)->toBe(250.0);
});

test('omitting the override recomputes quoted_amount from width, height, and quantity', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();
    $pricingEntry = PricingEntry::factory()->create(['base_price' => 10, 'unit' => 'sq ft']);

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            [
                'description' => 'Tarpaulin',
                'type' => 'type_b',
                'pricing_entry_id' => $pricingEntry->id,
                'width_ft' => 2,
                'height_ft' => 5,
                'quantity' => 1,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $jobOrder = JobOrder::firstOrFail();

    expect((float) $jobOrder->quoted_amount)->toBe(100.0);
});
