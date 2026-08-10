<?php

namespace Database\Seeders;

use App\Models\MarketplaceAsset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MarketplaceAssetSeeder extends Seeder
{
    /** @return list<array<string, mixed>> */
    private function demoAssets(): array
    {
        return [
            [
                'slug' => 'toyota-hilux-2022',
                'category' => 'vehicle',
                'title' => 'Toyota Hilux Double Cab 2022',
                'description' => 'Reliable pickup for business and personal use. Low mileage, full service history.',
                'supplier_name' => 'AutoTrade Tanzania',
                'asset_value' => 45000000,
                'supplier_deposit' => 9000000,
                'weekly_installment' => 850000,
                'photos' => ['/images/marketplace/hilux.jpg'],
            ],
            [
                'slug' => 'isuzu-dmax-2021',
                'category' => 'vehicle',
                'title' => 'Isuzu D-Max 2021',
                'description' => 'Heavy-duty pickup ideal for logistics and construction support.',
                'supplier_name' => 'Prime Motors',
                'asset_value' => 52000000,
                'supplier_deposit' => 10400000,
                'weekly_installment' => 980000,
                'photos' => ['/images/marketplace/dmax.jpg'],
            ],
            [
                'slug' => 'tuk-tuk-passenger',
                'category' => 'vehicle',
                'title' => 'Passenger Bajaj Tuk-Tuk',
                'description' => 'Start earning with a ready-to-work passenger tuk-tuk.',
                'supplier_name' => 'City Mobility Co.',
                'asset_value' => 8500000,
                'supplier_deposit' => 1700000,
                'weekly_installment' => 165000,
                'photos' => ['/images/marketplace/bajaj.jpg'],
            ],
            [
                'slug' => 'motorcycle-delivery',
                'category' => 'vehicle',
                'title' => 'Delivery Motorcycle 150cc',
                'description' => 'Fuel-efficient bike for courier and last-mile delivery.',
                'supplier_name' => 'Rider Hub',
                'asset_value' => 3200000,
                'supplier_deposit' => 640000,
                'weekly_installment' => 62000,
                'photos' => ['/images/marketplace/motorcycle.jpg'],
            ],
            [
                'slug' => 'solar-home-system',
                'category' => 'equipment',
                'title' => 'Solar Home System 500W',
                'description' => 'Complete kit with panels, inverter, and battery backup.',
                'supplier_name' => 'SunPower East Africa',
                'asset_value' => 2800000,
                'supplier_deposit' => 560000,
                'weekly_installment' => 54000,
                'photos' => ['/images/marketplace/solar.jpg'],
            ],
            [
                'slug' => 'pos-terminal-bundle',
                'category' => 'equipment',
                'title' => 'POS Terminal Bundle',
                'description' => 'Smart POS with printer and starter merchant account setup.',
                'supplier_name' => 'PayTech Solutions',
                'asset_value' => 1200000,
                'supplier_deposit' => 240000,
                'weekly_installment' => 23000,
                'photos' => ['/images/marketplace/pos.jpg'],
            ],
            [
                'slug' => 'industrial-sewing-machine',
                'category' => 'equipment',
                'title' => 'Industrial Sewing Machine',
                'description' => 'Heavy-duty machine for tailoring and garment businesses.',
                'supplier_name' => 'Textile Works Ltd',
                'asset_value' => 1800000,
                'supplier_deposit' => 360000,
                'weekly_installment' => 35000,
                'photos' => ['/images/marketplace/industrial-sewing.jpg'],
            ],
            [
                'slug' => 'water-pump-set',
                'category' => 'equipment',
                'title' => 'Irrigation Water Pump Set',
                'description' => 'Pump, pipes, and fittings for smallholder farms.',
                'supplier_name' => 'AgriEquip TZ',
                'asset_value' => 950000,
                'supplier_deposit' => 190000,
                'weekly_installment' => 18500,
                'photos' => ['/images/marketplace/pump.jpg'],
            ],
            [
                'slug' => 'smartphone-business',
                'category' => 'electronics',
                'title' => 'Business Smartphone Bundle',
                'description' => 'Mid-range smartphone with protective case and data starter pack.',
                'supplier_name' => 'Mobile World',
                'asset_value' => 650000,
                'supplier_deposit' => 130000,
                'weekly_installment' => 12500,
                'photos' => ['/images/marketplace/phone.jpg'],
            ],
            [
                'slug' => 'laptop-business',
                'category' => 'electronics',
                'title' => 'Business Laptop 14"',
                'description' => 'Lightweight laptop for traders, agents, and freelancers.',
                'supplier_name' => 'TechPoint Africa',
                'asset_value' => 1400000,
                'supplier_deposit' => 280000,
                'weekly_installment' => 27000,
                'photos' => ['/images/marketplace/laptop.jpg'],
            ],
            [
                'slug' => 'refrigerated-display',
                'category' => 'equipment',
                'title' => 'Refrigerated Display Counter',
                'description' => 'Shop display fridge for beverages and fresh goods.',
                'supplier_name' => 'ColdChain Supplies',
                'asset_value' => 2200000,
                'supplier_deposit' => 440000,
                'weekly_installment' => 42000,
                'photos' => ['/images/marketplace/fridge.jpg'],
            ],
            [
                'slug' => 'mini-bus-14-seater',
                'category' => 'vehicle',
                'title' => '14-Seater Mini Bus',
                'description' => 'Passenger mini bus for route operators and school transport.',
                'supplier_name' => 'Fleet Masters',
                'asset_value' => 68000000,
                'supplier_deposit' => 13600000,
                'weekly_installment' => 1280000,
                'photos' => ['/images/marketplace/minibus.jpg'],
            ],
            [
                'slug' => 'commercial-sewing-machine',
                'category' => 'equipment',
                'title' => 'Commercial Sewing Machine',
                'description' => 'Reliable sewing machine for small workshops and tailoring shops.',
                'supplier_name' => 'Textile Works Ltd',
                'asset_value' => 950000,
                'supplier_deposit' => 190000,
                'weekly_installment' => 18500,
                'photos' => ['/images/marketplace/sewing.jpg'],
            ],
            [
                'slug' => 'maize-milling-plant',
                'category' => 'equipment',
                'title' => 'Maize Milling Plant',
                'description' => 'Complete milling setup for village and peri-urban flour businesses.',
                'supplier_name' => 'AgriEquip TZ',
                'asset_value' => 12500000,
                'supplier_deposit' => 2500000,
                'weekly_installment' => 240000,
                'photos' => ['/images/marketplace/mill.jpg'],
            ],
        ];
    }

    public function run(): void
    {
        $sources = array_merge(config('asset_marketplace.assets', []), $this->demoAssets());

        foreach ($sources as $asset) {
            $deposit = (float) ($asset['deposit'] ?? $asset['supplier_deposit'] ?? 0);
            $value = (float) ($asset['asset_value'] ?? ($deposit * 1.4));

            MarketplaceAsset::updateOrCreate(
                ['slug' => $asset['slug'] ?? $asset['id'] ?? Str::slug($asset['title'] ?? 'asset')],
                [
                    'category'               => $asset['category'] ?? 'equipment',
                    'title'                  => $asset['title'] ?? 'Marketplace asset',
                    'description'            => $asset['description'] ?? null,
                    'supplier_name'          => $asset['vendor'] ?? $asset['supplier_name'] ?? 'KopaFasta Partner',
                    'asset_value'            => $value,
                    'supplier_deposit'       => $deposit,
                    'deposit_markup_percent' => 10,
                    'customer_deposit'       => $deposit * 1.1,
                    'weekly_installment'     => (float) ($asset['weekly_installment'] ?? 0),
                    'max_tenure_months'      => (int) ($asset['max_tenure_months'] ?? 12),
                    'photos'                 => $asset['photos'] ?? [],
                    'is_active'              => true,
                    'availability_status'    => 'available',
                ]
            );
        }
    }
}
