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
        $format = app(CountrySettingsService::class)->forCode($countryCode)['national_id_format'] ?? 'alphanumeric';

        if ($format === 'nida_20') {
            return NidaNumber::format($value);
        }

        if (! self::isValid($value, $countryCode)) {
            return null;
        }

        return match ($format) {
            'digits_8', 'digits_16' => preg_replace('/\D/', '', $value),
            default => strtoupper(trim($value)),
        };
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
}
