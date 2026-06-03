<?php

namespace App\Support;

/**
 * System-wide numeric formatting (display: 1,000 · 50,000,000 · 1,500,000.50).
 * Use format_number() / format_money() in Blade and services; data-money-input in forms.
 */
final class MoneyFormat
{
    public static function toNumber(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = preg_replace('/[^\d.]/', '', (string) $value) ?? '';

        return $cleaned === '' ? 0.0 : (float) $cleaned;
    }

    public static function toInteger(mixed $value): int
    {
        return (int) round(self::toNumber($value));
    }

    public static function format(?float $amount, int $decimals = 0): string
    {
        if ($amount === null) {
            return '';
        }

        return number_format((float) $amount, $decimals, '.', ',');
    }

    public static function forInput(mixed $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value) && str_contains($value, ',')) {
            return (string) $value;
        }

        return self::format(self::toNumber($value), $decimals);
    }
}
