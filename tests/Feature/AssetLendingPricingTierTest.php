<?php

namespace Tests\Feature;

use App\Services\AssetLendingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetLendingPricingTierTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposed_deposit_and_financing_tiers_resolve_from_settings_defaults(): void
    {
        $lending = app(AssetLendingService::class);

        $quote = $lending->pricingQuoteFromAssetPrice(20_000_000);

        $this->assertSame(6.0, $quote['deposit_percent']);
        $this->assertSame(1_200_000.0, $quote['deposit_amount']);
        $this->assertSame(18_800_000.0, $quote['financed_amount']);
        $this->assertSame(2.5, $quote['monthly_rate_percent']);
        $this->assertSame('reducing_balance', $quote['rate_method']);
        $this->assertSame(6, $quote['tenure_months']);
        $this->assertFalse($quote['valuation_applicable']);
        $this->assertNotEmpty($quote['deposit_tier_snapshot']);
        $this->assertNotEmpty($quote['financing_tier_snapshot']);
        $this->assertNotEmpty($quote['snapshotted_at']);
        $this->assertCount(6, $quote['schedule']);
    }

    public function test_large_asset_uses_lower_deposit_and_financing_tiers(): void
    {
        $quote = app(AssetLendingService::class)->pricingQuoteFromAssetPrice(60_000_000);

        $this->assertSame(3.0, $quote['deposit_percent']);
        $this->assertSame(1_800_000.0, $quote['deposit_amount']);
        $this->assertSame(58_200_000.0, $quote['financed_amount']);
        $this->assertSame(1.5, $quote['monthly_rate_percent']);
    }

    public function test_member_quote_and_displayed_rate_use_the_same_financing_source(): void
    {
        $lending = app(AssetLendingService::class);
        $product = \App\Models\LoanProduct::query()->where('code', 'AL')->first()
            ?? \App\Models\LoanProduct::create([
                'code' => 'AL',
                'name' => 'Asset Lending',
                'category' => 'asset_finance',
                'interest_rate' => 0.155,
                'min_amount' => 500_000,
                'max_amount' => 100_000_000,
                'tenure_min_months' => 1,
                'tenure_max_months' => 6,
                'is_active' => true,
                'status' => 'active',
            ]);

        $range = app(\App\Services\DisplayedRateService::class)->borrowerRateRange($product);
        $tiers = collect($lending->financingTiers())->pluck('monthly_rate_percent');
        $this->assertEqualsWithDelta(((float) $tiers->min()) / 100, $range['min'], 0.0001);
        $this->assertEqualsWithDelta(((float) $tiers->max()) / 100, $range['max'], 0.0001);

        $asset = new \App\Models\MarketplaceAsset([
            'asset_value' => 20_000_000,
            'customer_deposit' => 1_200_000,
            'max_tenure_months' => 6,
        ]);
        $weekly = app(\App\Services\MarketplaceAssetService::class)->suggestWeeklyInstallment($asset);
        $quote = $lending->pricingQuoteFromAssetPrice(20_000_000, 6);
        $this->assertGreaterThan(0, $weekly);
        $this->assertEqualsWithDelta(round(((float) $quote['installment']) / 4.33, 2), $weekly, 0.05);
    }

    public function test_product_configuration_persist_is_the_quote_source(): void
    {
        $lending = app(AssetLendingService::class);
        $lending->persistPricingTiers(
            [['from' => 0, 'to' => null, 'percent' => 12, 'active' => true]],
            [['from' => 0, 'to' => null, 'monthly_rate_percent' => 4, 'method' => 'reducing_balance', 'max_tenure_months' => 6, 'active' => true]],
        );

        $quote = $lending->pricingQuoteFromAssetPrice(20_000_000);
        $this->assertSame(12.0, $quote['deposit_percent']);
        $this->assertSame(4.0, $quote['monthly_rate_percent']);
    }

    public function test_marketplace_vehicle_valuation_is_not_applicable(): void
    {
        $lending = app(AssetLendingService::class);

        $this->assertFalse($lending->requiresMarketplaceValuation('vehicle'));
        $this->assertFalse($lending->requiresMarketplaceValuation('motorcycle'));
        $this->assertTrue($lending->requiresMarketplaceValuation('house'));
    }
}
