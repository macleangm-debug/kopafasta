<?php

namespace App\Rules;

use App\Models\Setting;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MinimumAge implements ValidationRule
{
    public function __construct(
        protected ?int $minimumAge = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $minAge = $this->minimumAge ?? (int) (Setting::group('kyc')['min_age'] ?? 18);

        try {
            $dob = Carbon::parse($value);
        } catch (\Throwable) {
            $fail('Please enter a valid date of birth.');

            return;
        }

        if ($dob->age < $minAge) {
            $fail("You must be at least {$minAge} years old to use Kopafasta.");
        }
    }
}
