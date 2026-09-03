<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateAttributionService;
use App\Services\AffiliateMembershipService;
use App\Services\AffiliateSettingsService;
use App\Services\AffiliateTermsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AffiliatePortalOverhaulFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function affiliateUser(array $overrides = []): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $affiliate = Vendor::create(array_merge([
            'user_id' => $user->id,
            'vendor_number' => 'AFF-PORT-'.random_int(100, 999),
            'name' => 'Portal Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '255712341'.random_int(100, 999),
            'affiliate_code' => 'PORT'.random_int(100, 999),
            'affiliate_kyc_status' => 'verified',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
        ], $overrides));

        return [$user, $affiliate];
    }

    public function test_premium_affiliate_skips_membership_pay_and_gets_agreement(): void
    {
        [$user, $affiliate] = $this->affiliateUser([
            'affiliate_premium' => true,
            'affiliate_code' => 'PREM001',
            'membership_status' => null,
            'membership_started_at' => null,
            'membership_expires_at' => null,
        ]);

        app(AffiliateTermsService::class)->accept($affiliate, Request::create('/terms', 'POST'));
        $affiliate = $affiliate->fresh();

        $this->assertSame(24, app(AffiliateSettingsService::class)->premiumContractDurationMonths());
        $this->assertTrue(app(AffiliateMembershipService::class)->hasValidAgreement($affiliate));
        $this->assertSame(0.0, app(AffiliateMembershipService::class)->feeFor($affiliate));

        $this->actingAs($user)
            ->get(route('site.affiliate.membership.pay'))
            ->assertRedirect(route('site.affiliate.agreement'));

        $this->actingAs($user)
            ->get(route('site.affiliate.dashboard'))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.hero_available'), false)
            ->assertDontSee(__('site.affiliate_portal.membership_pay'), false);
    }

    public function test_referral_link_establishes_claim_and_auto_applies_promo(): void
    {
        [$user, $affiliate] = $this->affiliateUser(['affiliate_code' => 'LINK001']);

        $this->get('/aff/LINK001')
            ->assertRedirect()
            ->assertSessionHas(AffiliateAttributionService::CLAIM_SESSION_KEY);

        $customer = Customer::create([
            'customer_number' => 'C-LINK-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Link',
            'last_name' => 'Borrower',
            'phone' => '+255700222333',
        ]);

        app(\App\Services\AffiliateService::class)->attachAffiliate($customer, null);
        $customer = $customer->fresh();

        $this->assertSame($affiliate->id, $customer->affiliate_vendor_id);

        $quote = app(\App\Services\PaymentGateService::class)->quote(
            $customer,
            10000,
            'application_fee',
            false,
            null,
            null,
        );

        $this->assertTrue($quote['has_affiliate']);
        $this->assertTrue($quote['promo_valid']);
        $this->assertSame('LINK001', $quote['promo_code']);
    }

    public function test_promo_code_change_preserves_customer_attribution(): void
    {
        Setting::set('affiliates.promo_code', [
            'affiliate_can_edit' => true,
            'min_length' => 3,
            'max_length' => 24,
            'allowed_pattern' => 'A-Z0-9_-',
            'change_cooldown_days' => 0,
            'old_code_grace_days' => 14,
            'reserved' => ['ADMIN'],
        ]);

        [$user, $affiliate] = $this->affiliateUser(['affiliate_code' => 'OLD001']);
        $customer = Customer::create([
            'customer_number' => 'C-OLD-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Old',
            'last_name' => 'Code',
            'phone' => '+255700333444',
            'affiliate_vendor_id' => $affiliate->id,
        ]);

        app(\App\Services\AffiliateService::class)->updateCode($affiliate, 'NEW001');
        $affiliate = $affiliate->fresh();

        $this->assertSame('NEW001', $affiliate->affiliate_code);
        $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);
        $this->assertNotNull(app(\App\Services\AffiliateService::class)->resolveByPublicCode('OLD001'));
    }

    public function test_affiliate_translation_keys_have_full_sw_parity(): void
    {
        $enPortal = trans('site.affiliate_portal', [], 'en');
        $swPortal = trans('site.affiliate_portal', [], 'sw');
        $this->assertIsArray($enPortal);
        $this->assertIsArray($swPortal);

        $missingPortal = array_values(array_diff(array_keys($enPortal), array_keys($swPortal)));
        $this->assertSame([], $missingPortal, 'Missing SW affiliate_portal keys: '.implode(', ', $missingPortal));

        $enTerms = trans('affiliate_terms', [], 'en');
        $swTerms = trans('affiliate_terms', [], 'sw');
        $missingTerms = array_values(array_diff(array_keys($enTerms), array_keys($swTerms)));
        $this->assertSame([], $missingTerms, 'Missing SW affiliate_terms keys: '.implode(', ', $missingTerms));

        $this->assertStringNotContainsString('2-year', trans('affiliate_terms.contract_months', ['count' => 24], 'en'));
        $this->assertStringContainsString('24-month', trans('affiliate_terms.contract_months', ['count' => 24], 'en'));
    }

    public function test_standard_and_premium_portals_render_in_en_and_sw(): void
    {
        [$standardUser, $standard] = $this->affiliateUser(['affiliate_code' => 'STDWALK1', 'name' => 'Said Standard']);
        app(AffiliateTermsService::class)->accept($standard, Request::create('/terms', 'POST'));

        [$premiumUser, $premium] = $this->affiliateUser([
            'affiliate_code' => 'PREMWALK',
            'name' => 'Amina Premium',
            'affiliate_premium' => true,
            'membership_status' => null,
            'membership_started_at' => null,
            'membership_expires_at' => null,
        ]);
        app(AffiliateTermsService::class)->accept($premium, Request::create('/terms', 'POST'));

        $routes = [
            'site.affiliate.dashboard',
            'site.affiliate.share',
            'site.affiliate.referrals',
            'site.affiliate.wallet',
            'site.affiliate.performance',
            'site.affiliate.notifications',
            'site.affiliate.profile',
            'site.affiliate.settings',
            'site.affiliate.terms',
        ];

        foreach (['en', 'sw'] as $locale) {
            foreach ($routes as $route) {
                $html = $this->actingAs($standardUser)
                    ->withSession(['locale' => $locale, 'country' => 'TZ'])
                    ->get(route($route))
                    ->assertOk()
                    ->getContent();

                $this->assertDoesNotMatchRegularExpression('/site\.affiliate_portal\.[a-z0-9_]+/', $html, $route.' '.$locale);
                $this->assertDoesNotMatchRegularExpression('/affiliate_terms\.[a-z0-9_]+/', $html, $route.' '.$locale);
            }

            $premiumDashboard = $this->actingAs($premiumUser)
                ->withSession(['locale' => $locale, 'country' => 'TZ'])
                ->get(route('site.affiliate.dashboard'))
                ->assertOk();

            $premiumDashboard
                ->assertSee(__('site.affiliate_portal.hero_available', [], $locale), false)
                ->assertDontSee(__('site.affiliate_portal.membership_pay', [], $locale), false)
                ->assertDontSee(__('site.affiliate_portal.membership_subtitle', [], $locale), false)
                ->assertDontSee('Pay membership', false)
                ->assertDontSee('2-year', false)
                ->assertDontSee('2 year', false);

            $this->actingAs($premiumUser)
                ->withSession(['locale' => $locale, 'country' => 'TZ'])
                ->get(route('site.affiliate.agreement'))
                ->assertOk()
                ->assertSee(__('site.affiliate_portal.premium_agreement', [], $locale), false)
                ->assertDontSee(__('site.affiliate_portal.membership_pay', [], $locale), false);

            if ($locale === 'sw') {
                $this->actingAs($standardUser)
                    ->withSession(['locale' => 'sw', 'country' => 'TZ'])
                    ->get(route('site.affiliate.dashboard'))
                    ->assertOk()
                    ->assertSee('Inapatikana kutoa', false)
                    ->assertDontSee('Available to withdraw', false)
                    ->assertDontSee('Good afternoon', false)
                    ->assertDontSee('Share & Earn', false);
            }
        }
    }

    public function test_attribution_window_expiry_lock_and_settings_override(): void
    {
        $start = now()->startOfSecond();
        \Illuminate\Support\Carbon::setTestNow($start);

        try {
            [$user, $affiliate] = $this->affiliateUser(['affiliate_code' => 'WIN001']);
            $rival = $this->affiliateUser(['affiliate_code' => 'WIN002'])[1];

            $this->get('/aff/WIN001')->assertRedirect();
            $this->assertNotNull(app(AffiliateAttributionService::class)->pendingClaim());

            \Illuminate\Support\Carbon::setTestNow($start->copy()->addDays(29));
            $this->assertNotNull(app(AffiliateAttributionService::class)->pendingClaim());

            $customer = Customer::create([
                'customer_number' => 'C-WIN-1',
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'Window',
                'last_name' => 'Borrower',
                'phone' => '+255700444555',
            ]);
            app(\App\Services\AffiliateService::class)->attachAffiliate($customer, null);
            $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);

            $application = LoanApplication::create([
                'customer_id' => $customer->id,
                'loan_product_id' => LoanProduct::create([
                    'code' => 'WINPL',
                    'name' => 'Window Personal Loan',
                    'category' => 'personal',
                    'is_active' => true,
                    'interest_rate' => 0.03,
                    'min_amount' => 100_000,
                    'max_amount' => 5_000_000,
                    'tenure_min_months' => 1,
                    'tenure_max_months' => 12,
                    'application_fee_amount' => 10_000,
                ])->id,
                'application_number' => 'APP-WIN-1',
                'requested_amount' => 1_000_000,
                'requested_tenure_months' => 12,
                'status' => 'submitted',
                'current_stage' => 'submitted',
            ]);
            app(\App\Services\AffiliateService::class)->trackApplication($application);
            $this->assertTrue(app(AffiliateAttributionService::class)->isLocked($customer->fresh()));

            \Illuminate\Support\Carbon::setTestNow($start->copy()->addDays(31));
            $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);

            app(\App\Services\AffiliateService::class)->attachAffiliate($customer->fresh(), 'WIN002');
            $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);

            $expired = Customer::create([
                'customer_number' => 'C-WIN-2',
                'type' => 'individual',
                'status' => 'active',
                'first_name' => 'Expired',
                'last_name' => 'Claim',
                'phone' => '+255700444556',
            ]);
            $this->assertNull(app(AffiliateAttributionService::class)->pendingClaim());
            app(\App\Services\AffiliateService::class)->attachAffiliate($expired, null);
            $this->assertNull($expired->fresh()->affiliate_vendor_id);

            Setting::set('affiliates.attribution', array_merge(
                app(AffiliateSettingsService::class)->attributionSettings(),
                ['window_days' => 7],
            ));
            $this->assertSame(7, app(AffiliateSettingsService::class)->attributionWindowDays());

            \Illuminate\Support\Carbon::setTestNow($start->copy()->addDays(40));
            $short = $this->affiliateUser(['affiliate_code' => 'WIN007'])[1];
            $this->get('/aff/WIN007')->assertRedirect();
            \Illuminate\Support\Carbon::setTestNow($start->copy()->addDays(40)->addDays(6));
            $this->assertNotNull(app(AffiliateAttributionService::class)->pendingClaim());
            \Illuminate\Support\Carbon::setTestNow($start->copy()->addDays(40)->addDays(8));
            $this->assertNull(app(AffiliateAttributionService::class)->pendingClaim());
            $this->assertSame('WIN007', $short->affiliate_code);
            $this->assertSame('WIN002', $rival->affiliate_code);
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    public function test_settings_control_premium_duration_badge_share_and_promo_rules(): void
    {
        Setting::set('affiliates.premium', [
            'membership_required' => false,
            'contract_duration_months' => 36,
            'renewal_window_days' => 30,
            'badge_label' => 'Gold Partner',
        ]);
        Setting::set('affiliates.messages', [
            'share_template' => 'HELLO {affiliate_link}',
            'referral_sms' => 'SMS {affiliate_link}',
        ]);
        Setting::set('affiliates.messages_sw', [
            'share_template' => 'HABARI {affiliate_link}',
            'referral_sms' => 'SMS {affiliate_link}',
        ]);
        Setting::set('affiliates.promo_code', [
            'affiliate_can_edit' => true,
            'min_length' => 3,
            'max_length' => 24,
            'allowed_pattern' => 'A-Z0-9_-',
            'change_cooldown_days' => 30,
            'old_code_grace_days' => 1,
            'reserved' => ['ADMIN'],
        ]);

        $this->assertSame(36, app(AffiliateSettingsService::class)->premiumContractDurationMonths());
        $this->assertSame('Gold Partner', app(AffiliateSettingsService::class)->premiumBadgeLabel());
        $this->assertSame(30, app(AffiliateSettingsService::class)->promoChangeCooldownDays());
        $this->assertSame(1, app(AffiliateSettingsService::class)->promoOldCodeGraceDays());

        [$user, $affiliate] = $this->affiliateUser([
            'affiliate_premium' => true,
            'affiliate_code' => 'SET001',
            'membership_status' => null,
            'membership_started_at' => null,
            'membership_expires_at' => null,
        ]);
        app(AffiliateTermsService::class)->accept($affiliate, Request::create('/terms', 'POST'));
        $affiliate = $affiliate->fresh();

        $this->assertTrue($affiliate->membership_expires_at->equalTo(
            $affiliate->membership_started_at->copy()->addMonths(36)
        ));

        $this->actingAs($user)
            ->withSession(['locale' => 'en', 'country' => 'TZ'])
            ->get(route('site.affiliate.dashboard'))
            ->assertOk()
            ->assertSee('GOLD PARTNER', false)
            ->assertDontSee('2-year', false);

        $this->actingAs($user)
            ->withSession(['locale' => 'en', 'country' => 'TZ'])
            ->get(route('site.affiliate.share'))
            ->assertOk()
            ->assertSee('HELLO ', false);

        $this->actingAs($user)
            ->withSession(['locale' => 'sw', 'country' => 'TZ'])
            ->get(route('site.affiliate.share'))
            ->assertOk()
            ->assertSee('HABARI ', false)
            ->assertDontSee('HELLO ', false);

        $this->expectException(\InvalidArgumentException::class);
        app(\App\Services\AffiliateService::class)->updateCode($affiliate, 'ADMIN');
    }

    public function test_promo_cooldown_and_alias_grace_follow_settings(): void
    {
        Setting::set('affiliates.promo_code', [
            'affiliate_can_edit' => true,
            'min_length' => 3,
            'max_length' => 24,
            'allowed_pattern' => 'A-Z0-9_-',
            'change_cooldown_days' => 30,
            'old_code_grace_days' => 1,
            'reserved' => ['ADMIN'],
        ]);

        [$user, $affiliate] = $this->affiliateUser(['affiliate_code' => 'COOL001']);
        app(\App\Services\AffiliateService::class)->updateCode($affiliate, 'COOL002');
        $affiliate = $affiliate->fresh();

        $this->assertFalse(app(\App\Services\AffiliateService::class)->canChangeCode($affiliate));
        $this->assertNotNull(app(\App\Services\AffiliateService::class)->resolveByPublicCode('COOL001'));

        try {
            app(\App\Services\AffiliateService::class)->updateCode($affiliate, 'COOL003');
            $this->fail('Cooldown should block a second change');
        } catch (\InvalidArgumentException $e) {
            $this->assertNotSame('', $e->getMessage());
        }

        \Illuminate\Support\Carbon::setTestNow(now()->addDays(2));
        try {
            $this->assertNull(app(\App\Services\AffiliateService::class)->resolveByPublicCode('COOL001'));
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    public function test_premium_membership_route_redirects_to_agreement(): void
    {
        [$user, $affiliate] = $this->affiliateUser([
            'affiliate_premium' => true,
            'affiliate_code' => 'PREMNAV1',
            'membership_status' => null,
            'membership_started_at' => null,
            'membership_expires_at' => null,
        ]);
        app(AffiliateTermsService::class)->accept($affiliate, Request::create('/terms', 'POST'));

        foreach (['en', 'sw'] as $locale) {
            $session = ['locale' => $locale, 'country' => 'TZ'];

            $this->actingAs($user)
                ->withSession($session)
                ->get(route('site.affiliate.profile', ['section' => 'membership']))
                ->assertRedirect(route('site.affiliate.agreement'));

            $this->actingAs($user)
                ->withSession($session)
                ->get(route('site.affiliate.membership.pay'))
                ->assertRedirect(route('site.affiliate.agreement'));

            $pages = [
                $this->actingAs($user)->withSession($session)->get(route('site.affiliate.dashboard'))->assertOk()->getContent(),
                $this->actingAs($user)->withSession($session)->get(route('site.affiliate.share'))->assertOk()->getContent(),
                $this->actingAs($user)->withSession($session)->get(route('site.affiliate.wallet'))->assertOk()->getContent(),
                $this->actingAs($user)->withSession($session)->get(route('site.affiliate.performance'))->assertOk()->getContent(),
            ];

            foreach ($pages as $html) {
                $this->assertStringNotContainsString('Pay membership', $html);
                $this->assertStringNotContainsString('2-year', $html);
                $this->assertStringNotContainsString('2 year', $html);
                $this->assertStringNotContainsString(__('site.affiliate_portal.membership_pay', [], $locale), $html);
                $this->assertStringNotContainsString(__('site.affiliate_portal.membership_subtitle', [], $locale), $html);
                $this->assertDoesNotMatchRegularExpression('/site\.affiliate_portal\.[a-z0-9_]+/', $html);
            }

            $agreement = $this->actingAs($user)
                ->withSession($session)
                ->get(route('site.affiliate.agreement'))
                ->assertOk()
                ->assertSee(__('site.affiliate_portal.premium_agreement', [], $locale), false)
                ->assertDontSee(__('site.affiliate_portal.membership_pay', [], $locale), false)
                ->getContent();
            $this->assertStringNotContainsString('2-year', $agreement);
            $this->assertDoesNotMatchRegularExpression('/site\.affiliate_portal\.[a-z0-9_]+/', $agreement);
        }
    }

    public function test_promo_edit_http_path_keeps_affiliate_id_and_updates_links(): void
    {
        Setting::set('affiliates.promo_code', [
            'affiliate_can_edit' => true,
            'min_length' => 3,
            'max_length' => 24,
            'allowed_pattern' => 'A-Z0-9_-',
            'change_cooldown_days' => 0,
            'old_code_grace_days' => 14,
            'reserved' => ['ADMIN'],
        ]);

        [$user, $affiliate] = $this->affiliateUser(['affiliate_code' => 'HTTPOLD1']);
        $customer = Customer::create([
            'customer_number' => 'C-HTTP-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Kept',
            'last_name' => 'Referral',
            'phone' => '+255700777888',
            'affiliate_vendor_id' => $affiliate->id,
        ]);

        $this->actingAs($user)
            ->withSession(['locale' => 'en', 'country' => 'TZ'])
            ->put(route('site.affiliate.profile.update', ['section' => 'personal']), [
                'focus' => 'promo',
                'affiliate_code' => 'HTTPNEW1',
            ])
            ->assertRedirect();

        $affiliate = $affiliate->fresh();
        $this->assertSame('HTTPNEW1', $affiliate->affiliate_code);
        $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);
        $this->assertNotNull(app(\App\Services\AffiliateService::class)->resolveByPublicCode('HTTPOLD1'));

        $this->actingAs($user)
            ->withSession(['locale' => 'en', 'country' => 'TZ'])
            ->get(route('site.affiliate.share'))
            ->assertOk()
            ->assertSee('/aff/HTTPNEW1', false)
            ->assertDontSee('/aff/HTTPOLD1', false);

        $this->actingAs($user)
            ->from(route('site.affiliate.share'))
            ->put(route('site.affiliate.profile.update', ['section' => 'personal']), [
                'focus' => 'promo',
                'affiliate_code' => 'ADMIN',
            ])
            ->assertRedirect(route('site.affiliate.share'))
            ->assertSessionHasErrors('affiliate_code');
    }

    public function test_attributed_customer_quote_auto_applies_promo_without_typed_code(): void
    {
        [$user, $affiliate] = $this->affiliateUser([
            'affiliate_code' => 'AUTO001',
            'application_discount_percent' => 10,
        ]);

        $this->get('/aff/AUTO001')->assertRedirect();

        $borrower = User::factory()->create(['role' => 'borrower']);
        $customer = Customer::create([
            'user_id' => $borrower->id,
            'customer_number' => 'C-AUTO-1',
            'type' => 'individual',
            'status' => 'active',
            'first_name' => 'Auto',
            'last_name' => 'Apply',
            'phone' => '+255700888999',
            'membership_issued_at' => now(),
            'membership_expires_at' => now()->addYear(),
        ]);

        app(\App\Services\AffiliateService::class)->attachAffiliate($customer, null);
        $this->assertSame($affiliate->id, $customer->fresh()->affiliate_vendor_id);

        $product = LoanProduct::create([
            'code' => 'AUTOPL',
            'name' => 'Auto Personal Loan',
            'category' => 'personal',
            'is_active' => true,
            'interest_rate' => 0.03,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'tenure_min_months' => 1,
            'tenure_max_months' => 12,
            'application_fee_amount' => 10_000,
        ]);

        $quote = app(\App\Services\ApplicationFeePaymentService::class)->quote($customer->fresh(), $product, false, null, null, null);

        $this->assertTrue($quote['has_affiliate']);
        $this->assertTrue($quote['promo_valid']);
        $this->assertSame('AUTO001', $quote['promo_code']);
        $this->assertGreaterThan(0, (float) $quote['affiliate_discount']);
    }

    public function test_affiliate_hero_keeps_wallet_code_and_single_mobile_cta(): void
    {
        [$user] = $this->affiliateUser(['affiliate_code' => 'HERO001', 'name' => 'Hero Affiliate']);

        $html = $this->actingAs($user)
            ->withSession(['locale' => 'en', 'country' => 'TZ'])
            ->get(route('site.affiliate.dashboard'))
            ->assertOk()
            ->assertSee(__('site.affiliate_portal.hero_available', [], 'en'), false)
            ->assertSee('HERO001', false)
            ->getContent();

        $this->assertStringContainsString('hidden sm:block', $html);
        $this->assertStringContainsString('hidden sm:inline-flex', $html);
    }
}
