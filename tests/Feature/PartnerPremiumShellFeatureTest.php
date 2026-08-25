<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PartnerDemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerPremiumShellFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_partner_portal_uses_premium_shell(): void
    {
        $this->seed(PartnerDemoAccountsSeeder::class);

        $user = User::query()->where('email', 'collection@kopafasta.local')->firstOrFail();

        $this->actingAs($user)
            ->get(route('site.partner.dashboard'))
            ->assertOk()
            ->assertSee(__('site.partner_portal.shell_debt_collector'), false)
            ->assertSee(__('site.partner_portal.nav_recovery'), false)
            ->assertSee(__('site.partner_portal.nav_commission'), false)
            ->assertSee('kf-premium-panel', false)
            ->assertSee(__('site.partner_portal.no_assigned_tasks'), false)
            ->assertSee('bg-[#faf8f5]', false);
    }

    public function test_valuer_portal_hides_recovery_nav(): void
    {
        $this->seed(PartnerDemoAccountsSeeder::class);

        $user = User::query()->where('email', 'valuer@kopafasta.local')->firstOrFail();

        $this->actingAs($user)
            ->get(route('site.partner.dashboard'))
            ->assertOk()
            ->assertSee(__('site.partner_portal.shell_valuer'), false)
            ->assertSee(__('site.partner_portal.nav_valuation_jobs'), false)
            ->assertDontSee(__('site.partner_portal.nav_recovery'), false);
    }

    public function test_affiliate_and_supplier_use_shared_shell(): void
    {
        $this->seed(PartnerDemoAccountsSeeder::class);

        $affiliate = User::query()->where('email', 'affiliate@kopafasta.local')->firstOrFail();
        $this->actingAs($affiliate)
            ->get(route('site.affiliate.dashboard'))
            ->assertOk()
            ->assertSee('bg-[#faf8f5]', false);

        $supplier = User::query()->where('email', 'supplier@kopafasta.local')->firstOrFail();
        $this->actingAs($supplier)
            ->get(route('site.supplier.dashboard'))
            ->assertOk()
            ->assertSee(__('site.supplier_portal.title'), false)
            ->assertSee(__('site.supplier_portal.nav_assets'), false)
            ->assertSee(__('site.supplier_portal.no_assigned_tasks'), false)
            ->assertSee('kf-premium-panel', false);
    }

    public function test_partner_dashboard_swahili_translates_cards(): void
    {
        $this->seed(PartnerDemoAccountsSeeder::class);
        $user = User::query()->where('email', 'valuer@kopafasta.local')->firstOrFail();

        $this->actingAs($user)
            ->withSession(['locale' => 'sw'])
            ->get(route('site.partner.dashboard'))
            ->assertOk()
            ->assertSee(__('site.partner_portal.no_assigned_tasks', [], 'sw'), false)
            ->assertSee(__('site.partner_portal.shell_valuer', [], 'sw'), false)
            ->assertDontSee('Manage assigned work', false);
    }

    public function test_empty_payments_hide_table_headers(): void
    {
        $this->seed(PartnerDemoAccountsSeeder::class);
        $user = User::query()->where('email', 'valuer@kopafasta.local')->firstOrFail();

        $this->actingAs($user)
            ->get(route('site.partner.payments'))
            ->assertOk()
            ->assertSee(__('site.partner_portal.payments_empty_title'), false)
            ->assertDontSee('<thead', false);
    }

    public function test_footer_includes_service_partners_link(): void
    {
        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee(route('site.partners'), false)
            ->assertSee(__('site.footer.service_partners'), false);
    }
}
