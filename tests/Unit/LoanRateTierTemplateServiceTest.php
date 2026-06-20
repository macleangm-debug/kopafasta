<?php

namespace Tests\Unit;

use App\Models\LoanProduct;
use App\Services\LoanRateTierTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanRateTierTemplateServiceTest extends TestCase
{
    use RefreshDatabase;
    public function test_tiers_span_product_limits_with_interest_rate_as_maximum(): void
    {
        $product = new LoanProduct([
            'code' => 'IL',
            'name' => 'Individual Loan',
            'interest_rate' => 0.19,
            'min_amount' => 100_000,
            'max_amount' => 50_000_000,
        ]);

        $service = app(LoanRateTierTemplateService::class);
        $tiers = $service->tiersForProduct($product);

        $this->assertNotEmpty($tiers);
        $this->assertSame(100_000, (int) $tiers[0]['min_amount']);
        $this->assertSame(50_000_000, (int) $tiers[array_key_last($tiers)]['max_amount']);
        $this->assertEqualsWithDelta(0.19, (float) $tiers[0]['monthly_rate'], 0.0001);

        $rates = array_map(fn (array $t) => (float) $t['monthly_rate'], $tiers);
        $this->assertSame(max($rates), (float) $tiers[0]['monthly_rate']);
        $this->assertLessThan($rates[0], $rates[array_key_last($rates)]);
    }

    public function test_emergency_product_uses_configured_tier_count(): void
    {
        $product = new LoanProduct([
            'code' => 'EM',
            'name' => 'Emergency Loan',
            'interest_rate' => 0.20,
            'min_amount' => 50_000,
            'max_amount' => 3_000_000,
        ]);

        $tiers = app(LoanRateTierTemplateService::class)->tiersForProduct($product);

        $this->assertCount(3, $tiers);
        $this->assertEqualsWithDelta(0.20, (float) $tiers[0]['monthly_rate'], 0.0001);
        $this->assertEqualsWithDelta(0.18, (float) $tiers[2]['monthly_rate'], 0.0001);
    }

    public function test_build_amount_bands_are_contiguous(): void
    {
        $service = app(LoanRateTierTemplateService::class);
        $bands = $service->buildAmountBands(100_000, 10_000_000, 4);

        $this->assertCount(4, $bands);
        $this->assertSame(100_000, $bands[0]['min_amount']);
        $this->assertSame(10_000_000, $bands[3]['max_amount']);
        $this->assertSame($bands[1]['min_amount'], $bands[0]['max_amount'] + 1);
    }
}
