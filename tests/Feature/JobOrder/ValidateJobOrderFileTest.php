<?php

use App\Actions\JobOrder\ValidateJobOrderFile;
use App\Enums\FileValidationOutcome;
use App\Models\SpecificationOption;
use Illuminate\Http\UploadedFile;

/**
 * A print size the shop has recorded real dimensions for, so effective DPI
 * can actually be computed against it.
 */
function tarpaulinThreeBySix(): SpecificationOption
{
    return SpecificationOption::factory()->printSize()->create([
        'label' => 'Tarpaulin 3x6ft',
        'width_inches' => 36,
        'height_inches' => 72,
    ]);
}

test('a pdf within size and format limits passes without a resolution check', function () {
    $file = UploadedFile::fake()->create('design.pdf', 500);

    $result = (new ValidateJobOrderFile)($file);

    expect($result['outcome'])->toBe(FileValidationOutcome::Passed);
    expect($result['reason'])->toBeNull();
});

test('an unsupported extension is rejected outright rather than sent to an artist', function () {
    $file = UploadedFile::fake()->create('design.xyz', 500);

    $result = (new ValidateJobOrderFile)($file);

    // Rejected, not NeedsArtist: no artist can turn this into artwork, only
    // the customer can supply a usable file.
    expect($result['outcome'])->toBe(FileValidationOutcome::Rejected);
    expect($result['reason'])->toContain('isn\'t accepted');
});

test('a file over the max size limit is rejected outright', function () {
    $file = UploadedFile::fake()->create('design.pdf', 60000);

    $result = (new ValidateJobOrderFile)($file);

    expect($result['outcome'])->toBe(FileValidationOutcome::Rejected);
    expect($result['reason'])->toContain('exceeds');
});

test('a missing file on a type a job order is rejected', function () {
    $result = (new ValidateJobOrderFile)(null);

    expect($result['outcome'])->toBe(FileValidationOutcome::Rejected);
    expect($result['reason'])->toContain('No file was attached');
});

test('an image too low-resolution for the ordered size goes to an artist, not the customer', function () {
    tarpaulinThreeBySix();

    // 720px across a 72in long edge is 10 effective DPI, far under the 100 floor.
    $file = UploadedFile::fake()->image('design.jpg', 720, 360);

    $result = (new ValidateJobOrderFile)($file, 'Tarpaulin 3x6ft');

    expect($result['outcome'])->toBe(FileValidationOutcome::NeedsArtist);
    expect($result['reason'])->toContain('10 DPI');
    expect($result['reason'])->toContain('720x360px');
});

test('an image with enough pixels for the ordered size passes', function () {
    tarpaulinThreeBySix();

    // 7200px across 72in is 100 effective DPI, exactly the threshold.
    $file = UploadedFile::fake()->image('design.jpg', 7200, 3600);

    $result = (new ValidateJobOrderFile)($file, 'Tarpaulin 3x6ft');

    expect($result['outcome'])->toBe(FileValidationOutcome::Passed);
});

test('the same file passes at a small size and fails at a large one', function () {
    tarpaulinThreeBySix();
    SpecificationOption::factory()->printSize()->create([
        'label' => 'Business Card (3.5x2in)',
        'width_inches' => 3.5,
        'height_inches' => 2,
    ]);

    // This is the whole point of measuring effective DPI rather than the
    // absolute figure in the file's metadata: 1000px is crisp on a card and
    // useless on a tarpaulin.
    $small = (new ValidateJobOrderFile)(
        UploadedFile::fake()->image('design.jpg', 1000, 600),
        'Business Card (3.5x2in)',
    );
    $large = (new ValidateJobOrderFile)(
        UploadedFile::fake()->image('design.jpg', 1000, 600),
        'Tarpaulin 3x6ft',
    );

    expect($small['outcome'])->toBe(FileValidationOutcome::Passed);
    expect($large['outcome'])->toBe(FileValidationOutcome::NeedsArtist);
});

test('a print size with no recorded dimensions skips the resolution check', function () {
    SpecificationOption::factory()->printSize()->create([
        'label' => 'Custom Size',
        'width_inches' => null,
        'height_inches' => null,
    ]);

    // Refusing a paying customer over a size nobody has measured would be
    // worse than letting the press catch it downstream.
    $result = (new ValidateJobOrderFile)(
        UploadedFile::fake()->image('design.jpg', 200, 200),
        'Custom Size',
    );

    expect($result['outcome'])->toBe(FileValidationOutcome::Passed);
});

test('an unrecognised print size label skips the resolution check', function () {
    $result = (new ValidateJobOrderFile)(
        UploadedFile::fake()->image('design.jpg', 200, 200),
        'Something The Owner Deleted',
    );

    expect($result['outcome'])->toBe(FileValidationOutcome::Passed);
});

test('a portrait file ordered at a landscape size is judged on its long edge', function () {
    tarpaulinThreeBySix();

    // Long edge to long edge: 7200px over 72in clears the bar. Comparing
    // width-to-width would have failed this on orientation alone.
    $result = (new ValidateJobOrderFile)(
        UploadedFile::fake()->image('design.jpg', 3600, 7200),
        'Tarpaulin 3x6ft',
    );

    expect($result['outcome'])->toBe(FileValidationOutcome::Passed);
});
