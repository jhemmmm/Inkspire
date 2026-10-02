<?php

use App\Enums\JobOrderStatus;
use App\Enums\PaymentStatus;
use App\Models\JobOrder;
use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * payment_status is not mass-assignable, so tests set it with forceFill.
 */
function productionOrder(JobOrderStatus $status, PaymentStatus $payment = PaymentStatus::Paid): JobOrder
{
    $jobOrder = JobOrder::factory()->create(['status' => $status->value]);
    $jobOrder->forceFill(['payment_status' => $payment])->save();

    return $jobOrder;
}

test('start, done and undo move a paid job order along the allowed transitions and log each one', function (string $action, JobOrderStatus $from, JobOrderStatus $to) {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = productionOrder($from);

    $response = $this->actingAs($staff)->patch(route("production-staff.job-orders.{$action}", $jobOrder));

    $response->assertRedirect();
    expect($jobOrder->fresh()->status)->toBe($to);

    expect(ProductionLog::query()->where('job_order_id', $jobOrder->id)->count())->toBe(1);
    $log = ProductionLog::query()->where('job_order_id', $jobOrder->id)->first();
    expect($log->from_status)->toBe($from);
    expect($log->to_status)->toBe($to);
    expect($log->recorded_by)->toBe($staff->id);
    expect($log->reason)->toBeNull();
})->with([
    'start: ForProduction to Printing' => ['start', JobOrderStatus::ForProduction, JobOrderStatus::Printing],
    'done: ForProduction to ReadyForPickup' => ['done', JobOrderStatus::ForProduction, JobOrderStatus::ReadyForPickup],
    'done: Printing to ReadyForPickup' => ['done', JobOrderStatus::Printing, JobOrderStatus::ReadyForPickup],
    'done: QualityCheck to ReadyForPickup' => ['done', JobOrderStatus::QualityCheck, JobOrderStatus::ReadyForPickup],
    'undo: ReadyForPickup to Printing' => ['undo', JobOrderStatus::ReadyForPickup, JobOrderStatus::Printing],
    'undo: Printing to ForProduction' => ['undo', JobOrderStatus::Printing, JobOrderStatus::ForProduction],
    'undo: QualityCheck to ForProduction' => ['undo', JobOrderStatus::QualityCheck, JobOrderStatus::ForProduction],
]);

test('a transition from the wrong stage is rejected and creates no log row', function (string $action, JobOrderStatus $from) {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = productionOrder($from);

    $response = $this->actingAs($staff)
        ->withHeaders(['X-Inertia' => 'true'])
        ->patch(route("production-staff.job-orders.{$action}", $jobOrder));

    $response->assertRedirect();
    $response->assertSessionHas(
        'inertia.flash_data',
        fn (array $flash) => $flash['toast']['type'] === 'error'
            && $flash['toast']['message'] === 'This job order already moved on. The board has refreshed — check its current stage before trying again.',
    );
    expect($jobOrder->fresh()->status)->toBe($from);
    expect(ProductionLog::query()->where('job_order_id', $jobOrder->id)->count())->toBe(0);
})->with([
    'start from Printing' => ['start', JobOrderStatus::Printing],
    'start from ReadyForPickup' => ['start', JobOrderStatus::ReadyForPickup],
    'done from ReadyForPickup' => ['done', JobOrderStatus::ReadyForPickup],
    'undo from ForProduction' => ['undo', JobOrderStatus::ForProduction],
]);

test('start and done reject an order that is not cleared for production', function (string $action, PaymentStatus $payment) {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = productionOrder(JobOrderStatus::ForProduction, $payment);

    $response = $this->actingAs($staff)
        ->withHeaders(['X-Inertia' => 'true'])
        ->patch(route("production-staff.job-orders.{$action}", $jobOrder));

    $response->assertRedirect();
    $response->assertSessionHas(
        'inertia.flash_data',
        fn (array $flash) => $flash['toast']['type'] === 'error'
            && $flash['toast']['message'] === 'Awaiting payment — send the customer to the Cashier.',
    );
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::ForProduction);
    expect(ProductionLog::query()->where('job_order_id', $jobOrder->id)->count())->toBe(0);
})->with([
    'start, unpaid' => ['start', PaymentStatus::Unpaid],
    'done, unpaid' => ['done', PaymentStatus::Unpaid],
    'start, pending confirmation' => ['start', PaymentStatus::PendingConfirmation],
    'done, credit pending approval' => ['done', PaymentStatus::CreditPendingApproval],
    'start, credit rejected' => ['start', PaymentStatus::CreditRejected],
]);

test('start and done accept a down-paid, paid or on-credit order', function (string $action, PaymentStatus $payment) {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = productionOrder(JobOrderStatus::ForProduction, $payment);

    $this->actingAs($staff)
        ->patch(route("production-staff.job-orders.{$action}", $jobOrder))
        ->assertRedirect();

    expect($jobOrder->fresh()->status)->not->toBe(JobOrderStatus::ForProduction);
    expect(ProductionLog::query()->where('job_order_id', $jobOrder->id)->count())->toBe(1);
})->with([
    'start, partially paid' => ['start', PaymentStatus::PartiallyPaid],
    'start, paid' => ['start', PaymentStatus::Paid],
    'start, on credit' => ['start', PaymentStatus::OnCredit],
    'done, partially paid' => ['done', PaymentStatus::PartiallyPaid],
    'done, paid' => ['done', PaymentStatus::Paid],
    'done, on credit' => ['done', PaymentStatus::OnCredit],
]);

test('undo is not payment-gated', function () {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = productionOrder(JobOrderStatus::Printing, PaymentStatus::Unpaid);

    $this->actingAs($staff)->patch(route('production-staff.job-orders.undo', $jobOrder))->assertRedirect();

    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::ForProduction);
});

test('a cancelled, released or off-board job order rejects every action', function (string $action) {
    $staff = User::factory()->productionStaff()->create();

    $cancelled = productionOrder(JobOrderStatus::Printing);
    $cancelled->forceFill(['cancelled_at' => now()])->save();

    $released = productionOrder(JobOrderStatus::ReadyForPickup);
    $released->forceFill(['released_at' => now()])->save();

    $offBoard = productionOrder(JobOrderStatus::InDesign);

    foreach ([$cancelled, $released, $offBoard] as $jobOrder) {
        $before = $jobOrder->fresh()->status;

        $this->actingAs($staff)
            ->patch(route("production-staff.job-orders.{$action}", $jobOrder))
            ->assertStatus(422);

        expect($jobOrder->fresh()->status)->toBe($before);
        expect(ProductionLog::query()->where('job_order_id', $jobOrder->id)->count())->toBe(0);
    }
})->with(['start', 'done', 'undo']);

test('a cancellation landing after route binding is still caught because every guard reads the locked row', function (string $action) {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = productionOrder(JobOrderStatus::Printing);

    Event::listen(TransactionBeginning::class, function () use ($jobOrder): void {
        DB::table('job_orders')->where('id', $jobOrder->id)->update(['cancelled_at' => now()]);
    });

    $response = $this->actingAs($staff)->patch(route("production-staff.job-orders.{$action}", $jobOrder));

    $response->assertStatus(422);
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::Printing);
    expect(ProductionLog::query()->where('job_order_id', $jobOrder->id)->count())->toBe(0);
})->with(['done', 'undo']);

test('a payment reversal landing after route binding is still caught by the locked payment check', function () {
    $staff = User::factory()->productionStaff()->create();
    $jobOrder = productionOrder(JobOrderStatus::ForProduction);

    Event::listen(TransactionBeginning::class, function () use ($jobOrder): void {
        DB::table('job_orders')->where('id', $jobOrder->id)->update(['payment_status' => PaymentStatus::Unpaid->value]);
    });

    $this->actingAs($staff)
        ->patch(route('production-staff.job-orders.start', $jobOrder))
        ->assertStatus(422);

    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::ForProduction);
    expect(ProductionLog::query()->where('job_order_id', $jobOrder->id)->count())->toBe(0);
});

test('a non-production-staff role is forbidden from start, done and undo', function (string $action) {
    $staff = User::factory()->frontlineStaff()->create();
    $jobOrder = productionOrder(JobOrderStatus::ForProduction);

    $this->actingAs($staff)->patch(route("production-staff.job-orders.{$action}", $jobOrder))->assertForbidden();
    expect($jobOrder->fresh()->status)->toBe(JobOrderStatus::ForProduction);
})->with(['start', 'done', 'undo']);
