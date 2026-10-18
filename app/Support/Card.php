<?php

namespace App\Support;

/**
 * Helpers for raw card numbers. The PAN itself must never be persisted,
 * logged or returned; only its brand, last four digits and fingerprint.
 */
class Card
{
    /**
     * Strip spaces and dashes from a card number.
     */
    public static function normalize(string $number): string
    {
        return preg_replace('/[\s-]/', '', $number);
    }

    /**
     * Whether the number passes the Luhn checksum.
     */
    public static function passesLuhn(string $number): bool
    {
        if (! preg_match('/^\d{13,19}$/', $number)) {
            return false;
        }

        $sum = 0;
        $double = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($double) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }

    /**
     * Detect the card brand from its number.
     */
    public static function brand(string $number): string
    {
        return match (true) {
            str_starts_with($number, '4') => 'visa',
            (bool) preg_match('/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/', $number) => 'mastercard',
            (bool) preg_match('/^3[47]/', $number) => 'amex',
            (bool) preg_match('/^(6011|65)/', $number) => 'discover',
            default => 'unknown',
        };
    }

    /**
     * A keyed, non-reversible fingerprint that lets the same card be
     * recognised across payments without storing the number.
     */
    public static function fingerprint(string $number): string
    {
        $key = hash_hmac('sha256', 'card-fingerprint', (string) config('app.key'));

        return hash_hmac('sha256', $number, $key);
    }
}
