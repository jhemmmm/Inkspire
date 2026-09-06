<?php

use App\Enums\JobOrderStatus;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('a type a addJobOrder post with a valid pdf reaches for_production', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Tarpaulin, 3x5ft',
        'type' => 'type_a',
        'file' => UploadedFile::fake()->create('design.pdf', 500),
    ]);

    $jobOrder = $queueEntry->jobOrders()->firstOrFail();
    expect($jobOrder->status)->toBe(JobOrderStatus::ForProduction);
    expect($jobOrder->validation_failure_reason)->toBeNull();
});

test('a type a addJobOrder post with an unsupported extension reaches validation_failed', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Tarpaulin, 3x5ft',
        'type' => 'type_a',
        'file' => UploadedFile::fake()->create('design.xyz', 500),
    ]);

    $jobOrder = $queueEntry->jobOrders()->firstOrFail();
    expect($jobOrder->status)->toBe(JobOrderStatus::ValidationFailed);
    expect($jobOrder->validation_failure_reason)->toContain('isn\'t accepted');
});

test('a type a addJobOrder post with an oversized file reaches validation_failed', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Tarpaulin, 3x5ft',
        'type' => 'type_a',
        'file' => UploadedFile::fake()->create('design.pdf', 60000),
    ]);

    $jobOrder = $queueEntry->jobOrders()->firstOrFail();
    expect($jobOrder->status)->toBe(JobOrderStatus::ValidationFailed);
    expect($jobOrder->validation_failure_reason)->toContain('exceeds');
});

test('a type b addJobOrder post auto-assigns to the sole available artist', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $artist = User::factory()->artist()->create(['is_available' => true]);
    $queueEntry = QueueEntry::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Sticker, A4',
        'type' => 'type_b',
    ]);

    $jobOrder = $queueEntry->jobOrders()->firstOrFail();
    expect($jobOrder->status)->toBe(JobOrderStatus::Assigned);
    expect($jobOrder->assigned_artist_id)->toBe($artist->id);
});

test('a type b addJobOrder post with zero available artists leaves the job order unassigned', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Sticker, A4',
        'type' => 'type_b',
    ]);

    $jobOrder = $queueEntry->jobOrders()->firstOrFail();
    expect($jobOrder->status)->toBe(JobOrderStatus::Intake);
    expect($jobOrder->assigned_artist_id)->toBeNull();
});

test('replacing the file on a validation-failed type a job order with a valid file updates it to for_production', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->typeA()->validationFailed()->create();

    $response = $this->actingAs($staff)->post(route('frontline-staff.job-orders.replace-file', $jobOrder), [
        'file' => UploadedFile::fake()->create('design.pdf', 500),
    ]);

    $response->assertRedirect();
    $jobOrder->refresh();
    expect($jobOrder->status)->toBe(JobOrderStatus::ForProduction);
    expect($jobOrder->validation_failure_reason)->toBeNull();
    Storage::disk('local')->assertExists($jobOrder->file_path);
});

test('replace-file on a type b job order returns a 422', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create(['type' => 'type_b']);

    $response = $this->actingAs($staff)->post(route('frontline-staff.job-orders.replace-file', $jobOrder), [
        'file' => UploadedFile::fake()->create('design.pdf', 500),
    ]);

    $response->assertStatus(422);
});

test('a non frontline staff role is forbidden from the replace-file route', function () {
    $user = User::factory()->create(['role' => 'cashier']);
    $jobOrder = JobOrder::factory()->typeA()->validationFailed()->create();

    $response = $this->actingAs($user)->post(route('frontline-staff.job-orders.replace-file', $jobOrder), [
        'file' => UploadedFile::fake()->create('design.pdf', 500),
    ]);

    $response->assertForbidden();
});

test('a type a addJobOrder post writes an updated audit_trail row for the job order', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Tarpaulin, 3x5ft',
        'type' => 'type_a',
        'file' => UploadedFile::fake()->create('design.pdf', 500),
    ]);

    $jobOrder = $queueEntry->jobOrders()->firstOrFail();

    expect(
        DB::table('audit_trail')
            ->where('auditable_type', JobOrder::class)
            ->where('auditable_id', $jobOrder->id)
            ->where('action', 'updated')
            ->exists()
    )->toBeTrue();
});

test('a store post with one type a row and one type b row applies both outcomes in the same request', function () {
    Storage::fake('local');

    $staff = User::factory()->frontlineStaff()->create();
    $artist = User::factory()->artist()->create(['is_available' => true]);
    $customer = Customer::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
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

    $typeARow = JobOrder::where('type', 'type_a')->firstOrFail();
    $typeBRow = JobOrder::where('type', 'type_b')->firstOrFail();

    expect($typeARow->status)->toBe(JobOrderStatus::ForProduction);
    expect($typeBRow->status)->toBe(JobOrderStatus::Assigned);
    expect($typeBRow->assigned_artist_id)->toBe($artist->id);
});
