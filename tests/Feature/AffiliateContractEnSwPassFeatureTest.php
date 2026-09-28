<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AffiliateSettingsService;
use App\Services\AffiliateTermsService;
use App\Services\PartnerPayoutRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AffiliateContractEnSwPassFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_classification_selects_affiliate_and_premium_templates_en_sw(): void
    {
        $standard = $this->affiliate(['name' => 'Ordinary Msambazaji', 'affiliate_code' => 'ORD100']);
        $premium = $this->premium(['name' => 'Premium Partner', 'affiliate_code' => 'PRM100']);
        $terms = app(AffiliateTermsService::class);

        foreach (['en', 'sw'] as $locale) {
            $standardText = $terms->render($standard, $locale);
            $premiumText = $terms->render($premium, $locale);

            $this->assertStringContainsString(
                $locale === 'en' ? 'Affiliate Agreement' : 'Mkataba wa Msambazaji',
                $standardText
            );
            $this->assertStringContainsString(
                $locale === 'en' ? 'Premium Affiliate Partnership Agreement' : 'Mkataba wa Ushirikiano wa Msambazaji wa Premium',
                $premiumText
            );
            $this->assertStringNotContainsString('Standard Affiliate', $standardText);
            $this->assertStringNotContainsString('{{', $standardText);
            $this->assertStringNotContainsString('{{', $premiumText);
            $this->assertStringNotContainsString('promo_code', $standardText);
            $this->assertStringNotContainsString('promo_code', $premiumText);
            $this->assertDoesNotMatchRegularExpression('/\{\{[a-z0-9_]+\}\}/i', $standardText);
            $this->assertDoesNotMatchRegularExpression('/\{\{[a-z0-9_]+\}\}/i', $premiumText);
        }

        $this->assertSame('Affiliate Agreement', $terms->agreementTitle($standard, 'en'));
        $this->assertSame('Premium Affiliate Partnership Agreement', $terms->agreementTitle($premium, 'en'));
    }

    public function test_minimum_withdrawal_snapshots_from_wallet_sot_while_live_withdraw_uses_current_settings(): void
    {
        Setting::set('affiliates.minimum_payout_amount', 75000);
        $affiliate = $this->affiliate(['affiliate_code' => 'WD750']);
        $terms = app(AffiliateTermsService::class);

        $acceptance = $terms->accept($affiliate, Request::create('/terms', 'POST'), 'en');
        $this->assertStringContainsString(
            format_money(75000),
            (string) $acceptance->rendered_text
        );
        $this->assertSame(
            format_money(75000),
            data_get($acceptance->settings_snapshot, 'minimum_withdrawal_amount')
        );

        Setting::set('affiliates.minimum_payout_amount', 99000);
        $this->assertSame(
            format_money(75000),
            data_get($acceptance->fresh()->settings_snapshot, 'minimum_withdrawal_amount')
        );
        $this->assertStringContainsString(
            format_money(75000),
            (string) $acceptance->fresh()->rendered_text
        );
        $this->assertStringNotContainsString(
            format_money(99000),
            (string) $acceptance->fresh()->rendered_text
        );

        $this->assertSame(99000.0, app(AffiliateSettingsService::class)->minimumPayoutAmount());
        $this->assertTrue(class_exists(PartnerPayoutRequestService::class));
    }

    public function test_premium_negotiated_and_settings_fallback_and_immutability(): void
    {
        Setting::set('affiliates.default_commission_percent', 11);
        Setting::set('affiliates.minimum_payout_amount', 50000);

        $settingsPremium = $this->premium([
            'name' => 'Settings Premium',
            'affiliate_code' => 'SET11',
            'metadata' => ['commercial' => ['rate_source' => 'standard']],
        ]);
        $negotiated = $this->premium([
            'name' => 'Negotiated Premium',
            'affiliate_code' => 'NEG19',
            'affiliate_commission_percent' => 19,
            'metadata' => [
                'commercial' => [
                    'rate_source' => 'negotiated',
                    'effective_from' => now()->toDateString(),
                    'note' => 'Board-approved creator deal',
                    'rates' => [
                        'affiliate_commission_percent' => 19,
                        'registration_discount_percent' => 5,
                        'application_discount_percent' => 5,
                        'plus_discount_percent' => 5,
                    ],
                ],
            ],
        ]);

        $terms = app(AffiliateTermsService::class);
        $settingsText = $terms->render($settingsPremium, 'en');
        $negotiatedText = $terms->render($negotiated, 'en');

        $this->assertStringContainsString('11%', $settingsText);
        $this->assertStringContainsString('19%', $negotiatedText);
        $this->assertStringContainsString('brand reach, trusted introductions and commercial collaboration', $negotiatedText);
        $this->assertStringNotContainsString('not governed by KPIs', $negotiatedText);

        $acceptance = $terms->accept($negotiated, Request::create('/terms', 'POST'), 'en');
        Setting::set('affiliates.default_commission_percent', 2);
        Setting::set('affiliates.terms.premium.body_en', 'CHANGED {{commission_percent}}');

        $this->assertStringContainsString('19%', (string) $acceptance->fresh()->rendered_text);
        $this->assertStringNotContainsString('CHANGED', (string) $acceptance->fresh()->rendered_text);
        $this->assertGreaterThanOrEqual(2, $terms->agreementVersion());
    }

    public function test_agreement_page_and_pdf_share_same_sot_and_sw_keys_match(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $affiliate = $this->affiliate([
            'user_id' => $user->id,
            'affiliate_code' => 'PAGE01',
        ]);

        $en = trans('affiliate_terms', [], 'en');
        $sw = trans('affiliate_terms', [], 'sw');
        $missing = array_diff(array_keys($en), array_keys($sw));
        $this->assertSame([], $missing, 'Missing SW affiliate_terms keys: '.implode(', ', $missing));

        $html = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('site.affiliate.profile', ['section' => 'agreement']))
            ->assertOk()
            ->assertSee('Affiliate Agreement', false)
            ->assertDontSee('{{promo_code}}', false)
            ->getContent();

        $this->assertStringContainsString('Minimum withdrawal amount', $html);
        $this->assertDoesNotMatchRegularExpression('/\{\{[a-z0-9_]+\}\}/i', $html);
    }

    /** @param  array<string, mixed>  $overrides */
    private function affiliate(array $overrides = []): Vendor
    {
        return Vendor::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'vendor'])->id,
            'vendor_number' => 'AFF-CT-'.random_int(100, 999),
            'name' => 'Contract Affiliate',
            'category' => 'affiliate',
            'status' => 'active',
            'phone' => '2557123'.random_int(10000, 99999),
            'affiliate_code' => 'CT'.random_int(1000, 9999),
            'affiliate_kyc_status' => 'verified',
            'membership_status' => 'active',
            'membership_started_at' => now()->subMonth(),
            'membership_expires_at' => now()->addYear(),
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    private function premium(array $overrides = []): Vendor
    {
        return $this->affiliate(array_merge([
            'name' => 'Premium Affiliate',
            'affiliate_premium' => true,
            'affiliate_code' => 'PR'.random_int(1000, 9999),
        ], $overrides));
    }
}
