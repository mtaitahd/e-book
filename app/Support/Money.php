<?php

namespace App\Support;

/**
 * Integer-arithmetic money helpers.
 *
 * All money math for the store is derived from decimal strings (the Laravel
 * `decimal:2` cast) and converted to integer cents before any addition.
 * Floating-point is never used for money calculations.
 */
final class Money
{
    /**
     * Convert a `1234.56` style string to an integer cent value.
     */
    public static function toCents(string $amount): int
    {
        $amount = trim($amount);
        $negative = str_starts_with($amount, '-');
        $amount = str_replace(',', '', ltrim($amount, '-'));

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        $whole = $whole === '' ? '0' : (string) (int) $whole;
        $fraction = str_pad(substr(str_pad($fraction, 2, '0'), 0, 2), 2, '0');

        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    /**
     * Convert an integer cent value back to a `1234.56` style string.
     */
    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
    }

    /**
     * Render an amount for display, e.g. "1,500.00 TZS".
     */
    public static function format(string $amount, string $currency = 'TZS'): string
    {
        $normalized = self::fromCents(self::toCents($amount));
        [$whole, $fraction] = explode('.', $normalized, 2);

        $sign = str_starts_with($normalized, '-') ? '-' : '';
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', ltrim($whole, '-'));

        return sprintf('%s%s.%s %s', $sign, $whole, $fraction, $currency);
    }

    /**
     * Whole-unit (no sub-unit currency) rendering, e.g. "25,000 TZS".
     *
     * TZS has no smallest-unit coinage in practice, so payment amounts are
     * integer whole shillings. This converts a decimal-2 amount string into
     * an integer whole amount (banker-style rounding is not used; we round
     * half up in integer arithmetic only).
     */
    public static function toWholeInt(string $amount): int
    {
        $cents = self::toCents($amount);

        return intdiv(abs($cents) + 50, 100) * ($cents < 0 ? -1 : 1);
    }

    /**
     * Render a whole integer amount with thousands separators, e.g. "25,000 TZS".
     */
    public static function formatWhole(int $amount, string $currency = 'TZS'): string
    {
        $sign = $amount < 0 ? '-' : '';
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', (string) abs($amount));

        return sprintf('%s%s %s', $sign, $whole, $currency);
    }
}