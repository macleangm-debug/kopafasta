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

        if ($product->requires_guarantor) {
            $features[] = __('borrower.apply.product_features.guarantor');
        }

        if ($product->requires_collateral) {
            $features[] = __('borrower.apply.product_features.collateral');
        }

        $features[] = __('borrower.apply.product_features.tenure_range', [
            'min' => $product->tenure_min_months,
            'max' => $product->tenure_max_months,
        ]);

        return $features;
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

if (! function_exists('loan_product_wizard_payload')) {
    /** @return array<string, mixed> */
    function loan_product_wizard_payload(LoanProduct $product, ?Customer $customer = null): array
    {
        $rateService = app(\App\Services\DisplayedRateService::class);

        return [
            'id'                => $product->id,
            'code'              => $product->code,
            'name'              => $product->name,
            'loan_type'         => loan_product_type_label($product),
            'features'          => loan_product_features($product),
            'application_fee'   => loan_product_application_fee($customer, $product),
            'rate'              => (float) $rateService->displayedMonthlyRate($product),
            'rate_label'        => $rateService->formatBorrowerRateRange($product),
            'tiers'             => app(\App\Services\LoanRateTierService::class)->tiersForProduct($product),
            'min'               => (float) $product->min_amount,
            'max'               => (float) $product->max_amount,
            'tmin'              => (int) $product->tenure_min_months,
            'tmax'              => (int) $product->tenure_max_months,
            'desc'              => $product->description,
            'requires_guarantor' => (bool) $product->requires_guarantor,
            'frequency'         => $product->repayment_cadence ?? 'weekly',
        ];
    }
}
