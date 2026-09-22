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
}
