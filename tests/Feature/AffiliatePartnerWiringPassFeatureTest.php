<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateEligibilityService;
use App\Services\AffiliateSettingsService;
use App\Services\AffiliateTermsService;
use App\Services\PartnerProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AffiliatePartnerWiringPassFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_matokeo_info_uses_portal_popover_and_bottom_sheet(): void
    {
        $blade = file_get_contents(resource_path('views/site/affiliate/performance.blade.php'));

        $this->assertStringContainsString('x-teleport="body"', $blade);
        $this->assertStringContainsString('x-site.bottom-sheet', $blade);
        $this->assertStringContainsString('bg-brand-gold', $blade);
        $this->assertStringContainsString('x-ref="infoBtn"', $blade);
        $this->assertStringContainsString('infoSheet = true', $blade);
        $this->assertStringNotContainsString('overflow-hidden">', $this->heroOpeningTag($blade));
    }

    public function test_residence_card_uses_canonical_completion_including_street(): void
    {
        [$user, $affiliate] = $this->affiliateUser([
            'metadata' => [
                'residence' => [
                    'region' => 'Dar es Salaam',
                    'district' => 'Ilala',
                ],
            ],
        ]);
        $profile = app(PartnerProfileService::class);

        $this->assertFalse($profile->sectionStatus($affiliate, 'residence')['complete']);
        $this->assertContains('street', array_column($profile->sectionGaps($affiliate, 'residence'), 'key'));

        $html = $this->actingAs($user)
            ->get(route('site.affiliate.profile', ['section' => 'residence']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('site.partner_account.street'), $html);
        $this->assertStringContainsString(__('site.partner_account.residence_section'), $html);
        $this->assertStringContainsString('data-kf-remaining-list', $html);
        $this->assertStringContainsString(
            trans_choice('borrower.profile.hub.remaining_count', 1, ['count' => 1]),
            $html
        );
        $blade = file_get_contents(resource_path('views/site/partner-account/residence.blade.php'));
        $this->assertStringContainsString("sectionStatus(\$partner, 'residence')", $blade);
        $this->assertStringContainsString(':complete="$residenceComplete"', $blade);

        $meta = $affiliate->metadata ?? [];
        $meta['residence']['street'] = 'Mtaa kamili 12';
        $affiliate->update(['metadata' => $meta]);

        $this->assertTrue($profile->sectionStatus($affiliate->fresh(), 'residence')['complete']);
    }

    public function test_desktop_account_opens_dropdown_and_mobile_keeps_profile_link(): void
    {
        $shell = file_get_contents(resource_path('views/components/site/partner-shell.blade.php'));

        $this->assertStringContainsString('profileOpen', $shell);
        $this->assertStringContainsString('kf-chrome-topbar-desktop', $shell);
        $this->assertStringContainsString('kf-chrome-topbar-mobile', $shell);
        $this->assertMatchesRegularExpression(
            '/kf-chrome-topbar-mobile[\s\S]+href="\{\{ \$profileHubHref \}\}"/',
            $shell
        );
        $this->assertStringContainsString("profileOpen = !profileOpen", $shell);
        $this->assertStringContainsString("borrower.layout.sign_out", $shell);

        [$user] = $this->affiliateUser();
        $html = $this->actingAs($user)
            ->get(route('site.affiliate.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('profileOpen', $html);
        $this->assertStringContainsString(__('site.partner_portal.nav_profile'), $html);
        $this->assertStringContainsString(__('site.card_verify.my_card_title'), $html);
        $this->assertStringContainsString(route('site.affiliate.settings'), $html);
        $this->assertStringContainsString(__('borrower.layout.sign_out'), $html);
        $this->assertMatchesRegularExpression(
            '/kf-chrome-topbar-mobile[\s\S]+href="'.preg_quote(e(route('site.affiliate.profile')), '/').'/',
            $html
        );
    }

    public function test_agreement_stays_on_profile_after_acceptance(): void
    {
        [$standardUser, $standard] = $this->affiliateUser();
        [$premiumUser, $premium] = $this->affiliateUser([
            'affiliate_premium' => true,
            'affiliate_code' => 'PREM24X',
            'membership_status' => null,
            'membership_started_at' => null,
            'membership_expires_at' => null,
        ]);
        $profile = app(PartnerProfileService::class);

        $standardKeys = array_column($profile->hubCards($standard, 'site.affiliate.profile'), 'key');
        $this->assertContains('agreement', $standardKeys);
        $this->assertContains('membership', $standardKeys);

        $premiumKeys = array_column($profile->hubCards($premium, 'site.affiliate.profile'), 'key');
        $this->assertContains('agreement', $premiumKeys);
        $this->assertNotContains('membership', $premiumKeys);

        app(AffiliateTermsService::class)->accept($standard, Request::create('/terms', 'POST'));
        app(AffiliateTermsService::class)->accept($premium, Request::create('/terms', 'POST'));

        $this->actingAs($standardUser)
            ->get(route('site.affiliate.profile'))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.agreement_terms_section'), false)
            ->assertSee(route('site.affiliate.profile', ['section' => 'agreement']), false);

        $accepted = $this->actingAs($premiumUser)
            ->get(route('site.affiliate.profile', ['section' => 'agreement']))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.agreement_terms_section'), false)
            ->assertSee(__('site.affiliate_portal.premium_agreement'), false)
            ->assertSee(__('site.affiliate_portal.agreement_status_accepted'), false)
            ->assertSee(__('site.affiliate_portal.view_agreement'), false)
            ->getContent();

        $this->assertStringContainsString('v', $accepted);
        $this->assertSame(24, app(AffiliateSettingsService::class)->premiumContractDurationMonths());
    }

    public function test_account_active_stays_separate_from_terms_gate(): void
    {
        [, $affiliate] = $this->affiliateUser([
            'membership_status' => 'inactive',
            'membership_started_at' => null,
            'membership_expires_at' => null,
        ]);
        $eligibility = app(AffiliateEligibilityService::class)->for($affiliate);

        $this->assertSame('active', $eligibility['account']);
        $this->assertContains('terms_unaccepted', $eligibility['reasons']);
        $this->assertFalse($eligibility['can_share']);

        app(AffiliateTermsService::class)->accept($affiliate, Request::create('/terms', 'POST'));
        $after = app(AffiliateEligibilityService::class)->for($affiliate->fresh());

        $this->assertSame('active', $after['account']);
        $this->assertNotContains('terms_unaccepted', $after['reasons']);
    }

    public function test_supplier_swahili_uses_mtoa_bidhaa_not_msambazaji(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        Vendor::create([
            'user_id' => $user->id,
            'vendor_number' => 'PTR-SUP-SW-1',
            'name' => 'Supplier Label',
            'category' => 'supplier',
            'status' => 'active',
            'activated_at' => now(),
            'phone' => '255713000111',
        ]);

        $html = $this->actingAs($user)
            ->withSession(['locale' => 'sw', 'country' => 'TZ'])
            ->get(route('site.supplier.dashboard'))
            ->assertOk()
            ->assertSee(__('site.supplier_portal.title', [], 'sw'), false)
            ->assertDontSee('Portal ya msambazaji', false)
            ->assertDontSee('Portal ya Msambazaji', false)
            ->getContent();

        $this->assertStringContainsString('mtoa bidhaa', mb_strtolower($html));
        $this->assertStringNotContainsString('msambazaji', mb_strtolower($html));
        $this->assertSame('Mtoa bidhaa', __('site.card_verify.roles.supplier', [], 'sw'));
        $this->assertSame('Msambazaji', __('site.affiliate_portal.doc_affiliate', [], 'sw'));
    }

    /** @return array{0: User, 1: Vendor} */
    private function affiliateUser(array $overrides = []): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $affiliate = Vendor::create(array_merge([
            'user_id' => $user->id,
            'vendor_number' => 'AFF-WIRE-'.random_int(100, 999),
            'name' => 'Wiring Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '255712347'.random_int(100, 999),
            'affiliate_code' => 'WIRE'.random_int(1000, 9999),
            'affiliate_kyc_status' => 'verified',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
        ], $overrides));

        return [$user, $affiliate];
    }

    private function heroOpeningTag(string $blade): string
    {
        $this->assertMatchesRegularExpression('/<section class="relative mb-6"/', $blade);

        return '<section class="relative mb-6">';
    }
}
