<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Services\AccountWelcomeService;
use App\Services\KopafastaLaunchService;
use App\Services\PartnerPortalNavService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPortalBorrowerReuseTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Vendor} */
    private function supplier(array $overrides = []): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create(array_merge([
            'user_id' => $user->id,
            'vendor_number' => 'PTR-SUP-'.random_int(1000, 9999),
            'name' => 'MacLeans Autotraders',
            'category' => 'supplier',
            'status' => 'active',
            'activated_at' => now(),
            'phone' => '255713'.random_int(100000, 999999),
            'email' => 'supplier-reuse-'.random_int(100, 999).'@example.com',
        ], $overrides));

        return [$user, $vendor];
    }

    public function test_first_login_shows_supplier_welcome_before_dashboard(): void
    {
        $user = User::factory()->needsWelcome()->create(['role' => 'vendor']);
        Vendor::create([
            'user_id' => $user->id,
            'vendor_number' => 'PTR-SUP-WEL-1',
            'name' => 'MacLeans Autotraders',
            'category' => 'supplier',
            'status' => 'active',
            'activated_at' => now(),
            'phone' => '255713111222',
        ]);

        $this->assertSame('supplier', app(AccountWelcomeService::class)->audienceFor($user));

        $this->actingAs($user)
            ->get(route('site.supplier.dashboard'))
            ->assertRedirect(route('site.account-welcome.show'));

        $this->actingAs($user)
            ->get(route('site.account-welcome.show'))
            ->assertOk()
            ->assertSee(__('account_welcome.supplier.welcome_title'), false)
            ->assertSee(__('account_welcome.supplier.assets_title'), false)
            ->assertSee(__('account_welcome.skip'), false)
            ->assertSee(__('account_welcome.finish'), false)
            ->assertDontSee(__('account_welcome.valuer.welcome_title'), false)
            ->assertDontSee('kf-chrome-page', false);
    }

    public function test_supplier_welcome_skips_launcher_and_reuses_borrower_component(): void
    {
        $user = User::factory()->needsWelcome()->create(['role' => 'vendor']);
        Vendor::create([
            'user_id' => $user->id,
            'vendor_number' => 'PTR-SUP-WEL-2',
            'name' => 'MacLeans Autotraders',
            'category' => 'supplier',
            'status' => 'active',
            'activated_at' => now(),
            'phone' => '255713111223',
        ]);

        $this->actingAs($user)
            ->post(route('site.account-welcome.complete'), ['audience' => 'supplier'])
            ->assertRedirect(route('site.supplier.dashboard'));

        $this->assertFalse(app(KopafastaLaunchService::class)->pending());
        $this->assertNull(app(AccountWelcomeService::class)->forUser($user->fresh()));
    }

    public function test_dashboard_is_operational_and_nav_is_simplified(): void
    {
        [$user] = $this->supplier();

        $html = $this->actingAs($user)
            ->get(route('site.supplier.dashboard'))
            ->assertOk()
            ->assertSee(__('site.supplier_portal.stat_assets'), false)
            ->assertSee(__('site.supplier_portal.stat_active_financed'), false)
            ->assertSee(__('site.supplier_portal.stat_available'), false)
            ->assertSee(__('site.supplier_portal.stat_pending'), false)
            ->assertSee(__('site.supplier_portal.quick_upload'), false)
            ->assertSee(__('site.supplier_portal.quick_requests'), false)
            ->assertSee(__('site.supplier_portal.recent_payments_title'), false)
            ->assertSee(__('site.supplier_portal.asset_activity_title'), false)
            ->assertSee(__('site.supplier_portal.nav_home'), false)
            ->assertSee(__('site.supplier_portal.nav_money'), false)
            ->assertDontSee('Expected payouts', false)
            ->assertDontSee('What you can expect from loans', false)
            ->getContent();

        $this->assertStringContainsString('kf-mobile-bottom-nav', $html);

        $nav = collect(app(PartnerPortalNavService::class)->supplierNav())->pluck('key')->all();
        $this->assertSame(['dashboard', 'assets', 'requests', 'settlements', 'profile'], $nav);
    }

    public function test_reservations_and_expected_payouts_redirect_into_workspaces(): void
    {
        [$user] = $this->supplier();

        $this->actingAs($user)
            ->get(route('site.supplier.reservations'))
            ->assertRedirect(route('site.supplier.requests'));

        $this->actingAs($user)
            ->get(route('site.supplier.applications'))
            ->assertRedirect(route('site.supplier.settlements'));

        $this->actingAs($user)
            ->get(route('site.supplier.settlements'))
            ->assertOk()
            ->assertSee(__('site.supplier_portal.money_available'), false)
            ->assertSee(__('site.supplier_portal.money_pending'), false)
            ->assertDontSee('not the full underwriting', false);
    }

    public function test_profile_uses_chips_and_card_shows_active_account(): void
    {
        [$user] = $this->supplier();

        $this->actingAs($user)
            ->get(route('site.supplier.profile'))
            ->assertOk()
            ->assertSee(__('site.supplier_portal.tab_overview'), false)
            ->assertSee(__('site.supplier_portal.tab_contact'), false)
            ->assertSee(__('site.supplier_portal.tab_payment'), false)
            ->assertSee(__('site.supplier_portal.nav_card'), false);

        $this->actingAs($user)
            ->get(route('site.supplier.profile', ['section' => 'card']))
            ->assertOk()
            ->assertSee(__('site.supplier_portal.card_title'), false)
            ->assertSee(__('site.card_verify.status.active'), false)
            ->assertDontSee(__('site.card_verify.status.inactive'), false);

        $this->actingAs($user)
            ->get(route('site.supplier.profile', ['section' => 'payment']))
            ->assertOk()
            ->assertSee(__('site.partner_account.payment_section'), false);

        $this->actingAs($user)
            ->get(route('site.supplier.settings'))
            ->assertOk()
            ->assertSee(__('site.partner_account.settings_locale'), false)
            ->assertSee(__('borrower.security_tab.change_pin'), false);
    }

    public function test_add_asset_uses_existing_wizard(): void
    {
        [$user] = $this->supplier();

        $this->actingAs($user)
            ->get(route('site.supplier.assets.create'))
            ->assertOk()
            ->assertSee('admin-wizard', false)
            ->assertSee(__('site.supplier_portal.wizard_type'), false)
            ->assertSee(__('site.supplier_portal.wizard_review'), false)
            ->assertSee(__('site.supplier_portal.wizard_publish'), false);
    }

    public function test_canonical_nida_is_reused_on_profile(): void
    {
        [$user, $vendor] = $this->supplier();
        $vendor->forceFill([
            'metadata' => [
                'identity' => ['national_id' => '19900101123456789012'],
            ],
        ])->save();

        $this->actingAs($user)
            ->get(route('site.supplier.profile', ['section' => 'personal']))
            ->assertOk()
            ->assertSee('19900101123456789012', false);
    }
}
