<?php

namespace App\Support;

class Money
{
    public const CURRENCIES = ['IRT', 'IRR', 'USD', 'EUR', 'AED'];

    public static function format(null|string|float|int $amount, ?string $currency = null): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }
        // Always a whole number, for every currency: 7,000,000 (never 7,000,000.00).
        $text = number_format(round((float) $amount));

        return $currency ? $text.' '.__('app.currency.'.$currency) : $text;
    }

    /** "1,250,000" or "۱٬۲۵۰٬۰۰۰" → "1250000". */
    public static function parse(?string $value): ?string
    {
        $value = str_replace([',', '٬', ' '], '', Dates::latinDigits((string) $value));

        return $value === '' ? null : $value;
    }
}
