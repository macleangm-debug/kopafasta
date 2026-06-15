<?php

use App\Models\ChargesFee;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Services\AffiliateService;
use App\Services\PromotionService;
use App\Services\ReferralService;

if (! function_exists('payment_gateway_is_dummy')) {
    function payment_gateway_is_dummy(): bool
    {
        return config('payments.gateway_mode', 'dummy') !== 'live';
    }
}

if (! function_exists('payment_channels_for_amount')) {
    /**
     * @return array{mobile_money_allowed: bool, channels: list<string>, threshold: int}
     */
    function payment_channels_for_amount(float|int $amount): array
    {
        $threshold = (int) config('payments.mobile_money_threshold', 3_000_000);
        $allowed = $amount <= $threshold;

        return [
            'mobile_money_allowed' => $allowed,
            'threshold'            => $threshold,
            'channels'             => $allowed
                ? array_merge(config('payments.channels.mobile_money', []), config('payments.channels.bank', []))
                : config('payments.channels.bank', ['Bank transfer']),
        ];
    }
}

if (! function_exists('quoted_application_fee')) {
    function quoted_application_fee(?Customer $customer, ?LoanProduct $product = null): int
    {
        $base = null;
        if ($product && (int) ($product->application_fee_amount ?? 0) > 0) {
            $base = (float) $product->application_fee_amount;
        } else {
            $base = (float) (optional(ChargesFee::where('code', 'APP_FEE')->where('is_active', true)->first())->amount ?? 0);
        }

        if ($base <= 0) {
            return 0;
        }

        if ($customer && app(ReferralService::class)->referrer($customer)) {
            return (int) round(app(ReferralService::class)->quoteFee($customer, $base, false, 'application_fee')['after_discount']);
        }

        if ($customer) {
            return (int) round(app(AffiliateService::class)->quoteFee($customer, $base, 'application_fee')['after_discount']);
        }

        return (int) round(app(PromotionService::class)->applyAfter('application_fee', $base)['after_discount']);
    }
}

if (! function_exists('quoted_valuation_fee')) {
    function quoted_valuation_fee(?Customer $customer): int
    {
        $base = (float) (optional(ChargesFee::where('code', 'VAL_FEE')->where('is_active', true)->first())->amount ?? 0);

        if ($base <= 0) {
            return 0;
        }

        if ($customer && app(ReferralService::class)->referrer($customer)) {
            return (int) round(app(ReferralService::class)->quoteFee($customer, $base, false, 'valuation_fee')['after_discount']);
        }

        if ($customer) {
            return (int) round(app(AffiliateService::class)->quoteFee($customer, $base, 'valuation_fee')['after_discount']);
        }

        return (int) round(app(PromotionService::class)->applyAfter('valuation_fee', $base)['after_discount']);
    }
}

if (! function_exists('quoted_application_fee_due')) {
    function quoted_application_fee_due(?Customer $customer, ?LoanProduct $product = null, ?LoanApplication $application = null): int
    {
        if (! $customer || ! $product) {
            return quoted_application_fee($customer, $product);
        }

        return app(\App\Services\ApplicationFeeCreditService::class)->additionalDue($customer, $product, $application);
    }
}
