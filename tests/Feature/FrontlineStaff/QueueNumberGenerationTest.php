<?php

use App\Models\Customer;
use App\Models\QueueEntry;
use App\Models\User;

test('queue numbers increment sequentially within the same business day', function () {
    // This proves sequential correctness only. True concurrent-write safety
    // (two requests racing on the same lockForUpdate() row) requires real
    // MySQL InnoDB gap-locking behavior — SQLite serializes all writes on a
    // single connection and cannot exercise that guarantee. Documented as a
    // known test-coverage gap (RESEARCH.md Pattern 1), not silently assumed.
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            ['description' => 'Tarpaulin, 3x5ft', 'type' => 'type_b'],
        ],
    ]);

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            ['description' => 'Sticker, A4', 'type' => 'type_b'],
        ],
    ]);

    expect(QueueEntry::orderBy('id')->pluck('queue_number')->all())->toBe([1, 2]);
});
