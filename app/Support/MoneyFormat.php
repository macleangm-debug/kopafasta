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

    /**
     * Compact KPI display: 1,500 → 1.5K · 50,000 → 50K · 1,250,000 → 1.25M.
     * Keep full figures on receipts, tables, PDFs and payment screens.
     */
    public static function compact(mixed $value): string
    {
        $amount = self::toNumber($value);
        $sign = $amount < 0 ? '−' : '';
        $abs = abs($amount);

        if ($abs < 1000) {
            return $sign.self::format($abs, 0);
        }

        if ($abs < 1_000_000) {
            return $sign.self::compactUnit($abs / 1000, 'K');
        }

        if ($abs < 1_000_000_000) {
            return $sign.self::compactUnit($abs / 1_000_000, 'M');
        }

        return $sign.self::compactUnit($abs / 1_000_000_000, 'B');
    }

    public static function spoken(mixed $value, ?string $locale = null): string
    {
        $amount = abs(self::toNumber($value));
        $locale = ($locale ?? app()->getLocale()) === 'sw' ? 'sw' : 'en';
        if ($amount < 1000) {
            return '';
        }

        if ($amount < 1_000_000) {
            $n = self::trimUnit($amount / 1000);

            return $locale === 'sw' ? "elfu {$n}" : "{$n} thousand";
        }

        if ($amount < 1_000_000_000) {
            $n = self::trimUnit($amount / 1_000_000);

            return $locale === 'sw' ? "milioni {$n}" : "{$n} million";
        }

        $n = self::trimUnit($amount / 1_000_000_000);

        return $locale === 'sw' ? "bilioni {$n}" : "{$n} billion";
    }

    private static function compactUnit(float $value, string $suffix): string
    {
        return self::trimUnit($value).$suffix;
    }

    private static function trimUnit(float $value): string
    {
        $formatted = number_format($value, $value >= 10 ? 0 : 2, self::decimalSeparator(), '');

        if (str_contains($formatted, self::decimalSeparator())) {
            $formatted = rtrim(rtrim($formatted, '0'), self::decimalSeparator());
        }

        return $formatted;
    }
}
