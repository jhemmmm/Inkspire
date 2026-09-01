<?php

use App\Actions\JobOrder\ValidateJobOrderFile;
use Illuminate\Http\UploadedFile;

test('a pdf within size and format limits passes without a dpi check', function () {
    $file = UploadedFile::fake()->create('design.pdf', 500);

    $outcome = (new ValidateJobOrderFile)($file);

    expect($outcome['passed'])->toBeTrue();
    expect($outcome['reason'])->toBeNull();
});

test('a file with an unsupported extension fails validation', function () {
    $file = UploadedFile::fake()->create('design.xyz', 500);

    $outcome = (new ValidateJobOrderFile)($file);

    expect($outcome['passed'])->toBeFalse();
    expect($outcome['reason'])->toContain('isn\'t accepted');
});

test('a file over the max size limit fails validation', function () {
    $file = UploadedFile::fake()->create('design.pdf', 60000);

    $outcome = (new ValidateJobOrderFile)($file);

    expect($outcome['passed'])->toBeFalse();
    expect($outcome['reason'])->toContain('exceeds');
});

test('a jpg with no resolvable dpi metadata fails validation', function () {
    $file = UploadedFile::fake()->image('design.jpg', 100, 100);

    $outcome = (new ValidateJobOrderFile)($file);

    expect($outcome['passed'])->toBeFalse();
    expect($outcome['reason'])->toContain('Unable to determine');
});

test('resolveDpi parses a valid exif XResolution fraction into an integer', function () {
    $dpi = ValidateJobOrderFile::resolveDpi(['XResolution' => '300/1']);

    expect($dpi)->toBe(300);
});

test('resolveDpi returns null for missing or malformed XResolution', function () {
    expect(ValidateJobOrderFile::resolveDpi([]))->toBeNull();
    expect(ValidateJobOrderFile::resolveDpi(['XResolution' => '300/0']))->toBeNull();
});
