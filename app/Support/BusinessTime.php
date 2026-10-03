<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The shop's clock. Timestamps are stored in UTC; "today", report days,
 * due-today urgency and printed times are the shop's own
 * (`app.business_timezone`).
 */
final class BusinessTime
{
    public static function zone(): string
    {
        return config('app.business_timezone');
    }

    /**
     * Now, on the shop's clock.
     */
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zone());
    }

    /**
     * A stored moment on the shop's clock, for dates people read.
     */
    public static function local(CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)->setTimezone(self::zone());
    }

    /**
     * The first second of the shop day $day falls on, as a UTC instant: the
     * lower bound for a date filter over a stored timestamp.
     *
     * A query binding formats a Carbon in its own timezone, so a bound in
     * any other zone would be compared, as wall-clock text, against the UTC
     * values the database stores. Parse a date-only filter value in zone()
     * before passing it here, or it names the UTC day instead.
     *
     * @return ($day is null ? null : CarbonImmutable)
     */
    public static function utcStartOfDay(?CarbonInterface $day): ?CarbonImmutable
    {
        return $day === null ? null : self::local($day)->startOfDay()->utc();
    }

    /**
     * The last second of the shop day $day falls on, as a UTC instant: the
     * upper bound to utcStartOfDay()'s lower one.
     *
     * @return ($day is null ? null : CarbonImmutable)
     */
    public static function utcEndOfDay(?CarbonInterface $day): ?CarbonImmutable
    {
        return $day === null ? null : self::local($day)->endOfDay()->utc();
    }
}
