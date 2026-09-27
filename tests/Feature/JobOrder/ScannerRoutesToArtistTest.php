<?php

use App\Actions\JobOrder\ClaimJobOrderForArtist;
use App\Actions\JobOrder\ValidateJobOrderFile;
use App\Enums\FileValidationOutcome;
use App\Enums\JobOrderStatus;
use App\Enums\SpecificationCategory;
use App\Models\JobOrder;
use App\Models\SpecificationOption;
use App\Models\SystemConfiguration;
use Illuminate\Http\UploadedFile;

/**
 * The file scanner's whole point is that a file too rough to print reaches an
 * artist instead of the press. These pin the two halves of that: the verdict,
 * and the pool the verdict is supposed to put the job order into.
 */
beforeEach(function () {
    SystemConfiguration::updateOrCreate(
        ['key' => 'large_format_minimum_dpi'],
        [
            'group' => 'business_rules',
            'value' => 100,
            'type' => 'integer',
            'label' => 'Large format minimum DPI',
        ],
    );

    SpecificationOption::factory()->create([
        'category' => SpecificationCategory::PrintSize,
        'label' => '3x6 ft Tarpaulin',
        'width_inches' => 36,
        'height_inches' => 72,
    ]);
});

test('a photo far too small for the printed size is sent to an artist, not rejected', function () {
    // 400px across a 6ft (72in) print works out to 5 DPI.
    $file = UploadedFile::fake()->image('logo.jpg', 400, 200);

    $result = (new ValidateJobOrderFile)($file, '3x6 ft Tarpaulin');

    expect($result['outcome'])->toBe(FileValidationOutcome::NeedsArtist);
    expect($result['reason'])->toContain('artist');
});

test('a file in a format the shop cannot open is rejected outright', function () {
    $file = UploadedFile::fake()->create('artwork.psd', 120);

    $result = (new ValidateJobOrderFile)($file, '3x6 ft Tarpaulin');

    expect($result['outcome'])->toBe(FileValidationOutcome::Rejected);
});

test('a file with enough pixels for the printed size passes straight through', function () {
    // 7200px across 72in is 100 DPI, exactly the threshold.
    $file = UploadedFile::fake()->image('artwork.jpg', 7200, 3600);

    $result = (new ValidateJobOrderFile)($file, '3x6 ft Tarpaulin');

    expect($result['outcome'])->toBe(FileValidationOutcome::Passed);
});

test('the job order a NeedsArtist verdict parks at intake is visible in the artist pool', function () {
    $jobOrder = JobOrder::factory()->typeA()->create([
        'status' => JobOrderStatus::Intake->value,
        'validation_failure_reason' => 'Below the 100 DPI minimum at this print size.',
    ]);

    expect(ClaimJobOrderForArtist::pool()->pluck('id')->all())->toContain($jobOrder->id);
});
