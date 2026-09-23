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
        $amount = (float) $amount;
        $decimals = floor($amount) == $amount ? 0 : 2;
        $text = number_format($amount, $decimals);

        return $currency ? $text.' '.__('app.currency.'.$currency) : $text;
    }

    /** "1,250,000" or "۱٬۲۵۰٬۰۰۰" → "1250000". */
    public static function parse(?string $value): ?string
    {
        $value = str_replace([',', '٬', ' '], '', Dates::latinDigits((string) $value));

        return $value === '' ? null : $value;
    }
}
