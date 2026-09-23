<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

/**
 * Formats and parses dates in the user's calendar (Jalali or Gregorian).
 * The database always stores Gregorian dates.
 */
class Dates
{
    public static function calendar(): string
    {
        return auth()->user()?->calendar ?? session('calendar', 'jalali');
    }

    public static function isJalali(): bool
    {
        return self::calendar() === 'jalali';
    }

    /** Display a date (and optionally time) in the user's calendar. */
    public static function format(?CarbonInterface $date, bool $withTime = false): string
    {
        if (! $date) {
            return '';
        }
        $date = $date->copy()->setTimezone(config('app.timezone'));

        if (self::isJalali()) {
            return Jalalian::fromCarbon(Carbon::instance($date))->format($withTime ? 'Y/m/d H:i' : 'Y/m/d');
        }

        return $date->format($withTime ? 'Y-m-d H:i' : 'Y-m-d');
    }

    public static function dateTime(?CarbonInterface $date): string
    {
        return self::format($date, true);
    }

    /** "3 hours ago", translated to the current app locale. */
    public static function ago(?CarbonInterface $date): string
    {
        return $date ? $date->locale(app()->getLocale())->diffForHumans() : '';
    }

    /** Value for a date <input> in the user's calendar. */
    public static function input(?CarbonInterface $date): string
    {
        return self::format($date);
    }

    /**
     * Parse a user-entered date. Accepts "1405/07/01", "1405-07-01" (Jalali, year < 1700)
     * and "2026-09-23" (Gregorian), with Persian or Arabic digits.
     */
    public static function parse(?string $value): ?Carbon
    {
        $value = trim(self::latinDigits((string) $value));
        if ($value === '') {
            return null;
        }
        if (! preg_match('#^(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})$#', $value, $m)) {
            return null;
        }
        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];

        try {
            if ($y < 1700) {
                if (! CalendarUtils::checkDate($y, $mo, $d)) {
                    return null;
                }
                [$gy, $gm, $gd] = CalendarUtils::toGregorian($y, $mo, $d);

                return Carbon::create($gy, $gm, $gd)->startOfDay();
            }

            return checkdate($mo, $d, $y) ? Carbon::create($y, $mo, $d)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function isValid(?string $value): bool
    {
        return trim((string) $value) === '' || self::parse($value) !== null;
    }

    public static function latinDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
