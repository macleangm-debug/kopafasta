<?php

namespace Tests\Feature;

use Tests\TestCase;

class AffiliateUxProfilePassFeatureTest extends TestCase
{
    public function test_affiliate_facing_swahili_uses_matokeo_not_athari(): void
    {
        $sw = include lang_path('sw/site.php');
        $welcome = include lang_path('sw/account_welcome.php');

        $this->assertSame('Matokeo yako', data_get($sw, 'affiliate_portal.impact_title'));
        $this->assertSame('Tazama matokeo', data_get($sw, 'affiliate_portal.view_impact'));
        $this->assertSame('Matokeo', data_get($sw, 'affiliate_portal.nav_performance'));
        $this->assertSame('Fuatilia matokeo yako', data_get($welcome, 'affiliate.track_title'));
        $this->assertStringNotContainsString('Athari', (string) data_get($sw, 'affiliate_portal.impact_hero'));
    }

    public function test_shared_plus_picker_does_not_share_one_open_flag(): void
    {
        $picker = file_get_contents(resource_path('views/components/site/document-source-picker.blade.php'));

        $this->assertStringContainsString('menuOpen', $picker);
        $this->assertStringContainsString('sheetOpen', $picker);
        $this->assertStringContainsString('open="sheetOpen"', $picker);
        $this->assertStringContainsString('x-show="menuOpen"', $picker);
    }

    public function test_affiliate_nav_merges_results_and_wallet_has_one_page_hero(): void
    {
        $nav = file_get_contents(app_path('Services/PartnerPortalNavService.php'));
        $wallet = file_get_contents(resource_path('views/site/affiliate/wallet.blade.php'));
        $performance = file_get_contents(resource_path('views/site/affiliate/performance.blade.php'));
        $dashboard = file_get_contents(resource_path('views/site/affiliate/dashboard.blade.php'));
        $apply = file_get_contents(resource_path('views/site/affiliate/apply.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Site/AffiliateController.php'));

        $this->assertStringNotContainsString("'key' => 'referrals'", $nav);
        $this->assertStringContainsString("return redirect()->route('site.affiliate.performance')", $controller);
        $this->assertStringContainsString(':hero="false"', $wallet);
        $this->assertStringContainsString(':hero="false"', $performance);
        $this->assertStringContainsString('quick_actions_title', $dashboard);
        $this->assertStringContainsString(':required="true"', $apply);
        $this->assertStringContainsString('reference_contact', file_get_contents(app_path('Services/PartnerProfileService.php')));
    }
}
