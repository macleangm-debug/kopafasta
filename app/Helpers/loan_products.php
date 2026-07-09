<?php

use App\Models\Customer;
use App\Models\LoanProduct;

if (! function_exists('loan_product_type_label')) {
    function loan_product_type_label(LoanProduct $product): string
    {
        $category = (string) ($product->category ?? '');

        return match ($category) {
            'salary_loan'   => __('borrower.apply.product_type.salary'),
            'business_loan' => __('borrower.apply.product_type.business'),
            'agriculture'   => __('borrower.apply.product_type.agriculture'),
            'asset_finance' => __('borrower.apply.product_type.asset'),
            'emergency'     => __('borrower.apply.product_type.emergency'),
            default         => ucfirst(str_replace('_', ' ', $category ?: __('borrower.apply.product_type.general'))),
        };
    }
}

if (! function_exists('loan_product_features')) {
    /** @return list<string> */
    function loan_product_features(LoanProduct $product): array
    {
        $features = [];

        if ($product->description) {
            $features[] = $product->description;
        }

        if ($product->requires_collateral) {
            $features[] = __('borrower.apply.product_features.collateral');
        }

        return $features;
    }
}

if (! function_exists('loan_product_uses_capital_partner')) {
    function loan_product_uses_capital_partner(LoanProduct $product): bool
    {
        if (is_marketplace_loan_product($product->code)) {
            return false;
        }

        $category = strtolower((string) ($product->category ?? ''));

        if (in_array($category, ['asset_finance', 'asset_lending'], true)) {
            return false;
        }

        return (bool) ($product->uses_capital_partner ?? true);
    }
}

if (! function_exists('loan_product_application_fee')) {
    function loan_product_application_fee(?Customer $customer, LoanProduct $product): int
    {
        return $customer
            ? (int) quoted_application_fee($customer, $product)
            : (int) ($product->application_fee_amount ?? 0);
    }
}

if (! function_exists('is_asset_backed_loan_product')) {
    function is_asset_backed_loan_product(?string $code): bool
    {
        return strtoupper((string) $code) === 'AB';
    }
}

if (! function_exists('is_group_loan_product')) {
    function is_group_loan_product(LoanProduct|string|null $productOrCode): bool
    {
        if ($productOrCode instanceof LoanProduct) {
            return app(\App\Services\GroupLendingService::class)->isGroupProduct($productOrCode);
        }

        $code = strtoupper((string) $productOrCode);

        return $code === 'GL';
    }
}

if (! function_exists('loan_product_wizard_payload')) {
    /** @return array<string, mixed> */
    function loan_product_wizard_payload(LoanProduct $product, ?Customer $customer = null): array
    {
        $rateService = app(\App\Services\DisplayedRateService::class);
        $policy = app(\App\Services\LoanPolicyService::class);

        $groups = app(\App\Services\GroupLendingService::class);

        return [
            'id'                => $product->id,
            'code'              => $product->code,
            'name'              => $product->localizedName(),
            'loan_type'         => loan_product_type_label($product),
            'features'          => loan_product_features($product),
            'application_fee'   => loan_product_application_fee($customer, $product),
            'application_fee_per_member' => $groups->isGroupProduct($product)
                ? loan_product_application_fee($customer, $product)
                : null,
            'rate'              => (float) $rateService->displayedMonthlyRate($product),
            'rate_label'        => $rateService->formatBorrowerRateRange($product),
            'rate_disclosure'   => $rateService->borrowerDisclosureLines($product, (float) $product->min_amount),
            'tiers'             => app(\App\Services\LoanRateTierService::class)->tiersForProduct($product),
            'min'               => (float) $product->min_amount,
            'max'               => (float) $product->max_amount,
            'tmin'              => (int) $product->tenure_min_months,
            'tmax'              => (int) $product->tenure_max_months,
            'tenure_options'    => $groups->tenureOptions($product),
            'desc'              => $product->description,
            'requires_guarantor' => (bool) $product->requires_guarantor,
            'guarantor_required_above' => (float) ($policy->settings()['guarantor_required_above'] ?? 0),
            'frequency'         => $groups->effectiveRepaymentCadence($product),
            'group_cadence_label' => $groups->groupRepaymentCadenceLabel($product),
            'is_group'          => is_group_loan_product($product),
        ];
    }
}
