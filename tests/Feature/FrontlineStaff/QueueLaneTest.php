<?php

use App\Models\Customer;
use App\Models\QueueEntry;
use App\Models\User;

test('a visit containing a rush job order is issued a rush ticket', function () {
    $frontline = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($frontline)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            ['description' => 'Tarpaulin, 3x5ft', 'type' => 'type_b', 'is_rush' => true],
        ],
    ])->assertSessionHasNoErrors();

    $entry = QueueEntry::query()->latest('id')->first();

    expect($entry->queue_prefix)->toBe('R')
        ->and($entry->paddedNumber())->toBe('R-001');
});

test('a visit with no rush job order is issued a regular ticket', function () {
    $frontline = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($frontline)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            ['description' => 'Sticker, A4', 'type' => 'type_b', 'is_rush' => false],
        ],
    ])->assertSessionHasNoErrors();

    expect(QueueEntry::query()->latest('id')->first()->paddedNumber())->toBe('A-001');
});

test('one rush job order among several makes the whole visit rush', function () {
    $frontline = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($frontline)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            ['description' => 'Sticker, A4', 'type' => 'type_b', 'is_rush' => false],
            ['description' => 'Tarpaulin, 3x5ft', 'type' => 'type_b', 'is_rush' => true],
        ],
    ])->assertSessionHasNoErrors();

    expect(QueueEntry::query()->latest('id')->first()->queue_prefix)->toBe('R');
});

test('the two lanes number independently', function () {
    $frontline = User::factory()->frontlineStaff()->create();

    foreach ([true, true, false] as $isRush) {
        $this->actingAs($frontline)->post(route('frontline-staff.queue-entries.store'), [
            'customer_id' => Customer::factory()->create()->id,
            'job_orders' => [
                ['description' => 'Sticker, A4', 'type' => 'type_b', 'is_rush' => $isRush],
            ],
        ])->assertSessionHasNoErrors();
    }

    $tickets = QueueEntry::query()->orderBy('id')->get()->map(fn (QueueEntry $e) => $e->paddedNumber());

    // The rush lane reaching R-002 must not push the first regular ticket
    // past A-001.
    expect($tickets->all())->toBe(['R-001', 'R-002', 'A-001']);
});

test('both lanes may hold the same number on the same day', function () {
    $businessDate = QueueEntry::currentBusinessDate();

    QueueEntry::factory()->create(['queue_date' => $businessDate, 'queue_prefix' => 'R', 'queue_number' => 1]);
    QueueEntry::factory()->create(['queue_date' => $businessDate, 'queue_prefix' => 'A', 'queue_number' => 1]);

    expect(QueueEntry::query()->whereDate('queue_date', $businessDate)->count())->toBe(2);
});

test('rush visits are listed above regular ones in the frontline queue', function () {
    $frontline = User::factory()->frontlineStaff()->create();
    $businessDate = QueueEntry::currentBusinessDate();

    QueueEntry::factory()->create(['queue_date' => $businessDate, 'queue_prefix' => 'A', 'queue_number' => 1]);
    QueueEntry::factory()->create(['queue_date' => $businessDate, 'queue_prefix' => 'R', 'queue_number' => 5]);

    $response = $this->actingAs($frontline)->get(route('frontline-staff.queue-entries.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('queueEntries.0.queue_prefix', 'R')
        ->where('queueEntries.1.queue_prefix', 'A'));
});
