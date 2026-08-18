<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanProduct;

/**
 * Which contract modules apply for this product / file.
 * New product types add a detector here so the same master template can
 * include the right clauses without copying the whole agreement.
 */
class LoanAgreementProductProfile
{
    /** @return array<string, mixed> */
    public function for(LoanApplication $application): array
    {
        $application->loadMissing(['product', 'collateralAsset']);
        $product = $application->product;
        $code = strtoupper((string) ($product?->code ?? ''));
        $category = strtolower((string) ($product?->category ?? ''));
        $collateral = $application->collateralAsset;

        $isGroup = app(GroupLendingService::class)->isGroupProduct($product);
        $isAsset = in_array($code, ['AB', 'AL'], true)
            || in_array($category, ['asset', 'asset_finance', 'asset_lending'], true)
            || (bool) ($product?->requires_collateral);
        $isSalary = in_array($category, ['salary_loan', 'salary', 'salary_advance'], true)
            || in_array($code, ['SA', 'AS', 'SAL', 'SALARY'], true);
        $movable = (bool) ($collateral?->isMovableAsset());
        $gpsRequired = (bool) ($collateral?->gps_required) || $movable;
        $gpsPostApproval = $isAsset || $gpsRequired;

        return [
            'product_code' => $code !== '' ? $code : null,
            'product_category' => $category !== '' ? $category : null,
            'is_group' => $isGroup,
            'is_asset' => $isAsset,
            'is_salary_advance' => $isSalary,
            'gps_post_approval' => $gpsPostApproval,
            'show_collateral' => $isAsset || $collateral !== null,
            'show_group' => $isGroup,
            'show_guarantor' => (bool) ($product?->requires_guarantor),
        ];
    }

    public function needsGpsPostApprovalFee(LoanApplication $application): bool
    {
        return (bool) ($this->for($application)['gps_post_approval'] ?? false);
    }

    public function isSalaryAdvanceProduct(?LoanProduct $product): bool
    {
        if (! $product) {
            return false;
        }

        $category = strtolower((string) ($product->category ?? ''));
        $code = strtoupper((string) ($product->code ?? ''));

        return in_array($category, ['salary_loan', 'salary', 'salary_advance'], true)
            || in_array($code, ['SA', 'AS', 'SAL', 'SALARY'], true);
    }
}
