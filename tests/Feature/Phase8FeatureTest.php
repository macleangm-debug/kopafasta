<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Phase8FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_portal_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('site.partner.dashboard'));
        $this->assertTrue(Route::has('site.partner.tasks'));
        $this->assertTrue(Route::has('site.partner.profile'));
        $this->assertSame('/partner', route('site.partner.dashboard', [], false));
    }

    public function test_legacy_vendor_portal_routes_remain_available(): void
    {
        $this->assertTrue(Route::has('site.vendor.dashboard'));
        $this->assertSame('/vendor', route('site.vendor.dashboard', [], false));
    }

    public function test_partner_portal_shortcuts_redirect_to_dashboard(): void
    {
        $user = $this->makePartnerUser();

        $this->actingAs($user)
            ->get('/partner-portal')
            ->assertRedirect(route('site.partner.dashboard'));

        $this->actingAs($user)
            ->get('/vendor-portal')
            ->assertRedirect(route('site.partner.dashboard'));
    }

    public function test_authenticated_partner_dashboard_loads_at_partner_url(): void
    {
        $user = $this->makePartnerUser();

        $this->actingAs($user)
            ->get(route('site.partner.dashboard'))
            ->assertOk()
            ->assertSee(__('site.partner_portal.dashboard_title'), false);
    }

    public function test_legacy_vendor_dashboard_still_loads(): void
    {
        $user = $this->makePartnerUser();

        $this->actingAs($user)
            ->get(route('site.vendor.dashboard'))
            ->assertOk()
            ->assertSee(__('site.partner_portal.dashboard_title'), false);
    }

    public function test_register_partner_alias_points_to_vendor_registration(): void
    {
        $this->get('/register/partner')
            ->assertRedirect(route('site.register.vendor'));
    }

    public function test_settings_hub_landing_page_lists_grouped_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Settings hub', false)
            ->assertSee('Organization', false)
            ->assertSee('Partners', false)
            ->assertSee('Growth', false)
            ->assertSee('Communications', false);
    }

    private function makePartnerUser(): User
    {
        $user = User::factory()->create(['role' => 'vendor']);
        Vendor::create([
            'user_id' => $user->id,
            'vendor_number' => 'PTR-P8-001',
            'name' => 'Phase 8 Partner',
            'category' => 'gps',
            'status' => 'active',
        ]);

        return $user;
    }
}
