<?php

namespace App\Actions\JobOrder;

use App\Models\SystemConfiguration;
use Illuminate\Http\UploadedFile;

class ValidateJobOrderFile
{
    /**
     * Validate a Type A job order's attached file against the configured
     * DPI/format/size thresholds (D-01/D-02/D-03).
     *
     * Raster-only DPI validation (`jpg`/`png`) via PHP's built-in
     * `getimagesize()`/`exif_read_data()` — no image-processing dependency,
     * per D-01. Vector formats (`pdf`/`ai`/`eps`) skip the DPI check
     * entirely and are validated on format + size only.
     *
     * @return array{passed: bool, reason: ?string}
     */
    public function __invoke(UploadedFile $file): array
    {
        $acceptedFormats = SystemConfiguration::getArray('accepted_file_formats', ['pdf', 'ai', 'eps', 'jpg', 'png']);
        $maxFileSizeMb = SystemConfiguration::getInt('max_file_size_mb', 50);
        $dpiThreshold = SystemConfiguration::getInt('dpi_threshold_minimum', 300);

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, $acceptedFormats, true)) {
            return [
                'passed' => false,
                'reason' => sprintf(
                    'File format ".%s" isn\'t accepted. Accepted formats: %s. Replace the file with a supported format.',
                    $extension,
                    implode(', ', $acceptedFormats),
                ),
            ];
        }

        $actualMb = round($file->getSize() / 1024 / 1024, 1);

        if ($actualMb > $maxFileSizeMb) {
            return [
                'passed' => false,
                'reason' => sprintf(
                    'File is %sMB, which exceeds the %sMB limit. Replace the file with a smaller version.',
                    $actualMb,
                    $maxFileSizeMb,
                ),
            ];
        }

        if (in_array($extension, ['jpg', 'png'], true)) {
            // Error-suppressed: malformed image content must never throw an
            // uncaught exception here (T-03-03 DoS mitigation).
            @getimagesize($file->getRealPath());
            $exifData = @exif_read_data($file->getRealPath());

            $dpi = self::resolveDpi($exifData === false ? null : $exifData);

            if ($dpi === null) {
                return [
                    'passed' => false,
                    'reason' => 'Unable to determine image resolution from file metadata. Save the file as a high-resolution JPEG or PDF and try again.',
                ];
            }

            if ($dpi < $dpiThreshold) {
                return [
                    'passed' => false,
                    'reason' => sprintf(
                        'Image resolution is %d DPI. Minimum required is %d DPI. Replace the file with a higher-resolution version.',
                        $dpi,
                        $dpiThreshold,
                    ),
                ];
            }
        }

        return ['passed' => true, 'reason' => null];
    }

    /**
     * Resolve a DPI integer from a raw `exif_read_data()` result array.
     *
     * Public and static so it can be tested directly with a hand-built
     * array, without needing a real EXIF-embedded fixture file.
     *
     * @param  array<string, mixed>|null  $exifData
     */
    public static function resolveDpi(?array $exifData): ?int
    {
        if ($exifData === null || ! isset($exifData['XResolution'])) {
            return null;
        }

        $parts = explode('/', (string) $exifData['XResolution']);

        if (count($parts) !== 2) {
            return null;
        }

        [$numerator, $denominator] = array_map('intval', $parts);

        if ($denominator === 0) {
            return null;
        }

        return (int) round($numerator / $denominator);
    }
}
