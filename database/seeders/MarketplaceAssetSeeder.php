<?php

namespace Database\Seeders;

use App\Models\MarketplaceAsset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MarketplaceAssetSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('asset_marketplace.assets', []) as $asset) {
            MarketplaceAsset::updateOrCreate(
                ['slug' => $asset['id']],
                [
                    'category'               => $asset['category'],
                    'title'                  => $asset['title'],
                    'description'            => $asset['description'] ?? null,
                    'supplier_name'          => $asset['vendor'],
                    'asset_value'            => ($asset['deposit'] ?? 0) * 1.4,
                    'supplier_deposit'       => $asset['deposit'] ?? 0,
                    'deposit_markup_percent' => 10,
                    'customer_deposit'       => ($asset['deposit'] ?? 0) * 1.1,
                    'weekly_installment'     => $asset['weekly_installment'] ?? 0,
                    'max_tenure_months'      => 12,
                    'photos'                 => $asset['photos'] ?? [],
                    'is_active'              => true,
                ]
            );
        }
    }
}
