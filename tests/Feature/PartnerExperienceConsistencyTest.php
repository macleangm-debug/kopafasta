<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Services\AccountWelcomeService;
use App\Services\AffiliateTermsService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PartnerExperienceConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Vendor} */
    private function affiliateUser(array $overrides = []): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $affiliate = Vendor::create(array_merge([
            'user_id' => $user->id,
            'vendor_number' => 'AFF-UX-'.random_int(100, 999),
            'name' => 'UX Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '255712349'.random_int(100, 999),
            'affiliate_code' => 'UX'.random_int(1000, 9999),
            'affiliate_kyc_status' => 'verified',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
        ], $overrides));

        return [$user, $affiliate];
    }

    public function test_face_verification_uses_single_session_branded_camera(): void
    {
        [$user] = $this->affiliateUser();

        $html = $this->actingAs($user)
            ->get(route('site.affiliate.profile', ['section' => 'face']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('valuationCamera', $html);
        $this->assertStringContainsString(__('site.partner_account.face_start'), $html);
        $this->assertStringContainsString('guideFrame', $html);
        $this->assertStringContainsString('oval', $html);
        $this->assertStringNotContainsString('single-image-document-upload', $html);
    }

    public function test_standard_performance_uses_faqs_not_action_cards(): void
    {
        [$user] = $this->affiliateUser();

        $this->actingAs($user)
            ->get(route('site.affiliate.performance'))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.faq_assessed'), false)
            ->assertSee(__('site.affiliate_portal.faq_miss_target'), false)
            ->assertSee(__('site.affiliate_portal.faq_good_standing'), false)
            ->assertSee('<details', false);
    }

    public function test_premium_performance_is_impact_without_kpi_enforcement(): void
    {
        [$user] = $this->affiliateUser([
            'affiliate_premium' => true,
            'membership_status' => null,
            'membership_started_at' => null,
            'membership_expires_at' => null,
        ]);
        app(AffiliateTermsService::class)->accept(
            Vendor::query()->where('user_id', $user->id)->firstOrFail(),
            Request::create('/terms', 'POST')
        );

        $this->actingAs($user)
            ->get(route('site.affiliate.performance'))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.impact_title'), false)
            ->assertSee(__('site.affiliate_portal.impact_hero'), false)
            ->assertDontSee(__('site.affiliate_portal.faq_miss_target'), false)
            ->assertDontSee(__('site.affiliate_portal.performance_needs_attention'), false)
            ->assertDontSee(__('site.affiliate_portal.more_needed', ['count' => 3]), false);
    }

    public function test_wallet_is_hero_withdraw_and_history(): void
    {
        [$user] = $this->affiliateUser();

        $this->actingAs($user)
            ->get(route('site.affiliate.wallet'))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.withdraw'), false)
            ->assertSee(__('site.affiliate_portal.payment_history'), false)
            ->assertDontSee(__('site.affiliate_portal.how_i_earn'), false)
            ->assertDontSee(__('site.affiliate_portal.fee_registration_fee'), false)
            ->assertDontSee(__('site.affiliate_portal.eligible_business'), false);
    }

    public function test_promo_code_is_read_only_until_edit(): void
    {
        [$user, $affiliate] = $this->affiliateUser(['affiliate_code' => 'KITONGA']);

        $html = $this->actingAs($user)
            ->get(route('site.affiliate.share'))
            ->assertOk()
            ->assertSee('KITONGA', false)
            ->assertSee(__('site.affiliate_portal.edit_code'), false)
            ->getContent();

        $this->assertStringContainsString('editing: false', $html);
        $this->assertStringContainsString('x-show="editing"', $html);
    }

    public function test_agreement_lives_on_profile_not_dashboard(): void
    {
        [$user] = $this->affiliateUser([
            'affiliate_premium' => true,
            'affiliate_code' => 'AGREE01',
            'membership_status' => null,
            'membership_started_at' => null,
            'membership_expires_at' => null,
        ]);
        $affiliate = Vendor::query()->where('user_id', $user->id)->firstOrFail();
        app(AffiliateTermsService::class)->accept($affiliate, Request::create('/terms', 'POST'));

        $this->actingAs($user)
            ->get(route('site.affiliate.dashboard'))
            ->assertOk()
            ->assertDontSee(__('site.affiliate_portal.view_agreement'), false);

        $this->actingAs($user)
            ->get(route('site.affiliate.profile', ['section' => 'agreement']))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.premium_agreement'), false);

        $this->actingAs($user)
            ->get(route('site.affiliate.settings'))
            ->assertOk()
            ->assertSee(__('site.partner_account.settings_locale_save'), false);
    }

    public function test_account_welcome_shows_once_and_is_bilingual(): void
    {
        [$user] = $this->affiliateUser();
        $user->forceFill(['preferences' => []])->save();

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.affiliate.dashboard'))
            ->assertRedirect(route('site.account-welcome.show'));

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.account-welcome.show'))
            ->assertOk()
            ->assertSee(__('account_welcome.affiliate.welcome_title', [], 'en'), false)
            ->assertSee(__('account_welcome.skip', [], 'en'), false)
            ->assertSee(__('account_welcome.finish', [], 'en'), false)
            ->assertDontSee('kf-chrome-page', false)
            ->assertDontSee('kf-mobile-bottom-nav', false);

        $this->actingAs($user)
            ->post(route('site.account-welcome.complete'), ['audience' => 'affiliate'])
            ->assertRedirect(route('site.affiliate.dashboard'));

        $this->actingAs($user->fresh())
            ->get(route('site.affiliate.dashboard'))
            ->assertOk()
            ->assertDontSee(__('account_welcome.skip', [], 'en'), false);

        $borrower = User::factory()->needsWelcome()->create(['role' => 'borrower']);
        $this->actingAs($borrower)
            ->get(route('site.borrower.dashboard'))
            ->assertRedirect(route('site.account-welcome.show'));
        $this->actingAs($borrower)
            ->get(route('site.account-welcome.show'))
            ->assertOk()
            ->assertSee(__('account_welcome.borrower.welcome_title', [], 'en'), false)
            ->assertDontSee('kf-chrome-page', false);

        $payload = app(AccountWelcomeService::class)->forUser($borrower);
        $this->assertSame('borrower', $payload['audience']);
        $this->assertSame(__('account_welcome.borrower.welcome_title', [], 'en'), $payload['cards'][0]['title']);

        app()->setLocale('sw');
        $payloadSw = app(AccountWelcomeService::class)->forUser($borrower);
        $this->assertSame(__('account_welcome.borrower.welcome_title', [], 'sw'), $payloadSw['cards'][0]['title']);
        app()->setLocale('en');
    }

    public function test_account_welcome_covers_operational_partner_audiences(): void
    {
        $cases = [
            'valuer' => ['audience' => 'valuer', 'title' => 'account_welcome.valuer.welcome_title'],
            'gps_installer' => ['audience' => 'gps_installer', 'title' => 'account_welcome.gps.welcome_title'],
            'insurance' => ['audience' => 'insurance', 'title' => 'account_welcome.insurance.welcome_title'],
            'debt_collector' => ['audience' => 'recovery', 'title' => 'account_welcome.recovery.welcome_title'],
        ];

        foreach ($cases as $category => $expected) {
            $user = User::factory()->needsWelcome()->create(['role' => 'vendor']);
            Vendor::create([
                'user_id' => $user->id,
                'vendor_number' => 'WEL-'.strtoupper(substr($category, 0, 3)).random_int(100, 999),
                'name' => $category.' partner',
                'category' => $category,
                'status' => 'active',
                'phone' => '255713'.random_int(100000, 999999),
            ]);

            $this->assertSame($expected['audience'], app(AccountWelcomeService::class)->audienceFor($user));

            $this->actingAs($user)
                ->withSession(['locale' => 'en'])
                ->get(route('site.account-welcome.show'))
                ->assertOk()
                ->assertSee(__($expected['title'], [], 'en'), false)
                ->assertDontSee('kf-chrome-page', false);
        }
    }

    public function test_lifecycle_notifications_dedupe(): void
    {
        [$user, $affiliate] = $this->affiliateUser();
        $notifications = app(NotificationService::class);

        $first = $notifications->notifyPartnerOnce($affiliate, 'affiliate_referral_new', [
            '_fallback_subject' => 'New referral',
            '_fallback_body' => 'A customer registered.',
        ], null, 'reg:1');
        $second = $notifications->notifyPartnerOnce($affiliate->fresh(), 'affiliate_referral_new', [
            '_fallback_subject' => 'New referral',
            '_fallback_body' => 'A customer registered.',
        ], null, 'reg:1');

        $this->assertNotNull($first);
        $this->assertNull($second);
    }

    public function test_account_welcome_config_has_en_sw_parity(): void
    {
        $en = trans('account_welcome', [], 'en');
        $sw = trans('account_welcome', [], 'sw');
        $this->assertIsArray($en);
        $this->assertIsArray($sw);

        $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
            $keys = [];
            foreach ($items as $key => $value) {
                $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
                if (is_array($value)) {
                    $keys = array_merge($keys, $flatten($value, $path));
                } else {
                    $keys[] = $path;
                }
            }

            return $keys;
        };

        $this->assertSame([], array_values(array_diff($flatten($en), $flatten($sw))));
        $this->assertNotNull(app(AccountWelcomeService::class)->audienceFor(User::factory()->create(['role' => 'borrower'])));
    }
}
