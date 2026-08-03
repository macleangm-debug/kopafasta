<?php

namespace App\Support;

use App\Models\Setting;

/**
 * System-wide numeric formatting (display: 1,000 · 50,000,000 · 1,500,000.50).
 * Use format_number() / format_money() in Blade and services; data-money-input in forms.
 */
final class MoneyFormat
{
    public static function thousandsSeparator(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        try {
            $value = (string) (Setting::get('company.thousands_separator') ?: ',');
        } catch (\Throwable) {
            $value = ',';
        }

        return $cached = in_array($value, [',', '.', ' '], true) ? $value : ',';
    }

    public static function decimalSeparator(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        try {
            $value = (string) (Setting::get('company.decimal_separator') ?: '.');
        } catch (\Throwable) {
            $value = '.';
        }

        $thousands = self::thousandsSeparator();

        if ($value === $thousands || ! in_array($value, ['.', ','], true)) {
            return $cached = ($thousands === '.' ? ',' : '.');
        }

        return $cached = $value;
    }

    public static function toNumber(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $raw = (string) $value;
        $decimal = self::decimalSeparator();
        $thousands = self::thousandsSeparator();

        // Normalize European-style 1.500.000,50 and US-style 1,500,000.50
        if ($decimal === ',' && str_contains($raw, ',')) {
            $raw = str_replace($thousands === ' ' ? ' ' : '.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } else {
            $raw = str_replace([$thousands, ' '], '', $raw);
        }

        $cleaned = preg_replace('/[^\d.-]/', '', $raw) ?? '';

        return $cleaned === '' || $cleaned === '-' || $cleaned === '.' ? 0.0 : (float) $cleaned;
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

        return number_format(
            (float) $amount,
            $decimals,
            self::decimalSeparator(),
            self::thousandsSeparator(),
        );
    }

    public static function forInput(mixed $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value) && preg_match('/[,\s]/', $value)) {
            return (string) $value;
        }

        return self::format(self::toNumber($value), $decimals);
    }
}
