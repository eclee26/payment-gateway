<?php

namespace App\Support;

class Money
{
    /**
     * Currencies whose minor unit is the major unit (no decimals).
     */
    private const ZERO_DECIMAL = ['JPY'];

    /**
     * Format an amount in minor units for display, e.g. 1050 USD → "10.50 USD".
     */
    public static function format(int $amount, string $currency): string
    {
        $decimals = in_array($currency, self::ZERO_DECIMAL, true) ? 0 : 2;

        return number_format($amount / (10 ** $decimals), $decimals).' '.$currency;
    }
}
