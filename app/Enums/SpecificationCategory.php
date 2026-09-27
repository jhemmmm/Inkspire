<?php

namespace App\Enums;

/**
 * The kinds of print specification the Admin maintains a catalog for.
 *
 * A `category` column on one table rather than a table per kind: adding
 * "Finishing" or "Ink Type" later is a seeder line, not a migration.
 */
enum SpecificationCategory: string
{
    case PrintSize = 'print_size';

    /**
     * The plural, human-facing name for this category.
     */
    public function label(): string
    {
        return match ($this) {
            self::PrintSize => 'Print Sizes',
        };
    }
}
