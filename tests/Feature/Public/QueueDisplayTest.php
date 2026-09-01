<?php

use App\Models\Customer;
use App\Models\QueueEntry;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('an unauthenticated visitor can view the public queue display', function () {
    $response = $this->get(route('queue-display'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('public/QueueDisplay'));
});

test('the queue display payload contains only id, queue_number, and status, never customer data', function () {
    $customer = Customer::factory()->create(['name' => 'Juan Dela Cruz']);
    QueueEntry::factory()->create([
        'customer_id' => $customer->id,
        'queue_date' => QueueEntry::currentBusinessDate(),
        'queue_number' => 1,
    ]);

    $response = $this->get(route('queue-display'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('public/QueueDisplay')
        ->has(
            'queueEntries.0',
            fn (Assert $entry) => $entry
                ->hasAll(['id', 'queue_number', 'status'])
                ->missing('customer_id')
                ->missing('customer')
                ->etc()
        ));

    expect($response->getContent())->not->toContain('Juan Dela Cruz');
});

test('a queue entry from a different business date is excluded from the display', function () {
    $customer = Customer::factory()->create();
    QueueEntry::factory()->create([
        'customer_id' => $customer->id,
        'queue_date' => Carbon::yesterday(),
        'queue_number' => 1,
    ]);

    $response = $this->get(route('queue-display'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('public/QueueDisplay')
        ->has('queueEntries', 0));
});

test('entries appear with their correct serving or done status', function () {
    $customer = Customer::factory()->create();
    $serving = QueueEntry::factory()->serving()->create([
        'customer_id' => $customer->id,
        'queue_date' => QueueEntry::currentBusinessDate(),
        'queue_number' => 1,
    ]);
    $done = QueueEntry::factory()->done()->create([
        'customer_id' => $customer->id,
        'queue_date' => QueueEntry::currentBusinessDate(),
        'queue_number' => 2,
    ]);

    $response = $this->get(route('queue-display'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('public/QueueDisplay')
        ->where('queueEntries.0.status', 'serving')
        ->where('queueEntries.0.id', $serving->id)
        ->where('queueEntries.1.status', 'done')
        ->where('queueEntries.1.id', $done->id));
});
