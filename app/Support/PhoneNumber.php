<?php

namespace App\Support;

use App\Services\CountrySettingsService;

class PhoneNumber
{
    /** @return array{prefix: string, local: string, full: string} */
    public static function split(?string $phone, ?string $defaultCountry = null): array
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        $countries = app(CountrySettingsService::class)->forRegistration();

        usort($countries, fn (array $a, array $b) => strlen(preg_replace('/\D+/', '', $b['prefix']) ?? '') <=> strlen(preg_replace('/\D+/', '', $a['prefix']) ?? ''));

        foreach ($countries as $country) {
            $prefixDigits = preg_replace('/\D+/', '', $country['prefix']) ?? '';
            if ($prefixDigits !== '' && str_starts_with($digits, $prefixDigits)) {
                return [
                    'prefix' => $country['prefix'],
                    'local'  => ltrim(substr($digits, strlen($prefixDigits)), '0'),
                    'full'   => $prefixDigits.ltrim(substr($digits, strlen($prefixDigits)), '0'),
                ];
            }
        }

        $default = app(CountrySettingsService::class)->forCode($defaultCountry);
        $prefixDigits = preg_replace('/\D+/', '', $default['phone_prefix']) ?? '';

        if ($digits !== '' && $prefixDigits !== '' && ! str_starts_with($digits, $prefixDigits)) {
            $local = ltrim($digits, '0');

            return [
                'prefix' => $default['phone_prefix'],
                'local'  => $local,
                'full'   => $prefixDigits.$local,
            ];
        }

        $local = $prefixDigits !== '' && str_starts_with($digits, $prefixDigits)
            ? ltrim(substr($digits, strlen($prefixDigits)), '0')
            : ltrim($digits, '0');

        return [
            'prefix' => $default['phone_prefix'],
            'local'  => $local,
            'full'   => $prefixDigits.$local,
        ];
    }

    public static function merge(?string $prefix, ?string $local): ?string
    {
        $prefixDigits = preg_replace('/\D+/', '', (string) $prefix) ?? '';
        $localDigits = preg_replace('/\D+/', '', (string) $local) ?? '';

        if ($localDigits === '') {
            return null;
        }

        return $prefixDigits.ltrim($localDigits, '0');
    }
}
