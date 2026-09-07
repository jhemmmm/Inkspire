<?php

namespace App\Enums;

/**
 * D-03's aging bands, counted in days past `due_at`.
 *
 * Deliberate departure from `AccountsReceivableStatus`'s plain-enum
 * convention: this enum needs a ranking mechanism (rank()) for ordinal
 * comparison and a reminder-triggering subset (reminderBearing()); its
 * sibling status enum needs neither.
 */
enum AccountsReceivableAgingBracket: string
{
    case Current = 'current';
    case OneToFifteen = 'one_to_fifteen';
    case SixteenToThirty = 'sixteen_to_thirty';
    case ThirtyOneToSixty = 'thirty_one_to_sixty';
    case SixtyOneToNinety = 'sixty_one_to_ninety';
    case NinetyPlus = 'ninety_plus';

    /**
     * Ordinal rank for comparing bracket progression (D-03).
     */
    public function rank(): int
    {
        return match ($this) {
            self::Current => 0,
            self::OneToFifteen => 1,
            self::SixteenToThirty => 2,
            self::ThirtyOneToSixty => 3,
            self::SixtyOneToNinety => 4,
            self::NinetyPlus => 5,
        };
    }

    /**
     * The four brackets that trigger a reminder email on entry (AR-02).
     * `SixtyOneToNinety` is display-only — no reminder fires on entry into
     * it, matching D-03's 1:1 mapping onto AR-02's four reminder triggers.
     *
     * @return array<int, self>
     */
    public static function reminderBearing(): array
    {
        return [self::OneToFifteen, self::SixteenToThirty, self::ThirtyOneToSixty, self::NinetyPlus];
    }
}
