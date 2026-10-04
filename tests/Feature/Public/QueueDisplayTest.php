<?php

use App\Enums\JobOrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * One job order on today's board, in the given state. `status` and
 * `payment_status` are forced because payment_status is not fillable.
 *
 * @param  array<string, mixed>  $state
 */
function queueDisplayJobOrder(QueueEntry $entry, array $state = []): JobOrder
{
    $jobOrder = JobOrder::factory()->create(['queue_entry_id' => $entry->id]);
    $jobOrder->forceFill($state)->save();

    return $jobOrder;
}

test('an unauthenticated visitor can view the public queue display', function () {
    $response = $this->get(route('queue-display'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('public/QueueDisplay'));
});

test('the queue display payload carries only the number and its stations, never who the visit or the artist is', function () {
    $customer = Customer::factory()->create(['name' => 'Juan Dela Cruz']);
    $artist = User::factory()->artist()->create(['name' => 'Maria Clara Santos']);
    $entry = QueueEntry::factory()->create(['customer_id' => $customer->id, 'queue_number' => 1]);
    $jobOrder = JobOrder::factory()->assignedTo($artist)->create([
        'queue_entry_id' => $entry->id,
        'description' => 'Wedding tarpaulin for the Dela Cruz family',
    ]);
    $jobOrder->forceFill(['total_amount' => 4321.09])->save();

    $response = $this->get(route('queue-display'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('queueEntries', 1)
        ->where('queueEntries.0', [
            'id' => $entry->id,
            'queue_prefix' => QueueEntry::REGULAR_PREFIX,
            'queue_number' => 1,
            'stations' => [['to' => 'artist', 'label' => $artist->artist_label]],
        ]));

    expect($response->getContent())
        ->not->toContain('Juan Dela Cruz')
        ->not->toContain('Maria Clara Santos')
        ->not->toContain('Wedding tarpaulin')
        ->not->toContain('4321.09')
        ->not->toContain($jobOrder->number);
});

test('a queue entry from a different business date is excluded from the display', function () {
    $entry = QueueEntry::factory()->create(['queue_date' => Carbon::yesterday(), 'queue_number' => 1]);
    queueDisplayJobOrder($entry);

    $this->get(route('queue-display'))->assertInertia(fn (Assert $page) => $page
        ->component('public/QueueDisplay')
        ->has('queueEntries', 0));
});

test('an online lane entry never appears on the public queue display', function () {
    queueDisplayJobOrder(QueueEntry::factory()->create(['queue_number' => 1]));
    queueDisplayJobOrder(QueueEntry::factory()->create([
        'queue_prefix' => QueueEntry::ONLINE_PREFIX,
        'queue_number' => 1,
    ]));

    $this->get(route('queue-display'))->assertInertia(fn (Assert $page) => $page
        ->has('queueEntries', 1)
        ->where('queueEntries.0.queue_prefix', QueueEntry::REGULAR_PREFIX));
});

test('a number still waiting for an artist is on the board with nowhere to go yet', function () {
    queueDisplayJobOrder(QueueEntry::factory()->create());

    $this->get(route('queue-display'))->assertInertia(fn (Assert $page) => $page
        ->has('queueEntries', 1)
        ->where('queueEntries.0.stations', []));
});

test('a number is sent to the artist who has its job order', function (JobOrderStatus $status) {
    $artist = User::factory()->artist()->create();
    $entry = QueueEntry::factory()->create();
    JobOrder::factory()->assignedTo($artist)->create(['queue_entry_id' => $entry->id, 'status' => $status->value]);

    $this->get(route('queue-display'))->assertInertia(fn (Assert $page) => $page
        ->where('queueEntries.0.stations', [['to' => 'artist', 'label' => $artist->artist_label]]));
})->with([
    'accepted' => JobOrderStatus::Assigned,
    'in consultation' => JobOrderStatus::InConsultation,
    'in design' => JobOrderStatus::InDesign,
    'pending review' => JobOrderStatus::PendingReview,
]);

test('a number with something to pay is sent to the cashier', function (JobOrderStatus $status, PaymentStatus $paymentStatus) {
    queueDisplayJobOrder(QueueEntry::factory()->create(), [
        'status' => $status->value,
        'payment_status' => $paymentStatus->value,
    ]);

    $this->get(route('queue-display'))->assertInertia(fn (Assert $page) => $page
        ->where('queueEntries.0.stations', [['to' => 'cashier', 'label' => 'Cashier']]));
})->with([
    'print-ready, unpaid' => [JobOrderStatus::ReadyForProduction, PaymentStatus::Unpaid],
    'design approved, unpaid' => [JobOrderStatus::DesignApproved, PaymentStatus::Unpaid],
    'for production, unpaid' => [JobOrderStatus::ForProduction, PaymentStatus::Unpaid],
    'for production, credit rejected' => [JobOrderStatus::ForProduction, PaymentStatus::CreditRejected],
    'printed, balance left' => [JobOrderStatus::ReadyForPickup, PaymentStatus::PartiallyPaid],
    'printed, unpaid' => [JobOrderStatus::ReadyForPickup, PaymentStatus::Unpaid],
]);

test('a printed number that is cleared for release is sent to frontline to claim', function (PaymentStatus $paymentStatus) {
    queueDisplayJobOrder(QueueEntry::factory()->create(), [
        'status' => JobOrderStatus::ReadyForPickup->value,
        'payment_status' => $paymentStatus->value,
    ]);

    $this->get(route('queue-display'))->assertInertia(fn (Assert $page) => $page
        ->where('queueEntries.0.stations', [['to' => 'frontline', 'label' => 'Frontline']]));
})->with([
    'paid' => PaymentStatus::Paid,
    'on credit' => PaymentStatus::OnCredit,
]);

test('a number with nothing left for the customer to do is off the board', function (array $state) {
    queueDisplayJobOrder(QueueEntry::factory()->create(), $state);

    $this->get(route('queue-display'))->assertInertia(fn (Assert $page) => $page->has('queueEntries', 0));
})->with([
    'in production on a down payment' => [['status' => JobOrderStatus::Printing->value, 'payment_status' => PaymentStatus::PartiallyPaid->value]],
    'paid and queued for printing' => [['status' => JobOrderStatus::ForProduction->value, 'payment_status' => PaymentStatus::Paid->value]],
    'paying online' => [['status' => JobOrderStatus::ForProduction->value, 'payment_status' => PaymentStatus::PendingConfirmation->value]],
    'credit awaiting admin' => [['status' => JobOrderStatus::ForProduction->value, 'payment_status' => PaymentStatus::CreditPendingApproval->value]],
    'rejected file' => [['status' => JobOrderStatus::ValidationFailed->value]],
    'released' => [['status' => JobOrderStatus::ReadyForPickup->value, 'payment_status' => PaymentStatus::Paid->value, 'released_at' => now()]],
    'cancelled while unpaid' => [['status' => JobOrderStatus::ForProduction->value, 'cancelled_at' => now()]],
]);

test('a visit with an order at an artist and another to pay lists both, artist first, each once', function () {
    $artist = User::factory()->artist()->create();
    $entry = QueueEntry::factory()->create();
    queueDisplayJobOrder($entry, ['status' => JobOrderStatus::ForProduction->value]);
    JobOrder::factory()->count(2)->assignedTo($artist)->create(['queue_entry_id' => $entry->id]);
    queueDisplayJobOrder($entry);

    $this->get(route('queue-display'))->assertInertia(fn (Assert $page) => $page
        ->has('queueEntries', 1)
        ->where('queueEntries.0.stations', [
            ['to' => 'artist', 'label' => $artist->artist_label],
            ['to' => 'cashier', 'label' => 'Cashier'],
        ]));
});

test('rush numbers lead the board, then numbers in order', function () {
    queueDisplayJobOrder(QueueEntry::factory()->create(['queue_number' => 2]));
    queueDisplayJobOrder(QueueEntry::factory()->create(['queue_number' => 1]));
    queueDisplayJobOrder(QueueEntry::factory()->create(['queue_prefix' => QueueEntry::RUSH_PREFIX, 'queue_number' => 5]));

    $this->get(route('queue-display'))->assertInertia(fn (Assert $page) => $page
        ->where('queueEntries.0.queue_prefix', QueueEntry::RUSH_PREFIX)
        ->where('queueEntries.1.queue_number', 1)
        ->where('queueEntries.2.queue_number', 2));
});
