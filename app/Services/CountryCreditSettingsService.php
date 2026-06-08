<?php

namespace App\Services;

use App\Models\Setting;

class CountryCreditSettingsService
{
    public function defaultCountryCode(): string
    {
        return strtoupper((string) (Setting::get('country.default_code', 'TZ') ?: 'TZ'));
    }

    public function countryName(?string $code = null): string
    {
        $code = strtoupper($code ?: $this->defaultCountryCode());

        return match ($code) {
            'TZ' => 'Tanzania',
            'KE' => 'Kenya',
            'UG' => 'Uganda',
            default => $code,
        };
    }

    /** Maximum share of monthly income allowed for loan repayment (0.3333 = 33.33%). */
    public function repaymentRatio(?string $countryCode = null): float
    {
        $code = strtolower($countryCode ?: $this->defaultCountryCode());
        $val = Setting::get("country.{$code}.repayment_ratio")
            ?? Setting::get('credit.repayment_ratio')
            ?? Setting::get('credit.dsr_max');

        $ratio = is_numeric($val) ? (float) $val : 0.3333;

        return $ratio > 1 ? round($ratio / 100, 4) : round($ratio, 4);
    }

    public function crbFreshnessDays(?string $countryCode = null): int
    {
        $code = strtolower($countryCode ?: $this->defaultCountryCode());
        $val = Setting::get("country.{$code}.crb_freshness_days")
            ?? Setting::get('kyc.crb_freshness_days', 90);

        return max(30, min(365, (int) $val));
    }

    public function kycFreshnessDays(?string $countryCode = null): int
    {
        $code = strtolower($countryCode ?: $this->defaultCountryCode());
        $val = Setting::get("country.{$code}.kyc_freshness_days")
            ?? Setting::get('kyc.freshness_days', 90);

        return max(30, min(365, (int) $val));
    }

    public function guarantorRequired(?string $countryCode = null): bool
    {
        $code = strtolower($countryCode ?: $this->defaultCountryCode());
        $val = Setting::get("country.{$code}.guarantor_required");

        if ($val === null) {
            return true;
        }

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<string, mixed> */
    public function summary(?string $countryCode = null): array
    {
        $code = strtoupper($countryCode ?: $this->defaultCountryCode());

        return [
            'code'               => $code,
            'name'               => $this->countryName($code),
            'repayment_ratio'    => $this->repaymentRatio($code),
            'repayment_ratio_pct'=> round($this->repaymentRatio($code) * 100, 2),
            'crb_freshness_days' => $this->crbFreshnessDays($code),
            'kyc_freshness_days' => $this->kycFreshnessDays($code),
            'guarantor_required' => $this->guarantorRequired($code),
        ];
    }
}
