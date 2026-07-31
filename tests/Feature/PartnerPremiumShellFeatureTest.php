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
            ->assertSee('Collections portal', false)
            ->assertSee('Recovery Cases', false)
            ->assertSee('Commission Wallet', false)
            ->assertSee('bg-brand', false)
            ->assertSee('bg-[#faf8f5]', false);
    }

    public function test_valuer_portal_hides_recovery_nav(): void
    {
        $this->seed(PartnerDemoAccountsSeeder::class);

        $user = User::query()->where('email', 'valuer@kopafasta.local')->firstOrFail();

        $this->actingAs($user)
            ->get(route('site.partner.dashboard'))
            ->assertOk()
            ->assertSee('Valuer portal', false)
            ->assertSee('Assigned Tasks', false)
            ->assertDontSee('Recovery Cases', false);
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
            ->assertSee('Supplier portal', false)
            ->assertSee('Assets', false);
    }

    public function test_footer_includes_service_partners_link(): void
    {
        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee(route('site.partners'), false)
            ->assertSee(__('site.footer.service_partners'), false);
    }
}
