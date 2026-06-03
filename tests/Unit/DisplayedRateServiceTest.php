<?php

namespace Tests\Unit;

use App\Models\LoanProduct;
use App\Models\LoanProductRateTier;
use App\Services\DisplayedRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisplayedRateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_level_rate_sums_bot_and_fees(): void
    {
        $product = LoanProduct::create([
            'code' => 'TST-01',
            'name' => 'Test Product',
            'interest_rate' => 0.12,
            'bot_regulated_rate' => 0.035,
            'processing_fee_rate' => 0.05,
            'service_fee_rate' => 0.035,
            'administration_fee_rate' => 0,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'min_amount' => 100_000,
            'max_amount' => 1_000_000,
            'is_active' => true,
            'status' => 'active',
        ]);

        $rate = app(DisplayedRateService::class)->displayedMonthlyRate($product);

        $this->assertEqualsWithDelta(0.12, $rate, 0.0001);
    }

    public function test_tier_rate_is_total_borrower_rate_for_amount_band(): void
    {
        $product = LoanProduct::create([
            'code' => 'TST-02',
            'name' => 'Tiered Product',
            'interest_rate' => 0.12,
            'bot_regulated_rate' => 0.035,
            'processing_fee_rate' => 0.05,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'is_active' => true,
            'status' => 'active',
        ]);

        LoanProductRateTier::create([
            'loan_product_id' => $product->id,
            'min_amount' => 100_000,
            'max_amount' => 500_000,
            'monthly_rate' => 0.17,
            'sort_order' => 1,
        ]);

        $service = app(DisplayedRateService::class);

        $this->assertEqualsWithDelta(0.17, $service->displayedMonthlyRate($product, 200_000), 0.0001);
        $this->assertSame('17%', $service->formatBorrowerRateRange($product));
    }
}
