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

    public static function format(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $split = self::split($phone);
        if ($split['local'] === '') {
            return null;
        }

        return trim($split['prefix'].' '.$split['local']);
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

    /**
     * Resolve a phone from a request field, preferring the visible *_local digits
     * (avoids browser autofill overwriting the hidden full MSISDN).
     */
    public static function fromRequest(\Illuminate\Http\Request $request, string $field, ?string $countryCode = null): ?string
    {
        $local = $request->input($field.'_local');
        $full = $request->input($field);
        $raw = filled($local) ? (string) $local : (filled($full) ? (string) $full : null);

        return self::normalizeForCountry($raw, $countryCode);
    }

    /**
     * Force a phone onto the given country's prefix (for post-registration locked prefix).
     * Strips a known country prefix / leading zeros from the input, then re-applies the locked country prefix.
     */
    public static function normalizeForCountry(?string $phone, ?string $countryCode): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        $country = app(CountrySettingsService::class)->forCode($countryCode);
        $prefixDigits = preg_replace('/\D+/', '', $country['phone_prefix'] ?? '') ?? '';

        $countries = app(CountrySettingsService::class)->forRegistration();
        usort($countries, fn (array $a, array $b) => strlen(preg_replace('/\D+/', '', $b['prefix']) ?? '') <=> strlen(preg_replace('/\D+/', '', $a['prefix']) ?? ''));

        // Strip every leading known country prefix (handles autofill / paste of full MSISDNs).
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($countries as $candidate) {
                $candidatePrefix = preg_replace('/\D+/', '', $candidate['prefix'] ?? '') ?? '';
                if ($candidatePrefix !== '' && str_starts_with($digits, $candidatePrefix) && strlen($digits) > strlen($candidatePrefix) + 5) {
                    $digits = substr($digits, strlen($candidatePrefix));
                    $changed = true;
                    break;
                }
            }
        }

        $local = ltrim($digits, '0');
        if ($local === '') {
            return null;
        }

        return $prefixDigits.$local;
    }
}
