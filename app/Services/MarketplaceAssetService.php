<?php

namespace App\Services;

use App\Models\MarketplaceAsset;
use App\Models\Vendor;
use Illuminate\Support\Str;

class MarketplaceAssetService
{
    public function syncDeposit(MarketplaceAsset $asset, ?Vendor $vendor = null): void
    {
        $vendor ??= $asset->vendor;
        $markup = (float) ($asset->deposit_markup_percent ?: $vendor?->deposit_markup_percent ?: $vendor?->markup_percent ?: 0);

        if ($markup !== (float) $asset->deposit_markup_percent) {
            $asset->deposit_markup_percent = $markup;
        }

        $asset->customer_deposit = $asset->computeCustomerDeposit();
    }

    /** @param array<string, mixed> $data */
    public function prepareForSave(array $data, ?MarketplaceAsset $existing = null): array
    {
        if (empty($data['slug']) && ! empty($data['title'])) {
            $data['slug'] = Str::slug($data['title']).'-'.Str::lower(Str::random(4));
        }

        if (! empty($data['vendor_id'])) {
            $vendor = Vendor::find($data['vendor_id']);
            if ($vendor) {
                $data['supplier_name'] = $data['supplier_name'] ?? $vendor->name;
                if (empty($data['deposit_markup_percent'])) {
                    $data['deposit_markup_percent'] = $vendor->deposit_markup_percent ?? $vendor->markup_percent ?? 0;
                }
            }
        }

        $asset = $existing ?? new MarketplaceAsset($data);
        $asset->fill($data);
        $this->syncDeposit($asset, $asset->vendor ?? null);
        $data['customer_deposit'] = $asset->customer_deposit;
        $data['deposit_markup_percent'] = $asset->deposit_markup_percent;

        return $data;
    }
}
