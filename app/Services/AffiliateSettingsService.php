<?php

namespace App\Services;

use App\Models\Setting;

class AffiliateSettingsService
{
    /** @return array<string, bool> */
    public function appliesTo(): array
    {
        $defaults = config('affiliates.applies_to', []);
        $stored = Setting::get('affiliates.applies_to');

        if (! is_array($stored)) {
            return $defaults;
        }

        return array_merge($defaults, $stored);
    }

    public function appliesToFeeType(string $feeType): bool
    {
        return (bool) ($this->appliesTo()[$feeType] ?? false);
    }

    public function commissionCalculationBase(): string
    {
        $base = (string) Setting::get('affiliates.commission_calculation_base', config('affiliates.commission_calculation_base', 'discounted_amount'));

        return in_array($base, ['original_amount', 'discounted_amount'], true) ? $base : 'discounted_amount';
    }

    public function commissionMode(): string
    {
        $mode = (string) Setting::get('affiliates.commission_mode', config('affiliates.commission_mode', 'percentage'));

        return in_array($mode, ['percentage', 'fixed', 'tiered', 'hybrid'], true) ? $mode : 'percentage';
    }

    /** @return array<string, float> */
    public function fixedCommissionAmounts(): array
    {
        $defaults = config('affiliates.fixed_commission_amounts', []);
        $stored = Setting::get('affiliates.fixed_commission_amounts');

        if (! is_array($stored)) {
            return $defaults;
        }

        return array_merge($defaults, $stored);
    }

    /** @return list<array<string, mixed>> */
    public function commissionTiers(): array
    {
        $defaults = config('affiliates.commission_tiers', []);
        $stored = Setting::get('affiliates.commission_tiers');

        if (! is_array($stored) || $stored === []) {
            return $defaults;
        }

        return $stored;
    }

    public function hybridFixedAmount(?\App\Models\Vendor $affiliate = null): float
    {
        return (float) Setting::get('affiliates.hybrid_fixed_amount', config('affiliates.hybrid_fixed_amount', 0));
    }

    public function hybridPercent(?\App\Models\Vendor $affiliate = null): float
    {
        return (float) Setting::get('affiliates.hybrid_percent', config('affiliates.hybrid_percent', 0));
    }

    /** @return array<string, mixed> */
    public function forForm(): array
    {
        $messages = $this->messages();

        return [
            'code_prefix'                         => Setting::get('affiliates.code_prefix', config('affiliates.code_prefix')),
            'default_registration_discount_percent' => Setting::get('affiliates.default_registration_discount_percent', config('affiliates.default_registration_discount_percent')),
            'default_application_discount_percent'  => Setting::get('affiliates.default_application_discount_percent', config('affiliates.default_application_discount_percent')),
            'default_plus_discount_percent'         => Setting::get('affiliates.default_plus_discount_percent', config('affiliates.default_plus_discount_percent', 10)),
            'default_commission_percent'          => Setting::get('affiliates.default_commission_percent', config('affiliates.default_commission_percent')),
            'commission_mode'                     => $this->commissionMode(),
            'fixed_commission_amounts'            => $this->fixedCommissionAmounts(),
            'commission_tiers'                    => $this->commissionTiers(),
            'hybrid_fixed_amount'               => $this->hybridFixedAmount(),
            'hybrid_percent'                    => $this->hybridPercent(),
            'evaluation'                        => $this->evaluationSettings(),
            'fraud'                             => $this->fraudSettings(),
            'commission_calculation_base'         => $this->commissionCalculationBase(),
            'applies_to'                          => $this->appliesTo(),
            'message_share_template'              => $messages['share_template'],
            'message_referral_sms'                => $messages['referral_sms'],
            'message_verification_notice'         => $messages['verification_notice'],
            'message_welcome_partner'             => $messages['welcome_partner'],
            'require_kyc_for_verification'        => $this->requireKycForVerification(),
            'minimum_payout_amount'               => Setting::get('affiliates.minimum_payout_amount', config('affiliates.minimum_payout_amount', 50000)),
            'membership'                          => AffiliateMembershipService::config(),
            'terms_body_en'                       => (string) Setting::get('affiliates.terms.body_en', ''),
            'terms_body_sw'                       => (string) Setting::get('affiliates.terms.body_sw', ''),
            'message_share_template_sw'           => $this->localizedMessage('share_template', 'sw'),
            'message_referral_sms_sw'             => $this->localizedMessage('referral_sms', 'sw'),
            'message_verification_notice_sw'      => $this->localizedMessage('verification_notice', 'sw'),
            'message_welcome_partner_sw'          => $this->localizedMessage('welcome_partner', 'sw'),
        ];
    }

    public function localizedMessage(string $key, string $locale): string
    {
        if ($locale === 'en') {
            return $this->messages()[$key] ?? '';
        }

        $stored = Setting::get('affiliates.messages_'.$locale);
        if (is_array($stored) && filled($stored[$key] ?? null)) {
            return (string) $stored[$key];
        }

        return $this->messages()[$key] ?? '';
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $defaults = config('affiliates.messages', []);
        $stored = Setting::get('affiliates.messages');

        if (! is_array($stored)) {
            return $defaults;
        }

        return array_merge($defaults, $stored);
    }

    public function message(string $key, array $replacements = []): string
    {
        $template = $this->messages()[$key] ?? '';

        foreach ($replacements as $placeholder => $value) {
            $template = str_replace('{'.$placeholder.'}', (string) $value, $template);
        }

        return $template;
    }

    public function requireKycForVerification(): bool
    {
        $stored = Setting::get('affiliates.require_kyc_for_verification');

        return $stored === null
            ? (bool) config('affiliates.require_kyc_for_verification', true)
            : (bool) $stored;
    }

    public function minimumPayoutAmount(): float
    {
        return (float) Setting::get(
            'affiliates.minimum_payout_amount',
            config('affiliates.minimum_payout_amount', 50000)
        );
    }

    /** @return array<string, mixed> */
    public function evaluationSettings(): array
    {
        $defaults = config('affiliates.evaluation', []);
        $stored = Setting::get('affiliates.evaluation');

        if (! is_array($stored)) {
            return $defaults;
        }

        $merged = array_merge($defaults, $stored);
        $merged['weights'] = array_merge($defaults['weights'] ?? [], $stored['weights'] ?? []);
        $merged['kpis'] = $this->mergeKpiCatalog($defaults['kpis'] ?? [], $stored['kpis'] ?? []);

        return $merged;
    }

    public function autoApplyActions(): bool
    {
        return (bool) ($this->evaluationSettings()['auto_apply_actions'] ?? true);
    }

    public function evaluationPeriodDays(): int
    {
        return max(1, (int) ($this->evaluationSettings()['period_days'] ?? 90));
    }

    public function minEventsForScoring(): int
    {
        return max(1, (int) ($this->evaluationSettings()['min_events_for_scoring'] ?? 3));
    }

    public function watchlistRiskScore(): float
    {
        return (float) ($this->evaluationSettings()['watchlist_risk_score'] ?? 60);
    }

    public function watchlistFraudScore(): float
    {
        return (float) ($this->evaluationSettings()['watchlist_fraud_score'] ?? 50);
    }

    public function suspendRiskScore(): float
    {
        return (float) ($this->evaluationSettings()['suspend_risk_score'] ?? 80);
    }

    public function suspendFraudScore(): float
    {
        return (float) ($this->evaluationSettings()['suspend_fraud_score'] ?? 75);
    }

    public function duplicateIpRegistrationThreshold(): int
    {
        return max(1, (int) ($this->evaluationSettings()['duplicate_ip_registration_threshold'] ?? 3));
    }

    public function lowConversionThreshold(): float
    {
        return (float) ($this->evaluationSettings()['low_conversion_threshold'] ?? 5);
    }

    public function highClickThreshold(): int
    {
        return max(1, (int) ($this->evaluationSettings()['high_click_threshold'] ?? 50));
    }

    public function volumeMinActiveDays(): int
    {
        return max(0, (int) ($this->evaluationSettings()['volume_min_active_days'] ?? 90));
    }

    public function volumeMissesBeforeNudge(): int
    {
        return max(1, (int) ($this->evaluationSettings()['volume_misses_before_nudge'] ?? 1));
    }

    public function volumeMissesBeforeWatchlist(): int
    {
        return max(1, (int) ($this->evaluationSettings()['volume_misses_before_watchlist'] ?? 2));
    }

    public function volumeMissesBeforeSuspend(): int
    {
        return max(1, (int) ($this->evaluationSettings()['volume_misses_before_suspend'] ?? 3));
    }

    /** @return array{volume: float, conversion: float, commission: float} */
    public function evaluationWeights(): array
    {
        $weights = $this->evaluationSettings()['weights'] ?? [];

        return [
            'volume'     => (float) ($weights['volume'] ?? 0.3),
            'conversion' => (float) ($weights['conversion'] ?? 0.4),
            'commission' => (float) ($weights['commission'] ?? 0.3),
        ];
    }

    /** @return array<string, mixed> */
    public function fraudSettings(): array
    {
        $defaults = config('affiliates.fraud', []);
        $stored = Setting::get('affiliates.fraud');

        return is_array($stored) ? array_merge($defaults, $stored) : $defaults;
    }

    public function mediumFraudScore(): int
    {
        return (int) ($this->fraudSettings()['medium_score'] ?? 20);
    }

    public function highFraudScore(): int
    {
        return (int) ($this->fraudSettings()['high_score'] ?? 50);
    }

    public function blockedFraudScore(): int
    {
        return (int) ($this->fraudSettings()['blocked_score'] ?? 80);
    }

    public function sharedPhoneCustomerThreshold(): int
    {
        return max(1, (int) ($this->fraudSettings()['shared_phone_customer_threshold'] ?? 2));
    }

    public function sharedDeviceRegistrationThreshold(): int
    {
        return max(1, (int) ($this->fraudSettings()['shared_device_registration_threshold'] ?? 2));
    }

    public function monthlyRegistrationTarget(): int
    {
        $eval = $this->evaluationSettings();
        if (array_key_exists('monthly_registration_target', $eval) && $eval['monthly_registration_target'] !== null) {
            return max(0, (int) $eval['monthly_registration_target']);
        }

        $kpis = $this->kpiCatalog();

        return max(0, (int) ($kpis['qualified_referrals']['target'] ?? 10));
    }

    public function policyVersion(): int
    {
        return max(1, (int) ($this->evaluationSettings()['policy_version'] ?? 1));
    }

    public function autoRecover(): bool
    {
        return (bool) ($this->evaluationSettings()['auto_recover'] ?? true);
    }

    /**
     * @return array<string, array{enabled: bool, target: float, weight: float}>
     */
    public function kpiCatalog(): array
    {
        $defaults = config('affiliates.evaluation.kpis', []);
        $stored = $this->evaluationSettings()['kpis'] ?? [];

        return $this->mergeKpiCatalog(is_array($defaults) ? $defaults : [], is_array($stored) ? $stored : []);
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $stored
     * @return array<string, array{enabled: bool, target: float, weight: float}>
     */
    private function mergeKpiCatalog(array $defaults, array $stored): array
    {
        $keys = array_unique(array_merge(array_keys($defaults), array_keys($stored)));
        $out = [];
        foreach ($keys as $key) {
            $base = is_array($defaults[$key] ?? null) ? $defaults[$key] : [];
            $overlay = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            $merged = array_merge($base, $overlay);
            $out[$key] = [
                'enabled' => (bool) ($merged['enabled'] ?? false),
                'target' => (float) ($merged['target'] ?? 0),
                'weight' => (float) ($merged['weight'] ?? 1),
            ];
        }

        return $out;
    }

    public function multiAccountDeviceThreshold(): int
    {
        return max(1, (int) ($this->fraudSettings()['multi_account_device_threshold'] ?? 2));
    }
}
