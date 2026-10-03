<?php

use App\Actions\JobOrder\CreateJobOrder;
use App\Enums\FileValidationOutcome;
use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\QueueEntry;
use Illuminate\Support\Facades\DB;

/**
 * @param  array{outcome: FileValidationOutcome|string, reason: ?string}  $fileCheck
 */
function createPreCheckedJobOrder(array $fileCheck): JobOrder
{
    $entry = QueueEntry::factory()->create();

    return DB::transaction(fn () => app(CreateJobOrder::class)($entry, [
        'description' => 'Tarpaulin, 3x5ft',
        'type' => 'type_a',
        'file_path' => 'job-orders/already-checked.pdf',
        'file_check' => $fileCheck,
    ]));
}

test('a pre-checked passing file goes straight to production', function () {
    $jobOrder = createPreCheckedJobOrder(['outcome' => FileValidationOutcome::Passed, 'reason' => null]);

    expect($jobOrder->fresh())
        ->status->toBe(JobOrderStatus::ForProduction)
        ->due_at->not->toBeNull()
        ->file_path->toBe('job-orders/already-checked.pdf');
});

test('a pre-checked file that needs an artist stays at intake with the reason', function () {
    $jobOrder = createPreCheckedJobOrder(['outcome' => 'needs_artist', 'reason' => 'Resolution is too low']);

    expect($jobOrder->fresh())
        ->status->toBe(JobOrderStatus::Intake)
        ->validation_failure_reason->toBe('Resolution is too low');
});
