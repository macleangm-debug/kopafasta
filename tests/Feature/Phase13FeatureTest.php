<?php

namespace Tests\Feature;

use App\Models\CreditHistory;
use App\Models\Customer;
use App\Models\MarketplaceAsset;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CrbBillingService;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase13FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_featured_marketplace_section_when_assets_exist(): void
    {
        MarketplaceAsset::create([
            'slug'                => 'p13-truck',
            'title'               => 'Phase 13 Truck',
            'category'            => 'vehicle',
            'supplier_name'       => 'Phase 13 Supplier',
            'asset_value'         => 25_000_000,
            'supplier_deposit'    => 5_000_000,
            'customer_deposit'    => 5_500_000,
            'weekly_installment'  => 250_000,
            'max_tenure_months'   => 24,
            'is_active'           => true,
            'availability_status' => 'available',
        ]);

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('Asset marketplace', false)
            ->assertSee('Phase 13 Truck', false)
            ->assertSee(route('site.marketplace', [], false), false);
    }

    public function test_crb_billing_service_summarizes_monthly_usage(): void
    {
        $customer = Customer::create([
            'customer_number' => 'CU-P13-001',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Crb',
            'last_name'       => 'Billing',
            'phone'           => '255712345698',
        ]);

        CreditHistory::create([
            'customer_id' => $customer->id,
            'source'      => 'crb_stub',
            'payload'     => ['report_type' => 'credit'],
            'checked_at'  => now(),
        ]);

        $summary = app(CrbBillingService::class)->monthlySummary();

        $this->assertSame(1, $summary['requests']);
        $this->assertCount(6, app(CrbBillingService::class)->recentMonths(6));
    }

    public function test_admin_crb_settings_page_shows_billing_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.crb'))
            ->assertOk()
            ->assertSee('Monthly usage', false)
            ->assertSee('Cost per bureau request', false);
    }

    public function test_borrower_kin_profile_page_is_available(): void
    {
        $user = User::factory()->create(['role' => 'borrower']);
        app(PinService::class)->setPin($user, '1234');

        Customer::create([
            'user_id'         => $user->id,
            'customer_number' => 'CU-P13-002',
            'type'            => 'individual',
            'status'          => 'active',
            'first_name'      => 'Kin',
            'last_name'       => 'Profile',
            'phone'           => '255712345699',
        ]);

        $this->actingAs($user)
            ->get(route('site.borrower.profile', ['section' => 'kin']))
            ->assertOk()
            ->assertSee(__('borrower.profile.kin_title'), false)
            ->assertSee(__('borrower.profile.fields.relationship'), false);
    }

    public function test_recovery_partner_admin_links_use_partner_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = Vendor::create([
            'vendor_number' => 'PTR-P13-001',
            'name'          => 'Recovery Partner',
            'category'      => 'debt_collector',
            'status'        => 'active',
            'phone'         => '255712345700',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.recovery.partners.type', ['type' => 'debt_collector']))
            ->assertOk()
            ->assertSee(route('admin.partners.create', ['category' => 'debt_collector']), false)
            ->assertSee(route('admin.partners.show', $partner), false);
    }
}
