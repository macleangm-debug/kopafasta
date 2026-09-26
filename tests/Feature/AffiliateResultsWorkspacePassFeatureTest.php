<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateTermsService;
use App\Services\PartnerPortalNavService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AffiliateResultsWorkspacePassFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_nav_is_home_performance_share_reports_profile(): void
    {
        $nav = app(PartnerPortalNavService::class)->affiliateNav();

        $this->assertSame(['dashboard', 'performance', 'share', 'reports', 'profile'], array_column($nav, 'key'));
        $this->assertCount(5, $nav);

        $mobile = app(PartnerPortalNavService::class)->mobilePrimaryNav($nav);
        $this->assertSame(['dashboard', 'performance', 'share', 'reports', 'profile'], array_column($mobile, 'key'));
        $this->assertCount(5, $mobile);
    }

    public function test_wallet_route_redirects_to_results_commissions(): void
    {
        $affiliate = $this->affiliate();

        $this->actingAs($affiliate->user)
            ->get(route('site.affiliate.wallet'))
            ->assertRedirect(route('site.affiliate.performance', ['tab' => 'commissions']));

        $html = $this->actingAs($affiliate->user)
            ->get(route('site.affiliate.results', ['tab' => 'commissions']))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.tab_overview'), false)
            ->assertSee(__('site.affiliate_portal.tab_commissions'), false)
            ->assertSee(__('site.affiliate_portal.tab_withdrawals'), false)
            ->assertSee(__('site.affiliate_portal.figure_available'), false)
            ->getContent();

        $this->assertStringNotContainsString(__('site.affiliate_portal.nav_wallet'), $html);
    }

    public function test_dashboard_balance_opens_results_withdrawals(): void
    {
        $affiliate = $this->affiliate();
        $dashboard = file_get_contents(resource_path('views/site/affiliate/dashboard.blade.php'));

        $this->assertStringContainsString("['tab' => 'withdrawals'", $dashboard);
        $this->assertStringContainsString("['tab' => 'overview']", $dashboard);
        $this->assertStringContainsString("['tab' => 'commissions']", $dashboard);
        $this->assertStringNotContainsString("route('site.affiliate.wallet')", $dashboard);

        $this->actingAs($affiliate->user)
            ->get(route('site.affiliate.dashboard'))
            ->assertOk()
            ->assertSee(route('site.affiliate.performance', ['tab' => 'overview']), false);
    }

    private function affiliate(): Vendor
    {
        $affiliate = Vendor::create([
            'user_id' => User::factory()->create(['role' => 'vendor'])->id,
            'vendor_number' => 'AFF-RS-'.random_int(100, 999),
            'name' => 'Results Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '2557123'.random_int(10000, 99999),
            'affiliate_code' => 'RS'.random_int(1000, 9999),
            'affiliate_kyc_status' => 'verified',
            'affiliate_lifecycle_status' => 'active',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
        ]);
        app(AffiliateTermsService::class)->accept($affiliate, Request::create('/terms', 'POST'));

        return $affiliate->fresh();
    }
}
