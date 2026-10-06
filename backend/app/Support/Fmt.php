<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Human-readable dates and times, the same on every screen (DECISIONS D-78):
 *   date 06 Oct 2026 · time 3:45 PM · dateTime 06 Oct 2026, 3:45 PM · ago 2 hours ago
 * Times are shown in the app timezone (APP_TIMEZONE, Asia/Kolkata).
 */
final class Fmt
{
    public static function date(CarbonInterface|string|null $value): string
    {
        return $value ? self::parse($value)->format('d M Y') : '—';
    }

    /** Accepts a time ("15:45" / "15:45:00") or a datetime. */
    public static function time(CarbonInterface|string|null $value): string
    {
        if (! $value) {
            return '—';
        }

        return (is_string($value) && preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value)
            ? Carbon::createFromFormat(strlen($value) === 5 ? 'H:i' : 'H:i:s', $value)
            : self::parse($value))->format('g:i A');
    }

    public static function dateTime(CarbonInterface|string|null $value): string
    {
        return $value ? self::parse($value)->format('d M Y, g:i A') : '—';
    }

    public static function ago(CarbonInterface|string|null $value): string
    {
        return $value ? self::parse($value)->diffForHumans() : '';
    }

    private static function parse(CarbonInterface|string $value): CarbonInterface
    {
        return ($value instanceof CarbonInterface ? $value : Carbon::parse($value))->timezone(config('app.timezone'));
    }
}
