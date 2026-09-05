<?php

namespace App\Support;

/**
 * Converts the integer display format used by Vietnamese money inputs without
 * allowing locale punctuation or a numeric cast to hide malformed input.
 */
final class VietnameseInteger
{
    private const MAX_DIGITS = '999999999999999999';

    public static function normalize(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 && $value <= (int) self::MAX_DIGITS ? $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || preg_match('/\A(?:[0-9]+|[1-9][0-9]{0,2}(?:\.[0-9]{3})+)\z/', $value) !== 1) {
            return null;
        }

        $digits = str_replace('.', '', $value);
        if (strlen($digits) > strlen(self::MAX_DIGITS)
            || (strlen($digits) === strlen(self::MAX_DIGITS) && strcmp($digits, self::MAX_DIGITS) > 0)) {
            return null;
        }

        return (int) $digits;
    }

    public static function format(mixed $value): string
    {
        $normalized = self::normalize($value);

        return $normalized === null ? '' : number_format($normalized, 0, ',', '.');
    }
}
