<?php

namespace Tests\Feature;

use Tests\TestCase;

class AffiliateOwnerUatUxPassFeatureTest extends TestCase
{
    public function test_affiliate_profile_hero_owns_completion_cta(): void
    {
        $shell = file_get_contents(resource_path('views/site/partner-account/_shell.blade.php'));
        $hero = file_get_contents(resource_path('views/components/site/account-shell-hero.blade.php'));

        $this->assertStringContainsString("\$portal === 'affiliate'", $shell);
        $this->assertStringContainsString('remainingItemCount', $shell);
        $this->assertStringContainsString('firstIncompleteSection', $shell);
        $this->assertStringContainsString('hero_completion_cta', $shell);
        $this->assertStringContainsString('remainingCount', $hero);
        $this->assertStringContainsString('remaining_count', $hero);
    }

    public function test_matokeo_uses_shared_snap_rail(): void
    {
        $blade = file_get_contents(resource_path('views/site/affiliate/performance.blade.php'));

        $this->assertStringContainsString('snap-x snap-mandatory', $blade);
        $this->assertStringContainsString('data-kf-matokeo-rail', $blade);
        $this->assertStringContainsString('lg:grid-cols-5', $blade);
        $this->assertStringContainsString('x-teleport="body"', $blade);
        $this->assertStringContainsString('x-site.bottom-sheet', $blade);
    }

    public function test_share_promo_is_inline_and_uses_invitation_builder(): void
    {
        $share = file_get_contents(resource_path('views/site/affiliate/share.blade.php'));
        $service = file_get_contents(app_path('Services/AffiliateService.php'));
        $presenter = file_get_contents(app_path('Services/AffiliatePortalPresenter.php'));

        $this->assertStringNotContainsString('window.confirmForm($el', $share);
        $this->assertStringContainsString('affiliatePromoEditor', $share);
        $this->assertStringContainsString('@blur="check()"', $share);
        $this->assertStringContainsString(':hero="false"', $share);
        $this->assertStringContainsString('function shareInvitation', $service);
        $this->assertStringContainsString('configuredMemberBenefit', $service);
        $this->assertStringContainsString('shareInvitation($vendor)', $presenter);
        $this->assertStringContainsString('hasPayoutAccount', $presenter);
        $this->assertStringContainsString('profileCompletionUrl', $presenter);
    }

    public function test_wallet_checks_payment_account_and_styles_close(): void
    {
        $wallet = file_get_contents(resource_path('views/site/affiliate/wallet.blade.php'));

        $this->assertStringContainsString('hasPayoutAccount', $wallet);
        $this->assertStringContainsString("section' => 'payment'", $wallet);
        $this->assertStringContainsString('withdraw_need_account_title', $wallet);
        $this->assertStringContainsString('rounded-xl ring-1 ring-gray-200', $wallet);
        $this->assertStringContainsString(':hero="false"', $wallet);
    }

    public function test_welcome_notification_is_role_specific(): void
    {
        $welcome = file_get_contents(app_path('Services/PartnerWelcomeService.php'));

        $this->assertStringContainsString("'supplier'", $welcome);
        $this->assertStringContainsString("'insurance'", $welcome);
        $this->assertStringContainsString('notify_welcome_body', $welcome);
        $this->assertStringContainsString('partner_welcome_sent_at', $welcome);
        $this->assertStringContainsString("route('site.affiliate.share')", $welcome);
        $this->assertStringContainsString("route('site.supplier.dashboard')", $welcome);
    }
}
