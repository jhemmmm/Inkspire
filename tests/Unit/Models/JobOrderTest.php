<?php

use App\Models\JobOrder;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('outstandingBalance returns 0.0 when total_amount is null', function () {
    $jobOrder = JobOrder::factory()->create(['total_amount' => null]);

    expect($jobOrder->outstandingBalance())->toBe(0.0);
});

test('outstandingBalance returns the full total when there are no completed transactions', function () {
    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000]);

    expect($jobOrder->outstandingBalance())->toBe(1000.0);
});

test('outstandingBalance subtracts only Completed transactions', function () {
    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000]);

    Transaction::factory()->for($jobOrder)->create(['amount' => 400]);
    Transaction::factory()->for($jobOrder)->pendingConfirmation()->create(['amount' => 300]);

    expect($jobOrder->outstandingBalance())->toBe(600.0);
});

test('outstandingBalance returns the correct value when the transactions relation is eager-loaded', function () {
    $jobOrder = JobOrder::factory()->create(['total_amount' => 1000]);

    Transaction::factory()->for($jobOrder)->create(['amount' => 400]);

    $loaded = JobOrder::query()->with('transactions')->find($jobOrder->id);

    expect($loaded->relationLoaded('transactions'))->toBeTrue()
        ->and($loaded->outstandingBalance())->toBe(600.0);
});
