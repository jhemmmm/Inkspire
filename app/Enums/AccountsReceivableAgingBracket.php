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

    /**
     * The collection letter body copied for this bracket (D-12), literal
     * `07-UI-SPEC.md` Copywriting Contract text -- `{due date}` and `{n}`
     * remain as literal placeholder tokens for the printable letter page to
     * interpolate at render time. `SixtyOneToNinety` and `NinetyPlus` share
     * the same final-notice body, per D-12. Never persisted anywhere.
     *
     * `Current` is unreachable in practice -- `CollectionLetterController`
     * guards against ever calling this on a not-yet-due entry -- but the
     * `match` must still be exhaustive, so it throws rather than silently
     * returning an empty string for what would be a controller bug.
     */
    public function letterBody(): string
    {
        return match ($this) {
            self::Current => throw new \LogicException('A Current entry has no collection letter body.'),
            self::OneToFifteen => 'This is a friendly reminder that the balance below became due on {due date} and is still open as of today. If you have already sent your payment, thank you — please disregard this notice. Otherwise, we would appreciate settlement at your earliest convenience.',
            self::SixteenToThirty => 'Our records show the balance below has been outstanding for {n} days past its due date of {due date}. We ask that you settle this amount within seven (7) days of this notice. If there is a problem with this account, please contact us so we can work it out with you.',
            self::ThirtyOneToSixty => 'The balance below is now {n} days past its due date of {due date}. We are formally requesting full settlement within seven (7) days of this notice. Continued non-payment will affect your eligibility for credit terms with us on future orders.',
            self::SixtyOneToNinety, self::NinetyPlus => 'This is a final notice. The balance below is {n} days past its due date of {due date} and remains unsettled despite our earlier reminders. Please settle it in full within seven (7) days of this notice. If we do not hear from you, this account will be endorsed for collection and may be written off as a loss, which ends your credit terms with us.',
        };
    }
}
