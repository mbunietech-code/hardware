<?php

namespace App\Support;

class Money
{
    public static function round(float|int|string|null $value): float
    {
        return round((float) $value, 2);
    }

    public static function qty(float|int|string|null $value): float
    {
        return round((float) $value, 3);
    }

    public static function format(float|int|string|null $value, bool $withCurrency = true): string
    {
        $v = round((float) $value, 2);
        $formatted = number_format($v, fmod($v, 1.0) == 0.0 ? 0 : 2);

        // Non-breaking space keeps "TZS 5,000" on one line in tables.
        return $withCurrency ? Settings::get('currency', 'TZS')."\u{00A0}".$formatted : $formatted;
    }

    public static function formatQty(float|int|string|null $value): string
    {
        $v = (float) $value;

        return fmod($v, 1.0) == 0.0 ? number_format($v, 0) : rtrim(rtrim(number_format($v, 3), '0'), '.');
    }
}
