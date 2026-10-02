<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Tanzania (and locked-country) phone: prefix + exact national digit length.
 * Reuses PhoneNumber::canonicalDigits — does not invent a second formatter.
 */
class CanonicalPhone implements ValidationRule
{
    public function __construct(
        private ?string $countryCode = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $country = strtoupper((string) (
            $this->countryCode
            ?? request()->input('country')
            ?? session('country')
            ?? 'TZ'
        ));

        if (! is_string($value) || ! PhoneNumber::isValidCanonical($value, $country)) {
            $digits = PhoneNumber::nationalLengthFor($country);
            $fail(
                str_starts_with(app()->getLocale(), 'sw')
                    ? "Weka tarakimu {$digits} za nambari ya simu."
                    : "Enter the {$digits}-digit phone number."
            );
        }
    }
}
