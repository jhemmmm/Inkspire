<?php

use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\SystemConfiguration;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('a single save creates one queue entry and its job orders atomically, storing the type a file', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            [
                'description' => 'Tarpaulin, 3x5ft',
                'type' => 'type_a',
                'file' => UploadedFile::fake()->create('design.pdf', 500),
            ],
            [
                'description' => 'Sticker, A4',
                'type' => 'type_b',
            ],
        ],
    ]);

    expect(QueueEntry::count())->toBe(1);
    expect(JobOrder::count())->toBe(2);

    $queueEntry = QueueEntry::first();
    $typeARow = JobOrder::where('type', 'type_a')->firstOrFail();

    expect($typeARow->file_path)->not->toBeNull();
    Storage::disk('local')->assertExists($typeARow->file_path);

    $response->assertRedirect(route('frontline-staff.new-visit', [
        'customer' => $queueEntry->customer_id,
        'queueEntry' => $queueEntry->id,
    ]));
});

test('the post-save redirect renders the confirmed queue entry with its job orders', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            ['description' => 'Business Cards, 100pcs', 'type' => 'type_b'],
        ],
    ]);

    $queueEntry = QueueEntry::first();

    $response = $this->actingAs($staff)->get(route('frontline-staff.new-visit', [
        'customer' => $customer->id,
        'queueEntry' => $queueEntry->id,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('frontline-staff/NewVisit')
        ->where('confirmedQueueEntry.id', $queueEntry->id)
        ->where('confirmedQueueEntry.queue_number', $queueEntry->queue_number)
        ->has('confirmedQueueEntry.job_orders', 1)
    );
});

test('submitting an empty job orders array fails validation', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [],
    ]);

    $response->assertSessionHasErrors('job_orders');
    expect(QueueEntry::count())->toBe(0);
});

test('a rejected type a file fails validation on its own row and saves nothing', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            [
                'description' => 'Sticker, A4',
                'type' => 'type_b',
            ],
            [
                'description' => 'Tarpaulin, 3x5ft',
                'type' => 'type_a',
                'file' => UploadedFile::fake()->create('design.xyz', 500),
            ],
        ],
    ]);

    $response->assertSessionHasErrors('job_orders.1.file');
    $response->assertSessionDoesntHaveErrors('job_orders.0.file');
    expect(session('errors')->first('job_orders.1.file'))->toContain('isn\'t accepted');

    expect(QueueEntry::count())->toBe(0);
    expect(JobOrder::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('the intake screen offers exactly the file formats the type a check accepts', function () {
    SystemConfiguration::updateOrCreate(
        ['key' => 'accepted_file_formats'],
        [
            'group' => 'file_handling',
            'value' => ['pdf', 'png'],
            'type' => 'array',
            'label' => 'Accepted file formats',
        ],
    );

    $staff = User::factory()->frontlineStaff()->create();

    $this->actingAs($staff)->get(route('frontline-staff.new-visit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('frontline-staff/NewVisit')
            ->where('acceptedFileFormats', ['pdf', 'png'])
        );
});

test('a non frontline staff role is blocked from creating a queue entry', function () {
    $user = User::factory()->create(['role' => 'cashier']);
    $customer = Customer::factory()->create();

    $response = $this->actingAs($user)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            ['description' => 'Sticker, A4', 'type' => 'type_b'],
        ],
    ]);

    $response->assertForbidden();
    expect(QueueEntry::count())->toBe(0);
});

test('creating a queue entry and its job orders each write an audit_trail row', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            [
                'description' => 'Tarpaulin, 3x5ft',
                'type' => 'type_a',
                'file' => UploadedFile::fake()->create('design.pdf', 500),
            ],
            ['description' => 'Sticker, A4', 'type' => 'type_b'],
        ],
    ]);

    $queueEntry = QueueEntry::firstOrFail();

    expect(
        DB::table('audit_trail')
            ->where('auditable_type', QueueEntry::class)
            ->where('auditable_id', $queueEntry->id)
            ->where('action', 'created')
            ->exists()
    )->toBeTrue();

    foreach (JobOrder::where('queue_entry_id', $queueEntry->id)->pluck('id') as $jobOrderId) {
        expect(
            DB::table('audit_trail')
                ->where('auditable_type', JobOrder::class)
                ->where('auditable_id', $jobOrderId)
                ->where('action', 'created')
                ->exists()
        )->toBeTrue();
    }
});
