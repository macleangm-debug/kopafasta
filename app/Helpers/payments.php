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
        $mode = \App\Models\Setting::get('payments.gateway_mode');
        if ($mode === null || $mode === '') {
            $mode = config('payments.gateway_mode', 'dummy');
        }

        return $mode !== 'live';
    }
}

if (! function_exists('payment_mobile_money_threshold')) {
    function payment_mobile_money_threshold(): int
    {
        $stored = \App\Models\Setting::get('payments.mobile_money_threshold');
        if ($stored !== null && $stored !== '') {
            return max(0, (int) $stored);
        }

        return (int) config('payments.mobile_money_threshold', 3_000_000);
    }
}

if (! function_exists('payment_channels_for_amount')) {
    /**
     * @return array{mobile_money_allowed: bool, channels: list<string>, threshold: int}
     */
    function payment_channels_for_amount(float|int $amount): array
    {
        $threshold = payment_mobile_money_threshold();
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

if (! function_exists('product_includes_valuation_fee')) {
    function product_includes_valuation_fee(?LoanProduct $product): bool
    {
        return $product && strtoupper((string) $product->code) === 'AB';
    }
}

if (! function_exists('quoted_origination_fee')) {
    /** Application fee plus valuation fee (asset-backed products only). */
    function quoted_origination_fee(?Customer $customer, ?LoanProduct $product = null): int
    {
        $total = quoted_application_fee($customer, $product);

        if (product_includes_valuation_fee($product)) {
            $total += quoted_valuation_fee($customer);
        }

        return $total;
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

        $after = $base;

        if ($customer && app(ReferralService::class)->referrer($customer)) {
            $after = (float) app(ReferralService::class)->quoteFee($customer, $base, false, 'application_fee')['after_discount'];
        } elseif ($customer) {
            $after = (float) app(AffiliateService::class)->quoteFee($customer, $base, 'application_fee')['after_discount'];
        } else {
            $after = (float) app(PromotionService::class)->applyAfter('application_fee', $base)['after_discount'];
        }

        if ($customer) {
            $loyalty = app(\App\Services\LoyaltyRedemptionService::class)->discountForFee($customer, 'application_fee', $after);

            return max(0, (int) round($after - (float) $loyalty['discount']));
        }

        return (int) round($after);
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
