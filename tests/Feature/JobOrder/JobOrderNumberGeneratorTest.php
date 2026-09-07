<?php

use App\Models\JobOrder;

test('nextNumberForYear returns a JO-{year}-{seq} formatted number', function () {
    $number = JobOrder::nextNumberForYear(2026);

    expect($number)->toMatch('/^JO-\d{4}-\d{4}$/');
});

test('nextNumberForYear increments sequentially within the same year', function () {
    $first = JobOrder::nextNumberForYear(2026);
    JobOrder::factory()->create(['number' => $first]);

    $second = JobOrder::nextNumberForYear(2026);

    expect($first)->toBe('JO-2026-0001');
    expect($second)->toBe('JO-2026-0002');
});

test('nextNumberForYear keeps counting past the four-digit sequence instead of colliding', function () {
    JobOrder::factory()->create(['number' => 'JO-2026-9999']);

    $tenThousandth = JobOrder::nextNumberForYear(2026);
    JobOrder::factory()->create(['number' => $tenThousandth]);

    $tenThousandAndFirst = JobOrder::nextNumberForYear(2026);

    expect($tenThousandth)->toBe('JO-2026-10000');
    expect($tenThousandAndFirst)->toBe('JO-2026-10001');
});

test('nextNumberForYear maintains independent sequences per year', function () {
    $first2025 = JobOrder::nextNumberForYear(2025);
    JobOrder::factory()->create(['number' => $first2025]);
    $first2026 = JobOrder::nextNumberForYear(2026);
    JobOrder::factory()->create(['number' => $first2026]);

    $second2025 = JobOrder::nextNumberForYear(2025);
    $second2026 = JobOrder::nextNumberForYear(2026);

    expect($first2025)->toBe('JO-2025-0001');
    expect($second2025)->toBe('JO-2025-0002');
    expect($first2026)->toBe('JO-2026-0001');
    expect($second2026)->toBe('JO-2026-0002');
});
