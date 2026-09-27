<?php

namespace App\Actions\JobOrder;

use App\Enums\FileValidationOutcome;
use App\Models\SpecificationOption;
use App\Models\SystemConfiguration;
use Illuminate\Http\UploadedFile;

class ValidateJobOrderFile
{
    /**
     * Validate a Type A job order's attached file (D-01/D-02/D-03).
     *
     * Sharpness is judged as **effective DPI** — pixels along an edge divided
     * by the printed length of that edge — not as the absolute DPI recorded in
     * the file's own metadata. Two reasons the old absolute test was the wrong
     * one for a large-format shop: most exported files report 72 DPI whatever
     * their real pixel count, and a DPI figure means nothing without a print
     * size. The same 2000px file is crisp on a business card and unusable on a
     * 3x6ft tarpaulin.
     *
     * The outcome is three-way, because "unusable" and "needs work" call for
     * different people — see FileValidationOutcome.
     *
     * Raster-only (`jpg`/`png`) via PHP's built-in `getimagesize()`; no image
     * library dependency, per D-01. Vector formats (`pdf`/`ai`/`eps`) are
     * resolution-independent and are checked on format and size alone. When
     * intake recorded an explicit `width_ft`/`height_ft` pair, that
     * customer-specified size takes precedence over the `print_size`
     * catalog preset lookup.
     *
     * @return array{outcome: FileValidationOutcome, reason: ?string}
     */
    public function __invoke(?UploadedFile $file, ?string $printSize = null, ?float $widthFt = null, ?float $heightFt = null): array
    {
        if ($file === null) {
            return [
                'outcome' => FileValidationOutcome::Rejected,
                'reason' => 'No file was attached. A Type A job order needs the customer\'s print-ready file.',
            ];
        }

        $acceptedFormats = self::acceptedFormats();
        $maxFileSizeMb = SystemConfiguration::getInt('max_file_size_mb', 50);

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, $acceptedFormats, true)) {
            return [
                'outcome' => FileValidationOutcome::Rejected,
                'reason' => sprintf(
                    'File format ".%s" isn\'t accepted. Accepted formats: %s. Ask the customer for a supported format.',
                    $extension,
                    implode(', ', $acceptedFormats),
                ),
            ];
        }

        $actualMb = round($file->getSize() / 1024 / 1024, 1);

        if ($actualMb > $maxFileSizeMb) {
            return [
                'outcome' => FileValidationOutcome::Rejected,
                'reason' => sprintf(
                    'File is %sMB, which exceeds the %sMB limit. Ask the customer for a smaller version.',
                    $actualMb,
                    $maxFileSizeMb,
                ),
            ];
        }

        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return ['outcome' => FileValidationOutcome::Passed, 'reason' => null];
        }

        return $this->checkEffectiveResolution($file, $printSize, $widthFt, $heightFt);
    }

    /**
     * The file extensions the shop accepts for a Type A file, lower-case and
     * without the leading dot. Shared with the intake screen so its file
     * picker offers exactly what this check will let through.
     *
     * @return list<string>
     */
    public static function acceptedFormats(): array
    {
        return array_values(array_map(
            fn (mixed $format): string => strtolower((string) $format),
            SystemConfiguration::getArray('accepted_file_formats', ['pdf', 'ai', 'eps', 'jpg', 'png']),
        ));
    }

    /**
     * Compare the image's pixel dimensions against the printed size it was
     * ordered at.
     *
     * Anything that cannot be measured passes rather than blocking the
     * counter: an unreadable image header, or a print size the Admin has not
     * recorded dimensions for. A false rejection sends a paying customer away;
     * a false pass is caught downstream at quality check.
     *
     * @return array{outcome: FileValidationOutcome, reason: ?string}
     */
    private function checkEffectiveResolution(UploadedFile $file, ?string $printSize, ?float $widthFt = null, ?float $heightFt = null): array
    {
        // Error-suppressed: malformed image content must never throw an
        // uncaught exception here (T-03-03 DoS mitigation).
        $dimensions = @getimagesize($file->getRealPath());

        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
            return [
                'outcome' => FileValidationOutcome::NeedsArtist,
                'reason' => 'The image dimensions could not be read from this file. An artist should open it and confirm it is usable.',
            ];
        }

        if ($widthFt !== null && $heightFt !== null && $widthFt > 0 && $heightFt > 0) {
            $size = [$widthFt * 12, $heightFt * 12];
            $sizeDescription = sprintf('%s × %s ft', self::formatFeet($widthFt), self::formatFeet($heightFt));
        } else {
            $size = $this->printSizeInInches($printSize);
            $sizeDescription = (string) $printSize;
        }

        if ($size === null) {
            return ['outcome' => FileValidationOutcome::Passed, 'reason' => null];
        }

        [$printWidth, $printHeight] = $size;

        // Compare the file's long edge against the print's long edge, so a
        // portrait file ordered at a landscape size is not failed for
        // orientation alone.
        $filePixels = max($dimensions[0], $dimensions[1]);
        $printInches = max($printWidth, $printHeight);

        $effectiveDpi = (int) floor($filePixels / $printInches);
        $threshold = SystemConfiguration::getInt('large_format_minimum_dpi', 100);

        if ($effectiveDpi < $threshold) {
            return [
                'outcome' => FileValidationOutcome::NeedsArtist,
                'reason' => sprintf(
                    'At %s the file works out to %d DPI, below the %d DPI minimum (it is %dx%dpx). An artist needs to upscale or rebuild the artwork before printing.',
                    $sizeDescription,
                    $effectiveDpi,
                    $threshold,
                    $dimensions[0],
                    $dimensions[1],
                ),
            ];
        }

        return ['outcome' => FileValidationOutcome::Passed, 'reason' => null];
    }

    /**
     * The printed dimensions, in inches, recorded for a print size label.
     *
     * @return array{0: float, 1: float}|null
     */
    private function printSizeInInches(?string $printSize): ?array
    {
        if ($printSize === null || $printSize === '') {
            return null;
        }

        $option = SpecificationOption::query()
            ->where('label', $printSize)
            ->whereNotNull('width_inches')
            ->whereNotNull('height_inches')
            ->first(['width_inches', 'height_inches']);

        return $option === null ? null : [$option->width_inches, $option->height_inches];
    }

    /**
     * Trim a feet measurement down to its significant digits for display —
     * `10.00` becomes `"10"`, `10.50` becomes `"10.5"`.
     */
    private static function formatFeet(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
