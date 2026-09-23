<?php

namespace App\Support;

/** Converts between "HH:MM" strings and minutes. */
class Duration
{
    public const PATTERN = '/^\d{1,4}:[0-5]\d$/';

    public static function toMinutes(?string $value): ?int
    {
        $value = trim(Dates::latinDigits((string) $value));
        if ($value === '') {
            return null;
        }
        if (! preg_match(self::PATTERN, $value)) {
            return null;
        }
        [$h, $m] = array_map('intval', explode(':', $value));

        return $h * 60 + $m;
    }

    public static function format(?int $minutes): string
    {
        if ($minutes === null) {
            return '';
        }

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
