<?php

namespace App\Rules;

use App\Support\NationalIdValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidNationalId implements ValidationRule
{
    public function __construct(
        private ?string $countryCode = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $country = $this->countryCode
            ?? request()->input('country')
            ?? request()->user()?->customer?->country_code;

        if (! NationalIdValidator::isValid(is_string($value) ? $value : null, is_string($country) ? $country : null)) {
            $fail(NationalIdValidator::message(is_string($country) ? $country : null));
        }
    }
}
