<?php

namespace App\Support;

use App\Models\Setting;

/** Size limit of ticket and follow-up attachments: set by an admin, never above what PHP accepts. */
class Uploads
{
    public const DEFAULT_MB = 30;

    /** Most MB one file can have on this server (PHP upload_max_filesize and post_max_size). */
    public static function serverMaxMb(): int
    {
        $bytes = min(self::iniBytes('upload_max_filesize'), self::iniBytes('post_max_size'));

        return max(1, intdiv($bytes, 1024 * 1024));
    }

    /** The admin's setting, capped by the server limit. */
    public static function maxMb(): int
    {
        return min((int) Setting::get('attachment_max_mb', self::DEFAULT_MB), self::serverMaxMb());
    }

    /** Laravel "max" rule for one attachment (in KB). */
    public static function maxRule(): string
    {
        return 'max:'.(self::maxMb() * 1024);
    }

    private static function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));
        if ($value === '' || $value === '0' || $value === '-1') {
            return PHP_INT_MAX;   // no limit
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
