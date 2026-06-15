<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanApplicationAsset;
use App\Models\LoanProduct;

class AssetBackedLoanService
{
    public function isAssetBackedProduct(?LoanProduct $product): bool
    {
        return $product && strtoupper((string) $product->code) === 'AB';
    }

    public function isAssetBackedApplication(LoanApplication $application): bool
    {
        $application->loadMissing('product');

        return $this->isAssetBackedProduct($application->product);
    }

    public function asset(LoanApplication $application): ?LoanApplicationAsset
    {
        return LoanApplicationAsset::query()
            ->where('loan_application_id', $application->id)
            ->first();
    }

    public function valuationComplete(LoanApplication $application): bool
    {
        if (! $this->isAssetBackedApplication($application)) {
            return true;
        }

        $asset = $this->asset($application);

        return $asset
            && $asset->valuation_status === 'completed'
            && (float) $asset->forced_sale_value > 0;
    }

    public function maxOfferAmount(LoanApplication $application): ?float
    {
        $asset = $this->asset($application);
        if (! $asset || (float) $asset->max_loan_amount <= 0) {
            return null;
        }

        return (float) $asset->max_loan_amount;
    }

    /** @return list<string> */
    public function requiredDocumentCodes(): array
    {
        return [
            'asset_photo_front',
            'asset_photo_rear',
            'asset_photo_left',
            'asset_photo_right',
            'ownership_certificate',
            'insurance_certificate',
        ];
    }

    /** @return list<string> */
    public function assetTypeOptions(): array
    {
        return collect(config('repossession_charges.asset_types', []))
            ->mapWithKeys(fn (array $row, string $key) => [$key => $row['label'] ?? $key])
            ->all();
    }
}
