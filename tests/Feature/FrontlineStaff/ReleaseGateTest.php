<?php

use App\Enums\JobOrderStatus;
use App\Enums\PaymentStatus;
use App\Models\JobOrder;
use App\Models\User;

test('releasing a paid job order sets released_at and succeeds', function () {
    $frontlineStaff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
        'total_amount' => 1000,
        'payment_status' => PaymentStatus::Paid->value,
    ]);

    $response = $this->actingAs($frontlineStaff)->post(route('frontline-staff.job-orders.release', $jobOrder));

    $response->assertRedirect();
    expect($jobOrder->fresh()->released_at)->not->toBeNull();
});

test('releasing an on_credit job order succeeds', function () {
    $frontlineStaff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
        'total_amount' => 1000,
        'payment_status' => PaymentStatus::OnCredit->value,
    ]);

    $response = $this->actingAs($frontlineStaff)->post(route('frontline-staff.job-orders.release', $jobOrder));

    $response->assertRedirect();
    expect($jobOrder->fresh()->released_at)->not->toBeNull();
});

test('releasing a job order with a blocked payment status is rejected and leaves released_at null', function (PaymentStatus $paymentStatus) {
    $frontlineStaff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
        'total_amount' => 1000,
        'payment_status' => $paymentStatus->value,
    ]);

    $response = $this->actingAs($frontlineStaff)->post(route('frontline-staff.job-orders.release', $jobOrder));

    $response->assertStatus(422);
    expect($jobOrder->fresh()->released_at)->toBeNull();
})->with([
    PaymentStatus::Unpaid,
    PaymentStatus::PartiallyPaid,
    PaymentStatus::PendingConfirmation,
    PaymentStatus::CreditPendingApproval,
    PaymentStatus::CreditRejected,
]);

test('a direct post to the release route for an unpaid job order is rejected identically to the ui-gated path, proving server-side enforcement', function () {
    $frontlineStaff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
        'total_amount' => 1000,
        'payment_status' => PaymentStatus::Unpaid->value,
    ]);

    $response = $this->actingAs($frontlineStaff)->post(route('frontline-staff.job-orders.release', $jobOrder));

    $response->assertStatus(422);
    expect($jobOrder->fresh()->released_at)->toBeNull();
});

test('a job order that has already been released cannot be released again', function () {
    $frontlineStaff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
        'total_amount' => 1000,
        'payment_status' => PaymentStatus::Paid->value,
    ]);
    $jobOrder->forceFill(['released_at' => now()])->save();

    $response = $this->actingAs($frontlineStaff)->post(route('frontline-staff.job-orders.release', $jobOrder));

    $response->assertStatus(422);
});

test('a cancelled job order cannot be released even if it was somehow marked paid (CR-03)', function () {
    $frontlineStaff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create([
        'status' => JobOrderStatus::ReadyForPickup->value,
        'total_amount' => 1000,
        'payment_status' => PaymentStatus::Paid->value,
    ]);
    $jobOrder->forceFill(['cancelled_at' => now()])->save();

    $response = $this->actingAs($frontlineStaff)->post(route('frontline-staff.job-orders.release', $jobOrder));

    $response->assertStatus(422);
    expect($jobOrder->fresh()->released_at)->toBeNull();
});

test('a fully paid job order that production has not finished cannot be released', function (JobOrderStatus $status) {
    $frontlineStaff = User::factory()->frontlineStaff()->create();
    $jobOrder = JobOrder::factory()->create([
        'status' => $status->value,
        'total_amount' => 1000,
        'payment_status' => PaymentStatus::Paid->value,
    ]);

    $response = $this->actingAs($frontlineStaff)->post(route('frontline-staff.job-orders.release', $jobOrder));

    // Stamping released_at here would drop the order off the Production
    // Board (whereNull('released_at')) and make /track report "Completed"
    // for an order that was never printed.
    $response->assertStatus(422);
    expect($jobOrder->fresh()->released_at)->toBeNull();
})->with([
    JobOrderStatus::ReadyForProduction,
    JobOrderStatus::ForProduction,
    JobOrderStatus::Printing,
    JobOrderStatus::QualityCheck,
]);
