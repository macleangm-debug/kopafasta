<?php

namespace Tests\Feature;

use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\MarketplaceAsset;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AssetLendingService;
use App\Services\MarketplaceAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetLendingAssetTenureTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_maximum_is_the_absolute_ceiling(): void
    {
        $lending = $this->productCeiling(24);

        $this->assertSame(24, $lending->productMaxTenureMonths());
        $this->assertSame(24, $lending->clampAssetTenure(36));
        $this->assertSame(12, $lending->effectiveAssetTenure(12));
        $this->assertSame(24, $lending->effectiveAssetTenure(36));
        $this->assertSame(24, $lending->effectiveAssetTenure(null));
    }

    public function test_effective_tenure_is_min_of_product_and_asset(): void
    {
        $this->productCeiling(24);
        $asset = new MarketplaceAsset(['max_tenure_months' => 12]);

        $this->assertSame(12, effective_marketplace_asset_max_tenure($asset));

        $this->productCeiling(6);
        $this->assertSame(6, effective_marketplace_asset_max_tenure($asset));
    }

    public function test_prepare_for_save_keeps_asset_tenure_within_product_ceiling(): void
    {
        $this->productCeiling(24);
        $prepared = app(MarketplaceAssetService::class)->prepareForSave([
            'category' => 'vehicle',
            'title' => 'Tenure Laptop',
            'asset_value' => 10_000_000,
            'max_tenure_months' => 12,
            'is_active' => true,
        ]);

        $this->assertSame(12, (int) $prepared['max_tenure_months']);
    }

    public function test_quote_tenure_uses_product_ceiling_not_financing_band(): void
    {
        $lending = $this->productCeiling(24);
        $quote = $lending->pricingQuoteFromAssetPrice(10_000_000, 18);

        $this->assertSame(18, $quote['tenure_months']);
        $this->assertSame(24, $quote['max_tenure_months']);
        $this->assertCount(18, $quote['schedule']);

        $quotes = $lending->quotesByTenure(10_000_000, 36);
        $this->assertCount(24, $quotes);
        $this->assertArrayHasKey(1, $quotes);
        $this->assertArrayHasKey(24, $quotes);
        $this->assertArrayNotHasKey(25, $quotes);
    }

    public function test_supplier_and_admin_reject_tenure_above_product_maximum(): void
    {
        $this->productCeiling(24);
        $assets = app(MarketplaceAssetService::class);
        $rules = $assets->validationRules();
        $messages = $assets->validationMessages();

        $validator = validator(['max_tenure_months' => 36], [
            'max_tenure_months' => $rules['max_tenure_months'],
        ], $messages);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            __('site.supplier_portal.wizard_max_tenure_error', ['max' => 24]),
            $validator->errors()->first('max_tenure_months')
        );
    }

    public function test_public_marketplace_show_survives_array_category_and_description(): void
    {
        $asset = MarketplaceAsset::create([
            'slug' => 'grand-terron-9-maxus-sx6y',
            'title' => 'Grand Terron 9 Maxus',
            'category' => 'vehicle',
            'description' => 'Grand Terron 9 Maxus',
            'supplier_name' => 'MacLeans Autotraders',
            'asset_value' => 10_000_000,
            'supplier_deposit' => 800_000,
            'customer_deposit' => 880_000,
            'max_tenure_months' => 6,
            'is_active' => true,
            'availability_status' => 'available',
            'specs' => ['city' => ['Dar es Salaam', 'Arusha']],
        ]);

        $this->get(route('site.marketplace.show', $asset->slug))
            ->assertOk()
            ->assertSee('Grand Terron 9 Maxus', false)
            ->assertDontSee('Choose duration', false);
    }

    public function test_prepare_for_save_assigns_supplier_tied_asset_number(): void
    {
        $vendor = Vendor::create([
            'vendor_number' => 'PT-SP-TZ-MHXL',
            'name' => 'MacLeans Autotraders',
            'category' => 'supplier',
            'status' => 'active',
        ]);

        $prepared = app(MarketplaceAssetService::class)->prepareForSave([
            'category' => 'vehicle',
            'title' => 'Numbered Vehicle',
            'asset_value' => 10_000_000,
            'max_tenure_months' => 6,
            'vendor_id' => $vendor->id,
            'is_active' => true,
        ]);

        $this->assertSame('PT-SP-TZ-MHXL-A001', $prepared['asset_number']);
    }

    public function test_admin_create_form_uses_the_same_compact_tenure_field(): void
    {
        $this->productCeiling(24);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.marketplace-assets.create'))
            ->assertOk()
            ->assertSee(__('site.supplier_portal.wizard_max_tenure'), false)
            ->assertSee('Choose the longest repayment period you allow for this asset', false)
            ->assertSee('24 months', false)
            ->assertDontSee('Max tenure (months)', false);
    }

    public function test_existing_application_snapshot_keeps_requested_tenure(): void
    {
        $this->productCeiling(24);
        [$application, $asset] = $this->quotedApplication(6);
        $lending = app(AssetLendingService::class);

        $first = $lending->snapshotCommercialTerms($application->fresh(['assetReservation.asset.vendor', 'product']));
        $this->assertSame(6, (int) $first['tenure_months']);

        $asset->update(['max_tenure_months' => 18]);
        $again = $lending->snapshotCommercialTerms($application->fresh(['assetReservation.asset.vendor', 'product']));
        $this->assertSame(6, (int) $again['tenure_months']);
        $this->assertSame(6, (int) ($again['quote']['tenure_months'] ?? 0));
    }

    private function productCeiling(int $months): AssetLendingService
    {
        $lending = app(AssetLendingService::class);
        $lending->persistPricingTiers(
            $lending->depositTiers(),
            collect($lending->financingTiers())
                ->map(function (array $tier) use ($months) {
                    $tier['max_tenure_months'] = $months;

                    return $tier;
                })
                ->all(),
        );

        return $lending;
    }

    /** @return array{0: LoanApplication, 1: MarketplaceAsset} */
    private function quotedApplication(int $tenure): array
    {
        $vendor = Vendor::create([
            'vendor_number' => 'PTR-AL-TENURE',
            'name' => 'Tenure Supplier',
            'category' => 'supplier',
            'status' => 'active',
            'supplier_type' => 'managed_loan',
        ]);
        $asset = MarketplaceAsset::create([
            'vendor_id' => $vendor->id,
            'slug' => 'tenure-quoted-asset',
            'title' => 'Quoted Vehicle',
            'category' => 'vehicle',
            'supplier_name' => $vendor->name,
            'asset_value' => 10_000_000,
            'supplier_deposit' => 2_000_000,
            'customer_deposit' => 2_200_000,
            'max_tenure_months' => $tenure,
            'is_active' => true,
        ]);
        $product = LoanProduct::query()->where('code', 'AL')->first()
            ?? LoanProduct::create([
                'code' => 'AL',
                'name' => 'Asset Lending',
                'category' => 'asset_finance',
                'is_active' => true,
                'interest_rate' => 0.15,
                'min_amount' => 500_000,
                'max_amount' => 50_000_000,
                'tenure_min_months' => 1,
                'tenure_max_months' => 24,
            ]);
        $customer = Customer::create([
            'customer_number' => 'CU-AL-TENURE',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Quoted',
            'last_name' => 'Borrower',
            'phone' => '25571'.random_int(1000000, 9999999),
        ]);
        $application = LoanApplication::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'application_number' => 'APP-AL-TENURE',
            'status' => 'submitted',
            'current_stage' => 'screening',
            'requested_amount' => 8_000_000,
            'requested_tenure_months' => $tenure,
            'application_fee_amount' => 10_000,
        ]);
        AssetReservation::create([
            'customer_id' => $customer->id,
            'loan_application_id' => $application->id,
            'marketplace_asset_id' => $asset->id,
            'status' => 'reserved',
            'deposit_amount' => 2_200_000,
            'deposit_status' => 'pending',
        ]);

        return [$application, $asset];
    }
}
