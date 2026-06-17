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

    /** @return array<string, mixed> */
    public function forForm(): array
    {
        $messages = $this->messages();

        return [
            'code_prefix'                         => Setting::get('affiliates.code_prefix', config('affiliates.code_prefix')),
            'default_registration_discount_percent' => Setting::get('affiliates.default_registration_discount_percent', config('affiliates.default_registration_discount_percent')),
            'default_application_discount_percent'  => Setting::get('affiliates.default_application_discount_percent', config('affiliates.default_application_discount_percent')),
            'default_commission_percent'          => Setting::get('affiliates.default_commission_percent', config('affiliates.default_commission_percent')),
            'commission_calculation_base'         => $this->commissionCalculationBase(),
            'applies_to'                          => $this->appliesTo(),
            'message_share_template'              => $messages['share_template'],
            'message_referral_sms'                => $messages['referral_sms'],
            'message_verification_notice'         => $messages['verification_notice'],
            'message_welcome_partner'             => $messages['welcome_partner'],
            'require_kyc_for_verification'        => $this->requireKycForVerification(),
        ];
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
}
