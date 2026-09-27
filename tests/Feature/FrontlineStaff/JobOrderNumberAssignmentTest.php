<?php

use App\Enums\AccountsReceivableStatus;
use App\Enums\PaymentStatus;
use App\Models\AccountsReceivable;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('submitting a visit with two job orders assigns each a distinct sequential JO-{year}-#### number', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.store'), [
        'customer_id' => $customer->id,
        'job_orders' => [
            ['description' => 'Tarpaulin, 3x5ft', 'type' => 'type_b'],
            ['description' => 'Sticker, A4', 'type' => 'type_b'],
        ],
    ]);

    $numbers = JobOrder::orderBy('id')->pluck('number');

    expect($numbers)->toHaveCount(2);
    $numbers->each(fn (?string $number) => expect($number)->toMatch('/^JO-\d{4}-\d{4}$/'));
    expect($numbers[0])->not->toBe($numbers[1]);
    expect((int) substr($numbers[1], -4))->toBe((int) substr($numbers[0], -4) + 1);
});

test('adding a job order to an existing visit continues the sequence from the highest existing number for that year', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();
    $year = JobOrder::currentNumberingYear();
    JobOrder::factory()->for($queueEntry)->create(['number' => "JO-{$year}-0005"]);

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Business Cards, 100pcs',
        'type' => 'type_b',
    ]);

    $newJobOrder = JobOrder::where('number', '!=', "JO-{$year}-0005")->firstOrFail();

    expect($newJobOrder->number)->toBe("JO-{$year}-0006");
});

test('adding a job order to an existing visit inserts inside an enclosing transaction so the number lock outlives the generator', function () {
    $staff = User::factory()->frontlineStaff()->create();
    $queueEntry = QueueEntry::factory()->create();

    $baselineLevel = DB::transactionLevel();
    $levelAtInsert = null;

    JobOrder::creating(function () use (&$levelAtInsert): void {
        $levelAtInsert = DB::transactionLevel();
    });

    $this->actingAs($staff)->post(route('frontline-staff.queue-entries.job-orders.store', $queueEntry), [
        'description' => 'Business Cards, 100pcs',
        'type' => 'type_b',
    ]);

    // nextNumberForYear() opens and commits its own transaction, releasing
    // its lockForUpdate() row/gap lock. Only an enclosing transaction keeps
    // that lock alive until the insert lands — visible here as one extra
    // nesting level at insert time. SQLite makes lockForUpdate() a no-op,
    // so the transaction boundary itself is the only assertable evidence.
    expect($levelAtInsert)->toBe($baselineLevel + 1);
});

test('the Cashier Dashboard response includes each job order\'s number', function () {
    $cashier = User::factory()->cashier()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create(['number' => 'JO-2026-0042']);

    $response = $this->actingAs($cashier)->get(route('cashier.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('jobOrders.0.id', $jobOrder->id)
        ->where('jobOrders.0.number', 'JO-2026-0042')
    );
});

test('the Admin Credit Requests response includes the job order\'s number', function () {
    $admin = User::factory()->admin()->create();
    $jobOrder = JobOrder::factory()->readyForProduction()->create([
        'number' => 'JO-2026-0099',
        'total_amount' => 1000,
        'payment_status' => PaymentStatus::CreditPendingApproval->value,
    ]);
    AccountsReceivable::factory()->for($jobOrder)->create([
        'balance' => 1000,
        'status' => AccountsReceivableStatus::PendingApproval->value,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.credit-requests.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('creditRequests.0.job_order.id', $jobOrder->id)
        ->where('creditRequests.0.job_order.number', 'JO-2026-0099')
    );
});
