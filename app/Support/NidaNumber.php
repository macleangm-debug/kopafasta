<?php

namespace App\Support;

final class NidaNumber
{
    public static function isValid(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $digits = self::digits($value);

        return strlen($digits) === 20;
    }

    public static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    public static function format(?string $value): ?string
    {
        $digits = self::digits($value);

        if (strlen($digits) !== 20) {
            return null;
        }

        return sprintf(
            '%s-%s-%s-%s',
            substr($digits, 0, 8),
            substr($digits, 8, 5),
            substr($digits, 13, 5),
            substr($digits, 18, 2),
        );
    }

    /**
     * CRB Live Request uses IDENTIFIER_NUMBER without dashes.
     */
    public static function forCrb(?string $value): ?string
    {
        $digits = self::digits($value);

        return strlen($digits) === 20 ? $digits : null;
    }
}
