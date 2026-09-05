<?php

namespace App\Services;

use App\Models\Setting;

class CountrySettingsService
{
    /** @return list<string> */
    public function codes(): array
    {
        return array_keys(config('countries', []));
    }

    public function defaultCountryCode(): string
    {
        return strtoupper((string) (Setting::get('country.default_code', 'TZ') ?: 'TZ'));
    }

    /** @return array<string, mixed> */
    public function forCode(?string $code): array
    {
        $code = strtoupper($code ?: $this->defaultCountryCode());
        $defaults = config("countries.{$code}", config('countries.TZ', []));

        $prefix = strtolower($code);
        $stored = Setting::group("country.{$prefix}");

        $merged = array_merge($defaults, array_filter($stored, fn ($v) => $v !== null && $v !== ''));

        $ratio = $merged['repayment_ratio'] ?? 0.3333;
        if (is_numeric($ratio) && (float) $ratio > 1) {
            $ratio = round((float) $ratio / 100, 4);
        }

        $language = (string) ($merged['language'] ?? 'en');
        $contractLocale = (string) ($merged['contract_locale'] ?? $language);

        $emoji = (string) ($merged['emoji'] ?? '');
        if ($emoji === '') {
            $emoji = match ($code) {
                'TZ' => '🇹🇿', 'KE' => '🇰🇪', 'UG' => '🇺🇬', 'RW' => '🇷🇼', 'BI' => '🇧🇮', 'SS' => '🇸🇸',
                default => '🌍',
            };
        }

        $currency = strtoupper((string) ($merged['currency'] ?? 'TZS'));
        if ($this->tanzaniaOnlyMode()) {
            $currency = 'TZS';
        }

        return [
            'code'                => $code,
            'name'                => (string) ($merged['name'] ?? $code),
            'emoji'               => $emoji,
            'active'              => $this->isActiveForLending($code, filter_var($merged['active'] ?? true, FILTER_VALIDATE_BOOLEAN)),
            'language'            => in_array($language, ['en', 'sw'], true) ? $language : 'en',
            'currency'            => $currency,
            'timezone'            => (string) ($merged['timezone'] ?? 'Africa/Dar_es_Salaam'),
            'phone_prefix'        => (string) ($merged['phone_prefix'] ?? '+255'),
            'national_id_label'   => (string) ($merged['national_id_label'] ?? 'National ID'),
            'national_id_format'  => (string) ($merged['national_id_format'] ?? 'alphanumeric'),
            'national_id_groups'  => array_values(array_filter(array_map('intval', (array) ($merged['national_id_groups'] ?? [])))),
            'grace_period_days'   => max(0, (int) ($merged['grace_period_days'] ?? 2)),
            'repayment_ratio'     => round((float) $ratio, 4),
            'repayment_ratio_pct' => round((float) $ratio * 100, 2),
            'crb_freshness_days'  => max(30, min(365, (int) ($merged['crb_freshness_days'] ?? 90))),
            'kyc_freshness_days'  => max(30, min(365, (int) ($merged['kyc_freshness_days'] ?? 90))),
            'guarantor_required'  => filter_var($merged['guarantor_required'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'borrower_membership_allowed' => filter_var($merged['borrower_membership_allowed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'contract_locale'     => in_array($contractLocale, ['en', 'sw'], true) ? $contractLocale : 'en',
            'contract_template'   => $merged['contract_template'] ?? null,
            'loan_policy_notes'   => (string) ($merged['loan_policy_notes'] ?? ''),
        ];
    }

    /**
     * BoT digital lending LNO: licensed Tier 2 entity serves United Republic of Tanzania only.
     * Set DIGITAL_LENDING_TZ_ONLY=false to re-open other markets later.
     */
    public function tanzaniaOnlyMode(): bool
    {
        return filter_var(env('DIGITAL_LENDING_TZ_ONLY', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function isActiveForLending(string $code, bool $configuredActive): bool
    {
        if ($this->tanzaniaOnlyMode() && strtoupper($code) !== 'TZ') {
            return false;
        }

        return $configuredActive;
    }

    /** @return list<array<string, mixed>> */
    public function activeCountries(): array
    {
        return collect($this->codes())
            ->map(fn (string $code) => $this->forCode($code))
            ->filter(fn (array $c) => (bool) ($c['active'] ?? false))
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function forRegistration(): array
    {
        $rows = collect($this->codes())
            ->map(fn (string $code) => $this->forCode($code))
            ->map(fn (array $c) => [
                'code'    => $c['code'],
                'label'   => $c['name'],
                'prefix'  => $c['phone_prefix'],
                'emoji'   => $c['emoji'],
                'active'  => $c['active'],
                'note'    => $c['active'] ? 'Live now in East Africa.' : 'Opening soon.',
            ]);

        if ($this->tanzaniaOnlyMode()) {
            $rows = $rows->filter(fn (array $c) => (bool) ($c['active'] ?? false));
        }

        return $rows->values()->all();
    }

    public function defaultLocale(?string $countryCode = null): string
    {
        return $this->forCode($countryCode)['language'];
    }

    public function countryName(?string $code = null): string
    {
        return $this->forCode($code)['name'];
    }

    public function repaymentRatio(?string $countryCode = null): float
    {
        return $this->forCode($countryCode)['repayment_ratio'];
    }

    public function crbFreshnessDays(?string $countryCode = null): int
    {
        return $this->forCode($countryCode)['crb_freshness_days'];
    }

    public function kycFreshnessDays(?string $countryCode = null): int
    {
        return $this->forCode($countryCode)['kyc_freshness_days'];
    }

    public function guarantorRequired(?string $countryCode = null): bool
    {
        return $this->forCode($countryCode)['guarantor_required'];
    }

    public function gracePeriodDays(?string $countryCode = null): int
    {
        return $this->forCode($countryCode)['grace_period_days'];
    }

    /** @return array<string, mixed> */
    public function summary(?string $countryCode = null): array
    {
        $c = $this->forCode($countryCode);

        return [
            'code'                => $c['code'],
            'name'                => $c['name'],
            'repayment_ratio'     => $c['repayment_ratio'],
            'repayment_ratio_pct' => $c['repayment_ratio_pct'],
            'crb_freshness_days'  => $c['crb_freshness_days'],
            'kyc_freshness_days'  => $c['kyc_freshness_days'],
            'guarantor_required'  => $c['guarantor_required'],
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(string $code, array $data): void
    {
        $code = strtolower($code);
        $pairs = [];

        foreach ([
            'active', 'language', 'currency', 'timezone', 'phone_prefix',
            'national_id_label', 'national_id_format', 'grace_period_days',
            'crb_freshness_days', 'kyc_freshness_days', 'guarantor_required',
            'borrower_membership_allowed',
            'contract_locale', 'contract_template', 'loan_policy_notes',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                $pairs["country.{$code}.{$key}"] = $data[$key];
            }
        }

        if (isset($data['repayment_ratio_pct'])) {
            $pairs["country.{$code}.repayment_ratio"] = round((float) $data['repayment_ratio_pct'] / 100, 4);
        }

        Setting::setMany($pairs);
    }
}
