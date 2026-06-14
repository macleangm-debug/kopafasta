<?php

namespace App\Services;

/**
 * Backwards-compatible facade — credit policy reads country rules via CountrySettingsService.
 */
class CountryCreditSettingsService
{
    public function __construct(
        private readonly CountrySettingsService $countries,
    ) {}

    public function defaultCountryCode(): string
    {
        return $this->countries->defaultCountryCode();
    }

    public function countryName(?string $code = null): string
    {
        return $this->countries->countryName($code);
    }

    public function repaymentRatio(?string $countryCode = null): float
    {
        return $this->countries->repaymentRatio($countryCode);
    }

    public function crbFreshnessDays(?string $countryCode = null): int
    {
        return $this->countries->crbFreshnessDays($countryCode);
    }

    public function kycFreshnessDays(?string $countryCode = null): int
    {
        return $this->countries->kycFreshnessDays($countryCode);
    }

    public function guarantorRequired(?string $countryCode = null): bool
    {
        return $this->countries->guarantorRequired($countryCode);
    }

    /** @return array<string, mixed> */
    public function summary(?string $countryCode = null): array
    {
        return $this->countries->summary($countryCode);
    }
}
