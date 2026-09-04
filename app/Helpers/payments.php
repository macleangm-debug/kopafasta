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
    /** Application fee plus valuation fee (asset-backed products only). Valuation × number of pledged assets. */
    function quoted_origination_fee(?Customer $customer, ?LoanProduct $product = null, int $assetCount = 1): int
    {
        $total = quoted_application_fee($customer, $product);

        if (product_includes_valuation_fee($product)) {
            $total += quoted_valuation_fee($customer, $assetCount);
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

        $base = app(\App\Services\Staging\StagingPaymentsService::class)
            ->effective('application_fee', $base, $product);

        $after = $base;

        if ($customer && app(ReferralService::class)->referrer($customer)) {
            $after = (float) app(ReferralService::class)->quoteFee($customer, $base, false, 'application_fee')['after_discount'];
        } elseif ($customer) {
            $after = (float) app(AffiliateService::class)->quoteFee($customer, $base, 'application_fee')['after_discount'];
        } else {
            $after = (float) app(PromotionService::class)->applyAfter('application_fee', $base)['after_discount'];
        }

        if ($customer) {
            return (int) round($after);
        }

        return (int) round($after);
    }
}

if (! function_exists('selected_collateral_count')) {
    function selected_collateral_count(array $form = []): int
    {
        $ids = collect((array) ($form['customer_asset_ids'] ?? []))
            ->push($form['customer_asset_id'] ?? null)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique();

        return max(1, $ids->count());
    }
}

if (! function_exists('quoted_valuation_fee')) {
    /** Per-asset valuation fee × $assetCount. Application fee is never multiplied. */
    function quoted_valuation_fee(?Customer $customer, int $assetCount = 1): int
    {
        $count = max(1, $assetCount);
        $quote = app(\App\Services\ValuationPricingService::class)->quote();
        $base = (float) $quote['borrower_amount'];

        if ($base <= 0) {
            $base = (float) (optional(ChargesFee::where('code', 'VAL_FEE')->where('is_active', true)->first())->amount ?? 0);
        }

        if ($base <= 0) {
            return 0;
        }

        $base = app(\App\Services\Staging\StagingPaymentsService::class)
            ->effective('valuation_fee', $base);

        $unit = match (true) {
            (bool) ($customer && app(ReferralService::class)->referrer($customer)) => (int) round(app(ReferralService::class)->quoteFee($customer, $base, false, 'valuation_fee')['after_discount']),
            (bool) $customer => (int) round(app(AffiliateService::class)->quoteFee($customer, $base, 'valuation_fee')['after_discount']),
            default => (int) round(app(PromotionService::class)->applyAfter('valuation_fee', $base)['after_discount']),
        };

        return $unit * $count;
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
