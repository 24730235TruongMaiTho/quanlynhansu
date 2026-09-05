<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

final class DisplayDateFormatter
{
    public static function format(mixed $value, string $invalidFallback = ''): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if (! is_string($value) || $value === '') {
            return $invalidFallback;
        }

        if (self::isDisplayDate($value)) {
            return $value;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
            $errors = CarbonImmutable::getLastErrors();
        } catch (Throwable) {
            return $invalidFallback;
        }

        if ($date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            return $invalidFallback;
        }

        return $date->format('d/m/Y');
    }

    private static function isDisplayDate(string $value): bool
    {
        if (preg_match('/\A[0-9]{2}\/[0-9]{2}\/[0-9]{4}\z/', $value) !== 1) {
            return false;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!d/m/Y', $value);
            $errors = CarbonImmutable::getLastErrors();
        } catch (Throwable) {
            return false;
        }

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('d/m/Y') === $value;
    }
}
