<?php

namespace App\Support;

/**
 * Tanzanian phone number normalization.
 *
 * The internal canonical format for the store is country-code format without
 * a leading plus or zero: 255XXXXXXXXX. Numbers entered as 07XXXXXXXXX,
 * 7XXXXXXXXX or +255XXXXXXXXX are all normalized to the same value so the
 * database stores a single canonical form.
 */
final class Phone
{
    public const PREFIX_TZ = '255';

    /**
     * Normalize a Tanzanian mobile number to 255XXXXXXXXX, or null if invalid.
     */
    public static function normalizeTanzanian(?string $number): ?string
    {
        if ($number === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $number);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, self::PREFIX_TZ)) {
            $candidate = $digits;
        } elseif (str_starts_with($digits, '0')) {
            $candidate = self::PREFIX_TZ.substr($digits, 1);
        } else {
            $candidate = self::PREFIX_TZ.$digits;
        }

        if (strlen($candidate) === 12 && preg_match('/^255(6|7)\d{8}$/', $candidate) === 1) {
            return $candidate;
        }

        return null;
    }
}