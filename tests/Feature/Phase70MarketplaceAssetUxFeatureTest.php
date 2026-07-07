<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MarketplaceAsset;
use App\Models\User;
use App\Models\Vendor;
use App\Services\MarketplaceAssetService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase70MarketplaceAssetUxFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_percent_is_derived_from_asset_value_on_save(): void
    {
        $prepared = app(MarketplaceAssetService::class)->prepareForSave([
            'category'          => 'vehicle',
            'title'             => 'Deposit Percent Truck',
            'asset_value'       => 10_000_000,
            'deposit_percent'   => 25,
            'max_tenure_months' => 12,
            'is_active'         => true,
        ]);

        $this->assertSame(2_500_000.0, (float) $prepared['supplier_deposit']);
        $this->assertSame(2_750_000.0, (float) $prepared['customer_deposit']);
    }

    public function test_admin_can_edit_asset_by_slug(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = MarketplaceAsset::create([
            'slug'               => 'edit-by-slug-truck',
            'title'              => 'Slug Truck',
            'category'           => 'vehicle',
            'supplier_name'      => 'Supplier',
            'asset_value'        => 5_000_000,
            'supplier_deposit'   => 1_000_000,
            'customer_deposit'   => 1_100_000,
            'weekly_installment' => 120_000,
            'max_tenure_months'  => 12,
            'is_active'          => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.marketplace-assets.edit', $asset->slug))
            ->assertOk()
            ->assertSee('Deposit (% of asset value)', false);
    }

    public function test_borrower_can_access_reserve_flow_after_asset_is_locked(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-P70-002',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Locked',
            'last_name'             => 'Borrower',
            'phone'                 => '255712340071',
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $asset = MarketplaceAsset::create([
            'slug'               => 'locked-truck-001',
            'title'              => 'Locked Truck',
            'category'           => 'vehicle',
            'supplier_name'      => 'Supplier',
            'asset_value'        => 8_000_000,
            'supplier_deposit'   => 1_600_000,
            'customer_deposit'   => 1_760_000,
            'weekly_installment' => 150_000,
            'max_tenure_months'  => 18,
            'is_active'          => true,
        ]);

        $this->actingAs($user)
            ->post(route('site.borrower.marketplace.apply', $asset->slug))
            ->assertRedirect(route('site.borrower.marketplace.reserve', $asset->slug));

        $asset->refresh();
        $this->assertSame('locked', $asset->availability_status);

        $this->actingAs($user)
            ->get(route('site.borrower.marketplace.reserve', $asset->slug))
            ->assertOk()
            ->assertSee('Locked Truck', false);
    }

    public function test_borrower_can_apply_for_marketplace_asset(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');
        Customer::create([
            'user_id'               => $user->id,
            'customer_number'       => 'CU-P70-001',
            'type'                  => 'individual',
            'status'                => 'active',
            'first_name'            => 'Apply',
            'last_name'             => 'Borrower',
            'phone'                 => '255712340070',
            'membership_status'     => 'active',
            'membership_expires_at' => now()->addYear(),
        ]);

        $asset = MarketplaceAsset::create([
            'slug'               => 'apply-truck-001',
            'title'              => 'Apply Truck',
            'category'           => 'vehicle',
            'supplier_name'      => 'Supplier',
            'asset_value'        => 8_000_000,
            'supplier_deposit'   => 1_600_000,
            'customer_deposit'   => 1_760_000,
            'weekly_installment' => 150_000,
            'max_tenure_months'  => 18,
            'is_active'          => true,
            'photos'             => ['marketplace/test.jpg'],
        ]);

        $this->actingAs($user)
            ->post(route('site.borrower.marketplace.apply', $asset->slug))
            ->assertRedirect(route('site.borrower.marketplace.reserve', $asset->slug));
    }

    public function test_marketplace_card_shows_supplier_and_region(): void
    {
        $supplier = Vendor::create([
            'vendor_number' => 'SUP-P70',
            'name'          => 'Dar Motors',
            'category'      => 'supplier',
            'status'        => 'active',
            'phone'         => '255712340070',
            'coverage_type' => 'nationwide',
        ]);

        MarketplaceAsset::create([
            'slug'               => 'card-truck',
            'title'              => 'Card Truck',
            'category'           => 'vehicle',
            'supplier_name'      => 'Dar Motors',
            'partner_id'         => $supplier->id,
            'asset_value'        => 4_000_000,
            'supplier_deposit'   => 800_000,
            'customer_deposit'   => 880_000,
            'weekly_installment' => 90_000,
            'max_tenure_months'  => 12,
            'is_active'          => true,
        ]);

        $this->get(route('site.marketplace'))
            ->assertOk()
            ->assertSee('Dar Motors', false)
            ->assertSee('Nationwide', false);
    }
}
