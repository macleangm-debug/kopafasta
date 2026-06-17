<?php

namespace Tests\Feature;

use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\Promotion;
use App\Models\User;
use App\Services\ApplicationFeePaymentService;
use App\Services\LoanRateTierTemplateService;
use App\Services\MarketplaceAssetService;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Phase14FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotions_reject_interest_scope_on_promo_code(): void
    {
        Promotion::create([
            'code'              => 'INTEREST10',
            'name'              => 'Interest promo',
            'type'              => 'promo_code',
            'status'            => 'active',
            'discount_percent'  => 10,
            'applies_to'        => 'interest',
        ]);

        $result = app(PromotionService::class)->applyPromoCode('INTEREST10', 'application_fee', 50_000);

        $this->assertFalse($result['valid']);
        $this->assertSame(50_000.0, $result['after_discount']);
    }

    public function test_promotion_create_form_does_not_offer_interest_discount_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.promotions.create'))
            ->assertOk()
            ->assertDontSee('Interest discount', false)
            ->assertSee('Promotions apply to fees only', false);
    }

    public function test_marketplace_asset_service_suggests_weekly_installment(): void
    {
        $asset = new MarketplaceAsset([
            'asset_value'            => 10_000_000,
            'supplier_deposit'       => 2_000_000,
            'deposit_markup_percent' => 10,
            'max_tenure_months'      => 12,
        ]);

        $weekly = app(MarketplaceAssetService::class)->suggestWeeklyInstallment($asset);

        $this->assertGreaterThan(0, $weekly);
    }

    public function test_prepare_for_save_auto_fills_weekly_installment_when_missing(): void
    {
        $prepared = app(MarketplaceAssetService::class)->prepareForSave([
            'category'          => 'vehicle',
            'title'             => 'Auto Priced Truck',
            'asset_value'       => 8_000_000,
            'supplier_deposit'  => 1_600_000,
            'max_tenure_months' => 18,
            'is_active'         => true,
        ]);

        $this->assertGreaterThan(0, (float) ($prepared['weekly_installment'] ?? 0));
        $this->assertGreaterThan(0, (float) ($prepared['customer_deposit'] ?? 0));
    }

    public function test_regenerate_rate_tiers_route_is_registered(): void
    {
        $this->assertTrue(Route::has('admin.loan-products.regenerate-rate-tiers'));
    }

    public function test_regenerate_rate_tiers_replaces_product_bands(): void
    {
        $product = LoanProduct::create([
            'code'              => 'IL-P14',
            'name'              => 'Phase 14 Product',
            'is_active'         => true,
            'interest_rate'     => 0.17,
            'min_amount'        => 500_000,
            'max_amount'        => 5_000_000,
            'tenure_min_months' => 3,
            'tenure_max_months' => 24,
        ]);

        app(LoanRateTierTemplateService::class)->applyDefaults($product, replaceExisting: true);

        $this->assertGreaterThan(0, $product->fresh()->rateTiers()->count());
    }

    public function test_application_fee_must_be_paid_before_submit(): void
    {
        $this->assertFalse(app(ApplicationFeePaymentService::class)->isFeeSatisfied(null, 50_000));
        $this->assertFalse(app(ApplicationFeePaymentService::class)->isFeeSatisfied(['status' => 'pending'], 50_000));
        $this->assertTrue(app(ApplicationFeePaymentService::class)->isFeeSatisfied(['status' => 'paid'], 50_000));
        $this->assertTrue(app(ApplicationFeePaymentService::class)->isFeeSatisfied(null, 0));
    }
}
