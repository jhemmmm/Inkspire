<?php

use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('the intake form persists a rush job order alongside a non-rush one', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            ['description' => 'Tarpaulin, 3x5ft', 'type' => 'type_b', 'is_rush' => true],
            ['description' => 'Sticker, A4', 'type' => 'type_b'],
        ],
    ])->assertSessionHasNoErrors();

    $rush = JobOrder::where('description', 'Tarpaulin, 3x5ft')->firstOrFail();
    $notRush = JobOrder::where('description', 'Sticker, A4')->firstOrFail();

    expect($rush->is_rush)->toBeTrue();
    expect($notRush->is_rush)->toBeFalse();
});

test('a rush flag submitted as multipart form data alongside a type a file still persists', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    // The FormData path Inertia's forceFormData uses serialises a JS boolean
    // as the string "1"/"0" — the exact shape a bare (bool) cast on "0" gets
    // right but a naive string check would not.
    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            [
                'description' => 'Tarpaulin, 3x5ft',
                'type' => 'type_a',
                'is_rush' => '1',
                'file' => UploadedFile::fake()->create('design.pdf', 500),
            ],
            [
                'description' => 'Sticker, A4',
                'type' => 'type_a',
                'is_rush' => '0',
                'file' => UploadedFile::fake()->create('sticker.pdf', 500),
            ],
        ],
    ])->assertSessionHasNoErrors();

    expect(JobOrder::where('description', 'Tarpaulin, 3x5ft')->firstOrFail()->is_rush)->toBeTrue();
    expect(JobOrder::where('description', 'Sticker, A4')->firstOrFail()->is_rush)->toBeFalse();
});

test('the add job order dialog persists the rush flag on an existing visit', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Business Cards, 100pcs',
        'type' => 'type_b',
        'is_rush' => '1',
    ])->assertSessionHasNoErrors();

    expect($queueEntry->jobOrders()->firstOrFail()->is_rush)->toBeTrue();
});

test('omitting the rush field on the add job order dialog creates a non-rush job order', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();

    // An unchecked reka-ui Switch omits its hidden checkbox from the
    // submission entirely, so the absent-field case is the common one.
    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Business Cards, 100pcs',
        'type' => 'type_b',
    ])->assertSessionHasNoErrors();

    expect($queueEntry->jobOrders()->firstOrFail()->is_rush)->toBeFalse();
});

test('is_rush is never null', function () {
    $jobOrder = JobOrder::factory()->create();

    expect($jobOrder->fresh()->getAttributes()['is_rush'])->not->toBeNull();
    expect($jobOrder->fresh()->is_rush)->toBeFalse();
});

test('the rush factory state produces a rush job order', function () {
    expect(JobOrder::factory()->rush()->create()->fresh()->is_rush)->toBeTrue();
});
