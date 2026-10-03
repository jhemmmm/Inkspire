<?php

use App\Models\JobOrder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\Exceptions;

test('a key missing from a fillable list throws outside production', function () {
    expect(fn () => new JobOrder(['description' => 'Tarpaulin', 'tracking_token' => 'chosen-by-a-request']))
        ->toThrow(MassAssignmentException::class);
});

test('in production a key missing from a fillable list is reported and the rest still fills', function () {
    Exceptions::fake();
    $this->app->detectEnvironment(fn (): string => 'production');

    $jobOrder = new JobOrder(['description' => 'Tarpaulin', 'tracking_token' => 'chosen-by-a-request']);

    Exceptions::assertReported(MassAssignmentException::class);
    expect($jobOrder->getAttributes())->toBe(['description' => 'Tarpaulin']);
});
