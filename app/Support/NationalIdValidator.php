<?php

namespace App\Support;

use App\Services\CountrySettingsService;

final class NationalIdValidator
{
    public static function isValid(?string $value, ?string $countryCode = null): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        $format = app(CountrySettingsService::class)->forCode($countryCode)['national_id_format'] ?? 'alphanumeric';

        return match ($format) {
            'nida_20'      => NidaNumber::isValid($value),
            'digits_8'     => (bool) preg_match('/^\d{8}$/', preg_replace('/\D/', '', $value)),
            'digits_16'    => (bool) preg_match('/^\d{16}$/', preg_replace('/\D/', '', $value)),
            'alphanumeric' => (bool) preg_match('/^[A-Za-z0-9\-]{5,30}$/', trim($value)),
            default        => strlen(trim($value)) >= 5,
        };
    }

    public static function format(?string $value, ?string $countryCode = null): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (! self::isValid($value, $countryCode)) {
            return null;
        }

        $groups = self::groups($countryCode);
        if ($groups !== []) {
            $digits = preg_replace('/\D/', '', (string) $value) ?? '';
            $parts = [];
            $pos = 0;
            foreach ($groups as $len) {
                $parts[] = substr($digits, $pos, $len);
                $pos += $len;
            }

            return implode('-', $parts);
        }

        return strtoupper(trim((string) $value));
    }

    public static function message(?string $countryCode = null): string
    {
        $settings = app(CountrySettingsService::class)->forCode($countryCode);
        $label = $settings['national_id_label'] ?? 'National ID';

        return match ($settings['national_id_format'] ?? 'alphanumeric') {
            'nida_20'      => "Enter a valid {$label} number (XXXXXXXX-XXXXX-XXXXX-XX).",
            'digits_8'     => "Enter a valid 8-digit {$label}.",
            'digits_16'    => "Enter a valid 16-digit {$label}.",
            default        => "Enter a valid {$label} (5–30 characters).",
        };
    }

    /** @return list<int> Digit groups for boxed entry (empty = free-text). */
    public static function groups(?string $countryCode = null): array
    {
        $settings = app(CountrySettingsService::class)->forCode($countryCode);
        $groups = $settings['national_id_groups'] ?? [];
        if (is_array($groups) && $groups !== []) {
            return array_values(array_filter(array_map('intval', $groups)));
        }

        return match ($settings['national_id_format'] ?? 'alphanumeric') {
            'nida_20'   => [8, 5, 5, 2],
            'digits_8'  => [8],
            'digits_16' => [4, 4, 4, 4],
            default     => [],
        };
    }

    public static function placeholder(?string $countryCode = null): string
    {
        $groups = self::groups($countryCode);
        if ($groups === []) {
            return app(CountrySettingsService::class)->forCode($countryCode)['national_id_label'] ?? 'National ID';
        }

        return collect($groups)->map(fn (int $len) => str_repeat('X', $len))->implode('-');
    }
}
