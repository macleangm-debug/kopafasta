<?php

namespace Tests\Feature;

use App\Models\LoanProduct;
use App\Models\LoanProductRateTier;
use App\Models\MarketplaceAsset;
use App\Models\User;
use App\Models\Vendor;
use App\Services\LoanRateTierRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase5FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_tier_repair_normalizes_percent_stored_tiers(): void
    {
        $product = LoanProduct::create([
            'code'               => 'IL-P5',
            'name'               => 'Repair Test',
            'is_active'          => true,
            'interest_rate'      => 19,
            'min_amount'         => 100_000,
            'max_amount'         => 10_000_000,
            'tenure_min_months'  => 3,
            'tenure_max_months'  => 24,
        ]);

        $tier = LoanProductRateTier::create([
            'loan_product_id'         => $product->id,
            'min_amount'              => 100_000,
            'max_amount'              => 500_000,
            'bot_regulated_rate'      => 3.5,
            'processing_fee_rate'     => 5,
            'service_fee_rate'        => 3.5,
            'administration_fee_rate' => 0,
            'monthly_rate'            => 19,
            'sort_order'              => 1,
        ]);

        $repair = app(LoanRateTierRepairService::class);
        $this->assertTrue($repair->tierNeedsRepair($tier));

        $result = $repair->repairAll(false);

        $this->assertSame(1, $result['tiers_fixed']);
        $this->assertGreaterThanOrEqual(1, $result['products_updated']);

        $tier->refresh();
        $product->refresh();

        $this->assertLessThanOrEqual(1, (float) $tier->monthly_rate);
        $this->assertLessThanOrEqual(1, (float) $product->interest_rate);
        $this->assertEqualsWithDelta(0.12, (float) $tier->monthly_rate, 0.001);
    }

    public function test_repair_rate_tiers_command_runs_in_dry_run_mode(): void
    {
        LoanProductRateTier::create([
            'loan_product_id'    => LoanProduct::create([
                'code'              => 'DRY-P5',
                'name'              => 'Dry Run',
                'is_active'         => true,
                'interest_rate'     => 0.15,
                'min_amount'        => 100_000,
                'max_amount'        => 1_000_000,
                'tenure_min_months' => 1,
                'tenure_max_months' => 12,
            ])->id,
            'min_amount'         => 100_000,
            'max_amount'         => 500_000,
            'monthly_rate'       => 70.9,
            'bot_regulated_rate' => 3.5,
            'sort_order'         => 1,
        ]);

        $this->artisan('loan-products:repair-rate-tiers', ['--dry-run' => true])
            ->assertSuccessful();
    }

    public function test_supplier_asset_service_persists_photos_and_insurance_fields(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $vendor = Vendor::create([
            'user_id'       => $user->id,
            'vendor_number' => 'SUP-P5-001',
            'name'          => 'Phase 5 Supplier',
            'category'      => 'supplier',
            'status'        => 'active',
        ]);

        $assets = app(\App\Services\MarketplaceAssetService::class);
        $validated = [
            'category'                => 'vehicle',
            'title'                   => 'Isuzu NQR',
            'description'             => 'Low mileage truck',
            'insurance_policy_number' => 'POL-P5-001',
            'insurance_expires_at'    => now()->addYear()->toDateString(),
            'asset_value'             => 5_000_000,
            'supplier_deposit'        => 1_000_000,
            'deposit_markup_percent'  => 10,
            'weekly_installment'      => 120_000,
            'max_tenure_months'       => 24,
            'waiting_period_days'     => 7,
        ];

        $data = $assets->prepareForSave(array_merge($validated, [
            'vendor_id'     => $vendor->id,
            'supplier_name' => $vendor->name,
            'is_active'     => true,
        ]));

        $record = MarketplaceAsset::create($data);
        $assets->syncPhotos($record, [UploadedFile::fake()->image('cover.jpg')]);

        $record->refresh();

        $this->assertSame('POL-P5-001', $record->insurance_policy_number);
        $this->assertSame(7, (int) $record->waiting_period_days);
        $this->assertCount(1, $record->photos ?? []);
        Storage::disk('public')->assertExists($record->photos[0]);
    }

    public function test_supplier_asset_form_includes_insurance_and_photo_fields(): void
    {
        $user = User::factory()->create();
        Vendor::create([
            'user_id'       => $user->id,
            'vendor_number' => 'SUP-P5-002',
            'name'          => 'Phase 5 Supplier',
            'category'      => 'supplier',
            'status'        => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('site.supplier.assets.create'))
            ->assertOk()
            ->assertSee('Insurance expiry', false)
            ->assertSee('Waiting period', false)
            ->assertSee('Photos (up to 4)', false)
            ->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_partner_dashboard_uses_partner_portal_label(): void
    {
        $user = User::factory()->create();
        Vendor::create([
            'user_id'       => $user->id,
            'vendor_number' => 'PTR-P5-001',
            'name'          => 'GPS Partner',
            'category'      => 'gps',
            'status'        => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('site.vendor.dashboard'))
            ->assertOk()
            ->assertSee('Partner portal', false)
            ->assertSee('Partner dashboard', false);
    }
}
