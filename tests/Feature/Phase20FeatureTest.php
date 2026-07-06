<?php

namespace Tests\Feature;

use App\Models\MarketplaceAsset;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AssetLendingService;
use App\Services\MarketplaceAssetService;
use App\Services\PinService;
use App\Services\ReferralService;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase20FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_seeder_includes_credit_compliance_and_it(): void
    {
        $this->seed(DepartmentSeeder::class);

        $this->assertDatabaseHas('departments', ['code' => 'CRD', 'name' => 'Credit']);
        $this->assertDatabaseHas('departments', ['code' => 'CMP', 'name' => 'Compliance']);
        $this->assertDatabaseHas('departments', ['code' => 'IT', 'name' => 'Information Technology']);
    }

    public function test_admin_asset_lending_settings_page_shows_monthly_rate_field(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.asset-lending'))
            ->assertOk()
            ->assertSee('Default monthly rate', false);
    }

    public function test_marketplace_weekly_installment_uses_configured_monthly_rate(): void
    {
        Setting::set('asset_lending.default_monthly_rate_percent', 18);

        $asset = MarketplaceAsset::create([
            'slug'                => 'p20-rate-asset',
            'title'               => 'Rate Test Asset',
            'category'            => 'vehicle',
            'supplier_name'       => 'Supplier',
            'asset_value'         => 10_000_000,
            'supplier_deposit'    => 2_000_000,
            'customer_deposit'    => 2_200_000,
            'max_tenure_months'   => 12,
            'is_active'           => true,
            'availability_status' => 'available',
        ]);

        $weeklyAt18 = app(MarketplaceAssetService::class)->suggestWeeklyInstallment($asset);
        $this->assertEqualsWithDelta(0.18, app(AssetLendingService::class)->defaultMonthlyRate(), 0.001);

        Setting::set('asset_lending.default_monthly_rate_percent', 12);
        $weeklyAt12 = app(MarketplaceAssetService::class)->suggestWeeklyInstallment($asset->fresh());

        $this->assertGreaterThan($weeklyAt12, $weeklyAt18);
    }

    public function test_public_marketplace_asset_page_uses_apply_for_asset_copy(): void
    {
        $asset = MarketplaceAsset::create([
            'slug'                => 'p20-public-asset',
            'title'               => 'Public Apply Asset',
            'category'            => 'vehicle',
            'supplier_name'       => 'Supplier',
            'asset_value'         => 5_000_000,
            'supplier_deposit'    => 1_000_000,
            'customer_deposit'    => 1_100_000,
            'weekly_installment'  => 100_000,
            'max_tenure_months'   => 12,
            'is_active'           => true,
            'availability_status' => 'available',
        ]);

        $this->get(route('site.marketplace.show', $asset->slug))
            ->assertOk()
            ->assertSee(__('borrower.marketplace.public_apply_hint'), false)
            ->assertSee(__('borrower.marketplace.public_apply_login'), false);
    }

    public function test_borrower_referrals_page_shows_localized_share_actions(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        $customer = \App\Models\Customer::create([
            'user_id'         => $user->id,
            'customer_number' => 'CU-P20-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Refer',
            'last_name'       => 'Friend',
            'phone'           => '255712345814',
        ]);

        app(ReferralService::class)->ensureCode($customer);

        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('site.borrower.referrals'))
            ->assertOk()
            ->assertSee(__('borrower.referrals.share_whatsapp'), false)
            ->assertSee(__('borrower.referrals.share_copy'), false);
    }
}
