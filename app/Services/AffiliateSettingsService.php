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
        return [
            'code_prefix'                         => Setting::get('affiliates.code_prefix', config('affiliates.code_prefix')),
            'default_registration_discount_percent' => Setting::get('affiliates.default_registration_discount_percent', config('affiliates.default_registration_discount_percent')),
            'default_application_discount_percent'  => Setting::get('affiliates.default_application_discount_percent', config('affiliates.default_application_discount_percent')),
            'default_commission_percent'          => Setting::get('affiliates.default_commission_percent', config('affiliates.default_commission_percent')),
            'commission_calculation_base'         => $this->commissionCalculationBase(),
            'applies_to'                          => $this->appliesTo(),
        ];
    }
}
