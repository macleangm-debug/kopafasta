<?php

namespace Tests\Feature;

use App\Models\MarketplaceAsset;
use App\Models\Setting;
use App\Models\User;
use App\Services\MarketplaceAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase39FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepare_for_save_uses_platform_markup_not_supplier_override(): void
    {
        Setting::setMany(['asset_lending.default_deposit_markup_percent' => 12]);

        $prepared = app(MarketplaceAssetService::class)->prepareForSave([
            'category'               => 'vehicle',
            'title'                  => 'Locked Markup Truck',
            'asset_value'            => 5_000_000,
            'supplier_deposit'       => 1_000_000,
            'deposit_markup_percent' => 99,
            'max_tenure_months'      => 12,
            'is_active'              => true,
        ]);

        $this->assertSame(12.0, (float) $prepared['deposit_markup_percent']);
        $this->assertSame(1_120_000.0, (float) $prepared['customer_deposit']);
    }

    public function test_sync_deposit_recalculates_from_platform_default(): void
    {
        Setting::setMany(['asset_lending.default_deposit_markup_percent' => 15]);

        $asset = MarketplaceAsset::create([
            'slug'                   => 'p39-bike',
            'title'                  => 'Motorbike',
            'category'               => 'motorbike',
            'supplier_name'          => 'Supplier',
            'asset_value'            => 2_000_000,
            'supplier_deposit'       => 400_000,
            'deposit_markup_percent' => 5,
            'customer_deposit'       => 420_000,
            'weekly_installment'     => 50_000,
            'max_tenure_months'      => 12,
            'is_active'              => true,
        ]);

        app(MarketplaceAssetService::class)->syncDeposit($asset);
        $asset->refresh();

        $this->assertSame(15.0, (float) $asset->deposit_markup_percent);
        $this->assertSame(460_000.0, (float) $asset->customer_deposit);
    }

    public function test_public_marketplace_list_uses_minimal_cards_without_breakdown(): void
    {
        MarketplaceAsset::create([
            'slug'               => 'p39-truck',
            'title'              => 'Minimal Card Truck',
            'category'           => 'vehicle',
            'supplier_name'      => 'Supplier',
            'asset_value'        => 5_000_000,
            'supplier_deposit'   => 1_000_000,
            'customer_deposit'   => 1_100_000,
            'weekly_installment' => 120_000,
            'max_tenure_months'  => 24,
            'is_active'          => true,
        ]);

        $this->get(route('site.marketplace'))
            ->assertOk()
            ->assertSee('Minimal Card Truck', false)
            ->assertSee(__('borrower.marketplace.view_details'), false)
            ->assertDontSee('Deposit breakdown', false)
            ->assertDontSee('Company markup', false);
    }

    public function test_asset_lending_settings_page_describes_markup_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.asset-lending'))
            ->assertOk()
            ->assertSee('Markup rules', false)
            ->assertSee('Suppliers cannot override markup', false);
    }
}
