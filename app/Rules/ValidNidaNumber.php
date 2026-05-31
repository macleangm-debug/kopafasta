<?php

namespace App\Rules;

use App\Support\NidaNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidNidaNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! NidaNumber::isValid($value)) {
            $fail('Enter a valid NIDA number (XXXXXXXX-XXXXX-XXXXX-XX).');
        }
    }
}
